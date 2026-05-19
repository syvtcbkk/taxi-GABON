<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'driver') {
    header('Location: login.php');
    exit;
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

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
    .card-kpi {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .card-kpi:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1) !important;
    }

    .ride-card {
        border-left: 5px solid #ffd700;
        transition: all 0.3s ease;
    }

    .ride-card:hover {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08) !important;
        background-color: #fffff8;
    }

    .form-check-input:checked {
        background-color: #ffd700;
        border-color: #ffd700;
    }
</style>

<div class="container py-5 mt-4">

    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h2 class="fw-bold" style="color:#1a1a2e;">
                Espace Chauffeur — <?= htmlspecialchars($_SESSION['user_name']) ?> 🚕
            </h2>
            <div class="d-flex align-items-center mt-2 flex-wrap gap-2">
                <span class="badge <?= $profile['is_available'] ? 'bg-success' : 'bg-secondary' ?> px-3 py-2 rounded-pill" id="statusBadge">
                    <i class="fa-solid <?= $profile['is_available'] ? 'fa-circle-check' : 'fa-circle-xmark' ?> me-1"></i>
                    <?= $profile['is_available'] ? 'En ligne' : 'Hors ligne' ?>
                </span>
                <?php if ($profile && $profile['plate_number']): ?>
                    <span class="text-muted ms-2">
                        <i class="fa-solid fa-car me-1 text-warning"></i>
                        <strong><?= htmlspecialchars($profile['vehicle_brand'] . ' ' . $profile['vehicle_model']) ?></strong>
                        <span class="badge bg-light text-dark border ms-1"><?= htmlspecialchars($profile['plate_number']) ?></span>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <div class="bg-light p-3 rounded-4 d-inline-block shadow-sm">
                <div class="form-check form-switch d-flex align-items-center mb-0 px-5">
                    <input class="form-check-input fs-4 cursor-pointer" type="checkbox" role="switch"
                        id="statusSwitch" <?= $profile['is_available'] ? 'checked' : '' ?>>
                    <label class="form-check-label ms-3 fw-bold text-dark cursor-pointer" for="statusSwitch">Disponible</label>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 text-dark card-kpi" style="background: linear-gradient(135deg, #ffd700, #ffb700);">
                <div class="card-body p-4 text-center">
                    <h5 class="fw-bold mb-1 opacity-75">Revenus du jour</h5>
                    <h2 class="display-5 fw-bold mb-0">
                        <?= number_format($todayStats['total_fcfa'], 0, ',', ' ') ?>
                        <small class="fs-5 fw-medium">FCFA</small>
                    </h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 card-kpi">
                <div class="card-body p-4 text-center">
                    <div class="rounded-circle bg-warning bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3"
                        style="width:60px;height:60px;">
                        <i class="fa-solid fa-route fa-2x text-warning"></i>
                    </div>
                    <h2 class="fw-bold mb-1"><?= (int)$todayStats['total_rides'] ?></h2>
                    <p class="text-muted mb-0">Courses aujourd'hui</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 card-kpi">
                <div class="card-body p-4 text-center">
                    <div class="rounded-circle bg-warning bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3"
                        style="width:60px;height:60px;">
                        <i class="fa-solid fa-star fa-2x text-warning"></i>
                    </div>
                    <h2 class="fw-bold mb-1"><?= number_format($driverRating, 1) ?> <span class="fs-5 text-muted">/ 5</span></h2>
                    <p class="text-muted mb-0">Note moyenne</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0"><i class="fa-solid fa-chart-simple text-warning me-2"></i>Revenus — 7 derniers jours</h5>
                    </div>
                    <canvas id="revenueChart" height="220"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                <div class="card-body p-0">
                    <div id="driverMap" style="height:350px;width:100%;"></div>
                </div>
                <div class="card-footer border-0 bg-white py-3 text-center border-top">
                    <small class="text-muted"><i class="fa-solid fa-location-crosshairs text-warning me-1 animate-pulse"></i>
                        Position synchronisée en arrière-plan</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h4 class="fw-bold mb-0"><i class="fa-solid fa-bell text-warning me-2"></i>Nouvelles Demandes</h4>
                <span class="badge bg-danger rounded-pill px-3 py-2 animate-bounce"><?= count($pending) ?> disponible(s)</span>
            </div>

            <?php if (empty($pending)): ?>
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center text-muted">
                    <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width:70px;height:70px;">
                        <i class="fa-solid fa-hourglass-half fa-2x text-muted"></i>
                    </div>
                    <h5 class="fw-bold text-dark">En attente de clients...</h5>
                    <p class="mb-0 small">Les demandes à proximité s'afficheront instantanément ici.</p>
                </div>
            <?php else: ?>
                <?php foreach ($pending as $ride): ?>
                    <div class="card border-0 shadow-sm rounded-4 mb-3 ride-card" id="ride-<?= $ride['id'] ?>">
                        <div class="card-body p-4">
                            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-3 flex-wrap gap-2">
                                        <span class="badge bg-danger px-2 py-1">Nouveau</span>
                                        <div class="fw-bold text-dark fs-5">
                                            <i class="fa-solid fa-location-dot text-success me-1"></i> <?= htmlspecialchars($ride['origin_address']) ?>
                                            <i class="fa-solid fa-arrow-right-long text-muted mx-2"></i>
                                            <i class="fa-solid fa-location-dot text-danger me-1"></i> <?= htmlspecialchars($ride['dest_address']) ?>
                                        </div>
                                    </div>
                                    <div class="row g-2 text-muted small">
                                        <div class="col-sm-6 col-md-4">
                                            <i class="fa-solid fa-user me-2 text-warning"></i>
                                            <?= htmlspecialchars($ride['first_name'] . ' ' . $ride['last_name']) ?>
                                            <span class="text-warning fw-bold">(★ <?= number_format($ride['rating'], 1) ?>)</span>
                                        </div>
                                        <div class="col-sm-6 col-md-4">
                                            <i class="fa-solid fa-route me-2 text-warning"></i>Distance : <strong><?= $ride['distance_km'] ?> km</strong>
                                        </div>
                                        <div class="col-sm-6 col-md-4">
                                            <i class="fa-solid fa-clock me-2 text-warning"></i>Temps : <strong>~<?= $ride['duration_min'] ?> min</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-md-end d-flex flex-row flex-md-column justify-content-between align-items-end gap-2 border-top border-md-top-0 pt-3 pt-md-0">
                                    <div class="text-start text-md-end mb-md-2">
                                        <small class="text-muted d-block">Net à percevoir :</small>
                                        <span class="fs-4 fw-bold text-success"><?= number_format($ride['price_fcfa'], 0, '.', ' ') ?> <small class="fs-6">FCFA</small></span>
                                    </div>
                                    <div class="d-flex gap-2 w-100 justify-content-end">
                                        <button class="btn btn-light border fw-bold rounded-pill px-4"
                                            onclick="respondRide(<?= $ride['id'] ?>, 'cancel')">
                                            Refuser
                                        </button>
                                        <button class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm"
                                            onclick="respondRide(<?= $ride['id'] ?>, 'accept')">
                                            Accepter
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    // ── GRAPHIQUE REVENUS (Chart.js) ─────────────────────────────────────────────
    const ctx = document.getElementById('revenueChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($revenueLabels) ?>,
            datasets: [{
                label: 'Revenus (FCFA)',
                data: <?= json_encode($revenueValues) ?>,
                backgroundColor: 'rgba(255,215,0,0.85)',
                borderColor: '#e6c300',
                borderWidth: 1.5,
                borderRadius: 6,
                hoverBackgroundColor: '#ffb700'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: v => v.toLocaleString('fr-FR') + ' F'
                    }
                }
            }
        }
    });

    // ── CARTE LEAFLET — Position du chauffeur en temps réel ──────────────────────
    const driverMap = L.map('driverMap', {
        zoomControl: false
    }).setView([0.3924, 9.4536], 13);
    L.control.zoom({
        position: 'bottomright'
    }).addTo(driverMap);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(driverMap);

    const driverIcon = L.divIcon({
        html: '<div style="background:#ffd700;border:3px solid #1a1a2e;box-shadow:0 0 10px rgba(0,0,0,0.3);border-radius:50%;width:34px;height:34px;display:flex;align-items:center;justify-content:center;font-size:18px;">🚕</div>',
        className: '',
        iconSize: [34, 34],
        iconAnchor: [17, 17]
    });

    let driverMarker = null;

    function updateDriverPosition(lat, lng) {
        if (!driverMarker) {
            driverMarker = L.marker([lat, lng], {
                icon: driverIcon
            }).addTo(driverMap).bindPopup('<b>Votre position actuelle</b>');
        } else {
            driverMarker.setLatLng([lat, lng]);
        }
    }

    function startTracking() {
        if (!navigator.geolocation) return;
        navigator.geolocation.watchPosition(
            pos => {
                const {
                    latitude,
                    longitude
                } = pos.coords;
                updateDriverPosition(latitude, longitude);
                driverMap.setView([latitude, longitude], 15);

                fetch('api/update-position.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        lat: latitude,
                        lng: longitude
                    })
                }).catch(() => {});
            },
            err => console.warn('Géolocalisation:', err.message), {
                enableHighAccuracy: true,
                maximumAge: 10000
            }
        );
    }
    startTracking();

    // ── SWITCH DISPONIBILITÉ ─────────────────────────────────────────────────────
    document.getElementById('statusSwitch').addEventListener('change', function() {
        const available = this.checked ? 1 : 0;
        const badge = document.getElementById('statusBadge');
        if (available) {
            badge.className = 'badge bg-success px-3 py-2 rounded-pill';
            badge.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i> En ligne';
        } else {
            badge.className = 'badge bg-secondary px-3 py-2 rounded-pill';
            badge.innerHTML = '<i class="fa-solid fa-circle-xmark me-1"></i> Hors ligne';
        }
        fetch('api/update-status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                available
            })
        }).catch(() => {});
    });

    // ── ACCEPTER / REFUSER UNE COURSE ────────────────────────────────────────────
    function respondRide(rideId, action) {
        fetch('api/respond-ride.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    ride_id: rideId,
                    action
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const card = document.getElementById('ride-' + rideId);
                    if (card) {
                        card.style.transform = 'scale(0.9)';
                        card.style.opacity = '0';
                        setTimeout(() => card.remove(), 300);
                    }
                    if (action === 'accept') {
                        window.location.href = 'ride-active.php?id=' + rideId;
                    }
                }
            })
            .catch(() => alert('Erreur réseau, réessayez.'));
    }
</script>

<?php require_once 'includes/footer.php'; ?>