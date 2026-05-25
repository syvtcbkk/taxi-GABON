<?php
// actions/auth-login.php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php'); exit;
}

require_once __DIR__ . '/../includes/csrf.php';

$csrf = $_POST['csrf_token'] ?? '';
if (!verify_csrf($csrf)) {
    $_SESSION['error'] = 'Requête invalide (token CSRF manquant ou incorrect).';
    header('Location: ../login.php'); exit;
}

$identifiant = trim($_POST['identifiant'] ?? '');
$password    = $_POST['password'] ?? '';

if (empty($identifiant) || empty($password)) {
    $_SESSION['error'] = "Veuillez remplir tous les champs.";
    header('Location: ../login.php'); exit;
}

if (!isset($pdo)) {
    die("Erreur système : Connexion à la base de données indisponible.");
}

try {
    // Recherche par email OU par téléphone
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ?");
    $stmt->execute([$identifiant, $identifiant]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        
        // Sécurité : On vérifie si le compte est actif
        if ((int)$user['is_verified'] !== 1) {
            // Mode développement: bypass auto uniquement si env spécifié
            $devMode = ($_ENV['DEV_MODE'] ?? 'false') === 'true';
            if ($devMode && (in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true))) {
                $pdo->prepare('UPDATE users SET is_verified = 1 WHERE id = ?')->execute([$user['id']]);
                $user['is_verified'] = 1;
            }
        }

        if ((int)$user['is_verified'] !== 1) {
            $_SESSION['verify_email'] = $user['email'];
            $_SESSION['error'] = "Veuillez d'abord valider votre compte avec le code à 6 chiffres.";
            header('Location: ../verify-code.php'); exit;
        }

        // Regénération de l'identifiant de session pour plus de sécurité
        session_regenerate_id(true);

        // On remplit la session
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['first_name'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['role']       = $user['role'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name']  = $user['last_name'];

        // Redirection selon le profil
        if ($user['role'] === 'driver') {
            header('Location: ../dashboard-driver.php');
        } else {
            header('Location: ../dashboard-passenger.php');
        }
        exit;
        
    } else {
        $_SESSION['error'] = "Identifiants ou mot de passe incorrects.";
        header('Location: ../login.php'); exit;
    }

} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur de connexion : " . $e->getMessage();
    header('Location: ../login.php'); exit;
}