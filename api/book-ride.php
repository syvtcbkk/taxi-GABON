<?php
// api/book-ride.php — Passager crée une nouvelle course
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

$sessionRole = $_SESSION['user_role'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $sessionRole !== 'passenger') {
    $error = 'Non autorisé';
    if (isset($_SESSION['user_id']) && $sessionRole !== 'passenger') {
        $error = 'Vous devez être connecté en tant que passager.';
    }
    echo json_encode(['success' => false, 'error' => $error, 'session_role' => $sessionRole]); exit;
}
$body = json_decode(file_get_contents('php://input'), true);

$required = ['origin_address','origin_lat','origin_lng','dest_address','dest_lat','dest_lng','distance_km','duration_min','price_fcfa'];
foreach ($required as $k) {
    if (empty($body[$k]) && $body[$k] !== 0) {
        echo json_encode(['success' => false, 'error' => "Champ manquant : $k"]); exit;
    }
}

$pdo = getPDO();

// Annuler toute course en attente existante
$pdo->prepare('UPDATE rides SET status = "cancelled" WHERE passenger_id = ? AND status = "pending"')
    ->execute([$_SESSION['user_id']]);

$pdo->prepare('
    INSERT INTO rides
        (passenger_id, origin_address, origin_lat, origin_lng, dest_address, dest_lat, dest_lng,
         distance_km, duration_min, price_fcfa, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")
')->execute([
    $_SESSION['user_id'],
    $body['origin_address'], $body['origin_lat'], $body['origin_lng'],
    $body['dest_address'],   $body['dest_lat'],   $body['dest_lng'],
    $body['distance_km'], $body['duration_min'], $body['price_fcfa']
]);

echo json_encode(['success' => true, 'ride_id' => $pdo->lastInsertId()]);
