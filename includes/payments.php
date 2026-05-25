<?php
// includes/payments.php — helper minimal pour Stripe via cURL
if (session_status() === PHP_SESSION_NONE) session_start();

function stripe_secret()
{
    // Lire depuis .env via $_ENV
    return $_ENV['STRIPE_SECRET'] ?? getenv('STRIPE_SECRET') ?? '';
}

function stripe_post($path, $params = [])
{
    $secret = stripe_secret();
    if (!$secret) throw new RuntimeException('STRIPE_SECRET non configuré.');

    $ch = curl_init('https://api.stripe.com/v1' . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $secret . ':');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err) throw new RuntimeException('cURL error: ' . $err);
    $data = json_decode($resp, true);
    if ($code < 200 || $code >= 300) {
        throw new RuntimeException('Stripe API error: ' . ($data['error']['message'] ?? $resp));
    }
    return $data;
}

?>
