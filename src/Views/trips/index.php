<div class="action-bar">
    <h2>Liste des Trajets</h2>
    <button class="btn-primary">Nouveau Trajet</button>
</div>

<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Véhicule ID</th>
                <th>Chauffeur ID</th>
                <th>Départ</th>
                <th>Arrivée</th>
                <th>Heure de Départ</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($trips)): ?>
                <?php foreach ($trips as $trip): ?>
                    <tr>
                        <td><?= htmlspecialchars($trip['id']) ?></td>
                        <td><?= htmlspecialchars($trip['vehicle_id']) ?></td>
                        <td><?= htmlspecialchars($trip['driver_id']) ?></td>
                        <td><?= htmlspecialchars($trip['departure_location']) ?></td>
                        <td><?= htmlspecialchars($trip['arrival_location']) ?></td>
                        <td><?= htmlspecialchars($trip['departure_time']) ?></td>
                        <td>
                            <?php
                            $statusClass = 'badge-active';
                            if ($trip['status'] === 'scheduled') $statusClass = 'badge-warning';
                            if ($trip['status'] === 'cancelled') $statusClass = 'badge-error';
                            
                            $statusLabel = [
                                'scheduled' => 'Planifié',
                                'in_progress' => 'En cours',
                                'completed' => 'Terminé',
                                'cancelled' => 'Annulé'
                            ][$trip['status']] ?? $trip['status'];
                            ?>
                            <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></span>
                        </td>
                        <td>
                            <button class="btn-icon" title="Modifier">✏️</button>
                            <button class="btn-icon text-danger" title="Supprimer">🗑️</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center" style="padding: 32px;">Aucun trajet trouvé.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
