<?php
// 1. Démarrage sécurisé de la session si elle n'est pas déjà lancée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Fonction maison pour charger le fichier .env proprement
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignorer les commentaires
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Séparer la clé et la valeur
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Charger le fichier .env situé à la racine
loadEnv(__DIR__ . '/../.env');

// 3. Récupération des variables d'environnement
$host     = $_ENV['DB_HOST'] ?? 'localhost';
$dbname   = $_ENV['DB_NAME'] ?? '';
$user     = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASSWORD'] ?? '';
$charset  = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

// 4. Connexion à la base de données via PDO
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Active les erreurs SQL sous forme d'exceptions PHP
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retourne les données sous forme de tableaux associatifs
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Sécurité renforcée contre les injections SQL
];

try {
    $pdo = new PDO($dsn, $user, $password, $options);
} catch (\PDOException $e) {
    // En développement, on affiche l'erreur. En production, on cachera ça.
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}