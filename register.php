<?php 
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['user_id'], $_SESSION['user_role'])) {
    if ($_SESSION['user_role'] === 'driver') {
        header('Location: dashboard-driver.php');
        exit;
    }
    header('Location: dashboard-passenger.php');
    exit;
}
require_once 'includes/header.php'; 
?>

<div class="container">
    <div class="auth-container">
        <div class="text-center mb-4">
            <i class="fa-solid fa-user-plus fa-3x text-warning mb-3"></i>
            <h2 class="auth-title mb-0">Créer un compte</h2>
            <p class="text-muted mt-2">Rejoignez la communauté Taxi Gabon</p>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger text-center small rounded-3 py-2"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success text-center small rounded-3 py-2"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <form action="actions/auth-register.php" method="POST">
            <div class="row mb-3">
                <div class="col-sm-6 mb-3 mb-sm-0">
                    <label class="form-label fw-bold">Prénom</label>
                    <input type="text" name="first_name" class="form-control form-control-lg" placeholder="Jean" required>
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-bold">Nom</label>
                    <input type="text" name="last_name" class="form-control form-control-lg" placeholder="Dupont" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Email</label>
                <input type="email" name="email" class="form-control form-control-lg" placeholder="jean.dupont@example.com" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Téléphone</label>
                <input type="tel" name="phone" class="form-control form-control-lg" placeholder="+241 00 00 00 00" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Mot de passe</label>
                <input type="password" name="password" class="form-control form-control-lg" placeholder="••••••••" required>
            </div>
            <div class="mb-4">
                <label class="form-label fw-bold">Je m'inscris en tant que :</label>
                <select name="role" class="form-select form-select-lg">
                    <option value="passenger" <?php echo (isset($_GET['role']) && $_GET['role'] == 'driver') ? '' : 'selected'; ?>>Passager (Réserver un taxi)</option>
                    <option value="driver" <?php echo (isset($_GET['role']) && $_GET['role'] == 'driver') ? 'selected' : ''; ?>>Chauffeur (Générer des revenus)</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold rounded-pill">S'inscrire</button>
            
            <div class="text-center mt-4 pt-3 border-top">
                <p class="mb-0 text-muted">Vous avez déjà un compte ? <a href="login.php" class="text-decoration-none fw-bold text-dark">Connectez-vous</a></p>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>