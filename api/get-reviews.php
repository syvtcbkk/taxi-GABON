<?php
// api/get-reviews.php — retourne les avis pour un utilisateur (chauffeur/passager)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

$driverId = isset($_GET['driver_id']) ? (int)$_GET['driver_id'] : 0;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? min(100, max(1, (int)$_GET['per_page'])) : 20;
$offset = ($page - 1) * $per_page;

$min_rating = isset($_GET['min_rating']) ? (int)$_GET['min_rating'] : 0;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null; // YYYY-MM-DD
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null; // YYYY-MM-DD

if ($driverId <= 0) {
    echo json_encode(['success' => false, 'error' => 'driver_id missing']); exit;
}

$pdo = getPDO();
$params = [$driverId];
$where = ' WHERE r.to_user_id = ? ';
if ($min_rating > 0) { $where .= ' AND r.rating >= ? '; $params[] = $min_rating; }
if ($start_date) { $where .= ' AND r.created_at >= ? '; $params[] = $start_date . ' 00:00:00'; }
if ($end_date) { $where .= ' AND r.created_at <= ? '; $params[] = $end_date . ' 23:59:59'; }

$sql = 'SELECT r.id, r.ride_id, r.from_user_id, r.to_user_id, r.rating, r.comment, r.created_at, u.first_name as from_first, u.last_name as from_last'
    . ' FROM reviews r LEFT JOIN users u ON u.id = r.from_user_id '
    . $where
    . ' ORDER BY r.created_at DESC '
    . ' LIMIT ? OFFSET ?';
$params[] = $per_page; $params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total count for pagination (apply same filters)
$cntParams = [$driverId];
$cntWhere = ' WHERE to_user_id = ? ';
if ($min_rating > 0) { $cntWhere .= ' AND rating >= ? '; $cntParams[] = $min_rating; }
if ($start_date) { $cntWhere .= ' AND created_at >= ? '; $cntParams[] = $start_date . ' 00:00:00'; }
if ($end_date) { $cntWhere .= ' AND created_at <= ? '; $cntParams[] = $end_date . ' 23:59:59'; }
$cntSql = 'SELECT COUNT(*) as total, AVG(rating) as avg_rating FROM reviews ' . $cntWhere;
$avg = $pdo->prepare($cntSql);
$avg->execute($cntParams);
$s = $avg->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'reviews' => $rows,
    'avg_rating' => $s['avg_rating'] ? round((float)$s['avg_rating'],2) : null,
    'total' => (int)($s['total'] ?? 0),
    'page' => $page,
    'per_page' => $per_page
]);
