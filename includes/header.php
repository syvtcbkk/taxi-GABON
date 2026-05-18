<?php 
// Sécurisation du chemin d'inclusion de la base de données
require_once __DIR__ . '/db.php'; 
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taxi Gabon - Réservation et Transport Urbain</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top custom-navbar">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fa-solid fa-taxi"></i> Taxi Gabon</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Accueil</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#services">Services</a></li>
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['role'] === 'driver'): ?>
                            <li class="nav-item"><a class="nav-link text-warning fw-bold" href="dashboard-driver.php">Mon Espace Chauffeur</a></li>
                        <?php else: ?>
                            <li class="nav-item"><a class="nav-link text-warning fw-bold" href="dashboard-passenger.php">Mon Espace Passager</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="nav-link btn btn-outline-danger ms-2 px-3 text-white border-danger" href="actions/logout.php">Déconnexion</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link btn btn-outline-light ms-2 px-3" href="login.php">Connexion</a></li>
                        <li class="nav-item"><a class="nav-link btn btn-primary text-dark ms-2 px-3 fw-bold" href="register.php">Inscription</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container" style="margin-top: 90px; margin-bottom: 0;">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm border-0 mb-3" role="alert">
                <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm border-0 mb-3" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($_SESSION['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
    </div>