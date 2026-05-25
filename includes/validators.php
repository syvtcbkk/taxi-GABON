<?php
// includes/validators.php — Fonctions de validation stricte

/**
 * Valide une adresse email
 */
function validateEmail(string $email): bool
{
    $email = trim($email);
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valide un numéro de téléphone (format international basique)
 */
function validatePhone(string $phone): bool
{
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    return preg_match('/^\+?[1-9]\d{6,14}$/', $phone) === 1;
}

/**
 * Valide une coordonnée GPS (latitude ou longitude)
 */
function validateCoordinate(float $coord, bool $isLatitude = true): bool
{
    if ($isLatitude) {
        return $coord >= -90 && $coord <= 90;
    } else {
        return $coord >= -180 && $coord <= 180;
    }
}

/**
 * Valide les coordonnées complètes d'une localisation
 */
function validateLocation(float $lat, float $lng, string $address = ''): bool
{
    if (!validateCoordinate($lat, true) || !validateCoordinate($lng, false)) {
        return false;
    }
    
    if ($address && (strlen($address) < 3 || strlen($address) > 255)) {
        return false;
    }
    
    return true;
}

/**
 * Valide une distance (en km)
 */
function validateDistance(float $distance): bool
{
    return $distance > 0 && $distance <= 10000; // Max 10 000 km
}

/**
 * Valide une durée (en minutes)
 */
function validateDuration(int $duration): bool
{
    return $duration > 0 && $duration <= 1440; // Max 24 heures
}

/**
 * Valide un montant en FCFA
 */
function validateAmount(int $amount): bool
{
    return $amount > 0 && $amount <= 10000000; // Max 10M FCFA
}

/**
 * Valide que les paramètres requis sont présents
 * @param array $data Données à valider
 * @param array $required Liste des clés requises
 * @return string|null Message d'erreur ou null si valide
 */
function validateRequired(array $data, array $required): ?string
{
    foreach ($required as $key) {
        if (empty($data[$key]) && $data[$key] !== 0 && $data[$key] !== '0') {
            return "Champ manquant ou vide : $key";
        }
    }
    return null;
}

/**
 * Nettoie une chaîne d'entrée
 */
function sanitizeString(string $input, int $maxLength = 255): string
{
    $input = trim($input);
    if (strlen($input) > $maxLength) {
        $input = substr($input, 0, $maxLength);
    }
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

/**
 * Parse et valide les données JSON d'une requête API
 * @return array|null Array parsé ou null si invalide
 */
function parseJsonRequest(): ?array
{
    $content = file_get_contents('php://input');
    if (empty($content)) {
        return null;
    }

    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    return is_array($data) ? $data : null;
}
