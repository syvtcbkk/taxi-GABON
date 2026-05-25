<?php
// api/stripe-webhook.php — point d'entrée pour webhooks Stripe
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/payments.php';

// Lire le payload brut
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$webhook_secret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? getenv('STRIPE_WEBHOOK_SECRET') ?: '';

// Fonction utilitaire de vérification simple (si secret fourni)
function verify_stripe_signature($payload, $sig_header, $secret)
{
    if (!$sig_header || !$secret) return false;
    // Extrait t= et v1=
    $parts = [];
    foreach (explode(',', $sig_header) as $part) {
        [$k, $v] = array_map('trim', explode('=', $part, 2) + [1 => '']);
        $parts[$k] = $v;
    }
    if (empty($parts['t']) || empty($parts['v1'])) return false;
    $t = $parts['t'];
    $sig = $parts['v1'];
    $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
    // Tolérance: 5 minutes
    if (abs(time() - (int)$t) > 300) return false;
    return hash_equals($expected, $sig);
}

// Si webhook secret configuré, vérifier signature
if ($webhook_secret) {
    if (!verify_stripe_signature($payload, $sig_header, $webhook_secret)) {
        http_response_code(400);
        echo json_encode(['error' => 'Signature invalide']);
        exit;
    }
}

$event = json_decode($payload, true);
// Si payload vide ou parse fail, tenter de récupérer l'event via l'API (moins sécurisé)
if (!$event || empty($event['type'])) {
    // essayer d'extraire un event id
    $data = json_decode($payload, true);
    $event_id = $data['id'] ?? null;
    if ($event_id) {
        try {
            $event = stripe_post('/v1/events/' . rawurlencode($event_id));
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Impossible de récupérer l\'événement']);
            exit;
        }
    }
}

// Traiter l'événement
try {
    $type = $event['type'] ?? '';
    if ($type === 'checkout.session.completed') {
        $session = $event['data']['object'] ?? [];
        if (($session['payment_status'] ?? '') === 'paid') {
            $meta = $session['metadata'] ?? [];
            $session_id = $session['id'] ?? null;
            $pdo = getPDO();

            // Vérifier paiement déjà enregistré
            if ($session_id) {
                $payStmt = $pdo->prepare('SELECT * FROM payments WHERE stripe_session_id = ? LIMIT 1');
                $payStmt->execute([$session_id]);
                $payRow = $payStmt->fetch(PDO::FETCH_ASSOC);
                if ($payRow && $payRow['status'] === 'paid') {
                    // déjà traité
                    http_response_code(200);
                    echo json_encode(['status' => 'already_paid']);
                    exit;
                }
            }
            // Vérification minimale
            $required = ['passenger_id','origin_address','origin_lat','origin_lng','dest_address','dest_lat','dest_lng','price_fcfa'];
            foreach ($required as $k) if (empty($meta[$k])) {
                // ignore if data manquante
                http_response_code(200);
                echo json_encode(['status' => 'ignored', 'reason' => 'metadata_missing']);
                exit;
            }

            // Détection simple de doublon: même passager + mêmes adresses + même montant récent
            $dupStmt = $pdo->prepare('SELECT id FROM rides WHERE passenger_id = ? AND origin_address = ? AND dest_address = ? AND price_fcfa = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE) LIMIT 1');
            $dupStmt->execute([(int)$meta['passenger_id'], $meta['origin_address'], $meta['dest_address'], (int)$meta['price_fcfa']]);
            if ($dupStmt->fetch()) {
                // Marquer payment si existant
                if (!empty($payRow)) {
                    $u = $pdo->prepare('UPDATE payments SET status = ?, updated_at = NOW() WHERE id = ?');
                    $u->execute(['paid', $payRow['id']]);
                }
                http_response_code(200);
                echo json_encode(['status' => 'duplicate']);
                exit;
            }

            // Insérer la course (avec linkage stripe_session_id)
            $stmt = $pdo->prepare('INSERT INTO rides (passenger_id, origin_address, origin_lat, origin_lng, dest_address, dest_lat, dest_lng, distance_km, duration_min, price_fcfa, status, stripe_session_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", ?, NOW())');
            $stmt->execute([
                (int)$meta['passenger_id'],
                $meta['origin_address'], $meta['origin_lat'], $meta['origin_lng'],
                $meta['dest_address'], $meta['dest_lat'], $meta['dest_lng'],
                $meta['distance_km'] ?? null, $meta['duration_min'] ?? null, (int)$meta['price_fcfa'], $session_id
            ]);
            $rideId = $pdo->lastInsertId();

            // Mettre à jour payments s'il existe
            if ($session_id) {
                if ($payRow) {
                    $u = $pdo->prepare('UPDATE payments SET status = ?, ride_id = ?, updated_at = NOW() WHERE id = ?');
                    $u->execute(['paid', $rideId, $payRow['id']]);
                } else {
                    // créer en secours
                    $ins = $pdo->prepare('INSERT INTO payments (stripe_session_id, passenger_id, amount, currency, status, metadata, ride_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $ins->execute([$session_id, (int)$meta['passenger_id'], (int)$meta['price_fcfa'], 'xaf', 'paid', json_encode($meta), $rideId]);
                }
            }

            http_response_code(200);
            echo json_encode(['status' => 'created', 'ride_id' => $rideId]);
            exit;
        }
    }
    // autres événements: accepter et retourner 200
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'type' => $type]);
    exit;
} catch (Exception $e) {
    // Log detailed error server-side for investigation
    try { logger()->error('[stripe-webhook] ' . $e->getMessage()); } catch (Throwable $ex) { error_log('[stripe-webhook] ' . $e->getMessage()); }
    // Return generic response to Stripe / caller
    http_response_code(200);
    // Respond with minimal info so Stripe will not retry endlessly; rely on logs for debugging
    echo json_encode(['status' => 'error', 'message' => 'processed_with_issues']);
    exit;
}
