<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'passenger') {
    header('Location: login.php'); exit;
}
require_once 'includes/db.php';
require_once 'includes/header.php';

$pdo    = getPDO();
$userId = $_SESSION['user_id'];

// ── Course active ─────────────────────────────────────────────────────────────
$activeStmt = $pdo->prepare('
    SELECT r.*, u.first_name AS driver_first, u.last_name AS driver_last,
           dp.vehicle_brand, dp.vehicle_model, dp.plate_number,
           lp.latitude AS driver_lat, lp.longitude AS driver_lng
    FROM rides r
    JOIN users u ON u.id = r.driver_id
    LEFT JOIN driver_profiles dp ON dp.user_id = r.driver_id
    LEFT JOIN live_positions lp ON lp.user_id = r.driver_id
    WHERE r.passenger_id = ? AND r.status IN ("accepted","in_progress")
    LIMIT 1
');
$activeStmt->execute([$userId]);
$activeRide = $activeStmt->fetch();

// ── Historique ────────────────────────────────────────────────────────────────
$histStmt = $pdo->prepare('
    SELECT r.*, u.first_name AS driver_first, u.last_name AS driver_last
    FROM rides r
    LEFT JOIN users u ON u.id = r.driver_id
    WHERE r.passenger_id = ? AND r.status = "completed"
    ORDER BY r.completed_at DESC LIMIT 10
');
$histStmt->execute([$userId]);
$history = $histStmt->fetchAll();

// ── Total courses ─────────────────────────────────────────────────────────────
$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM rides WHERE passenger_id = ? AND status = "completed"');
$totalStmt->execute([$userId]);
$totalRides = $totalStmt->fetchColumn();
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

<div class="container py-5 mt-4">

    <!-- ── En-tête ─────────────────────────────────────────────────────────── -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h2 class="fw-bold" style="color:#1a1a2e;">
                Bonjour, <?= htmlspecialchars($_SESSION['user_name']) ?> ! 👋
            </h2>
            <p class="text-muted">Gérez vos réservations et suivez vos courses.</p>
        </div>
        <div class="col-md-4 text-md-end">
            <button class="btn btn-primary fw-bold px-4 rounded-pill"
                    data-bs-toggle="modal" data-bs-target="#bookRideModal">
                <i class="fa-solid fa-plus me-2"></i>Nouvelle Course
            </button>
        </div>
    </div>

    <!-- ── KPI + Course active ─────────────────────────────────────────────── -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 text-center">
                    <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3"
                         style="width:60px;height:60px;">
                        <i class="fa-solid fa-route fa-2x text-warning"></i>
                    </div>
                    <h3 class="fw-bold"><?= (int)$totalRides ?></h3>
                    <p class="text-muted mb-0">Courses Totales</p>
                </div>
            </div>
        </div>

        <?php if ($activeRide): ?>
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4 h-100 border-warning" style="border:2px solid #ffd700 !important;">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">🚕 Course en cours</h5>
                    <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-3 mb-3">
                        <div>
                            <h6 class="fw-bold mb-1">
                                <?= htmlspecialchars($activeRide['origin_address']) ?>
                                ➔ <?= htmlspecialchars($activeRide['dest_address']) ?>
                            </h6>
                            <p class="text-muted small mb-0">
                                Chauffeur : <?= htmlspecialchars($activeRide['driver_first'].' '.$activeRide['driver_last']) ?>
                                · <?= htmlspecialchars($activeRide['vehicle_brand'].' '.$activeRide['vehicle_model']) ?>
                                (<?= htmlspecialchars($activeRide['plate_number'] ?? '') ?>)
                            </p>
                            <p class="text-muted small mb-0">
                                ETA : <span id="eta">calcul en cours…</span>
                                · Prix : <?= number_format($activeRide['price_fcfa'],0,'.',' ') ?> FCFA
                            </p>
                        </div>
                        <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">En cours</span>
                    </div>
                    <!-- Mini-carte de suivi -->
                    <div id="trackMap" style="height:220px;border-radius:.75rem;overflow:hidden;"></div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 d-flex flex-column align-items-center justify-content-center text-center text-muted">
                    <i class="fa-solid fa-taxi fa-3x mb-3 text-warning"></i>
                    <h6 class="fw-bold">Aucune course active</h6>
                    <p class="small mb-0">Cliquez sur « Nouvelle Course » pour réserver un taxi.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Historique ──────────────────────────────────────────────────────── -->
    <h4 class="fw-bold mb-4">Historique Récent</h4>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="bg-light">
                    <tr>
                        <th class="px-4 py-3 border-0">Date</th>
                        <th class="py-3 border-0">Départ</th>
                        <th class="py-3 border-0">Arrivée</th>
                        <th class="py-3 border-0">Chauffeur</th>
                        <th class="py-3 border-0">Statut</th>
                        <th class="px-4 py-3 border-0 text-end">Prix</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Aucune course terminée pour le moment.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($history as $r): ?>
                        <tr>
                            <td class="px-4 py-3 text-muted small">
                                <?= date('d/m/Y H:i', strtotime($r['completed_at'])) ?>
                            </td>
                            <td class="py-3 fw-medium"><?= htmlspecialchars($r['origin_address']) ?></td>
                            <td class="py-3 fw-medium"><?= htmlspecialchars($r['dest_address']) ?></td>
                            <td class="py-3">
                                <i class="fa-solid fa-circle-user text-secondary me-1"></i>
                                <?= htmlspecialchars($r['driver_first'] ?? '—') ?>
                            </td>
                            <td class="py-3">
                                <span class="badge bg-success bg-opacity-10 text-success px-2 py-1 rounded">Terminée</span>
                            </td>
                            <td class="px-4 py-3 fw-bold text-end">
                                <?= number_format($r['price_fcfa'],0,'.',' ') ?> FCFA
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     MODAL — Réserver une course
═══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="bookRideModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Réserver un Taxi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Étape 1 : Saisie des adresses -->
                <div id="step1">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold text-uppercase">Lieu de départ</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fa-solid fa-location-dot text-success"></i>
                            </span>
                            <input type="text" id="originInput" class="form-control border-start-0 ps-0"
                                   placeholder="Où êtes-vous ?" autocomplete="off">
                        </div>
                        <div id="originSuggestions" class="list-group mt-1 position-absolute w-100" style="z-index:2000;max-width:460px;"></div>
                    </div>
                    <div class="mb-3 position-relative">
                        <label class="form-label text-muted small fw-bold text-uppercase">Lieu d'arrivée</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fa-solid fa-location-dot text-danger"></i>
                            </span>
                            <input type="text" id="destInput" class="form-control border-start-0 ps-0"
                                   placeholder="Où allez-vous ?" autocomplete="off">
                        </div>
                        <div id="destSuggestions" class="list-group mt-1 position-absolute w-100" style="z-index:2000;max-width:460px;"></div>
                    </div>
                    <!-- Carte de sélection -->
                    <div id="bookMap" style="height:200px;border-radius:.75rem;margin-bottom:1rem;"></div>

                    <button class="btn btn-outline-secondary w-100 rounded-pill mb-2" onclick="useMyLocation()">
                        <i class="fa-solid fa-crosshairs me-2"></i>Utiliser ma position actuelle comme départ
                    </button>
                    <button class="btn btn-primary w-100 fw-bold rounded-pill py-2" onclick="estimateRide()">
                        Estimer le trajet
                    </button>
                </div>

                <!-- Étape 2 : Estimation & confirmation -->
                <div id="step2" class="d-none">
                    <div class="card border-0 bg-light rounded-3 p-4 mb-4">
                        <div class="row text-center g-3">
                            <div class="col-4">
                                <div class="fw-bold fs-5" id="estDistance">—</div>
                                <small class="text-muted">Distance</small>
                            </div>
                            <div class="col-4">
                                <div class="fw-bold fs-5" id="estDuration">—</div>
                                <small class="text-muted">Durée estimée</small>
                            </div>
                            <div class="col-4">
                                <div class="fw-bold fs-5 text-warning" id="estPrice">—</div>
                                <small class="text-muted">Prix FCFA</small>
                            </div>
                        </div>
                    </div>
                    <div id="routeMap" style="height:220px;border-radius:.75rem;margin-bottom:1rem;"></div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary fw-bold rounded-pill flex-grow-1"
                                onclick="backToStep1()">Modifier</button>
                        <button class="btn btn-primary fw-bold rounded-pill flex-grow-1"
                                onclick="confirmRide()">Confirmer la course</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<!-- Leaflet Routing Machine -->
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css"/>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>

<script>
// ══════════════════════════════════════════════════════════════
//  VARIABLES GLOBALES
// ══════════════════════════════════════════════════════════════
const TARIF_BASE      = 500;   // FCFA de prise en charge
const TARIF_KM        = 400;   // FCFA par km

let bookMap, routeMap, trackMap;
let originLatLng  = null;
let destLatLng    = null;
let originMarker  = null;
let destMarker    = null;
let routingControl = null;
let rideEstimate   = {};

// ══════════════════════════════════════════════════════════════
//  INITIALISATION CARTE DE RÉSERVATION
// ══════════════════════════════════════════════════════════════
document.getElementById('bookRideModal').addEventListener('shown.bs.modal', () => {
    if (!bookMap) {
        bookMap = L.map('bookMap').setView([0.3924, 9.4536], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap', maxZoom: 19
        }).addTo(bookMap);

        bookMap.on('click', e => {
            if (!originLatLng) setOrigin(e.latlng.lat, e.latlng.lng, 'Position sélectionnée');
            else               setDest(e.latlng.lat, e.latlng.lng, 'Destination sélectionnée');
        });
    } else {
        bookMap.invalidateSize();
    }
});

// ══════════════════════════════════════════════════════════════
//  GÉOCODAGE — Nominatim (OpenStreetMap, gratuit)
// ══════════════════════════════════════════════════════════════
let searchTimeout;
function setupAutocomplete(inputId, suggestionsId, onSelect) {
    const input = document.getElementById(inputId);
    const box   = document.getElementById(suggestionsId);
    input.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        const q = input.value.trim();
        if (q.length < 3) { box.innerHTML = ''; return; }
        searchTimeout = setTimeout(async () => {
            const res  = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q+' Gabon')}&limit=5`);
            const data = await res.json();
            box.innerHTML = '';
            data.forEach(item => {
                const a = document.createElement('a');
                a.href = '#';
                a.className = 'list-group-item list-group-item-action small';
                a.textContent = item.display_name;
                a.addEventListener('click', e => {
                    e.preventDefault();
                    input.value = item.display_name;
                    box.innerHTML = '';
                    onSelect(parseFloat(item.lat), parseFloat(item.lon), item.display_name);
                });
                box.appendChild(a);
            });
        }, 400);
    });
}

function setOrigin(lat, lng, label) {
    originLatLng = { lat, lng, label };
    if (originMarker) bookMap.removeLayer(originMarker);
    originMarker = L.marker([lat, lng]).addTo(bookMap).bindPopup('Départ').openPopup();
    bookMap.setView([lat, lng], 15);
    document.getElementById('originInput').value = label;
}
function setDest(lat, lng, label) {
    destLatLng = { lat, lng, label };
    if (destMarker) bookMap.removeLayer(destMarker);
    destMarker = L.marker([lat, lng], {
        icon: L.icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png', iconSize:[25,41], iconAnchor:[12,41] })
    }).addTo(bookMap).bindPopup('Arrivée').openPopup();
    document.getElementById('destInput').value = label;
}

setupAutocomplete('originInput', 'originSuggestions', (lat,lng,label) => setOrigin(lat,lng,label));
setupAutocomplete('destInput',   'destSuggestions',   (lat,lng,label) => setDest(lat,lng,label));

function useMyLocation() {
    navigator.geolocation.getCurrentPosition(pos => {
        setOrigin(pos.coords.latitude, pos.coords.longitude, 'Ma position');
    }, () => alert('Impossible d\'obtenir votre position.'));
}

// ══════════════════════════════════════════════════════════════
//  ESTIMATION DU TRAJET (OSRM gratuit)
// ══════════════════════════════════════════════════════════════
async function estimateRide() {
    if (!originLatLng || !destLatLng) {
        alert('Veuillez sélectionner un départ et une arrivée.'); return;
    }
    const url = `https://router.project-osrm.org/route/v1/driving/`
        + `${originLatLng.lng},${originLatLng.lat};${destLatLng.lng},${destLatLng.lat}`
        + `?overview=full&geometries=geojson`;

    const res  = await fetch(url);
    const data = await res.json();
    if (!data.routes || !data.routes[0]) { alert('Impossible de calculer le trajet.'); return; }

    const route    = data.routes[0];
    const distKm   = (route.distance / 1000).toFixed(1);
    const durMin   = Math.ceil(route.duration / 60);
    const price    = Math.round(TARIF_BASE + distKm * TARIF_KM);

    rideEstimate = { distKm, durMin, price, route };

    document.getElementById('estDistance').textContent = distKm + ' km';
    document.getElementById('estDuration').textContent = durMin + ' min';
    document.getElementById('estPrice').textContent    = price.toLocaleString('fr-FR') + ' FCFA';

    document.getElementById('step1').classList.add('d-none');
    document.getElementById('step2').classList.remove('d-none');

    // Afficher la route sur une nouvelle carte
    setTimeout(() => {
        if (!routeMap) {
            routeMap = L.map('routeMap');
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                { attribution: '© OpenStreetMap', maxZoom: 19 }).addTo(routeMap);
        } else {
            routeMap.eachLayer(l => { if (l instanceof L.Polyline || l instanceof L.Marker) routeMap.removeLayer(l); });
        }
        const geojson = L.geoJSON(route.geometry, { style: { color:'#ffd700', weight:5 } }).addTo(routeMap);
        L.marker([originLatLng.lat, originLatLng.lng]).addTo(routeMap).bindPopup('Départ');
        L.marker([destLatLng.lat, destLatLng.lng]).addTo(routeMap).bindPopup('Arrivée');
        routeMap.fitBounds(geojson.getBounds(), { padding:[20,20] });
    }, 200);
}

