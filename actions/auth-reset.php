<?php
// actions/auth-reset.php — Vérifie le code et met à jour le mot de passe

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../reset-password.php'); exit;
}

$email    = $_SESSION['reset_email'] ?? '';
$code     = trim($_POST['code']     ?? '');
$password = $_POST['password']      ?? '';
$confirm  = $_POST['confirm']       ?? '';

if (!$email || !$code || !$password) {
    $_SESSION['error'] = 'Informations manquantes.';
    header('Location: ../reset-password.php'); exit;
}
if ($password !== $confirm) {
    $_SESSION['error'] = 'Les mots de passe ne correspondent pas.';
    header('Location: ../reset-password.php'); exit;
}
if (strlen($password) < 8) {
    $_SESSION['error'] = 'Le mot de passe doit contenir au moins 8 caractères.';
    header('Location: ../reset-password.php'); exit;
}

$pdo  = getPDO();
$stmt = $pdo->prepare('
    SELECT id FROM users
    WHERE email = ? AND reset_code = ? AND reset_expires > NOW()
    LIMIT 1
');
$stmt->execute([$email, $code]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = 'Code invalide ou expiré. Veuillez recommencer.';
    header('Location: ../reset-password.php'); exit;
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$pdo->prepare('
    UPDATE users SET password_hash = ?, reset_code = NULL, reset_expires = NULL WHERE id = ?
')->execute([$hash, $user['id']]);

unset($_SESSION['reset_email']);
$_SESSION['success'] = 'Mot de passe mis à jour avec succès ! Vous pouvez vous connecter.';
header('Location: ../login.php'); exit;
