<?php require_once 'includes/header.php'; ?>

<section class="hero-section">
    <div class="container">
        <h1 class="hero-title">Déplacez-vous en toute sérénité</h1>
        <p class="hero-subtitle">Réservez votre taxi à Libreville et Port-Gentil en quelques clics. Sécurité, rapidité et confort garantis.</p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="register.php" class="btn btn-primary btn-lg fw-bold px-4 py-3 rounded-pill shadow-sm">Réserver maintenant</a>
            <a href="register.php?role=driver" class="btn btn-outline-light btn-lg px-4 py-3 rounded-pill">Devenir Chauffeur</a>
        </div>
    </div>
</section>

<section id="services" class="py-5 bg-white">
    <div class="container py-5">
        <div class="text-center mb-5">
            <span class="text-warning fw-bold text-uppercase tracking-wider">Pourquoi nous choisir</span>
            <h2 class="mt-2 fw-bold" style="color: #1a1a2e;">Nos Avantages Exclusifs</h2>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-card">
                    <i class="fa-solid fa-map-location-dot feature-icon"></i>
                    <h4 class="fw-bold mb-3">Suivi en Temps Réel</h4>
                    <p class="text-muted">Suivez l'arrivée de votre taxi sur la carte en temps réel grâce à notre intégration API de géolocalisation de pointe.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <i class="fa-solid fa-shield-halved feature-icon"></i>
                    <h4 class="fw-bold mb-3">Sécurité Maximale</h4>
                    <p class="text-muted">Tous nos chauffeurs sont rigoureusement vérifiés. Partagez les détails de votre course avec vos proches en un clic.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <i class="fa-solid fa-wallet feature-icon"></i>
                    <h4 class="fw-bold mb-3">Revenus Numériques</h4>
                    <p class="text-muted">Pour les chauffeurs, gérez vos courses et maximisez vos revenus directement depuis votre tableau de bord dédié.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5" style="background-color: #f8f9fa;">
    <div class="container py-4">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <img src="https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Taxi Driver" class="img-fluid rounded-4 shadow-lg">
            </div>
            <div class="col-lg-6 px-lg-5">
                <h2 class="fw-bold mb-4" style="color: #1a1a2e;">Rejoignez la révolution du transport au Gabon</h2>
                <p class="lead text-muted mb-4">Que vous soyez passager cherchant un trajet sûr ou chauffeur souhaitant augmenter ses revenus, Taxi Gabon est la plateforme qu'il vous faut.</p>
                <ul class="list-unstyled mb-4">
                    <li class="mb-3"><i class="fa-solid fa-check text-warning me-2"></i> Application facile à utiliser</li>
                    <li class="mb-3"><i class="fa-solid fa-check text-warning me-2"></i> Tarification transparente</li>
                    <li class="mb-3"><i class="fa-solid fa-check text-warning me-2"></i> Support client 24/7</li>
                </ul>
                <a href="register.php" class="btn btn-primary btn-lg fw-bold px-4 rounded-pill">Commencer l'aventure</a>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
