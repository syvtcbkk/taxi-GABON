<?php
// api/driver-position.php — Passager récupère la position du chauffeur
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode([]); exit; }
$driverId = (int)($_GET['driver_id'] ?? 0);
if (!$driverId) { echo json_encode([]); exit; }

$pdo  = getPDO();
$stmt = $pdo->prepare('SELECT latitude AS lat, longitude AS lng FROM live_positions WHERE user_id = ?');
$stmt->execute([$driverId]);
$pos  = $stmt->fetch() ?: [];
echo json_encode($pos);
