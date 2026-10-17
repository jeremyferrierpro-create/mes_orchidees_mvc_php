<?php
/**
 * Repository des notifications — accès PDO/MySQL à la table `notifications`.
 *
 * ISOLATION (même discipline que RG1) : toute lecture, tout marquage « lu » et toute
 * suppression sont plafonnés par `WHERE ... user_id = :uid`. Une notification d'un
 * autre utilisateur n'est JAMAIS atteignable, même en devinant son UUID.
 *
 * La jointure (`LEFT JOIN collections → orchids`) enrichit l'affichage du nom de la
 * plante concernée sans exposer les données d'autrui. L'`id` (UUID) est généré en PHP
 * (v4) à la création, pour que la valeur retournée corresponde réellement à la ligne
 * insérée (le `DEFAULT (UUID())` de MySQL n'est pas récupérable pour une colonne CHAR).
 *
 * @package App\Repository
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Repository;

use App\Entity\Notification;
use PDO;

class NotificationRepository
{
    /** Sélection jointe : notification + plante rattachée (via collection → orchidée). */
    private const SELECT_JOINED = 'SELECT n.id, n.legacy_id, n.user_id, n.collection_id, n.type,
                                          n.message, n.is_read, n.notif_date, n.created_at,
                                          o.name AS orchid_name
                                     FROM notifications n
                                     LEFT JOIN collections c ON c.id = n.collection_id
                                     LEFT JOIN orchids o     ON o.id = c.orchid_id';

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
     * Toutes les notifications de l'utilisateur, triées de la plus récente (isolation).
     *
     * @param string $userId UUID du propriétaire.
     * @return Notification[]
     */
    public function findByUser(string $userId): array
    {
        $sql  = self::SELECT_JOINED . ' WHERE n.user_id = :uid ORDER BY n.notif_date DESC, n.created_at DESC';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        return array_map(fn(array $r) => Notification::fromRow($r), $stmt->fetchAll());
    }

    /**
     * Une notification, UNIQUEMENT si elle appartient à l'utilisateur.
     *
     * @param string $id     UUID de la notification.
     * @param string $userId Propriétaire attendu.
     * @return Notification|null null si absente OU appartenant à un autre utilisateur.
     */
    public function findByIdForUser(string $id, string $userId): ?Notification
    {
        $sql  = self::SELECT_JOINED . ' WHERE n.id = :id AND n.user_id = :uid LIMIT 1';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        $row = $stmt->fetch();
        return $row === false ? null : Notification::fromRow($row);
    }

    /**
     * Nombre de notifications non lues de l'utilisateur (badge).
     *
     * @param string $userId Propriétaire.
     * @return int
     */
    public function unreadCount(string $userId): int
    {
        $sql  = 'SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Marque une notification comme lue, UNIQUEMENT si elle appartient à l'utilisateur.
     *
     * @param string $id     UUID de la notification.
     * @param string $userId Propriétaire attendu.
     * @return bool True si une ligne du bon propriétaire a été modifiée.
     */
    public function markReadForUser(string $id, string $userId): bool
    {
        $stmt = $this->pdo()->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid');
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Supprime une notification, UNIQUEMENT si elle appartient à l'utilisateur.
     *
     * @param string $id     UUID de la notification.
     * @param string $userId Propriétaire attendu.
     * @return bool True si une ligne du bon propriétaire a été supprimée.
     */
    public function deleteForUser(string $id, string $userId): bool
    {
        $stmt = $this->pdo()->prepare('DELETE FROM notifications WHERE id = :id AND user_id = :uid');
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Insère une notification. L'`id` est généré en PHP (UUID v4).
     *
     * Le `user_id` de l'entité désigne le DESTINATAIRE ; il est fixé par le caller
     * (jamais saisi depuis une requête client) : destinataire = propriétaire de la
     * collection (RG3) ou auteur d'une proposition (RG5).
     *
     * @param Notification $n Entité peuplée (userId + message au minimum).
     * @return string L'id (UUID) de la ligne insérée.
     */
    public function create(Notification $n): string
    {
        $id    = $this->uuid();
        $n->id = $id;

        $notifDate = $n->notifDate ?? date('Y-m-d H:i:s');

        $sql = 'INSERT INTO notifications (id, user_id, collection_id, type, message, is_read, notif_date)
                VALUES (:id, :user_id, :collection_id, :type, :message, :is_read, :notif_date)';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([
            ':id'            => $id,
            ':user_id'       => $n->userId,
            ':collection_id' => $n->collectionId,
            ':type'          => in_array($n->type, Notification::TYPES, true) ? $n->type : 'info',
            ':message'       => $n->message,
            ':is_read'       => $n->isRead ? 1 : 0,
            ':notif_date'    => $notifDate,
        ]);

        return $id;
    }

    /**
     * Fabrique un UUID v4 (RFC 4122) en PHP pur.
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
}
