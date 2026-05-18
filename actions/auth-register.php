<?php
// On inclut db.php qui gère déjà le démarrage de session
require_once '../includes/db.php';

// Sécurité : On bloque l'accès direct via URL
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../register.php');
    exit;
}

$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'passenger';

// Validation des données
if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($password)) {
    $_SESSION['error'] = "Veuillez remplir tous les champs.";
    header('Location: ../register.php');
    exit;
}

// Hachage sécurisé du mot de passe
$password_hash = password_hash($password, PASSWORD_BCRYPT);

try {
    $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$first_name, $last_name, $email, $phone, $password_hash, $role]);
    
    $_SESSION['success'] = "Inscription réussie ! Connectez-vous dès maintenant.";
    header('Location: ../login.php');
    exit;

} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // Doublon unique sur l'email ou le téléphone
        $_SESSION['error'] = "Cet email ou ce numéro de téléphone est déjà enregistré au Gabon.";
    } else {
        $_SESSION['error'] = "Une erreur technique est survenue.";
    }
    header('Location: ../register.php');
    exit;
}