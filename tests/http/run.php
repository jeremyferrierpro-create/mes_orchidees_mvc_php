<?php
/**
 * Suite de tests HTTP sur serveur réel (Phase 08, Option B validée en Phase 07).
 *
 * Complète (ne remplace pas) `php tests/run.php` : celle-ci teste des unités et de
 * l'intégration en appelant directement du code PHP ; la présente suite exerce le
 * parcours complet `client → serveur HTTP → routage → contrôleur → session/auth →
 * repository → base de test → réponse HTTP`, via de vraies requêtes réseau.
 *
 * Ordre d'exécution :
 *   1. gardes d'isolation AVANT toute écriture (fixtures.php) ;
 *   2. démarrage d'un serveur `php -S` dédié (tests/http/server-router.php) ;
 *   3. PREUVE d'isolation par la route réservée `/__http-test-probe` (le serveur
 *      doit être connecté à la base de test, jamais à la base de développement) ;
 *   4. fixtures + scénarios A→F ;
 *   5. nettoyage, preuve d'absence de résidu et d'intégrité de la base de dev ;
 *   6. tableau de résultats + bilan.
 *
 * Codes de sortie : 0 = aucun échec, 1 = au moins un FAIL, 2 = BLOCKED (prérequis
 * ou isolation non garantie) — le code de sortie seul ne suffit jamais à déclarer
 * un succès, le tableau de résultats est la source de vérité.
 *
 * Aucune donnée sensible dans la sortie : ni mot de passe, ni cookie, ni DSN.
 *
 * Lancement (PowerShell) :
 *   $env:TEST_HTTP_DB_USER='root'; $env:TEST_HTTP_DB_PASS=''
 *   php tests/http/run.php
 *
 * @package App\Tests\Http
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 1.0.0
 * @since   2026-10-17
 */

declare(strict_types=1);

require __DIR__ . '/client.php';
require __DIR__ . '/fixtures.php';

/**
 * Enregistreur des scénarios : un rang = une ligne du tableau de résultats.
 */
final class Suite
{
    /** @var array<int, array<string, string>> */
    public array $rows = [];

    /** @var array<string, int> */
    public array $counts = ['PASS' => 0, 'FAIL' => 0, 'BLOCKED' => 0, 'NOT RUN' => 0, 'NOT VERIFIED' => 0];

    /** @var string[] */
    public array $failures = [];

    /**
     * Vérification binaire : attendu vs obtenu, statut PASS ou FAIL.
     */
    public function check(
        string $group,
        string $name,
        string $request,
        string $expected,
        string $obtained,
        bool $ok
    ): void {
        $this->row($group, $name, $request, $expected, $obtained, $ok ? 'PASS' : 'FAIL');
        if (!$ok) {
            $this->failures[] = "[{$group}] {$name} — attendu {$expected}, obtenu {$obtained}";
        }
    }

    /**
     * Scénario non exécutable : un prérequis manque (statut BLOCKED).
     */
    public function blocked(string $group, string $name, string $request, string $why): void
    {
        $this->row($group, $name, $request, 'prérequis exécutable', $why, 'BLOCKED');
    }

    /**
     * Scénario volontairement non exécuté (périmètre hors du contrat actuel).
     */
    public function notRun(string $group, string $name, string $request, string $why): void
    {
        $this->row($group, $name, $request, '—', $why, 'NOT RUN');
    }

    /**
     * Exécuté mais invérifiable : la preuve attendue est inaccessible.
     */
    public function notVerified(string $group, string $name, string $request, string $why): void
    {
        $this->row($group, $name, $request, 'preuve accessible', $why, 'NOT VERIFIED');
    }

    /**
     * Rang brut (les scénarios de service — démarrage, isolation — sont aussi tracés).
     */
    public function row(string $group, string $name, string $request, string $expected, string $obtained, string $status): void
    {
        $this->rows[] = [
            'groupe'   => $group,
            'nom'      => $name,
            'requete'  => $request,
            'attendu'  => $expected,
            'obtenu'   => $obtained,
            'statut'   => $status,
        ];
        $this->counts[$status] = ($this->counts[$status] ?? 0) + 1;
    }

    /**
     * Tableau Markdown, destiné à être recopié dans le rapport de phase.
     */
    public function table(): string
    {
        $out = "| Groupe | Scénario | Méthode + chemin | Attendu | Obtenu | Statut |\n";
        $out .= "|---|---|---|---|---|---|\n";
        foreach ($this->rows as $r) {
            $out .= sprintf(
                '| %s | %s | %s | %s | %s | %s |',
                $r['groupe'],
                str_replace('|', '/', $r['nom']),
                str_replace('|', '/', $r['requete']),
                str_replace('|', '/', $r['attendu']),
                str_replace('|', '/', $r['obtenu']),
                $r['statut']
            ) . "\n";
        }
        return $out;
    }

    public function summary(): string
    {
        return sprintf(
            'PASS %d · FAIL %d · BLOCKED %d · NOT RUN %d · NOT VERIFIED %d',
            $this->counts['PASS'],
            $this->counts['FAIL'],
            $this->counts['BLOCKED'],
            $this->counts['NOT RUN'],
            $this->counts['NOT VERIFIED']
        );
    }

    /** Nombre de scénarios réellement évalués (PASS + FAIL). */
    public function executed(): int
    {
        return $this->counts['PASS'] + $this->counts['FAIL'];
    }
}

/**
 * Gestion du serveur HTTP de test (`php -S`) : démarrage, attente, arrêt.
 */
final class HttpTestServer
{
    /** @var resource|null */
    private $process = null;

    public string $baseUrl = '';
    public int $port = 0;
    public string $logFile = '';

    /**
     * Démarre le serveur sur le premier port libre et attend que la route de preuve
     * réponde. Échoue (return false) si aucun port ne s'éveille.
     */
    public function launch(string $root, Suite $suite): bool
    {
        $router  = str_replace('\\', '/', $root . '/tests/http/server-router.php');
        $docroot = str_replace('\\', '/', $root . '/public');
        $this->logFile = sys_get_temp_dir() . '/meso_http_server.log';
        @unlink($this->logFile);

        foreach (range(8710, 8716) as $port) {
            // Commande en TABLEAU (PHP >= 8.0 même sous Windows) : aucun cmd.exe n'est
            // engendré, le fils direct est bien « php -S » et proc_terminate() l'arrête
            // vraiment (avec une commande « string », on tuait le shell et le serveur
            // restait orphelien sur le port).
            $cmd = [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $docroot, $router];
            $proc = @proc_open($cmd, [
                1 => ['file', $this->logFile, 'a'],
                2 => ['file', $this->logFile, 'a'],
            ], $pipes);

            if (!is_resource($proc)) {
                continue;
            }

            $this->process = $proc;
            $this->port    = (int) $port;
            $this->baseUrl = "http://127.0.0.1:{$port}";

            if ($this->waitReady($suite)) {
                $suite->row(
                    'PRE',
                    'Serveur HTTP de test démarré',
                    'php -S 127.0.0.1:' . $port,
                    'démarrage + sonde prête',
                    'sonde prête après attente',
                    'PASS'
                );
                return true;
            }

            $this->stop();
        }

        return false;
    }

    /**
     * Interroge `/__http-test-probe` jusqu'à ce qu'elle réponde (max ~12 s).
     */
    private function waitReady(Suite $suite): bool
    {
        $client = new HttpClient($this->baseUrl);
        for ($i = 0; $i < 48; $i++) {
            try {
                $res = $client->get('/__http-test-probe', ['cookies' => false]);
                if ($res->status === 200) {
                    $json = $res->json();
                    if (is_array($json) && ($json['probe'] ?? null) === 'ok') {
                        $this->probe = $json;
                        return true;
                    }
                }
            } catch (Throwable) {
                // Pas encore à l'écoute : on réessaie.
            }
            usleep(250000);
        }
        return false;
    }

    /** Résultat de la sonde d'isolation (rempli par waitReady()). */
    public ?array $probe = null;

    /**
     * Arrête proprement le serveur (terminaison du processus enfant).
     */
    public function stop(): void
    {
        if (is_resource($this->process)) {
            // Filet de sécurité Windows : si le fils traine encore (ancien mode « string »,
            // ou php récalcitrant), on abat l'arbre complet du PID enfant.
            if (PHP_OS_FAMILY === 'Windows') {
                $status = proc_get_status($this->process);
                $pid    = (int) ($status['pid'] ?? 0);
                if ($pid > 0) {
                    @exec('taskkill /F /T /PID ' . $pid . ' 2>NUL');
                }
            }
            proc_terminate($this->process);
            proc_close($this->process);
        }
        $this->process = null;
    }

    public function __destruct()
    {
        $this->stop();
    }
}

/**
 * Contexte partagé des scénarios : URL de base, connexion PDO de vérification,
 * comptes de test, fabrique de clients HTTP (journaux agrégés pour le bilan 5xx).
 */
final class Ctx
{
    /** @var HttpClient[] tous les clients créés (bilan global des 5xx) */
    public array $clients = [];

    public int $calls = 0;

    public function __construct(
        public readonly string $base,
        public readonly PDO $db,
        public readonly array $ids,
        public readonly string $runId
    ) {
    }

    /** Un nouveau « navigateur » (jar de cookies indépendant). */
    public function client(): HttpClient
    {
        $client = new HttpClient($this->base);
        $this->clients[] = $client;
        return $client;
    }

    /**
     * Ouvre une session par le vrai formulaire HTTP : GET /login → jeton CSRF → POST.
     *
     * @param string $who Clé du compte de test ('userA' | 'userB' | 'admin' | 'modo')
     * @return array{0: HttpClient, 1: HttpResponse}
     */
    public function login(string $who): array
    {
        $client = $this->client();
        $csrf   = $client->csrfFromPage('/login');
        $res = $client->post('/login', [
            'email'      => $this->ids[$who]['email'],
            'password'   => $this->ids['password'],
            'csrf_token' => (string) $csrf,
        ]);
        $this->calls += 2;
        return [$client, $res];
    }

    /**
     * Jeton CSRF lu dans une page réellement rendue par l'application.
     */
    public function csrf(HttpClient $client, string $page): ?string
    {
        $this->calls++;
        return $client->csrfFromPage($page);
    }

    // -----------------------------------------------------------------
    // VÉRIFICATIONS CÔTÉ BASE (après la requête HTTP)
    // -----------------------------------------------------------------

    public function scalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    public function int(string $sql, array $params = []): int
    {
        return (int) $this->scalar($sql, $params);
    }

