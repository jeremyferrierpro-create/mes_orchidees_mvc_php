<?php
/**
 * Endpoint API pour la connexion utilisateur
 * 
 * Ce fichier gère l'authentification des utilisateurs via email/mot de passe,
 * génère un JWT et le stocke dans un cookie HTTPOnly sécurisé.
 * 
 * @package MesOrchidées\API\Auth
 * @author Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 2.0.0
 * @since 2026-09-01
 */

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/middleware/http.php';

/**
 * Envoie les headers HTTP standard pour l'API
 */
sendApiHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'Methode non autorisee'], 405);
}

requireSameOriginRequest();
$input = readJsonBody();
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !is_string($password) || $password === '') {
    sendJson(['error' => 'Identifiants invalides'], 400);
}

try {
    $pdo = getDb();
    $stmt = $pdo->prepare('SELECT id, nom, prenom, email, password_hash, role FROM public.users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Verifie le hash argon2id
    if (!$user || !password_verify($password, $user['password_hash'])) {
        sendJson(['error' => 'Identifiants incorrects'], 401);
    }

    // Generation manuelle d'un JWT (Base64UrlEncode)
    function base64UrlEncode($text) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));
    }

    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'sub' => $user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'nom' => $user['nom'],
        'prenom' => $user['prenom'],
        'iat' => time(),
        'exp' => time() + 86400 // 24h
    ]);

    $base64UrlHeader = base64UrlEncode($header);
    $base64UrlPayload = base64UrlEncode($payload);
    
    $secret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET');
    if (!is_string($secret) || $secret === '') {
        throw new RuntimeException('JWT_SECRET manquant');
    }
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
    $base64UrlSignature = base64UrlEncode($signature);

    $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

    // Envoi du cookie HTTPOnly
    setcookie('session', $jwt, [
        'expires' => time() + 86400,
        'path' => '/',
        'domain' => $_ENV['COOKIE_DOMAIN'] ?? getenv('COOKIE_DOMAIN') ?? 'localhost',
        'secure' => filter_var($_ENV['COOKIE_SECURE'] ?? getenv('COOKIE_SECURE'), FILTER_VALIDATE_BOOLEAN),
        'httponly' => true,
        'samesite' => $_ENV['COOKIE_SAMESITE'] ?? getenv('COOKIE_SAMESITE') ?? 'Strict'
    ]);

    unset($user['password_hash']); // Ne jamais renvoyer le hash

    sendJson([
        'success' => true,
        'message' => 'Connexion reussie',
        'user' => $user
    ]);

} catch (Throwable $e) {
    error_log('Echec de connexion: ' . $e->getMessage());
    sendJson(['error' => 'Erreur serveur'], 500);
}
