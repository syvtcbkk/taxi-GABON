<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'driver') {
    header('Location: login.php'); exit;
}
require_once 'includes/db.php';
require_once 'includes/header.php';

$pdo    = getPDO();
$userId = $_SESSION['user_id'];

// ── Stats du jour ─────────────────────────────────────────────────────────────
$today = date('Y-m-d');
$stmt  = $pdo->prepare('
    SELECT COUNT(*) as total_rides, COALESCE(SUM(price_fcfa),0) as total_fcfa
    FROM rides WHERE driver_id = ? AND DATE(completed_at) = ? AND status = "completed"
');
$stmt->execute([$userId, $today]);
$todayStats = $stmt->fetch();

// ── Revenus des 7 derniers jours (pour le graphique) ─────────────────────────
$stmt7 = $pdo->prepare('
    SELECT DATE(completed_at) as day, COALESCE(SUM(price_fcfa),0) as total
    FROM rides
    WHERE driver_id = ? AND status = "completed"
      AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(completed_at) ORDER BY day
');
$stmt7->execute([$userId]);
$weekData = $stmt7->fetchAll();

// Remplir les jours manquants
$revenueLabels = [];
$revenueValues = [];
$weekMap = array_column($weekData, 'total', 'day');
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $revenueLabels[] = date('D d/m', strtotime($d));
    $revenueValues[] = (int)($weekMap[$d] ?? 0);
}

// ── Courses en attente ────────────────────────────────────────────────────────
$pendingRides = $pdo->prepare('
    SELECT r.*, u.first_name, u.last_name, u.rating
    FROM rides r JOIN users u ON u.id = r.passenger_id
    WHERE r.status = "pending"
    ORDER BY r.created_at DESC LIMIT 5
');
$pendingRides->execute();
$pending = $pendingRides->fetchAll();

// ── Note moyenne du chauffeur ─────────────────────────────────────────────────
$ratingStmt = $pdo->prepare('SELECT rating FROM users WHERE id = ?');
$ratingStmt->execute([$userId]);
$driverRating = $ratingStmt->fetchColumn() ?: 5.00;

// ── Profil chauffeur ──────────────────────────────────────────────────────────
$profileStmt = $pdo->prepare('SELECT * FROM driver_profiles WHERE user_id = ?');
$profileStmt->execute([$userId]);
$profile = $profileStmt->fetch();
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="container py-5 mt-4">

    <!-- ── En-tête ─────────────────────────────────────────────────────────── -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h2 class="fw-bold" style="color:#1a1a2e;">
                Espace Chauffeur — <?= htmlspecialchars($_SESSION['user_name']) ?> 🚕
            </h2>
            <div class="d-flex align-items-center mt-2 flex-wrap gap-2">
                <span class="badge bg-success px-3 py-2 rounded-pill" id="statusBadge">
                    <i class="fa-solid fa-circle-check me-1"></i> En ligne
                </span>
                <?php if ($profile && $profile['plate_number']): ?>
                <span class="text-muted">
                    <i class="fa-solid fa-car me-1"></i>
                    <?= htmlspecialchars($profile['vehicle_brand'].' '.$profile['vehicle_model']) ?>
                    (<?= htmlspecialchars($profile['plate_number']) ?>)
                </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <div class="form-check form-switch d-inline-block">
                <input class="form-check-input fs-4" type="checkbox" role="switch"
                       id="statusSwitch" <?= $profile['is_available'] ? 'checked' : '' ?>>
                <label class="form-check-label ms-2 fw-bold" for="statusSwitch">Disponible</label>
            </div>
        </div>
    </div>

    <!-- ── KPIs ────────────────────────────────────────────────────────────── -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 text-dark" style="background:#ffd700;">
                <div class="card-body p-4 text-center">
                    <h5 class="fw-bold mb-1">Revenus du jour</h5>
                    <h2 class="display-5 fw-bold mb-0">
                        <?= number_format($todayStats['total_fcfa'], 0, ',', ' ') ?>
                        <small class="fs-5">FCFA</small>
                    </h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 text-center">
                    <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3"
                         style="width:60px;height:60px;">
                        <i class="fa-solid fa-route fa-2x text-warning"></i>
                    </div>
                    <h3 class="fw-bold"><?= (int)$todayStats['total_rides'] ?></h3>
                    <p class="text-muted mb-0">Courses aujourd'hui</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 text-center">
                    <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3"
                         style="width:60px;height:60px;">
                        <i class="fa-solid fa-star fa-2x text-warning"></i>
                    </div>
                    <h3 class="fw-bold"><?= number_format($driverRating, 1) ?> <span class="fs-5 text-muted">/ 5</span></h3>
                    <p class="text-muted mb-0">Note moyenne</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Graphique + Carte ────────────────────────────────────────────────── -->
    <div class="row g-4 mb-5">
        <!-- Graphique revenus 7 jours -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4">Revenus — 7 derniers jours</h5>
                    <canvas id="revenueChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <!-- Carte temps réel -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                <div class="card-body p-0">
                    <div id="driverMap" style="height:340px;width:100%;border-radius:1rem;"></div>
                </div>
                <div class="card-footer border-0 bg-white p-3 text-center">
                    <small class="text-muted"><i class="fa-solid fa-location-crosshairs me-1"></i>
                        Position mise à jour toutes les 10 s</small>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Courses en attente ───────────────────────────────────────────────── -->
    <div class="row">
        <div class="col-12">
            <h4 class="fw-bold mb-4">Nouvelles Demandes</h4>

            <?php if (empty($pending)): ?>
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center text-muted">
                    <i class="fa-solid fa-hourglass-half fa-2x mb-3"></i>
                    <p class="mb-0">Aucune demande en attente pour le moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($pending as $ride): ?>
                <div class="card border-0 shadow-sm rounded-4 mb-3" id="ride-<?= $ride['id'] ?>">
                    <div class="card-body p-4">
                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                            <div>
                                <div class="d-flex align-items-center mb-2 flex-wrap gap-2">
                                    <span class="badge bg-danger">Nouveau</span>
                                    <h6 class="fw-bold mb-0">
                                        <?= htmlspecialchars($ride['origin_address']) ?>
                                        ➔ <?= htmlspecialchars($ride['dest_address']) ?>
                                    </h6>
                                </div>
                                <p class="text-muted small mb-1">
                                    <i class="fa-solid fa-user me-2"></i>
                                    <?= htmlspecialchars($ride['first_name'].' '.$ride['last_name']) ?>
                                    (Note: <?= number_format($ride['rating'],1) ?>)
                                </p>
                                <p class="text-muted small mb-0">
                                    <i class="fa-solid fa-coins me-2"></i>
                                    Prix estimé: <?= number_format($ride['price_fcfa'],0,'.',' ') ?> FCFA
                                    • Distance: <?= $ride['distance_km'] ?> km
                                    • ~<?= $ride['duration_min'] ?> min
                                </p>
                            </div>
                            <div class="mt-3 mt-md-0 d-flex gap-2">
                                <button class="btn btn-outline-danger fw-bold rounded-pill px-4"
                                        onclick="respondRide(<?= $ride['id'] ?>, 'cancel')">
                                    Refuser
                                </button>
                                <button class="btn btn-primary fw-bold rounded-pill px-4"
                                        onclick="respondRide(<?= $ride['id'] ?>, 'accept')">
                                    Accepter
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// ══════════════════════════════════════════════════════════════
//  GRAPHIQUE REVENUS (Chart.js)
// ══════════════════════════════════════════════════════════════
const ctx = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($revenueLabels) ?>,
        datasets: [{
            label: 'FCFA',
            data: <?= json_encode($revenueValues) ?>,
            backgroundColor: 'rgba(255,215,0,0.75)',
            borderColor: '#e6c300',
            borderWidth: 2,
            borderRadius: 8,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: v => v.toLocaleString('fr-FR') + ' F'
                }
            }
        }
    }
});