    public function row(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** Colonne unique (liste de scalaires) — pour sélectionner plusieurs identifiants. */
    public function col(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map(static fn(mixed $v): string => (string) $v, $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}

/**
 * Extrait la valeur du cookie de session envoyée par le serveur (sert à tester le
 * réemploi d'un identifiant détruit). La valeur n'est jamais imprimée.
 */
function session_cookie_value(HttpResponse $res): ?string
{
    foreach ($res->headers as $header) {
        if (stripos($header, 'set-cookie:') !== false && preg_match('/PHPSESSID=([a-z0-9,.-]+)/i', $header, $m) === 1) {
            return $m[1];
        }
    }
    return null;
}

/**
 * Le corps d'une page d'erreur ne doit rien révéler d'interne.
 */
function body_leaks_internals(string $body): bool
{
    $needles = ['SQLSTATE', 'Stack trace', 'Fatal error', 'Warning:', 'Notice:', 'PDO', 'password_hash', '.env'];
    foreach ($needles as $needle) {
        if (stripos($body, $needle) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Le serveur a-t-il renvoyé une redirection 302 vers la cible attendue ?
 */
function redirected_to(HttpResponse $res, string $expectedLocation): bool
{
    return $res->status === 302 && $res->location() === $expectedLocation;
}

/* ===========================================================================
   ÉTAPE PRÉPARATOIRE — objets partagés créés PAR HTTP (aucune insertion SQL
   détournée) pour que les groupes C/D partent d'un état produit par l'app.
   =========================================================================== */

/**
 * @return array{colA?: string, soinA?: string, notifA?: string, secOrchid?: string}
 */
function prerequisites(Suite $s, Ctx $ctx): array
{
    $out = [];

    // --- Seconde orchidée PUBLIÉE, créée par l'admin via la vraie route ---
    [$admin, $res] = $ctx->login('admin');
    $s->check('PRE', 'Connexion admin (formulaire réel)', 'POST /login', '302 → /macollection',
        $res->status . ' → ' . ($res->location() ?? '-'), $res->status === 302 && $res->location() === '/macollection');

    $csrf = $ctx->csrf($admin, '/orchid/new');
    $r = $admin->post('/orchid/new', [
        'id'         => 'httptest-sec-' . $ctx->runId,
        'name'       => 'Zx Secondary ' . $ctx->runId,
        'genre'      => 'Zxgenus',
        'family'     => 'Orchidaceae',
        'short_desc' => 'Seconde fiche publiée de test ' . $ctx->runId,
        'csrf_token' => (string) $csrf,
    ]);
    $s->check('PRE', 'Création admin d\'une seconde fiche publiée', 'POST /orchid/new', '302 → /orchid/httptest_sec_*',
        $r->status . ' → ' . ($r->location() ?? '-'),
        $r->status === 302 && str_starts_with((string) $r->location(), '/orchid/httptest_sec_'));

    $row = $ctx->row(
        'SELECT id FROM orchids WHERE id IN (?, ?)',
        ['httptest-sec-' . $ctx->runId, 'httptest_sec_' . $ctx->runId]
    );
    $out['secOrchid'] = $row === null ? null : (string) $row['id'];
    $s->check('PRE', 'Seconde fiche écrite en base par le serveur', 'SELECT orchids', '1 ligne',
        $out['secOrchid'] === null ? '0 ligne' : '1 ligne', $out['secOrchid'] !== null);

    // --- Collection de userA, créée via HTTP ---
    [$userA, $res] = $ctx->login('userA');
    $s->check('PRE', 'Connexion userA (formulaire réel)', 'POST /login', '302 → /macollection',
        $res->status . ' → ' . ($res->location() ?? '-'), $res->status === 302 && $res->location() === '/macollection');

    $csrf = $ctx->csrf($userA, '/macollection');
    $r = $userA->post('/collection/new', [
        'orchid_id'  => $ctx->ids['markerOrchid'],
        'location'   => 'Fenêtre Est',
        'notes'      => 'Note initiale de test',
        'temp'       => '18-22C',
        'csrf_token' => (string) $csrf,
    ]);
    preg_match('#/collection/([0-9a-f\-]{36})#', (string) $r->location(), $m);
    $out['colA'] = $m[1] ?? null;
    $s->check('PRE', 'userA ajoute une plante via HTTP', 'POST /collection/new', '302 → /collection/{uuid}',
        $r->status . ' → ' . ($out['colA'] !== null ? '/collection/{uuid}' : 'aucune Location'),
        $r->status === 302 && $out['colA'] !== null);

    if ($out['colA'] === null) {
        return $out;
    }

    $s->check('PRE', 'Propriétaire = userA (session, jamais le POST)', 'SELECT collections',
        (string) $ctx->ids['userA']['id'],
        (string) $ctx->scalar('SELECT user_id FROM collections WHERE id = ?', [$out['colA']]),
        $ctx->scalar('SELECT user_id FROM collections WHERE id = ?', [$out['colA']]) === $ctx->ids['userA']['id']);

    // --- Soin + rappel automatique RG3 ---
    $csrf = $ctx->csrf($userA, '/collection/' . $out['colA']);
    $r = $userA->post('/soin/new', [
        'collection_id' => $out['colA'],
        'type'          => 'arrosage',
        'soin_date'     => '2026-10-17',
        'notes'         => 'Soin initial de test',
        'csrf_token'    => (string) $csrf,
    ]);
    $s->check('PRE', 'userA enregistre un soin via HTTP', 'POST /soin/new', '302', (string) $r->status, $r->status === 302);

    $soin = $ctx->row('SELECT id FROM soins WHERE collection_id = ? ORDER BY created_at DESC, id', [$out['colA']]);
    $out['soinA'] = $soin === null ? null : (string) $soin['id'];
    $s->check('PRE', 'Soin écrit en base par le serveur', 'SELECT soins', '1 ligne',
        $out['soinA'] === null ? '0 ligne' : '1 ligne', $out['soinA'] !== null);

    $notif = $ctx->row('SELECT id FROM notifications WHERE collection_id = ? AND type = ? ORDER BY created_at DESC, id', [$out['colA'], 'rappel_soin']);
    $out['notifA'] = $notif === null ? null : (string) $notif['id'];
    $s->check('PRE', 'RG3 — rappel de soin programmé automatiquement', 'SELECT notifications',
        '1 notification rappel_soin', $out['notifA'] === null ? '0' : '1', $out['notifA'] !== null);

    return $out;
}

/* ===========================================================================
   GROUPE A — anonyme, authentification, session, rôles
   =========================================================================== */

function scenarios_a(Suite $s, Ctx $ctx): void
{
    $anon = $ctx->client();

    // A1 — pages publiques : statut réellement attendu (200)
    $publics = [
        'Accueil'               => '/',
        'Encyclopédie'          => '/encyclopedie',
        'Conseils'              => '/conseils',
        'Confidentialité'       => '/confidentialite',
        'Mentions'              => '/mentions',
        'Accessibilité'         => '/accessibilite',
        'Formulaire connexion'  => '/login',
        'Formulaire inscription' => '/register',
    ];
    foreach ($publics as $name => $path) {
        $r = $anon->get($path);
        $ctx->calls++;
        $s->check('A', 'Page publique : ' . $name, 'GET ' . $path, '200', (string) $r->status, $r->status === 200);
    }

    // A2 — routes protégées en anonyme : contrat OBSERVÉ (403), et non supposé
    $protected = [
        'Ma collection (HTML)'          => '/macollection',
        'Liste collections (JSON)'      => '/collections',
        'Administration'                => '/administration',
        'Gestion utilisateurs'          => '/users',
        'Modération'                    => '/moderation',
        'Session courante (JSON)'       => '/me',
        'Notifications'                 => '/notifications',
        'Historique soins (JSON)'       => '/soins',
        'Formulaire création orchidée'  => '/orchid/new',
    ];
    foreach ($protected as $name => $path) {
        $r = $anon->get($path);
        $ctx->calls++;
        $s->check('A', 'Accès anonyme refusé : ' . $name, 'GET ' . $path, '403 (grant refusé)', (string) $r->status, $r->status === 403);
    }

    // A3 — le jeton CSRF vient de la page rendue
    $form = $anon->get('/login');
    $ctx->calls++;
    $token = $form->csrfToken();
    $s->check('A', 'Jeton CSRF rendu dans le formulaire de connexion', 'GET /login',
        'input csrf_token (64 hex)', $token === null ? 'absent' : 'présent',
        $token !== null && preg_match('/^[0-9a-f]{64}$/', $token) === 1);

    // A4 — connexion valide, session persistante
    [$userA, $res] = $ctx->login('userA');
    $s->check('A', 'Connexion valide userA', 'POST /login', '302 → /macollection',
        $res->status . ' → ' . ($res->location() ?? '-'), $res->status === 302 && $res->location() === '/macollection');
    $s->check('A', 'Cookie de session posé à la connexion', 'POST /login', 'Set-Cookie présent',
        $res->sentSessionCookie() ? 'oui' : 'non', $res->sentSessionCookie());

    $me   = $userA->get('/me');
    $json = $me->json();
    $ctx->calls++;
    $s->check('A', 'Persistance de session : /me renvoie l\'utilisateur connecté', 'GET /me',
        '200 + id userA', $me->status . ' id=' . (string) ($json['id'] ?? '-'),
        $me->status === 200 && ($json['id'] ?? null) === $ctx->ids['userA']['id']);
    $s->check('A', 'Contenu de session sans secret', 'GET /me',
        'aucun champ password_hash', isset($json['password_hash']) ? 'champ présent' : 'absent',
        !isset($json['password_hash']));

    $dash = $userA->get('/macollection');
    $ctx->calls++;
    $s->check('A', 'Page privée accessible après connexion', 'GET /macollection', '200', (string) $dash->status, $dash->status === 200);

    $again = $userA->get('/login');
    $ctx->calls++;
    $s->check('A', 'GET /login déjà connecté renvoie vers l\'espace privé', 'GET /login', '302 → /macollection',
        $again->status . ' → ' . ($again->location() ?? '-'), $again->status === 302 && $again->location() === '/macollection');

    // A5 — identifiants invalides : aucune session ouverte
    $bad = $ctx->client();
    $tok = $ctx->csrf($bad, '/login');
    $r = $bad->post('/login', ['email' => $ctx->ids['userA']['email'], 'password' => 'MauvaisMotDePasse1!', 'csrf_token' => (string) $tok]);
    $ctx->calls++;
    $s->check('A', 'Connexion avec mot de passe erroné', 'POST /login', '302 → /login',
        $r->status . ' → ' . ($r->location() ?? '-'), $r->status === 302 && $r->location() === '/login');

    $meBad = $bad->get('/me');
    $ctx->calls++;
    $s->check('A', 'Aucune session ouverte après mot de passe erroné', 'GET /me', '403', (string) $meBad->status, $meBad->status === 403);

    $unk = $ctx->client();
    $tok = $ctx->csrf($unk, '/login');
    $r = $unk->post('/login', ['email' => 'http+inexistant.' . $ctx->runId . '@example.invalid', 'password' => 'peu importe 1!', 'csrf_token' => (string) $tok]);
    $ctx->calls++;
    $s->check('A', 'Connexion avec email inconnu', 'POST /login', '302 → /login',
        $r->status . ' → ' . ($r->location() ?? '-'), $r->status === 302 && $r->location() === '/login');

    $anonPwd = $ctx->client();
    $tok = $ctx->csrf($anonPwd, '/login');
    $r = $anonPwd->post('/login', ['email' => $ctx->ids['admin']['email'], 'password' => '', 'csrf_token' => (string) $tok]);
    $ctx->calls++;
    $s->check('A', 'Connexion avec mot de passe vide', 'POST /login', '302 → /login',
        $r->status . ' → ' . ($r->location() ?? '-'), $r->status === 302 && $r->location() === '/login');

    // A6 — inscription par HTTP puis connexion avec le compte créé
    $reg = $ctx->client();
    $tok = $ctx->csrf($reg, '/register');
    $regEmail = 'http+reg.' . $ctx->runId . '@example.invalid';
    $r = $reg->post('/register', [
        'nom'        => 'Inscrite',
        'prenom'     => 'Test',
        'email'      => $regEmail,
        'password'   => 'Insc12!test',
        'csrf_token' => (string) $tok,
    ]);
    $s->check('A', 'Inscription valide', 'POST /register', '302 → /login',
        $r->status . ' → ' . ($r->location() ?? '-'), $r->status === 302 && $r->location() === '/login');

    $newUser = $ctx->row('SELECT role FROM users WHERE email = ?', [$regEmail]);
    $s->check('A', 'Compte réellement créé en base par le serveur', 'SELECT users', '1 ligne',
        $newUser === null ? '0 ligne' : '1 ligne', $newUser !== null);
    $s->check('A', 'Rôle forcé à « user » (pas d\'élévation par le formulaire public)', 'SELECT users',
        'user', (string) ($newUser['role'] ?? '-'), ($newUser['role'] ?? null) === 'user');

    $tok = $ctx->csrf($reg, '/login');
    $r = $reg->post('/login', ['email' => $regEmail, 'password' => 'Insc12!test', 'csrf_token' => (string) $tok]);
    $s->check('A', 'Connexion possible avec le compte inscrit (hash opérationnel)', 'POST /login',
        '302 → /macollection', $r->status . ' → ' . ($r->location() ?? '-'),
        $r->status === 302 && $r->location() === '/macollection');

    // A7 — régénération d'identifiant, déconnexion, réemploi d'un identifiant détruit
    $fix = $ctx->client();
    $pageFix = $fix->get('/login');
    $ctx->calls++;
    $sid0 = session_cookie_value($pageFix);
    $resFix = $fix->post('/login', [
        'email'      => $ctx->ids['userB']['email'],
        'password'   => $ctx->ids['password'],
        'csrf_token' => (string) $pageFix->csrfToken(),
    ]);
    $sid1 = session_cookie_value($resFix);
    $s->check('A', 'Régénération d\'identifiant de session à la connexion (anti-figement)', 'POST /login',
        'identifiant différent de celui d\'avant connexion',
        ($sid0 === null || $sid1 === null) ? 'non observé' : ($sid0 === $sid1 ? 'identique' : 'différent'),
        $sid0 !== null && $sid1 !== null && $sid0 !== $sid1);

    $tok = $ctx->csrf($fix, '/macollection');
    $out = $fix->post('/logout', ['csrf_token' => (string) $tok]);
    $s->check('A', 'Déconnexion', 'POST /logout', '302 → /login',
        $out->status . ' → ' . ($out->location() ?? '-'), $out->status === 302 && $out->location() === '/login');

    $after = $fix->get('/me');
    $ctx->calls++;
    $s->check('A', 'Session inutilisable après déconnexion (même navigateur)', 'GET /me', '403', (string) $after->status, $after->status === 403);

    $replay = $ctx->client();
    $stolen = $replay->get('/me', [
        'cookies' => false,
        'headers' => $sid1 === null ? [] : ['Cookie: PHPSESSID=' . $sid1],
    ]);
    $ctx->calls++;
    $s->check('A', 'Réemploi d\'un identifiant de session détruit par la déconnexion', 'GET /me (ancien PHPSESSID)',
        '403', (string) $stolen->status, $stolen->status === 403);

    // A8 — matrice des rôles : droits distincts, pas un code unique supposé
    $roles = [
        'userA → administration refusé'        => ['userA', '/administration', 403],
        'admin → administration accordé'       => ['admin', '/administration', 200],
        'modérateur → administration refusé'   => ['modo', '/administration', 403],
        'modérateur → modération accordé'      => ['modo', '/moderation', 200],
        'admin → modération accordé'           => ['admin', '/moderation', 200],
        'userA → modération refusé'            => ['userA', '/moderation', 403],
        'userA → utilisateurs refusé'          => ['userA', '/users', 403],
        'modérateur → utilisateurs refusé'     => ['modo', '/users', 403],
        'admin → utilisateurs accordé'         => ['admin', '/users', 200],
        'userA → formulaire orchidée refusé'   => ['userA', '/orchid/new', 403],
    ];
    foreach ($roles as $name => [$who, $path, $expected]) {
        [$c, $res] = $ctx->login($who);
        $r = $c->get($path);
        $ctx->calls++;
        $s->check('A', 'Rôles : ' . $name, 'GET ' . $path, (string) $expected, (string) $r->status, $r->status === $expected);
    }
}

/* ===========================================================================
   GROUPE B — CSRF : jeton absent, invalide, valide, sans session
   =========================================================================== */

function scenarios_b(Suite $s, Ctx $ctx, array $pre): void
{
    // B1 — route publique /login
    $c = $ctx->client();
    $r = $c->post('/login', ['email' => $ctx->ids['userA']['email'], 'password' => $ctx->ids['password']]);
    $s->check('B', 'POST /login sans jeton CSRF', 'POST /login', '400 (formulaire expiré)', (string) $r->status, $r->status === 400);

    $c = $ctx->client();
    $tok = $ctx->csrf($c, '/login');
    $r = $c->post('/login', ['email' => $ctx->ids['userA']['email'], 'password' => $ctx->ids['password'], 'csrf_token' => str_repeat('0', 64)]);
    $s->check('B', 'POST /login avec jeton CSRF erroné', 'POST /login', '400', (string) $r->status, $r->status === 400);

    $me = $c->get('/me');
    $ctx->calls++;
    $s->check('B', 'Bon mot de passe + mauvais jeton = aucune session', 'GET /me', '403', (string) $me->status, $me->status === 403);

    // B2 — collection : rejet CSRF sans effet d'bord
    [$userA, $res] = $ctx->login('userA');
    $before = $ctx->int('SELECT COUNT(*) FROM collections WHERE user_id = ?', [$ctx->ids['userA']['id']]);

    $r = $userA->post('/collection/new', ['orchid_id' => (string) $pre['secOrchid']]);
    $s->check('B', 'POST /collection/new sans jeton CSRF', 'POST /collection/new', '400', (string) $r->status, $r->status === 400);
    $after = $ctx->int('SELECT COUNT(*) FROM collections WHERE user_id = ?', [$ctx->ids['userA']['id']]);
    $s->check('B', 'Aucune écriture en base sur rejet CSRF (collection)', 'SELECT collections',
        (string) $before, (string) $after, $before === $after);

    $r = $userA->post('/collection/new', ['orchid_id' => (string) $pre['secOrchid'], 'csrf_token' => str_repeat('a', 64)]);
    $s->check('B', 'POST /collection/new avec jeton CSRF invalide', 'POST /collection/new', '400', (string) $r->status, $r->status === 400);

    // B3 — notification marquage lu
    if (($pre['notifA'] ?? null) !== null) {
        $r = $userA->post('/notification/' . $pre['notifA'] . '/read', []);
        $s->check('B', 'POST /notification/{id}/read sans jeton CSRF', 'POST /notification/{id}/read', '400', (string) $r->status, $r->status === 400);
        $s->check('B', 'Le rejet CSRF ne déclenche pas l\'action métier', 'SELECT notifications', 'is_read = 0',
            'is_read = ' . (string) $ctx->scalar('SELECT is_read FROM notifications WHERE id = ?', [$pre['notifA']]),
            (int) $ctx->scalar('SELECT is_read FROM notifications WHERE id = ?', [$pre['notifA']]) === 0);

        $r = $userA->post('/notification/' . $pre['notifA'] . '/read', ['csrf_token' => str_repeat('b', 64)]);
        $s->check('B', 'POST /notification/{id}/read avec jeton invalide', 'POST /notification/{id}/read', '400', (string) $r->status, $r->status === 400);

        $tok = $ctx->csrf($userA, '/notifications');
        $r = $userA->post('/notification/' . $pre['notifA'] . '/read', ['csrf_token' => (string) $tok]);
        $s->check('B', 'POST /notification/{id}/read avec jeton valide (cas positif)', 'POST /notification/{id}/read',
            '302 → /notifications', $r->status . ' → ' . ($r->location() ?? '-'),
            $r->status === 302 && $r->location() === '/notifications');
        $s->check('B', 'L\'action autorisée est bien appliquée en base', 'SELECT notifications', 'is_read = 1',
            'is_read = ' . (string) $ctx->scalar('SELECT is_read FROM notifications WHERE id = ?', [$pre['notifA']]),
            (int) $ctx->scalar('SELECT is_read FROM notifications WHERE id = ?', [$pre['notifA']]) === 1);
    }

    // B4 — modération : le jeton protège la publication
    [$modo, $res] = $ctx->login('modo');
    $pending = (string) $ctx->ids['pendingOrchid'];
    $pubBefore = (string) $ctx->scalar('SELECT is_published FROM orchids WHERE id = ?', [$pending]);

    $tok = $ctx->csrf($modo, '/moderation');
    $r = $modo->post('/moderation/' . rawurlencode($pending) . '/approve', ['csrf_token' => substr((string) $tok, 0, 62) . 'ff']);
    $s->check('B', 'POST /moderation/{id}/approve avec jeton altéré', 'POST /moderation/{id}/approve', '400', (string) $r->status, $r->status === 400);
    $pubAfter = (string) $ctx->scalar('SELECT is_published FROM orchids WHERE id = ?', [$pending]);
    $s->check('B', 'Proposition toujours non publiée après rejet CSRF', 'SELECT orchids', $pubBefore, $pubAfter, $pubBefore === $pubAfter);

    $r = $modo->post('/moderation/' . rawurlencode($pending) . '/approve', []);
    $s->check('B', 'POST /moderation/{id}/approve sans jeton CSRF', 'POST /moderation/{id}/approve', '400', (string) $r->status, $r->status === 400);

    // B5 — /logout protégé : la session doit survivre au rejet
    [$keep, $res] = $ctx->login('userA');
    $r = $keep->post('/logout', []);
    $s->check('B', 'POST /logout sans jeton CSRF', 'POST /logout', '400', (string) $r->status, $r->status === 400);
    $me = $keep->get('/me');
    $ctx->calls++;
    $s->check('B', 'La déconnexion CSRF-bloquée ne détruit pas la session', 'GET /me', '200', (string) $me->status, $me->status === 200);

    // B6 — proposition d'orchidée : absence de jeton = aucune fiche créée
    [$userB, $res] = $ctx->login('userB');
    $propId = 'httptest-b6-' . $ctx->runId;
    $r = $userB->post('/orchid/propose', ['id' => $propId, 'name' => 'Proposition CSRF ' . $ctx->runId, 'genre' => 'Cattleya']);
    $s->check('B', 'POST /orchid/propose sans jeton CSRF', 'POST /orchid/propose', '400', (string) $r->status, $r->status === 400);
    $found = $ctx->int('SELECT COUNT(*) FROM orchids WHERE id IN (?, ?)', [$propId, str_replace('-', '_', $propId)]);
    $s->check('B', 'Aucune fiche créée sur rejet CSRF (proposition)', 'SELECT orchids', '0 ligne', (string) $found, $found === 0);

    $tok = $ctx->csrf($userB, '/macollection');
    $r = $userB->post('/orchid/propose', [
        'id'         => $propId,
        'name'       => 'Proposition CSRF ' . $ctx->runId,
        'genre'      => 'Cattleya',
        'csrf_token' => (string) $tok,
    ]);
    $s->check('B', 'POST /orchid/propose avec jeton valide (cas positif)', 'POST /orchid/propose',
        '302 → /encyclopedie', $r->status . ' → ' . ($r->location() ?? '-'),
        $r->status === 302 && $r->location() === '/encyclopedie');

    // B7 — sans session : l'autorisation prime sur le CSRF (contrat OBSERVÉ, non supposé)
    $nosess = $ctx->client();
    $r = $nosess->post('/orchid/propose',
        ['id' => 'httptest-b7-' . $ctx->runId, 'name' => 'Xyz ' . $ctx->runId, 'genre' => 'Y', 'csrf_token' => str_repeat('c', 64)],
        ['cookies' => false]);
    $s->check('B', 'POST protégé sans session (jeton forgé) : refus d\'accès d\'abord', 'POST /orchid/propose',
        '403 (grant avant CSRF)', (string) $r->status, $r->status === 403);

    // B8 — page d'erreur CSRF homogène, sans fuite interne
    $c = $ctx->client();
    $r = $c->post('/collection/new', ['orchid_id' => 'x']);
    $s->check('B', 'Page d\'erreur 400 sans fuite technique', 'POST /collection/new',
        'corps sans trace interne', body_leaks_internals($r->body) ? 'fuite détectée' : 'aucune fuite', !body_leaks_internals($r->body));

    // B9 — aucun 500 sur l'ensemble des parcours CSRF
    $errors = 0;
    foreach ($ctx->clients as $cl) {
        $errors += $cl->countServerErrors();
    }
    $s->check('B', 'Aucune erreur serveur sur les parcours CSRF', 'requêtes cumulées du harnais',
        '0 réponse 5xx', (string) $errors, $errors === 0);
}

/* ===========================================================================
   GROUPE C — RG1 / IDOR : l'identifiant visé appartient à un autre utilisateur
   =========================================================================== */

function scenarios_c(Suite $s, Ctx $ctx, array $pre): void
{
    if (($pre['colA'] ?? null) === null) {
        $s->blocked('C', 'Parcours RG1 (collections, soins, notifications)', 'GET/POST /collection, /soin, /notification',
            'prérequis de collection non créé : aucune ressource à attaquer');
        return;
    }

    $colA   = (string) $pre['colA'];
    $soinA  = $pre['soinA'] ?? null;
    $notifA = $pre['notifA'] ?? null;

    [$userB, $res] = $ctx->login('userB');
    $tok = $ctx->csrf($userB, '/macollection');

    // C1 — lecture croisée
    $r = $userB->get('/collection/' . $colA);
    $s->check('C', 'Lecture de la fiche d\'une autre utilisateur', 'GET /collection/{idA}', '404', (string) $r->status, $r->status === 404);
    $s->check('C', 'La fiche refusée ne fuit pas le contenu d\'autrui', 'GET /collection/{idA}',
        'aucune donnée d\'autrui dans le HTML', body_contains($r->body, 'Note initiale de test') ? 'contenu présent' : 'contenu absent',
        !body_contains($r->body, 'Note initiale de test'));

    // C2 — modification croisée sur id manipulable
    $notesBefore = (string) $ctx->scalar('SELECT notes FROM collections WHERE id = ?', [$colA]);
    $r = $userB->post('/update/collection', ['id' => $colA, 'notes' => 'PIED DE NEZ', 'csrf_token' => (string) $tok]);
    $s->check('C', 'Modification d\'une collection d\'autrui', 'POST /update/collection',
        '302 → /macollection (refus)', $r->status . ' → ' . ($r->location() ?? '-'),
        $r->status === 302 && $r->location() === '/macollection');
    $notesAfter = (string) $ctx->scalar('SELECT notes FROM collections WHERE id = ?', [$colA]);
    $s->check('C', 'Base inchangée après modification croisée (notes)', 'SELECT collections',
        $notesBefore, $notesAfter, $notesBefore === $notesAfter);

    // C3 — suppression croisée
    $r = $userB->post('/collection/delete/' . $colA, ['csrf_token' => (string) $tok]);
    $s->check('C', 'Suppression d\'une collection d\'autrui', 'POST /collection/delete/{idA}',
        '302 → /macollection', $r->status . ' → ' . ($r->location() ?? '-'), $r->status === 302);
    $s->check('C', 'La collection visée existe toujours en base', 'SELECT collections', '1 ligne',
        (string) $ctx->int('SELECT COUNT(*) FROM collections WHERE id = ?', [$colA]),
        $ctx->int('SELECT COUNT(*) FROM collections WHERE id = ?', [$colA]) === 1);
    $s->check('C', 'Et toujours propriété de userA', 'SELECT collections', (string) $ctx->ids['userA']['id'],
        (string) $ctx->scalar('SELECT user_id FROM collections WHERE id = ?', [$colA]),
        $ctx->scalar('SELECT user_id FROM collections WHERE id = ?', [$colA]) === $ctx->ids['userA']['id']);

    // C4/C5/C6 — soins croisés
    if ($soinA !== null) {
        $soinsBefore = $ctx->int('SELECT COUNT(*) FROM soins');
        $r = $userB->post('/soin/new', ['collection_id' => $colA, 'type' => 'arrosage', 'csrf_token' => (string) $tok]);
        $s->check('C', 'Ajout d\'un soin sur la plante d\'autrui', 'POST /soin/new',
            '302 → /macollection', $r->status . ' → ' . ($r->location() ?? '-'),
            $r->status === 302 && $r->location() === '/macollection');
        $s->check('C', 'Aucun soin ajouté en base par l\'assaillant', 'SELECT soins', (string) $soinsBefore,
            (string) $ctx->int('SELECT COUNT(*) FROM soins'), $ctx->int('SELECT COUNT(*) FROM soins') === $soinsBefore);

        $soinNotes = (string) $ctx->scalar('SELECT notes FROM soins WHERE id = ?', [$soinA]);
        $r = $userB->post('/update/soin', ['id' => $soinA, 'type' => 'ravageurs', 'notes' => 'PIED DE NEZ', 'csrf_token' => (string) $tok]);
        $s->check('C', 'Modification d\'un soin d\'autrui', 'POST /update/soin',
            '302 → /macollection', $r->status . ' → ' . ($r->location() ?? '-'),
            $r->status === 302 && $r->location() === '/macollection');
        $s->check('C', 'Soin d\'autrui inchangé en base', 'SELECT soins', $soinNotes,
            (string) $ctx->scalar('SELECT notes FROM soins WHERE id = ?', [$soinA]),
            (string) $ctx->scalar('SELECT notes FROM soins WHERE id = ?', [$soinA]) === $soinNotes);

        $r = $userB->post('/soin/delete/' . $soinA, ['csrf_token' => (string) $tok]);
        $s->check('C', 'Suppression d\'un soin d\'autrui', 'POST /soin/delete/{id}',
            '302 → /macollection', $r->status . ' → ' . ($r->location() ?? '-'), $r->status === 302);
        $s->check('C', 'Le soin visé n\'a pas été supprimé', 'SELECT soins', '1 ligne',
            (string) $ctx->int('SELECT COUNT(*) FROM soins WHERE id = ?', [$soinA]),
            $ctx->int('SELECT COUNT(*) FROM soins WHERE id = ?', [$soinA]) === 1);
    }

    // C7/C8/C9 — notifications croisées
    if ($notifA !== null) {
        $readBefore = (string) $ctx->scalar('SELECT is_read FROM notifications WHERE id = ?', [$notifA]);
        $r = $userB->get('/notification/' . $notifA);
        $s->check('C', 'Lecture d\'une notification d\'autrui', 'GET /notification/{id}', '404', (string) $r->status, $r->status === 404);
        $s->check('C', 'La lecture croisée ne modifie pas l\'état lu', 'SELECT notifications', 'is_read = ' . $readBefore,
            'is_read = ' . (string) $ctx->scalar('SELECT is_read FROM notifications WHERE id = ?', [$notifA]),
            (string) $ctx->scalar('SELECT is_read FROM notifications WHERE id = ?', [$notifA]) === $readBefore);

        $r = $userB->post('/notification/' . $notifA . '/delete', ['csrf_token' => (string) $tok]);
        $s->check('C', 'Route de suppression avec id d\'autrui dans un chemin inattendu', 'POST /notification/{id}/delete',
            '404 (route non déclarée)', (string) $r->status, $r->status === 404);

        $countBefore = $ctx->int('SELECT COUNT(*) FROM notifications WHERE id = ?', [$notifA]);
        $r = $userB->post('/notification/delete/' . $notifA, ['csrf_token' => (string) $tok]);
        $s->check('C', 'Suppression de la notification d\'autrui', 'POST /notification/delete/{id}',
            '302 → /notifications', $r->status . ' → ' . ($r->location() ?? '-'), $r->status === 302);
        $s->check('C', 'Notification d\'autrui toujours présente en base', 'SELECT notifications', (string) $countBefore,
            (string) $ctx->int('SELECT COUNT(*) FROM notifications WHERE id = ?', [$notifA]),
            $ctx->int('SELECT COUNT(*) FROM notifications WHERE id = ?', [$notifA]) === $countBefore);
    }

    // C10 — usurpation par champ POST « user_id »
    $adminCount = $ctx->int('SELECT COUNT(*) FROM collections WHERE user_id = ?', [$ctx->ids['admin']['id']]);
    $userBCount = $ctx->int('SELECT COUNT(*) FROM collections WHERE user_id = ?', [$ctx->ids['userB']['id']]);
    $r = $userB->post('/collection/new', [
        'orchid_id'  => (string) $pre['secOrchid'],
        'user_id'    => $ctx->ids['admin']['id'],
        'notes'      => 'tentative d\'usurpation',
        'csrf_token' => (string) $tok,
    ]);
    $s->check('C', 'Ajout de collection avec user_id soumis (usurpation)', 'POST /collection/new',
        '302 → /collection/{id}', $r->status . ' → ' . (str_starts_with((string) $r->location(), '/collection/') ? '/collection/{id}' : (string) $r->location()),
        $r->status === 302 && str_starts_with((string) $r->location(), '/collection/'));

    preg_match('#/collection/([0-9a-f\-]{36})#', (string) $r->location(), $m);
    $owner = isset($m[1]) ? (string) $ctx->scalar('SELECT user_id FROM collections WHERE id = ?', [$m[1]]) : null;
    $s->check('C', 'L\'entrée créée appartient à la session, jamais au champ soumis', 'SELECT collections',
        (string) $ctx->ids['userB']['id'], (string) $owner, $owner === $ctx->ids['userB']['id']);
    $s->check('C', 'Aucune entrée ajoutée au compte visé par l\'usurpation', 'SELECT collections',
        (string) $adminCount, (string) $ctx->int('SELECT COUNT(*) FROM collections WHERE user_id = ?', [$ctx->ids['admin']['id']]),
        $ctx->int('SELECT COUNT(*) FROM collections WHERE user_id = ?', [$ctx->ids['admin']['id']]) === $adminCount);
    $s->check('C', 'L\'appelant a gagné exactement une entrée', 'SELECT collections', (string) ($userBCount + 1),
        (string) $ctx->int('SELECT COUNT(*) FROM collections WHERE user_id = ?', [$ctx->ids['userB']['id']]),
        $ctx->int('SELECT COUNT(*) FROM collections WHERE user_id = ?', [$ctx->ids['userB']['id']]) === $userBCount + 1);

    // C11 — droits back-office assumés sur les comptes
    $r = $userB->get('/user/' . $ctx->ids['admin']['id']);
    $s->check('C', 'Un utilisateur simple ne lit pas la fiche d\'un autre compte', 'GET /user/{id}', '403', (string) $r->status, $r->status === 403);

    [$admin, $res] = $ctx->login('admin');
    $r = $admin->get('/user/' . $ctx->ids['userB']['id']);
    $s->check('C', 'L\'admin lit la fiche d\'un utilisateur (droit back-office)', 'GET /user/{id}', '200', (string) $r->status, $r->status === 200);

    $list = $userB->get('/collections');
    $ctx->calls++;
    $foreign = false;
    foreach ((array) ($list->json() ?? []) as $item) {
        if (($item['id'] ?? null) === $colA) {
            $foreign = true;
        }
    }
    $s->check('C', 'GET /collections (JSON) ne renvoie que les entrées de l\'appelant', 'GET /collections',
        'aucun identifiant d\'autrui', $foreign ? 'identifiant d\'autrui présent' : 'aucun', !$foreign);
}

/** Le corps contient-il cette chaîne ? (teste de fuite de contenu) */
function body_contains(string $body, string $needle): bool
{
    return str_contains($body, $needle);
}

/* ===========================================================================
   GROUPE D — parcours métier (lecture, collection, soins, notifications,
   orchidées, conseils, utilisateurs, modération)
   =========================================================================== */

function scenarios_d(Suite $s, Ctx $ctx, array $pre): void
{
    $marker  = (string) $ctx->ids['markerOrchid'];
    $pending = (string) $ctx->ids['pendingOrchid'];

    // --- D1 catalogue public en JSON : publiée visible, proposition masquée ---
    $anon = $ctx->client();
    $r = $anon->get('/orchids');
    $ctx->calls++;
    $ids = array_column((array) ($r->json() ?? []), 'id');
    $s->check('D', 'Catalogue JSON', 'GET /orchids', '200', (string) $r->status, $r->status === 200);
    $s->check('D', 'La fiche publiée de test est servie', 'GET /orchids', 'contient la fiche marqueur',
        in_array($marker, $ids, true) ? 'présente' : 'absente', in_array($marker, $ids, true));
    $s->check('D', 'La proposition non publiée est masquée', 'GET /orchids', 'ne contient pas la proposition',
        in_array($pending, $ids, true) ? 'présente' : 'absente', !in_array($pending, $ids, true));

    // --- D2 recherche plein-texte (marqueur unique dans short_desc) ---
    $r = $anon->get('/orchids/search?q=zqx' . $ctx->runId);
    $ctx->calls++;
    $hit = array_column((array) ($r->json() ?? []), 'id');
    $s->check('D', 'Recherche d\'orchidées sur un terme unique', 'GET /orchids/search?q=…',
        '200 + 1 résultat (marqueur)', $r->status . ' / ' . count($hit) . ' résultat(s)',
        $r->status === 200 && in_array($marker, $hit, true));

    $r = $anon->get('/orchids/search');
    $ctx->calls++;
    $s->check('D', 'Recherche sans terme', 'GET /orchids/search', '200 + tableau vide',
        $r->status . ' / ' . json_encode($r->json()), $r->status === 200 && $r->json() === []);

    // --- D3 fiche HTML ---
    $r = $anon->get('/orchid/' . rawurlencode($marker));
    $ctx->calls++;
    $s->check('D', 'Fiche détaillée publiée', 'GET /orchid/{id}', '200 + titre de la fiche dans le HTML',
        (string) $r->status, $r->status === 200 && body_contains($r->body, 'Phalaenopsis Test ' . $ctx->runId));

    $r = $anon->get('/orchid/' . rawurlencode($pending));
    $ctx->calls++;
    $s->check('D', 'Fiche non publiée inaccessible au public', 'GET /orchid/{id}', '404', (string) $r->status, $r->status === 404);

    // --- D4 conseils (lecture publique) ---
    $r = $anon->get('/conseils');
    $ctx->calls++;
    $s->check('D', 'Liste publique des conseils', 'GET /conseils', '200', (string) $r->status, $r->status === 200);

    // --- D5 cycle de vie d\'une collection (userB) ---
    [$userB, $res] = $ctx->login('userB');
    $tok = $ctx->csrf($userB, '/macollection');
    $r = $userB->post('/collection/new', [
        'orchid_id'  => $marker,
        'location'   => 'Baie sud',
        'notes'      => 'Nouvelle acquisition',
        'csrf_token' => (string) $tok,
    ]);
    preg_match('#/collection/([0-9a-f\-]{36})#', (string) $r->location(), $m);
    $colB = $m[1] ?? null;
    $s->check('D', 'Ajout d\'une plante à la collection', 'POST /collection/new', '302 → /collection/{uuid}',
        $r->status . ' → ' . ($colB !== null ? '/collection/{uuid}' : 'sans Location'), $r->status === 302 && $colB !== null);

    if ($colB === null) {
        $s->blocked('D', 'Suite du cycle collection / soins / notifications', 'GET/POST /collection, /soin, /notification',
            'aucune collection créée par le serveur : rien à poursuivre');
        return;
    }

    $r = $userB->get('/collection/' . $colB);
    $ctx->calls++;
    $s->check('D', 'Fiche de la plante ajoutée (propriétaire)', 'GET /collection/{id}', '200', (string) $r->status, $r->status === 200);

    $tok = $ctx->csrf($userB, '/collection/' . $colB);
    $r = $userB->post('/update/collection', ['id' => $colB, 'notes' => 'Note modifiée par le propriétaire', 'hygro' => '65%', 'csrf_token' => (string) $tok]);
    $s->check('D', 'Modification par le propriétaire', 'POST /update/collection', '302 → /collection/{id}',
        $r->status . ' → ' . (str_starts_with((string) $r->location(), '/collection/') ? '/collection/{id}' : (string) $r->location()),
        $r->status === 302);
    $s->check('D', 'Modification réellement persistée', 'SELECT collections', 'Note modifiée par le propriétaire',
        (string) $ctx->scalar('SELECT notes FROM collections WHERE id = ?', [$colB]),
        $ctx->scalar('SELECT notes FROM collections WHERE id = ?', [$colB]) === 'Note modifiée par le propriétaire');

    $list = $userB->get('/collections');
    $ctx->calls++;
    $seen = in_array($colB, array_column((array) ($list->json() ?? []), 'id'), true);
    $s->check('D', 'La liste JSON de l\'espace privé contient la nouvelle entrée', 'GET /collections',
        '200 + entrée présente', $list->status . ' / ' . ($seen ? 'présente' : 'absente'), $list->status === 200 && $seen);

    // --- D6 soins + rappel RG3 (3 soins sur la même plante → 1 seul rappel, dédupplique) ---
    $soinIds = [];
    for ($i = 1; $i <= 2; $i++) {
        $tok = $ctx->csrf($userB, '/collection/' . $colB);
        $r = $userB->post('/soin/new', [
            'collection_id' => $colB,
            'type'          => $i === 1 ? 'arrosage' : 'rempotage',
            'soin_date'     => '2026-10-' . (16 + $i),
            'notes'         => 'Soin ' . $i . ' de la suite HTTP',
            'csrf_token'    => (string) $tok,
        ]);
        $s->check('D', 'Enregistrement du soin ' . $i, 'POST /soin/new', '302 → /collection/{id}',
            (string) $r->status, $r->status === 302);
    }
    $soins = $userB->get('/soins');
    $ctx->calls++;
    $soinIds = array_column((array) ($soins->json() ?? []), 'id');
    $s->check('D', 'Historique JSON des soins de l\'utilisateur', 'GET /soins',
        '200 + 2 soins', $soins->status . ' / ' . count($soinIds) . ' soin(s)', $soins->status === 200 && count($soinIds) >= 2);

    // RG3 étendu : déduplication. 2 soins rapprochés sur la même plante = 1 seul
    // rappel `planifie` (la ligne est mise à jour, pas accumulée).
    $notifCount = $ctx->int('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND collection_id = ? AND type = ?',
        [$ctx->ids['userB']['id'], $colB, 'rappel_soin']);
    $s->check('D', 'RG3 étendu — un seul rappel par plante (dédupliqué à chaque soin)', 'SELECT notifications',
        '1 rappel', (string) $notifCount, $notifCount === 1);
    $rappelB = $ctx->scalar("SELECT id FROM notifications WHERE collection_id = ? AND type = 'rappel_soin' ORDER BY notif_date DESC LIMIT 1", [$colB]);
    $statutB = (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$rappelB]);
    $s->check('D', 'RG3 — le rappel est écrit en statut « planifie » (échéance future)', 'SELECT notifications',
        'planifie', $statutB, $statutB === 'planifie');

    $soinUpd = $soinIds[0] ?? null;
    if ($soinUpd !== null) {
        $tok = $ctx->csrf($userB, '/collection/' . $colB);
        $r = $userB->post('/update/soin', ['id' => $soinUpd, 'type' => 'engrais', 'notes' => 'Soin modifié', 'csrf_token' => (string) $tok]);
        $s->check('D', 'Modification d\'un soin', 'POST /update/soin', '302 → /collection/{id}', (string) $r->status, $r->status === 302);
        // Jointure vers la table de lookup types_soin (3NF sélective — migration 20261018).
        $sqlLabel = 'SELECT ts.label FROM soins s JOIN types_soin ts ON ts.id = s.id_type_soin WHERE s.id = ?';
        $s->check('D', 'Soin modifié en base', 'SELECT soins JOIN types_soin', 'engrais',
            (string) $ctx->scalar($sqlLabel, [$soinUpd]),
            $ctx->scalar($sqlLabel, [$soinUpd]) === 'engrais');
    }

    // Type de soin inconnu → repli métier 'arrosage' (jamais une erreur serveur).
    // La note est rendue unique : sans elle, deux soins créés dans la même seconde sont
    // strictement ex-aequo sur « ORDER BY created_at DESC » et la requête de contrôle peut
    // relire la ligne précédente au lieu de la nouvelle.
    $tok = $ctx->csrf($userB, '/collection/' . $colB);
    $noteRepli = 'repli type ' . $ctx->runId;
    $r = $userB->post('/soin/new', ['collection_id' => $colB, 'type' => 'type-inexistant', 'notes' => $noteRepli, 'csrf_token' => (string) $tok]);
    $repli = (string) $ctx->scalar('SELECT ts.label FROM soins s JOIN types_soin ts ON ts.id = s.id_type_soin WHERE s.collection_id = ? AND s.notes = ?', [$colB, $noteRepli]);
    $s->check('D', 'Type de soin inconnu : repli sans erreur serveur', 'POST /soin/new',
        'arrosage (repli)', $r->status . ' / ' . ($repli === '' ? 'aucune ligne' : $repli),
        $r->status === 302 && $repli === 'arrosage');

    // --- D7 notifications de userB (fraîches, indépendantes du groupe B) ---
    // Prérequis : 2 notifications non lues. Le rappel étant déduppliqué PAR PLANTE
    // et l'anti-doublon « une espèce = une entrée de collection » interdit de
    // redonner la même plante, on ajoute une SECONDE espèce déjà publiée
    // (fixture `publishedByName`) puis un soin dessus : chaque plante porte son
    // propre rappel — preuve complémentaire de RG3 étendu.
    $secondeEspece = (string) $ctx->ids['publishedByName'];
    $tok = $ctx->csrf($userB, '/macollection');
    $r = $userB->post('/collection/new', [
        'orchid_id'  => $secondeEspece,
        'location'   => 'Fenêtre nord',
        'notes'      => 'Seconde plante (prérequis D7)',
        'csrf_token' => (string) $tok,
    ]);
    preg_match('#/collection/([0-9a-f\-]{36})#', (string) $r->location(), $mB2);
    $colB2 = $mB2[1] ?? null;
    $s->check('D', 'Prérequis D7 — seconde espèce publiée ajoutée à la collection', 'POST /collection/new', '302 → /collection/{uuid}',
        $r->status . ' → ' . ($colB2 !== null ? '/collection/{uuid}' : (string) $r->location()), $r->status === 302 && $colB2 !== null);
    if ($colB2 !== null) {
        $tok = $ctx->csrf($userB, '/collection/' . $colB2);
        $r = $userB->post('/soin/new', [
            'collection_id' => $colB2,
            'type'          => 'arrosage',
            'soin_date'     => '2026-10-17',
            'notes'         => 'Soin de la seconde plante (D7)',
            'csrf_token'    => (string) $tok,
        ]);
        $s->check('D', 'Prérequis D7 — soin sur la seconde plante (génère son rappel)', 'POST /soin/new', '302',
            (string) $r->status, $r->status === 302);
    }

    $r = $userB->get('/notifications');
    $ctx->calls++;
    $s->check('D', 'Boîte de notifications du propriétaire', 'GET /notifications', '200', (string) $r->status, $r->status === 200);

    // Deux non-lues garanties : le rappel de colB (D6) + celui de colB2 (plante
    // dédiée créée ci-dessus, dédup « une plante = un rappel » oblige).
    $notifs = $ctx->col('SELECT id FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY id', [$ctx->ids['userB']['id']]);
    if (count($notifs) >= 2) {
        $n1 = (string) $notifs[0];
        $n2 = (string) $notifs[1];

        $tok = $ctx->csrf($userB, '/notifications');
        $r = $userB->post('/notification/' . $n1 . '/read', ['csrf_token' => (string) $tok]);
        $s->check('D', 'Marquage « lu » par le propriétaire', 'POST /notification/{id}/read', '302 → /notifications',
            $r->status . ' → ' . ($r->location() ?? '-'), $r->status === 302 && $r->location() === '/notifications');
        $s->check('D', 'État lu persisté en base', 'SELECT notifications', 'is_read = 1',
            'is_read = ' . (string) $ctx->scalar('SELECT is_read FROM notifications WHERE id = ?', [$n1]),
            (int) $ctx->scalar('SELECT is_read FROM notifications WHERE id = ?', [$n1]) === 1);

        $r = $userB->get('/notification/' . $n2);
        $ctx->calls++;
        $s->check('D', 'Détail d\'une notification (lecture vaut marquage)', 'GET /notification/{id}', '200',
            (string) $r->status, $r->status === 200);
        $s->check('D', 'Le détail a marqué la notification comme lue', 'SELECT notifications', 'is_read = 1',
            'is_read = ' . (string) $ctx->scalar('SELECT is_read FROM notifications WHERE id = ?', [$n2]),
            (int) $ctx->scalar('SELECT is_read FROM notifications WHERE id = ?', [$n2]) === 1);

        $tok = $ctx->csrf($userB, '/notifications');
        $r = $userB->post('/notification/delete/' . $n1, ['csrf_token' => (string) $tok]);
        $s->check('D', 'Suppression d\'une notification par le propriétaire', 'POST /notification/delete/{id}',
            '302 → /notifications', $r->status . ' → ' . ($r->location() ?? '-'), $r->status === 302);
        $s->check('D', 'Notification réellement supprimée de la base', 'SELECT notifications', '0 ligne',
            (string) $ctx->int('SELECT COUNT(*) FROM notifications WHERE id = ?', [$n1]),
            $ctx->int('SELECT COUNT(*) FROM notifications WHERE id = ?', [$n1]) === 0);
    } else {
        $s->blocked('D', 'Parcours notifications (marquage, détail, suppression)', 'POST /notification/{id}/read',
            'moins de deux notifications non lues disponibles (' . count($notifs) . ' non lues · total ' .
            $ctx->int('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$ctx->ids['userB']['id']]) . ' · colB2=' .
            ($colB2 ?? 'null') . ')');
    }

    // Nettoyage de la plante « prérequis D7 » : rend son espèce à D9d
    // (la saisie libre autocomplétée doit pouvoir la lier en collection).
    if ($colB2 !== null) {
        $tok = $ctx->csrf($userB, '/collection/' . $colB2);
        $userB->post('/collection/delete/' . $colB2, ['csrf_token' => (string) $tok]);
    }

    // --- G rappels de soins (RG3 étendu — fréquence + cycle de vie + vue dédiée) ---
    // G1 — Fréquence personnalisable persistée via /update/collection.
    $tok = $ctx->csrf($userB, '/collection/' . $colB);
    $r = $userB->post('/update/collection', [
        'id'                     => $colB,
        'notes'                  => 'Fréquence rappel testée',
        'rappel_frequence_jours' => '14',
        'csrf_token'             => (string) $tok,
    ]);
    $s->check('G', 'Fréquence de rappel personnalisable', 'POST /update/collection', '302',
        (string) $r->status, $r->status === 302);
    $s->check('G', 'collections.rappel_frequence_jours = 14', 'SELECT collections', '14',
        (string) $ctx->scalar('SELECT rappel_frequence_jours FROM collections WHERE id = ?', [$colB]),
        (int) $ctx->scalar('SELECT rappel_frequence_jours FROM collections WHERE id = ?', [$colB]) === 14);

    // Valeur aberrante → refus silencieux, la valeur actuelle (14) est conservée.
    $tok = $ctx->csrf($userB, '/collection/' . $colB);
    $r = $userB->post('/update/collection', [
        'id'                     => $colB,
        'rappel_frequence_jours' => '99999',
        'csrf_token'             => (string) $tok,
    ]);
    $s->check('G', 'Fréquence hors bornes [1 ; 365] rejetée', 'POST /update/collection',
        '14 conservée', (string) $ctx->scalar('SELECT rappel_frequence_jours FROM collections WHERE id = ?', [$colB]),
        (int) $ctx->scalar('SELECT rappel_frequence_jours FROM collections WHERE id = ?', [$colB]) === 14);

    // G2 — Nouveau soin après changement de fréquence : notif replanifiée à J+14
    // sur la même ligne (dédup), pas empilée.
    $tok = $ctx->csrf($userB, '/collection/' . $colB);
    $r = $userB->post('/soin/new', [
        'collection_id' => $colB,
        'type'          => 'arrosage',
        'soin_date'     => '2026-10-20',
        'notes'         => 'Soin déclencheur freq 14',
        'csrf_token'    => (string) $tok,
    ]);
    $s->check('G', 'Soin après changement de fréquence → replanification', 'POST /soin/new', '302',
        (string) $r->status, $r->status === 302);
    $nbRappelsApres = $ctx->int('SELECT COUNT(*) FROM notifications WHERE collection_id = ? AND type = ?',
        [$colB, 'rappel_soin']);
    $s->check('G', 'Pas d\'empilement : toujours 1 seul rappel pour cette plante', 'SELECT notifications',
        '1', (string) $nbRappelsApres, $nbRappelsApres === 1);
    $dateRappel = (string) $ctx->scalar("SELECT notif_date FROM notifications WHERE collection_id = ? AND type = 'rappel_soin' LIMIT 1", [$colB]);
    // Le rappel est calé sur 2026-10-20 + 14 j = 2026-11-03 (± 23h59 autour).
    $jourAttendu = '2026-11-03';
    $s->check('G', ' notif_date = soin + 14 jours (fréquence personnalisée appliquée)', 'SELECT notifications',
        $jourAttendu, substr($dateRappel, 0, 10), substr($dateRappel, 0, 10) === $jourAttendu);

    // Récupère l'identifiant du rappel courant pour les actions de cycle de vie.
    $rappelG = $ctx->scalar("SELECT id FROM notifications WHERE collection_id = ? AND type = 'rappel_soin' LIMIT 1", [$colB]);

    // C0 — le rappel porte désormais le type STRUCTURÉ du soin qui le programme.
    $idArrosage = (string) $ctx->scalar("SELECT id FROM types_soin WHERE label = 'arrosage'");
    $s->check('G', 'C0 — notifications.id_type_soin persisté à la génération', 'SELECT notifications',
        $idArrosage, (string) $ctx->scalar('SELECT id_type_soin FROM notifications WHERE id = ?', [$rappelG]),
        $rappelG !== null && (string) $ctx->scalar('SELECT id_type_soin FROM notifications WHERE id = ?', [$rappelG]) === $idArrosage);

    // C0 — rétro-compatibilité : un rappel « historique » sans type (id_type_soin NULL,
    // antérieur à la colonne C0), déjà dû, doit rester affichable et classé « En retard ».
    $legacyId = 'httptest-c0-' . $ctx->runId;
    $insLegacy = $ctx->db->prepare(
        "INSERT INTO notifications (id, user_id, collection_id, type, message, is_read, statut, id_type_soin, notif_date)
         VALUES (?, ?, NULL, 'rappel_soin', ?, 0, 'actif', NULL, ?)"
    );
    $insLegacy->execute([
        $legacyId,
        $ctx->ids['userB']['id'],
        'Rappel historique sans type ' . $ctx->runId,
        date('Y-m-d H:i:s', strtotime('-1 day')),
    ]);

    // G3 — Vue /rappels (HTML) : le rappel y figure.
    $r = $userB->get('/rappels');
    $ctx->calls++;
    $s->check('G', 'Vue dédiée « Mes rappels » (GET /rappels)', 'GET /rappels', '200',
        (string) $r->status, $r->status === 200);
    $s->check('G', 'Le rappel courant est listé dans la vue', 'GET /rappels', 'contient « Soin déclencheur » ou fréquence',
        body_contains($r->body, 'fréquence 14') ? 'présent' : 'absent',
        body_contains($r->body, 'fréquence 14'));
    // C0 — sections calculées + badge de type + rappel historique NULL : la refonte rend.
    $s->check('G', 'C0 — section « En retard » rendue (rappel dû dépassé)', 'GET /rappels', 'contient « En retard »',
        body_contains($r->body, 'En retard') ? 'présente' : 'absente',
        body_contains($r->body, 'En retard'));
    $s->check('G', 'C0 — rappel historique sans type reste affichable (NULL rétro-compat)', 'GET /rappels',
        'contient le message du rappel legacy',
        body_contains($r->body, 'Rappel historique sans type ' . $ctx->runId) ? 'présent' : 'absent',
        body_contains($r->body, 'Rappel historique sans type ' . $ctx->runId));
    $s->check('G', 'C0 — badge du type de soin affiché sur la carte', 'GET /rappels', 'contient « Arrosage »',
        body_contains($r->body, 'Arrosage') ? 'présent' : 'absent',
        body_contains($r->body, 'Arrosage'));

    // G4 — « Effectuer le soin » (C0 : consigne le soin + clôture + replanifie).
    if ($rappelG !== null) {
        $nbSoinsAvant = $ctx->int('SELECT COUNT(*) FROM soins WHERE collection_id = ?', [$colB]);
        $tok = $ctx->csrf($userB, '/rappels');
        $r = $userB->post('/rappel/' . $rappelG . '/fait', ['csrf_token' => (string) $tok]);
        $s->check('G', 'Marquage « traité » par le propriétaire', 'POST /rappel/{id}/fait', '302',
            (string) $r->status, $r->status === 302);
        $s->check('G', 'notifications.statut = traite', 'SELECT notifications', 'traite',
            (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$rappelG]),
            (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$rappelG]) === 'traite');

        // C0 — l'action consigne un soin RÉEL (pas un simple changement de statut).
        $nbSoinsApres = $ctx->int('SELECT COUNT(*) FROM soins WHERE collection_id = ?', [$colB]);
        $s->check('G', 'C0 — « Effectuer le soin » consigne un soin (COUNT soins +1)', 'SELECT soins',
            (string) ($nbSoinsAvant + 1), (string) $nbSoinsApres, $nbSoinsApres === $nbSoinsAvant + 1);
        $s->check('G', 'C0 — le soin consigné reprend le type du rappel', 'SELECT soins', $idArrosage,
            (string) $ctx->scalar("SELECT id_type_soin FROM soins WHERE collection_id = ? AND notes = 'Soin consigné depuis le rappel.' LIMIT 1", [$colB]),
            (string) $ctx->scalar("SELECT id_type_soin FROM soins WHERE collection_id = ? AND notes = 'Soin consigné depuis le rappel.' LIMIT 1", [$colB]) === $idArrosage);

        // C0 — reprogrammation immédiate si la plante est en opt-in (rappel_actif = 1 ici) :
        // un nouveau rappel « planifie » à today + fréquence (14 j), type hérité.
        $nbPlanApresFait = $ctx->int("SELECT COUNT(*) FROM notifications WHERE collection_id = ? AND type='rappel_soin' AND statut='planifie'", [$colB]);
        $jourReplan = date('Y-m-d', strtotime('+14 days'));
        $dateReplan = (string) $ctx->scalar("SELECT notif_date FROM notifications WHERE collection_id = ? AND type='rappel_soin' AND statut='planifie' ORDER BY notif_date DESC LIMIT 1", [$colB]);
        $s->check('G', 'C0 — rappel reprogrammé après consignation (jour + fréquence 14)', 'SELECT notifications',
            '1 planifie à ' . $jourReplan, $nbPlanApresFait . ' planifie à ' . substr($dateReplan, 0, 10),
            $nbPlanApresFait === 1 && substr($dateReplan, 0, 10) === $jourReplan);
        $s->check('G', 'C0 — le nouveau rappel hérite du type structuré', 'SELECT notifications', $idArrosage,
            (string) $ctx->scalar("SELECT id_type_soin FROM notifications WHERE collection_id = ? AND statut='planifie' LIMIT 1", [$colB]),
            (string) $ctx->scalar("SELECT id_type_soin FROM notifications WHERE collection_id = ? AND statut='planifie' LIMIT 1", [$colB]) === $idArrosage);

        // C0 — anti-double-frappe : un rappel déjà clos ne consignifie plus rien.
        $tok = $ctx->csrf($userB, '/rappels');
        $userB->post('/rappel/' . $rappelG . '/fait', ['csrf_token' => (string) $tok]);
        $nbSoinsBis = $ctx->int('SELECT COUNT(*) FROM soins WHERE collection_id = ?', [$colB]);
        $s->check('G', 'C0 — anti-double-frappe : second /fait ne consigne pas de soin', 'POST /rappel/{id}/fait (déjà traite)',
            (string) ($nbSoinsAvant + 1), (string) $nbSoinsBis, $nbSoinsBis === $nbSoinsAvant + 1);

        // C0 — le rappel clos bascule dans la section « Historique » de la vue.
        $r = $userB->get('/rappels');
        $ctx->calls++;
        $s->check('G', 'C0 — section « Historique » rendue avec le rappel clos', 'GET /rappels',
            'contient « Historique » + badge « Traité »',
            (body_contains($r->body, 'Historique') && body_contains($r->body, 'Traité')) ? 'présent' : 'absent',
            body_contains($r->body, 'Historique') && body_contains($r->body, 'Traité'));

        // G5 — Report : le rappel repart en `planifie` à une date décalée.
        $tok = $ctx->csrf($userB, '/rappels');
        $r = $userB->post('/rappel/' . $rappelG . '/report/7', ['csrf_token' => (string) $tok]);
        $s->check('G', 'Report de 7 jours', 'POST /rappel/{id}/report/7', '302',
            (string) $r->status, $r->status === 302);
        $s->check('G', ' notifications.statut repassé à « planifie »', 'SELECT notifications', 'planifie',
            (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$rappelG]),
            (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$rappelG]) === 'planifie');

        // G6 — Ignorer.
        $tok = $ctx->csrf($userB, '/rappels');
        $r = $userB->post('/rappel/' . $rappelG . '/ignorer', ['csrf_token' => (string) $tok]);
        $s->check('G', 'Marquage « ignoré »', 'POST /rappel/{id}/ignorer', '302',
            (string) $r->status, $r->status === 302);
        $s->check('G', 'notifications.statut = ignore', 'SELECT notifications', 'ignore',
            (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$rappelG]),
            (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$rappelG]) === 'ignore');

        // G7 — Report invalide (jours > 365) : statut inchangé.
        $tok = $ctx->csrf($userB, '/rappels');
        $r = $userB->post('/rappel/' . $rappelG . '/report/9999', ['csrf_token' => (string) $tok]);
        $s->check('G', 'Report hors bornes [1 ; 365] refusé', 'POST /rappel/{id}/report/9999',
            '302 + statut inchange', $r->status . ' / ' .
            (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$rappelG]),
            $r->status === 302 && (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$rappelG]) === 'ignore');

        // G8 — Isolation RG1 : userA ne peut pas traiter le rappel de userB.
        [$userA, $resA] = $ctx->login('userA');
        $tokA = $ctx->csrf($userA, '/rappels');
        if ($tokA !== null) {
            // C0 — renforcé : autrui ne peut pas davantage consigner un soin sur
            // la plante de userB en devinant l'UUID du rappel.
            $nbSoinsAvantG8 = $ctx->int('SELECT COUNT(*) FROM soins WHERE collection_id = ?', [$colB]);
            $r = $userA->post('/rappel/' . $rappelG . '/fait', ['csrf_token' => (string) $tokA]);
            $s->check('G', 'RG1 — autrui ne peut pas marquer un rappel', 'POST /rappel/{id}/fait (userA)',
                'ignore conserve', (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$rappelG]),
                (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$rappelG]) === 'ignore');
            $s->check('G', 'C0 — RG1 : aucun soin créé par autrui via /fait', 'SELECT soins',
                (string) $nbSoinsAvantG8, (string) $ctx->int('SELECT COUNT(*) FROM soins WHERE collection_id = ?', [$colB]),
                $ctx->int('SELECT COUNT(*) FROM soins WHERE collection_id = ?', [$colB]) === $nbSoinsAvantG8);
        }

        // G9 — Suppression unitaire.
        $tok = $ctx->csrf($userB, '/rappels');
        $r = $userB->post('/rappel/delete/' . $rappelG, ['csrf_token' => (string) $tok]);
        $s->check('G', 'Suppression du rappel', 'POST /rappel/delete/{id}', '302',
            (string) $r->status, $r->status === 302);
        $s->check('G', 'Le rappel a été retiré de la base', 'SELECT notifications', '0 ligne',
            (string) $ctx->int('SELECT COUNT(*) FROM notifications WHERE id = ?', [$rappelG]),
            $ctx->int('SELECT COUNT(*) FROM notifications WHERE id = ?', [$rappelG]) === 0);
    }

    // G10 — RBAC : /rappels est réservé aux authentifiés (conforme à la politique
    // de la suite : les routes GRANT_AUTH répondent 403 nu, jamais redirigées).
    $anonG = $ctx->client();
    $r = $anonG->get('/rappels');
    $ctx->calls++;
    $s->check('G', 'Accès anonyme à /rappels refusé', 'GET /rappels (anon)', '403',
        (string) $r->status, $r->status === 403);

    // G11 — Opt-in du rappel à l'enregistrement d'un soin (RG3 étendu).
    // (a) rappel_actif=1 → plante activée + rappel « planifie » créé.
    $tok = $ctx->csrf($userB, '/collection/' . $colB);
    $r = $userB->post('/soin/new', [
        'collection_id' => $colB,
        'type'          => 'arrosage',
        'soin_date'     => '2026-10-21',
        'notes'         => 'Soin opt-in ON ' . $ctx->runId,
        'rappel_actif'  => '1',
        'csrf_token'    => (string) $tok,
    ]);
    $nbPlanApresOn = $ctx->int("SELECT COUNT(*) FROM notifications WHERE collection_id = ? AND type='rappel_soin' AND statut='planifie'", [$colB]);
    $s->check('G', 'Opt-in activé → rappel programmé', 'POST /soin/new (rappel_actif=1)', '302 + 1 rappel planifie',
        $r->status . ' / ' . $nbPlanApresOn . ' planifie', $r->status === 302 && $nbPlanApresOn === 1);
    $s->check('G', 'collections.rappel_actif = 1 persisté', 'SELECT collections', '1',
        (string) $ctx->scalar('SELECT rappel_actif FROM collections WHERE id = ?', [$colB]),
        (int) $ctx->scalar('SELECT rappel_actif FROM collections WHERE id = ?', [$colB]) === 1);

    $rappelOpt = $ctx->scalar("SELECT id FROM notifications WHERE collection_id = ? AND type='rappel_soin' AND statut='planifie' ORDER BY notif_date DESC LIMIT 1", [$colB]);

    // (b) rappel_actif=0 → plante désactivée, rappel planifié existant annulé (ignore)
    //     mais CONSERVÉ (aucune suppression), et aucun nouveau rappel empilé.
    $tok = $ctx->csrf($userB, '/collection/' . $colB);
    $r = $userB->post('/soin/new', [
        'collection_id' => $colB,
        'type'          => 'engrais',
        'soin_date'     => '2026-10-22',
        'notes'         => 'Soin opt-in OFF ' . $ctx->runId,
        'rappel_actif'  => '0',
        'csrf_token'    => (string) $tok,
    ]);
    $s->check('G', 'Opt-in désactivé → collections.rappel_actif = 0', 'POST /soin/new (rappel_actif=0)', '0',
        (string) $ctx->scalar('SELECT rappel_actif FROM collections WHERE id = ?', [$colB]),
        (int) $ctx->scalar('SELECT rappel_actif FROM collections WHERE id = ?', [$colB]) === 0);
    $s->check('G', 'Désactivation → plus aucun rappel « planifie »', 'SELECT notifications', '0 planifie',
        (string) $ctx->int("SELECT COUNT(*) FROM notifications WHERE collection_id = ? AND type='rappel_soin' AND statut='planifie'", [$colB]),
        $ctx->int("SELECT COUNT(*) FROM notifications WHERE collection_id = ? AND type='rappel_soin' AND statut='planifie'", [$colB]) === 0);
    $s->check('G', 'Rappel annulé mais CONSERVÉ (statut ignore, non supprimé)', 'SELECT notifications', 'ignore',
        $rappelOpt !== null ? (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$rappelOpt]) : 'absent',
        $rappelOpt !== null && (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$rappelOpt]) === 'ignore');

    // Suppression de la collection de test (nettoyage par l\'application elle-même)
    $tok = $ctx->csrf($userB, '/collection/' . $colB);
    $r = $userB->post('/collection/delete/' . $colB, ['csrf_token' => (string) $tok]);
    $s->check('D', 'Retrait de la plante par le propriétaire', 'POST /collection/delete/{id}', '302 → /macollection',
        $r->status . ' → ' . ($r->location() ?? '-'), $r->status === 302 && $r->location() === '/macollection');
    $s->check('D', 'Cascade : les soins de l\'entrée ont suivi', 'SELECT soins', '0 ligne',
        (string) $ctx->int('SELECT COUNT(*) FROM soins WHERE collection_id = ?', [$colB]),
        $ctx->int('SELECT COUNT(*) FROM soins WHERE collection_id = ?', [$colB]) === 0);

    // --- D8 CRUD orchidée par l'admin ---
    [$admin, $res] = $ctx->login('admin');
    $d9Id = 'httptest-d8-' . $ctx->runId;
    $tok = $ctx->csrf($admin, '/orchid/new');
    $r = $admin->post('/orchid/new', [
        'id'         => $d9Id,
        'name'       => 'Zx Admin Create ' . $ctx->runId,
        'genre'      => 'Zxgenus',
        'family'     => 'Orchidaceae',
        'short_desc' => 'Fiche créée par la suite HTTP',
        'csrf_token' => (string) $tok,
    ]);
    preg_match('#/orchid/(.+)$#', (string) $r->location(), $m);
    $created = $m[1] ?? null;
    $s->check('D', 'Création d\'une fiche par l\'admin', 'POST /orchid/new', '302 → /orchid/{slug}',
        $r->status . ' → ' . ($created !== null ? '/orchid/{slug}' : (string) $r->location()),
        $r->status === 302 && $created !== null);

    if ($created !== null) {
        $row = $ctx->row('SELECT is_published, proposed_by FROM orchids WHERE id = ?', [$created]);
        $s->check('D', 'Fiche publiée directement (action admin)', 'SELECT orchids', 'is_published = 1',
            'is_published = ' . (string) ($row['is_published'] ?? '-'), (int) ($row['is_published'] ?? -1) === 1);
        $s->check('D', 'Auteur de la fiche tracé (proposed_by = admin)', 'SELECT orchids',
            (string) $ctx->ids['admin']['id'], (string) ($row['proposed_by'] ?? '-'), ($row['proposed_by'] ?? null) === $ctx->ids['admin']['id']);

        $r = $admin->get('/update/orchid/' . rawurlencode($created));
        $ctx->calls++;
        $s->check('D', 'Formulaire d\'édition pré-rempli', 'GET /update/orchid/{id}', '200', (string) $r->status, $r->status === 200);

        $tok = $ctx->csrf($admin, '/update/orchid/' . rawurlencode($created));
        $r = $admin->post('/update/orchid', [
            'id'         => $created,
            'name'       => 'Zx Admin Update ' . $ctx->runId,
            'genre'      => 'Zxgenus',
            'short_desc' => 'Fiche modifiée par la suite HTTP',
            'csrf_token' => (string) $tok,
        ]);
        $s->check('D', 'Mise à jour de la fiche (sans case publier)', 'POST /update/orchid', '302 → /orchid/{slug}',
            (string) $r->status, $r->status === 302);
        $s->check('D', 'Dépublication bien enregistrée (case absente = 0)', 'SELECT orchids', 'is_published = 0',
            'is_published = ' . (string) $ctx->scalar('SELECT is_published FROM orchids WHERE id = ?', [$created]),
            (int) $ctx->scalar('SELECT is_published FROM orchids WHERE id = ?', [$created]) === 0);

        $tok = $ctx->csrf($admin, '/update/orchid/' . rawurlencode($created));
        $r = $admin->post('/orchid/delete/' . rawurlencode($created), ['csrf_token' => (string) $tok]);
        $s->check('D', 'Suppression de la fiche par l\'admin', 'POST /orchid/delete/{id}', '302 → /encyclopedie',
            $r->status . ' → ' . ($r->location() ?? '-'), $r->status === 302 && $r->location() === '/encyclopedie');
        $s->check('D', 'Fiche réellement supprimée de la base', 'SELECT orchids', '0 ligne',
            (string) $ctx->int('SELECT COUNT(*) FROM orchids WHERE id = ?', [$created]),
            $ctx->int('SELECT COUNT(*) FROM orchids WHERE id = ?', [$created]) === 0);
    }

    // --- D9 proposition utilisateur + modération (RG5) ---
    [$userA, $res] = $ctx->login('userA');
    $propId = 'httptest-d9-' . $ctx->runId;
    $tok = $ctx->csrf($userA, '/macollection');
    $r = $userA->post('/orchid/propose', [
        'id'         => $propId,
        'name'       => 'Zx Proposition ' . $ctx->runId,
        'genre'      => 'Cattleya',
        'short_desc' => 'Proposition d\'un utilisateur',
        'csrf_token' => (string) $tok,
    ]);
    preg_match('#/orchid/(.+)$#', (string) $r->location(), $m);
    $s->check('D', 'Proposition d\'une espèce par un utilisateur connecté', 'POST /orchid/propose',
        '302 → /encyclopedie', $r->status . ' → ' . ($r->location() ?? '-'), $r->status === 302 && $r->location() === '/encyclopedie');

    $prop = $ctx->row('SELECT id, is_published, proposed_by FROM orchids WHERE id IN (?, ?)', [$propId, str_replace('-', '_', $propId)]);
    $s->check('D', 'Proposition enregistrée en attente de validation', 'SELECT orchids', 'is_published = 0',
        'is_published = ' . (string) ($prop['is_published'] ?? '-'), (int) ($prop['is_published'] ?? -1) === 0);
    $s->check('D', 'Auteur de la proposition enregistré', 'SELECT orchids', (string) $ctx->ids['userA']['id'],
        (string) ($prop['proposed_by'] ?? '-'), ($prop['proposed_by'] ?? null) === $ctx->ids['userA']['id']);

    [$modo, $res] = $ctx->login('modo');
    $r = $modo->get('/moderation');
    $ctx->calls++;
    // Diagnostic : on distingue « page vide » de « texte absent du HTML » pour ne
    // jamais conclure sur un simple 200 muet.
    // Le nom est normalisé en MAJUSCULES par OrchidController::fromRequest() (règle
    // héritée du catalogue : « ACACALIS CYANEA »). C'est donc cette forme qu'on attend
    // dans la file de modération, et non celle tapée par l'auteur.
    $nomAttendu   = mb_strtoupper('Zx Proposition ' . $ctx->runId);
    $hasFixture   = body_contains($r->body, 'Proposition modération ' . $ctx->runId);
    $hasProp      = body_contains($r->body, $nomAttendu);
    $diag = $r->status . ' · ' . substr_count($r->body, 'moderation-card') . ' carte(s) · fixture='
          . ($hasFixture ? 'vue' : 'absente') . ' · proposition=' . ($hasProp ? 'vue' : 'absente')
          . (body_contains($r->body, 'Aucune proposition') ? ' · file annoncée vide' : '');
    $s->check('D', 'La file de modération liste les propositions', 'GET /moderation',
        '200 + proposition visible', $diag,
        $r->status === 200 && $hasFixture && $hasProp);
    $s->check('D', 'La proposition saisie en minuscules est normalisée en majuscules', 'SELECT orchids',
        $nomAttendu, (string) $ctx->scalar('SELECT name FROM orchids WHERE id = ?', [$prop['id'] ?? $propId]),
        $ctx->scalar('SELECT name FROM orchids WHERE id = ?', [$prop['id'] ?? $propId]) === $nomAttendu);

    $approved = $prop;
    if ($approved !== null) {
        $tok = $ctx->csrf($modo, '/moderation');
        $r = $modo->post('/moderation/' . rawurlencode((string) $approved['id']) . '/approve', ['csrf_token' => (string) $tok]);
        $s->check('D', 'Validation d\'une proposition par le modérateur', 'POST /moderation/{id}/approve',
            '302 → /moderation', $r->status . ' → ' . ($r->location() ?? '-'), $r->status === 302 && $r->location() === '/moderation');
        $s->check('D', 'Proposition publiée en base', 'SELECT orchids', 'is_published = 1',
            'is_published = ' . (string) $ctx->scalar('SELECT is_published FROM orchids WHERE id = ?', [$approved['id']]),
            (int) $ctx->scalar('SELECT is_published FROM orchids WHERE id = ?', [$approved['id']]) === 1);
        $s->check('D', 'RG5 — notification de validation envoyée à l\'auteur', 'SELECT notifications', '1 notification validation',
            (string) $ctx->int("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ?", [$ctx->ids['userA']['id'], 'validation']),
            $ctx->int("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ?", [$ctx->ids['userA']['id'], 'validation']) >= 1);
        // La fiche approuvée devient visible du public
        $pub = $anon->get('/orchid/' . rawurlencode((string) $approved['id']));
        $ctx->calls++;
        $s->check('D', 'Fiche approuvée ensuite visible publiquement', 'GET /orchid/{id}', '200', (string) $pub->status, $pub->status === 200);
    }

    $tok = $ctx->csrf($modo, '/moderation');
    $r = $modo->post('/moderation/' . rawurlencode($pending) . '/reject', ['csrf_token' => (string) $tok]);
    $s->check('D', 'Refus d\'une proposition', 'POST /moderation/{id}/reject', '302 → /moderation',
        $r->status . ' → ' . ($r->location() ?? '-'), $r->status === 302 && $r->location() === '/moderation');
    // Refus SOFT : la plante n'est plus supprimée (FK RESTRICT + cahier des charges :
    // elle reste acquise au propriétaire). Elle est marquée « rejected » et dépubliée.
    $s->check('D', 'Proposition refusée CONSERVÉE en base (pas de suppression)', 'SELECT orchids', '1 ligne',
        (string) $ctx->int('SELECT COUNT(*) FROM orchids WHERE id = ?', [$pending]),
        $ctx->int('SELECT COUNT(*) FROM orchids WHERE id = ?', [$pending]) === 1);
    $s->check('D', 'Fiche refusée : statut rejected et non publiée', 'SELECT orchids',
        'rejected / is_published = 0',
        (string) $ctx->scalar("SELECT moderation_status || '/' || is_published FROM orchids WHERE id = ?", [$pending]),
        (string) $ctx->scalar("SELECT moderation_status FROM orchids WHERE id = ?", [$pending]) === 'rejected'
            && (int) $ctx->scalar('SELECT is_published FROM orchids WHERE id = ?', [$pending]) === 0);
    $s->check('D', 'RG5 — notification de refus envoyée à l\'auteur', 'SELECT notifications', '1 notification rejet',
        (string) $ctx->int("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ?", [$ctx->ids['userA']['id'], 'rejet']),
        $ctx->int("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = ?", [$ctx->ids['userA']['id'], 'rejet']) >= 1);

    // --- D9b ajout LIBRE en collection → proposition + notification des admins ---
    // L'utilisateur saisit un nom absent de l'encyclopédie + les champs botaniques.
    // La plante est TOUJOURS ajoutée à sa collection, une proposition « pending » est
    // créée, et les admins/modérateurs sont notifiés.
    $freeName = 'Httptest D9b Zxq ' . $ctx->runId;
    $freeSlug = 'httptest_d9b_zxq_' . $ctx->runId;
    $tok = $ctx->csrf($userA, '/macollection');
    $r = $userA->post('/collection/new', [
        'name'       => $freeName,
        'genre'      => 'Zxcalystachya',
        'family'     => 'Orchidaceae',
        'behavior'   => 'Epiphyte',
        'location'   => 'Serre de test D9b',
        'csrf_token' => (string) $tok,
    ]);
    preg_match('#/collection/([0-9a-f\-]{36})#', (string) $r->location(), $m);
    $colFree = $m[1] ?? null;
    $s->check('D', 'Ajout libre d\'une orchidée inconnue → collection', 'POST /collection/new',
        '302 → /collection/{uuid}', $r->status . ' → ' . ($colFree !== null ? '/collection/{uuid}' : 'aucune Location'),
        $r->status === 302 && $colFree !== null);

    $proposed = $ctx->row('SELECT id, is_published, moderation_status, proposed_by FROM orchids WHERE id = ?', [$freeSlug]);
    $s->check('D', 'Proposition créée en base (pending, non publiée)', 'SELECT orchids',
        'pending / is_published = 0',
        ($proposed === null ? 'absente' : $proposed['moderation_status'] . ' / ' . $proposed['is_published']),
        $proposed !== null && (string) $proposed['moderation_status'] === 'pending' && (int) $proposed['is_published'] === 0);
    $s->check('D', 'Auteur de la proposition = utilisateur connecté (RG1)', 'SELECT orchids',
        (string) $ctx->ids['userA']['id'],
        (string) ($proposed['proposed_by'] ?? '-'),
        ($proposed !== null && (string) $proposed['proposed_by'] === (string) $ctx->ids['userA']['id']));
    $s->check('D', 'La plante proposée est bien dans la collection de userA', 'SELECT collections',
        $freeSlug,
        (string) $ctx->scalar('SELECT orchid_id FROM collections WHERE id = ?', [$colFree ?? '']),
        $colFree !== null && (string) $ctx->scalar('SELECT orchid_id FROM collections WHERE id = ?', [$colFree]) === $freeSlug);
    $s->check('D', 'Notification « nouvelle_proposition » envoyée aux admins/modérateurs', 'SELECT notifications',
        '≥ 1 notification',
        (string) $ctx->int("SELECT COUNT(*) FROM notifications WHERE type = ?", ['nouvelle_proposition']),
        $ctx->int("SELECT COUNT(*) FROM notifications WHERE type = ?", ['nouvelle_proposition']) >= 1);

    // --- D9c autocomplétion : le champ « nom » doit lister les espèces publiées ---
    $r = $userA->get('/macollection');
    $s->check('D', 'Le formulaire d\'ajout expose une autocomplétion des espèces publiées', 'GET /macollection',
        '200 + <datalist id=liste-especes>',
        $r->status . ' · ' . (body_contains($r->body, 'id="liste-especes"') ? 'datalist vue' : 'datalist absente'),
        $r->status === 200 && body_contains($r->body, 'id="liste-especes"')
            && body_contains($r->body, 'list="liste-especes"'));

    // --- D9d nom saisi = espèce publiée → lien, aucune proposition, aucune notif ---
    $pubName = 'Httptest Auto ' . $ctx->runId;
    $pubSlug = (string) $ctx->ids['publishedByName'];
    $beforeNotif = $ctx->int("SELECT COUNT(*) FROM notifications WHERE type = ?", ['nouvelle_proposition']);
    $tok = $ctx->csrf($userB, '/macollection');
    $r = $userB->post('/collection/new', [
        'name'       => $pubName,
        'location'   => 'Fenêtre Autocomplétion',
        'csrf_token' => (string) $tok,
    ]);
    preg_match('#/collection/([0-9a-f\-]{36})#', (string) $r->location(), $m);
    $colAuto = $m[1] ?? null;
    $s->check('D', 'Saisie libre d\'une espèce publiée → lien en collection', 'POST /collection/new',
        '302 → /collection/{uuid}', $r->status . ' → ' . ($colAuto !== null ? '/collection/{uuid}' : 'aucune Location'),
        $r->status === 302 && $colAuto !== null);
    $s->check('D', 'La collection référence l\'espèce publiée existante (pas de création)', 'SELECT collections',
        $pubSlug,
        (string) $ctx->scalar('SELECT orchid_id FROM collections WHERE id = ?', [$colAuto ?? '']),
        $colAuto !== null && (string) $ctx->scalar('SELECT orchid_id FROM collections WHERE id = ?', [$colAuto]) === $pubSlug);
    $s->check('D', 'Aucune nouvelle fiche en base (le slug publié existait déjà)', 'SELECT orchids',
        '1',
        (string) $ctx->int('SELECT COUNT(*) FROM orchids WHERE id = ?', [$pubSlug]),
        $ctx->int('SELECT COUNT(*) FROM orchids WHERE id = ?', [$pubSlug]) === 1);
    $s->check('D', 'Aucune nouvelle notification « nouvelle_proposition »', 'SELECT notifications',
        (string) $beforeNotif,
        (string) $ctx->int("SELECT COUNT(*) FROM notifications WHERE type = ?", ['nouvelle_proposition']),
        $ctx->int("SELECT COUNT(*) FROM notifications WHERE type = ?", ['nouvelle_proposition']) === $beforeNotif);

    // --- D10 conseils (CRUD admin, lecture publique) ---
    $tok = $ctx->csrf($admin, '/conseil/new');
    $r = $admin->post('/conseil/new', [
        'id'      => 'httptest-d10-' . $ctx->runId,
        'type'    => 'category',
        'name'    => 'Conseil de test ' . $ctx->runId,
        'category'=> 'Arrosage',
        'content' => 'Contenu de test généré par la suite HTTP.',
        'csrf_token' => (string) $tok,
    ]);
    preg_match('#/conseil/(.+)$#', (string) $r->location(), $m);
    $conseilId = $m[1] ?? null;
    $s->check('D', 'Création d\'un conseil par l\'admin', 'POST /conseil/new', '302 → /conseil/{slug}',
        $r->status . ' → ' . ($conseilId !== null ? '/conseil/{slug}' : (string) $r->location()),
        $r->status === 302 && $conseilId !== null);

    if ($conseilId !== null) {
        $r = $anon->get('/conseil/' . rawurlencode($conseilId));
        $ctx->calls++;
        $s->check('D', 'Fiche conseil accessible au public', 'GET /conseil/{id}', '200 + contenu',
            (string) $r->status, $r->status === 200 && body_contains($r->body, 'Contenu de test généré par la suite HTTP.'));

        $r = $admin->get('/update/conseil/' . rawurlencode($conseilId));
        $ctx->calls++;
        $s->check('D', 'Formulaire d\'édition d\'un conseil', 'GET /update/conseil/{id}', '200', (string) $r->status, $r->status === 200);

        $tok = $ctx->csrf($admin, '/update/conseil/' . rawurlencode($conseilId));
        $r = $admin->post('/update/conseil', [
            'id'      => $conseilId,
            'type'    => 'category',
            'name'    => 'Conseil modifié ' . $ctx->runId,
            'category'=> 'Arrosage',
            'content' => 'Contenu mis à jour par la suite HTTP.',
            'csrf_token' => (string) $tok,
        ]);
        $s->check('D', 'Mise à jour d\'un conseil', 'POST /update/conseil', '302 → /conseil/{slug}', (string) $r->status, $r->status === 302);
        $s->check('D', 'Conseil modifié en base', 'SELECT conseils', 'Contenu mis à jour par la suite HTTP.',
            (string) $ctx->scalar('SELECT content FROM conseils WHERE id = ?', [$conseilId]),
            $ctx->scalar('SELECT content FROM conseils WHERE id = ?', [$conseilId]) === 'Contenu mis à jour par la suite HTTP.');

        $tok = $ctx->csrf($admin, '/conseil/' . rawurlencode($conseilId));
        $r = $admin->post('/conseil/delete/' . rawurlencode($conseilId), ['csrf_token' => (string) $tok]);
        $s->check('D', 'Suppression d\'un conseil', 'POST /conseil/delete/{id}', '302 → /conseils',
            $r->status . ' → ' . ($r->location() ?? '-'), $r->status === 302 && $r->location() === '/conseils');
        $s->check('D', 'Conseil réellement supprimé', 'SELECT conseils', '0 ligne',
            (string) $ctx->int('SELECT COUNT(*) FROM conseils WHERE id = ?', [$conseilId]),
            $ctx->int('SELECT COUNT(*) FROM conseils WHERE id = ?', [$conseilId]) === 0);
    }

    // --- D10b Tables de lookup (3NF sélective — migration 20261018) ---
    $nbTypes = $ctx->int('SELECT COUNT(*) FROM types_soin');
    $s->check('D', 'Table de lookup types_soin alimentée (vocabulaire fermé, 5 valeurs)', 'SELECT types_soin',
        '5', (string) $nbTypes, $nbTypes === 5);
    $idArrosage = $ctx->scalar("SELECT id FROM types_soin WHERE label = 'arrosage'");
    $s->check('D', 'Le type fermé « arrosage » est résolvable par label', 'SELECT types_soin',
        'id non nul', $idArrosage === null ? 'null' : (string) $idArrosage, $idArrosage !== null);
    $catTest = $ctx->scalar("SELECT id FROM categories_conseil WHERE label = 'Arrosage'");
    $s->check('D', 'Catégorie de conseil créée à la volée par findOrCreateLabel()', 'SELECT categories_conseil',
        'id non nul', $catTest === null ? 'null' : (string) $catTest, $catTest !== null);

    // --- D11 utilisateurs (back-office admin) ---
    $r = $admin->get('/user/new');
    $ctx->calls++;
    $s->check('D', 'Formulaire de création utilisateur', 'GET /user/new', '200', (string) $r->status, $r->status === 200);

    $usrEmail = 'http+usr.' . $ctx->runId . '@example.invalid';
    $tok = $ctx->csrf($admin, '/user/new');
    $r = $admin->post('/update/user', [
        'id'        => '',
        'nom'       => 'Géré',
        'prenom'    => 'Test',
        'email'     => $usrEmail,
        'password'  => 'Gere12!test',
        'role'      => 'user',
        'csrf_token' => (string) $tok,
    ]);
    preg_match('#/user/([0-9a-f\-]{36})#', (string) $r->location(), $m);
    $usrId = $m[1] ?? null;
    $s->check('D', 'Création d\'un utilisateur par l\'admin', 'POST /update/user', '302 → /user/{uuid}',
        $r->status . ' → ' . ($usrId !== null ? '/user/{uuid}' : (string) $r->location()), $r->status === 302 && $usrId !== null);

    if ($usrId !== null) {
        $s->check('D', 'Utilisateur créé en base', 'SELECT users', '1 ligne',
            (string) $ctx->int('SELECT COUNT(*) FROM users WHERE id = ?', [$usrId]),
            $ctx->int('SELECT COUNT(*) FROM users WHERE id = ?', [$usrId]) === 1);

        $tok = $ctx->csrf($admin, '/user/' . $usrId);
        $r = $admin->post('/update/user', [
            'id' => $usrId, 'nom' => 'Géré', 'prenom' => 'Test', 'email' => $usrEmail,
            'role' => 'moderateur', 'csrf_token' => (string) $tok,
        ]);
        $s->check('D', 'Édition de rôle par l\'admin', 'POST /update/user', '302 → /users',
            $r->status . ' → ' . ($r->location() ?? '-'), $r->status === 302 && $r->location() === '/users');
        $s->check('D', 'Nouveau rôle enregistré', 'SELECT users', 'moderateur',
            (string) $ctx->scalar('SELECT role FROM users WHERE id = ?', [$usrId]),
            $ctx->scalar('SELECT role FROM users WHERE id = ?', [$usrId]) === 'moderateur');

        // Garantie d'accès : l'admin ne peut pas se rétrograder lui-même
        $tok = $ctx->csrf($admin, '/user/' . $ctx->ids['admin']['id']);
        $r = $admin->post('/update/user', [
            'id' => $ctx->ids['admin']['id'], 'nom' => 'TestAdmin', 'prenom' => 'Run' . $ctx->runId,
            'email' => $ctx->ids['admin']['email'], 'role' => 'user', 'csrf_token' => (string) $tok,
        ]);
        $s->check('D', 'Auto-rétrogradation d\'un admin refusée', 'POST /update/user',
            '302 → /user/{soi} et rôle admin conservé', $r->status . ' → ' . ($r->location() ?? '-'),
            $r->status === 302 && (string) $ctx->scalar('SELECT role FROM users WHERE id = ?', [$ctx->ids['admin']['id']]) === 'admin');

        $tok = $ctx->csrf($admin, '/users');
        $r = $admin->post('/user/delete/' . $usrId, ['csrf_token' => (string) $tok]);
        $s->check('D', 'Suppression d\'un utilisateur', 'POST /user/delete/{id}', '302 → /users',
            $r->status . ' → ' . ($r->location() ?? '-'), $r->status === 302 && $r->location() === '/users');
        $s->check('D', 'Utilisateur réellement supprimé', 'SELECT users', '0 ligne',
            (string) $ctx->int('SELECT COUNT(*) FROM users WHERE id = ?', [$usrId]),
            $ctx->int('SELECT COUNT(*) FROM users WHERE id = ?', [$usrId]) === 0);
    }

    // L'admin ne peut pas se supprimer lui-même (compte de service)
    $tok = $ctx->csrf($admin, '/users');
    $r = $admin->post('/user/delete/' . $ctx->ids['admin']['id'], ['csrf_token' => (string) $tok]);
    $s->check('D', 'Auto-suppression d\'un admin refusée', 'POST /user/delete/{id}',
        'compte toujours présent', (string) $r->status,
        $ctx->int('SELECT COUNT(*) FROM users WHERE id = ?', [$ctx->ids['admin']['id']]) === 1);
}

