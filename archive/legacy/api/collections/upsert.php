<?php
require_once dirname(__DIR__) . '/middleware/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/middleware/http.php';

sendApiHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
    sendJson(['error' => 'Methode non autorisee'], 405);
}

requireSameOriginRequest();
$user = requireAuth();
$input = readJsonBody();
$data = $input['data'] ?? $input;

if (!is_array($data) || count($data) > 100) {
    sendJson(['error' => 'Format invalide'], 400);
}

try {
    $pdo = getDb();
    
    // L'UPSERT est atomique et très performant.
    $stmt = $pdo->prepare("
        INSERT INTO public.collections 
            (id, legacy_id, user_id, orchid_id, location, notes, temp, hygro, light, ventilation, added_at)
        VALUES 
            (gen_random_uuid(), :legacy_id, :user_id, :orchid_id, :location, :notes, :temp, :hygro, :light, :ventilation, COALESCE(:added_at, NOW()))
        ON CONFLICT (user_id, legacy_id)
        DO UPDATE SET
            location    = EXCLUDED.location,
            notes       = EXCLUDED.notes,
            temp        = EXCLUDED.temp,
            hygro       = EXCLUDED.hygro,
            light       = EXCLUDED.light,
            ventilation = EXCLUDED.ventilation,
            updated_at  = NOW()
    ");

    $pdo->beginTransaction();
    
    foreach ($data as $item) {
        if (!is_array($item) || !isset($item['collectionId'], $item['orchidId'])) {
            throw new InvalidArgumentException('Entree de collection invalide');
        }

        $collectionId = trim((string) $item['collectionId']);
        $orchidId = trim((string) $item['orchidId']);
        if (!preg_match('/^[A-Za-z0-9_-]{1,100}$/', $collectionId) || !preg_match('/^[A-Za-z0-9_-]{1,200}$/', $orchidId)) {
            throw new InvalidArgumentException('Identifiant invalide');
        }
        
        $stmt->execute([
            ':legacy_id' => $collectionId,
            ':user_id' => $user['sub'],
            ':orchid_id' => $item['orchidId'],
            ':location' => isset($item['location']) ? mb_substr(trim((string) $item['location']), 0, 200) : null,
            ':notes' => isset($item['notes']) ? mb_substr(trim((string) $item['notes']), 0, 5000) : null,
            ':temp' => isset($item['temp']) ? mb_substr(trim((string) $item['temp']), 0, 50) : null,
            ':hygro' => isset($item['hygro']) ? mb_substr(trim((string) $item['hygro']), 0, 50) : null,
            ':light' => isset($item['light']) ? mb_substr(trim((string) $item['light']), 0, 100) : null,
            ':ventilation' => isset($item['ventilation']) ? mb_substr(trim((string) $item['ventilation']), 0, 100) : null,
            ':added_at' => null
        ]);
    }
    
    $pdo->commit();
    
    sendJson(['success' => true]);
} catch (InvalidArgumentException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    sendJson(['error' => 'Donnees de collection invalides'], 400);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Echec de sauvegarde de collection: ' . $e->getMessage());
    sendJson(['error' => 'Erreur serveur'], 500);
}
