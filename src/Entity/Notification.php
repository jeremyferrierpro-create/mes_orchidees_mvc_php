<?php
/**
 * Entité Notification — rappel de soin et alerte utilisateur (table MySQL `notifications`).
 *
 * Une notification appartient STRICTEMENT à un utilisateur (RG : isolation par
 * `user_id`). Elle peut être rattachée à une entrée de collection (`collection_id`,
 * ex : rappel de soin) ou être globale (`collection_id = NULL`, ex : validation d'une
 * proposition d'orchidée — RG5). Les propriétés sont en camelCase ; la correspondance
 * avec les colonnes snake_case est assurée dans NotificationRepository::fromRow().
 *
 * @package App\Entity
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Entity;

class Notification
{
    public string $id = '';
    public ?int $legacyId = null;
    public string $userId = '';
    public ?string $collectionId = null;
    public string $type = 'rappel_soin';
    public string $message = '';
    public bool $isRead = false;
    public ?string $notifDate = null;
    public ?string $createdAt = null;

    /** Champ d'affichage transitoire : nom de l'orchidée rattachée (jointure). */
    public ?string $orchidName = null;

    /** Types de notification reconnus. */
    public const TYPES = ['rappel_soin', 'validation', 'rejet', 'info'];

    /**
     * Construit une entité à partir d'une ligne MySQL (colonnes snake_case).
     *
     * @param array $row Ligne issue de NotificationRepository.
     * @return self
     */
    public static function fromRow(array $row): self
    {
        $n = new self();
        $n->id           = (string) ($row['id'] ?? '');
        $n->legacyId     = isset($row['legacy_id']) ? (int) $row['legacy_id'] : null;
        $n->userId       = (string) ($row['user_id'] ?? '');
        $n->collectionId = $row['collection_id'] ?? null;
        $n->type         = (string) ($row['type'] ?? 'rappel_soin');
        $n->message      = (string) ($row['message'] ?? '');
        // MySQL renvoie 0/1 (TINYINT) ; on normalise en booléen.
        $n->isRead       = (bool) ($row['is_read'] ?? false);
        $n->notifDate    = $row['notif_date'] ?? null;
        $n->createdAt    = $row['created_at'] ?? null;
        $n->orchidName   = $row['orchid_name'] ?? null;
        return $n;
    }

    /**
     * Libellé lisible du type de notification.
     *
     * @return string
     */
    public function typeLabel(): string
    {
        return match ($this->type) {
            'rappel_soin' => 'Rappel de soin',
            'validation'  => 'Proposition validée',
            'rejet'       => 'Proposition refusée',
            'info'        => 'Information',
            default       => ucfirst($this->type),
        };
    }

    /**
     * La notification est-elle non lue ?
     *
     * @return bool
     */
    public function isUnread(): bool
    {
        return !$this->isRead;
    }

    /**
     * Date de notification formatée en français (jj/mm/aaaa hh:mm).
     *
     * @return string
     */
    public function dateFr(): string
    {
        if ($this->notifDate === null || $this->notifDate === '') {
            return '';
        }
        try {
            return (new \DateTime($this->notifDate))->format('d/m/Y H:i');
        } catch (\Exception $e) {
            return $this->notifDate;
        }
    }

    /**
     * Représentation tableau (camelCase) pour les réponses JSON.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'type'         => $this->type,
            'typeLabel'    => $this->typeLabel(),
            'message'      => $this->message,
            'isRead'       => $this->isRead,
            'collectionId' => $this->collectionId,
            'orchidName'   => $this->orchidName,
            'notifDate'    => $this->notifDate,
            'dateFr'       => $this->dateFr(),
        ];
    }
}
