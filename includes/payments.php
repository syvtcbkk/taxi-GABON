<?php
// includes/payments.php — helper minimal pour Stripe via cURL
if (session_status() === PHP_SESSION_NONE) session_start();

// Charger les variables d'environnement depuis .env si elles ne sont pas déjà chargées
if (!isset($_ENV['STRIPE_SECRET']) && file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (class_exists('Dotenv\Dotenv')) {
        try {
            Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();
        } catch (Exception $e) {
            // Ignore dotenv load errors
        }
    }
}

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
    if ($ch === false) {
        throw new RuntimeException('Impossible d\'initialiser cURL.');
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $secret . ':');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (PHP_VERSION_ID < 80500) {
        curl_close($ch);
    }
    if ($err) throw new RuntimeException('cURL error: ' . $err);
    $data = json_decode($resp, true);
    if ($code < 200 || $code >= 300) {
        throw new RuntimeException('Stripe API error: ' . ($data['error']['message'] ?? $resp));
    }
    return $data;
}

function stripe_get($path)
{
    $secret = stripe_secret();
    if (!$secret) throw new RuntimeException('STRIPE_SECRET non configuré.');

    $ch = curl_init('https://api.stripe.com/v1' . $path);
    if ($ch === false) {
        throw new RuntimeException('Impossible d\'initialiser cURL.');
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $secret . ':');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (PHP_VERSION_ID < 80500) {
        curl_close($ch);
    }
    if ($err) throw new RuntimeException('cURL error: ' . $err);
    $data = json_decode($resp, true);
    if ($code < 200 || $code >= 300) {
        throw new RuntimeException('Stripe API error: ' . ($data['error']['message'] ?? $resp));
    }
    return $data;
}

?>
