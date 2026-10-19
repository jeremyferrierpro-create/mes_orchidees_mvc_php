<?php
/**
 * Fixtures et garde d'isolation de la suite HTTP (Phase 08).
 *
 * Responsabilités :
 *  - ouvrir la connexion PDO du côté TEST (celle qui prépare et vérifie l'état en base) ;
 *  - REFUSER de continuer si la base visée n'est pas explicitement dédiée aux tests
 *    (double garde : nom déclaré + nom réellement résolu par `SELECT DATABASE()`) ;
 *  - créer des données de test déterministes et marquées, pour un nettoyage exact ;
 *  - vérifier qu'il ne reste AUCUNE donnée de test avant et après l'exécution ;
 *  - fournir un instantané en LECTURE SEULE de la base de développement, afin de
 *    Prouver qu'elle n'a pas été altérée (aucune écriture, aucun test destructive).
 *
 * Aucune information sensible : les identifiants viennent de variables
 * d'environnement, jamais de fichiers suivis, et rien n'est affiché.
 *
 * @package App\Tests\Http
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 1.0.0
 * @since   2026-10-17
 */

declare(strict_types=1);

/** Exception d'arrêt : prérequis manquant ou isolation non garantie (statut BLOCKED). */
final class HttpTestAbort extends RuntimeException {}

/**
 * Paramètres de connexion de la base de test, résolus depuis l'environnement.
 *
 * Les variables `TEST_HTTP_DB_*` sont la source de vérité du serveur de test ;
 * `TEST_DB_*` (convention Phase 05) sert de repli pour que la même commande
 * environnementale alimente les deux suites.
 */
function http_db_config(): array
{
    $get = static function (string $primary, ?string $fallback = null, ?string $default = null): string {
        $value = getenv($primary);
        if (($value === false || $value === '') && $fallback !== null) {
            $value = getenv($fallback);
        }
        if ($value === false || $value === '') {
            $value = $default ?? '';
        }
        return (string) $value;
    };

    $name = $get('TEST_HTTP_DB_NAME', 'TEST_DB_NAME', 'mes_orchidees_test');
    $user = $get('TEST_HTTP_DB_USER', 'TEST_DB_USER');
    $pass = $get('TEST_HTTP_DB_PASS', 'TEST_DB_PASS', '');
    $host = $get('TEST_HTTP_DB_HOST', 'TEST_DB_HOST', '127.0.0.1');
    $port = $get('TEST_HTTP_DB_PORT', 'TEST_DB_PORT', '3306');

    return [
        'name'           => $name,
        'user'           => $user,
        'pass'           => $pass,
        'host'           => $host,
        'port'           => $port,
        'dsn'            => sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name),
        // Base de développement à ne JAMAIS toucher (liste interdite explicite).
        'forbidden_name' => 'mes_orchidees',
    ];
}

/**
 * Connexion PDO du harnais sur la base de test, après garde d'isolation.
 *
 * Règle impérative n° 12 : si l'isolation ne peut pas être garantie, on s'arrête
 * AVANT toute opération susceptible d'écrire.
 */
function http_connect(array $cfg, string $runId): PDO
{
    if ($cfg['user'] === '') {
        throw new HttpTestAbort(
            "Aucun utilisateur de base de test fourni (TEST_HTTP_DB_USER ou TEST_DB_USER).\n" .
            "  La suite HTTP refuserait de deviner une connexion : relancez avec l'environnement dédié."
        );
    }

    // Garde 1 — le nom déclaré doit être manifestement une base de test.
    $isTestName = preg_match('/(^|_|-)test(_|$|s)/i', $cfg['name']) === 1;
    if (!$isTestName || $cfg['name'] === $cfg['forbidden_name']) {
        throw new HttpTestAbort(sprintf(
            'Le nom de base déclaré (%s) n\'est pas reconnu comme base de test dédiée. Arrêt.',
            $cfg['name']
        ));
    }

    // Connexion (serveur seulement, pour pouvoir lire DATABASE() réellement résolu).
    try {
        $pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        throw new HttpTestAbort(
            'Connexion impossible à la base de test (hôte/port/nom saisis). Détail court : '
            . substr($e->getMessage(), 0, 100)
        );
    }

    // Garde 2 — le nom RÉSOLU par le serveur doit correspondre (pas de tromperie de DSN).
    $resolved = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($resolved !== $cfg['name']) {
        throw new HttpTestAbort(sprintf(
            'La base réellement sélectionnée (%s) diffère de la base de test attendue (%s). Arrêt.',
            $resolved !== '' ? $resolved : 'aucune', $cfg['name']
        ));
    }
    if ($resolved === $cfg['forbidden_name']) {
        throw new HttpTestAbort('La base sélectionnée est la base de DÉVELOPPEMENT. Arrêt immédiat.');
    }

    // Garde 3 — le schéma attendu doit exister (sinon les tests seraient des faux PASS).
    $tables = (int) $pdo->query(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'
    )->fetchColumn();
    if ($tables < 7) {
        throw new HttpTestAbort(sprintf(
            'La base de test ne contient que %d table(s) : importez data/schema_mysql.sql ' .
            'dans la base de test AVANT d\'exécuter la suite HTTP.', $tables
        ));
    }

    return $pdo;
}