// ══════════════════════════════════════════════════════════════
//  CARTE LEAFLET — Position du chauffeur en temps réel
// ══════════════════════════════════════════════════════════════
const driverMap = L.map('driverMap').setView([0.3924, 9.4536], 13); // Libreville par défaut

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/">OpenStreetMap</a>',
    maxZoom: 19
}).addTo(driverMap);

const driverIcon = L.divIcon({
    html: '<div style="background:#ffd700;border:3px solid #1a1a2e;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;font-size:18px;">🚕</div>',
    className: '',
    iconSize: [32, 32],
    iconAnchor: [16, 16]
});

let driverMarker = null;

function updateDriverPosition(lat, lng) {
    if (!driverMarker) {
        driverMarker = L.marker([lat, lng], { icon: driverIcon })
            .addTo(driverMap)
            .bindPopup('<b>Votre position</b>');
    } else {
        driverMarker.setLatLng([lat, lng]);
    }
    // Envoyer la position au serveur
    fetch('api/update-position.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lat, lng })
    }).catch(() => {});
}

function startTracking() {
    if (!navigator.geolocation) return;
    navigator.geolocation.watchPosition(
        pos => {
            updateDriverPosition(pos.coords.latitude, pos.coords.longitude);
            driverMap.setView([pos.coords.latitude, pos.coords.longitude], 15);
        },
        err => console.warn('Géolocalisation:', err.message),
        { enableHighAccuracy: true, maximumAge: 10000 }
    );
}
startTracking();

// ══════════════════════════════════════════════════════════════
//  SWITCH DISPONIBILITÉ
// ══════════════════════════════════════════════════════════════
document.getElementById('statusSwitch').addEventListener('change', function() {
    const available = this.checked ? 1 : 0;
    document.getElementById('statusBadge').className =
        available ? 'badge bg-success px-3 py-2 rounded-pill' : 'badge bg-secondary px-3 py-2 rounded-pill';
    document.getElementById('statusBadge').innerHTML =
        available ? '<i class="fa-solid fa-circle-check me-1"></i> En ligne'
                  : '<i class="fa-solid fa-circle-xmark me-1"></i> Hors ligne';
    fetch('api/update-status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ available })
    }).catch(() => {});
});

// ══════════════════════════════════════════════════════════════
//  ACCEPTER / REFUSER UNE COURSE
// ══════════════════════════════════════════════════════════════
function respondRide(rideId, action) {
    fetch('api/respond-ride.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ride_id: rideId, action })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const card = document.getElementById('ride-' + rideId);
            if (card) {
                card.style.transition = 'opacity .4s';
                card.style.opacity = '0';
                setTimeout(() => card.remove(), 400);
            }
            if (action === 'accept') {
                window.location.href = 'ride-active.php?id=' + rideId;
            }
        }
    })
    .catch(() => alert('Erreur réseau, réessayez.'));
}

// ══════════════════════════════════════════════════════════════
//  POLLING — nouvelles demandes toutes les 15 s
// ══════════════════════════════════════════════════════════════
setInterval(() => {
    fetch('api/pending-rides.php')
        .then(r => r.json())
        .then(rides => {
            // Mise à jour UI si nécessaire (implémentation complète via WebSocket en prod)
        })
        .catch(() => {});
}, 15000);
</script>

<?php require_once 'includes/footer.php'; ?>
