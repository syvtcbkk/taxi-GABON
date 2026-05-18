<?php

namespace Controllers;

use Models\Vehicle;

class VehicleController {
    public function index() {
        try {
            $vehicleModel = new Vehicle();
            $vehicles = $vehicleModel->getAll();
        } catch (\PDOException $e) {
            $vehicles = []; // Liste vide si pas de BD
        }

        $pageTitle = "Gestion des Véhicules";
        $content = __DIR__ . '/../Views/vehicles/index.php';
        require_once __DIR__ . '/../Views/layout.php';
    }
}
