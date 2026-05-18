<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/header.php';
?>

<div class="container">
    <div class="auth-container">
        <div class="text-center mb-4">
            <i class="fa-solid fa-key fa-3x text-warning mb-3"></i>
            <h2 class="auth-title mb-0">Mot de passe oublié</h2>
            <p class="text-muted mt-2">Entrez votre e-mail pour recevoir un code de récupération.</p>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger text-center small rounded-3 py-2"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <form action="actions/auth-forgot.php" method="POST">
            <div class="mb-4">
                <label class="form-label fw-bold">Adresse e-mail</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-envelope"></i></span>
                    <input type="email" name="email" class="form-control form-control-lg"
                           placeholder="jean.dupont@example.com" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold rounded-pill">
                Envoyer le code
            </button>
            <div class="text-center mt-4 pt-3 border-top">
                <p class="mb-0 text-muted">Vous vous souvenez ?
                    <a href="login.php" class="text-decoration-none fw-bold text-dark">Connexion</a>
                </p>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
