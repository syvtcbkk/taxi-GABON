<div class="action-bar">
    <h2>Liste des Véhicules</h2>
    <button class="btn-primary">Ajouter un Véhicule</button>
</div>

<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Marque</th>
                <th>Modèle</th>
                <th>Immatriculation</th>
                <th>Capacité</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($vehicles)): ?>
                <?php foreach ($vehicles as $vehicle): ?>
                    <tr>
                        <td><?= htmlspecialchars($vehicle['id']) ?></td>
                        <td><?= htmlspecialchars($vehicle['brand']) ?></td>
                        <td><?= htmlspecialchars($vehicle['model']) ?></td>
                        <td><span class="badge badge-license"><?= htmlspecialchars($vehicle['license_plate']) ?></span></td>
                        <td><?= htmlspecialchars($vehicle['capacity']) ?> places</td>
                        <td>
                            <?php
                            $statusClass = 'badge-active';
                            if ($vehicle['status'] === 'maintenance') $statusClass = 'badge-warning';
                            if ($vehicle['status'] === 'retired') $statusClass = 'badge-error';
                            
                            $statusLabel = [
                                'active' => 'Actif',
                                'maintenance' => 'En maintenance',
                                'retired' => 'Retiré'
                            ][$vehicle['status']] ?? $vehicle['status'];
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
                    <td colspan="7" class="text-center" style="padding: 32px;">Aucun véhicule trouvé. (Avez-vous importé `database/init.sql` dans WAMP ?)</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
