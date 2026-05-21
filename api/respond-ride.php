<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

$sessionRole = $_SESSION['user_role'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $sessionRole !== 'driver') {
    $error = 'Non autorisé';
    if (isset($_SESSION['user_id']) && $sessionRole !== 'driver') {
        $error = 'Vous devez être connecté en tant que chauffeur.';
    }
    echo json_encode(['success' => false, 'error' => $error, 'session_role' => $sessionRole]); exit;
}

$body   = json_decode(file_get_contents('php://input'), true);
$rideId = (int)($body['ride_id'] ?? 0);
$action = $body['action'] ?? '';

if (!$rideId || !in_array($action, ['accept','cancel','in_progress','completed'])) {
    echo json_encode(['success' => false, 'error' => 'Paramètres invalides']); exit;
}

$pdo = getPDO();

switch ($action) {
    case 'accept':
        $stmt = $pdo->prepare('SELECT id FROM rides WHERE id = ? AND status = "pending"');
        $stmt->execute([$rideId]);
        if (!$stmt->fetch()) { echo json_encode(['success' => false, 'error' => 'Course déjà prise']); exit; }
        $pdo->prepare('UPDATE rides SET driver_id = ?, status = "accepted" WHERE id = ? AND status = "pending"')
            ->execute([$_SESSION['user_id'], $rideId]);
        break;

    case 'in_progress':
        $pdo->prepare('UPDATE rides SET status = "in_progress" WHERE id = ? AND driver_id = ? AND status = "accepted"')
            ->execute([$rideId, $_SESSION['user_id']]);
        break;

    case 'completed':
        $pdo->prepare('UPDATE rides SET status = "completed", completed_at = NOW() WHERE id = ? AND driver_id = ? AND status IN ("accepted","in_progress")')
            ->execute([$rideId, $_SESSION['user_id']]);
        break;

    case 'cancel':
        $pdo->prepare('UPDATE rides SET status = "cancelled" WHERE id = ? AND driver_id = ? AND status IN ("accepted","in_progress")')
            ->execute([$rideId, $_SESSION['user_id']]);
        break;
}

echo json_encode(['success' => true]);