<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/db.php';
require_once 'includes/header.php';

$token = trim($_GET['token'] ?? '');
$status = 'error';

if ($token) {
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
            $status  = 'success';
            $name    = $user['first_name'];
        }
    }
}
?>

<div class="container">
    <div class="auth-container text-center">
        <?php if ($status === 'success'): ?>
            <i class="fa-solid fa-circle-check fa-4x text-success mb-4"></i>
            <h2 class="auth-title">E-mail vérifié !</h2>
            <p class="text-muted">Bonjour <strong><?= htmlspecialchars($name ?? '') ?></strong>, votre compte est maintenant actif.</p>
            <a href="login.php" class="btn btn-primary btn-lg fw-bold rounded-pill px-5 mt-3">Se connecter</a>

        <?php elseif ($status === 'already'): ?>
            <i class="fa-solid fa-circle-info fa-4x text-warning mb-4"></i>
            <h2 class="auth-title">Déjà vérifié</h2>
            <p class="text-muted">Votre adresse e-mail est déjà confirmée.</p>
            <a href="login.php" class="btn btn-primary btn-lg fw-bold rounded-pill px-5 mt-3">Se connecter</a>

        <?php else: ?>
            <i class="fa-solid fa-circle-xmark fa-4x text-danger mb-4"></i>
            <h2 class="auth-title">Lien invalide</h2>
            <p class="text-muted">Ce lien de vérification est invalide ou a expiré.</p>
            <a href="register.php" class="btn btn-primary btn-lg fw-bold rounded-pill px-5 mt-3">Créer un compte</a>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
