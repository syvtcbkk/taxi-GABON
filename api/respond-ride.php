<?php
// api/respond-ride.php — Chauffeur accepte ou refuse une course
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'driver') {
    echo json_encode(['success' => false]); exit;
}
$body   = json_decode(file_get_contents('php://input'), true);
$rideId = (int)($body['ride_id'] ?? 0);
$action = $body['action'] ?? '';

if (!$rideId || !in_array($action, ['accept','cancel'])) {
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides']); exit;
}

$pdo = getPDO();

if ($action === 'accept') {
    // Vérifier que la course est encore en attente
    $stmt = $pdo->prepare('SELECT id FROM rides WHERE id = ? AND status = "pending"');
    $stmt->execute([$rideId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Course déjà prise']); exit;
    }
    $pdo->prepare('UPDATE rides SET driver_id = ?, status = "accepted" WHERE id = ? AND status = "pending"')
        ->execute([$_SESSION['user_id'], $rideId]);
} else {
    // Le chauffeur refuse — la course reste "pending" pour d'autres chauffeurs
    // On peut logguer le refus si besoin
}

echo json_encode(['success' => true]);
