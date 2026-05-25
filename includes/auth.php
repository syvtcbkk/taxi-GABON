<?php
// includes/auth.php — Helper pour vérifier l'authentification sur les APIs

/**
 * Vérifie que l'utilisateur est authentifié
 * Retourne l'ID utilisateur ou termine avec une erreur JSON
 */
function requireAuth(): int
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Non authentifié']);
        exit;
    }

    return (int)$_SESSION['user_id'];
}

/**
 * Vérifie que l'utilisateur est authentifié ET possède un rôle spécifique
 */
function requireRole(string ...$allowedRoles): int
{
    $userId = requireAuth();
    
    $sessionRole = $_SESSION['user_role'] ?? $_SESSION['role'] ?? null;
    if (!in_array($sessionRole, $allowedRoles, true)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Accès refusé : rôle ' . $sessionRole . ' non autorisé'
        ]);
        exit;
    }

    return $userId;
}

/**
 * Rate limiter basique en session
 * @param string $action Identifiant unique de l'action (ex: 'book_ride')
 * @param int $maxAttempts Nombre maximum de tentatives
 * @param int $windowSeconds Fenêtre de temps en secondes
 */
function checkRateLimit(string $action, int $maxAttempts = 10, int $windowSeconds = 60): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $key = "_rate_limit_$action";
    $now = time();

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }

    // Nettoyer les tentatives expirées
    $_SESSION[$key] = array_filter(
        $_SESSION[$key],
        fn($t) => ($now - $t) < $windowSeconds
    );

    if (count($_SESSION[$key]) >= $maxAttempts) {
        return false;
    }

    $_SESSION[$key][] = $now;
    return true;
}

/**
 * Enregistre une action sensible pour l'audit
 */
function auditLog(string $action, string $details = '', ?int $userId = null): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $userId = $userId ?? ($_SESSION['user_id'] ?? null);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $timestamp = date('Y-m-d H:i:s');

    try {
        if (function_exists('logger')) {
            logger()->info("[AUDIT] action=$action | user=$userId | ip=$ip | details=$details");
        } else {
            error_log("[AUDIT] $timestamp | action=$action | user=$userId | ip=$ip | $details");
        }
    } catch (Throwable $e) {
        error_log("[AUDIT_ERROR] " . $e->getMessage());
    }
}
