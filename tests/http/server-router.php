<?php
/**
 * Script de routing du serveur de test `php -S` (Phase 08).
 *
 * Ce fichier N'EST PAS du code de production : c'est une pièce du harnais de test.
 * Il est exécuté par le serveur Web interne PHP AVANT de déléguer au vrai
 * front-controller (`public/index.php`), afin de garantir trois choses :
 *
 *  1. ISOLATION BASE — les variables de configuration de la base sont injectées
 *     dans `$_ENV`/`$_SERVER`/`putenv()` AVANT le chargement de `config/env.php`.
 *     Ce point est indispensable : avec `variables_order=GPCS` (config PHP locale),
 *     `$_ENV` n'est PAS peuplé par l'environnement système, et sans ces clés déjà
 *     définies, `loadEnv()` (config/env.php l. 48) écrirait les valeurs du `.env`
 *     réel — donc la base de DÉVELOPPEMENT. En pré-déclarant les clés, on rend
 *     l'isolation déterministe et indépendante de l'ini local.
 *
 *  2. PREUVE VÉRIFIABLE — une route réservée `/__http-test-probe` renvoie le nom
 *     effectif de la base à laquelle le processus serveur se connecte (aucun
 *     identifiant, aucun mot de passe, aucun DSN complet). Le runner s'arrête si
 *     cette preuve ne correspond pas à la base de test.
 *
 *  3. FIDÉLITÉ — tout le reste du trafic est transmis sans modification au vrai
 *     front-controller (même routage, mêmes sessions, mêmes contrôleurs, mêmes
 *     repositories). Aucun faux contrôleur, aucune réponse simulée.
 *
 * @package App\Tests\Http
 * @author  Jérémy Ferrier
 * @version 1.0.0
 * @since   2026-10-17
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);

// -----------------------------------------------------------------------------
// 1) Injection de la configuration de test (avant tout chargement de l'app)
// -----------------------------------------------------------------------------

/**
 * Définit une variable de configuration dans les trois supports lus par l'app.
 */
$setConfig = static function (string $key, ?string $value): void {
    if ($value === null || $value === '') {
        return;
    }
    // Les superglobales sont accessibles dans toute portée : pas de `use (&…)` possible.
    $_ENV[$key]    = $value;
    $_SERVER[$key] = $value;
    putenv($key . '=' . $value);
};

$dbHost = getenv('TEST_HTTP_DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('TEST_HTTP_DB_PORT') ?: '3306';
$dbName = getenv('TEST_HTTP_DB_NAME') ?: 'mes_orchidees_test';
$dbUser = getenv('TEST_HTTP_DB_USER') ?: (getenv('TEST_DB_USER') ?: '');
$dbPass = getenv('TEST_HTTP_DB_PASS');
if ($dbPass === false) {
    $dbPass = getenv('TEST_DB_PASS');
    if ($dbPass === false) {
        $dbPass = '';
    }
}
$dsn = getenv('TEST_HTTP_DB_DSN') ?: sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $dbHost,
    $dbPort,
    $dbName
);

// En mode test : erreurs PHP non affichées (comme en production) pour que les
// pages d'erreur homogènes de l'app soient observées, pas les traces.
$setConfig('DB_DSN', $dsn);
$setConfig('DB_USER', $dbUser);
$setConfig('DB_PASS', $dbPass);
$setConfig('DB_NAME', $dbName);
$setConfig('DB_HOST', $dbHost);
$setConfig('DB_PORT', $dbPort);
$setConfig('APP_DEBUG', getenv('TEST_HTTP_APP_DEBUG') !== false ? getenv('TEST_HTTP_APP_DEBUG') : 'false');
$setConfig('COOKIE_SECURE', 'false'); // HTTP local : sinon le cookie serait intransportable
$setConfig('COOKIE_SAMESITE', getenv('TEST_HTTP_COOKIE_SAMESITE') ?: 'Lax');
$setConfig('APP_BASE_PATH', '');

// -----------------------------------------------------------------------------
// 2) Route de preuve d'isolation (harnais uniquement, jamais en production réelle)
// -----------------------------------------------------------------------------

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = is_string($uri) ? $uri : '/';

if ($uri === '/__http-test-probe') {
    header('Content-Type: application/json; charset=utf-8');

    // Connexion avec la MÊME configuration que l'app (les clés $_ENV forcées ci-dessus).
    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        echo json_encode([
            'probe'    => 'ok',
            'database' => (string) $pdo->query('SELECT DATABASE()')->fetchColumn(),
            'tables'   => (int) $pdo->query(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'
            )->fetchColumn(),
            'engine'   => (string) $pdo->query('SELECT VERSION()')->fetchColumn(),
            'sapi'     => PHP_SAPI,
            'php'      => PHP_VERSION,
        ]);
    } catch (Throwable $e) {
        // Message court : ni DSN, ni identifiant, ni mot de passe.
        echo json_encode(['probe' => 'error', 'database' => null, 'error' => substr($e->getMessage(), 0, 80)]);
    }
    exit;
}

// -----------------------------------------------------------------------------
// 3) Fichiers statiques : servis par le serveur interne (docroot = public/)
// -----------------------------------------------------------------------------

if ($uri !== '/') {
    $static = realpath($root . '/public' . $uri);
    $docroot = realpath($root . '/public');
    if ($static !== false && $docroot !== false && str_starts_with($static, $docroot) && is_file($static)) {
        return false; // laisse `php -S` servir le fichier
    }
}

// -----------------------------------------------------------------------------
// 4) Délégation intégrale au front-controller réel de l'application
// -----------------------------------------------------------------------------

require $root . '/public/index.php';
