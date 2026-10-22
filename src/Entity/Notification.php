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
 * @author  Jérémy Ferrier
 * @version 3.1.0
 * @since   2026-10-18 (C0 : le rappel porte le type de soin attendu)
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
    /**
     * Cycle de vie du rappel de soin.
     *  - 'planifie' : notif_date est une date future d'échéance (pas encore due).
     *  - 'actif'    : notification à traiter maintenant (ou rappel devenu dû).
     *  - 'traite'   : le propriétaire a consigné le soin correspondant.
     *  - 'ignore'   : le propriétaire a explicitement écarté le rappel.
     * Les notifications non-rappel (validation, rejet, info, nouvelle_proposition)
     * restent à 'actif' par parité avec l'ancien comportement (bande annonces).
     */
    public string $statut = 'actif';
    /**
     * Type de soin attendu par le rappel (FK `types_soin`, vocabulaire fermé).
     * NULL = rappel historique (antérieur à C0) ou notification globale.
     */
    public ?int $idTypeSoin = null;
    public ?string $notifDate = null;
    public ?string $createdAt = null;

    /** Champ d'affichage transitoire : nom de l'orchidée rattachée (jointure). */
    public ?string $orchidName = null;

    /** Champ d'affichage transitoire : libellé du type de soin attendu (jointure types_soin). */
    public ?string $typeSoinLabel = null;

    /** Champ d'affichage transitoire : photo personnelle de la plante rattachée (jointure). */
    public ?string $collectionPhoto = null;

    /** Champ d'affichage transitoire : image de l'espèce rattachée (jointure). */
    public ?string $orchidImg = null;

    /** Champ d'affichage transitoire : fréquence de rappel de la plante (jointure collections). */
    public ?int $collectionFrequence = null;

    /** Types de notification reconnus. */
    public const TYPES = ['rappel_soin', 'validation', 'rejet', 'nouvelle_proposition', 'info'];

    /** Statuts de cycle de vie. */
    public const STATUTS = ['planifie', 'actif', 'traite', 'ignore'];

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
        $n->statut       = (string) ($row['statut'] ?? 'actif');
        $n->idTypeSoin   = isset($row['id_type_soin']) ? (int) $row['id_type_soin'] : null;
        $n->notifDate    = $row['notif_date'] ?? null;
        $n->createdAt    = $row['created_at'] ?? null;
        $n->orchidName   = $row['orchid_name'] ?? null;
        $n->typeSoinLabel = $row['type_soin_label'] ?? null;
        $n->collectionPhoto = $row['collection_photo'] ?? null;
        $n->orchidImg    = $row['orchid_img'] ?? null;
        $n->collectionFrequence = isset($row['collection_frequence']) ? (int) $row['collection_frequence'] : null;
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
            'nouvelle_proposition' => 'Nouvelle proposition',
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
     * L'échéance du rappel tombe-t-elle aujourd'hui (comparaison en date de jour) ?
     *
     * @return bool
     */
    public function isDueToday(): bool
    {
        if ($this->notifDate === null || $this->notifDate === '') {
            return false;
        }
        return substr($this->notifDate, 0, 10) === date('Y-m-d');
    }

    /**
     * L'échéance du rappel est-elle dépassée (avant aujourd'hui) ?
     *
     * @return bool
     */
    public function isLate(): bool
    {
        if ($this->notifDate === null || $this->notifDate === '') {
            return false;
        }
        return substr($this->notifDate, 0, 10) < date('Y-m-d');
    }

    /**
     * Image à afficher sur la carte du rappel : la photo personnelle de la plante
     * si elle existe, sinon l'image de l'espèce jointe (même logique que
     * Collection::displayImageUrl()). NULL = pas d'image fiable → carte sans photo.
     *
     * @return string|null
     */
    public function displayImageUrl(): ?string
    {
        foreach ([['collectionPhoto', $this->collectionPhoto], ['orchidImg', $this->orchidImg]] as [$_, $raw]) {
            if ($raw === null || $raw === '') {
                continue;
            }
            $clean = ltrim($raw, './');
            if (!str_starts_with($clean, 'assets/')) {
                $clean = 'assets/' . ltrim($raw, '/');
            }
            return function_exists('asset') ? asset($clean) : '/' . $clean;
        }
        return null;
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
            'statut'       => $this->statut,
            'idTypeSoin'   => $this->idTypeSoin,
            'typeSoinLabel' => $this->typeSoinLabel,
            'collectionId' => $this->collectionId,
            'orchidName'   => $this->orchidName,
            'notifDate'    => $this->notifDate,
            'dateFr'       => $this->dateFr(),
        ];
    }
}
