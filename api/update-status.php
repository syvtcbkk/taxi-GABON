<?php
// api/update-status.php — Chauffeur change son statut disponible/indisponible
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'driver') {
    echo json_encode(['success' => false]); exit;
}
$body      = json_decode(file_get_contents('php://input'), true);
$available = isset($body['available']) ? (int)(bool)$body['available'] : 0;

$pdo = getPDO();
$pdo->prepare('UPDATE driver_profiles SET is_available = ? WHERE user_id = ?')
    ->execute([$available, $_SESSION['user_id']]);

echo json_encode(['success' => true]);
