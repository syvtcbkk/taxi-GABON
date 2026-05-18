<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - TransportApp' : 'TransportApp' ?></title>
    <!-- On suppose que l'URL racine est /PROJET_PHP/public -->
    <link rel="stylesheet" href="/PROJET_PHP/public/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <h2>Transport<span>App</span></h2>
            </div>
            <nav class="nav-menu">
                <a href="/PROJET_PHP/public/" class="nav-link">Tableau de bord</a>
                <a href="/PROJET_PHP/public/vehicles" class="nav-link">Véhicules</a>
                <a href="/PROJET_PHP/public/drivers" class="nav-link">Chauffeurs</a>
                <a href="/PROJET_PHP/public/trips" class="nav-link">Trajets</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <h1><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard' ?></h1>
                <div class="user-profile">Administrateur</div>
            </header>
            
            <div class="content-wrapper">
                <?php 
                if (isset($content) && file_exists($content)) {
                    require_once $content;
                } else {
                    echo "<p>Contenu introuvable.</p>";
                }
                ?>
            </div>
        </main>
    </div>
</body>
</html>
