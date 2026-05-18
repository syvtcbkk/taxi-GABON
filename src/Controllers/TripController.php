<?php

namespace Controllers;

use Models\Trip;

class TripController {
    public function index() {
        try {
            $tripModel = new Trip();
            $trips = $tripModel->getAll();
        } catch (\PDOException $e) {
            $trips = [];
        }

        $pageTitle = "Gestion des Trajets";
        $content = __DIR__ . '/../Views/trips/index.php';
        require_once __DIR__ . '/../Views/layout.php';
    }
}
