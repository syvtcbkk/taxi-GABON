<?php
// actions/submit-review.php — enregistre un avis et met à jour la note utilisateur
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard-passenger.php'); exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verify_csrf($csrf)) {
    $_SESSION['error'] = 'Requête invalide.'; header('Location: ../dashboard-passenger.php'); exit;
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { $_SESSION['error'] = 'Non autorisé.'; header('Location: ../login.php'); exit; }

$rideId = (int)($_POST['ride_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if ($rideId <= 0 || $rating < 1 || $rating > 5) {
    $_SESSION['error'] = 'Données invalides.'; header('Location: ../dashboard-passenger.php'); exit;
}

$pdo = getPDO();
// Vérifier que le ride existe et implique l'utilisateur
$stmt = $pdo->prepare('SELECT * FROM rides WHERE id = ? LIMIT 1');
$stmt->execute([$rideId]);
$ride = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ride) { $_SESSION['error'] = 'Trajet introuvable.'; header('Location: ../dashboard-passenger.php'); exit; }

// Déterminer destinataire: si user is passenger and ride has driver
if ((int)$ride['passenger_id'] === (int)$userId) {
    $toUser = (int)$ride['driver_id'];
    $role = 'passenger_to_driver';
} elseif ((int)$ride['driver_id'] === (int)$userId) {
    $toUser = (int)$ride['passenger_id'];
    $role = 'driver_to_passenger';
} else {
    $_SESSION['error'] = 'Vous n\'êtes pas impliqué dans ce trajet.'; header('Location: ../dashboard-passenger.php'); exit;
}
if (!$toUser) { $_SESSION['error'] = 'Destinataire introuvable.'; header('Location: ../dashboard-passenger.php'); exit; }

// Vérifier qu'il n'y a pas déjà d'avis du même utilisateur pour ce trajet
$chk = $pdo->prepare('SELECT id FROM reviews WHERE ride_id = ? AND from_user_id = ? LIMIT 1');
$chk->execute([$rideId, $userId]);
if ($chk->fetch()) { $_SESSION['error'] = 'Vous avez déjà laissé un avis pour ce trajet.'; header('Location: ../dashboard-passenger.php'); exit; }

// Insérer review
$ins = $pdo->prepare('INSERT INTO reviews (ride_id, from_user_id, to_user_id, rating, comment) VALUES (?, ?, ?, ?, ?)');
$ins->execute([$rideId, $userId, $toUser, $rating, $comment]);

// Mettre à jour colonne dans rides et recalculer note moyenne utilisateur
if ($role === 'passenger_to_driver') {
    $u = $pdo->prepare('UPDATE rides SET driver_rating = ? WHERE id = ?');
    $u->execute([$rating, $rideId]);
} else {
    $u = $pdo->prepare('UPDATE rides SET passenger_rating = ? WHERE id = ?');
    $u->execute([$rating, $rideId]);
}

// Recalcul note moyenne
$avg = $pdo->prepare('SELECT AVG(rating) as avg_rating FROM reviews WHERE to_user_id = ?');
$avg->execute([$toUser]);
$avgVal = $avg->fetchColumn() ?: 5.0;
$upd = $pdo->prepare('UPDATE users SET rating = ? WHERE id = ?');
$upd->execute([round((float)$avgVal,2), $toUser]);

$_SESSION['success'] = 'Merci pour votre avis !';
header('Location: ../dashboard-passenger.php'); exit;
