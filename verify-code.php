<?php
// verify-code.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. On force l'inclusion de la connexion de base de données
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/header.php';

// On s'assure que $pdo est bien accessible globalement
global $pdo;

$email = $_SESSION['verify_email'] ?? '';
$error = '';
$success = $_SESSION['success'] ?? '';

// On nettoie la session pour éviter les affichages en boucle
unset($_SESSION['success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // On récupère les cases du code à 6 chiffres si elles sont séparées, ou le champ unique
    $codeSaisi = '';
    if (isset($_POST['code'])) {
        $codeSaisi = trim($_POST['code']);
    } elseif (isset($_POST['digit']) && is_array($_POST['digit'])) {
        $codeSaisi = implode('', $_POST['digit']);
    }
    
    $codeSaisi = trim($codeSaisi);

    if (strlen($codeSaisi) !== 6) {
        $error = "Veuillez entrer un code complet à 6 chiffres.";
    } elseif (!isset($pdo) || $pdo === null) {
        $error = "Erreur de connexion : Impossible d'accéder à la base de données.";
    } else {
        try {
            // Recherche de l'utilisateur avec son code en cours de validité
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND reset_code = ?");
            $stmt->execute([$email, $codeSaisi]);
            $user = $stmt->fetch();

            if ($user) {
                // Succès ! On active définitivement le compte de l'utilisateur
                $update = $pdo->prepare("UPDATE users SET is_verified = 1, reset_code = NULL, reset_expires = NULL WHERE id = ?");
                $update->execute([$user['id']]);

                $_SESSION['success'] = "Votre compte a été activé avec succès au Gabon ! Connectez-vous.";
                header('Location: login.php');
                exit;
            } else {
                $error = "Code de vérification incorrect ou expiré. Veuillez réessayer.";
            }
        } catch (PDOException $e) {
            $error = "Erreur technique système : " . $e->getMessage();
        }
    }
}
?>

<div class="container py-5 mt-5">
    <div class="auth-container text-center mx-auto" style="max-width: 450px; padding: 40px; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.05);">
        
        <div class="mb-4">
            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill small">🔒 Mode Développeur Activé</span>
        </div>

        <h2 class="fw-bold mb-2">Vérifiez votre e-mail</h2>
        <p class="text-muted small">Un code à 6 chiffres a été généré pour <br><strong><?= htmlspecialchars($email ? $email : 'votre adresse email') ?></strong></p>

        <?php if (!empty($success)): ?>
            <div class="alert alert-info py-2 small border-0 text-start">
                <i class="fa-solid fa-circle-info me-2"></i> <?= htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small border-0 text-start">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="verify-code.php" method="POST" class="mt-4">
            <div class="mb-4">
                <label class="form-label text-muted small">Entrez le code à 6 chiffres reçu :</label>
                <input type="text" name="code" class="form-control form-control-lg text-center fw-bold letter-spacing-2" 
                       placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autocomplete="off"
                       style="font-size: 28px; letter-spacing: 6px; border-radius: 12px; border: 2px solid #e0e0e0;">
            </div>
            
            <button type="submit" class="btn btn-warning w-100 fw-bold py-3 text-dark rounded-pill shadow-sm" style="background-color: #ffcc00; border: none;">
                Vérifier mon compte
            </button>
        </form>
        
        <p class="mt-4 mb-0 small text-muted">Vous n'avez pas reçu le code ? <a href="register.php" class="text-dark fw-bold text-decoration-none">Réessayer</a></p>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>