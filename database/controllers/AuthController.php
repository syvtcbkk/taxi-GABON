<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

class AuthController {

    // ─── PASSAGER : INSCRIPTION ───────────────────────────
    public function inscriptionPassager(): void {
        $data = json_decode(file_get_contents('php://input'), true);

        $champs = ['nom', 'prenom', 'telephone', 'mot_de_passe'];
        foreach ($champs as $champ) {
            if (empty($data[$champ])) {
                http_response_code(400);
                echo json_encode(['erreur' => "Champ '$champ' requis"]);
                return;
            }
        }

        $db   = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE telephone = ?");
        $stmt->execute([$data['telephone']]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['erreur' => 'Téléphone déjà utilisé']);
            return;
        }

        $hash = password_hash($data['mot_de_passe'], PASSWORD_BCRYPT);
        $stmt = $db->prepare("
            INSERT INTO users (nom, prenom, telephone, email, mot_de_passe, ville)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['nom'],
            $data['prenom'],
            $data['telephone'],
            $data['email'] ?? null,
            $hash,
            $data['ville'] ?? 'Libreville'
        ]);

        http_response_code(201);
        echo json_encode(['message' => 'Compte passager créé avec succès']);
    }

    // ─── PASSAGER : CONNEXION ─────────────────────────────
    public function connexionPassager(): void {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['telephone']) || empty($data['mot_de_passe'])) {
            http_response_code(400);
            echo json_encode(['erreur' => 'Téléphone et mot de passe requis']);
            return;
        }

        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE telephone = ?");
        $stmt->execute([$data['telephone']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($data['mot_de_passe'], $user['mot_de_passe'])) {
            http_response_code(401);
            echo json_encode(['erreur' => 'Identifiants incorrects']);
            return;
        }

        $token = genererToken([
            'id'   => $user['id'],
            'role' => 'passager',
            'nom'  => $user['nom']
        ]);

        echo json_encode([
            'message' => 'Connexion réussie',
            'token'   => $token,
            'user'    => [
                'id'     => $user['id'],
                'nom'    => $user['nom'],
                'prenom' => $user['prenom'],
                'ville'  => $user['ville']
            ]
        ]);
    }

    // ─── CHAUFFEUR : INSCRIPTION ──────────────────────────
    public function inscriptionChauffeur(): void {
        $data = json_decode(file_get_contents('php://input'), true);

        $champs = ['nom', 'prenom', 'telephone', 'mot_de_passe', 'numero_permis', 'immatriculation'];
        foreach ($champs as $champ) {
            if (empty($data[$champ])) {
                http_response_code(400);
                echo json_encode(['erreur' => "Champ '$champ' requis"]);
                return;
            }
        }

        $db   = getDB();
        $stmt = $db->prepare("SELECT id FROM chauffeurs WHERE telephone = ?");
        $stmt->execute([$data['telephone']]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['erreur' => 'Téléphone déjà utilisé']);
            return;
        }

        $hash = password_hash($data['mot_de_passe'], PASSWORD_BCRYPT);
        $stmt = $db->prepare("
            INSERT INTO chauffeurs (nom, prenom, telephone, email, mot_de_passe, numero_permis, immatriculation, marque_vehicule, ville)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['nom'],
            $data['prenom'],
            $data['telephone'],
            $data['email']           ?? null,
            $hash,
            $data['numero_permis'],
            $data['immatriculation'],
            $data['marque_vehicule'] ?? null,
            $data['ville']           ?? 'Libreville'
        ]);

        http_response_code(201);
        echo json_encode(['message' => 'Compte chauffeur créé avec succès']);
    }

    // ─── CHAUFFEUR : CONNEXION ────────────────────────────
    public function connexionChauffeur(): void {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['telephone']) || empty($data['mot_de_passe'])) {
            http_response_code(400);
            echo json_encode(['erreur' => 'Téléphone et mot de passe requis']);
            return;
        }

        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM chauffeurs WHERE telephone = ?");
        $stmt->execute([$data['telephone']]);
        $chauffeur = $stmt->fetch();

        if (!$chauffeur || !password_verify($data['mot_de_passe'], $chauffeur['mot_de_passe'])) {
            http_response_code(401);
            echo json_encode(['erreur' => 'Identifiants incorrects']);
            return;
        }

        $token = genererToken([
            'id'   => $chauffeur['id'],
            'role' => 'chauffeur',
            'nom'  => $chauffeur['nom']
        ]);

        echo json_encode([
            'message'   => 'Connexion réussie',
            'token'     => $token,
            'chauffeur' => [
                'id'               => $chauffeur['id'],
                'nom'              => $chauffeur['nom'],
                'prenom'           => $chauffeur['prenom'],
                'immatriculation'  => $chauffeur['immatriculation'],
                'ville'            => $chauffeur['ville']
            ]
        ]);
    }
}