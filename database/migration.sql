-- Base de données : taxi_gabon
CREATE DATABASE IF NOT EXISTS taxi_gabon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE taxi_gabon;

-- Table des utilisateurs (passagers)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    telephone VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(150) UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    ville ENUM('Libreville', 'Port-Gentil', 'Franceville', 'Oyem') DEFAULT 'Libreville',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des chauffeurs
CREATE TABLE chauffeurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    telephone VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(150) UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    numero_permis VARCHAR(50) NOT NULL UNIQUE,
    immatriculation VARCHAR(30) NOT NULL UNIQUE,
    marque_vehicule VARCHAR(100),
    ville ENUM('Libreville', 'Port-Gentil', 'Franceville', 'Oyem') DEFAULT 'Libreville',
    statut ENUM('disponible', 'en_course', 'hors_ligne') DEFAULT 'hors_ligne',
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des courses
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    chauffeur_id INT,
    depart_adresse VARCHAR(255) NOT NULL,
    depart_lat DECIMAL(10, 8) NOT NULL,
    depart_lng DECIMAL(11, 8) NOT NULL,
    arrivee_adresse VARCHAR(255) NOT NULL,
    arrivee_lat DECIMAL(10, 8) NOT NULL,
    arrivee_lng DECIMAL(11, 8) NOT NULL,
    statut ENUM('en_attente', 'acceptee', 'en_cours', 'terminee', 'annulee') DEFAULT 'en_attente',
    prix DECIMAL(10, 2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (chauffeur_id) REFERENCES chauffeurs(id) ON DELETE SET NULL
);