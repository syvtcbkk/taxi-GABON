<?php
// actions/auth-forgot.php — Génère et envoie un code à 6 chiffres

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../forgot-password.php');
    exit;
}

$email = strtolower(trim($_POST['email'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = 'Adresse e-mail invalide.';
    header('Location: ../forgot-password.php');
    exit;
}

$pdo  = getPDO();
$stmt = $pdo->prepare('SELECT id, first_name FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

// Message générique pour éviter l'énumération d'e-mails
$genericMsg = "Si cet e-mail est associé à un compte, un code de récupération vous a été envoyé.";

if ($user) {
    $code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    $pdo->prepare('UPDATE users SET reset_code = ?, reset_expires = ? WHERE id = ?')
        ->execute([$code, $expires, $user['id']]);

    sendMail(
        $email,
        $user['first_name'],
        '🔐 Code de récupération — Taxi Gabon',
        passwordResetTemplate($user['first_name'], $code)
    );

    // On stocke l'e-mail en session pour la page de reset
    $_SESSION['reset_email'] = $email;
}

$_SESSION['success'] = $genericMsg;
header('Location: ../reset-password.php');
exit;
