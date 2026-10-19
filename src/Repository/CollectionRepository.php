<?php
/**
 * Repository des collections — accès PDO/MySQL à la table `collections`.
 *
 * ISOLATION RG1 (rgl : « Ma Collection » est un espace STRICTEMENT privé) : chaque
 * requête, en LECTURE comme en ÉCRITURE, est plafonnée par la clause
 * `WHERE ... user_id = :uid`. Aucune ligne d'un autre propriétaire n'est jamais
 * lue, modifiée ni supprimée via ce repository. La jointure avec `orchids` enrichit
 * l'affichage (nom, image) sans jamais exposer d'autres collections.
 *
 * `id` (UUID) est généré par MySQL (DEFAULT (UUID())) : il n'est jamais lié en
 * INSERT. En UPDATE/DELETE, l'identifiant cible passe PAR le filtre `user_id`.
 *
 * @package App\Repository
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Repository;

use App\Entity\Collection;
use PDO;

class CollectionRepository
{
    /** Colonnes de collection jointes à l'espèce (alias d'affichage `orchid_*`). */
    private const SELECT_JOINED = 'SELECT c.id, c.legacy_id, c.user_id, c.orchid_id, c.location, c.notes,
                                          c.temp, c.hygro, c.light, c.ventilation, c.photo,
                                          c.rappel_frequence_jours, c.rappel_actif,
                                          c.added_at, c.updated_at,
                                          o.name AS orchid_name, o.vernacular AS orchid_vernacular, o.img AS orchid_img
                                     FROM collections c
                                     JOIN orchids o ON o.id = c.orchid_id';

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
     * Toutes les plantes de l'utilisateur (RG1 : WHERE user_id).
     *
     * @param string $userId UUID de l'utilisateur connecté.
     * @return Collection[]
     */
    public function findByUser(string $userId): array
    {
        $sql  = self::SELECT_JOINED . ' WHERE c.user_id = :uid ORDER BY c.added_at DESC, orchid_name ASC';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        return array_map(fn(array $r) => Collection::fromRow($r), $stmt->fetchAll());
    }

    /**
     * Une plante de la collection, UNIQUEMENT si elle appartient à l'utilisateur (RG1).
     *
     * @param string $id     UUID de l'entrée de collection.
     * @param string $userId UUID du propriétaire attendu.
     * @return Collection|null null si absente OU appartenant à un autre utilisateur.
     */
    public function findByIdForUser(string $id, string $userId): ?Collection
    {
        $sql  = self::SELECT_JOINED . ' WHERE c.id = :id AND c.user_id = :uid LIMIT 1';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        $row = $stmt->fetch();
        return $row === false ? null : Collection::fromRow($row);
    }

    /**
     * Ajoute une plante à la collection de l'utilisateur. L'UUID est généré par MySQL.
     *
     * Le `user_id` de l'entité DOIT être celui de l'utilisateur connecté (contrôle
     * du Service/Contrôleur) : on n'insère jamais un user_id tiers.
     *
     * @param Collection $c Entité peuplée (user_id + orchid_id renseignés).
     * @return string L'id (UUID) de la ligne insérée.
     */
    public function create(Collection $c): string
    {
        // On génère l'UUID en PHP (v4) et on l'insère explicitement : ainsi l'id
        // retourné correspond VRAIMENT à la ligne créée (le DEFAULT (UUID()) de MySQL
        // produirait une valeur différente, non récupérable pour une colonne CHAR).
        $id    = $this->uuid();
        $c->id = $id;

        $sql = 'INSERT INTO collections (id, user_id, orchid_id, location, notes, temp, hygro, light, ventilation, photo, rappel_frequence_jours, rappel_actif)
                VALUES (:id, :user_id, :orchid_id, :location, :notes, :temp, :hygro, :light, :ventilation, :photo, :rappel_frequence_jours, :rappel_actif)';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([
            ':id'          => $id,
            ':user_id'     => $c->userId,
            ':orchid_id'   => $c->orchidId,
            ':location'    => $c->location,
            ':notes'       => $c->notes,
            ':temp'        => $c->temp,
            ':hygro'       => $c->hygro,
            ':light'       => $c->light,
            ':ventilation' => $c->ventilation,
            ':photo'       => $c->photo,
            ':rappel_frequence_jours' => max(1, (int) $c->rappelFrequenceJours),
            ':rappel_actif' => $c->rappelActif ? 1 : 0,
        ]);

        return $id;
    }

    /**
     * Fabrique un UUID v4 (RFC 4122) en PHP pur (16 octets aléatoires + bits de version).
     *
     * @return string UUID au format 8-4-4-4-12.
     */
    private function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40); // version 4
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80); // variante RFC 4122
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    /**
     * Met à jour une entrée, UNIQUEMENT si elle appartient à l'utilisateur (RG1).
     *
     * @param Collection $c      Entité peuplée (id = clé à mettre à jour).
     * @param string     $userId Propriétaire attendu.
     * @return bool True si une ligne du bon propriétaire a été modifiée.
     */
    public function updateForUser(Collection $c, string $userId): bool
    {
        $sql = 'UPDATE collections
                   SET orchid_id = :orchid_id, location = :location, notes = :notes,
                       temp = :temp, hygro = :hygro, light = :light, ventilation = :ventilation,
                       photo = :photo, rappel_frequence_jours = :rappel_frequence_jours,
                       rappel_actif = :rappel_actif
                 WHERE id = :id AND user_id = :uid';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([
            ':orchid_id'   => $c->orchidId,
            ':location'    => $c->location,
            ':notes'       => $c->notes,
            ':temp'        => $c->temp,
            ':hygro'       => $c->hygro,
            ':light'       => $c->light,
            ':ventilation' => $c->ventilation,
            ':photo'       => $c->photo,
            ':rappel_frequence_jours' => max(1, (int) $c->rappelFrequenceJours),
            ':rappel_actif' => $c->rappelActif ? 1 : 0,
            ':id'          => $c->id,
            ':uid'         => $userId,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Bascule UNIQUEMENT l'opt-in du rappel d'une plante (RG1 : WHERE user_id).
     *
     * Methode volontairement ciblee (et non un updateForUser complet) pour que
     * l'enregistrement d'un soin puisse activer/desactiver le rappel sans toucher
     * aux autres champs de culture. Ne supprime aucune donnee : seul le drapeau
     * `rappel_actif` est ecrit.
     *
     * @param string $id     UUID de l'entree de collection.
     * @param string $userId Proprietaire attendu.
     * @param bool   $actif  Etat souhaite (true = rappels actifs).
     * @return bool True si la ligne du bon proprietaire a ete modifiee.
     */
    public function setRappelActifForUser(string $id, string $userId, bool $actif): bool
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE collections SET rappel_actif = :actif WHERE id = :id AND user_id = :uid'
        );
        $stmt->execute([':actif' => $actif ? 1 : 0, ':id' => $id, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Supprime une entrée, UNIQUEMENT si elle appartient à l'utilisateur (RG1).
     *
     * @param string $id     UUID de l'entrée.
     * @param string $userId Propriétaire attendu.
     * @return bool True si une ligne du bon propriétaire a été supprimée.
     */
    public function deleteForUser(string $id, string $userId): bool
    {
        $stmt = $this->pdo()->prepare('DELETE FROM collections WHERE id = :id AND user_id = :uid');
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * L'utilisateur possède-t-il déjà cette espèce ? (anti-doublon à l'ajout, RG1).
     *
     * @param string $userId Propriétaire.
     * @param string $orchidId Id de l'espèce.
     * @return bool
     */
    public function existsForUser(string $userId, string $orchidId): bool
    {
        $stmt = $this->pdo()->prepare('SELECT 1 FROM collections WHERE user_id = :uid AND orchid_id = :oid LIMIT 1');
        $stmt->execute([':uid' => $userId, ':oid' => $orchidId]);
        return $stmt->fetchColumn() !== false;
    }
}
