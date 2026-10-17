<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/middleware/http.php';

sendApiHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJson(['error' => 'Methode non autorisee'], 405);
}

$query = trim($_GET['q'] ?? '');
if (strlen($query) < 2) {
    sendJson([]);
}

try {
    $pdo = getDb();
    $stmt = $pdo->prepare("
        SELECT id, name, vernacular, botanical_order as \"order\", species, genre, family, subfamily, tribu, subtribu, behavior, discovered, origin, img, short_desc as \"shortDesc\", long_desc as \"longDesc\",
               ts_rank(search_vector, to_tsquery('french', :q)) AS relevance
        FROM public.orchids
        WHERE search_vector @@ to_tsquery('french', :q)
           OR name ILIKE :like
           OR vernacular ILIKE :like
        ORDER BY relevance DESC, name ASC
        LIMIT 20
    ");
    
    $tsQuery = implode(' | ', array_map(fn($w) => $w . ':*', explode(' ', $query)));
    $stmt->execute([':q' => $tsQuery, ':like' => '%' . $query . '%']);
    
    sendJson($stmt->fetchAll());
} catch (Throwable $e) {
    error_log('Echec de recherche d orchidees: ' . $e->getMessage());
    sendJson(['error' => 'Erreur serveur'], 500);
}
