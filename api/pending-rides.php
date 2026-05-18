<?php
// api/pending-rides.php — Retourne les courses en attente pour le chauffeur connecté
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'driver') {
    echo json_encode([]); exit;
}
$pdo  = getPDO();
$stmt = $pdo->prepare('
    SELECT r.id, r.origin_address, r.dest_address, r.distance_km, r.duration_min, r.price_fcfa,
           u.first_name, u.last_name, u.rating
    FROM rides r JOIN users u ON u.id = r.passenger_id
    WHERE r.status = "pending"
    ORDER BY r.created_at DESC LIMIT 5
');
$stmt->execute();
echo json_encode($stmt->fetchAll());
