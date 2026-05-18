<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

class DriverController {

    //VOIR SON PROFIL 
    public function monProfil(): void {
        $user = verifierToken();

        if ($user['role'] !== 'chauffeur') {
            http_response_code(403);
            echo json_encode(['erreur' => 'Accès réservé aux chauffeurs']);
            return;
        }

        $db   = getDB();
        $stmt = $db->prepare("
            SELECT id, nom, prenom, telephone, email, numero_permis,
                   immatriculation, marque_vehicule, ville, statut, created_at
            FROM chauffeurs WHERE id = ?
        ");
        $stmt->execute([$user['id']]);
        $chauffeur = $stmt->fetch();

        if (!$chauffeur) {
            http_response_code(404);
            echo json_encode(['erreur' => 'Chauffeur introuvable']);
            return;
        }

        echo json_encode($chauffeur);
    }

    // MODIFIER SON PROFIL
    public function modifierProfil(): void {
        $user = verifierToken();

        if ($user['role'] !== 'chauffeur') {
            http_response_code(403);
            echo json_encode(['erreur' => 'Accès réservé aux chauffeurs']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        $db   = getDB();
        $stmt = $db->prepare("
            UPDATE chauffeurs
            SET nom             = COALESCE(?, nom),
                prenom          = COALESCE(?, prenom),
                email           = COALESCE(?, email),
                marque_vehicule = COALESCE(?, marque_vehicule),
                ville           = COALESCE(?, ville)
            WHERE id = ?
        ");
        $stmt->execute([
            $data['nom']             ?? null,
            $data['prenom']          ?? null,
            $data['email']           ?? null,
            $data['marque_vehicule'] ?? null,
            $data['ville']           ?? null,
            $user['id']
        ]);

        echo json_encode(['message' => 'Profil mis à jour']);
    }

    // CHANGER MOT DE PASSE 
    public function changerMotDePasse(): void {
        $user = verifierToken();

        if ($user['role'] !== 'chauffeur') {
            http_response_code(403);
            echo json_encode(['erreur' => 'Accès réservé aux chauffeurs']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['ancien_mdp']) || empty($data['nouveau_mdp'])) {
            http_response_code(400);
            echo json_encode(['erreur' => 'Ancien et nouveau mot de passe requis']);
            return;
        }

        $db   = getDB();
        $stmt = $db->prepare("SELECT mot_de_passe FROM chauffeurs WHERE id = ?");
        $stmt->execute([$user['id']]);
        $chauffeur = $stmt->fetch();

        if (!password_verify($data['ancien_mdp'], $chauffeur['mot_de_passe'])) {
            http_response_code(401);
            echo json_encode(['erreur' => 'Ancien mot de passe incorrect']);
            return;
        }

        $hash = password_hash($data['nouveau_mdp'], PASSWORD_BCRYPT);
        $db->prepare("UPDATE chauffeurs SET mot_de_passe = ? WHERE id = ?")->execute([$hash, $user['id']]);

        echo json_encode(['message' => 'Mot de passe mis à jour']);
    }

    //METTRE À JOUR SA POSITION GPS
    public function mettreAJourPosition(): void {
        $user = verifierToken();

        if ($user['role'] !== 'chauffeur') {
            http_response_code(403);
            echo json_encode(['erreur' => 'Accès réservé aux chauffeurs']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['latitude']) || empty($data['longitude'])) {
            http_response_code(400);
            echo json_encode(['erreur' => 'Latitude et longitude requises']);
            return;
        }

        $db   = getDB();
        $stmt = $db->prepare("UPDATE chauffeurs SET latitude = ?, longitude = ? WHERE id = ?");
        $stmt->execute([$data['latitude'], $data['longitude'], $user['id']]);

        echo json_encode(['message' => 'Position mise à jour']);
    }

    //CHANGER SON STATUT
    public function changerStatut(): void {
        $user = verifierToken();

        if ($user['role'] !== 'chauffeur') {
            http_response_code(403);
            echo json_encode(['erreur' => 'Accès réservé aux chauffeurs']);
            return;
        }

        $data   = json_decode(file_get_contents('php://input'), true);
        $statuts = ['disponible', 'hors_ligne'];

        if (empty($data['statut']) || !in_array($data['statut'], $statuts)) {
            http_response_code(400);
            echo json_encode(['erreur' => 'Statut invalide (disponible ou hors_ligne)']);
            return;
        }

        $db   = getDB();
        $stmt = $db->prepare("UPDATE chauffeurs SET statut = ? WHERE id = ?");
        $stmt->execute([$data['statut'], $user['id']]);

        echo json_encode(['message' => 'Statut mis à jour : ' . $data['statut']]);
    }

    //VOIR SES COURSES TERMINÉES 
    public function mesCoursesTerminees(): void {
        $user = verifierToken();

        if ($user['role'] !== 'chauffeur') {
            http_response_code(403);
            echo json_encode(['erreur' => 'Accès réservé aux chauffeurs']);
            return;
        }

        $db   = getDB();
        $stmt = $db->prepare("
            SELECT c.*, u.nom AS passager_nom, u.prenom AS passager_prenom
            FROM courses c
            JOIN users u ON c.user_id = u.id
            WHERE c.chauffeur_id = ? AND c.statut = 'terminee'
            ORDER BY c.updated_at DESC
        ");
        $stmt->execute([$user['id']]);

        echo json_encode($stmt->fetchAll());
    }

    // VOIR SES GAINS TOTAUX 
    //     public function mesGains(): void {
        $user = verifierToken();

        if ($user['role'] !== 'chauffeur') {
            http_response_code(403);
            echo json_encode(['erreur' => 'Accès réservé aux chauffeurs']);
            return;
        }

        $db   = getDB();
        $stmt = $db->prepare("
            SELECT
                COUNT(*) AS total_courses,
                SUM(prix) AS gains_totaux,
                AVG(prix) AS gain_moyen
            FROM courses
            WHERE chauffeur_id = ? AND statut = 'terminee'
        ");
        $stmt->execute([$user['id']]);

        echo json_encode($stmt->fetch());
    }
}