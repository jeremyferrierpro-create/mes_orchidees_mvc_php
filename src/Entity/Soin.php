<?php
/**
 * Entité Soin — une entrée d'historique de culture (table MySQL `soins`).
 *
 * Un soin (arrosage, engrais, rempotage, contrôle ravageurs, changement de substrat…)
 * est rattaché à une entrée de collection (`collection_id`). La propriété de l'entrée
 * est vérifiée via `collections.user_id` (isolation RG1) dans SoinRepository.
 *
 * `id` est un UUID CHAR(36) généré par MySQL (DEFAULT (UUID())) : jamais saisi en écriture.
 *
 * @package App\Entity
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Entity;

class Soin
{
    public string $id = '';
    public ?string $legacyId = null;
    public string $collectionId = '';
    /** @var string Date au format MySQL 'YYYY-MM-DD'. */
    public string $soinDate = '';
    public string $type = 'arrosage';
    public ?string $notes = null;
    public bool $engrais = false;
    public bool $substrat = false;
    public bool $ravageurs = false;
    public ?string $createdAt = null;

    // --- Champ transitoire (jointure collections/orchids, lecture seule) ---
    public ?string $orchidName = null;

    /**
     * Construit une entité à partir d'une ligne MySQL (colonnes snake_case).
     *
     * @param array $row Ligne issue de SoinRepository.
     * @return self
     */
    public static function fromRow(array $row): self
    {
        $s = new self();
        $s->id           = (string) ($row['id'] ?? '');
        $s->legacyId     = $row['legacy_id'] ?? null;
        $s->collectionId = (string) ($row['collection_id'] ?? '');
        // soin_date peut arriver en string 'YYYY-MM-DD' (DATE) ; on normalise.
        $s->soinDate     = (string) ($row['soin_date'] ?? '');
        $s->type         = (string) ($row['type'] ?? 'arrosage');
        $s->notes        = $row['notes'] ?? null;
        // TINYINT(1) → 0/1 : normalisation en booléen.
        $s->engrais      = (bool) ($row['engrais'] ?? false);
        $s->substrat     = (bool) ($row['substrat'] ?? false);
        $s->ravageurs    = (bool) ($row['ravageurs'] ?? false);
        $s->createdAt    = $row['created_at'] ?? null;

        $s->orchidName   = $row['orchid_name'] ?? null;

        return $s;
    }

    /**
     * Le type lisible du soin (borne le champ libre à une valeur connue pour l'affichage).
     *
     * @return string
     */
    public function typeLabel(): string
    {
        return match ($this->type) {
            'arrosage'  => 'Arrosage',
            'engrais'   => 'Engrais',
            'rempotage' => 'Rempotage',
            'substrat'  => 'Substrat',
            'ravageurs' => 'Ravageurs',
            default     => ucfirst($this->type),
        };
    }

    /**
     * Représentation tableau (camelCase) pour la réponse JSON (/soins).
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'collectionId' => $this->collectionId,
            'orchidName'   => $this->orchidName,
            'soinDate'     => $this->soinDate,
            'type'         => $this->type,
            'notes'        => $this->notes,
            'engrais'      => $this->engrais,
            'substrat'     => $this->substrat,
            'ravageurs'    => $this->ravageurs,
        ];
    }
}
