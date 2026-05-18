<?php require_once 'includes/header.php'; ?>

<div class="container py-5 mt-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h2 class="fw-bold" style="color: #1a1a2e;">Espace Chauffeur - Marc 🚕</h2>
            <div class="d-flex align-items-center mt-2">
                <span class="badge bg-success px-3 py-2 rounded-pill me-3"><i class="fa-solid fa-circle-check me-1"></i> En ligne</span>
                <span class="text-muted"><i class="fa-solid fa-car me-1"></i> Toyota Yaris (AB-123-CD)</span>
            </div>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <div class="form-check form-switch d-inline-block">
                <input class="form-check-input fs-4" type="checkbox" role="switch" id="statusSwitch" checked>
                <label class="form-check-label ms-2 fw-bold" for="statusSwitch">Disponible</label>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-primary text-dark" style="background-color: #ffd700 !important;">
                <div class="card-body p-4 text-center">
                    <h5 class="fw-bold mb-1">Revenus du jour</h5>
                    <h2 class="display-5 fw-bold mb-0">15 000 <small class="fs-5">FCFA</small></h2>
                    <p class="mb-0 mt-2"><i class="fa-solid fa-arrow-trend-up"></i> +20% vs hier</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 text-center">
                    <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fa-solid fa-route fa-2x text-warning"></i>
                    </div>
                    <h3 class="fw-bold">8</h3>
                    <p class="text-muted mb-0">Courses effectuées</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 text-center">
                    <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fa-solid fa-star fa-2x text-warning"></i>
                    </div>
                    <h3 class="fw-bold">4.8 <span class="fs-5 text-muted">/ 5</span></h3>
                    <p class="text-muted mb-0">Note moyenne</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <h4 class="fw-bold mb-4">Nouvelles Demandes</h4>
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                        <div>
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge bg-danger mb-2 me-2">Urgent</span>
                                <h6 class="fw-bold mb-2">Trajet: Louis ➔ Aéroport</h6>
                            </div>
                            <p class="text-muted small mb-1"><i class="fa-solid fa-user me-2"></i>Passager: Sylvie (Note: 4.9)</p>
                            <p class="text-muted small mb-0"><i class="fa-solid fa-coins me-2"></i>Prix estimé: 2500 FCFA • Distance: 5 km</p>
                        </div>
                        <div class="mt-3 mt-md-0 d-flex gap-2">
                            <button class="btn btn-outline-danger fw-bold rounded-pill px-4">Refuser</button>
                            <button class="btn btn-primary fw-bold rounded-pill px-4">Accepter</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card border-0 shadow-sm rounded-4 mb-4 opacity-75">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                        <div>
                            <h6 class="fw-bold mb-2">Trajet: Charbonnages ➔ Centre-ville</h6>
                            <p class="text-muted small mb-1"><i class="fa-solid fa-user me-2"></i>Passager: Eric (Note: 4.5)</p>
                            <p class="text-muted small mb-0"><i class="fa-solid fa-coins me-2"></i>Prix estimé: 1500 FCFA • Distance: 3 km</p>
                        </div>
                        <div class="mt-3 mt-md-0 d-flex gap-2">
                            <button class="btn btn-outline-danger fw-bold rounded-pill px-4">Refuser</button>
                            <button class="btn btn-primary fw-bold rounded-pill px-4">Accepter</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <h4 class="fw-bold mb-4">Carte</h4>
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden" style="min-height: 300px;">
                <!-- Espace pour la carte (Google Maps, Leaflet etc.) -->
                <div class="bg-light w-100 h-100 d-flex flex-column align-items-center justify-content-center p-4 text-center">
                    <i class="fa-solid fa-map-location-dot fa-3x text-muted mb-3"></i>
                    <h6 class="fw-bold text-muted">Intégration API Carte</h6>
                    <p class="small text-muted mb-0">La position du taxi et des clients sera affichée ici.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
