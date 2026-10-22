<?php
/**
 * Entité Conseil — modèle des fiches de culture (table MySQL `conseils`).
 *
 * Deux types de conseils coexistent dans cette table :
 *  - 'category' : conseil général (arrosage, épiphytes, sortie de flask…) ;
 *  - 'species'  : fiche de culture liée à une espèce (FK `orchid_id`).
 *
 * Les noms de propriétés sont en camelCase ; la correspondance avec les colonnes
 * snake_case de MySQL est assurée dans ConseilRepository (fromRow / bind).
 *
 * `id` est un slug texte (ex : "conseils-base", "fiche-acacalis_cyanea"), convention
 * héritée du JSON d'origine.
 *
 * `care_cards` est stocké en JSON natif MySQL ; l'entité l'expose en tableau PHP
 * (associatif souple : temperature, arrosage, hygrometrie, rempotage, engrais, substrats).
 *
 * @package App\Entity
 * @author  Jérémy Ferrier
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Entity;

class Conseil
{
    public string $id = '';
    /** @var string 'category' | 'species' */
    public string $type = 'category';
    public string $name = '';
    public ?string $img = null;
    /** FK vers `categories_conseil.id` — vocabulaire ouvert des catégories. */
    public ?int $idCategorie = null;
    /** Label transitoire (jointure `categories_conseil`, lecture seule).
     *  Exposé par la propriété `category` pour compatibilité avec le code existant. */
    public ?string $category = null;
    public ?string $content = null;
    /** Carte de soins (tableau associatif souple, décodée du JSON MySQL). */
    public array $careCards = [];
    public ?string $orchidId = null;

    /**
     * Construit une entité à partir d'une ligne MySQL (colonnes snake_case).
     *
     * @param array $row Ligne issue de ConseilRepository.
     * @return self
     */
    public static function fromRow(array $row): self
    {
        $c = new self();
        $c->id          = (string) ($row['id'] ?? '');
        $c->type        = (string) ($row['type'] ?? 'category');
        $c->name        = (string) ($row['name'] ?? '');
        $c->img         = $row['img']      ?? null;
        // Jointure `categories_conseil` exposée par ConseilRepository sous l'alias `category`.
        $c->idCategorie = isset($row['id_categorie']) && $row['id_categorie'] !== null ? (int) $row['id_categorie'] : null;
        $c->category    = $row['category'] ?? null;
        $c->content     = $row['content']  ?? null;
        $c->orchidId    = $row['orchid_id'] ?? null;

        // care_cards : MySQL/MariaDB renvoie une chaîne JSON (ou déjà un tableau selon le driver).
        $raw = $row['care_cards'] ?? null;
        if (is_array($raw)) {
            $c->careCards = $raw;
        } elseif (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $c->careCards = is_array($decoded) ? $decoded : [];
        } else {
            $c->careCards = [];
        }

        return $c;
    }

    /**
     * Le conseil est-il une fiche liée à une espèce ?
     *
     * @return bool
     */
    public function isSpecies(): bool
    {
        return $this->type === 'species';
    }

    /**
     * URL de l'image du conseil (réécrite sous /assets via l'helper asset()).
     *
     * @return string|null
     */
    public function imageUrl(): ?string
    {
        if ($this->img === null || $this->img === '') {
            return null;
        }
        $clean = ltrim($this->img, './');
        if (!str_starts_with($clean, 'assets/')) {
            $clean = 'assets/' . ltrim($clean, '/');
        }
        return function_exists('asset') ? asset($clean) : '/' . $clean;
    }

    /**
     * Représentation tableau (camelCase) pour les réponses JSON éventuelles.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'type'      => $this->type,
            'name'      => $this->name,
            'img'       => $this->imageUrl(),
            'category'  => $this->category,
            'content'   => $this->content,
            'careCards' => $this->careCards,
            'orchidId'  => $this->orchidId,
        ];
    }
}
