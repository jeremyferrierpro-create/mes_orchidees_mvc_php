<?php
/**
 * Repository des types de soin — table `types_soin` (vocabulaire fermé, 5 valeurs).
 *
 * Le vocabulaire est posé par la migration
 * `data/migrations/20261018_lookup_types_soin_categories_conseil.sql` et ne doit
 * PAS être alimenté depuis les requêtes HTTP (pas de création dynamique).
 *
 * @package App\Repository
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-18
 */

namespace App\Repository;

use App\Entity\TypeSoin;
use PDO;

class TypeSoinRepository
{
    private ?PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    private function pdo(): PDO
    {
        if ($this->db instanceof PDO) {
            return $this->db;
        }
        $this->db = getDb();
        return $this->db;
    }

    /**
     * Tous les types, triés par id croissant (ordre de la migration).
     *
     * @return TypeSoin[]
     */
    public function findAll(): array
    {
        $rows = $this->pdo()->query('SELECT id, label FROM types_soin ORDER BY id ASC')->fetchAll();
        return array_map(fn(array $r) => TypeSoin::fromRow($r), $rows);
    }

    /**
     * Résout un label en identifiant. Retourne null si le label n'existe pas
     * dans le vocabulaire fermé — l'appelant (contrôleur) doit refuser la saisie.
     *
     * @param string $label
     * @return int|null
     */
    public function findIdByLabel(string $label): ?int
    {
        $stmt = $this->pdo()->prepare('SELECT id FROM types_soin WHERE label = :label LIMIT 1');
        $stmt->execute([':label' => $label]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /**
     * Identifiant du type par défaut (premier inséré — « arrosage » en pratique).
     * Utilisé comme repli défensif par SoinController si le type soumis est inconnu.
     *
     * @return int
     */
    public function defaultId(): int
    {
        $id = $this->pdo()->query('SELECT MIN(id) FROM types_soin')->fetchColumn();
        return (int) $id;
    }
}