/**
 * Supprime TOUTES les données de test (marqueurs), y compris les résidus d'une
 * exécution interrompue. Ordre imposé par les clés étrangères.
 */
function http_cleanup(PDO $pdo): void
{
    $users = $pdo->query("SELECT id FROM users WHERE email LIKE 'http+%@example.invalid'")->fetchAll(PDO::FETCH_COLUMN);

    if ($users !== []) {
        $in = implode(',', array_fill(0, count($users), '?'));
        // 1) soins des collections de ces utilisateurs
        $stmt = $pdo->prepare("DELETE FROM soins WHERE collection_id IN
            (SELECT id FROM collections WHERE user_id IN ($in))");
        $stmt->execute($users);
        // 2) notifications
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id IN ($in)");
        $stmt->execute($users);
        // 3) collections
        $stmt = $pdo->prepare("DELETE FROM collections WHERE user_id IN ($in)");
        $stmt->execute($users);
        // 4) utilisateurs (CASCADE complète le nettoyage résiduel)
        $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($in)");
        $stmt->execute($users);
    }

    // 5) fiches orchidées de test (après les collections : FK RESTRICT).
    //    `httptest%` et non `httptest-%` : slugify() des contrôleurs remplace « - » par « _ »,
    //    les identifiants créés via HTTP peuvent donc être « httptest_d9_xxx ».
    $pdo->exec("DELETE FROM orchids WHERE id LIKE 'httptest%'");
    // 6) conseils de test éventuels (même règle de slug).
    $pdo->exec("DELETE FROM conseils WHERE id LIKE 'httptest%'");
}

/**
 * Compte les données de test encore présentes (doit être 0 avant et après).
 */
function http_test_rows_count(PDO $pdo): int
{
    $users = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email LIKE 'http+%@example.invalid'")->fetchColumn();
    $orchi = (int) $pdo->query("SELECT COUNT(*) FROM orchids WHERE id LIKE 'httptest%'")->fetchColumn();
    $cons  = (int) $pdo->query("SELECT COUNT(*) FROM conseils WHERE id LIKE 'httptest%'")->fetchColumn();
    return $users + $orchi + $cons;
}

/**
 * Crée les fixtures déterministes et retourne les identifiants utiles.
 *
 * Quatre comptes (user A, user B, admin, modérateur) + une orchidée publiée
 * « marqueur » (qui sert aussi de PREUVE comportementale d'isolation : elle
 * n'existe QUE dans la base de test) + une fiche non publiée pour la modération.
 */
function http_seed(PDO $pdo, string $runId): array
{
    // Mot de passe de test unique par exécution : jamais consigné dans un rapport.
    $password = 'HttpTest!' . $runId;
    $hash     = password_hash($password, PASSWORD_DEFAULT);

    // id est CHAR(36) avec DEFAULT (UUID()) : on relit l'identifiant généré
    // (aucun LAST_INSERT_ID possible sur une clé UUID textuelle).
    $userId = static function (string $slug, string $role) use ($pdo, $hash, $runId): array {
        $email = sprintf('http+%s.%s@example.invalid', $slug, $runId);
        $stmt  = $pdo->prepare(
            'INSERT INTO users (nom, prenom, email, password_hash, role)
             VALUES (:nom, :prenom, :email, :hash, :role)'
        );
        $stmt->execute([
            'nom'    => 'Test' . ucfirst($slug),
            'prenom' => 'Run' . $runId,
            'email'  => $email,
            'hash'   => $hash,
            'role'   => $role,
        ]);
        $row = $pdo->prepare('SELECT id, email FROM users WHERE email = ?');
        $row->execute([$email]);
        $found = $row->fetch();
        if (!is_array($found)) {
            throw new HttpTestAbort('Fixture utilisateur non retrouvée après insertion : ' . $slug);
        }
        return ['id' => (string) $found['id'], 'email' => (string) $found['email']];
    };

    $userA  = $userId('alpha', 'user');
    $userB  = $userId('beta', 'user');
    $admin  = $userId('admin', 'admin');
    $modo   = $userId('modo', 'moderateur');

    // Orchidée publiée « marqueur » : n'existe QUE dans la base de test.
    $markerOrchid = 'httptest-' . $runId . '-phal';
    $stmt = $pdo->prepare(
        'INSERT INTO orchids (id, name, vernacular, genre, family, short_desc, origin, is_published, proposed_by)
         VALUES (:id, :name, :vern, :genre, :fam, :short, :origin, 1, NULL)'
    );
    $stmt->execute([
        'id'    => $markerOrchid,
        'name'  => 'Phalaenopsis Test ' . $runId,
        'vern'  => 'Orchidée lune bleue ' . $runId,
        'genre' => 'Phalaenopsis',
        'fam'   => 'Orchidaceae',
        'short' => 'Fiche de test créée par la suite HTTP (contenu unique : zqx' . $runId . ').',
        'origin'=> 'Serre de test ' . $runId,
    ]);

    // Orchidée publiée « autocomplétion » : id = slugify(name), pour le test D9c
    // (utilisateur qui tape le nom d'une espèce déjà publiée → lien, pas de proposition).
    $publishedByName = 'httptest_auto_' . $runId;
    $stmt = $pdo->prepare(
        'INSERT INTO orchids (id, name, vernacular, genre, family, short_desc, origin, is_published, moderation_status, proposed_by)
         VALUES (:id, :name, :vern, :genre, :fam, :short, :origin, 1, :status, NULL)'
    );
    $stmt->execute([
        'id'     => $publishedByName,
        'name'   => 'Httptest Auto ' . $runId,
        'vern'   => 'Autocomplétion ' . $runId,
        'genre'  => 'Phalaenopsis',
        'fam'    => 'Orchidaceae',
        'short'  => 'Fiche autocomplétion (suite HTTP).',
        'origin' => 'Serre auto ' . $runId,
        'status' => 'approved',
    ]);

    // Fiche non publiée : alimente le parcours de modération.
    $pendingOrchid = 'httptest-' . $runId . '-pend';
    $stmt = $pdo->prepare(
        'INSERT INTO orchids (id, name, genre, short_desc, is_published, moderation_status, proposed_by)
         VALUES (:id, :name, :genre, :short, 0, :status, :prop)'
    );
    $stmt->execute([
        'id'     => $pendingOrchid,
        'name'   => 'Proposition modération ' . $runId,
        'genre'  => 'Cattleya',
        'short'  => 'Proposition en attente de validation (suite HTTP).',
        'status' => 'pending',
        'prop'   => $userA['id'],
    ]);

    return [
        'password'      => $password,
        'userA'         => $userA,
        'userB'         => $userB,
        'admin'         => $admin,
        'modo'          => $modo,
        'markerOrchid'  => $markerOrchid,
        'publishedByName' => $publishedByName,
        'pendingOrchid' => $pendingOrchid,
    ];
}

/**
 * Instantané LECTURE SEULE de la base de développement (preuve d'intégrité).
 *
 * Retourne null si la base de développement n'est pas accessible depuis cette
 * connexion (auquel cas la preuve est marquée NOT VERIFIED, pas un faux PASS).
 * Aucune écriture n'est jamais tentée ici.
 */
function http_dev_snapshot(PDO $pdo, string $devName): ?array
{
    try {
        $counts = [];
        foreach (['orchids', 'users', 'collections', 'soins', 'notifications', 'conseils'] as $table) {
            $counts[$table] = (int) $pdo->query(
                sprintf('SELECT COUNT(*) FROM `%s`.`%s`', $devName, $table)
            )->fetchColumn();
        }
        // Marqueurs : une donnée de test ne doit JAMAIS se retrouver en base de dev.
        $leaks = (int) $pdo->query(
            sprintf("SELECT COUNT(*) FROM `%s`.`users` WHERE email LIKE 'http+%%@example.invalid'", $devName)
        )->fetchColumn();
        $leaks += (int) $pdo->query(
            sprintf("SELECT COUNT(*) FROM `%s`.`orchids` WHERE id LIKE 'httptest%%'", $devName)
        )->fetchColumn();
        $counts['test_markers'] = $leaks;
        return $counts;
    } catch (Throwable $e) {
        return null;
    }
}
