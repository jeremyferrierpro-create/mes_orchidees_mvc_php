<?php
/**
 * Repository des conseils — accès PDO/MySQL à la table `conseils`.
 *
 * Traduction serveur des anciens endpoints api/conseils (PostgreSQL/Neon :
 * `public.`, `to_tsquery`, `ILIKE`). Tout est réécrit pour MySQL 8 / MariaDB :
 * table `conseils`, recherche via index FULLTEXT + repli LIKE, requêtes préparées,
 * connexion paresseuse (getDb()).
 *
 * La colonne `care_cards` (JSON natif) est encodée/décodée ici : on insère une
 * chaîne JSON (json_encode) et Conseil::fromRow la retransforme en tableau.
 *
 * @package App\Repository
 * @author  Jérémy Ferrier
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Repository;

use App\Entity\Conseil;
use PDO;

class ConseilRepository
{
    /** Liste des colonnes exposées (alias stable pour l'entité). La catégorie
     *  arrive par jointure LEFT `categories_conseil` pour préserver le comportement
     *  historique (`$c->category` peut rester null sur un conseil sans catégorie). */
    private const SELECT_BASE = 'SELECT c.id, c.type, c.name, c.img, c.content, c.care_cards, c.orchid_id,
                                       c.id_categorie, cc.label AS category
                                  FROM conseils c
                                  LEFT JOIN categories_conseil cc ON cc.id = c.id_categorie';

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
     * Tous les conseils, catégories générales d'abord puis fiches espèce.
     *
     * @return Conseil[]
     */
    public function findAll(): array
    {
        // type ASC : 'category' < 'species' → les conseils généraux remontent en premier.
        $sql  = self::SELECT_BASE . ' ORDER BY c.type ASC, c.name ASC';
        $rows = $this->pdo()->query($sql)->fetchAll();
        return array_map(fn(array $r) => Conseil::fromRow($r), $rows);
    }

    /**
     * Les conseils par type ('category' | 'species').
     *
     * @param string $type Type de conseil.
     * @return Conseil[]
     */
    public function findByType(string $type): array
    {
        $sql  = self::SELECT_BASE . ' WHERE c.type = :type ORDER BY c.name ASC';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':type' => $type]);
        return array_map(fn(array $r) => Conseil::fromRow($r), $stmt->fetchAll());
    }

    /**
     * Recherche plein-texte (index FULLTEXT sur name + content) + repli LIKE.
     *
     * Depuis la normalisation `categories_conseil`, la catégorie n'est plus une
     * colonne indexée en FULLTEXT : le repli LIKE couvre `cc.label` pour préserver
     * le comportement fonctionnel de l'interface.
     *
     * @param string $query Terme de recherche (min. 2 caractères).
     * @return Conseil[]
     */
    public function search(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $sql = 'SELECT c.id, c.type, c.name, c.img, c.content, c.care_cards, c.orchid_id,
                       c.id_categorie, cc.label AS category,
                       MATCH(c.name, c.content) AGAINST(:q IN NATURAL LANGUAGE MODE) AS score
                  FROM conseils c
                  LEFT JOIN categories_conseil cc ON cc.id = c.id_categorie
                 WHERE MATCH(c.name, c.content) AGAINST(:q2 IN NATURAL LANGUAGE MODE)
                    OR c.name LIKE :like1
                    OR c.content LIKE :like2
                    OR cc.label LIKE :like3
                 ORDER BY score DESC, c.name ASC
                 LIMIT 30';

        $like = '%' . $query . '%';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([
            ':q'     => $query,
            ':q2'    => $query,
            ':like1' => $like,
            ':like2' => $like,
            ':like3' => $like,
        ]);
        return array_map(fn(array $r) => Conseil::fromRow($r), $stmt->fetchAll());
    }

    /**
     * Un conseil par son id (slug).
     *
     * @param string $id Slug du conseil.
     * @return Conseil|null
     */
    public function findById(string $id): ?Conseil
    {
        $stmt = $this->pdo()->prepare(self::SELECT_BASE . ' WHERE c.id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : Conseil::fromRow($row);
    }

    /**
     * Insère un conseil. Retourne l'entité.
     *
     * @param Conseil $c Entité peuplée (id renseigné = slug).
     * @return Conseil
     */
    public function create(Conseil $c): Conseil
    {
        $sql = 'INSERT INTO conseils (id, type, name, img, id_categorie, content, care_cards, orchid_id)
                VALUES (:id, :type, :name, :img, :id_categorie, :content, :care_cards, :orchid_id)';
        $this->pdo()->prepare($sql)->execute($this->bind($c));
        return $c;
    }

    /**
     * Met à jour un conseil existant (par id). Retourne l'entité.
     *
     * @param Conseil $c Entité peuplée (id = clé à mettre à jour).
     * @return Conseil
     */
    public function update(Conseil $c): Conseil
    {
        $sql = 'UPDATE conseils
                   SET type = :type, name = :name, img = :img, id_categorie = :id_categorie,
                       content = :content, care_cards = :care_cards, orchid_id = :orchid_id
                 WHERE id = :id';
        $this->pdo()->prepare($sql)->execute($this->bind($c));
        return $c;
    }

    /**
     * Supprime un conseil par id.
     *
     * @param string $id Slug.
     * @return bool True si une ligne a été supprimée.
     */
    public function delete(string $id): bool
    {
        $stmt = $this->pdo()->prepare('DELETE FROM conseils WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Un id existe-t-il déjà ? (détection de doublon à la création).
     *
     * @param string $id Slug.
     * @return bool
     */
    public function exists(string $id): bool
    {
        $stmt = $this->pdo()->prepare('SELECT 1 FROM conseils WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Prépare le tableau de binding PDO (camelCase → paramètres snake_case).
     *
     * `care_cards` est réencodé en chaîne JSON (colonne JSON MySQL). Tableau vide → NULL.
     *
     * @param Conseil $c
     * @return array
     */
    private function bind(Conseil $c): array
    {
        return [
            ':id'            => $c->id,
            ':type'          => $c->type === 'species' ? 'species' : 'category',
            ':name'          => $c->name,
            ':img'           => $c->img,
            ':id_categorie'  => $c->idCategorie,
            ':content'       => $c->content,
            ':care_cards'    => $c->careCards === [] ? null : json_encode($c->careCards, JSON_UNESCAPED_UNICODE),
            ':orchid_id'     => $c->orchidId,
        ];
    }
}
