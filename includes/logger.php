<?php
// includes/logger.php — wrapper minimal pour Monolog avec fallback
if (session_status() === PHP_SESSION_NONE) session_start();

$logger = null;
if (class_exists('\Monolog\Logger')) {
    // Ensure log directory exists
    $logDir = __DIR__ . '/../storage/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    try {
        $logger = new \Monolog\Logger('taxi-gabon');
        // Use rotating file handler if available
        if (class_exists('\Monolog\Handler\RotatingFileHandler')) {
            $handler = new \Monolog\Handler\RotatingFileHandler($logDir . '/app.log', 7, \Monolog\Logger::DEBUG);
        } else {
            $handler = new \Monolog\Handler\StreamHandler($logDir . '/app.log', \Monolog\Logger::DEBUG);
        }
        $logger->pushHandler($handler);
    } catch (Exception $e) {
        // fallback
        $logger = null;
    }
}

if (!$logger) {
    // Simple fallback logger exposing same methods
    $logger = new class {
        public function error($msg) { error_log($msg); }
        public function warning($msg) { error_log($msg); }
        public function info($msg) { error_log($msg); }
        public function debug($msg) { error_log($msg); }
    };
}

function logger() {
    global $logger;
    return $logger;
}