/* ===========================================================================
   GROUPE E — validation des entrées et échappement (périmètre explicite)
   =========================================================================== */

function scenarios_e(Suite $s, Ctx $ctx, array $pre): void
{
    // E1 — champs manquants à l'inscription
    $c = $ctx->client();
    $tok = $ctx->csrf($c, '/register');
    $email = 'http+E1.' . $ctx->runId . '@example.invalid';
    $r = $c->post('/register', ['nom' => 'Manquant', 'prenom' => '', 'email' => $email, 'password' => '', 'csrf_token' => (string) $tok]);
    $s->check('E', 'Inscription avec champs obligatoires vides', 'POST /register', '302 → /register',
        $r->status . ' → ' . ($r->location() ?? '-'), $r->status === 302 && $r->location() === '/register');
    $s->check('E', 'Aucun compte créé sur entrée invalide', 'SELECT users', '0 ligne',
        (string) $ctx->int('SELECT COUNT(*) FROM users WHERE email = ?', [$email]),
        $ctx->int('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) === 0);

    // E2 — email déjà utilisé
    $before = $ctx->int('SELECT COUNT(*) FROM users');
    $r = $c->post('/register', ['nom' => 'Doublon', 'prenom' => 'Test', 'email' => $ctx->ids['userA']['email'], 'password' => 'Doublon12!', 'csrf_token' => (string) $tok]);
    $s->check('E', 'Inscription avec un email déjà pris', 'POST /register', '302 → /register',
        $r->status . ' → ' . ($r->location() ?? '-'), $r->status === 302 && $r->location() === '/register');
    $s->check('E', 'Aucune ligne ajoutée sur email en double', 'SELECT users', (string) $before,
        (string) $ctx->int('SELECT COUNT(*) FROM users'), $ctx->int('SELECT COUNT(*) FROM users') === $before);

    // E3 — référence d'espèce inexistante
    [$userA, $res] = $ctx->login('userA');
    $tok = $ctx->csrf($userA, '/macollection');
    $count = $ctx->int('SELECT COUNT(*) FROM collections');
    $r = $userA->post('/collection/new', ['orchid_id' => 'espece-inexistante-' . $ctx->runId, 'csrf_token' => (string) $tok]);
    $s->check('E', 'Ajout d\'une espèce inexistante à la collection', 'POST /collection/new',
        '302 → /macollection (refus)', $r->status . ' → ' . ($r->location() ?? '-'),
        $r->status === 302 && $r->location() === '/macollection');
    $s->check('E', 'Aucune collection orpheline créée', 'SELECT collections', (string) $count,
        (string) $ctx->int('SELECT COUNT(*) FROM collections'), $ctx->int('SELECT COUNT(*) FROM collections') === $count);

    // E4 — échappement HTML d'une donnée stockée puis relue (contexte HTML)
    $payload = '<script>alert(1)</script>';
    if (($pre['colA'] ?? null) !== null) {
        $tok = $ctx->csrf($userA, '/collection/' . $pre['colA']);
        $r = $userA->post('/update/collection', ['id' => $pre['colA'], 'notes' => $payload, 'csrf_token' => (string) $tok]);
        $s->check('E', 'Enregistrement d\'une note contenant une balise script', 'POST /update/collection',
            '302 (aucune erreur)', (string) $r->status, $r->status === 302);

        $raw = (string) $ctx->scalar('SELECT notes FROM collections WHERE id = ?', [$pre['colA']]);
        $s->check('E', 'La donnée est stockée telle quelle (l\'échappement est un rôle de la vue)', 'SELECT collections',
            $payload, $raw, $raw === $payload);

        $page = $userA->get('/collection/' . $pre['colA']);
        $ctx->calls++;
        $escaped = body_contains($page->body, '&lt;script&gt;alert(1)&lt;/script&gt;');
        $naked   = body_contains($page->body, '<script>alert(1)</script>');
        $s->check('E', 'Rendu HTML de la note : balise échappée', 'GET /collection/{id}',
            'contenu affiché en &lt;script&gt;', $escaped ? 'échappé' : 'non échappé', $escaped);
        $s->check('E', 'Aucun script exécutable injecté dans la page relue', 'GET /collection/{id}',
            'aucune balise <script> issue de la note', $naked ? 'balise nue présente' : 'absente', !$naked);
    } else {
        $s->blocked('E', 'Échappement d\'une note stockée', 'GET /collection/{id}', 'collection de test indisponible');
    }

    // E5 — reflection dans une réponse JSON (contexte JSON, pas HTML)
    $anon = $ctx->client();
    $r = $anon->get('/orchids/search?q=' . rawurlencode($payload));
    $ctx->calls++;
    $ct = (string) $r->header('content-type');
    $s->check('E', 'Recherche avec charge utile script (réponse JSON)', 'GET /orchids/search?q=…',
        '200 + Content-Type application/json', $r->status . ' / ' . $ct,
        $r->status === 200 && str_starts_with($ct, 'application/json'));
    $s->check('E', 'La charge utile n\'est pas réinjectée dans le corps de la réponse', 'GET /orchids/search?q=…',
        'aucune balise script dans le corps', body_contains($r->body, '<script>alert(1)') ? 'présente' : 'absente',
        !body_contains($r->body, '<script>alert(1)'));

    // E6 — entrées hostiles dans l'URL (aucune erreur serveur)
    $hostile = ["' OR 1=1 --", '%00', '../../../etc/passwd', '10000000000000000000', '[]'];
    foreach ($hostile as $i => $probe) {
        $r = $anon->get('/orchid/' . rawurlencode($probe));
        $ctx->calls++;
        $s->check('E', 'Identifiant hostile n° ' . ($i + 1) . ' sur une fiche', 'GET /orchid/{id}',
            '404 (jamais 5xx)', (string) $r->status, $r->status === 404);
    }

    // E7 — tentative d'injection SQL dans un terme de recherche
    $total = $ctx->int('SELECT COUNT(*) FROM orchids');
    $r = $anon->get('/orchids/search?q=' . rawurlencode("' ; DROP TABLE orchids; -- " . $ctx->runId));
    $ctx->calls++;
    $s->check('E', 'Recherche avec syntaxe SQL hostile', 'GET /orchids/search?q=…', '200 (aucun effet sur les données)',
        (string) $r->status, $r->status === 200);
    $s->check('E', 'Table intacte après la charge SQL', 'SELECT orchids', (string) $total,
        (string) $ctx->int('SELECT COUNT(*) FROM orchids'), $ctx->int('SELECT COUNT(*) FROM orchids') === $total);

    // E8 — entrée très longue (TEXT MySQL) : pas de 500
    if (($pre['colA'] ?? null) !== null) {
        $long = str_repeat('a', 3000);
        $tok = $ctx->csrf($userA, '/collection/' . $pre['colA']);
        $r = $userA->post('/update/collection', ['id' => $pre['colA'], 'notes' => $long, 'csrf_token' => (string) $tok]);
        $len = (int) $ctx->scalar('SELECT CHAR_LENGTH(notes) FROM collections WHERE id = ?', [$pre['colA']]);
        $s->check('E', 'Note de 3000 caractères', 'POST /update/collection', '302 (aucune erreur serveur)',
            (string) $r->status, $r->status === 302);
        $s->check('E', 'Longueur réellement stockée', 'SELECT collections', '3000', (string) $len, $len === 3000);
    }
}

/* ===========================================================================
   GROUPE F — erreurs, robustesse, absence de fuite
   =========================================================================== */

function scenarios_f(Suite $s, Ctx $ctx, array $pre): void
{
    $anon = $ctx->client();

    // F1 — route inconnue
    $r = $anon->get('/route-inexistante-' . $ctx->runId);
    $ctx->calls++;
    $s->check('F', 'Route non déclarée', 'GET /route-inexistante', '404', (string) $r->status, $r->status === 404);

    // F2/F3/F4 — méthode non autorisée sur route existante
    $r = $anon->request('DELETE', '/orchids');
    $ctx->calls++;
    $s->check('F', 'Méthode DELETE non déclarée', 'DELETE /orchids', '405', (string) $r->status, $r->status === 405);

    $r = $anon->get('/logout');
    $ctx->calls++;
    $s->check('F', 'GET sur une route POST (déconnexion)', 'GET /logout', '405', (string) $r->status, $r->status === 405);

    $r = $anon->post('/orchids', ['name' => 'x']);
    $ctx->calls++;
    $s->check('F', 'POST sur une route GET (catalogue)', 'POST /orchids', '405', (string) $r->status, $r->status === 405);

    $r = $anon->get('/notification/' . ($pre['notifA'] ?? '00000000-0000-4000-8000-000000000000') . '/delete');
    $ctx->calls++;
    $s->check('F', 'Chemin de route inventé dans le parcours', 'GET /notification/{id}/delete', '405 ou 404 (jamais 5xx)',
        (string) $r->status, $r->status === 405 || $r->status === 404);

    // F5 — ressource absente
    $r = $anon->get('/orchid/oid-n-existent-pas-' . $ctx->runId);
    $ctx->calls++;
    $s->check('F', 'Fiche orchidée absente', 'GET /orchid/{id}', '404', (string) $r->status, $r->status === 404);

    $r = $anon->get('/conseil/conseil-absent-' . $ctx->runId);
    $ctx->calls++;
    $s->check('F', 'Fiche conseil absente', 'GET /conseil/{id}', '404', (string) $r->status, $r->status === 404);

    [$userA, $res] = $ctx->login('userA');
    $r = $userA->get('/collection/00000000-0000-4000-8000-000000000000');
    $ctx->calls++;
    $s->check('F', 'Collection inexistante pour un utilisateur connecté', 'GET /collection/{id}', '404', (string) $r->status, $r->status === 404);

    $r = $userA->get('/notification/notification-absente');
    $ctx->calls++;
    $s->check('F', 'Notification inexistante', 'GET /notification/{id}', '404', (string) $r->status, $r->status === 404);

    [$admin, $res] = $ctx->login('admin');
    $r = $admin->get('/user/00000000-0000-4000-8000-000000000000');
    $ctx->calls++;
    $s->check('F', 'Fiche utilisateur absente (admin)', 'GET /user/{id}', '404', (string) $r->status, $r->status === 404);

    $r = $admin->get('/update/orchid/orchidee-absente');
    $ctx->calls++;
    $s->check('F', 'Formulaire d\'édition d\'une fiche absente', 'GET /update/orchid/{id}', '404', (string) $r->status, $r->status === 404);

    // F6 — paramètres manquants ou vides
    $tok = $ctx->csrf($userA, '/macollection');
    $r = $userA->post('/update/collection', ['id' => '', 'notes' => 'x', 'csrf_token' => (string) $tok]);
    $s->check('F', 'Mise à jour sans identifiant', 'POST /update/collection', '302 → /macollection',
        $r->status . ' → ' . ($r->location() ?? '-'), $r->status === 302 && $r->location() === '/macollection');

    $r = $userA->post('/soin/new', ['collection_id' => '', 'type' => 'arrosage', 'csrf_token' => (string) $tok]);
    $soinsAfter = $ctx->int('SELECT COUNT(*) FROM soins');
    $s->check('F', 'Soin sans rattachement de plante', 'POST /soin/new', '302 → /macollection, aucune écriture',
        $r->status . ' → ' . ($r->location() ?? '-'), $r->status === 302 && $r->location() === '/macollection');

    $r = $userA->post('/collection/delete/identifiant-faux', ['csrf_token' => (string) $tok]);
    $s->check('F', 'Suppression d\'une ressource inexistante', 'POST /collection/delete/{id}', '302 → /macollection',
        $r->status . ' → ' . ($r->location() ?? '-'), $r->status === 302 && $r->location() === '/macollection');

    // F7 — session absente sur les routes authentifiées
    $fresh = $ctx->client();
    $r = $fresh->get('/me', ['cookies' => false]);
    $ctx->calls++;
    $s->check('F', 'Route authentifiée sans aucun cookie de session', 'GET /me', '403', (string) $r->status, $r->status === 403);

    $r = $fresh->get('/collections', ['cookies' => false]);
    $ctx->calls++;
    $s->check('F', 'API JSON protégée sans session', 'GET /collections', '403', (string) $r->status, $r->status === 403);

    // F8 — traversée de chemin hors du docroot
    $r = $anon->get('/' . rawurlencode('../config/db.php'));
    $ctx->calls++;
    $leak = body_contains($r->body, 'getDb') || body_contains($r->body, 'DB_DSN');
    $s->check('F', 'Tentative d\'accès à un fichier de configuration', 'GET /../config/db.php',
        'aucun contenu de source servi', $r->status . ($leak ? ' (fuite)' : ''), $r->status !== 200 && !$leak);

    // F9 — les pages d'erreur ne révélent rien d'interne
    $samples = [
        ['GET', '/route-inexistante-' . $ctx->runId],
        ['GET', '/orchid/absent-' . $ctx->runId],
        ['DELETE', '/orchids'],
        ['GET', '/macollection'],
    ];
    foreach ($samples as $i => [$method, $path]) {
        $c = $ctx->client();
        $r = $c->request($method, $path);
        $ctx->calls++;
        $s->check('F', 'Page d\'erreur n° ' . ($i + 1) . ' sans fuite interne', $method . ' ' . $path,
            'aucune trace, aucune information SQL', body_leaks_internals($r->body) ? 'fuite détectée' : 'aucune fuite',
            !body_leaks_internals($r->body));
    }

    // F10 — aucune réponse 5xx sur l'ensemble de la suite
    $errors = 0;
    foreach ($ctx->clients as $cl) {
        $errors += $cl->countServerErrors();
    }
    $s->check('F', 'Aucune réponse 5xx sur l\'ensemble des parcours HTTP', 'toutes les requêtes émises',
        '0', (string) $errors, $errors === 0);
}

/* ===========================================================================
   GROUPE C1 — notification persistante des rappels dus (jour J)
   Flux GET /api/rappels/dus + marquage « lu » POST /notification/{id}/read en
   JSON. Toutes les requêtes passent par le vrai serveur et les vraies routes.
   =========================================================================== */

function scenarios_c1(Suite $s, Ctx $ctx, array $pre): void
{
    $uidB = $ctx->ids['userB']['id'];
    $uidA = $ctx->ids['userA']['id'];

    // Fixtures : rappels de soin marqués, insérés pour le seul compte de test
    // userB (nettoyés par cascade). Un échu planifié (doit être activé puis
    // remonter), un futur, un déjà lu, un traité — plus un rappel d'userA.
    $make = static function (string $id, string $uid, string $statut, string $date, int $lu) use ($ctx): void {
        $stmt = $ctx->db->prepare(
            "INSERT INTO notifications (id, user_id, collection_id, type, message, is_read, statut, id_type_soin, notif_date)
             VALUES (?, ?, NULL, 'rappel_soin', ?, ?, ?, NULL, ?)"
        );
        $stmt->execute([$id, $uid, 'Rappel C1 ' . $id, $lu, $statut, $date]);
    };
    $dusId     = 'httptest-c1-dus-'     . $ctx->runId;
    $futurId   = 'httptest-c1-futur-'   . $ctx->runId;
    $luId      = 'httptest-c1-lu-'      . $ctx->runId;
    $traiteId  = 'httptest-c1-traite-'  . $ctx->runId;
    $tierId    = 'httptest-c1-tier-'    . $ctx->runId;
    $make($dusId,    $uidB, 'planifie', date('Y-m-d H:i:s', strtotime('-1 day')), 0);
    $make($futurId,  $uidB, 'planifie', date('Y-m-d H:i:s', strtotime('+3 day')), 0);
    $make($luId,     $uidB, 'actif',    date('Y-m-d H:i:s', strtotime('-2 day')), 1);
    $make($traiteId, $uidB, 'traite',   date('Y-m-d H:i:s', strtotime('-1 day')), 0);
    $make($tierId,   $uidA, 'actif',    date('Y-m-d H:i:s', strtotime('-1 day')), 0);

    [$userB] = $ctx->login('userB');

    // C1-1 — Le rappel échu, activé à cette demande, remonte à son propriétaire.
    $r = $userB->get('/api/rappels/dus', ['headers' => ['Accept: application/json']]);
    $ctx->calls++;
    $json = $r->json();
    $s->check('C1', 'Flux JSON des rappels dus (200 + status ok)', 'GET /api/rappels/dus (auth)', '200 · status ok',
        (string) $r->status . ' · ' . (string) ($json['status'] ?? '?'),
        $r->status === 200 && ($json['status'] ?? '') === 'ok');
    $idsRetournes = array_column($json['rappels'] ?? [], 'id');
    $s->check('C1', 'Rappel échu non lu renvoyé à son propriétaire', 'GET /api/rappels/dus', 'contient ' . $dusId,
        in_array($dusId, $idsRetournes, true) ? 'présent' : 'absent', in_array($dusId, $idsRetournes, true));
    $s->check('C1', 'activateDueReminders réutilisé : le planifié échu est devenu actif', 'SELECT notifications',
        'actif', (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$dusId]),
        (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$dusId]) === 'actif');

    // Contrat de forme : champs exacts de Notification::toArray() + late documenté.
    $item = null;
    foreach (($json['rappels'] ?? []) as $ligne) {
        if (($ligne['id'] ?? '') === $dusId) { $item = $ligne; break; }
    }
    $champsAttendus = ['id', 'type', 'typeLabel', 'message', 'isRead', 'statut', 'idTypeSoin',
                       'typeSoinLabel', 'collectionId', 'orchidName', 'notifDate', 'dateFr', 'late'];
    $champsOK = $item !== null && count(array_diff($champsAttendus, array_keys($item))) === 0;
    $s->check('C1', 'Stabilité du JSON : chaque rappel porte les champs toArray + late', 'GET /api/rappels/dus',
        $champsAttendus ? '13 champs' : '', $champsOK ? 'contractuels' : 'différents', $champsOK);
    $s->check('C1', 'Jeton CSRF transmis uniquement dans la réponse authentifiée', 'GET /api/rappels/dus',
        'csrf 64 hex', (string) strlen((string) ($json['csrf'] ?? '')),
        preg_match('/^[0-9a-f]{64}$/', (string) ($json['csrf'] ?? '')) === 1);

    // C1-2/3/4 — Éligibilité stricte : futur, déjà lu et traité exclus.
    $s->check('C1', 'Rappel à échéance future non renvoyé', 'GET /api/rappels/dus', 'absent',
        in_array($futurId, $idsRetournes, true) ? 'présent' : 'absent', !in_array($futurId, $idsRetournes, true));
    $s->check('C1', 'Rappel déjà lu non renvoyé', 'GET /api/rappels/dus', 'absent',
        in_array($luId, $idsRetournes, true) ? 'présent' : 'absent', !in_array($luId, $idsRetournes, true));
    $s->check('C1', 'Rappel traité non renvoyé', 'GET /api/rappels/dus', 'absent',
        in_array($traiteId, $idsRetournes, true) ? 'présent' : 'absent', !in_array($traiteId, $idsRetournes, true));

    // C1-5 — Non authentifié : le flux protégé est refusé (403 du grant).
    $anon = $ctx->client();
    $r = $anon->get('/api/rappels/dus', ['headers' => ['Accept: application/json']]);
    $ctx->calls++;
    $s->check('C1', 'Accès anonyme au flux refusé', 'GET /api/rappels/dus (anon)', '403',
        (string) $r->status, $r->status === 403);
    $s->check('C1', 'Le refus anonyme ne révèle aucune donnée', 'GET /api/rappels/dus (anon)',
        'aucun identifiant de rappel', body_contains($r->body, $dusId) ? 'fuite' : 'aucune fuite',
        !body_contains($r->body, $dusId) && !body_contains($r->body, 'Rappel C1'));

    // C1-6 — Isolation RG1 en lecture : userA ne voit jamais le rappel de userB.
    [$userA] = $ctx->login('userA');
    $r = $userA->get('/api/rappels/dus', ['headers' => ['Accept: application/json']]);
    $ctx->calls++;
    $jsonA  = $r->json();
    $idsA   = array_column($jsonA['rappels'] ?? [], 'id');
    $s->check('C1', 'RG1 : le rappel d\'autrui est absent du flux d\'un autre propriétaire', 'GET /api/rappels/dus (userA)',
        'absent · le sien présent',
        (in_array($dusId, $idsA, true) ? 'fuite' : 'isolé') . ' · ' . (in_array($tierId, $idsA, true) ? 'son rappel visible' : 'son rappel invisible'),
        !in_array($dusId, $idsA, true) && in_array($tierId, $idsA, true));

    // C1-7 — CSRF : absent puis invalide → 400, aucun changement d'état.
    $r = $userB->post('/notification/' . $dusId . '/read', [], ['headers' => ['Accept: application/json']]);
    $ctx->calls++;
    $s->check('C1', 'CSRF absent refusé sur la route de lecture', 'POST /notification/{id}/read', '400',
        (string) $r->status, $r->status === 400);
    $r = $userB->post('/notification/' . $dusId . '/read', ['csrf_token' => str_repeat('0', 64)],
        ['headers' => ['Accept: application/json']]);
    $ctx->calls++;
    $s->check('C1', 'CSRF invalide refusé, rappel toujours non lu', 'POST /notification/{id}/read', '400 · is_read=0',
        (string) $r->status . ' · is_read=' . (string) $ctx->scalar('SELECT is_read FROM notifications WHERE id = ?', [$dusId]),
        $r->status === 400 && (int) $ctx->scalar('SELECT is_read FROM notifications WHERE id = ?', [$dusId]) === 0);

    // C1-6 bis — RG1 en écriture : userA, même avec SON jeton CSRF valide, ne peut
    // pas marquer lu le rappel de userB (404 côté JSON, état inchangé côté B).
    $csrfA = $ctx->csrf($userA, '/rappels');
    $r = $userA->post('/notification/' . $dusId . '/read', ['csrf_token' => (string) $csrfA],
        ['headers' => ['Accept: application/json']]);
    $s->check('C1', 'RG1 : marquage lu d\'un rappel tiers impossible (404, état intact)', 'POST /notification/{id}/read (userA)',
        '404 · not_found · is_read=0',
        (string) $r->status . ' · ' . (string) (($r->json()['status'] ?? '?')) . ' · is_read=' .
        (string) $ctx->scalar('SELECT is_read FROM notifications WHERE id = ?', [$dusId]),
        $r->status === 404 && ($r->json()['status'] ?? '') === 'not_found'
            && (int) $ctx->scalar('SELECT is_read FROM notifications WHERE id = ?', [$dusId]) === 0);

    // C1-8 — Lecture réussie : le rappel quitte le flux des non lus.
    $csrfB = $ctx->csrf($userB, '/rappels');
    $r = $userB->post('/notification/' . $dusId . '/read', ['csrf_token' => (string) $csrfB],
        ['headers' => ['Accept: application/json']]);
    $okRead = $r->status === 200 && ($r->json()['status'] ?? '') === 'ok';
    $s->check('C1', 'Marquage « lu » JSON par le propriétaire (200 ok)', 'POST /notification/{id}/read (userB)',
        '200 · ok', (string) $r->status . ' · ' . (string) (($r->json()['status'] ?? '?')), $okRead);
    $r = $userB->get('/api/rappels/dus', ['headers' => ['Accept: application/json']]);
    $ctx->calls++;
    $idsApres = array_column(($r->json()['rappels'] ?? []), 'id');
    $s->check('C1', 'Rappel lu : rendu inéligible au flux non lu', 'GET /api/rappels/dus (après lecture)', 'absent',
        in_array($dusId, $idsApres, true) ? 'encore présent' : 'absent', !in_array($dusId, $idsApres, true));

    // C1-10 — Plusieurs chargements successifs : aucune création, aucun doublon.
    $nbAvant = $ctx->int('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$uidB]);
    $userB->get('/api/rappels/dus', ['headers' => ['Accept: application/json']]);
    $userB->get('/api/rappels/dus', ['headers' => ['Accept: application/json']]);
    $ctx->calls += 2;
    $nbApres = $ctx->int('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$uidB]);
    $s->check('C1', 'Chargements répétés : aucun rappel créé, aucun doublon', 'GET /api/rappels/dus ×2',
        (string) $nbAvant, (string) $nbApres, $nbAvant === $nbApres);

    // C1-11 — Régression de surface : le parcours HTML /rappels (C0) reste rendu.
    $r = $userB->get('/rappels');
    $ctx->calls++;
    $s->check('C1', 'La vue /rappels (C0) reste opérationnelle', 'GET /rappels', '200',
        (string) $r->status, $r->status === 200);

    // C1-9 — Échec réseau côté client : ressort du module JS (aucun retrait de
    // toast sans 200 ok) — vérifiable uniquement dans le navigateur.
    $s->notRun('C1', 'Échec réseau ne marque pas le rappel lu (comportement fetch)', 'navigateur',
        'simulation réseau coupée côté client', 'test HTTP serveur réel impossible ; protocole manuel documenté au rapport C1');

    // Nettoyage explicite des fixtures C1 (userB survivra au run, ses notifs non).
    $ctx->db->prepare('DELETE FROM notifications WHERE id IN (?, ?, ?, ?, ?)')
        ->execute([$dusId, $futurId, $luId, $traiteId, $tierId]);
}

/* ===========================================================================
   GROUPE MDL — Phase D : modale « Gérer mes rappels » (fragment + entrées)
   =========================================================================== */

/**
 * Tests HTTP réels du fragment GET /rappels/modal et de ses intégrations :
 * sections/compteurs/badges, RG1 en lecture et en écriture, CSRF, absence de
 * mutation sur GET, entrée sur les pages collection, repli /rappels intact.
 * Les comportements purement navigateur (ouverture, focus, réseau coupé) sont
 * NOT RUN avec protocole manuel documenté en docs/project-foundation/phase-d/.
 */
function scenarios_mdl(Suite $s, Ctx $ctx, array $pre): void
{
    $uidB = $ctx->ids['userB']['id'];
    $uidA = $ctx->ids['userA']['id'];

    // Fixtures : un rappel par section + un pour l'action « fait » + un pour le
    // rejet CSRF + un rappel tiers (userA) pour les contrôles RG1.
    $make = static function (string $id, string $uid, string $statut, string $date, string $libelle) use ($ctx): void {
        $stmt = $ctx->db->prepare(
            "INSERT INTO notifications (id, user_id, collection_id, type, message, is_read, statut, id_type_soin, notif_date)
             VALUES (?, ?, NULL, 'rappel_soin', ?, 0, ?, NULL, ?)"
        );
        $stmt->execute([$id, $uid, $libelle . ' ' . $ctx->runId, $statut, $date]);
    };
    $j          = date('Y-m-d H:i:s');
    $mdlJour    = 'httptest-mdl-jour-'    . $ctx->runId;
    $mdlRetard  = 'httptest-mdl-retard-'  . $ctx->runId;
    $mdlAvenir  = 'httptest-mdl-avenir-'  . $ctx->runId;
    $mdlHist    = 'httptest-mdl-hist-'    . $ctx->runId;
    $mdlFait    = 'httptest-mdl-fait-'    . $ctx->runId;
    $mdlCsrf    = 'httptest-mdl-csrf-'    . $ctx->runId;
    $mdlTier    = 'httptest-mdl-tier-'    . $ctx->runId;
    $make($mdlJour,   $uidB, 'actif',    $j,                                        'Rappel MDL du jour');
    $make($mdlRetard, $uidB, 'actif',    date('Y-m-d H:i:s', strtotime('-2 day')),  'Rappel MDL en retard');
    $make($mdlAvenir, $uidB, 'planifie', date('Y-m-d H:i:s', strtotime('+5 day')),  'Rappel MDL à venir');
    $make($mdlHist,   $uidB, 'traite',   date('Y-m-d H:i:s', strtotime('-1 day')),  'Rappel MDL historique');
    $make($mdlFait,   $uidB, 'actif',    $j,                                        'Rappel MDL à clôturer');
    $make($mdlCsrf,   $uidB, 'actif',    $j,                                        'Rappel MDL sans jeton');
    $make($mdlTier,   $uidA, 'actif',    date('Y-m-d H:i:s', strtotime('-1 day')),  'Rappel MDL tiers');

    [$userB] = $ctx->login('userB');

    // MDL-1 — Fragment authentifié : 200, contenu seul (pas de layout), sections dues.
    $r = $userB->get('/rappels/modal');
    $ctx->calls++;
    $s->check('MDL', 'GET /rappels/modal authentifié : 200 fragment sans layout', 'GET /rappels/modal (userB)',
        '200 · sans <!DOCTYPE>',
        $r->status . ' · ' . (stripos($r->body, '<!doctype') === false ? 'sans doctype' : 'layout fuitem'),
        $r->status === 200 && stripos($r->body, '<!doctype') === false);

    // MDL-2 — Les quatre sémantiques de section sont rendues avec les marqueurs.
    $s->check('MDL', 'Section « À faire aujourd\'hui » : rappel du jour présent', 'GET /rappels/modal',
        'contient jour + section',
        (body_contains($r->body, 'Rappel MDL du jour ' . $ctx->runId) && body_contains($r->body, "À faire aujourd'hui")) ? 'présent' : 'absent',
        body_contains($r->body, 'Rappel MDL du jour ' . $ctx->runId) && body_contains($r->body, "À faire aujourd'"));
    $s->check('MDL', 'Section « En retard » : rappel dépassé + compteur de jours', 'GET /rappels/modal',
        'contient retard + badge',
        (body_contains($r->body, 'Rappel MDL en retard ' . $ctx->runId) && body_contains($r->body, 'En retard')) ? 'présent' : 'absent',
        body_contains($r->body, 'Rappel MDL en retard ' . $ctx->runId) && body_contains($r->body, 'En retard'));
    $s->check('MDL', 'Section « À venir » : planifié non encore dû, resté planifié', 'GET /rappels/modal',
        'contient avenir · statut planifie',
        (body_contains($r->body, 'Rappel MDL à venir ' . $ctx->runId) && $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$mdlAvenir]) === 'planifie') ? 'présent' : 'absent',
        body_contains($r->body, 'Rappel MDL à venir ' . $ctx->runId) && (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$mdlAvenir]) === 'planifie');
    $s->check('MDL', 'Historique : rappels clos listés (lignes cohérentes avec la base + badge « Traité »)', 'GET /rappels/modal',
        'lignes = DB · badge',
        substr_count($r->body, '<li class="rappel-histo') . ' lignes · ' . (body_contains($r->body, 'Traité') ? 'badge' : 'sans badge'),
        substr_count($r->body, '<li class="rappel-histo')
            === $ctx->int("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'rappel_soin' AND statut IN ('traite','ignore')", [$uidB])
            && body_contains($r->body, 'Traité'));

    // MDL-3 — RG1 en lecture : rien de tiers dans le fragment ; le tiers ne voit que son rappel.
    $s->check('MDL', 'RG1 : le fragment de userB ne contient aucun rappel de userA', 'GET /rappels/modal (userB)',
        'marqueur tiers absent', body_contains($r->body, 'Rappel MDL tiers ' . $ctx->runId) ? 'fuite !' : 'absent',
        !body_contains($r->body, 'Rappel MDL tiers ' . $ctx->runId));
    [$userA] = $ctx->login('userA');
    $rA = $userA->get('/rappels/modal');
    $ctx->calls++;
    $s->check('MDL', 'RG1 : userA ne voit que son propre rappel dû', 'GET /rappels/modal (userA)',
        '200 · tiers présent · B absents',
        $rA->status . ' · ' . (body_contains($rA->body, 'Rappel MDL tiers ' . $ctx->runId) ? 'tiers présent' : 'tiers absent')
            . ' · ' . (body_contains($rA->body, 'Rappel MDL du jour') ? 'fuite B !' : 'B absent'),
        $rA->status === 200
            && body_contains($rA->body, 'Rappel MDL tiers ' . $ctx->runId)
            && !body_contains($rA->body, 'Rappel MDL du jour')
            && !body_contains($rA->body, 'Rappel MDL en retard'));

    // MDL-4 — Anonyme : 403 du routeur, aucun marqueur de rappel ne fuit.
    $anon = $ctx->client();
    $rAn = $anon->get('/rappels/modal');
    $ctx->calls++;
    $s->check('MDL', 'Accès anonyme au fragment refusé (403, contenu non fuite)', 'GET /rappels/modal (anonyme)',
        '403 · 0 marqueur',
        $rAn->status . ' · ' . (body_contains($rAn->body, 'Rappel MDL') ? 'fuite !' : 'aucun marqueur'),
        $rAn->status === 403 && !body_contains($rAn->body, 'Rappel MDL'));

    // MDL-5 — GET pur : ne mute rien, ne crée rien, même répété.
    $nbNotifs = $ctx->int('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$uidB]);
    $nbSoins  = $ctx->int('SELECT COUNT(*) FROM soins');
    $userB->get('/rappels/modal');
    $userB->get('/rappels/modal');
    $ctx->calls += 2;
    $s->check('MDL', 'Chargements répétés : aucune notification créée (GET sans effet de bord)', 'GET /rappels/modal ×2',
        (string) $nbNotifs, (string) $ctx->int('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$uidB]),
        $nbNotifs === $ctx->int('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$uidB]));
    $s->check('MDL', 'Aucun soin consigné par un simple chargement du fragment', 'SELECT soins',
        (string) $nbSoins, (string) $ctx->int('SELECT COUNT(*) FROM soins'),
        $nbSoins === $ctx->int('SELECT COUNT(*) FROM soins'));

    // MDL-6 — Le fragment embarque les formulaires POST réels + jeton CSRF 64 hex.
    $jeton = null;
    if (preg_match('#name="csrf_token" value="([0-9a-f]{64})"#', $r->body, $m)) {
        $jeton = $m[1];
    }
    $s->check('MDL', 'Fragment : actions POST C0 présentes et jeton CSRF 64 hex embarqué', 'GET /rappels/modal',
        '/fait + /ignorer + /report + csrf valide',
        (body_contains($r->body, '/fait') ? '/fait ' : '') . (body_contains($r->body, '/ignorer') ? '/ignorer ' : '')
            . (body_contains($r->body, '/report/') ? '/report ' : '') . ($jeton !== null ? 'csrf ok' : 'csrf absent'),
        body_contains($r->body, '/fait') && body_contains($r->body, '/ignorer')
            && body_contains($r->body, '/report/') && $jeton !== null);

    // MDL-7 — Action « effectuer le soin » (rappel global : clôture seule, C0
    // ne consigne rien sans plante) puis relecture : l'état affiché persiste.
    $nbCartes = substr_count($r->body, '<article class="rappel-card');
    $tok = $ctx->csrf($userB, '/rappels');
    $r = $userB->post('/rappel/' . $mdlFait . '/fait', ['csrf_token' => (string) $tok]);
    $s->check('MDL', 'POST /rappel/{id}/fait depuis le flux modale : 302', 'POST /rappel/{id}/fait (userB)',
        '302 → /rappels', $r->status . ' → ' . ($r->location() ?? '-'),
        $r->status === 302 && $r->location() === '/rappels');
    $s->check('MDL', 'Rappel clos côté serveur (statut traite persisté)', 'SELECT notifications',
        'traite', (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$mdlFait]),
        (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$mdlFait]) === 'traite');
    $r = $userB->get('/rappels/modal');
    $ctx->calls++;
    $nbCartesApres = substr_count($r->body, '<article class="rappel-card');
    $nbHistoApres  = substr_count($r->body, '<li class="rappel-histo');
    $s->check('MDL', 'Rechargement du fragment : carte ouverte en moins, ligne d\'historique en plus (état persisté)', 'GET /rappels/modal (après fait)',
        $nbCartes . '→' . ($nbCartes - 1) . ' cartes · histo=DB',
        $nbCartesApres . ' cartes · ' . $nbHistoApres . ' lignes histo (DB ' . $ctx->int("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'rappel_soin' AND statut IN ('traite','ignore')", [$uidB]) . ')',
        $nbCartesApres === $nbCartes - 1
            && $nbHistoApres === $ctx->int("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'rappel_soin' AND statut IN ('traite','ignore')", [$uidB]));
    $statutApres = (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$mdlFait]);
    $s->check('MDL', 'Cohérence persistée : le fragment rechargé reflète la base (pas d\'état optimiste)', 'SELECT + GET',
        'traite · fragment 200', $statutApres . ' · ' . (string) $r->status,
        $statutApres === 'traite' && $r->status === 200);

    // MDL-8 — CSRF absent puis invalide sur l'écriture : 400, état intact.
    $r = $userB->post('/rappel/' . $mdlCsrf . '/fait', []);
    $s->check('MDL', 'CSRF absent refusé sur la carte d\'action (400)', 'POST /rappel/{id}/fait sans jeton',
        '400 · statut intact', $r->status . ' · ' . (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$mdlCsrf]),
        $r->status === 400 && (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$mdlCsrf]) === 'actif');
    $r = $userB->post('/rappel/' . $mdlCsrf . '/fait', ['csrf_token' => str_repeat('a', 64)]);
    $s->check('MDL', 'CSRF invalide refusé, rappel toujours ouvert', 'POST /rappel/{id}/fait (jeton faux)',
        '400 · statut actif', $r->status . ' · ' . (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$mdlCsrf]),
        $r->status === 400 && (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$mdlCsrf]) === 'actif');

    // MDL-9 — RG1 en écriture : userA ne peut pas clôturer le rappel de userB.
    $tokA = $ctx->csrf($userA, '/rappels');
    $r = $userA->post('/rappel/' . $mdlRetard . '/fait', ['csrf_token' => (string) $tokA]);
    $s->check('MDL', 'RG1 : clôture croisée refusée (302 sans effet, état intact)', 'POST /rappel/{id}/fait (userA → rappel de B)',
        '302 · statut actif · 0 soin',
        $r->status . ' · ' . (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$mdlRetard])
            . ' · ' . $ctx->int('SELECT COUNT(*) FROM soins WHERE collection_id IS NULL') . ' soin',
        $r->status === 302 && (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$mdlRetard]) === 'actif');

    // MDL-J — Négociation JSON de la modale (Phase D) : MÊMES routes, MÊME
    // logique métier, réponse lisible sans suivre de redirection. Le parcours
    // HTML (MDL-7 ci-dessus, 302 + flash) reste le comportement par défaut.
    $jsonOpts = ['headers' => ['Accept: application/json']];

    // MJ-1 « ignorer » en JSON : 200 {status:ok, resultat:ignore} + écarts persistés.
    $tokJ = $ctx->csrf($userB, '/rappels');
    $r = $userB->post('/rappel/' . $mdlCsrf . '/ignorer', ['csrf_token' => (string) $tokJ], $jsonOpts);
    $ctx->calls++;
    $j = $r->json();
    $s->check('MDL', 'JSON : ignorer un rappel (200 ok · statut ignore persisté)', 'POST /rappel/{id}/ignorer (Accept: json)',
        '200 · ok · ignore · db=ignore',
        $r->status . ' · ' . ($j['status'] ?? '-') . ' · ' . ($j['resultat'] ?? '-')
            . ' · db=' . (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$mdlCsrf]),
        $r->status === 200 && ($j['status'] ?? '') === 'ok' && ($j['resultat'] ?? '') === 'ignore'
            && (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$mdlCsrf]) === 'ignore');

    // MJ-2 double-frappe : un rappel déjà clos ne consigne RIEN une seconde fois.
    $soinsNullAvant = $ctx->int('SELECT COUNT(*) FROM soins WHERE collection_id IS NULL');
    $tokJ = $ctx->csrf($userB, '/rappels');
    $r = $userB->post('/rappel/' . $mdlFait . '/fait', ['csrf_token' => (string) $tokJ], $jsonOpts);
    $ctx->calls++;
    $j = $r->json();
    $s->check('MDL', 'JSON : rappel déjà clôturé → info, aucune seconde écriture', 'POST /rappel/{id}/fait (déjà traite)',
        '200 · ok · info · deja_cloture · soins invariables',
        $r->status . ' · ' . ($j['status'] ?? '-') . ' · ' . ($j['type'] ?? '-') . ' · ' . ($j['resultat'] ?? '-')
            . ' · soins ' . $soinsNullAvant . '→' . $ctx->int('SELECT COUNT(*) FROM soins WHERE collection_id IS NULL'),
        $r->status === 200 && ($j['status'] ?? '') === 'ok' && ($j['type'] ?? '') === 'info'
            && ($j['resultat'] ?? '') === 'deja_cloture'
            && $ctx->int('SELECT COUNT(*) FROM soins WHERE collection_id IS NULL') === $soinsNullAvant);

    // MJ-3 RG1 en JSON : le rappel d'un tiers est « introuvable » (200 status ko),
    // jamais 500, jamais le statut d'autrui modifié.
    $tokJA = $ctx->csrf($userA, '/rappels');
    $r = $userA->post('/rappel/' . $mdlRetard . '/fait', ['csrf_token' => (string) $tokJA], $jsonOpts);
    $ctx->calls++;
    $j = $r->json();
    $s->check('MDL', 'JSON + RG1 : rappel tier → status ko (introuvable), état de B intact', 'POST /rappel/{id}/fait (userA → rappel de B)',
        '200 · ko · introuvable · actif',
        $r->status . ' · ' . ($j['status'] ?? '-') . ' · ' . ($j['resultat'] ?? '-')
            . ' · db=' . (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$mdlRetard]),
        $r->status === 200 && ($j['status'] ?? '') === 'ko' && ($j['resultat'] ?? '') === 'introuvable'
            && (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$mdlRetard]) === 'actif');

    // MJ-4 report en JSON : nouvelle échéance renvoyée ET persistée (date postérieure).
    $avant = (string) $ctx->scalar('SELECT notif_date FROM notifications WHERE id = ?', [$mdlAvenir]);
    $tokJ = $ctx->csrf($userB, '/rappels');
    $r = $userB->post('/rappel/' . $mdlAvenir . '/report/7', ['csrf_token' => (string) $tokJ], $jsonOpts);
    $ctx->calls++;
    $j = $r->json();
    $apres = (string) $ctx->scalar('SELECT notif_date FROM notifications WHERE id = ?', [$mdlAvenir]);
    $s->check('MDL', 'JSON : report de 7 jours (200 reporte · échéance repoussée en base)', 'POST /rappel/{id}/report/7',
        '200 · ok · reporte · date > avant',
        $r->status . ' · ' . ($j['status'] ?? '-') . ' · ' . ($j['resultat'] ?? '-') . ' · ' . $avant . ' → ' . $apres,
        $r->status === 200 && ($j['status'] ?? '') === 'ok' && ($j['resultat'] ?? '') === 'reporte'
            && strtotime($apres) > strtotime($avant));

    // MJ-5 refus CSRF en JSON : le front-controller reste sur 400 (page générique),
    // aucune écriture — la modale ne peut donc pas afficher un faux succès.
    $r = $userB->post('/rappel/' . $mdlRetard . '/fait', [], $jsonOpts);
    $ctx->calls++;
    $s->check('MDL', 'JSON sans jeton CSRF : 400 (jamais 200), rappel encore ouvert', 'POST /rappel/{id}/fait (Accept: json, sans jeton)',
        '400 · statut actif', $r->status . ' · ' . (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$mdlRetard]),
        $r->status === 400 && (string) $ctx->scalar('SELECT statut FROM notifications WHERE id = ?', [$mdlRetard]) === 'actif');

    // MJ-6 le contrat JSON d'une action ne contient AUCUNE donnée d'un autre
    // utilisateur et n'expose ni trace interne ni détail technique.
    $tokJ = $ctx->csrf($userB, '/rappels');
    $r = $userB->post('/rappel/' . $mdlRetard . '/ignorer', ['csrf_token' => (string) $tokJ], $jsonOpts);
    $ctx->calls++;
    $j = $r->json();
    $ct = (string) $r->header('content-type');
    $s->check('MDL', 'Réponse JSON d\'action : Content-Type json et aucune fuite interne', 'POST /rappel/{id}/ignorer (Accept: json)',
        'application/json · sans fuite',
        $ct . ' · ' . (body_leaks_internals((string) json_encode($j)) ? 'fuite' : 'aucune fuite'),
        str_contains(strtolower($ct), 'application/json')
            && ($j['status'] ?? '') === 'ok'
            && !body_leaks_internals((string) json_encode($j)));

    // MDL-10 — Entrées : dashboard et fiche plante exposent le déclencheur +
    // le squelette de modale avec l'URL du fragment générée par url().
    $r = $userB->get('/macollection');
    $ctx->calls++;
    $s->check('MDL', 'Tableau de bord : entrée « Gérer mes rappels » + squelette + endpoint', 'GET /macollection',
        '200 · trigger · modal · endpoint',
        $r->status . ' · ' . (body_contains($r->body, 'data-rappels-modal-trigger') ? 'trigger' : 'absent')
            . ' · ' . (body_contains($r->body, 'id="rappels-modal"') ? 'squelette' : 'absent')
            . ' · ' . (body_contains($r->body, 'rappels/modal') ? 'endpoint' : 'absent'),
        $r->status === 200 && body_contains($r->body, 'data-rappels-modal-trigger')
            && body_contains($r->body, 'id="rappels-modal"') && body_contains($r->body, 'rappels/modal'));

    // MDL-10b — Discipline CSP (script-src 'self') : ni le squelette rendu sur
    // /macollection ni le fragment ne portent de gestionnaire en ligne (on*=").
    $pageDash = $r->body;
    $r = $userB->get('/rappels/modal');
    $ctx->calls++;
    $nbHandlers = 0;
    foreach ([$pageDash, $r->body] as $corpsTeste) {
        preg_match_all('/\son[a-z-]+\s*=/i', $corpsTeste, $mm);
        $nbHandlers += count($mm[0]);
    }
    $s->check('MDL', 'CSP : aucun gestionnaire en ligne (on*=) dans le squelette ni le fragment', 'GET /macollection + GET /rappels/modal',
        '0 attribut on*', (string) $nbHandlers, $nbHandlers === 0 && $r->status === 200);

    // MDL-10c — Le fragment est de l'HTML de données : il ne doit embarquer aucun
    // <script> (le module externe raccorde le DOM injecte).
    $nbScripts = preg_match_all('/<script/i', $r->body);
    $s->check('MDL', 'Fragment : aucun <script> embarque (chargement par le module externe)', 'GET /rappels/modal',
        '0 balise script', (string) $nbScripts, $nbScripts === 0);

    // Fiche plante : l'anti-doublon « une espèce = une entrée par utilisateur »
    // (CollectionRepository::existsForUser) a déjà consommé les espèces des
    // blocs C/D pour userB. On crée donc UNE espèce dédiée à ce bloc, puis on
    // passe par la route réelle pour obtenir une fiche plante.
    $orchMdl = 'httptest-mdl-orch-' . $ctx->runId;
    $ctx->db->prepare(
        'INSERT INTO orchids (id, name, vernacular, genre, family, short_desc, origin, is_published, moderation_status, proposed_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, NULL)'
    )->execute([
        $orchMdl,
        'Httptest Modale ' . $ctx->runId,
        'Orchidée de la modale ' . $ctx->runId,
        'Phalaenopsis', 'Orchidaceae',
        'Fiche créée par le bloc MDL (suite HTTP).',
        'Serre de test ' . $ctx->runId,
        'approved',
    ]);
    $tok = $ctx->csrf($userB, '/macollection');
    $r = $userB->post('/collection/new', [
        'orchid_id'  => $orchMdl,
        'location'   => 'Test modale D',
        'csrf_token' => (string) $tok,
    ]);
    preg_match('#/collection/([0-9a-f\-]{36})#', (string) $r->location(), $mMdl);
    $colMdl = $mMdl[1] ?? null;
    if ($colMdl !== null) {
        $r = $userB->get('/collection/' . $colMdl);
        $ctx->calls++;
        $s->check('MDL', 'Fiche plante : entrée « Gérer mes rappels » + squelette présentés', 'GET /collection/{id}',
            '200 · trigger · modal · endpoint', $r->status . ' · '
                . (body_contains($r->body, 'data-rappels-modal-trigger') ? 'trigger' : 'absent') . ' · '
                . (body_contains($r->body, 'id="rappels-modal"') ? 'squelette' : 'absent') . ' · '
                . (body_contains($r->body, 'rappels/modal') ? 'endpoint' : 'absent'),
            $r->status === 200 && body_contains($r->body, 'data-rappels-modal-trigger')
                && body_contains($r->body, 'id="rappels-modal"') && body_contains($r->body, 'rappels/modal'));
        // RG1 côté fiche plante : un autre utilisateur ne doit voir ni le
        // déclencheur d'autrui ni le squelette lié à une plante qu'il ne possède
        // pas (la route elle-même refuse l'accès).
        $r = $userA->get('/collection/' . $colMdl);
        $ctx->calls++;
        $s->check('MDL', 'RG1 : la fiche d\'une plante tierce n\'est pas accessible (404, modale hors service)', 'GET /collection/{id} (userA)',
            '403 ou 404', (string) $r->status, in_array($r->status, [403, 404], true));
    } else {
        $s->blocked('MDL', 'Entrée de la fiche plante', 'GET /collection/{id}',
            'aucune collection créée par /collection/new durant ce run (statut ' . $r->status . ')');
    }

    // MDL-11 — Repli sans JavaScript : la page autonome /rappels garde tout
    // (refactor _liste.php n'a rien retiré)? Sections + cartes + formulaires.
    $r = $userB->get('/rappels');
    $ctx->calls++;
    $s->check('MDL', 'Repli sans JS : /rappels rend toujours sections, cartes et actions (régression C0)', 'GET /rappels',
        '200 · cartes · POST', $r->status . ' · '
            . (body_contains($r->body, 'rappel-card') ? 'cartes' : 'absent') . ' · '
            . (body_contains($r->body, '/fait') ? 'actions' : 'absent'),
        $r->status === 200 && body_contains($r->body, 'rappel-card') && body_contains($r->body, '/fait'));

    // MDL-12 — Comportement navigateur (ouverture/focus/Échap/réseau coupé) :
    // cette suite HTTP ne peut pas l'automatiser. Vérifié séparément dans un vrai
    // navigateur (php -S + base de test dédiée) — la ligne reste NOT RUN pour
    // l'automatisation, les mesures sont au rapport phase-d/04.
    $s->notRun('MDL', 'Ouverture, piègeage de focus, Échap, retour du focus, erreur réseau', 'navigateur',
        'non automatisable par la suite HTTP ; contrôle navigateur réel effectué séparément (voir docs phase-d/04)');

    // Nettoyage explicite des fixtures MDL (collection + espèce dédiées incluses).
    $ctx->db->prepare('DELETE FROM notifications WHERE id IN (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$mdlJour, $mdlRetard, $mdlAvenir, $mdlHist, $mdlFait, $mdlCsrf, $mdlTier]);
    if ($colMdl !== null) {
        $ctx->db->prepare('DELETE FROM collections WHERE id = ?')->execute([$colMdl]);
    }
    $ctx->db->prepare('DELETE FROM orchids WHERE id = ?')->execute([$orchMdl]);
}

