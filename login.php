<?php
// login.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ── REDIRECTION SÉCURISÉE AUTOMATIQUE ────────────────────────────────────────
// Si l'utilisateur est déjà connecté, on le redirige immédiatement vers son espace
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    if ($_SESSION['user_role'] === 'driver') {
        header('Location: dashboard-driver.php');
    } else {
        header('Location: dashboard-passenger.php');
    }
    exit;
}

// Si la redirection n'a pas eu lieu, on charge le header standard
require_once 'includes/header.php';
?>

<div class="container my-5">
    <div class="auth-container mx-auto" style="max-width: 450px; padding: 40px; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.05);">
        <div class="text-center mb-4">
            <i class="fa-solid fa-circle-user fa-3x text-warning mb-3"></i>
            <h2 class="auth-title mb-0 fw-bold">Connexion</h2>
            <p class="text-muted mt-2 small">Heureux de vous revoir !</p>
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
            <?php echo csrf_input(); ?>
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">Email ou Téléphone</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0" style="border-radius: 10px 0 0 10px;"><i class="fa-solid fa-envelope text-muted"></i></span>
                    <input type="text" name="identifiant" class="form-control form-control-lg border-start-0 small" placeholder="Votre email ou numéro" required style="border-radius: 0 10px 10px 0; font-size: 15px;">
                </div>
            </div>
            
            <div class="mb-4">
                <label class="form-label small fw-bold text-muted d-flex justify-content-between">
                    <span>Mot de passe</span>
                    <a href="forgot-password.php" class="text-decoration-none text-warning small fw-bold">Mot de passe oublié ?</a>
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0" style="border-radius: 10px 0 0 10px;"><i class="fa-solid fa-lock text-muted"></i></span>
                    <input type="password" name="password" class="form-control form-control-lg border-start-0 small" placeholder="••••••••" required style="border-radius: 0 10px 10px 0; font-size: 15px;">
                </div>
            </div>
            
            <div class="mb-4 form-check d-flex align-items-center">
                <input type="checkbox" class="form-check-input me-2" id="rememberMe" style="cursor: pointer;">
                <label class="form-check-label text-muted small" for="rememberMe" style="cursor: pointer; user-select: none;">Se souvenir de moi</label>
            </div>
            
            <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold text-dark rounded-pill py-2.5 shadow-sm" style="background-color: #ffcc00; border: none;">
                Se connecter
            </button>

            <div class="text-center mt-4 pt-3 border-top">
                <p class="mb-0 small text-muted">Pas encore de compte ? <a href="register.php" class="text-decoration-none fw-bold text-dark">Inscrivez-vous</a></p>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>