<?php
/**
 * Entité Collection — une orchidée détenue par un utilisateur (table MySQL `collections`).
 *
 * Représente un sujet personnel : une plante d'une espèce (`orchid_id`) ajoutée par
 * un utilisateur (`user_id`) dans « Ma Collection », avec ses conditions de culture
 * (emplacement, température, hygrométrie, lumière, ventilation, notes).
 *
 * `id` est un UUID CHAR(36) généré par MySQL (DEFAULT (UUID())) : il n'est jamais
 * saisi dans les requêtes d'écriture (INSERT/UPDATE).
 *
 * Les propriétés `orchidName`, `orchidImg` et `orchidVernacular` sont TRANSITOIRES :
 * elles proviennent de la jointure avec `orchids` (vue/SELECT d'affichage) et ne sont
 * JAMAIS écrites en base. Elles servent uniquement aux cartes du tableau de bord.
 *
 * @package App\Entity
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Entity;

class Collection
{
    public string $id = '';
    public ?string $legacyId = null;
    public string $userId = '';
    public string $orchidId = '';
    public ?string $location = null;
    public ?string $notes = null;
    public ?string $temp = null;
    public ?string $hygro = null;
    public ?string $light = null;
    public ?string $ventilation = null;
    /** Photo personnelle uploadée par le propriétaire (distincte de l'image de l'espèce). */
    public ?string $photo = null;
    /**
     * Fréquence personnalisable du rappel de soin (en jours). RG3 étendu :
     * remplace la constante fixe de 7 jours par un réglage par plante.
     * Bornée côté contrôleur à [1, 365] pour éviter le non-sens (0 = désactivé non
     * prévu ici ; l'utilisateur veut un rappel, sinon il n'enregistre pas de soin).
     */
    public int $rappelFrequenceJours = 7;
    /**
     * Opt-in du rappel automatique (RG3 étendu). `true` (défaut) : un rappel est
     * programmé à chaque soin enregistré. `false` : l'utilisateur a désactivé les
     * rappels pour cette plante — aucun nouveau rappel n'est créé et le rappel
     * éventuellement déjà planifié est annulé (côté contrôleur, sans destruction
     * de l'historique).
     */
    public bool $rappelActif = true;
    public ?string $addedAt = null;
    public ?string $updatedAt = null;

    // --- Champs transitifs (jointure orchids, lecture seule, non persistés) ---
    public ?string $orchidName = null;
    public ?string $orchidImg = null;
    public ?string $orchidVernacular = null;

    /**
     * Construit une entité à partir d'une ligne (colonnes snake_case de `collections`,
     * éventlement enrichie des colonnes `orchid_*` d'une jointure).
     *
     * @param array $row Ligne issue de CollectionRepository.
     * @return self
     */
    public static function fromRow(array $row): self
    {
        $c = new self();
        $c->id           = (string) ($row['id'] ?? '');
        $c->legacyId     = $row['legacy_id'] ?? null;
        $c->userId       = (string) ($row['user_id'] ?? '');
        $c->orchidId     = (string) ($row['orchid_id'] ?? '');
        $c->location     = $row['location'] ?? null;
        $c->notes        = $row['notes'] ?? null;
        $c->temp         = $row['temp'] ?? null;
        $c->hygro        = $row['hygro'] ?? null;
        $c->light        = $row['light'] ?? null;
        $c->ventilation  = $row['ventilation'] ?? null;
        $c->photo        = $row['photo'] ?? null;
        $c->rappelFrequenceJours = (int) ($row['rappel_frequence_jours'] ?? 7);
        $c->rappelActif    = (bool) ((int) ($row['rappel_actif'] ?? 1) === 1);
        $c->addedAt      = $row['added_at'] ?? null;
        $c->updatedAt    = $row['updated_at'] ?? null;

        // Champs d'affichage (présents seulement si la requête a jointé `orchids`).
        $c->orchidName        = $row['orchid_name'] ?? null;
        $c->orchidImg         = $row['orchid_img'] ?? null;
        $c->orchidVernacular  = $row['orchid_vernacular'] ?? null;

        return $c;
    }

    /**
     * URL de l'image de l'espèce jointe (réécrite sous /assets via asset()).
     *
     * @return string|null
     */
    public function orchidImageUrl(): ?string
    {
        if ($this->orchidImg === null || $this->orchidImg === '') {
            return null;
        }
        $clean = ltrim($this->orchidImg, './');
        if (!str_starts_with($clean, 'assets/')) {
            $clean = 'assets/' . ltrim($clean, '/');
        }
        return function_exists('asset') ? asset($clean) : '/' . $clean;
    }

    /**
     * URL de la photo personnelle du propriétaire (réécrite sous /assets via asset()).
     *
     * @return string|null
     */
    public function photoUrl(): ?string
    {
        if ($this->photo === null || $this->photo === '') {
            return null;
        }
        $clean = ltrim($this->photo, './');
        if (!str_starts_with($clean, 'assets/')) {
            $clean = 'assets/' . ltrim($this->photo, '/');
        }
        return function_exists('asset') ? asset($clean) : '/' . $clean;
    }

    /**
     * Image à afficher pour cette entrée : la photo personnelle si elle existe,
     * sinon l'image de l'espèce jointe.
     *
     * @return string|null
     */
    public function displayImageUrl(): ?string
    {
        return $this->photoUrl() ?? $this->orchidImageUrl();
    }

    /**
     * Le nom lisible de la plante (nom de l'espèce jointe, sinon l'id d'espèce).
     *
     * @return string
     */
    public function displayName(): string
    {
        return $this->orchidName ?: $this->orchidId;
    }

    /**
     * Représentation tableau (camelCase) pour les réponses JSON (/collections).
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'orchidId'    => $this->orchidId,
            'orchidName'  => $this->orchidName,
            'location'    => $this->location,
            'notes'       => $this->notes,
            'temp'        => $this->temp,
            'hygro'       => $this->hygro,
            'light'       => $this->light,
            'ventilation' => $this->ventilation,
            'photo'       => $this->photoUrl(),
            'rappelFrequenceJours' => $this->rappelFrequenceJours,
            'rappelActif'     => $this->rappelActif,
            'addedAt'     => $this->addedAt,
        ];
    }
}
