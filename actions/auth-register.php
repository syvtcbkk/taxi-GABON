<?php
// actions/auth-register.php

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../register.php');
    exit;
}

$firstName = trim($_POST['first_name'] ?? '');
$lastName  = trim($_POST['last_name']  ?? '');
$email     = strtolower(trim($_POST['email'] ?? ''));
$phone     = trim($_POST['phone']      ?? '');
$password  = $_POST['password']        ?? '';
$role      = in_array($_POST['role'] ?? '', ['passenger', 'driver']) ? $_POST['role'] : 'passenger';

// ── Validation basique ───────────────────────────────────────────────────────
if (!$firstName || !$lastName || !$email || !$phone || !$password) {
    $_SESSION['error'] = 'Veuillez remplir tous les champs.';
    header('Location: ../register.php');
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = 'Adresse e-mail invalide.';
    header('Location: ../register.php');
    exit;
}
if (strlen($password) < 8) {
    $_SESSION['error'] = 'Le mot de passe doit contenir au moins 8 caractères.';
    header('Location: ../register.php');
    exit;
}

$pdo = getPDO();

// ── Vérification doublon ─────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    $_SESSION['error'] = 'Un compte existe déjà avec cet e-mail.';
    header('Location: ../register.php');
    exit;
}

// ── Insertion ────────────────────────────────────────────────────────────────
$passwordHash  = password_hash($password, PASSWORD_BCRYPT);
$verifyToken   = bin2hex(random_bytes(32));

$pdo->prepare('
    INSERT INTO users (first_name, last_name, email, phone, password_hash, role, verify_token)
    VALUES (?, ?, ?, ?, ?, ?, ?)
')->execute([$firstName, $lastName, $email, $phone, $passwordHash, $role, $verifyToken]);

$userId = $pdo->lastInsertId();

// Créer un profil chauffeur vide si besoin
if ($role === 'driver') {
    $pdo->prepare('INSERT INTO driver_profiles (user_id) VALUES (?)')->execute([$userId]);
}

// ── Envoi e-mail de vérification ─────────────────────────────────────────────
// Détecte proprement si le projet est dans un sous-dossier (ex: /taxi-GABON)
$projectDir = str_replace('/actions', '', dirname($_SERVER['SCRIPT_NAME']));
$baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $projectDir;

// Génère le lien de vérification final propre
$verifyLink = rtrim($baseUrl, '/') . '/verify-email.php?token=' . $verifyToken;

$sent = sendMail(
    $email,
    "$firstName $lastName",
    '✅ Vérifiez votre adresse e-mail — Taxi Gabon',
    emailVerificationTemplate("$firstName $lastName", $verifyLink)
);

if ($sent) {
    $_SESSION['success'] = "Compte créé ! Un e-mail de vérification a été envoyé à $email.";
} else {
    $_SESSION['success'] = "Compte créé ! Vérifiez votre boîte e-mail pour activer votre compte.";
}

header('Location: ../login.php');
exit;
