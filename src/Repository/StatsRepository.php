<?php
/**
 * Repository des statistiques de plateforme (back-office admin).
 *
 * Agrège en lecture seule les compteurs des différentes tables pour le tableau de
 * bord d'administration (:total utilisateurs, orchidées publiées / en attente,
 * conseils, collections, soins, notifications). Aucune écriture : de simples
 * requêtes COUNT() préparées.
 *
 * @package App\Repository
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

namespace App\Repository;

use PDO;

class StatsRepository
{
    private ?PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    /** Connexion résolue à la demande (lazy). */
    private function pdo(): PDO
    {
        if ($this->db instanceof PDO) {
            return $this->db;
        }
        $this->db = getDb();
        return $this->db;
    }

    /**
     * Compte une table (eventuellement avec un prédicat WHERE simple).
     *
     * @param string $sql Requête retournant un scalaire COUNT(...).
     * @return int
     */
    private function count(string $sql): int
    {
        return (int) $this->pdo()->query($sql)->fetchColumn();
    }

    /**
     * Statistiques globales de la plateforme.
     *
     * @return array{
     *   users:int, users_admin:int, orchids_published:int, orchids_pending:int,
     *   orchids_total:int, conseils:int, collections:int, soins:int,
     *   notifications:int, notifications_unread:int
     * }
     */
    public function platformStats(): array
    {
        return [
            'users'               => $this->count('SELECT COUNT(*) FROM users'),
            'users_admin'         => $this->count("SELECT COUNT(*) FROM users WHERE role IN ('admin','moderateur')"),
            'orchids_published'   => $this->count('SELECT COUNT(*) FROM orchids WHERE is_published = 1'),
            'orchids_pending'     => $this->count('SELECT COUNT(*) FROM orchids WHERE is_published = 0'),
            'orchids_total'       => $this->count('SELECT COUNT(*) FROM orchids'),
            'conseils'            => $this->count('SELECT COUNT(*) FROM conseils'),
            'collections'         => $this->count('SELECT COUNT(*) FROM collections'),
            'soins'               => $this->count('SELECT COUNT(*) FROM soins'),
            'notifications'       => $this->count('SELECT COUNT(*) FROM notifications'),
            'notifications_unread'=> $this->count('SELECT COUNT(*) FROM notifications WHERE is_read = 0'),
        ];
    }

    /**
     * Répartition des utilisateurs par rôle (pour un mini-tableau admin).
     *
     * @return array<string,int> role => nombre.
     */
    public function usersByRole(): array
    {
        $rows = $this->pdo()
            ->query('SELECT role, COUNT(*) AS nb FROM users GROUP BY role')
            ->fetchAll();

        $map = ['user' => 0, 'admin' => 0, 'moderateur' => 0];
        foreach ($rows as $r) {
            $map[(string) $r['role']] = (int) $r['nb'];
        }
        return $map;
    }
}
