<?php
/**
 * Amorce de la suite de tests de non-régression (Phase 05).
 *
 * Harnais SANS dépendance (pas de PHPUnit) : le projet n'a aucune require-dev,
 * le Dockerfile construit l'autoloader en `--no-dev`, et la taille du projet ne
 * justifie pas d'embarquer une pile de test. Tout est rejouable via une seule
 * commande : `php tests/run.php`.
 *
 * Ce fichier ne lit JAMAIS le .env réel : les tests d'intégration (base) sont
 * pilotés par des variables d'environnement TEST_DB_* fournies au moment de
 * l'exécution. Sans elles, les tests base sont SKIPPED (ni échec, ni faux PASS).
 *
 * @package App\Tests
 * @author  Jérémy Ferrier
 * @version 1.0.0
 * @since   2026-10-17
 */

declare(strict_types=1);

// Autoloader PSR-4 réel du projet (App\ -> src/, Mithridatem\Routing\ -> lib/).
require dirname(__DIR__) . '/vendor/autoload.php';

// Helpers de vue globaux : c'est LE code de production testé (e(), url(), …).
require dirname(__DIR__) . '/config/helpers.php';

/**
 * Contrôleur d'exposition : hérite du vrai AbstractController et révèle ses
 * méthodes protégées (CSRF) pour les tester sans framework.
 */
final class TestableController extends \App\Controller\AbstractController
{
    public function exposeCsrfToken(): string
    {
        return $this->getCsrfToken();
    }

    public function exposeCsrfValid(array $post): bool
    {
        return $this->isCsrfTokenValid($post);
    }

    public function exposeAssertCsrf(array $post): void
    {
        $this->assertCsrf($post);
    }
}

/**
 * Connexion PDO vers une base de test ISOLÉE, ou null si indisponible.
 *
 * Ne dépend d'aucun compte personnel ni d'aucun secret committé : les
 * identifiants viennent de variables d'environnement au moment du run.
 */
function test_pdo(): ?PDO
{
    $user = getenv('TEST_DB_USER');
    if ($user === false || $user === '') {
        return null; // Pas d'environnement de test : on skippe les tests base.
    }
    $dsn  = getenv('TEST_DB_DSN');
    $pass = getenv('TEST_DB_PASS');
    if ($dsn === false || $dsn === '') {
        $host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
        $port = getenv('TEST_DB_PORT') ?: '3306';
        $name = getenv('TEST_DB_NAME') ?: 'mes_orchidees_test';
        $dsn  = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
    }
    try {
        return new PDO($dsn, $user, ($pass === false ? null : $pass), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (\Throwable $e) {
        return null;
    }
}
