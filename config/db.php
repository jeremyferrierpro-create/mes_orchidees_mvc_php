<?php
/**
 * Connexion à la base de données MySQL (PDO)
 *
 * Fournit une fabrique de connexions PDO pour MySQL 8, configurée via les
 * variables d'environnement du fichier .env. Cible MySQL actée en Phase 01/02
 * (décision D3), en remplacement de l'ancienne connexion PostgreSQL/Neon.
 *
 * @package App\Config
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

require_once __DIR__ . '/env.php';

/**
 * Retourne une instance PDO connectée à MySQL (singleton par requête).
 *
 * Deux modes de configuration sont acceptés :
 *  1. DSN complet            : DB_DSN="mysql:host=...;port=3306;dbname=...;charset=utf8mb4"
 *  2. Variables discrètes    : DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
 *
 * Les options PDO garantissent : erreurs en exception, associatif par défaut,
 * et vraies requêtes préparées (indispensable contre les injections SQL).
 *
 * @return PDO Instance PDO connectée.
 * @throws RuntimeException Si la configuration est absente.
 * @throws PDOException     Si la connexion échoue.
 */
function getDb(): PDO
{
    // On mémorise la connexion pour ne pas la rouvrir à chaque appel.
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // --- Lecture des variables d'environnement (avec getenv() en secours) ---
    $env = static function (string $key, ?string $default = null): ?string {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return $value;
    };

    // --- Construction du DSN ---
    $dsn = $env('DB_DSN');
    $user = $env('DB_USER');
    $pass = $env('DB_PASS');

    if (!is_string($dsn) || $dsn === '') {
        // Mode variables discrètes : on assemble le DSN MySQL soi-même.
        $host = $env('DB_HOST', '127.0.0.1');
        $port = $env('DB_PORT', '3306');
        $name = $env('DB_NAME');

        if (!is_string($name) || $name === '') {
            throw new RuntimeException(
                'Configuration MySQL absente : définir DB_DSN, ou DB_HOST/DB_NAME/DB_USER/DB_PASS dans .env.'
            );
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
    }

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // erreurs => exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,          // lignes associatives
            PDO::ATTR_EMULATE_PREPARES   => false,                     // vraies requêtes préparées
        ]);

        // Sécurité : coupe l'affichage des erreurs détaillées en production.
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    } catch (PDOException $e) {
        // En debug, on montre le message ; sinon, message générique (pas de fuite d'info).
        $debug = ($env('APP_DEBUG', 'false') === 'true');
        throw new RuntimeException(
            'Connexion à la base de données impossible.' . ($debug ? ' Détail : ' . $e->getMessage() : '')
        );
    }
}
