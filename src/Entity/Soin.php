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
 * @author  Jérémy Ferrier
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
    /** FK vers `types_soin.id` — vocabulaire fermé des 5 types de soin. */
    public int $idTypeSoin = 1;
    /** Label transitoire (jointure `types_soin`, lecture seule). */
    public ?string $typeLabel = null;
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
        $s->idTypeSoin   = (int) ($row['id_type_soin'] ?? 1);
        // Jointure `types_soin.label` exposée sous le alias `type_label` par SoinRepository.
        $s->typeLabel    = $row['type_label'] ?? null;
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
     * Le label lisible du soin, résolu par jointure avec `types_soin`.
     * Repli défensif sur « arrosage » si la jointure n'a pas été fournie.
     *
     * @return string
     */
    public function typeLabel(): string
    {
        return $this->typeLabel ?? 'arrosage';
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
            'type'         => $this->typeLabel(),
            'idTypeSoin'   => $this->idTypeSoin,
            'notes'        => $this->notes,
            'engrais'      => $this->engrais,
            'substrat'     => $this->substrat,
            'ravageurs'    => $this->ravageurs,
        ];
    }
}
