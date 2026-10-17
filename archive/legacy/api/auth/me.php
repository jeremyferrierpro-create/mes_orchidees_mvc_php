<?php
require_once dirname(__DIR__) . '/middleware/auth.php';
require_once dirname(__DIR__) . '/middleware/http.php';

sendApiHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJson(['error' => 'Methode non autorisee'], 405);
}

$user = getCurrentUser();

if ($user) {
    sendJson([
        'success' => true,
        'user' => [
            'id' => $user['sub'],
            'email' => $user['email'],
            'role' => $user['role'],
            'nom' => $user['nom'],
            'prenom' => $user['prenom']
        ]
    ]);
} else {
    sendJson(['success' => false, 'error' => 'Non authentifie'], 401);
}
