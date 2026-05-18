<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/RideController.php';
require_once __DIR__ . '/controllers/DriverController.php';

$uri    = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'];

$auth = new AuthController();
$ride = new RideController();
$driver = new DriverController();


// Extraire l'ID depuis l'URL (ex: api/courses/5)
$parties = explode('/', $uri);
$id      = isset($parties[2]) ? (int)$parties[2] : null;
$base    = $parties[0] . '/' . $parties[1];
$action  = $parties[3] ?? null;

switch (true) {

    //AUTH PASSAGER
    case $uri === 'api/inscription/passager' && $method === 'POST':
        $auth->inscriptionPassager();
        break;

    case $uri === 'api/connexion/passager' && $method === 'POST':
        $auth->connexionPassager();
        break;

    //AUTH CHAUFFEUR
    case $uri === 'api/inscription/chauffeur' && $method === 'POST':
        $auth->inscriptionChauffeur();
        break;

    case $uri === 'api/connexion/chauffeur' && $method === 'POST':
        $auth->connexionChauffeur();
        break;

    // COURSES 
    case $uri === 'api/courses' && $method === 'POST':
        $ride->demanderCourse();
        break;

    case $uri === 'api/courses' && $method === 'GET':
        $ride->mesCourses();
        break;

    case $uri === 'api/courses/attente' && $method === 'GET':
        $ride->coursesEnAttente();
        break;

    case $base === 'api/courses' && $action === 'accepter' && $method === 'PUT':
        $ride->accepterCourse($id);
        break;

    case $base === 'api/courses' && $action === 'demarrer' && $method === 'PUT':
        $ride->demarrerCourse($id);
        break;

    case $base === 'api/courses' && $action === 'terminer' && $method === 'PUT':
        $ride->terminerCourse($id);
        break;

    case $base === 'api/courses' && $action === 'annuler' && $method === 'PUT':
        $ride->annulerCourse($id);
        break;
     
        //CHAUFFEUR 
    case $uri === 'api/chauffeur/profil' && $method === 'GET':
        $driver->monProfil();
        break;

    case $uri === 'api/chauffeur/profil' && $method === 'PUT':
        $driver->modifierProfil();
        break;

    case $uri === 'api/chauffeur/mot-de-passe' && $method === 'PUT':
        $driver->changerMotDePasse();
        break;

    case $uri === 'api/chauffeur/position' && $method === 'PUT':
        $driver->mettreAJourPosition();
        break;

    case $uri === 'api/chauffeur/statut' && $method === 'PUT':
        $driver->changerStatut();
        break;

    case $uri === 'api/chauffeur/courses' && $method === 'GET':
        $driver->mesCoursesTerminees();
        break;

    case $uri === 'api/chauffeur/gains' && $method === 'GET':
        $driver->mesGains();
        break;
    //ROUTE INCONNUE
    default:
        http_response_code(404);
        echo json_encode(['erreur' => 'Route introuvable']);
        break;
}