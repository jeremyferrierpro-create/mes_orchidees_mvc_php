<?php
/**
 * Endpoint API pour la récupération des orchidées publiées
 * 
 * Ce fichier retourne la liste des orchidées publiées dans l'encyclopédie
 * avec leurs caractéristiques botaniques et informations de culture.
 * 
 * @package MesOrchidées\API\Orchids
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJson(['error' => 'Methode non autorisee'], 405);
}

try {
    $pdo = getDb();
    $stmt = $pdo->query('SELECT id, name, vernacular, botanical_order as "order", species, genre, family, subfamily, tribu, subtribu, behavior, discovered, origin, img, short_desc as "shortDesc", long_desc as "longDesc" FROM public.orchids WHERE is_published = TRUE ORDER BY name ASC');
    $orchids = $stmt->fetchAll();

    sendJson($orchids);
} catch (Throwable $e) {
    error_log('Echec de lecture des orchidees: ' . $e->getMessage());
    sendJson(['error' => 'Erreur serveur'], 500);
}

