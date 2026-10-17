<?php
/**
 * Repository des orchidées — accès PDO/MySQL à la table `orchids`.
 *
 * Traduction serveur des anciens endpoints api/orchids/*.php (qui étaient en
 * PostgreSQL/Neon : `public.`, `to_tsquery`, `ILIKE`). Ici, tout est réécrit pour
 * MySQL 8 : table `orchids`, recherche via index FULLTEXT (ngram) + repli LIKE,
 * requêtes préparées, connexion paresseuse.
 *
 * @package App\Repository
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Repository;

use App\Entity\Orchid;
use PDO;

class OrchidRepository
{
    /** Liste des colonnes exposées (alias stable pour l'entité). */
    private const COLUMNS = 'id, name, vernacular, botanical_order, species, genre, family,
                             subfamily, tribu, subtribu, behavior, discovered, origin, img,
                             short_desc, long_desc, is_published, proposed_by';

    private ?PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    /** Connexion résolue à la demande (lazy). */
    private function pdo(): PDO
    {
        if ($this->db instanceof PDO) {
            return $this->db;
        }
        $this->db = getDb();
        return $this->db;
    }

    /**
     * Toutes les orchidées publiées, triées par nom (encyclopédie).
     *
     * @return Orchid[]
     */
    public function findAllPublished(): array
    {
        $sql  = 'SELECT ' . self::COLUMNS . ' FROM orchids WHERE is_published = 1 ORDER BY name ASC';
        $rows = $this->pdo()->query($sql)->fetchAll();
        return array_map(fn(array $r) => Orchid::fromRow($r), $rows);
    }

    /**
     * Orchidées EN ATTENTE de modération (is_published = 0), triées les plus récentes.
     *
     * Réservé au workflow de modération (admin + modérateur).
     *
     * @return Orchid[]
     */
    public function findUnpublished(): array
    {
        $sql  = 'SELECT ' . self::COLUMNS . ' FROM orchids WHERE is_published = 0 ORDER BY created_at DESC, name ASC';
        $rows = $this->pdo()->query($sql)->fetchAll();
        return array_map(fn(array $r) => Orchid::fromRow($r), $rows);
    }

    /**
     * Nombre de propositions en attente de validation (badge de modération).
     *
     * @return int
     */
    public function countUnpublished(): int
    {
        return (int) $this->pdo()->query('SELECT COUNT(*) FROM orchids WHERE is_published = 0')->fetchColumn();
    }

    /**
     * Publie (ou dépublie) une orchidée par id. Utilisée par le workflow de modération.
     *
     * @param string $id       Slug de l'espèce.
     * @param bool   $published true = publiée.
     * @return bool True si une ligne a été mise à jour.
     */
    public function setPublished(string $id, bool $published): bool
    {
        $stmt = $this->pdo()->prepare('UPDATE orchids SET is_published = :pub WHERE id = :id');
        $stmt->execute([':pub' => $published ? 1 : 0, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Recherche plein-texte (index FULLTEXT ngram) + repli LIKE sur nom/vernaculaire.
     *
     * @param string $query Terme de recherche (min. 2 caractères).
     * @return Orchid[]
     */
    public function search(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $sql = 'SELECT ' . self::COLUMNS . ',
                       MATCH(name, vernacular, short_desc, origin) AGAINST(:q IN NATURAL LANGUAGE MODE) AS score
                  FROM orchids
                 WHERE is_published = 1
                   AND ( MATCH(name, vernacular, short_desc, origin) AGAINST(:q2 IN NATURAL LANGUAGE MODE)
                         OR name LIKE :like1
                         OR vernacular LIKE :like2 )
                 ORDER BY score DESC, name ASC
                 LIMIT 30';

        $like = '%' . $query . '%';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([
            ':q'     => $query,
            ':q2'    => $query,
            ':like1' => $like,
            ':like2' => $like,
        ]);
        $rows = $stmt->fetchAll();

        return array_map(fn(array $r) => Orchid::fromRow($r), $rows);
    }

    /**
     * Une orchidée par son id (slug). Publiée uniquement si $publishedOnly.
     *
     * @param string $id            Slug de l'espèce.
     * @param bool   $publishedOnly true = n'admettre que les fiches publiées.
     * @return Orchid|null
     */
    public function findById(string $id, bool $publishedOnly = true): ?Orchid
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM orchids WHERE id = :id'
             . ($publishedOnly ? ' AND is_published = 1' : '') . ' LIMIT 1';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : Orchid::fromRow($row);
    }

    /**
     * Insère une orchidée (proposition ou création admin). Retourne l'entité.
     *
     * @param Orchid $o Entité peuplée (id doit être renseigné = slug).
     * @return Orchid
     */
    public function create(Orchid $o): Orchid
    {
        $sql = 'INSERT INTO orchids (id, name, vernacular, botanical_order, species, genre, family,
                                     subfamily, tribu, subtribu, behavior, discovered, origin, img,
                                     short_desc, long_desc, is_published, proposed_by)
                VALUES (:id, :name, :vernacular, :botanical_order, :species, :genre, :family,
                        :subfamily, :tribu, :subtribu, :behavior, :discovered, :origin, :img,
                        :short_desc, :long_desc, :is_published, :proposed_by)';
        $this->pdo()->prepare($sql)->execute($this->bind($o));
        return $o;
    }

    /**
     * Met à jour une orchidée existante (par id). Retourne l'entité.
     *
     * @param Orchid $o Entité peuplée (id = clé à mettre à jour).
     * @return Orchid
     */
    public function update(Orchid $o): Orchid
    {
        $sql = 'UPDATE orchids
                   SET name = :name, vernacular = :vernacular, botanical_order = :botanical_order,
                       species = :species, genre = :genre, family = :family, subfamily = :subfamily,
                       tribu = :tribu, subtribu = :subtribu, behavior = :behavior, discovered = :discovered,
                       origin = :origin, img = :img, short_desc = :short_desc, long_desc = :long_desc,
                       is_published = :is_published, proposed_by = :proposed_by
                 WHERE id = :id';
        $this->pdo()->prepare($sql)->execute($this->bind($o));
        return $o;
    }

    /**
     * Supprime une orchidée par id.
     *
     * @param string $id Slug.
     * @return bool True si une ligne a été supprimée.
     */
    public function delete(string $id): bool
    {
        $stmt = $this->pdo()->prepare('DELETE FROM orchids WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Un id existe-t-il déjà ? (détection de doublon à la proposition/création).
     *
     * @param string $id Slug.
     * @return bool
     */
    public function exists(string $id): bool
    {
        $stmt = $this->pdo()->prepare('SELECT 1 FROM orchids WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Prépare le tableau de binding PDO (camelCase → paramètres snake_case).
     *
     * @param Orchid $o
     * @return array
     */
    private function bind(Orchid $o): array
    {
        return [
            ':id'             => $o->id,
            ':name'           => $o->name,
            ':vernacular'     => $o->vernacular,
            ':botanical_order'=> $o->botanicalOrder,
            ':species'        => $o->species,
            ':genre'          => $o->genre,
            ':family'         => $o->family ?: 'Orchidaceae',
            ':subfamily'      => $o->subfamily,
            ':tribu'          => $o->tribu,
            ':subtribu'       => $o->subtribu,
            ':behavior'       => $o->behavior,
            ':discovered'     => $o->discovered,
            ':origin'         => $o->origin,
            ':img'            => $o->img,
            ':short_desc'     => $o->shortDesc,
            ':long_desc'      => $o->longDesc,
            ':is_published'   => $o->isPublished ? 1 : 0,
            ':proposed_by'    => $o->proposedBy,
        ];
    }
}
