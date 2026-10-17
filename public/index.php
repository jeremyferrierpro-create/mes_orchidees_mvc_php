<?php
/**
 * FRONT-CONTROLLER — point d'entrée unique de l'application MES ORCHIDEES.
 *
 * Rôle : tout passe ici. On charge l'autoloader, on démarre une session sécurisée,
 * on construit le Routeur Mithridatem (avec le GrantChecker basé sur la session),
 * on enregistre les routes (config/routes.php), on dispatche la requête courante,
 * puis on affiche le résultat ou une page d'erreur homogène.
 *
 * Docroot = /public. Les fichiers de l'app (src/, config/, templates/, data/) sont
 * HORS docroot et donc inaccessibles directement par le web (décision D4).
 *
 * @package App\Public
 * @author  Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 3.0.0
 * @since   2026-10-17
 */

declare(strict_types=1);

$root = dirname(__DIR__); // racine du projet (parent de /public)

// -----------------------------------------------------------------------------
// 1) Autoloader : Composer si présent, sinon plan B PSR-4 minimal (dev local).
// -----------------------------------------------------------------------------
if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
} else {
    // Repli sans Composer : mappe App\ -> src/ et Mithridatem\Routing\ -> lib/.
    spl_autoload_register(static function (string $class) use ($root): void {
        $map = [
            'App\\'                 => $root . '/src/',
            'Mithridatem\\Routing\\' => $root . '/lib/Mithridatem/Router/src/',
        ];
        foreach ($map as $prefix => $dir) {
            if (str_starts_with($class, $prefix)) {
                $rel   = str_replace('\\', '/', substr($class, strlen($prefix)));
                $file  = $dir . $rel . '.php';
                if (is_file($file)) {
                    require $file;
                }
                return;
            }
        }
    });
}

// -----------------------------------------------------------------------------
// 2) Configuration PHP selon l'environnement.
// -----------------------------------------------------------------------------
require $root . '/config/env.php'; // définit $_ENV via .env
require $root . '/config/db.php'; // fabrique getDb() (PDO MySQL) consommé par les repositories
require $root . '/config/helpers.php'; // url(), asset(), e(), current_user()…

$debug = (($_ENV['APP_DEBUG'] ?? 'false') === 'true');
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

// -----------------------------------------------------------------------------
// 3) Session PHP native, cookie durci.
// -----------------------------------------------------------------------------
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure  = (($_ENV['COOKIE_SECURE']  ?? 'false') === 'true');
    $sameSite = $_ENV['COOKIE_SAMESITE'] ?? 'Strict';
    $domain   = $_ENV['COOKIE_DOMAIN']   ?? null;

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => $domain ?? '',
        'secure'   => $secure,   // true en prod (HTTPS)
        'httponly' => true,      // inaccessible en JS → limite le vol de cookie
        'samesite' => $sameSite, // anti-CSRF navigationnel
    ]);
    session_start();
}

// Jeton CSRF de session : créé une fois pour toutes, disponible pour tous les formulaires.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// -----------------------------------------------------------------------------
// 4) Construction du Routeur + GrantChecker basé sur la session.
// -----------------------------------------------------------------------------
use Mithridatem\Routing\Router;
use Mithridatem\Routing\Exception\RouteNotFoundException;
use Mithridatem\Routing\Exception\MethodNotAllowedException;
use Mithridatem\Routing\Exception\UnauthorizedException;
use Mithridatem\Routing\Exception\RouterException;
use App\Security\SessionGrantChecker;

$router = new Router();
$router->setGrantChecker(new SessionGrantChecker());

// Sous-répertoire éventuel (ex : /mes-orchidees sur Render) — sinon racine.
$basePath = $_ENV['APP_BASE_PATH'] ?? null;
if (is_string($basePath) && $basePath !== '') {
    $router->setBasePath($basePath);
}

require $root . '/config/routes.php'; // définit register_routes()
register_routes($router);

// -----------------------------------------------------------------------------
// 5) Aides d'affichage (page d'erreur homogène rendue dans le layout).
// -----------------------------------------------------------------------------
/**
 * Affiche une page d'erreur via le gabarit global.
 *
 * @param int    $status  Code HTTP.
 * @param string $message Message court (aucune information technique exposée).
 * @return void
 */
function render_error(int $status, string $message): void
{
    $file = dirname(__DIR__) . '/templates/pages/error.php';
    ob_start();
    include $file; // produit le fragment dans $content
    $content = ob_get_clean();

    $title   = 'Erreur ' . $status;
    $flashes = [];
    include dirname(__DIR__) . '/templates/layout.php';
}

// -----------------------------------------------------------------------------
// 6) Dispatch + gestion des exceptions HTTP.
// -----------------------------------------------------------------------------
try {
    $response = $router->dispatch();

    // Les contrôleurs renvoient soit du HTML/JSON (string), soit rien (redirection
    // déjà terminée par exit). On n'affiche que si un contenu est produit.
    if (is_string($response)) {
        echo $response;
    }
} catch (RouteNotFoundException $e) {
    http_response_code(404);
    render_error(404, 'La page demandée est introuvable.');
} catch (MethodNotAllowedException $e) {
    http_response_code(405);
    render_error(405, 'Méthode HTTP non autorisée pour cette route.');
} catch (UnauthorizedException $e) {
    http_response_code(403);
    render_error(403, 'Accès refusé : vous n\'avez pas les droits requis.');
} catch (RouterException $e) {
    // Classe ou méthode de contrôleur absente = contrat déclaré, non encore implémenté.
    if (stripos($e->getMessage(), 'not found') !== false) {
        http_response_code(501);
        render_error(501, 'Fonctionnalité prévue pour la Phase 03 (non implémentée).');
    } else {
        http_response_code(500);
        render_error(500, 'Erreur interne du routeur.');
    }
} catch (\Throwable $e) {
    http_response_code(500);
    if ($debug) {
        throw $e; // en développement : on laisse PHP afficher la trace
    }
    render_error(500, 'Erreur interne, merci de réessayer plus tard.');
}
