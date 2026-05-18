<?php
// actions/auth-login.php

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php'); exit;
}

$identifiant = trim($_POST['identifiant'] ?? '');
$password    = $_POST['password'] ?? '';

if (!$identifiant || !$password) {
    $_SESSION['error'] = 'Veuillez remplir tous les champs.';
    header('Location: ../login.php'); exit;
}

$pdo = getPDO();

// Recherche par e-mail ou téléphone
$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? OR phone = ? LIMIT 1');
$stmt->execute([$identifiant, $identifiant]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    $_SESSION['error'] = 'Identifiant ou mot de passe incorrect.';
    header('Location: ../login.php'); exit;
}

if (!$user['is_verified']) {
    $_SESSION['error'] = 'Veuillez vérifier votre adresse e-mail avant de vous connecter.';
    header('Location: ../login.php'); exit;
}

// ── Session ──────────────────────────────────────────────────────────────────
session_regenerate_id(true);
$_SESSION['user_id']    = $user['id'];
$_SESSION['user_name']  = $user['first_name'];
$_SESSION['user_role']  = $user['role'];
$_SESSION['user_email'] = $user['email'];

// Redirection selon le rôle
if ($user['role'] === 'driver') {
    header('Location: ../dashboard-driver.php');
} else {
    header('Location: ../dashboard-passenger.php');
}
exit;
