<?php
// api/create-checkout-session.php — crée une session Stripe Checkout
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/payments.php';
header('Content-Type: application/json');

$sessionRole = $_SESSION['user_role'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $sessionRole !== 'passenger') {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$amount = (int)($body['price_fcfa'] ?? 0);
if ($amount <= 0) { echo json_encode(['success' => false, 'error' => 'Montant invalide']); exit; }

// Stripe expects zero-decimal currencies (XAF) amounts as integer (no cents)
$currency = 'xaf';

try {
    $success_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        . '/payment-success.php?session_id={CHECKOUT_SESSION_ID}';
    $cancel_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        . '/dashboard-passenger.php';

    $params = [
        'payment_method_types[]' => 'card',
        'mode' => 'payment',
        'success_url' => $success_url,
        'cancel_url' => $cancel_url,
        // line items
        'line_items[0][price_data][currency]' => $currency,
        'line_items[0][price_data][product_data][name]' => 'Taxi Gabon - Course',
        'line_items[0][price_data][unit_amount]' => $amount,
        'line_items[0][quantity]' => 1,
        // metadata: serialize minimal ride info
        'metadata[passenger_id]' => $_SESSION['user_id'],
        'metadata[origin_address]' => $body['origin_address'] ?? '',
        'metadata[origin_lat]' => $body['origin_lat'] ?? '',
        'metadata[origin_lng]' => $body['origin_lng'] ?? '',
        'metadata[dest_address]' => $body['dest_address'] ?? '',
        'metadata[dest_lat]' => $body['dest_lat'] ?? '',
        'metadata[dest_lng]' => $body['dest_lng'] ?? '',
        'metadata[distance_km]' => $body['distance_km'] ?? '',
        'metadata[duration_min]' => $body['duration_min'] ?? '',
        'metadata[price_fcfa]' => $amount,
    ];

    $resp = stripe_post('/v1/checkout/sessions', $params);
    // Enregistrer la session dans la table payments pour idempotence
    $session_id = $resp['id'] ?? null;
    if ($session_id) {
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare('INSERT INTO payments (stripe_session_id, passenger_id, amount, currency, status, metadata) VALUES (?, ?, ?, ?, ?, ? )');
            $metadataJson = json_encode($params); // sauvegarde des données d'appel
            $stmt->execute([$session_id, (int)$_SESSION['user_id'], $amount, $currency, 'created', $metadataJson]);
        } catch (PDOException $e) {
            // Ignore duplicate or DB error — la session existe peut-être déjà
        }
    }
    // Return session id to client for potential tracking
    echo json_encode(['success' => true, 'url' => $resp['url'], 'session_id' => $session_id]);
    exit;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
