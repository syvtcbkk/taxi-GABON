<?php
// includes/db.php — Connexion PDO centralisée

$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$key, $val] = array_map('trim', explode('=', $line, 2));
        $_ENV[$key] = $val;
    }
}

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $host    = $_ENV['DB_HOST']    ?? 'localhost';
    $db      = $_ENV['DB_NAME']    ?? 'taxi_gabon';
    $user    = $_ENV['DB_USER']    ?? 'root';
    $pass    = $_ENV['DB_PASSWORD'] ?? '';
    $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}
