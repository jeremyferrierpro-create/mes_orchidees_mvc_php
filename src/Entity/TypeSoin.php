<?php
/**
 * Entité TypeSoin — vocabulaire fermé des types de soin.
 *
 * Reference partagée par toutes les entrées de `soins` (FK `id_type_soin`).
 * Les 5 valeurs sont posées par la migration
 * `data/migrations/20261018_lookup_types_soin_categories_conseil.sql`
 * et ne sont pas destinées à évoluer dynamiquement (vocabulaire métier stable).
 *
 * @package App\Entity
 * @author  Jérémy Ferrier
 * @version 3.0.0
 * @since   2026-10-18
 */

namespace App\Entity;

class TypeSoin
{
    public int $id = 0;
    public string $label = '';

    public static function fromRow(array $row): self
    {
        $t = new self();
        $t->id    = (int) ($row['id'] ?? 0);
        $t->label = (string) ($row['label'] ?? '');
        return $t;
    }
}
