<?php
// actions/auth-register.php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/validators.php';

if (file_exists(__DIR__ . '/../includes/mailer.php')) {
    require_once __DIR__ . '/../includes/mailer.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../register.php'); exit;
}

require_once __DIR__ . '/../includes/csrf.php';

$csrf = $_POST['csrf_token'] ?? '';
if (!verify_csrf($csrf)) {
    $_SESSION['error'] = 'Requête invalide (token CSRF manquant ou incorrect).';
    header('Location: ../register.php'); exit;
}

$firstName = trim($_POST['first_name'] ?? '');
$lastName  = trim($_POST['last_name']  ?? '');
$email     = strtolower(trim($_POST['email'] ?? ''));
$phone     = trim($_POST['phone']      ?? '');
$password  = $_POST['password']        ?? '';
$role      = in_array($_POST['role'] ?? '', ['passenger', 'driver']) ? $_POST['role'] : 'passenger';

if (!$firstName || !$lastName || !$email || !$phone || !$password) {
    $_SESSION['error'] = 'Veuillez remplir tous les champs.';
    header('Location: ../register.php'); exit;
}

if (!validateEmail($email)) {
    $_SESSION['error'] = 'Adresse email invalide.';
    header('Location: ../register.php'); exit;
}

if (!validatePhone($phone)) {
    $_SESSION['error'] = 'Numéro de téléphone invalide.';
    header('Location: ../register.php'); exit;
}

if (strlen($password) < 8) {
    $_SESSION['error'] = 'Le mot de passe doit contenir au moins 8 caractères.';
    header('Location: ../register.php'); exit;
}

if (!isset($pdo)) {
    die("Erreur de configuration : La variable \$pdo n'est pas disponible.");
}

try {
    // Vérification si l'email existe déjà
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? OR phone = ?');
    $stmt->execute([$email, $phone]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'Cet e-mail ou ce numéro de téléphone est déjà enregistré au Gabon.';
        header('Location: ../register.php'); exit;
    }

    // Préparation des données de hachage et du code de test
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $verifyCode   = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $verifyExpires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // Mode développeur : on activate d'office le compte si DEV_MODE=true (is_verified = 1)
    $devMode = ($_ENV['DEV_MODE'] ?? 'false') === 'true';
    $isVerifiedLocal = ($devMode && (in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true))) ? 1 : 0;

    // CORRECTION CRITIQUE : Plus aucune mention de verify_token ici !
    $insert = $pdo->prepare('
        INSERT INTO users (first_name, last_name, email, phone, password_hash, role, reset_code, reset_expires, is_verified)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $insert->execute([
        sanitizeString($firstName),
        sanitizeString($lastName),
        $email,
        $phone,
        $passwordHash,
        $role,
        $verifyCode,
        $verifyExpires,
        $isVerifiedLocal
    ]);

    $userId = $pdo->lastInsertId();

    if ($role === 'driver') {
        $pdo->prepare('INSERT INTO driver_profiles (user_id) VALUES (?)')->execute([$userId]);
    }

    // Tentative d'envoi du mail de courtoisie
    if (function_exists('sendMail') && function_exists('passwordResetTemplate')) {
        try {
            sendMail(
                $email,
                "$firstName $lastName",
                '🔑 Votre code de vérification — Taxi Gabon',
                passwordResetTemplate("$firstName $lastName", $verifyCode)
            );
        } catch (Exception $e) {
            error_log('Email envoi échoué: ' . $e->getMessage());
        }
    }

    $_SESSION['verify_email'] = $email;
    if ($isVerifiedLocal) {
        $_SESSION['success'] = "Mode Développeur : Compte activé !";
    } else {
        $_SESSION['success'] = "Inscription réussie ! Vérifiez votre email pour le code de confirmation.";
    }

    header('Location: ../verify-code.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur technique d'insertion : " . $e->getMessage();
    header('Location: ../register.php'); exit;
}