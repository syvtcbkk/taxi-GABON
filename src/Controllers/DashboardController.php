<?php

namespace Controllers;

use Models\Vehicle;
use Models\Driver;
use Models\Trip;

class DashboardController {
    public function index() {
        // Optionnel : ne pas planter s'il n'y a pas de base de données (pour tester le design)
        try {
            $vehicleModel = new Vehicle();
            $driverModel = new Driver();
            $tripModel = new Trip();

            $stats = [
                'vehicles' => $vehicleModel->getCount(),
                'drivers' => $driverModel->getCount(),
                'trips' => $tripModel->getCount()
            ];
        } catch (\PDOException $e) {
            // Si la BD n'est pas encore créée, on met des fausses données pour voir le design
            $stats = [
                'vehicles' => 15,
                'drivers' => 8,
                'trips' => 4
            ];
        }

        $pageTitle = "Tableau de Bord";
        $content = __DIR__ . '/../Views/dashboard.php';
        require_once __DIR__ . '/../Views/layout.php';
    }
}