function backToStep1() {
    document.getElementById('step2').classList.add('d-none');
    document.getElementById('step1').classList.remove('d-none');
}

// ══════════════════════════════════════════════════════════════
//  CONFIRMATION DE COURSE
// ══════════════════════════════════════════════════════════════
async function confirmRide() {
    if (!rideEstimate.price) return;
    const payload = {
        origin_address : originLatLng.label,
        origin_lat     : originLatLng.lat,
        origin_lng     : originLatLng.lng,
        dest_address   : destLatLng.label,
        dest_lat       : destLatLng.lat,
        dest_lng       : destLatLng.lng,
        distance_km    : rideEstimate.distKm,
        duration_min   : rideEstimate.durMin,
        price_fcfa     : rideEstimate.price,
    };
    const res  = await fetch('api/book-ride.php', {
        method : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body   : JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
        bootstrap.Modal.getInstance(document.getElementById('bookRideModal')).hide();
        alert('Course réservée ! En attente d\'un chauffeur…');
        setTimeout(() => location.reload(), 1500);
    } else {
        alert(data.message || 'Erreur lors de la réservation.');
    }
}

// ══════════════════════════════════════════════════════════════
//  SUIVI EN TEMPS RÉEL (course active)
// ══════════════════════════════════════════════════════════════
<?php if ($activeRide): ?>
const ACTIVE_RIDE_ID   = <?= $activeRide['id'] ?>;
const DRIVER_ID        = <?= $activeRide['driver_id'] ?>;
const PASSENGER_LAT    = <?= $activeRide['dest_lat'] ?>;
const PASSENGER_LNG    = <?= $activeRide['dest_lng'] ?>;

trackMap = L.map('trackMap').setView([0.3924, 9.4536], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
}).addTo(trackMap);

