<?php
// api/update-position.php — Chauffeur met à jour sa position GPS
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'driver') {
    echo json_encode(['success' => false]); exit;
}
$body = json_decode(file_get_contents('php://input'), true);
$lat  = (float)($body['lat'] ?? 0);
$lng  = (float)($body['lng'] ?? 0);
if (!$lat || !$lng) { echo json_encode(['success' => false]); exit; }

$pdo = getPDO();
$pdo->prepare('
    INSERT INTO live_positions (user_id, latitude, longitude)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude)
')->execute([$_SESSION['user_id'], $lat, $lng]);

// Mettre à jour aussi driver_profiles
$pdo->prepare('UPDATE driver_profiles SET latitude=?, longitude=? WHERE user_id=?')
    ->execute([$lat, $lng, $_SESSION['user_id']]);

echo json_encode(['success' => true]);
