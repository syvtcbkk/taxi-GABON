<?php
// payment-success.php — récupère la session Stripe et crée la course si payée
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/payments.php';

$session_id = $_GET['session_id'] ?? '';
if (!$session_id) {
    $_SESSION['error'] = 'Paramètre manquant.';
    header('Location: dashboard-passenger.php'); exit;
}

try {
    // Récupérer la session
    $session = stripe_post('/v1/checkout/sessions/' . rawurlencode($session_id), []);
    if (($session['payment_status'] ?? '') !== 'paid') {
        $_SESSION['error'] = 'Paiement non confirmé.';
        header('Location: dashboard-passenger.php'); exit;
    }

    $meta = $session['metadata'] ?? [];
    $session_id = $session['id'] ?? '';
    // Valider leader minimal
    $required = ['passenger_id','origin_address','origin_lat','origin_lng','dest_address','dest_lat','dest_lng','price_fcfa'];
    foreach ($required as $k) if (empty($meta[$k])) {
        $_SESSION['error'] = 'Données de réservation manquantes.';
        header('Location: dashboard-passenger.php'); exit;
    }

    $pdo = getPDO();

    // Vérifier payment enregistré
    $payStmt = $pdo->prepare('SELECT * FROM payments WHERE stripe_session_id = ? LIMIT 1');
    $payStmt->execute([$session_id]);
    $payRow = $payStmt->fetch(PDO::FETCH_ASSOC);

    if ($payRow && $payRow['status'] === 'paid' && !empty($payRow['ride_id'])) {
        // Déjà créé par webhook
        $_SESSION['success'] = 'Paiement confirmé — votre demande est en cours de traitement.';
        header('Location: dashboard-passenger.php'); exit;
    }

    // Si payment existant mais pas encore lié, créer la course (fallback)
    if ($payRow && $payRow['status'] === 'paid' && empty($payRow['ride_id'])) {
        // Annuler éventuel pending
        $pdo->prepare('UPDATE rides SET status = "cancelled" WHERE passenger_id = ? AND status = "pending"')
            ->execute([(int)$meta['passenger_id']]);
        $stmt = $pdo->prepare('INSERT INTO rides (passenger_id, origin_address, origin_lat, origin_lng, dest_address, dest_lat, dest_lng, distance_km, duration_min, price_fcfa, status, stripe_session_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", ?, NOW())');
        $stmt->execute([
            (int)$meta['passenger_id'],
            $meta['origin_address'], $meta['origin_lat'], $meta['origin_lng'],
            $meta['dest_address'], $meta['dest_lat'], $meta['dest_lng'],
            $meta['distance_km'] ?? null, $meta['duration_min'] ?? null, (int)$meta['price_fcfa'], $session_id
        ]);
        $rideId = $pdo->lastInsertId();

        // mettre à jour payments si présent
        if ($session_id) {
            $payStmt = $pdo->prepare('SELECT id FROM payments WHERE stripe_session_id = ? LIMIT 1');
            $payStmt->execute([$session_id]);
            $pay = $payStmt->fetch(PDO::FETCH_ASSOC);
            if ($pay) {
                $u = $pdo->prepare('UPDATE payments SET status = ?, ride_id = ?, updated_at = NOW() WHERE id = ?');
                $u->execute(['paid', $rideId, $pay['id']]);
            } else {
                $ins = $pdo->prepare('INSERT INTO payments (stripe_session_id, passenger_id, amount, currency, status, metadata, ride_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $ins->execute([$session_id, (int)$meta['passenger_id'], (int)$meta['price_fcfa'], 'xaf', 'paid', json_encode($meta), $rideId]);
            }
        }
        $rideId = $pdo->lastInsertId();
        $u = $pdo->prepare('UPDATE payments SET ride_id = ?, updated_at = NOW() WHERE id = ?');
        $u->execute([$rideId, $payRow['id']]);
        $_SESSION['success'] = 'Paiement confirmé — votre demande a été enregistrée.';
        header('Location: dashboard-passenger.php'); exit;
    }

    // Si payment non enregistré, informer l'utilisateur que la création est en cours via webhook
    $_SESSION['success'] = 'Paiement confirmé. Votre demande sera traitée sous peu (webhook en cours).';
    header('Location: dashboard-passenger.php'); exit;

} catch (Exception $e) {
    // Log technique côté serveur
    try { logger()->error('[payment-success] ' . $e->getMessage()); } catch (Throwable $ex) { error_log('[payment-success] ' . $e->getMessage()); }
    // Message non technique côté utilisateur
    $_SESSION['error'] = 'Un problème est survenu lors de la confirmation du paiement. Si le problème persiste, contactez l\'administrateur.';
    header('Location: dashboard-passenger.php'); exit;
}