const taxiIcon = L.divIcon({
    html: '<div style="background:#ffd700;border:3px solid #1a1a2e;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;font-size:18px;">🚕</div>',
    className: '', iconSize:[32,32], iconAnchor:[16,16]
});
let taxiMarker = null;
L.marker([PASSENGER_LAT, PASSENGER_LNG]).addTo(trackMap).bindPopup('Votre destination').openPopup();

async function pollDriverPosition() {
    try {
        const res  = await fetch(`api/driver-position.php?driver_id=${DRIVER_ID}`);
        const data = await res.json();
        if (!data.lat) return;

        if (!taxiMarker) {
            taxiMarker = L.marker([data.lat, data.lng], { icon: taxiIcon })
                           .addTo(trackMap).bindPopup('Votre chauffeur');
            trackMap.setView([data.lat, data.lng], 14);
        } else {
            taxiMarker.setLatLng([data.lat, data.lng]);
        }

        // Estimation ETA via OSRM
        const routeRes = await fetch(
            `https://router.project-osrm.org/route/v1/driving/${data.lng},${data.lat};${PASSENGER_LNG},${PASSENGER_LAT}?overview=false`
        );
        const routeData = await routeRes.json();
        if (routeData.routes?.[0]) {
            const eta = Math.ceil(routeData.routes[0].duration / 60);
            document.getElementById('eta').textContent = eta + ' min';
        }
    } catch(e) {}
}

pollDriverPosition();
setInterval(pollDriverPosition, 10000);
<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>
