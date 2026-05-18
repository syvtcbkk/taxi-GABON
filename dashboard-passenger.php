<?php require_once 'includes/header.php'; ?>

<div class="container py-5 mt-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h2 class="fw-bold" style="color: #1a1a2e;">Bonjour, Jean ! 👋</h2>
            <p class="text-muted">Gérez vos réservations et suivez vos courses.</p>
        </div>
        <div class="col-md-4 text-md-end">
            <button class="btn btn-primary fw-bold px-4 rounded-pill" data-bs-toggle="modal" data-bs-target="#bookRideModal">
                <i class="fa-solid fa-plus me-2"></i>Nouvelle Course
            </button>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 text-center">
                    <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fa-solid fa-route fa-2x text-warning"></i>
                    </div>
                    <h3 class="fw-bold">12</h3>
                    <p class="text-muted mb-0">Courses Totales</p>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4">Course Active</h5>
                    <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-white p-2 rounded-circle shadow-sm me-3">
                                <i class="fa-solid fa-taxi text-warning"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1">En route vers: Aéroport Léon Mba</h6>
                                <p class="text-muted small mb-0">Arrivée estimée: 15 mins • Chauffeur: Marc</p>
                            </div>
                        </div>
                        <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">En cours</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                    <tr>
                        <td class="px-4 py-3 text-muted">Aujourd'hui, 10:30</td>
                        <td class="py-3 fw-medium">Louis</td>
                        <td class="py-3 fw-medium">Centre-ville</td>
                        <td class="py-3">
                            <div class="d-flex align-items-center">
                                <i class="fa-solid fa-circle-user text-secondary me-2"></i> Paul
                            </div>
                        </td>
                        <td class="py-3"><span class="badge bg-success bg-opacity-10 text-success px-2 py-1 rounded">Terminée</span></td>
                        <td class="px-4 py-3 fw-bold text-end">2000 FCFA</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-muted">Hier, 18:15</td>
                        <td class="py-3 fw-medium">Owendo</td>
                        <td class="py-3 fw-medium">Nzeng-Ayong</td>
                        <td class="py-3">
                            <div class="d-flex align-items-center">
                                <i class="fa-solid fa-circle-user text-secondary me-2"></i> Fabrice
                            </div>
                        </td>
                        <td class="py-3"><span class="badge bg-success bg-opacity-10 text-success px-2 py-1 rounded">Terminée</span></td>
                        <td class="px-4 py-3 fw-bold text-end">3500 FCFA</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Réservation -->
<div class="modal fade" id="bookRideModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Réserver un Taxi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form action="#" method="POST">
                    <div class="mb-3 position-relative">
                        <label class="form-label text-muted small fw-bold text-uppercase">Lieu de départ</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-location-dot text-success"></i></span>
                            <input type="text" class="form-control border-start-0 ps-0" placeholder="Où êtes-vous ?" required>
                        </div>
                    </div>
                    <div class="mb-4 position-relative">
                        <label class="form-label text-muted small fw-bold text-uppercase">Lieu d'arrivée</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-location-dot text-danger"></i></span>
                            <input type="text" class="form-control border-start-0 ps-0" placeholder="Où allez-vous ?" required>
                        </div>
                    </div>
                    
                    <!-- Carte factice pour la sélection -->
                    <div class="bg-light rounded-3 mb-4 d-flex align-items-center justify-content-center border" style="height: 150px;">
                        <div class="text-center text-muted">
                            <i class="fa-solid fa-map-location-dot fa-2x mb-2"></i>
                            <p class="small mb-0">Carte interactive API de géolocalisation</p>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold rounded-pill py-2">Trouver un chauffeur</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
