<?php
/**
 * Entité Orchidée — modèle du catalogue botanique (table MySQL `orchids`).
 *
 * Représente une espèce d'orchidée de l'encyclopédie. Les noms de propriétés sont
 * en camelCase ; la correspondance avec les colonnes snake_case de MySQL est faite
 * dans OrchidRepository (fromRow / toColumnArray).
 *
 * `id` est un slug texte (ex : "acacalis_cyanea"), convention héritée du JSON d'origine.
 *
 * @package App\Entity
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Entity;

class Orchid
{
    public string $id = '';
    public string $name = '';
    public ?string $vernacular = null;
    public ?string $botanicalOrder = null;
    public ?string $species = null;
    public string $genre = '';
    public string $family = 'Orchidaceae';
    public ?string $subfamily = null;
    public ?string $tribu = null;
    public ?string $subtribu = null;
    public ?string $behavior = null;
    public ?string $discovered = null;
    public ?string $origin = null;
    public ?string $img = null;
    public ?string $shortDesc = null;
    public ?string $longDesc = null;
    public bool $isPublished = true;
    public ?string $proposedBy = null;

    /**
     * Construit une entité à partir d'une ligne MySQL (colonnes snake_case).
     *
     * @param array $row Ligne issue de OrchidRepository.
     * @return self
     */
    public static function fromRow(array $row): self
    {
        $o = new self();
        $o->id             = (string) ($row['id'] ?? '');
        $o->name           = (string) ($row['name'] ?? '');
        $o->vernacular     = $row['vernacular']     ?? null;
        $o->botanicalOrder = $row['botanical_order'] ?? null;
        $o->species        = $row['species']        ?? null;
        $o->genre          = (string) ($row['genre'] ?? '');
        $o->family         = (string) ($row['family'] ?? 'Orchidaceae');
        $o->subfamily      = $row['subfamily']      ?? null;
        $o->tribu          = $row['tribu']          ?? null;
        $o->subtribu       = $row['subtribu']       ?? null;
        $o->behavior       = $row['behavior']       ?? null;
        $o->discovered     = $row['discovered']     ?? null;
        $o->origin         = $row['origin']         ?? null;
        $o->img            = $row['img']            ?? null;
        $o->shortDesc      = $row['short_desc']     ?? null;
        $o->longDesc       = $row['long_desc']      ?? null;
        // MySQL renvoie 0/1 (TINYINT) ; on normalise en booléen.
        $o->isPublished    = (bool) ($row['is_published'] ?? true);
        $o->proposedBy     = $row['proposed_by']    ?? null;
        return $o;
    }

    /**
     * URL de l'image de l'orchidée (réécrite sous /assets via l'helper asset()).
     *
     * Le stockages contient des chemins type "./assets/images/orchids/x.png" hérités
     * du JSON ; on normalise en chemin servi par /public.
     *
     * @return string|null
     */
    public function imageUrl(): ?string
    {
        if ($this->img === null || $this->img === '') {
            return null;
        }
        // On retire un éventuel préfixe "./" ou "./assets/" pour reconstruire proprement.
        $clean = ltrim($this->img, './');
        if (!str_starts_with($clean, 'assets/')) {
            $clean = 'assets/' . ltrim($clean, '/');
        }
        return function_exists('asset') ? asset($clean) : '/' . $clean;
    }

    /**
     * Le nom complet scientifique (genre + espèce) pour l'affichage.
     *
     * @return string
     */
    public function scientificName(): string
    {
        return trim($this->genre . ' ' . ($this->species ?? '')) ?: $this->name;
    }

    /**
     * Représentation tableau (camelCase) pour les réponses JSON de l'API.
     *
     * Sert aux endpoints /orchids et /orchids/search consommés par le front JS.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'vernacular'     => $this->vernacular,
            'order'          => $this->botanicalOrder,
            'species'        => $this->species,
            'genre'          => $this->genre,
            'family'         => $this->family,
            'subfamily'      => $this->subfamily,
            'tribu'          => $this->tribu,
            'subtribu'       => $this->subtribu,
            'behavior'       => $this->behavior,
            'discovered'     => $this->discovered,
            'origin'         => $this->origin,
            'img'            => $this->imageUrl(),
            'shortDesc'      => $this->shortDesc,
            'longDesc'       => $this->longDesc,
            'isPublished'    => $this->isPublished,
        ];
    }
}
