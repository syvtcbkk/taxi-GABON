<?php
// includes/csrf.php
if (session_status() === PHP_SESSION_NONE) session_start();

function csrf_token()
{
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_expires']) || $_SESSION['csrf_token_expires'] < time()) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_expires'] = time() + 60 * 60; // valable 1 heure
    }
    return $_SESSION['csrf_token'];
}

function csrf_input()
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function verify_csrf($token)
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($token)) return false;
    if (empty($_SESSION['csrf_token'])) return false;
    $valid = hash_equals($_SESSION['csrf_token'], $token);
    return $valid;
}

?>
