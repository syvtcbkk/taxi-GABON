<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/header.php';
?>

<div class="container">
    <div class="auth-container">
        <div class="text-center mb-4">
            <i class="fa-solid fa-circle-user fa-3x text-warning mb-3"></i>
            <h2 class="auth-title mb-0">Connexion</h2>
            <p class="text-muted mt-2">Heureux de vous revoir !</p>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger text-center small rounded-3 py-2"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success text-center small rounded-3 py-2"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <form action="actions/auth-login.php" method="POST">
            <div class="mb-3">
                <label class="form-label fw-bold">Email ou Téléphone</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-envelope"></i></span>
                    <input type="text" name="identifiant" class="form-control form-control-lg" placeholder="Votre email ou numéro" required>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-bold d-flex justify-content-between">
                    <span>Mot de passe</span>
                    <a href="forgot-password.php" class="text-decoration-none text-muted small fw-normal">Mot de passe oublié ?</a>
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="password" class="form-control form-control-lg" placeholder="••••••••" required>
                </div>
            </div>
            <div class="mb-4 form-check">
                <input type="checkbox" class="form-check-input" id="rememberMe">
                <label class="form-check-label text-muted" for="rememberMe">Se souvenir de moi</label>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold rounded-pill">Se connecter</button>

            <div class="text-center mt-4 pt-3 border-top">
                <p class="mb-0 text-muted">Pas encore de compte ? <a href="register.php" class="text-decoration-none fw-bold text-dark">Inscrivez-vous</a></p>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>