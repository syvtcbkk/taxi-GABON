<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$sessionRole = $_SESSION['user_role'] ?? $_SESSION['role'] ?? null;
if ($sessionRole !== 'passenger') {
    header('Location: dashboard-driver.php');
    exit;
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

// Récupérer note moyenne du chauffeur pour le trajet actif
if ($activeRide && !empty($activeRide['driver_id'])) {
    $drv = $pdo->prepare('SELECT rating FROM users WHERE id = ?');
    $drv->execute([(int)$activeRide['driver_id']]);
    $activeRide['driver_rating'] = $drv->fetchColumn();
}

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

// Récupérer notes moyennes pour tous les chauffeurs dans l'historique
$driverIds = array_values(array_unique(array_filter(array_column($history, 'driver_id'))));
if (!empty($driverIds)) {
    $placeholders = implode(',', array_fill(0, count($driverIds), '?'));
    $mapStmt = $pdo->prepare("SELECT id, rating FROM users WHERE id IN ($placeholders)");
    $mapStmt->execute($driverIds);
    $map = [];
    while ($r = $mapStmt->fetch(PDO::FETCH_ASSOC)) { $map[(int)$r['id']] = $r['rating']; }
    foreach ($history as &$h) {
        $h['driver_rating'] = $map[(int)$h['driver_id']] ?? null;
    }
    unset($h);
}

// ── Total courses ─────────────────────────────────────────────────────────────
$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM rides WHERE passenger_id = ? AND status = "completed"');
$totalStmt->execute([$userId]);
$totalRides = $totalStmt->fetchColumn();

// ── Demande en attente (pour les passagers) ───────────────────────────────────
$pendingStmt = $pdo->prepare('SELECT * FROM rides WHERE passenger_id = ? AND status = "pending" ORDER BY created_at DESC LIMIT 1');
$pendingStmt->execute([$userId]);
$pendingRide = $pendingStmt->fetch();
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />

<style>
    .suggestion-box {
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        max-height: 200px;
        overflow-y: auto;
        z-index: 2500;
    }

    .suggestion-item {
        padding: 10px 15px;
        cursor: pointer;
        font-size: 14px;
        border-bottom: 1px solid #f5f5f5;
        display: block;
        color: #333;
        text-decoration: none;
    }

    .suggestion-item:hover {
        background-color: #f8f9fa;
        color: #1a1a2e;
    }
</style>

<div class="container py-5 mt-4">
    <!-- Overlay de traitement / spinner -->
    <div id="processingOverlay" style="display:none;position:fixed;inset:0;background:rgba(255,255,255,0.8);z-index:3000;align-items:center;justify-content:center;">
        <div class="text-center">
            <div class="spinner-border text-warning" role="status" style="width:3rem;height:3rem;"></div>
            <div class="mt-3 fw-bold">Traitement en cours, veuillez patienter...</div>
        </div>
    </div>

    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h2 class="fw-bold" style="color:#1a1a2e;">
                Bonjour, <?= htmlspecialchars($_SESSION['user_name']) ?> ! 👋
            </h2>
            <p class="text-muted mb-0">Commandez un chauffeur en quelques clics ou suivez vos trajets.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <button class="btn btn-primary fw-bold px-4 py-2 rounded-pill shadow-sm text-dark" style="background-color:#ffd700; border-color:#ffd700;"
                data-bs-toggle="modal" data-bs-target="#bookRideModal">
                <i class="fa-solid fa-square-plus me-2"></i>Nouvelle Course
            </button>
        </div>
    </div>

    <?php if ($pendingRide): ?>
    <div class="row g-4 mb-5">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white border-start border-warning border-4">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                        <div>
                            <span class="badge bg-warning text-dark rounded-pill px-3 py-2">En attente d'un chauffeur</span>
                            <h5 class="fw-bold mt-3 mb-2"><?= htmlspecialchars($pendingRide['origin_address']) ?> → <?= htmlspecialchars($pendingRide['dest_address']) ?></h5>
                            <p class="text-muted mb-1">Distance estimée : <strong><?= htmlspecialchars($pendingRide['distance_km']) ?> km</strong></p>
                            <p class="text-muted mb-0">Durée estimée : <strong>~<?= htmlspecialchars($pendingRide['duration_min']) ?> min</strong></p>
                        </div>
                        <div class="text-md-end">
                            <div class="fw-bold fs-4 text-success mb-2 price-badge"><?= number_format($pendingRide['price_fcfa'], 0, '.', ' ') ?> FCFA</div>
                            <small class="text-muted">Nous cherchons un chauffeur disponible pour vous.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-4 text-center">
                    <div class="rounded-circle bg-warning bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3"
                        style="width:60px;height:60px;">
                        <i class="fa-solid fa-receipt fa-2x text-warning"></i>
                    </div>
                    <h3 class="fw-bold text-dark mb-1"><?= (int)$totalRides ?></h3>
                    <p class="text-muted mb-0">Courses Effectuées</p>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <?php if ($activeRide): ?>
                <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 5px solid #ffd700 !important;">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                            <div>
                                <span class="badge bg-warning text-dark px-3 py-1.5 rounded-pill mb-2"><i class="fa-solid fa-spinner fa-spin me-1"></i> Taxi en approche</span>
                                <h5 class="fw-bold text-dark mb-1">
                                    <?= htmlspecialchars($activeRide['origin_address']) ?> ➔ <?= htmlspecialchars($activeRide['dest_address']) ?>
                                </h5>
                                <p class="text-muted small mb-0">
                                    Chauffeur : <strong><a href="#" class="open-reviews-btn text-decoration-none" data-driver="<?= (int)$activeRide['driver_id'] ?>"><?=
                                        htmlspecialchars($activeRide['driver_first'] . ' ' . $activeRide['driver_last'])
                                    ?></a></strong>
                                    <?php if (!empty($activeRide['driver_rating'])): ?>
                                        <span class="badge bg-warning text-dark ms-2"><?= number_format($activeRide['driver_rating'],1) ?>★</span>
                                    <?php endif; ?>
                                    • <?= htmlspecialchars($activeRide['vehicle_brand'] . ' ' . $activeRide['vehicle_model']) ?> (<?= htmlspecialchars($activeRide['plate_number'] ?? '') ?>)
                                </p>
                            </div>
                            <div class="text-end">
                                <span class="text-muted small d-block">Prix fixé :</span>
                                <span class="fw-bold fs-4 text-success price-badge"><?= number_format($activeRide['price_fcfa'], 0, '.', ' ') ?> <small class="fs-6">FCFA</small></span>
                            </div>
                        </div>
                        <div id="trackMap" style="height:220px; border-radius:.75rem;" class="shadow-inner mt-2"></div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4 h-100 bg-light border-dashed">
                    <div class="card-body p-4 d-flex flex-column align-items-center justify-content-center text-center text-muted">
                        <i class="fa-solid fa-taxi fa-2x mb-2 text-muted opacity-50"></i>
                        <h6 class="fw-bold text-dark mb-1">Aucun trajet en cours</h6>
                        <p class="small mb-0">Prêt à partir ? Appuyez sur le bouton "Nouvelle course" en haut à droite.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <h4 class="fw-bold mb-4"><i class="fa-solid fa-history text-warning me-2"></i>Historique Récent</h4>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="px-4 py-3 border-0">Date & Heure</th>
                        <th class="py-3 border-0">Lieu de départ</th>
                        <th class="py-3 border-0">Destination</th>
                        <th class="py-3 border-0">Chauffeur</th>
                        <th class="py-3 border-0">Statut</th>
                        <th class="px-4 py-3 border-0 text-end">Tarif</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="fa-solid fa-folder-open d-block mb-2 fa-2x opacity-50"></i> Vous n'avez pas encore effectué de courses.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history as $r): ?>
                            <tr>
                                <td class="px-4 py-3 text-muted small">
                                    <?= date('d/m/Y à H:i', strtotime($r['completed_at'])) ?>
                                </td>
                                <td class="py-3 fw-medium text-dark"><?= htmlspecialchars($r['origin_address']) ?></td>
                                <td class="py-3 fw-medium text-dark"><?= htmlspecialchars($r['dest_address']) ?></td>
                                <td class="py-3 text-muted">
                                    <i class="fa-solid fa-user-check text-success me-1"></i>
                                    <a href="#" class="open-reviews-btn text-decoration-none" data-driver="<?= (int)$r['driver_id'] ?>"><?= htmlspecialchars($r['driver_first'] ?? '—') ?></a>
                                    <?php if (!empty($r['driver_rating'])): ?>
                                        <span class="badge bg-warning text-dark ms-2"><?= number_format($r['driver_rating'],1) ?>★</span>
                                    <?php endif; ?>
                                    <?php if (!empty($r['driver_id']) && (empty($r['driver_rating']) || $r['driver_rating'] === null)): ?>
                                        <button class="btn btn-sm btn-outline-secondary ms-2 leave-review-btn" data-ride="<?= (int)$r['id'] ?>" data-driver="<?= (int)$r['driver_id'] ?>">Laisser un avis</button>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3">
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded">Terminée</span>
                                </td>
                                <td class="px-4 py-3 fw-bold text-end text-dark">
                                    <?= number_format($r['price_fcfa'], 0, '.', ' ') ?> FCFA
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal d'avis -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <form method="POST" action="actions/submit-review.php">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="ride_id" id="reviewRideId" value="">
                <div class="modal-header">
                    <h5 class="modal-title">Laisser un avis</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Note</label>
                        <select name="rating" id="reviewRating" class="form-select" required>
                            <option value="5">5 — Excellent</option>
                            <option value="4">4 — Très bien</option>
                            <option value="3">3 — Correct</option>
                            <option value="2">2 — Médiocre</option>
                            <option value="1">1 — Mauvais</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Commentaire (optionnel)</label>
                        <textarea name="comment" class="form-control" rows="4"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Envoyer l'avis</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: liste des avis -->
<div class="modal fade" id="reviewsListModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Avis</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="reviewsLoader" class="text-center py-4">
                    <div class="spinner-border text-warning" role="status"></div>
                </div>
                <div id="reviewsContent" style="display:none;">
                    <div class="mb-3">
                        <strong>Moyenne: </strong> <span id="avgRating">—</span>
                        <small class="text-muted">(<span id="reviewsCount">0</span> avis)</small>
                    </div>
                    <div id="reviewsList" class="list-group"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="bookRideModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold text-dark fs-4"><i class="fa-solid fa-car-side text-warning me-2"></i>Demander un Taxi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="step1">
                    <div class="mb-3 position-relative">
                        <label class="form-label text-dark small fw-bold text-uppercase mb-1">Point de ramassage</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-circle text-success fs-6"></i></span>
                            <input type="text" id="originInput" class="form-control border-start-0 ps-1" placeholder="Entrez le lieu de départ (ex: Libreville)..." autocomplete="off">
                        </div>
                        <div id="originSuggestions" class="suggestion-box position-absolute w-100 mt-1"></div>
                    </div>
                    <div class="mb-4 position-relative">
                        <label class="form-label text-dark small fw-bold text-uppercase mb-1">Destination finale</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-location-dot text-danger fs-5"></i></span>
                            <input type="text" id="destInput" class="form-control border-start-0 ps-1" placeholder="Où allez-vous ?" autocomplete="off">
                        </div>
                        <div id="destSuggestions" class="suggestion-box position-absolute w-100 mt-1"></div>
                    </div>
                    <div id="bookMap" style="height:250px; border-radius:.75rem;" class="mb-3 border shadow-sm"></div>

                    <div class="d-flex flex-column gap-2">
                        <button class="btn btn-outline-secondary border w-100 rounded-pill text-dark fw-medium btn-sm py-2" onclick="useMyLocation()">
                            <i class="fa-solid fa-crosshairs text-primary me-2"></i>Utiliser ma position actuelle
                        </button>
                        <button class="btn btn-outline-secondary w-100 fw-bold rounded-pill py-2 mt-2 text-dark shadow-sm" onclick="estimateRide()">
                            Calculer l'itinéraire & le prix
                        </button>
                    </div>
                </div>

                <div id="step2" class="d-none">
                    <div class="card border-0 bg-light rounded-3 p-4 mb-4 shadow-sm border-start border-warning border-4">
                        <div class="row text-center g-3">
                            <div class="col-4 border-end">
                                <div class="fw-bold fs-5 text-dark" id="estDistance">—</div>
                                <small class="text-muted">Distance</small>
                            </div>
                            <div class="col-4 border-end">
                                <div class="fw-bold fs-5 text-dark" id="estDuration">—</div>
                                <small class="text-muted">Durée de trajet</small>
                            </div>
                            <div class="col-4">
                                <div class="fw-bold fs-4 text-success" id="estPrice">—</div>
                                <small class="text-muted fw-bold">Prix Garanti</small>
                            </div>
                        </div>
                    </div>
                    <div id="routeMap" style="height:250px; border-radius:.75rem;" class="mb-4 border"></div>
                    <div class="d-flex gap-3">
                        <button class="btn btn-outline-secondary fw-bold rounded-pill flex-grow-1 py-2" onclick="backToStep1()">Modifier</button>
                        <button class="btn btn-primary fw-bold rounded-pill flex-grow-1 py-2 text-dark shadow-sm" onclick="confirmRide()">Confirmer & Commander</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>

<script>
    const TARIF_BASE = 500;
    const TARIF_KM = 400;

    // Sécurisation anti-XSS des chaînes injectées dynamiquement dans le DOM
    function escapeHTML(str) {
        return str.replace(/[&<>'"]/g,
            tag => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#39;',
                '"': '&quot;'
            } [tag] || tag)
        );
    }

    let bookMap, routeMap, trackMap;
    let originLatLng = null,
        destLatLng = null;
    let originMarker = null,
        destMarker = null;
    let routingControl = null;
    let rideEstimate = {};

    document.getElementById('bookRideModal').addEventListener('shown.bs.modal', () => {
        if (!bookMap) {
            bookMap = L.map('bookMap').setView([0.3924, 9.4536], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap'
            }).addTo(bookMap);
            bookMap.on('click', e => {
                if (!originLatLng) setOrigin(e.latlng.lat, e.latlng.lng, 'Coordonnées choisies (' + e.latlng.lat.toFixed(4) + ')');
                else if (!destLatLng) setDest(e.latlng.lat, e.latlng.lng, 'Destination choisie (' + e.latlng.lat.toFixed(4) + ')');
            });
        } else {
            bookMap.invalidateSize();
        }
    });

    let searchTimeout;

    function setupAutocomplete(inputId, suggestionsId, onSelect) {
        const input = document.getElementById(inputId);
        const box = document.getElementById(suggestionsId);

        input.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            const q = input.value.trim();
            if (q.length < 3) {
                box.innerHTML = '';
                return;
            }

            searchTimeout = setTimeout(async () => {
                try {
                    const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q+' Gabon')}&limit=5`);
                    const data = await res.json();
                    box.innerHTML = '';
                    data.forEach(item => {
                        const a = document.createElement('a');
                        a.href = '#';
                        a.className = 'suggestion-item';
                        a.innerHTML = escapeHTML(item.display_name);
                        a.addEventListener('click', e => {
                            e.preventDefault();
                            input.value = item.display_name;
                            box.innerHTML = '';
                            onSelect(parseFloat(item.lat), parseFloat(item.lon), item.display_name);
                        });
                        box.appendChild(a);
                    });
                } catch (e) {}
            }, 400);
        });
    }

    function setOrigin(lat, lng, label) {
        originLatLng = {
            lat,
            lng,
            label
        };
        if (originMarker) bookMap.removeLayer(originMarker);
        originMarker = L.marker([lat, lng]).addTo(bookMap).bindPopup('Départ').openPopup();
        bookMap.setView([lat, lng], 14);
        document.getElementById('originInput').value = label;
    }

    function setDest(lat, lng, label) {
        destLatLng = {
            lat,
            lng,
            label
        };
        if (destMarker) bookMap.removeLayer(destMarker);
        destMarker = L.marker([lat, lng], {
            icon: L.icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41]
            })
        }).addTo(bookMap).bindPopup('Arrivée').openPopup();
        document.getElementById('destInput').value = label;
    }

    setupAutocomplete('originInput', 'originSuggestions', (lat, lng, label) => setOrigin(lat, lng, label));
    setupAutocomplete('destInput', 'destSuggestions', (lat, lng, label) => setDest(lat, lng, label));

    function useMyLocation() {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition(pos => {
            setOrigin(pos.coords.latitude, pos.coords.longitude, 'Ma position actuelle');
        }, () => alert('Impossible d\'accéder à votre position actuelle.'));
    }

    function estimateRide() {
        if (!originLatLng || !destLatLng) {
            alert('Veuillez renseigner un lieu de départ et de destination.');
            return;
        }
        document.getElementById('step1').classList.add('d-none');
        document.getElementById('step2').classList.remove('d-none');

        setTimeout(() => {
            if (!routeMap) {
                routeMap = L.map('routeMap').setView([0.3924, 9.4536], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(routeMap);
            } else {
                routeMap.invalidateSize();
                if (routingControl) routeMap.removeControl(routingControl);
            }

            routingControl = L.Routing.control({
                waypoints: [L.latLng(originLatLng.lat, originLatLng.lng), L.latLng(destLatLng.lat, destLatLng.lng)],
                router: L.Routing.osrmv1({
                    serviceUrl: 'https://router.project-osrm.org/route/v1'
                }),
                lineOptions: {
                    styles: [{
                        color: '#ffb700',
                        opacity: 0.8,
                        weight: 6
                    }]
                },
                createMarker: () => null,
                addWaypoints: false
            }).addTo(routeMap);

            routingControl.on('routesfound', function(e) {
                const route = e.routes[0];
                const distance = (route.summary.totalDistance / 1000).toFixed(1);
                const duration = Math.round(route.summary.totalTime / 60);
                const price = TARIF_BASE + (Math.ceil(distance) * TARIF_KM);

                document.getElementById('estDistance').textContent = distance + ' km';
                document.getElementById('estDuration').textContent = duration + ' min';
                document.getElementById('estPrice').textContent = price.toLocaleString('fr-FR') + ' F';

                rideEstimate = {
                    distance_km: distance,
                    duration_min: duration,
                    price_fcfa: price,
                    origin_lat: originLatLng.lat,
                    origin_lng: originLatLng.lng,
                    origin_address: originLatLng.label,
                    dest_lat: destLatLng.lat,
                    dest_lng: destLatLng.lng,
                    dest_address: destLatLng.label
                };
            });
        }, 200);
    }

    function backToStep1() {
        document.getElementById('step2').classList.add('d-none');
        document.getElementById('step1').classList.remove('d-none');
    }

    // ── CARTE DE SUIVI — Course active ───────────────────────────────────────────
    <?php if ($activeRide && $activeRide['driver_lat'] && $activeRide['driver_lng']): ?>
            (function initTrackMap() {
                const driverLat = <?= (float)$activeRide['driver_lat'] ?>;
                const driverLng = <?= (float)$activeRide['driver_lng'] ?>;
                const destLat = <?= (float)$activeRide['dest_lat'] ?>;
                const destLng = <?= (float)$activeRide['dest_lng'] ?>;

                trackMap = L.map('trackMap').setView([driverLat, driverLng], 14);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap'
                }).addTo(trackMap);

                const taxiIcon = L.divIcon({
                    html: '<div style="background:#ffd700;border:3px solid #1a1a2e;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;font-size:16px;">🚕</div>',
                    className: '',
                    iconSize: [32, 32],
                    iconAnchor: [16, 16]
                });
                L.marker([driverLat, driverLng], {
                    icon: taxiIcon
                }).addTo(trackMap).bindPopup('Votre chauffeur');
                L.marker([destLat, destLng]).addTo(trackMap).bindPopup('Destination');

                // Remplacement du polling par SSE (EventSource) pour mises à jour temps réel
                let taxiMarker = null;
                const driverId = <?= isset($activeRide['driver_id']) ? (int)$activeRide['driver_id'] : 0 ?>;

                // Connexion SSE
                try {
                    const es = new EventSource('api/stream-positions.php');
                    es.addEventListener('positions', e => {
                        try {
                            const data = JSON.parse(e.data || '[]');
                            const pos = (data || []).find(p => Number(p.user_id) === Number(driverId));
                            if (!pos || !pos.lat || !pos.lng) return;
                            if (!taxiMarker) {
                                taxiMarker = L.marker([pos.lat, pos.lng], { icon: taxiIcon }).addTo(trackMap);
                            } else {
                                taxiMarker.setLatLng([pos.lat, pos.lng]);
                            }
                            trackMap.setView([pos.lat, pos.lng]);
                        } catch (err) { /* ignore parse errors */ }
                    });
                } catch (err) {
                    // Fallback sur polling toutes les 10s si EventSource non supporté
                    setInterval(() => {
                        fetch('api/driver-position.php?driver_id=' + driverId)
                            .then(r => r.json())
                            .then(pos => {
                                if (!pos.lat || !pos.lng) return;
                                if (!taxiMarker) {
                                    taxiMarker = L.marker([pos.lat, pos.lng], { icon: taxiIcon }).addTo(trackMap);
                                } else {
                                    taxiMarker.setLatLng([pos.lat, pos.lng]);
                                }
                                trackMap.setView([pos.lat, pos.lng]);
                            }).catch(() => {});
                    }, 10000);
                }
            })();
    <?php endif; ?>

    function confirmRide() {
        if (!rideEstimate.price_fcfa) return;
        // Afficher overlay et désactiver boutons
        const overlay = document.getElementById('processingOverlay');
        overlay.style.display = 'flex';
        // Démarrer le flux de paiement (Stripe Checkout)
        fetch('api/create-checkout-session.php', {
                method: 'POST',
                mode: 'same-origin',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(rideEstimate)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.url) {
                    // Rediriger vers Stripe Checkout
                    window.location.href = data.url;
                } else {
                    overlay.style.display = 'none';
                    alert(data.error || 'Erreur lors de la création de la session de paiement.');
                }
            }).catch(() => { overlay.style.display = 'none'; alert('Erreur réseau.'); });
    }

    // Review modal handling
    document.addEventListener('click', function (e) {
        if (e.target && e.target.matches('.leave-review-btn')) {
            var ride = e.target.getAttribute('data-ride');
            var driver = e.target.getAttribute('data-driver');
            var input = document.getElementById('reviewRideId');
            input.value = ride;
            var modal = new bootstrap.Modal(document.getElementById('reviewModal'));
            modal.show();
        }
        if (e.target && e.target.matches('.open-reviews-btn')) {
            e.preventDefault();
            var driverId = parseInt(e.target.getAttribute('data-driver')) || 0;
            if (!driverId) return;
            var modalEl = document.getElementById('reviewsListModal');
            var modal = new bootstrap.Modal(modalEl);
            // reset
            document.getElementById('reviewsContent').style.display = 'none';
            document.getElementById('reviewsLoader').style.display = 'block';
            document.getElementById('reviewsList').innerHTML = '';
            document.getElementById('avgRating').textContent = '—';
            document.getElementById('reviewsCount').textContent = '0';
            modal.show();
            fetch('api/get-reviews.php?driver_id=' + driverId)
                .then(r => r.json())
                .then(json => {
                    document.getElementById('reviewsLoader').style.display = 'none';
                    if (!json.success) {
                        document.getElementById('reviewsContent').innerHTML = '<div class="text-danger">Erreur lors du chargement.</div>';
                        document.getElementById('reviewsContent').style.display = 'block';
                        return;
                    }
                    document.getElementById('avgRating').textContent = json.avg_rating ? json.avg_rating : '—';
                    document.getElementById('reviewsCount').textContent = json.count || 0;
                    var list = document.getElementById('reviewsList');
                    if ((json.reviews || []).length === 0) {
                        list.innerHTML = '<div class="text-muted">Aucun avis pour le moment.</div>';
                    } else {
                        json.reviews.forEach(function (it) {
                            var item = document.createElement('div');
                            item.className = 'list-group-item';
                            var name = (it.from_first ? it.from_first : 'Utilisateur');
                            item.innerHTML = '<div class="d-flex justify-content-between align-items-start"><div><strong>' + escapeHTML(name) + '</strong> <small class="text-muted">' + escapeHTML(it.created_at) + '</small><div class="mt-2">' + escapeHTML(it.comment || '') + '</div></div><div class="text-end"><span class="badge bg-warning text-dark">' + (it.rating || 0) + '★</span></div></div>';
                            list.appendChild(item);
                        });
                    }
                    document.getElementById('reviewsContent').style.display = 'block';
                }).catch(() => {
                    document.getElementById('reviewsLoader').style.display = 'none';
                    document.getElementById('reviewsContent').innerHTML = '<div class="text-danger">Erreur réseau.</div>';
                    document.getElementById('reviewsContent').style.display = 'block';
                });
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>