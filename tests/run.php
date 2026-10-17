<?php
/**
 * Suite de tests de non-régression — MES ORCHIDÉES (Phase 05).
 *
 * Exécution :  php tests/run.php
 * Code retour : 0 si tout passe (SKIP autorisé), 1 si au moins un FAIL.
 *
 * Tests base de données : exécutés UNIQUEMENT si un environnement de test isolé
 * est fourni via TEST_DB_USER (et TEST_DB_PASS / TEST_DB_DSN). Sinon ils sont
 * SKIPPED — jamais présentés comme réussis sans exécution.
 *
 * @package App\Tests
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 1.0.0
 * @since   2026-10-17
 */

require __DIR__ . '/bootstrap.php';

$GLOBALS['pass'] = 0;
$GLOBALS['fail'] = 0;
$GLOBALS['skip'] = 0;
$GLOBALS['failures'] = [];
$GLOBALS['group'] = '';

function group(string $g): void
{
    $GLOBALS['group'] = $g;
    echo "\n=== $g ===\n";
}

/** Execute un test : la closure retourne true (PASS), 'SKIP', ou une raison (FAIL). */
function test(string $name, callable $fn): void
{
    try {
        $r = $fn();
        if ($r === true) {
            $GLOBALS['pass']++;
            echo "  [PASS] $name\n";
        } elseif ($r === 'SKIP') {
            $GLOBALS['skip']++;
            echo "  [SKIP] $name\n";
        } else {
            $GLOBALS['fail']++;
            $GLOBALS['failures'][] = $GLOBALS['group'] . ' / ' . $name;
            echo "  [FAIL] $name" . ($r === false ? '' : "  ($r)") . "\n";
        }
    } catch (\Throwable $e) {
        $GLOBALS['fail']++;
        $GLOBALS['failures'][] = $GLOBALS['group'] . ' / ' . $name . ' (exception)';
        echo "  [FAIL] $name  -> " . get_class($e) . ': ' . $e->getMessage() . "\n";
    }
}

/** Comparateur strict : true si égal, sinon description de l'écart. */
function eq($expected, $actual): bool|string
{
    if ($expected === $actual) {
        return true;
    }
    return 'attendu=' . var_export($expected, true) . ' obtenu=' . var_export($actual, true);
}

// -------------------------------------------------------------------------
// 1. ÉCHAPPEMENT ANTI-XSS (helper de production e())
// -------------------------------------------------------------------------
group('XSS / echappement e()');

test('e() neutralise une balise script', function () {
    return eq('&lt;script&gt;alert(1)&lt;/script&gt;', e('<script>alert(1)</script>'));
});
test('e() echappe les guillemets doubles (ENT_QUOTES)', function () {
    return eq('a&quot;b', e('a"b'));
});
test('e() echappe les guillemets simples (contexte attribut)', function () {
    return eq('a&#039;b', e("a'b"));
});
test('e() echappe les chevrons d\'un attribut injecte', function () {
    return eq('&lt;&gt;', e('<>'));
});
test('e() cast les entiers en chaine (sortie numerique sure)', function () {
    return eq('123', e(123));
});
test('e() rend une chaine vide pour null', function () {
    return eq('', e(null));
});
test('reflected search : terme GET echappe en attribut value=', function () {
    $query = '"><script>x</script>';
    $html  = '<input value="' . e($query) . '">';
    return (strpos($html, '<script>') === false) ? true : 'balise brute presentee';
});
test('sortie multiligne : nl2br(e(...)) preserve le texte sans HTML', function () {
    $stored  = "ligne1\n<img src=x onerror=alert(1)>";
    $out     = nl2br(e($stored));
    return (strpos($out, '<img') === false && strpos($out, '&lt;img') !== false)
        ? true : 'echappement multiligne insuffisant';
});

// -------------------------------------------------------------------------
// 2. CSRF (AbstractController::getCsrfToken / isCsrfTokenValid / assertCsrf)
// -------------------------------------------------------------------------
group('CSRF');

