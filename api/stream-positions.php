<?php
// api/stream-positions.php — SSE stream des positions live
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';

// Nécessite un utilisateur connecté (passager ou chauffeur)
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit;
}

set_time_limit(0);
ignore_user_abort(true);
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

$pdo = getPDO();

function send_event($name, $data)
{
    echo "event: $name\n";
    echo 'data: ' . json_encode($data) . "\n\n";
    @ob_flush();
    @flush();
}

$lastData = null;
while (true) {
    // Récupérer toutes les positions récentes
    $stmt = $pdo->query('SELECT user_id, latitude AS lat, longitude AS lng, updated_at FROM live_positions');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows) {
        // Comparaison simple par JSON pour éviter d'émettre si inchangé
        $json = json_encode($rows);
        if ($json !== $lastData) {
            send_event('positions', $rows);
            $lastData = $json;
        }
    }

    // Keep-alive comment (prévenir timeouts)
    echo ":\n";
    @ob_flush(); @flush();

    // Sleep court
    sleep(3);

    // Déconnecté ? on stoppe
    if (connection_aborted()) break;
}

?>
