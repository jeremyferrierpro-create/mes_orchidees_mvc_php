<?php
/**
 * Repository des catégories de conseils — table `categories_conseil`.
 *
 * Vocabulaire ouvert : s'enrichit à la création d'un conseil avec un label inédit
 * (findOrCreateLabel()). La clé UNIQUE sur `label` garantit l'idempotence.
 *
 * @package App\Repository
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-18
 */

namespace App\Repository;

use App\Entity\CategorieConseil;
use PDO;

class CategorieConseilRepository
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
     * Toutes les catégories, triées alphabétiquement.
     *
     * @return CategorieConseil[]
     */
    public function findAll(): array
    {
        $rows = $this->pdo()->query('SELECT id, label FROM categories_conseil ORDER BY label ASC')->fetchAll();
        return array_map(fn(array $r) => CategorieConseil::fromRow($r), $rows);
    }

    /**
     * Résout un label en identifiant (null si inexistant).
     *
     * @param string $label
     * @return int|null
     */
    public function findIdByLabel(string $label): ?int
    {
        $stmt = $this->pdo()->prepare('SELECT id FROM categories_conseil WHERE label = :label LIMIT 1');
        $stmt->execute([':label' => $label]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /**
     * Trouve ou crée une catégorie par label (upsert).
     *
     * Retourne l'identifiant existant ou nouvellement attribué. La clé UNIQUE
     * sur `label` garantit l'absence de doublon malgré la concurrence.
     *
     * @param string $label
     * @return int|null null si le label est vide après trim.
     */
    public function findOrCreateLabel(string $label): ?int
    {
        $label = trim($label);
        if ($label === '') {
            return null;
        }
        $existing = $this->findIdByLabel($label);
        if ($existing !== null) {
            return $existing;
        }
        $stmt = $this->pdo()->prepare('INSERT INTO categories_conseil (label) VALUES (:label) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
        $stmt->execute([':label' => $label]);
        return (int) $this->pdo()->lastInsertId();
    }
}
