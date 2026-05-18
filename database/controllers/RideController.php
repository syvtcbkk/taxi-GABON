<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

class RideController {

    //CALCUL DISTANCE (Haversine) 
    private function calculerDistance(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $rayon = 6371; // km
        $dLat  = deg2rad($lat2 - $lat1);
        $dLng  = deg2rad($lng2 - $lng1);
        $a     = sin($dLat / 2) * sin($dLat / 2) +
                 cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
                 sin($dLng / 2) * sin($dLng / 2);
        $c     = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($rayon * $c, 2);
    }

    //CALCUL PRIX 
    private function calculerPrix(float $distance): float {
        $base      = 500;  // FCFA
        $parKm     = 200;  // FCFA par km
        return round($base + ($distance * $parKm), 2);
    }

    // PASSAGER : DEMANDER UNE COURSE 
    public function demanderCourse(): void {
        $user = verifierToken();

        if ($user['role'] !== 'passager') {
            http_response_code(403);
            echo json_encode(['erreur' => 'Accès réservé aux passagers']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        $champs = ['depart_adresse', 'depart_lat', 'depart_lng', 'arrivee_adresse', 'arrivee_lat', 'arrivee_lng'];
        foreach ($champs as $champ) {
            if (empty($data[$champ])) {
                http_response_code(400);
                echo json_encode(['erreur' => "Champ '$champ' requis"]);
                return;
            }
        }

        $distance = $this->calculerDistance(
            $data['depart_lat'], $data['depart_lng'],
            $data['arrivee_lat'], $data['arrivee_lng']
        );
        $prix = $this->calculerPrix($distance);

        $db   = getDB();
        $stmt = $db->prepare("
            INSERT INTO courses (user_id, depart_adresse, depart_lat, depart_lng, arrivee_adresse, arrivee_lat, arrivee_lng, prix)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['id'],
            $data['depart_adresse'],
            $data['depart_lat'],
            $data['depart_lng'],
            $data['arrivee_adresse'],
            $data['arrivee_lat'],
            $data['arrivee_lng'],
            $prix
        ]);

        http_response_code(201);
        echo json_encode([
            'message'  => 'Course demandée avec succès',
            'distance' => $distance . ' km',
            'prix'     => $prix . ' FCFA'
        ]);
    }

    //PASSAGER : VOIR SES COURSES 
    public function mesCourses(): void {
        $user = verifierToken();

        if ($user['role'] !== 'passager') {
            http_response_code(403);
            echo json_encode(['erreur' => 'Accès réservé aux passagers']);
            return;
        }

        $db   = getDB();
        $stmt = $db->prepare("
            SELECT c.*, ch.nom AS chauffeur_nom, ch.prenom AS chauffeur_prenom, ch.telephone AS chauffeur_tel
            FROM courses c
            LEFT JOIN chauffeurs ch ON c.chauffeur_id = ch.id
            WHERE c.user_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$user['id']]);

        echo json_encode($stmt->fetchAll());
    }

    //CHAUFFEUR : VOIR LES COURSES EN ATTENTE 
    public function coursesEnAttente(): void {
        $user = verifierToken();

        if ($user['role'] !== 'chauffeur') {
            http_response_code(403);
            echo json_encode(['erreur' => 'Accès réservé aux chauffeurs']);
            return;
        }

        $db   = getDB();
        $stmt = $db->prepare("
            SELECT c.*, u.nom AS passager_nom, u.prenom AS passager_prenom, u.telephone AS passager_tel
            FROM courses c
            JOIN users u ON c.user_id = u.id
            WHERE c.statut = 'en_attente'
            ORDER BY c.created_at ASC
        ");
        $stmt->execute();

        echo json_encode($stmt->fetchAll());
    }

    //CHAUFFEUR : ACCEPTER UNE COURSE 
    public function accepterCourse(int $id): void {
        $user = verifierToken();

        if ($user['role'] !== 'chauffeur') {
            http_response_code(403);
            echo json_encode(['erreur' => 'Accès réservé aux chauffeurs']);
            return;
        }

        $db   = getDB();

        // Vérifie que la course est bien en attente
        $stmt = $db->prepare("SELECT * FROM courses WHERE id = ? AND statut = 'en_attente'");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['erreur' => 'Course introuvable ou déjà prise']);
            return;
        }

        $stmt = $db->prepare("UPDATE courses SET chauffeur_id = ?, statut = 'acceptee' WHERE id = ?");
        $stmt->execute([$user['id'], $id]);

        // Met le chauffeur en_course
        $db->prepare("UPDATE chauffeurs SET statut = 'en_course' WHERE id = ?")->execute([$user['id']]);

        echo json_encode(['message' => 'Course acceptée']);
    }

    //CHAUFFEUR : DÉMARRER UNE COURSE 
    public function demarrerCourse(int $id): void {
        $user = verifierToken();

        if ($user['role'] !== 'chauffeur') {
            http_response_code(403);
            echo json_encode(['erreur' => 'Accès réservé aux chauffeurs']);
            return;
        }

        $db   = getDB();
        $stmt = $db->prepare("UPDATE courses SET statut = 'en_cours' WHERE id = ? AND chauffeur_id = ? AND statut = 'acceptee'");
        $stmt->execute([$id, $user['id']]);

        if ($stmt->rowCount() === 0) {
            http_response_code(400);
            echo json_encode(['erreur' => 'Impossible de démarrer cette course']);
            return;
        }

        echo json_encode(['message' => 'Course démarrée']);
    }

    //CHAUFFEUR : TERMINER UNE COURSE 
    public function terminerCourse(int $id): void {
        $user = verifierToken();

        if ($user['role'] !== 'chauffeur') {
            http_response_code(403);
            echo json_encode(['erreur' => 'Accès réservé aux chauffeurs']);
            return;
        }

        $db   = getDB();
        $stmt = $db->prepare("UPDATE courses SET statut = 'terminee' WHERE id = ? AND chauffeur_id = ? AND statut = 'en_cours'");
        $stmt->execute([$id, $user['id']]);

        if ($stmt->rowCount() === 0) {
            http_response_code(400);
            echo json_encode(['erreur' => 'Impossible de terminer cette course']);
            return;
        }

        // Remet le chauffeur disponible
        $db->prepare("UPDATE chauffeurs SET statut = 'disponible' WHERE id = ?")->execute([$user['id']]);

        echo json_encode(['message' => 'Course terminée']);
    }

    //ANNULER UNE COURSE 
    public function annulerCourse(int $id): void {
        $user = verifierToken();

        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->execute([$id]);
        $course = $stmt->fetch();

        if (!$course) {
            http_response_code(404);
            echo json_encode(['erreur' => 'Course introuvable']);
            return;
        }

        // Passager annule sa propre course / Chauffeur annule la sienne
        if ($user['role'] === 'passager' && $course['user_id'] != $user['id']) {
            http_response_code(403);
            echo json_encode(['erreur' => 'Non autorisé']);
            return;
        }

        if (in_array($course['statut'], ['terminee', 'annulee'])) {
            http_response_code(400);
            echo json_encode(['erreur' => 'Cette course ne peut pas être annulée']);
            return;
        }

        $db->prepare("UPDATE courses SET statut = 'annulee' WHERE id = ?")->execute([$id]);

        // Si chauffeur annule, le remettre disponible
        if ($user['role'] === 'chauffeur') {
            $db->prepare("UPDATE chauffeurs SET statut = 'disponible' WHERE id = ?")->execute([$user['id']]);
        }

        echo json_encode(['message' => 'Course annulée']);
    }
}