test('getCsrfToken() genere 64 caracteres hexadecimaux', function () {
    $_SESSION = [];
    $c = new TestableController();
    $t = $c->exposeCsrfToken();
    return eq(1, preg_match('/^[0-9a-f]{64}$/', $t));
});
test('getCsrfToken() est stable dans une meme session', function () {
    $_SESSION = [];
    $c = new TestableController();
    return eq($c->exposeCsrfToken(), $c->exposeCsrfToken());
});
test('isCsrfTokenValid() vrai quand le jeton correspond', function () {
    $_SESSION = ['csrf_token' => 'deadbeef'];
    $c = new TestableController();
    return eq(true, $c->exposeCsrfValid(['csrf_token' => 'deadbeef']));
});
test('isCsrfTokenValid() faux quand le jeton est absent', function () {
    $_SESSION = ['csrf_token' => 'deadbeef'];
    $c = new TestableController();
    return eq(false, $c->exposeCsrfValid([]));
});
test('isCsrfTokenValid() faux quand le jeton est errone', function () {
    $_SESSION = ['csrf_token' => 'deadbeef'];
    $c = new TestableController();
    return eq(false, $c->exposeCsrfValid(['csrf_token' => 'WRONG']));
});
test('isCsrfTokenValid() faux quand la session na pas de jeton', function () {
    $_SESSION = [];
    $c = new TestableController();
    return eq(false, $c->exposeCsrfValid(['csrf_token' => 'x']));
});
test('assertCsrf() leve CsrfException sur jeton invalide', function () {
    $_SESSION = ['csrf_token' => 'good'];
    $c = new TestableController();
    try {
        $c->exposeAssertCsrf(['csrf_token' => 'bad']);
        return 'aucune exception lesee';
    } catch (\App\Exception\CsrfException $e) {
        return true;
    }
});
test('assertCsrf() narrive pas sur jeton valide', function () {
    $_SESSION = ['csrf_token' => 'good'];
    $c = new TestableController();
    $c->exposeAssertCsrf(['csrf_token' => 'good']);
    return true;
});

// -------------------------------------------------------------------------
// 3. HELPERS D URL (chemins relatif / sous-repertoire)
// -------------------------------------------------------------------------
group('Helpers url / base_path');

test('url() a la racine (APP_BASE_PATH vide)', function () {
    $_ENV['APP_BASE_PATH'] = '';
    return eq('/encyclopedie', url('/encyclopedie'));
});
test('url() prefixe le sous-repertoire', function () {
    $_ENV['APP_BASE_PATH'] = '/mes-orchidees';
    return eq('/mes-orchidees/encyclopedie', url('/encyclopedie'));
});
test('asset() construit un chemin sans / initial doublon', function () {
    $_ENV['APP_BASE_PATH'] = '';
    return eq('/assets/css/style.css', asset('assets/css/style.css'));
});
// remise a l'etat racine pour les tests suivants
$_ENV['APP_BASE_PATH'] = '';

// -------------------------------------------------------------------------
// 4. HELPERS DE SESSION / AUTORISATION
// -------------------------------------------------------------------------
group('Session / roles');

test('is_logged_in() faux sans utilisateur', function () {
    $_SESSION = [];
    return eq(false, is_logged_in());
});
test('is_logged_in() vrai avec id en session', function () {
    $_SESSION = ['user' => ['id' => 'u1', 'role' => 'user']];
    return eq(true, is_logged_in());
});
test('current_user() renvoie le tableau session', function () {
    $_SESSION = ['user' => ['id' => 'u1', 'role' => 'admin']];
    return eq('admin', current_user()['role']);
});
test('has_role() accepte un role unique', function () {
    $_SESSION = ['user' => ['id' => 'u1', 'role' => 'admin']];
    return eq(true, has_role('admin'));
});
test('has_role() accepte une liste de roles', function () {
    $_SESSION = ['user' => ['id' => 'u1', 'role' => 'moderateur']];
    return eq(true, has_role(['admin', 'moderateur']));
});
test('has_role() refuse un role non detenu', function () {
    $_SESSION = ['user' => ['id' => 'u1', 'role' => 'user']];
    return eq(false, has_role(['admin', 'moderateur']));
});

// -------------------------------------------------------------------------
// 5. INTEGRATION BASE — ISOLATION RG1 (notifications) — SKIPPED si pas de BDD test
// -------------------------------------------------------------------------
group('RG1 / isolation des donnees (integration BDD)');

