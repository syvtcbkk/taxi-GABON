<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validators.php';

header('Content-Type: application/json');

// 1. Authentification driver
$userId = requireRole('driver');

// 2. Rate limiting
if (!checkRateLimit('respond_ride', 20, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Trop de demandes']);
    exit;
}

// 3. Parse requête
$body = parseJsonRequest();
if (!$body) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Données JSON invalides']);
    exit;
}

// 4. Valide les paramètres
$rideId = (int)($body['ride_id'] ?? 0);
$action = $body['action'] ?? '';

if (!$rideId || !in_array($action, ['accept','cancel','in_progress','completed'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
    exit;
}

$pdo = getPDO();

try {
    switch ($action) {
        case 'accept':
            $stmt = $pdo->prepare('SELECT id FROM rides WHERE id = ? AND status = "pending" LIMIT 1');
            $stmt->execute([$rideId]);
            if (!$stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'Course indisponible']);
                exit;
            }
            $pdo->prepare('UPDATE rides SET driver_id = ?, status = "accepted" WHERE id = ? AND status = "pending"')
                ->execute([$userId, $rideId]);
            auditLog('ride_accept', "ride_id=$rideId");
            break;

        case 'in_progress':
            $pdo->prepare('UPDATE rides SET status = "in_progress" WHERE id = ? AND driver_id = ? AND status = "accepted"')
                ->execute([$rideId, $userId]);
            auditLog('ride_in_progress', "ride_id=$rideId");
            break;

        case 'completed':
            $pdo->prepare('UPDATE rides SET status = "completed", completed_at = NOW() WHERE id = ? AND driver_id = ? AND status IN ("accepted","in_progress")')
                ->execute([$rideId, $userId]);
            auditLog('ride_completed', "ride_id=$rideId");
            break;

        case 'cancel':
            $pdo->prepare('UPDATE rides SET status = "cancelled" WHERE id = ? AND driver_id = ? AND status IN ("accepted","in_progress")')
                ->execute([$rideId, $userId]);
            auditLog('ride_cancel', "ride_id=$rideId");
            break;
    }

    http_response_code(200);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log('[respond-ride] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}