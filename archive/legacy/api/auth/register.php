<?php
/**
 * Endpoint API pour l'inscription utilisateur
 * 
 * Ce fichier gère l'inscription de nouveaux utilisateurs avec validation
 * des données, hachage du mot de passe avec Argon2ID et insertion en base.
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
$nom = trim($input['nom'] ?? 'Utilisateur');
$prenom = trim($input['prenom'] ?? 'Nouveau');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !is_string($password) || strlen($password) < 8 || strlen($password) > 256 || $nom === '' || $prenom === '' || mb_strlen($nom) > 100 || mb_strlen($prenom) > 100) {
    sendJson(['error' => 'Donnees invalides'], 400);
}

try {
    $pdo = getDb();
    
    // Verifie si email existe
    $check = $pdo->prepare('SELECT id FROM public.users WHERE email = ?');
    $check->execute([$email]);
    if ($check->fetch()) {
        sendJson(['error' => 'Email deja utilise'], 409);
    }

    // Cree le hash
    $hash = password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]);

    $stmt = $pdo->prepare('INSERT INTO public.users (nom, prenom, email, password_hash, role) VALUES (?, ?, ?, ?, ?) RETURNING id, nom, prenom, email, role');
    $stmt->execute([$nom, $prenom, $email, $hash, 'user']);
    $user = $stmt->fetch();

    // On pourrait forcer la connexion en appelant la logique de login.php ici, 
    // ou laisser le client appeler /login apres success.

    sendJson([
        'success' => true,
        'message' => 'Inscription reussie',
        'user' => $user
    ]);

} catch (Throwable $e) {
    error_log('Echec d inscription: ' . $e->getMessage());
    sendJson(['error' => 'Erreur serveur lors de l\'inscription'], 500);
}
