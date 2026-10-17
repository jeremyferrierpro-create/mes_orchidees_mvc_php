<?php
require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/middleware/http.php';

sendApiHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'Methode non autorisee'], 405);
}

requireSameOriginRequest();

// Supprime le cookie
setcookie('session', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'domain' => $_ENV['COOKIE_DOMAIN'] ?? getenv('COOKIE_DOMAIN') ?? 'localhost',
    'secure' => filter_var($_ENV['COOKIE_SECURE'] ?? getenv('COOKIE_SECURE'), FILTER_VALIDATE_BOOLEAN),
    'httponly' => true,
    'samesite' => $_ENV['COOKIE_SAMESITE'] ?? getenv('COOKIE_SAMESITE') ?? 'Strict'
]);

sendJson(['success' => true, 'message' => 'Deconnexion reussie']);
