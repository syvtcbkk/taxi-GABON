<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$sessionRole = $_SESSION['user_role'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $sessionRole !== 'driver') {
    header('Location: login.php');
    exit;
}
require_once 'includes/db.php';
require_once 'includes/header.php';

$pdo    = getPDO();
$userId = $_SESSION['user_id'];
$rideId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('
    SELECT r.*,
           u.first_name AS passenger_first, u.last_name AS passenger_last,
           u.phone AS passenger_phone, u.rating AS passenger_rating
    FROM rides r
    JOIN users u ON u.id = r.passenger_id
    WHERE r.id = ? AND r.driver_id = ? AND r.status IN ("accepted","in_progress")
    LIMIT 1
');
$stmt->execute([$rideId, $userId]);
$ride = $stmt->fetch();

if (!$ride) {
    header('Location: dashboard-driver.php');
    exit;
}
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    .info-card { border-left: 5px solid #ffd700; }
    #rideMap { height: 380px; border-radius: 1rem; }
</style>

<div class="container py-4 mt-4">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="dashboard-driver.php" class="btn btn-light border rounded-circle p-2">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h4 class="fw-bold mb-0" style="color:#1a1a2e;">Course en cours</h4>
            <small class="text-muted">Course #<?= $rideId ?></small>
        </div>
        <span class="badge ms-auto px-3 py-2 rounded-pill <?= $ride['status'] === 'accepted' ? 'bg-warning text-dark' : 'bg-success text-white' ?>">
            <?= $ride['status'] === 'accepted' ? 'En route vers le client' : 'Trajet en cours' ?>
        </span>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 mb-4 info-card">
                <div class="card-body p-4">
                    <h6 class="text-muted text-uppercase fw-bold small mb-3">Passager</h6>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-circle bg-warning bg-opacity-20 d-flex align-items-center justify-content-center" style="width:50px;height:50px;">
                            <i class="fa-solid fa-user fa-lg text-warning"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0"><?= htmlspecialchars($ride['passenger_first'] . ' ' . $ride['passenger_last']) ?></h5>
                            <span class="text-warning fw-bold"><i class="fa-solid fa-star me-1"></i><?= number_format($ride['passenger_rating'], 1) ?></span>
                        </div>
                        <a href="tel:<?= htmlspecialchars($ride['passenger_phone']) ?>" class="btn btn-light border rounded-circle ms-auto p-2">
                            <i class="fa-solid fa-phone text-success"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h6 class="text-muted text-uppercase fw-bold small mb-3">Trajet</h6>
                    <div class="d-flex gap-3 mb-3">
                        <div class="d-flex flex-column align-items-center">
                            <i class="fa-solid fa-circle text-success"></i>
                            <div style="width:2px;height:30px;background:#dee2e6;margin:4px 0;"></div>
                            <i class="fa-solid fa-location-dot text-danger"></i>
                        </div>
                        <div class="flex-grow-1">
                            <p class="fw-bold mb-1 text-dark"><?= htmlspecialchars($ride['origin_address']) ?></p>
                            <p class="fw-bold mb-0 text-dark"><?= htmlspecialchars($ride['dest_address']) ?></p>
                        </div>
                    </div>
                    <hr>
                    <div class="row text-center g-2">
                        <div class="col-4">
                            <div class="fw-bold text-dark"><?= $ride['distance_km'] ?? '—' ?> km</div>
                            <small class="text-muted">Distance</small>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold text-dark"><?= $ride['duration_min'] ?? '—' ?> min</div>
                            <small class="text-muted">Durée</small>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold text-success price-badge"><?= number_format($ride['price_fcfa'], 0, '.', ' ') ?> F</div>
                            <small class="text-muted">Prix</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-3">
                <?php if ($ride['status'] === 'accepted'): ?>
                    <button class="btn fw-bold rounded-pill py-3 text-dark" style="background:#ffd700;border-color:#ffd700;" onclick="updateStatus('in_progress')">
                        <i class="fa-solid fa-play me-2"></i>Client embarqué — Démarrer
                    </button>
                <?php else: ?>
                    <button class="btn btn-success fw-bold rounded-pill py-3" onclick="updateStatus('completed')">
                        <i class="fa-solid fa-flag-checkered me-2"></i>Terminer la course
                    </button>
                <?php endif; ?>
                <button class="btn btn-outline-danger fw-bold rounded-pill py-2" onclick="updateStatus('cancel')">
                    <i class="fa-solid fa-xmark me-2"></i>Annuler
                </button>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div id="rideMap"></div>
                <div class="card-footer bg-white border-0 py-3 text-center">
                    <small class="text-muted"><i class="fa-solid fa-location-crosshairs text-warning me-1"></i>Position mise à jour en temps réel</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const map = L.map('rideMap').setView([<?= (float)($ride['origin_lat'] ?? 0.3924) ?>, <?= (float)($ride['origin_lng'] ?? 9.4536) ?>], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);

    <?php if ($ride['dest_lat'] && $ride['dest_lng']): ?>
    L.marker([<?= (float)$ride['dest_lat'] ?>, <?= (float)$ride['dest_lng'] ?>])
        .addTo(map).bindPopup('<b>Destination</b><br><?= htmlspecialchars($ride['dest_address']) ?>');
    <?php endif; ?>

    const driverIcon = L.divIcon({
        html: '<div style="background:#ffd700;border:3px solid #1a1a2e;border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center;font-size:20px;">🚕</div>',
        className: '', iconSize: [36,36], iconAnchor: [18,18]
    });
    let driverMarker = null;

    if (navigator.geolocation) {
        navigator.geolocation.watchPosition(pos => {
            const { latitude: lat, longitude: lng } = pos.coords;
            if (!driverMarker) {
                driverMarker = L.marker([lat, lng], { icon: driverIcon }).addTo(map).bindPopup('Votre position');
            } else {
                driverMarker.setLatLng([lat, lng]);
            }
            map.setView([lat, lng], 15);
            fetch('api/update-position.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ lat, lng })
            }).catch(() => {});
        }, err => console.warn(err.message), { enableHighAccuracy: true, maximumAge: 8000 });
    }

    function updateStatus(action) {
        const messages = { 'in_progress': 'Client embarqué ?', 'completed': 'Terminer la course ?', 'cancel': 'Annuler cette course ?' };
        if (!confirm(messages[action])) return;
        fetch('api/respond-ride.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ride_id: <?= $rideId ?>, action })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.location.href = (action === 'in_progress') ? window.location.href : 'dashboard-driver.php';
            } else {
                alert(data.error || 'Erreur.');
            }
        }).catch(() => alert('Erreur réseau.'));
    }
</script>

<?php require_once 'includes/footer.php'; ?>