/** Le schema de test est-il initialise (tables users + notifications presentes) ? */
function schema_ready(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM users LIMIT 1');
        $pdo->query('SELECT 1 FROM notifications LIMIT 1');
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

/** Cree deux utilisateurs de test (A et B) ; retourne leurs ids. */
function rg1_make_users(PDO $pdo, string $uidA, string $uidB): void
{
    $st = $pdo->prepare('INSERT INTO users (id, nom, prenom, email, password_hash, role)
                         VALUES (:id, :nom, :prenom, :email, :ph, :role)');
    foreach ([$uidA, $uidB] as $i => $id) {
        $st->execute([
            ':id'     => $id,
            ':nom'    => $i === 0 ? 'Alpha' : 'Beta',
            ':prenom' => $i === 0 ? 'A' : 'B',
            ':email'  => $id . '@rg1test.invalid',
            ':ph'     => password_hash('test-only-not-a-secret', PASSWORD_DEFAULT),
            ':role'   => 'user',
        ]);
    }
}

test('RG1 : un utilisateur ne voit pas la notification d\'un autre', function () {
    $pdo = test_pdo();
    if ($pdo === null || !schema_ready($pdo)) {
        return 'SKIP';
    }
    $repo   = new \App\Repository\NotificationRepository($pdo);
    $suffix = bin2hex(random_bytes(4));
    $uidA   = 'test-rg1a-' . $suffix;
    $uidB   = 'test-rg1b-' . $suffix;
    rg1_make_users($pdo, $uidA, $uidB);

    $notifB = 'test-rg1-notif-' . $suffix;
    $pdo->prepare('INSERT INTO notifications (id, user_id, type, message, is_read)
                   VALUES (:id, :uid, :type, :msg, 0)')
        ->execute([':id' => $notifB, ':uid' => $uidB, ':type' => 'info', ':msg' => 'notif de B']);

    try {
        $leak = $repo->findByIdForUser($notifB, $uidA);   // A tente de lire la notif de B
        $owns = $repo->findByIdForUser($notifB, $uidB);   // B lit la sienne
        $ok   = ($leak === null) && ($owns !== null && $owns->userId === $uidB);
        return $ok ? true : 'fuite RG1 detectee';
    } finally {
        // CASCADE users -> notifications : supprimer les users purge les notifs.
        $pdo->prepare('DELETE FROM users WHERE id IN (:a, :b)')
            ->execute([':a' => $uidA, ':b' => $uidB]);
    }
});

test('RG1 : markRead d\'un tiers ne modifie aucune ligne', function () {
    $pdo = test_pdo();
    if ($pdo === null || !schema_ready($pdo)) {
        return 'SKIP';
    }
    $repo   = new \App\Repository\NotificationRepository($pdo);
    $suffix = bin2hex(random_bytes(4));
    $uidA   = 'test-rg1a-' . $suffix;
    $uidB   = 'test-rg1b-' . $suffix;
    rg1_make_users($pdo, $uidA, $uidB);

    $id = 'test-rg1-mark-' . $suffix;
    $pdo->prepare('INSERT INTO notifications (id, user_id, type, message, is_read)
                   VALUES (:id, :uid, :type, :msg, 0)')
        ->execute([':id' => $id, ':uid' => $uidB, ':type' => 'info', ':msg' => 'x']);
    try {
        $stolen = $repo->markReadForUser($id, $uidA);      // A tente de marquer la notif de B
        return ($stolen === false) ? true : 'marquage inter-utilisateur accepte';
    } finally {
        $pdo->prepare('DELETE FROM users WHERE id IN (:a, :b)')
            ->execute([':a' => $uidA, ':b' => $uidB]);
    }
});


// -------------------------------------------------------------------------
// BILAN
// -------------------------------------------------------------------------
echo "\n--------------------------------------------------\n";
echo sprintf("PASS=%d  FAIL=%d  SKIP=%d\n", $GLOBALS['pass'], $GLOBALS['fail'], $GLOBALS['skip']);
if ($GLOBALS['fail'] > 0) {
    echo "Echecs :\n";
    foreach ($GLOBALS['failures'] as $f) {
        echo "  - $f\n";
    }
    echo "VERDICT: FAIL\n";
    exit(1);
}
echo $GLOBALS['skip'] > 0
    ? "VERDICT: PASS (avec tests BDD SKIPPED : fournir TEST_DB_* pour les exécuter)\n"
    : "VERDICT: PASS\n";
exit(0);
