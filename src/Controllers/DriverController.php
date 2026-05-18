<?php

namespace Controllers;

use Models\Driver;

class DriverController {
    public function index() {
        try {
            $driverModel = new Driver();
            $drivers = $driverModel->getAll();
        } catch (\PDOException $e) {
            $drivers = [];
        }

        $pageTitle = "Gestion des Chauffeurs";
        $content = __DIR__ . '/../Views/drivers/index.php';
        require_once __DIR__ . '/../Views/layout.php';
    }
}
