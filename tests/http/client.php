<?php
/**
 * Client HTTP de la suite de tests (Phase 08, Option B).
 *
 * Ce client exerce RÉELLEMENT l'application via le protocole HTTP : il négocie
 * de vraies requêtes vers le serveur `php -S` démarré par `tests/http/run.php`,
 * conserve les cookies de session dans un bocal (jar) dédié à chaque « navigateur
 * simulé », et expose la réponse brute (statut, en-têtes, corps).
 *
 * Choix technique : l'extension PHP `curl` (déjà chargée dans le CLI local —
 * vérifié en Phase 08). Aucune dépendance Composer n'est ajoutée, conformément à
 * la règle du harnais sans pile externe (Phase 05). `file_get_contents` aurait
 * exigé un gestionnaire manuel des cookies et des statuts >= 400.
 *
 * Important : `followRedirects` est désactivé par défaut. Les contrôleurs de
 * l'app répondent 302 + `Location:` ; c'est CE contrat que l'on teste, et non la
 * page suivante (qui est vérifiée par une requête explicite).
 *
 * @package App\Tests\Http
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 1.0.0
 * @since   2026-10-17
 */

declare(strict_types=1);

/**
 * Réponse HTTP brute, telle que reçue du serveur.
 */
final class HttpResponse
{
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
        public readonly string $method,
        public readonly string $path
    ) {
    }

    /**
     * valeur d'un en-tête (insensible à la casse), ou null.
     */
    public function header(string $name): ?string
    {
        $lower = strtolower($name);
        foreach ($this->headers as $header) {
            $pos = strpos($header, ':');
            if ($pos === false) {
                continue; // ligne de statut HTTP
            }
            if (strtolower(trim(substr($header, 0, $pos))) === $lower) {
                return trim(substr($header, $pos + 1));
            }
        }
        return null;
    }

    /**
     * Le serveur a-t-il envoyé un cookie de session ?
     */
    public function sentSessionCookie(): bool
    {
        foreach ($this->headers as $header) {
            if (stripos($header, 'set-cookie:') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Décode le corps en JSON (tableau), ou null si non décodable.
     */
    public function json(): ?array
    {
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Extrait le jeton CSRF réellement rendu dans le HTML (`name="csrf_token" value="…"`).
     *
     * C'est le SEUL moyen fidèle au navigateur : le jeton vient de la page, pas du code.
     */
    public function csrfToken(): ?string
    {
        if (preg_match('/name=["\']csrf_token["\']\s+value=["\']([0-9a-f]{64})["\']/', $this->body, $m) === 1) {
            return $m[1];
        }
        return null;
    }

    /**
     * Cible de redirection (`Location:`), ou null.
     */
    public function location(): ?string
    {
        return $this->header('location');
    }
}

/**
 * Client HTTP avec jar de cookies persistant (une instance = un « navigateur »).
 */
final class HttpClient
{
    private string $cookieFile;
    /** Historique des réponses pour le bilan de non-régression 5xx. */
    public array $log = [];

    public function __construct(private readonly string $baseUrl)
    {
        // Fichier de cookies dédié : isole les sessions entre clients.
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'meso_http_');
    }

    /**
     * Réinitialise complètement l'identité du client (nouveau jar = nouvelle session).
     */
    public function resetSession(): void
    {
        @unlink($this->cookieFile);
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'meso_http_');
    }

    /**
     * Exécute une vraie requête HTTP.
     *
     * @param string $method GET | POST | PUT | DELETE …
     * @param string $path   Chemin relatif à la base (ex : `/login`, `/orchids?q=x`)
     * @param array  $fields Champs de formulaire (application/x-www-form-urlencoded)
     * @param array  $opts   ['cookies' => bool, 'headers' => string[]]
     */
    public function request(string $method, string $path, array $fields = [], array $opts = []): HttpResponse
    {
        $url = rtrim($this->baseUrl, '/') . $path;
        $ch  = curl_init();

        $useCookies = ($opts['cookies'] ?? true) === true;

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,   // on veut observer le 302, pas le suivre
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
        ]);

        if ($useCookies) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile); // lu s'il existe
            curl_setopt($ch, CURLOPT_COOKIEJAR,  $this->cookieFile); // écrit à la fin
        }

        if ($fields !== []) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        }

        if (isset($opts['headers']) && is_array($opts['headers'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $opts['headers']);
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("Échec de la requête HTTP {$method} {$path} : {$error}");
        }

        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headerBlock = substr((string) $raw, 0, $headSize);
        $body        = substr((string) $raw, $headSize);

        // Sépare les en-têtes de la ligne de statut (plusieurs blocs si redirections suivies).
        $headers = array_values(array_filter(array_map('trim', explode("\r\n", $headerBlock)), static function (string $line): bool {
            return $line !== '';
        }));

        $response = new HttpResponse($status, $headers, (string) $body, strtoupper($method), $path);
        $this->log[] = $response;
        return $response;
    }

    public function get(string $path, array $opts = []): HttpResponse
    {
        return $this->request('GET', $path, [], $opts);
    }

    public function post(string $path, array $fields = [], array $opts = []): HttpResponse
    {
        return $this->request('POST', $path, $fields, $opts);
    }

    /**
     * Récupère le jeton CSRF courant en rechargeant une page publique, puis
     * en relisant le formulaire réel rendu par l'application.
     *
     * @param string $path Page contenant un formulaire à jour (ex : `/login`)
     */
    public function csrfFromPage(string $path): ?string
    {
        return $this->get($path)->csrfToken();
    }

    /**
     * Nombre de réponses 5xx observées (le contrat « pas de 500 » est global).
     */
    public function countServerErrors(): int
    {
        $n = 0;
        foreach ($this->log as $response) {
            if ($response->status >= 500) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * Chemin du fichier de cookies (pour le journal : jamais son contenu).
     */
    public function cookieFilePath(): string
    {
        return $this->cookieFile;
    }

    public function __destruct()
    {
        @unlink($this->cookieFile); // aucun cookie consigné dans les rapports
    }
}
