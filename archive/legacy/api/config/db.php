<?php
/**
 * Connexion à la base de données PostgreSQL (Neon)
 * 
 * Ce fichier gère la connexion à la base de données Neon PostgreSQL
 * en utilisant PDO avec les variables d'environnement pour la configuration.
 * 
 * @package MesOrchidées\API\Config
 * @author Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 2.0.0
 * @since 2026-09-01
 */

require_once __DIR__ . '/env.php';

/**
 * Crée et retourne une instance PDO connectée à la base de données Neon
 * 
 * Parse le DSN Neon depuis les variables d'environnement et configure
 * la connexion avec SSL activé et gestion des erreurs via exceptions.
 * 
 * @return \PDO Instance PDO connectée à la base de données
 * @throws \PDOException Si la connexion échoue
 * @throws \RuntimeException Si le DSN n'est pas configuré
 */
function getDb(): PDO {
    $dsn = $_ENV['NEON_DSN'] ?? getenv('NEON_DSN');
    
    if (!is_string($dsn) || $dsn === '') {
        throw new RuntimeException('NEON_DSN non configuré dans les variables d\'environnement');
        throw new RuntimeException('Variable NEON_DSN manquante dans .env');
    }

    $user = null;
    $password = null;

    // Si la chaine commence par postgresql:// ou postgres://, on la parse
    if (strpos($dsn, 'postgresql:') === 0 || strpos($dsn, 'postgres:') === 0) {
        if (strpos($dsn, 'postgresql:postgresql://') === 0) {
            $dsn = substr($dsn, 11);
        }

        $parsed = parse_url($dsn);
        if ($parsed) {
            $host = $parsed['host'] ?? '';
            $port = $parsed['port'] ?? 5432;
            $db = ltrim($parsed['path'] ?? '', '/');
            $user = $parsed['user'] ?? null;
            $password = $parsed['pass'] ?? null;
            
            // Format natif attendu par PDO pour PostgreSQL
            $pdoDsn = sprintf("pgsql:host=%s;port=%d;dbname=%s", $host, $port, $db);

            // Neon DB requires SNI. If older libpq, we must pass the endpoint ID explicitly.
            // Endpoint ID is the first part of the host (e.g., ep-empty-moon-b14n86hx)
            $hostParts = explode('.', $host);
            if (count($hostParts) > 0 && strpos($host, 'neon.tech') !== false) {
                $endpointId = $hostParts[0];
                $pdoDsn .= sprintf(";sslmode=require;options=endpoint=%s", $endpointId);
            }

            $dsn = $pdoDsn;
        }
    }

    try {
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, 
        ]);
        
        return $pdo;
    } catch (PDOException $e) {
        throw new RuntimeException('Erreur de connexion a la base de donnees. Verifiez NEON_DSN. ' . $e->getMessage());
    }
}
