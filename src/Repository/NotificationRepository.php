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
 * @version 3.1.0
 * @since   2026-10-18 (C0 : type de soin + images portés par les rappels)
 */

namespace App\Repository;

use App\Entity\Notification;
use PDO;

class NotificationRepository
{
    /** Sélection jointe : notification + plante rattachée (via collection → orchidée)
     *  + type de soin attendu (C0) + images d'affichage (photo personnelle, espèce). */
    private const SELECT_JOINED = 'SELECT n.id, n.legacy_id, n.user_id, n.collection_id, n.type,
                                          n.message, n.is_read, n.statut, n.id_type_soin,
                                          n.notif_date, n.created_at,
                                          o.name AS orchid_name, ts.label AS type_soin_label,
                                          c.photo AS collection_photo, o.img AS orchid_img,
                                          c.rappel_frequence_jours AS collection_frequence
                                     FROM notifications n
                                     LEFT JOIN collections c ON c.id = n.collection_id
                                     LEFT JOIN orchids o     ON o.id = c.orchid_id
                                     LEFT JOIN types_soin ts ON ts.id = n.id_type_soin';

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

    // ------------------------------------------------------------------
    // RAPPELS DE SOIN — cycle de vie (RG3 étendu)
    // ------------------------------------------------------------------

    /**
     * Rappels de l'utilisateur pour les statuts donnés, triés par échéance.
     *
     * RG1 : `user_id` est dans le WHERE ; un statut hors liste blanche ('', 'x',
     * SQL-injecté) est silencieusement ignoré (la méthode accepte un tableau
     * d'entiers de statut valides).
     *
     * @param string   $userId Propriétaire.
     * @param string[] $statuts Sous-ensemble de `Notification::STATUTS`. Ex : ['planifie','actif']
     * @param int|null $limit  Optionnel (nombre max de lignes).
     * @return Notification[]
     */
    public function findRappelsForUser(string $userId, array $statuts, ?int $limit = null): array
    {
        $statuts = array_values(array_intersect($statuts, Notification::STATUTS));
        if ($statuts === []) {
            return [];
        }
        $in   = implode(',', array_fill(0, count($statuts), '?'));
        $sql  = self::SELECT_JOINED
              . ' WHERE n.user_id = ? AND n.type = \'rappel_soin\' AND n.statut IN (' . $in . ')'
              . ' ORDER BY n.notif_date ASC';
        if ($limit !== null && $limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(array_merge([$userId], $statuts));
        return array_map(fn(array $r) => Notification::fromRow($r), $stmt->fetchAll());
    }

    /**
     * Change le statut d'un rappel (RG1 : WHERE user_id).
     *
     * @param string $id     UUID de la notification.
     * @param string $userId Propriétaire.
     * @param string $statut Nouveau statut — doit être dans `Notification::STATUTS`.
     * @return bool True si la ligne a été mise à jour.
     */
    public function updateStatutForUser(string $id, string $userId, string $statut): bool
    {
        if (!in_array($statut, Notification::STATUTS, true)) {
            return false;
        }
        $stmt = $this->pdo()->prepare('UPDATE notifications SET statut = :statut WHERE id = :id AND user_id = :uid');
        $stmt->execute([':statut' => $statut, ':id' => $id, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Replanifie un rappel à une nouvelle date (et replace le statut en `planifie`).
     *
     * @param string $id     UUID.
     * @param string $userId Propriétaire.
     * @param string $newDate Date ISO (Y-m-d ou Y-m-d H:i:s).
     * @return bool
     */
    public function rescheduleForUser(string $id, string $userId, string $newDate): bool
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE notifications SET notif_date = :date, statut = \'planifie\'
             WHERE id = :id AND user_id = :uid'
        );
        $stmt->execute([':date' => $newDate, ':id' => $id, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Bascule les rappels `planifie` dont la date d'échéance est passée en `actif`.
     *
     * Une seule écriture en lot : typiquement appelée une fois par page (dashboard
     * ou vue « Rappels ») pour rendre visibles les rappels devenus dus.
     * RG1 : le WHERE cible UNIQUEMENT les rappels de `$userId`, jamais un tiers.
     *
     * @param string $userId
     * @return int Nombre de lignes activées.
     */
    public function activateDueReminders(string $userId): int
    {
        $stmt = $this->pdo()->prepare(
            "UPDATE notifications SET statut = 'actif'
             WHERE user_id = :uid AND type = 'rappel_soin' AND statut = 'planifie' AND notif_date <= NOW()"
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->rowCount();
    }

    /**
     * Le dernier rappel (tous statuts) d'une plante, pour éviter les doublons
     * quand l'utilisateur enregistre plusieurs soins rapprochés.
     *
     * @param string $collectionId
     * @param string $userId
     * @return Notification|null
     */
    public function findLatestRappelForCollection(string $collectionId, string $userId): ?Notification
    {
        $sql  = self::SELECT_JOINED
              . " WHERE n.user_id = :uid AND n.collection_id = :cid AND n.type = 'rappel_soin'"
              . ' ORDER BY n.notif_date DESC LIMIT 1';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':uid' => $userId, ':cid' => $collectionId]);
        $row = $stmt->fetch();
        return $row === false ? null : Notification::fromRow($row);
    }

    /**
     * Met à jour le message et la date d'un rappel existant (utilisé quand un
     * nouveau soin remplace le précédent plutôt que d'empiler une notification).
     *
     * @param Notification $n Entité avec id déjà positionné + message/notifDate/statut à écrire.
     * @param string       $userId Propriétaire attendu (RG1).
     * @return bool
     */
    public function updateRappelForUser(Notification $n, string $userId): bool
    {
        $statut = in_array($n->statut, Notification::STATUTS, true) ? $n->statut : 'planifie';
        $stmt = $this->pdo()->prepare(
            'UPDATE notifications SET message = :message, notif_date = :date, statut = :statut,
                     id_type_soin = :type_soin, is_read = 0
             WHERE id = :id AND user_id = :uid'
        );
        $stmt->execute([
            ':message'    => $n->message,
            ':date'       => $n->notifDate ?? date('Y-m-d H:i:s'),
            ':statut'     => $statut,
            ':type_soin'  => $n->idTypeSoin,
            ':id'         => $n->id,
            ':uid'        => $userId,
        ]);
        return $stmt->rowCount() > 0;
    }

    // ------------------------------------------------------------------
    // AIDE PRIVÉE
    // ------------------------------------------------------------------

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
        $statut    = in_array($n->statut, Notification::STATUTS, true) ? $n->statut : 'actif';

        $sql = 'INSERT INTO notifications (id, user_id, collection_id, type, message, is_read, statut, id_type_soin, notif_date)
                VALUES (:id, :user_id, :collection_id, :type, :message, :is_read, :statut, :id_type_soin, :notif_date)';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([
            ':id'            => $id,
            ':user_id'       => $n->userId,
            ':collection_id' => $n->collectionId,
            ':type'          => in_array($n->type, Notification::TYPES, true) ? $n->type : 'info',
            ':message'       => $n->message,
            ':is_read'       => $n->isRead ? 1 : 0,
            ':statut'        => $statut,
            ':id_type_soin'  => $n->idTypeSoin,
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
