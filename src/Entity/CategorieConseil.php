<?php
/**
 * Entité CategorieConseil — vocabulaire des catégories de conseils.
 *
 * Reference partagée par les fiches `conseils` (FK `id_categorie`). Le vocabulaire
 * est ouvert : il s'enrichit au fil des créations côté admin (labels éditoriaux
 * type « Conseils de base » et comportements botaniques type « Epiphyte » cohabitent
 * dans cette table — choix documenté dans docs/architecture/00-arbitrage-denormalisation.md).
 *
 * @package App\Entity
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-18
 */

namespace App\Entity;

class CategorieConseil
{
    public int $id = 0;
    public string $label = '';

    public static function fromRow(array $row): self
    {
        $c = new self();
        $c->id    = (int) ($row['id'] ?? 0);
        $c->label = (string) ($row['label'] ?? '');
        return $c;
    }
}
