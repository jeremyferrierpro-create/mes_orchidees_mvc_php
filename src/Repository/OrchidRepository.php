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
                             short_desc, long_desc, is_published, moderation_status, proposed_by';

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
     * Orchidées EN ATTENTE de modération (moderation_status = 'pending'), récentes d'abord.
     *
     * Réservé au workflow de modération (admin + modérateur). Les fiches refusées
     * ('rejected') restent en base (acquises au propriétaire) mais ne sont plus
     * listées ici.
     *
     * @return Orchid[]
     */
    public function findUnpublished(): array
    {
        $sql  = 'SELECT ' . self::COLUMNS . " FROM orchids WHERE moderation_status = 'pending' ORDER BY created_at DESC, name ASC";
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
        return (int) $this->pdo()->query("SELECT COUNT(*) FROM orchids WHERE moderation_status = 'pending'")->fetchColumn();
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
     * Fixe l'état de modération d'une fiche ('pending' | 'approved' | 'rejected').
     *
     * Synchronise `is_published` : seule une fiche « approved » est publiée/visible.
     * Un refus ('rejected') conserve la ligne (elle reste acquise au propriétaire de
     * la collection qui l'a proposée) — on ne supprime plus la fiche.
     *
     * @param string $id     Slug de l'espèce.
     * @param string $status Nouvel état.
     * @return bool True si une ligne a été mise à jour.
     */
    public function setModerationStatus(string $id, string $status): bool
    {
        $allowed = ['pending', 'approved', 'rejected'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }
        $stmt = $this->pdo()->prepare('UPDATE orchids SET moderation_status = :status, is_published = :pub WHERE id = :id');
        $stmt->execute([':status' => $status, ':pub' => $status === 'approved' ? 1 : 0, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Une espèce publiée existe-t-elle pour ce nom (slug) ? Renvoie l'entité ou null.
     *
     * Sert à l'ajout libre en collection : détecte une fiche déjà publiée avant d'en
     * proposer une nouvelle.
     *
     * @param string $id Slug candidat.
     * @return Orchid|null
     */
    public function findPublishedBySlug(string $id): ?Orchid
    {
        return $this->findById($id, true);
    }

    /**
     * Liste triée et dé-dupliquée des valeurs distinctes d'une colonne botanique
     * (genre / family / behavior), pour alimenter les menus déroulants du formulaire.
     *
     * La colonne est validée par une liste blanche : aucune injection possible.
     *
     * @param string $column Nom de colonne autorisé ('genre' | 'family' | 'behavior').
     * @return string[]
     */
    public function distinctValues(string $column): array
    {
        $allowed = ['genre', 'family', 'behavior'];
        if (!in_array($column, $allowed, true)) {
            return [];
        }
        $sql = "SELECT DISTINCT `$column` AS v FROM orchids
                 WHERE `$column` IS NOT NULL AND `$column` <> '' ORDER BY v ASC";
        $rows = $this->pdo()->query($sql)->fetchAll();
        return array_map(static fn(array $r) => (string) $r['v'], $rows);
    }

    /**
     * Recherche plein-texte (index FULLTEXT) + repli LIKE sur tous les champs textuels.
     *
     * Le repli LIKE n'est pas une simple sécurité : sous MariaDB/InnoDB, les lignes
     * tout juste insérées ne sont pas immédiatement visibles par MATCH ... AGAINST
     * (cache FTS non vidé). Sans lui, une espèce créée ou approuvée à l'instant
     * serait introuvable par l'utilisateur.
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
                         OR vernacular LIKE :like2
                         OR short_desc LIKE :like3
                         OR origin LIKE :like4 )
                 ORDER BY score DESC, name ASC
                 LIMIT 30';

        $like = '%' . $query . '%';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([
            ':q'     => $query,
            ':q2'    => $query,
            ':like1' => $like,
            ':like2' => $like,
            ':like3' => $like,
            ':like4' => $like,
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
                                     short_desc, long_desc, is_published, moderation_status, proposed_by)
                VALUES (:id, :name, :vernacular, :botanical_order, :species, :genre, :family,
                        :subfamily, :tribu, :subtribu, :behavior, :discovered, :origin, :img,
                        :short_desc, :long_desc, :is_published, :moderation_status, :proposed_by)';
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
                       is_published = :is_published, moderation_status = :moderation_status, proposed_by = :proposed_by
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
            ':moderation_status' => $o->moderationStatus,
            ':proposed_by'    => $o->proposedBy,
        ];
    }
}
