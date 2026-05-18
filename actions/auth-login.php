<?php
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

$identifiant = trim($_POST['identifiant'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($identifiant) || empty($password)) {
    $_SESSION['error'] = "Veuillez renseigner vos identifiants de connexion.";
    header('Location: ../login.php');
    exit;
}

// Recherche de l'utilisateur par son email OU son téléphone
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ?");
$stmt->execute([$identifiant, $identifiant]);
$user = $stmt->fetch();

// Vérification du compte et du hash de mot de passe
if ($user && password_verify($password, $user['password_hash'])) {
    if ($user['status'] !== 'active') {
        $_SESSION['error'] = "Votre compte a été suspendu.";
        header('Location: ../login.php');
        exit;
    }

    // Assignation des sessions utilisateur globales
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['role'] = $user['role'];

    // Redirection selon le rôle vers le bon fichier racine
    if ($user['role'] === 'driver') {
        header('Location: ../dashboard-driver.php');
    } else {
        header('Location: ../dashboard-passenger.php');
    }
    exit;
} else {
    $_SESSION['error'] = "Identifiants ou mot de passe incorrects.";
    header('Location: ../login.php');
    exit;
}