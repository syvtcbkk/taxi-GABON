<?php

// Autoloader simple basé sur les namespaces
spl_autoload_register(function ($class) {
    // Convertir les namespaces en chemins de fichiers
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($path)) {
        require_once $path;
    }
});

// Routeur basique
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Gérer le cas où l'app est lancée depuis un sous-dossier (ex: WAMP/XAMPP)
$basePath = '/PROJET_PHP/public';
if (strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}

if ($uri === '/' || $uri === '') {
    $controller = new \Controllers\DashboardController();
    $controller->index();
} elseif ($uri === '/vehicles') {
    $controller = new \Controllers\VehicleController();
    $controller->index();
} elseif ($uri === '/drivers') {
    $controller = new \Controllers\DriverController();
    $controller->index();
} elseif ($uri === '/trips') {
    $controller = new \Controllers\TripController();
    $controller->index();
} else {
    http_response_code(404);
    echo "<h1>404 - Page non trouvée</h1>";
}
