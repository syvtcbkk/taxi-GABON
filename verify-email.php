<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/db.php';
require_once 'includes/header.php';

$token = trim($_GET['token'] ?? '');
$status = 'error';
$name = '';

// CORRECTION : On vérifie que la variable de connexion globale $pdo existe
if ($token) {
    try {
        $pdo  = getPDO();
        $stmt = $pdo->prepare('SELECT id, first_name, is_verified FROM users WHERE verify_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            if ($user['is_verified']) {
                $status = 'already';
            } else {
                $pdo->prepare('UPDATE users SET is_verified = 1, verify_token = NULL WHERE id = ?')
                    ->execute([$user['id']]);
                $status = 'success';
                $name   = $user['first_name'];
            }
        }
    } catch (PDOException $e) {
        $status = 'error';
    }
}
?>

<div class="container py-5 mt-5">
    <div class="auth-container text-center mx-auto" style="max-width: 500px; padding: 40px; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.05);">
        <?php if ($status === 'success'): ?>
            <i class="fa-solid fa-circle-check fa-4x text-success mb-4"></i>
            <h2 class="auth-title fw-bold mb-3" style="color: #1a1a2e;">E-mail vérifié !</h2>
            <p class="text-muted">Bonjour <strong><?= htmlspecialchars($name) ?></strong>, votre compte Taxi Gabon est maintenant actif.</p>
            <a href="login.php" class="btn btn-primary btn-lg fw-bold rounded-pill px-5 mt-4">Se connecter</a>

        <?php elseif ($status === 'already'): ?>
            <i class="fa-solid fa-circle-info fa-4x text-warning mb-4"></i>
            <h2 class="auth-title fw-bold mb-3" style="color: #1a1a2e;">Déjà vérifié</h2>
            <p class="text-muted">Votre adresse e-mail a déjà été confirmée. Vous pouvez l'utiliser pour vous connecter.</p>
            <a href="login.php" class="btn btn-primary btn-lg fw-bold rounded-pill px-5 mt-4">Se connecter</a>

        <?php else: ?>
            <i class="fa-solid fa-circle-xmark fa-4x text-danger mb-4"></i>
            <h2 class="auth-title fw-bold mb-3" style="color: #1a1a2e;">Lien inaccessible</h2>
            <p class="text-muted">Ce lien de vérification est invalide, a expiré ou votre serveur local n'a pas pu traiter la demande.</p>
            <a href="register.php" class="btn btn-primary btn-lg fw-bold rounded-pill px-5 mt-4">Créer un compte</a>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>