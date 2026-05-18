<div class="action-bar">
    <h2>Liste des Chauffeurs</h2>
    <button class="btn-primary">Ajouter un Chauffeur</button>
</div>

<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Prénom</th>
                <th>Nom</th>
                <th>Permis</th>
                <th>Téléphone</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($drivers)): ?>
                <?php foreach ($drivers as $driver): ?>
                    <tr>
                        <td><?= htmlspecialchars($driver['id']) ?></td>
                        <td><?= htmlspecialchars($driver['first_name']) ?></td>
                        <td><?= htmlspecialchars($driver['last_name']) ?></td>
                        <td><span class="badge badge-license"><?= htmlspecialchars($driver['license_number']) ?></span></td>
                        <td><?= htmlspecialchars($driver['phone'] ?? 'N/A') ?></td>
                        <td>
                            <?php
                            $statusClass = 'badge-active';
                            if ($driver['status'] === 'on_trip') $statusClass = 'badge-warning';
                            if ($driver['status'] === 'off_duty') $statusClass = 'badge-error';
                            
                            $statusLabel = [
                                'available' => 'Disponible',
                                'on_trip' => 'En trajet',
                                'off_duty' => 'Indisponible'
                            ][$driver['status']] ?? $driver['status'];
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
                    <td colspan="7" class="text-center" style="padding: 32px;">Aucun chauffeur trouvé.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
