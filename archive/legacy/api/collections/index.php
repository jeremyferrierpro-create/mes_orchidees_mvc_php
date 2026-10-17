<?php
/**
 * Endpoint API pour la récupération de la collection utilisateur
 * 
 * Ce fichier retourne la collection d'orchidées de l'utilisateur connecté
 * avec les informations de soins et de culture.
 * 
 * @package MesOrchidées\API\Collections
 * @author Jérémy Ferrier <jeremy.ferrierpro@gmail.com>
 * @version 2.0.0
 * @since 2026-09-01
 */

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/middleware/http.php';
require_once dirname(__DIR__) . '/middleware/auth.php';

/**
 * Envoie les headers HTTP standard pour l'API
 */
sendApiHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJson(['error' => 'Methode non autorisee'], 405);
}

$user = requireAuth();

try {
    $pdo = getDb();
    
    // Jointure avec orchids pour reconstruire le json attendu par le JS
    $stmt = $pdo->prepare('
        SELECT c.legacy_id as "collectionId", c.orchid_id as "orchidId", c.location, c.notes, c.temp, c.hygro, c.light, c.ventilation, c.added_at as "addedAt",
               o.name, o.behavior, o.img
        FROM public.collections c
        JOIN public.orchids o ON o.id = c.orchid_id
        WHERE c.user_id = ?
        ORDER BY c.added_at DESC
    ');
    $stmt->execute([$user['sub']]);
    $collections = $stmt->fetchAll();
    
    // Le JS s'attend a un tableau data enveloppe
    sendJson([
        'userId' => $user['sub'],
        'data' => $collections
    ]);

} catch (Throwable $e) {
    error_log('Echec de lecture de collection: ' . $e->getMessage());
    sendJson(['error' => 'Erreur serveur'], 500);
}
