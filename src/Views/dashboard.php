<div class="dashboard-stats">
    <div class="stat-card">
        <div class="stat-icon vehicle-icon">🚗</div>
        <div class="stat-info">
            <h3>Véhicules</h3>
            <p class="stat-number"><?= htmlspecialchars($stats['vehicles'] ?? 0) ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon driver-icon">👨‍✈️</div>
        <div class="stat-info">
            <h3>Chauffeurs</h3>
            <p class="stat-number"><?= htmlspecialchars($stats['drivers'] ?? 0) ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon trip-icon">🛣️</div>
        <div class="stat-info">
            <h3>Trajets</h3>
            <p class="stat-number"><?= htmlspecialchars($stats['trips'] ?? 0) ?></p>
        </div>
    </div>
</div>

<div class="recent-activity">
    <div class="table-container" style="padding: 32px; text-align: center;">
        <h2 style="margin-bottom: 16px;">Bienvenue sur TransportApp</h2>
        <p style="color: var(--text-muted);">Sélectionnez une option dans le menu latéral pour commencer à gérer votre flotte de véhicules et vos trajets.</p>
    </div>
</div>
