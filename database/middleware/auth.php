<?php

define('JWT_SECRET', 'taxi_gabon_secret_2024');

function genererToken(array $payload): string {
    $header    = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload['exp'] = time() + (60 * 60 * 24);
    $payload   = base64_encode(json_encode($payload));
    $signature = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    return "$header.$payload.$signature";
}

function verifierToken(): array {
    $headers = getallheaders();

    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        die(json_encode(['erreur' => 'Token manquant']));
    }

    $token   = str_replace('Bearer ', '', $headers['Authorization']);
    $parties = explode('.', $token);

    if (count($parties) !== 3) {
        http_response_code(401);
        die(json_encode(['erreur' => 'Token invalide']));
    }

    [$header, $payload, $signature] = $parties;

    $signatureAttendue = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    if (!hash_equals($signatureAttendue, $signature)) {
        http_response_code(401);
        die(json_encode(['erreur' => 'Token falsifié']));
    }

    $data = json_decode(base64_decode($payload), true);
    if ($data['exp'] < time()) {
        http_response_code(401);
        die(json_encode(['erreur' => 'Token expiré']));
    }

    return $data;
}