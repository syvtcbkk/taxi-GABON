<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/header.php';

// Si pas d'e-mail en session, retourner en arrière
if (empty($_SESSION['reset_email']) && !isset($_SESSION['success'])) {
    header('Location: forgot-password.php');
    exit;
}
?>

<div class="container">
    <div class="auth-container">
        <div class="text-center mb-4">
            <i class="fa-solid fa-lock-open fa-3x text-warning mb-3"></i>
            <h2 class="auth-title mb-0">Nouveau mot de passe</h2>
            <p class="text-muted mt-2">Saisissez le code reçu par e-mail et choisissez un nouveau mot de passe.</p>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger text-center small rounded-3 py-2"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success text-center small rounded-3 py-2"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <form action="actions/auth-reset.php" method="POST">
            <?php echo csrf_input(); ?>
            <!-- Saisie du code à 6 chiffres -->
            <div class="mb-4">
                <label class="form-label fw-bold text-center d-block">Code de récupération</label>
                <div class="d-flex justify-content-center gap-2" id="codeInputs">
                    <?php for ($i = 0; $i < 6; $i++): ?>
                        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]"
                            class="form-control form-control-lg text-center fw-bold fs-4 code-digit"
                            style="width:52px;height:60px;border-radius:10px;"
                            autocomplete="off">
                    <?php endfor; ?>
                </div>
                <!-- Champ caché qui regroupe les 6 chiffres -->
                <input type="hidden" name="code" id="codeHidden">
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Nouveau mot de passe</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="password" id="password"
                        class="form-control form-control-lg" placeholder="••••••••" required minlength="8">
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-bold">Confirmer le mot de passe</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="confirm" id="confirm"
                        class="form-control form-control-lg" placeholder="••••••••" required>
                </div>
                <div id="matchWarning" class="text-danger small mt-1 d-none">Les mots de passe ne correspondent pas.</div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold rounded-pill" id="submitBtn">
                Réinitialiser le mot de passe
            </button>
            <div class="text-center mt-3">
                <a href="forgot-password.php" class="text-muted small text-decoration-none">
                    <i class="fa-solid fa-rotate-left me-1"></i>Renvoyer un code
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    // ── Gestion des 6 cases du code ───────────────────────────────────────────────
    const digits = document.querySelectorAll('.code-digit');
    const hidden = document.getElementById('codeHidden');

    digits.forEach((input, i) => {
        input.addEventListener('input', () => {
            input.value = input.value.replace(/\D/, '');
            if (input.value && i < 5) digits[i + 1].focus();
            syncCode();
        });
        input.addEventListener('keydown', e => {
            if (e.key === 'Backspace' && !input.value && i > 0) {
                digits[i - 1].focus();
            }
        });
        // Coller tout le code dans la 1ère case
        input.addEventListener('paste', e => {
            const pasted = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
            if (pasted.length === 6) {
                e.preventDefault();
                pasted.split('').forEach((c, j) => {
                    digits[j].value = c;
                });
                digits[5].focus();
                syncCode();
            }
        });
    });

    function syncCode() {
        hidden.value = [...digits].map(d => d.value).join('');
    }

    // ── Vérification correspondance mdp ──────────────────────────────────────────
    const pw = document.getElementById('password');
    const conf = document.getElementById('confirm');
    const warn = document.getElementById('matchWarning');

    [pw, conf].forEach(el => el.addEventListener('input', () => {
        warn.classList.toggle('d-none', pw.value === conf.value || !conf.value);
    }));

    // ── Pré-remplissage du code depuis submit ─────────────────────────────────────
    document.querySelector('form').addEventListener('submit', e => {
        syncCode();
        if (hidden.value.length < 6) {
            e.preventDefault();
            digits.forEach(d => d.classList.add('is-invalid'));
            alert('Veuillez saisir les 6 chiffres du code.');
        }
        if (pw.value !== conf.value) {
            e.preventDefault();
            warn.classList.remove('d-none');
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>