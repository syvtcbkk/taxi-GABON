<?php
// includes/db.php

// Chargement des variables d'environnement si tu utilises un fichier .env
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (class_exists('Dotenv\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->safeLoad();
    }
}

// Variables de connexion de secours ou basées sur ton fichier .env (vu dans ton VS Code)
$host    = $_ENV['DB_HOST']     ?? 'localhost';
$dbname  = $_ENV['DB_NAME']     ?? 'taxi_gabon';
$user    = $_ENV['DB_USER']     ?? 'root';
$pass    = $_ENV['DB_PASSWORD'] ?? '';
$charset = $_ENV['DB_CHARSET']  ?? 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // On initialise la variable GLOBALE $pdo
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

function getPDO()
{
    global $pdo;
    if (!isset($pdo)) {
        throw new RuntimeException('Connexion PDO non initialisée.');
    }
    return $pdo;
}