<?php
/**
 * Repository des soins — accès PDO/MySQL à la table `soins`.
 *
 * ISOLATION RG1 : un soin n'appartient pas directement à un utilisateur mais à une
 * ENTRÉE DE COLLECTION. La propriété est donc vérifiée en JOIGNANT `collections` et
 * en filtrant `collections.user_id = :uid` sur TOUTES les requêtes (lecture, écriture,
 * suppression). Un soin rattaché à la collection d'un tiers est invisible et
 * inaltérable via ce repository.
 *
 * `id` (UUID) est généré en PHP et inséré explicitement (cohérence de l'id retourné).
 *
 * @package App\Repository
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Repository;

use App\Entity\Soin;
use PDO;

class SoinRepository
{
    /** Soins joint à leur collection (propriétaire) et à l'espèce (nom d'affichage). */
    private const SELECT_JOINED = 'SELECT s.id, s.legacy_id, s.collection_id, s.soin_date, s.type, s.notes,
                                          s.engrais, s.substrat, s.ravageurs, s.created_at,
                                          o.name AS orchid_name
                                     FROM soins s
                                     JOIN collections c ON c.id = s.collection_id
                                     LEFT JOIN orchids o ON o.id = c.orchid_id';

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
     * Tous les soins de l'utilisateur (via ses collections) — RG1.
     *
     * @param string $userId Propriétaire.
     * @return Soin[]
     */
    public function findByUser(string $userId): array
    {
        $sql  = self::SELECT_JOINED . ' WHERE c.user_id = :uid ORDER BY s.soin_date DESC, s.created_at DESC LIMIT 200';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        return array_map(fn(array $r) => Soin::fromRow($r), $stmt->fetchAll());
    }

    /**
     * Les soins d'UNE collection, UNIQUEMENT si elle appartient à l'utilisateur (RG1).
     *
     * @param string $collectionId UUID de l'entrée de collection.
     * @param string $userId       Propriétaire attendu.
     * @return Soin[]
     */
    public function findByCollectionForUser(string $collectionId, string $userId): array
    {
        $sql  = self::SELECT_JOINED . ' WHERE s.collection_id = :cid AND c.user_id = :uid ORDER BY s.soin_date DESC';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':cid' => $collectionId, ':uid' => $userId]);
        return array_map(fn(array $r) => Soin::fromRow($r), $stmt->fetchAll());
    }

    /**
     * Un soin, UNIQUEMENT s'il appartient à l'utilisateur via sa collection (RG1).
     *
     * @param string $id     UUID du soin.
     * @param string $userId Propriétaire attendu.
     * @return Soin|null
     */
    public function findByIdForUser(string $id, string $userId): ?Soin
    {
        $sql  = self::SELECT_JOINED . ' WHERE s.id = :id AND c.user_id = :uid LIMIT 1';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        $row = $stmt->fetch();
        return $row === false ? null : Soin::fromRow($row);
    }

    /**
     * La collection cible appartient-elle bien à l'utilisateur ? (garde d'ajout, RG1).
     *
     * @param string $collectionId UUID de la collection.
     * @param string $userId       Propriétaire attendu.
     * @return bool
     */
    public function collectionOwnedBy(string $collectionId, string $userId): bool
    {
        $stmt = $this->pdo()->prepare('SELECT 1 FROM collections WHERE id = :cid AND user_id = :uid LIMIT 1');
        $stmt->execute([':cid' => $collectionId, ':uid' => $userId]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Enregistre un soin rattaché à une collection (l'appartenance doit être vérifiée
     * AUPRARAVANT par le contrôleur via collectionOwnedBy()).
     *
     * @param Soin $s Entité peuplée (collection_id, type, soin_date renseignés).
     * @return string UUID du soin créé.
     */
    public function create(Soin $s): string
    {
        $id    = $this->uuid();
        $s->id = $id;

        $sql = 'INSERT INTO soins (id, collection_id, soin_date, type, notes, engrais, substrat, ravageurs)
                VALUES (:id, :collection_id, :soin_date, :type, :notes, :engrais, :substrat, :ravageurs)';
        $this->pdo()->prepare($sql)->execute($this->bind($s, $id));
        return $id;
    }

    /**
     * Met à jour un soin, UNIQUEMENT si sa collection appartient à l'utilisateur (RG1).
     *
     * @param Soin   $s      Entité peuplée (id = clé).
     * @param string $userId Propriétaire attendu.
     * @return bool
     */
    public function updateForUser(Soin $s, string $userId): bool
    {
        $sql = 'UPDATE soins s
                   JOIN collections c ON c.id = s.collection_id
                    SET s.soin_date = :soin_date, s.type = :type, s.notes = :notes,
                        s.engrais = :engrais, s.substrat = :substrat, s.ravageurs = :ravageurs
                  WHERE s.id = :id AND c.user_id = :uid';
        $params = $this->bind($s, $s->id);
        $params[':uid'] = $userId;
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    /**
     * Supprime un soin, UNIQUEMENT si sa collection appartient à l'utilisateur (RG1).
     *
     * @param string $id     UUID du soin.
     * @param string $userId Propriétaire attendu.
     * @return bool
     */
    public function deleteForUser(string $id, string $userId): bool
    {
        $sql  = 'DELETE s FROM soins s
                   JOIN collections c ON c.id = s.collection_id
                  WHERE s.id = :id AND c.user_id = :uid';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Tableau de binding PDO (booléens → 0/1, id fourni).
     *
     * @param Soin   $s
     * @param string $id UUID à lier sur :id.
     * @return array
     */
    private function bind(Soin $s, string $id): array
    {
        return [
            ':id'            => $id,
            ':collection_id' => $s->collectionId,
            ':soin_date'     => $s->soinDate !== '' ? $s->soinDate : date('Y-m-d'),
            ':type'          => $s->type,
            ':notes'         => $s->notes,
            ':engrais'       => $s->engrais ? 1 : 0,
            ':substrat'      => $s->substrat ? 1 : 0,
            ':ravageurs'     => $s->ravageurs ? 1 : 0,
        ];
    }

    /**
     * Fabrique un UUID v4 (RFC 4122) en PHP pur.
     *
     * @return string
     */
    private function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
