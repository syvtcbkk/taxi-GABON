<?php
// api/book-ride.php — Passager crée une nouvelle course
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validators.php';

header('Content-Type: application/json');

// 1. Authentification
$userId = requireRole('passenger');

// 2. Rate limiting
if (!checkRateLimit('book_ride', 5, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Trop de demandes. Réessayez dans 1 minute.']);
    exit;
}

// 3. Parse et valide les données
$body = parseJsonRequest();
if (!$body) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Données JSON invalides']);
    exit;
}

// 4. Valide les champs requis
$required = ['origin_address','origin_lat','origin_lng','dest_address','dest_lat','dest_lng','distance_km','duration_min','price_fcfa'];
if ($error = validateRequired($body, $required)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

// 5. Valide les valeurs
$originLat = (float)($body['origin_lat'] ?? 0);
$originLng = (float)($body['origin_lng'] ?? 0);
$destLat = (float)($body['dest_lat'] ?? 0);
$destLng = (float)($body['dest_lng'] ?? 0);

if (!validateLocation($originLat, $originLng, $body['origin_address'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Localisation de départ invalide']);
    exit;
}

if (!validateLocation($destLat, $destLng, $body['dest_address'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Localisation de destination invalide']);
    exit;
}

if (!validateDistance((float)($body['distance_km'] ?? 0))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Distance invalide']);
    exit;
}

if (!validateDuration((int)($body['duration_min'] ?? 0))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Durée invalide']);
    exit;
}

if (!validateAmount((int)($body['price_fcfa'] ?? 0))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Montant invalide']);
    exit;
}

// 6. Traitement DB
$pdo = getPDO();

try {
    $pdo->beginTransaction();

    // Annuler toute course en attente existante du passager
    $pdo->prepare('UPDATE rides SET status = "cancelled" WHERE passenger_id = ? AND status = "pending"')
        ->execute([$userId]);

    // Insérer la nouvelle course
    $pdo->prepare('
        INSERT INTO rides
            (passenger_id, origin_address, origin_lat, origin_lng, dest_address, dest_lat, dest_lng,
             distance_km, duration_min, price_fcfa, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")
    ')->execute([
        $userId,
        sanitizeString($body['origin_address']),
        $originLat,
        $originLng,
        sanitizeString($body['dest_address']),
        $destLat,
        $destLng,
        (float)$body['distance_km'],
        (int)$body['duration_min'],
        (int)$body['price_fcfa']
    ]);

    $rideId = $pdo->lastInsertId();
    $pdo->commit();

    // Audit
    auditLog('book_ride', "ride_id=$rideId, price=" . $body['price_fcfa']);

    http_response_code(201);
    echo json_encode(['success' => true, 'ride_id' => $rideId]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[book-ride] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
