<?php
/**
 * Routeur de DÉVELOPPEMENT pour le serveur intégré PHP (`php -S`).
 *
 * En production (Apache/Nginx/Render), la réécriture est assurée par
 * public/.htaccess (ou l'équivalent serveur) et ce fichier N'EST PAS utilisé.
 * Ici, il reproduit ce comportement pour le serveur local :
 *   - les fichiers statiques réels de /public sont servis tels quels ;
 *   - toute autre requête est transmise au front-controller public/index.php.
 *
 * Lancement :  php -S localhost:8000 -t public server.php
 *
 * @package App
 * @author  Jérémy Ferrier
 */

$uri     = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$docRoot = __DIR__ . '/public';
$file    = $docRoot . $uri;

// Fichier statique réel (CSS, JS, images, manifest.json, sw.js) → servi directement.
if ($uri !== '/' && is_file($file)) {
    return false;
}

// Sinon, tout passe par le point d'entrée unique MVC.
require $docRoot . '/index.php';