/* ===========================================================================
   EXÉCUTION — isolation d'abord, preuves ensuite
   =========================================================================== */

/**
 * Orchestration : gardes → serveur → preuve d'isolation → fixtures → scénarios →
 * nettoyage → preuve d'intégrité → tableau.
 *
 * @return int 0 = aucun échec, 1 = au moins un FAIL, 2 = BLOCKED
 */
function main(): int
{
    $suite = new Suite();
    $root  = dirname(__DIR__, 2);
    $cfg   = http_db_config();
    // Identifiant d'exécution : contenu des fixtures déterministe, clés uniques par run
    // (aucune collision possible avec un run interrompu, ni entre runs parallèles).
    $runId = substr(bin2hex(random_bytes(4)), 0, 8);

    echo "MES ORCHIDEES — suite de tests HTTP sur serveur réel (Phase 08)\n";
    echo 'Exécution ' . $runId . ' · base de test déclarée : ' . $cfg['name'] . "\n";
    echo 'PHP ' . PHP_VERSION . ' (' . PHP_SAPI . ")\n\n";

    // ---------------------------------------------------------------------
    // 1) Gardes d'isolation : AVANT toute écriture potentielle
    // ---------------------------------------------------------------------
    try {
        $db = http_connect($cfg, $runId);
    } catch (HttpTestAbort $e) {
        echo "BLOCKED — l'isolation de la base de test ne peut pas être garantie :\n"
            . '  ' . $e->getMessage() . "\n\nAucune écriture n'a été tentée.\n";
        return 2;
    }
    $suite->row('PRE', 'Gardes d\'isolation (nom déclaré, nom résolu, schéma)', 'SELECT DATABASE()',
        'base dédiée avec schéma complet', '3 gardes franchies', 'PASS');

    // Résidus éventuels d'une exécution interrompue
    $leftover = http_test_rows_count($db);
    $suite->check('INT', 'Aucune donnée de test avant l\'exécution', 'SELECT marqueurs', '0 ligne',
        (string) $leftover, $leftover === 0);
    if ($leftover > 0) {
        http_cleanup($db);
        $suite->row('INT', 'Nettoyage des résidus d\'un run interrompu', 'DELETE marqueurs',
            'base de test vierge', 'résidus supprimés avant fixture', 'PASS');
    }

    // Instantané LECTURE SEULE de la base de développement (preuve d'absence d'effet de bord)
    $devBefore = http_dev_snapshot($db, $cfg['forbidden_name']);

    // ---------------------------------------------------------------------
    // 2) Serveur HTTP réel, avec la configuration de test injectée
    // ---------------------------------------------------------------------
    putenv('TEST_HTTP_DB_HOST=' . $cfg['host']);
    putenv('TEST_HTTP_DB_PORT=' . $cfg['port']);
    putenv('TEST_HTTP_DB_NAME=' . $cfg['name']);
    putenv('TEST_HTTP_DB_USER=' . $cfg['user']);
    putenv('TEST_HTTP_DB_PASS=' . $cfg['pass']);

    $server = new HttpTestServer();
    if (!$server->launch($root, $suite)) {
        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'C1', 'MDL'] as $g) {
            $suite->blocked($g, 'Ensemble du groupe', '—', 'aucun serveur HTTP de test n\'a pu être démarré');
        }
        echo $suite->table() . "\n" . $suite->summary() . "\n";
        return 2;
    }

    // ---------------------------------------------------------------------
    // 3) PREUVE : la base à laquelle le serveur se connecte est la base de test
    // ---------------------------------------------------------------------
    $probe    = $server->probe ?? [];
    $served   = (string) ($probe['database'] ?? '');
    $tables   = (int) ($probe['tables'] ?? 0);
    $isolated = $served !== '' && $served === $cfg['name'] && $served !== $cfg['forbidden_name'];

    $suite->check('INT', 'Isolation vérifiée par preuve serveur (et non par hypothèse)', 'GET /__http-test-probe',
        $cfg['name'], $served === '' ? 'sonde muette' : $served, $isolated);
    $suite->check('INT', 'Schéma servi par le serveur de test', 'GET /__http-test-probe', '≥ 7 tables',
        (string) $tables, $tables >= 7);

    if (!$isolated) {
        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'C1', 'MDL'] as $g) {
            $suite->blocked($g, 'Ensemble du groupe', '—', 'le serveur est branché sur une base inattendue');
        }
        $server->stop();
        echo "\n" . $suite->table() . "\n" . $suite->summary() . "\n";
        echo "\nBLOCKED — arrêt avant toute écriture.\n";
        return 2;
    }

    echo 'Serveur : ' . $server->baseUrl . ' · moteur : ' . (string) ($probe['engine'] ?? '?') . "\n";
    echo 'Base de développement (lecture seule) : '
        . ($devBefore === null ? 'inaccessible depuis ce compte'
            : sprintf('%d orchidées, %d utilisateurs', $devBefore['orchids'], $devBefore['users'])) . "\n\n";

    // ---------------------------------------------------------------------
    // 4) Fixtures, puis scénarios
    // ---------------------------------------------------------------------
    $ids = http_seed($db, $runId);
    $suite->row('PRE', 'Fixtures marquées insérées (4 comptes, 2 fiches)', 'INSERT',
        'données déterministes', 'insertions validées par relecture', 'PASS');

    $ctx = new Ctx($server->baseUrl, $db, $ids, $runId);

    try {
        $pre = prerequisites($suite, $ctx);
        scenarios_a($suite, $ctx);
        scenarios_b($suite, $ctx, $pre);
        scenarios_c($suite, $ctx, $pre);
        scenarios_d($suite, $ctx, $pre);
        scenarios_e($suite, $ctx, $pre);
        scenarios_f($suite, $ctx, $pre);
        scenarios_c1($suite, $ctx, $pre);
        scenarios_mdl($suite, $ctx, $pre);
    } catch (Throwable $e) {
        $suite->row('ERR', 'Exécution interrompue par une exception du harnais', '—',
            'aucune exception', get_class($e) . ' : ' . substr($e->getMessage(), 0, 120), 'FAIL');
    } finally {
        // -----------------------------------------------------------------
        // 5) Nettoyage et preuves d'intégrité
        // -----------------------------------------------------------------
        http_cleanup($db);

        $residue = http_test_rows_count($db);
        $suite->check('INT', 'Aucun résidu de donnée de test après l\'exécution', 'SELECT marqueurs', '0 ligne',
            (string) $residue, $residue === 0);

        $devAfter = http_dev_snapshot($db, $cfg['forbidden_name']);
        if ($devBefore === null || $devAfter === null) {
            $suite->notVerified('INT', 'Base de développement inchangée', 'SELECT COUNT(*) (dev)',
                'instantané de comparaison indisponible depuis ce compte');
        } else {
            $suite->check('INT', 'Base de développement strictement inchangée', 'SELECT COUNT(*) (dev)',
                'compteurs identiques avant/après', $devBefore === $devAfter ? 'identiques' : 'différents',
                $devBefore === $devAfter);
            $suite->check('INT', 'Aucune marqueur de test introduit en base de développement', 'SELECT marqueurs (dev)',
                '0 ligne', (string) $devAfter['test_markers'], $devAfter['test_markers'] === 0);
        }

        $requests = 0;
        foreach ($ctx->clients as $cl) {
            $requests += count($cl->log);
        }
        $suite->check('INT', 'Volume d\'appels HTTP réellement émis (parcours serveur, pas d\'appel PHP direct)',
            'compteur cumulé des clients', '> 40 requêtes', (string) $requests, $requests > 40);

        $server->stop();
    }

    // ---------------------------------------------------------------------
    // 6) Sortie : le tableau est la source de vérité, pas le code de sortie
    // ---------------------------------------------------------------------
    echo "=== Tableau des scénarios ===\n\n" . $suite->table();
    echo "\n=== Bilan ===\n" . $suite->summary() . "\n";

    if ($suite->failures !== []) {
        echo "\nÉchecs relevés :\n";
        foreach ($suite->failures as $f) {
            echo '  - ' . $f . "\n";
        }
    }

    return $suite->counts['FAIL'] > 0 ? 1 : 0;
}

exit(main());
