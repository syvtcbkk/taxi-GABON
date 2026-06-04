<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$sessionRole = $_SESSION['user_role'] ?? $_SESSION['role'] ?? null;
if ($sessionRole !== 'driver') {
    header('Location: dashboard-passenger.php');
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
}$weekTotal = array_sum($revenueValues);
$hasRevenueData = max($revenueValues) > 0;
//Courses en attente 
$pendingRides = $pdo->prepare('
    SELECT r.*, u.first_name, u.last_name, u.rating
    FROM rides r JOIN users u ON u.id = r.passenger_id
    WHERE r.status = "pending"
    ORDER BY r.created_at DESC LIMIT 5
');
$pendingRides->execute();
$pending = $pendingRides->fetchAll();

// Note moyenne du chauffeur
$ratingStmt = $pdo->prepare('SELECT rating FROM users WHERE id = ?');
$ratingStmt->execute([$userId]);
$driverRating = $ratingStmt->fetchColumn() ?: 5.00;

// ── Profil chauffeur 
$profileStmt = $pdo->prepare('SELECT * FROM driver_profiles WHERE user_id = ?');
$profileStmt->execute([$userId]);
$profile = $profileStmt->fetch();

$historyStmt = $pdo->prepare('SELECT r.*, u.first_name AS passenger_first, u.last_name AS passenger_last
    FROM rides r
    LEFT JOIN users u ON u.id = r.passenger_id
    WHERE r.driver_id = ? AND r.status = "completed"
    ORDER BY r.completed_at DESC LIMIT 5');
$historyStmt->execute([$userId]);
$driverHistory = $historyStmt->fetchAll();
?>

<link rel="stylesheet" href="assets/vendor/leaflet/leaflet.css" onerror="this.onerror=null; this.href='https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';" />
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

    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 dashboard-hero-card p-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3">
                    <div>
                        <h2 class="fw-bold mb-2" style="color:#1a1a2e;">Espace Chauffeur — <?= htmlspecialchars($_SESSION['user_name']) ?> 🚕</h2>
                        <p class="mb-3 text-muted">Suivez vos gains, votre disponibilité et les nouvelles demandes en un coup d'œil.</p>
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <?php
                                $availClass = !empty($profile['is_available']) ? 'bg-success' : 'bg-secondary';
                                $availIcon  = !empty($profile['is_available']) ? 'fa-circle-check' : 'fa-circle-xmark';
                                $availText  = !empty($profile['is_available']) ? 'En ligne' : 'Hors ligne';
                            ?>
                            <span class="badge <?= htmlspecialchars($availClass, ENT_QUOTES, 'UTF-8') ?> px-3 py-2 rounded-pill" id="statusBadge">
                                <i class="fa-solid <?= htmlspecialchars($availIcon, ENT_QUOTES, 'UTF-8') ?> me-1"></i>
                                <?= htmlspecialchars($availText, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <?php if ($profile && $profile['plate_number']): ?>
                                <span class="text-muted">
                                    <i class="fa-solid fa-car me-1 text-warning"></i>
                                    <strong><?= htmlspecialchars($profile['vehicle_brand'] . ' ' . $profile['vehicle_model']) ?></strong>
                                    <span class="badge bg-light text-dark border ms-1"><?= htmlspecialchars($profile['plate_number']) ?></span>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-lg-end">
                        <div class="bg-white p-3 rounded-4 shadow-sm d-inline-block">
                            <div class="form-check form-switch d-flex align-items-center mb-0">
                                <input class="form-check-input fs-4 cursor-pointer" type="checkbox" role="switch"
                                    id="statusSwitch" <?= !empty($profile['is_available']) ? 'checked' : '' ?> >
                                <label class="form-check-label ms-3 fw-bold text-dark cursor-pointer" for="statusSwitch">Disponible</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5 align-items-stretch">
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
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
                        <div>
                            <h5 class="fw-bold mb-1"><i class="fa-solid fa-chart-simple text-warning me-2"></i>Revenus — 7 derniers jours</h5>
                            <p class="text-muted mb-0">Vue des revenus de vos trajets validés sur les 7 derniers jours.</p>
                        </div>
                        <div class="text-md-end">
                            <span class="badge bg-warning text-dark rounded-pill px-3 py-2">
                                Total semaine : <?= number_format($weekTotal, 0, ',', ' ') ?> FCFA
                            </span>
                        </div>
                    </div>
                    <?php if ($hasRevenueData): ?>
                        <div class="revenue-chart-wrapper">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center p-5 no-data-chart rounded-4">
                            <div class="text-center">
                                <i class="fa-solid fa-chart-simple fa-2x text-warning mb-3"></i>
                                <h6 class="fw-bold mb-1">Aucun revenu cette semaine</h6>
                                <p class="text-muted mb-0">Commencez à accepter des courses pour voir votre graphique se remplir.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                <div class="card-body p-0">
                    <div id="driverMap" style="height:350px;width:100%;"></div>
                </div>
                <div class="card-footer border-0 bg-white py-3 text-center border-top">
                    <div id="geoStatus" class="text-muted small mb-1">Position synchronisée en arrière-plan</div>
                    <small class="text-muted"><i class="fa-solid fa-location-crosshairs text-warning me-1 animate-pulse"></i>
                        Géolocalisation en cours...</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h5 class="fw-bold mb-1"><i class="fa-solid fa-list-check text-warning me-2"></i>Historique des courses</h5>
                            <p class="text-muted mb-0">Vos dernières courses terminées.</p>
                        </div>
                    </div>
                    <?php if (empty($driverHistory)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fa-solid fa-folder-open mb-3 fa-2x text-muted"></i>
                            <p class="mb-0">Aucune course terminée pour le moment.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Passager</th>
                                        <th>Départ</th>
                                        <th>Destination</th>
                                        <th>Distance</th>
                                        <th>Prix</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($driverHistory as $ride): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($ride['passenger_first'] . ' ' . $ride['passenger_last']) ?></td>
                                            <td><?= htmlspecialchars($ride['origin_address']) ?></td>
                                            <td><?= htmlspecialchars($ride['dest_address']) ?></td>
                                            <td><?= htmlspecialchars($ride['distance_km']) ?> km</td>
                                            <td class="text-success fw-bold"><?= number_format($ride['price_fcfa'], 0, '.', ' ') ?> FCFA</td>
                                            <td class="text-muted small"><?= date('d/m/Y', strtotime($ride['completed_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <h4 class="fw-bold mb-0"><i class="fa-solid fa-bell text-warning me-2"></i>Nouvelles Demandes</h4>
                        <span id="pendingCountBadge" class="badge bg-danger rounded-pill px-3 py-2 animate-bounce"><?= (int)count($pending) ?> disponible(s)</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button id="refreshPendingBtn" class="btn btn-outline-secondary btn-sm fw-bold rounded-pill px-4 py-2">
                        <i class="fa-solid fa-arrows-rotate me-1"></i> Actualiser
                    </button>
                    <span id="pendingRefreshStatus" class="text-muted small">Mise à jour automatique toutes les 20s</span>
                </div>
            </div>

            <div id="pendingRidesContainer">
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
                    <div class="card border-0 shadow-sm rounded-4 mb-3 ride-card" id="ride-<?= (int)$ride['id'] ?>">
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
                                            <i class="fa-solid fa-route me-2 text-warning"></i>Distance : <strong><?= isset($ride['distance_km']) ? htmlspecialchars($ride['distance_km'], ENT_QUOTES, 'UTF-8') : '—' ?> km</strong>
                                        </div>
                                        <div class="col-sm-6 col-md-4">
                                            <i class="fa-solid fa-clock me-2 text-warning"></i>Temps : <strong>~<?= isset($ride['duration_min']) ? htmlspecialchars($ride['duration_min'], ENT_QUOTES, 'UTF-8') : '—' ?> min</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-md-end d-flex flex-column justify-content-between gap-3 border-top border-md-top-0 pt-3 pt-md-0">
                                        <div class="text-start text-md-end mb-0">
                                        <small class="text-muted d-block">Net à percevoir :</small>
                                        <span class="fs-4 fw-bold text-success price-badge"><?= number_format($ride['price_fcfa'], 0, '.', ' ') ?> <small class="fs-6">FCFA</small></span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 justify-content-end">
                                        <button class="btn btn-outline-secondary fw-bold rounded-pill px-4"
                                            onclick="respondRide(<?= (int)$ride['id'] ?>, 'cancel')">
                                            Refuser
                                        </button>
                                        <button class="btn btn-warning fw-bold rounded-pill px-4 shadow-sm text-dark"
                                            onclick="respondRide(<?= (int)$ride['id'] ?>, 'accept')">
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
</div>

<script src="assets/vendor/leaflet/leaflet.js" onerror="this.onerror=null; this.src='https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';"></script>

<script>
    // ── GRAPHIQUE REVENUS (Chart.js) ─────────────────────────────────────────────
    <?php if ($hasRevenueData): ?>
    const ctx = document.getElementById('revenueChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($revenueLabels) ?>,
            datasets: [{
                label: 'Revenus (FCFA)',
                data: <?= json_encode($revenueValues) ?>,
                backgroundColor: 'rgba(255,215,0,0.92)',
                borderColor: '#e6c300',
                borderWidth: 1.5,
                borderRadius: 8,
                maxBarThickness: 40,
                hoverBackgroundColor: '#ffb700'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: ctx => {
                            const value = ctx.parsed.y || 0;
                            return ' ' + value.toLocaleString('fr-FR') + ' FCFA';
                        }
                    }
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
                        callback: v => Number(v).toLocaleString('fr-FR') + ' F'
                    }
                }
            }
        }
    });
    <?php endif; ?>

    function escapeHTML(str) {
        return String(str).replace(/[&<>'"]/g, tag => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        })[tag] || tag);
    }

    // ── CARTE LEAFLET — Position du chauffeur en temps réel ──────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        const geoStatus = document.getElementById('geoStatus');

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
        if (!navigator.geolocation) {
            if (geoStatus) {
                geoStatus.textContent = 'Géolocalisation non supportée par ce navigateur.';
                geoStatus.classList.add('text-danger');
            }
            return;
        }

        if (geoStatus) {
            geoStatus.textContent = 'Géolocalisation en attente de permission...';
            geoStatus.classList.remove('text-danger');
            geoStatus.classList.remove('text-success');
        }

        navigator.geolocation.watchPosition(
            pos => {
                const {
                    latitude,
                    longitude
                } = pos.coords;
                updateDriverPosition(latitude, longitude);
                driverMap.setView([latitude, longitude], 15);
                if (geoStatus) {
                    geoStatus.textContent = 'Géolocalisation active — position mise à jour.';
                    geoStatus.classList.remove('text-danger');
                    geoStatus.classList.add('text-success');
                }

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
            err => {
                console.warn('Géolocalisation:', err.message);
                if (geoStatus) {
                    let message = 'Erreur de géolocalisation : ' + err.message;
                    if (err.code === 1) {
                        message = 'Permission de localisation refusée. Autorisez l’accès dans votre navigateur.';
                    } else if (err.code === 2) {
                        message = 'Position introuvable. Vérifiez le GPS ou la couverture réseau.';
                    } else if (err.code === 3) {
                        message = 'Délai de localisation dépassé. Réessayez.';
                    }
                    geoStatus.textContent = message;
                    geoStatus.classList.remove('text-success');
                    geoStatus.classList.add('text-danger');
                }
            }, {
                enableHighAccuracy: true,
                maximumAge: 10000
            }
        );
    }
    startTracking();
    });

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
            credentials: 'same-origin',
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
        const card = document.getElementById('ride-' + rideId);
        const buttons = card ? card.querySelectorAll('button') : [];
        buttons.forEach(btn => btn.disabled = true);

        fetch('api/respond-ride.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    ride_id: rideId,
                    action
                })
            })
            .then(async r => {
                const text = await r.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error(text || 'Réponse invalide du serveur');
                }
            })
            .then(data => {
                if (!data.success) {
                    throw new Error(data.error || 'Erreur serveur');
                }
                if (action === 'accept') {
                    window.location.href = 'ride-active.php?id=' + rideId;
                    return;
                }
                fetchPendingRides();
            })
            .catch(err => {
                buttons.forEach(btn => btn.disabled = false);
                alert('Erreur : ' + (err.message || 'Veuillez réessayer.'));
            });
    }

    function buildRideCard(ride) {
        const card = document.createElement('div');
        card.className = 'card border-0 shadow-sm rounded-4 mb-3 ride-card';
        card.id = 'ride-' + ride.id;

        const cardBody = document.createElement('div');
        cardBody.className = 'card-body p-4';

        const row = document.createElement('div');
        row.className = 'd-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3';

        const left = document.createElement('div');
        left.className = 'flex-grow-1';

        const top = document.createElement('div');
        top.className = 'd-flex align-items-center mb-3 flex-wrap gap-2';
        top.innerHTML = '<span class="badge bg-danger px-2 py-1">Nouveau</span>';

        const path = document.createElement('div');
        path.className = 'fw-bold text-dark fs-5';
        path.innerHTML = '<i class="fa-solid fa-location-dot text-success me-1"></i> ' + escapeHTML(ride.origin_address)
            + ' <i class="fa-solid fa-arrow-right-long text-muted mx-2"></i> '
            + ' <i class="fa-solid fa-location-dot text-danger me-1"></i> ' + escapeHTML(ride.dest_address);

        const infoRow = document.createElement('div');
        infoRow.className = 'row g-2 text-muted small';
        infoRow.innerHTML =
            '<div class="col-sm-6 col-md-4"><i class="fa-solid fa-user me-2 text-warning"></i>' + escapeHTML(ride.first_name + ' ' + ride.last_name) +
            ' <span class="text-warning fw-bold">(★ ' + Number(ride.rating).toFixed(1) + ')</span></div>' +
            '<div class="col-sm-6 col-md-4"><i class="fa-solid fa-route me-2 text-warning"></i>Distance : <strong>' + escapeHTML(ride.distance_km) + ' km</strong></div>' +
            '<div class="col-sm-6 col-md-4"><i class="fa-solid fa-clock me-2 text-warning"></i>Temps : <strong>~' + escapeHTML(ride.duration_min) + ' min</strong></div>';

        left.appendChild(top);
        left.appendChild(path);
        left.appendChild(infoRow);

        const right = document.createElement('div');
        right.className = 'text-md-end d-flex flex-column justify-content-between gap-3 border-top border-md-top-0 pt-3 pt-md-0';

        const price = document.createElement('div');
        price.className = 'text-start text-md-end mb-0';
        price.innerHTML = '<small class="text-muted d-block">Net à percevoir :</small>' +
            '<span class="fs-4 fw-bold text-success price-badge">' + Number(ride.price_fcfa).toLocaleString('fr-FR') + ' <small class="fs-6">FCFA</small></span>';

        const actions = document.createElement('div');
        actions.className = 'd-flex flex-wrap gap-2 justify-content-end';
        const cancelBtn = document.createElement('button');
        cancelBtn.className = 'btn btn-outline-secondary fw-bold rounded-pill px-4';
        cancelBtn.type = 'button';
        cancelBtn.textContent = 'Refuser';
        cancelBtn.addEventListener('click', () => respondRide(ride.id, 'cancel'));

        const acceptBtn = document.createElement('button');
        acceptBtn.className = 'btn btn-warning fw-bold rounded-pill px-4 shadow-sm text-dark';
        acceptBtn.type = 'button';
        acceptBtn.textContent = 'Accepter';
        acceptBtn.addEventListener('click', () => respondRide(ride.id, 'accept'));

        actions.appendChild(cancelBtn);
        actions.appendChild(acceptBtn);
        right.appendChild(price);
        right.appendChild(actions);

        row.appendChild(left);
        row.appendChild(right);
        cardBody.appendChild(row);
        card.appendChild(cardBody);

        return card;
    }

    function renderPendingRides(rides) {
        const container = document.getElementById('pendingRidesContainer');
        const countBadge = document.getElementById('pendingCountBadge');
        const status = document.getElementById('pendingRefreshStatus');

        if (!container || !countBadge || !status) return;

        container.innerHTML = '';
        countBadge.textContent = rides.length + ' disponible(s)';

        if (rides.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'card border-0 shadow-sm rounded-4 p-5 text-center text-muted';
            empty.innerHTML = '<div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width:70px;height:70px;">'
                + '<i class="fa-solid fa-hourglass-half fa-2x text-muted"></i></div>'
                + '<h5 class="fw-bold text-dark">En attente de clients...</h5>'
                + '<p class="mb-0 small">Les demandes à proximité s\'afficheront instantanément ici.</p>';
            container.appendChild(empty);
            return;
        }

        rides.forEach(ride => {
            container.appendChild(buildRideCard(ride));
        });
        status.textContent = 'Dernière mise à jour : ' + new Date().toLocaleTimeString('fr-FR');
    }

    async function fetchPendingRides() {
        const status = document.getElementById('pendingRefreshStatus');
        if (status) {
            status.textContent = 'Actualisation...';
        }
        try {
            const response = await fetch('api/pending-rides.php');
            const rides = await response.json();
            if (Array.isArray(rides)) {
                renderPendingRides(rides);
            }
        } catch (error) {
            if (status) {
                status.textContent = 'Erreur de mise à jour';
            }
            console.warn('Erreur rafraîchissement demandes :', error);
        }
    }

    document.getElementById('refreshPendingBtn').addEventListener('click', fetchPendingRides);
    setInterval(() => {
        if (!document.hidden) {
            fetchPendingRides();
        }
    }, 20000);
    fetchPendingRides();
</script>

<?php require_once 'includes/footer.php'; ?>