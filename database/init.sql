-- ============================================================
-- TAXI GABON — Schéma de base de données
-- ============================================================

CREATE DATABASE IF NOT EXISTS taxi_gabon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE taxi_gabon;

-- Utilisateurs (passagers & chauffeurs)
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    first_name      VARCHAR(80) NOT NULL,
    last_name       VARCHAR(80) NOT NULL,
    email           VARCHAR(191) NOT NULL UNIQUE,
    phone           VARCHAR(30)  NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('passenger','driver') NOT NULL DEFAULT 'passenger',
    is_verified     TINYINT(1) NOT NULL DEFAULT 0,
    verify_token    VARCHAR(64)  DEFAULT NULL,
    reset_code      VARCHAR(6)   DEFAULT NULL,
    reset_expires   DATETIME     DEFAULT NULL,
    avatar_url      VARCHAR(255) DEFAULT NULL,
    rating          DECIMAL(3,2) DEFAULT 5.00,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Profils chauffeurs (infos véhicule, statut)
CREATE TABLE driver_profiles (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL UNIQUE,
    vehicle_brand   VARCHAR(80)  DEFAULT NULL,
    vehicle_model   VARCHAR(80)  DEFAULT NULL,
    plate_number    VARCHAR(20)  DEFAULT NULL,
    is_available    TINYINT(1) NOT NULL DEFAULT 0,
    latitude        DECIMAL(10,8) DEFAULT NULL,
    longitude       DECIMAL(11,8) DEFAULT NULL,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Courses
CREATE TABLE rides (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    passenger_id    INT NOT NULL,
    driver_id       INT DEFAULT NULL,
    origin_address  VARCHAR(255) NOT NULL,
    origin_lat      DECIMAL(10,8) NOT NULL,
    origin_lng      DECIMAL(11,8) NOT NULL,
    dest_address    VARCHAR(255) NOT NULL,
    dest_lat        DECIMAL(10,8) NOT NULL,
    dest_lng        DECIMAL(11,8) NOT NULL,
    distance_km     DECIMAL(8,2) DEFAULT NULL,
    duration_min    INT          DEFAULT NULL,
    price_fcfa      INT          DEFAULT NULL,
    status          ENUM('pending','accepted','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
    passenger_rating TINYINT     DEFAULT NULL,
    driver_rating    TINYINT     DEFAULT NULL,
    accepted_at     DATETIME DEFAULT NULL,
    started_at      DATETIME DEFAULT NULL,
    cancel_reason   VARCHAR(255) DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at    DATETIME DEFAULT NULL,
    stripe_session_id VARCHAR(191) DEFAULT NULL,
    FOREIGN KEY (passenger_id) REFERENCES users(id),
    FOREIGN KEY (driver_id)    REFERENCES users(id)
) ENGINE=InnoDB;

-- Positions en temps réel (polling léger)
CREATE TABLE live_positions (
    user_id         INT NOT NULL PRIMARY KEY,
    latitude        DECIMAL(10,8) NOT NULL,
    longitude       DECIMAL(11,8) NOT NULL,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table des paiements Stripe pour idempotence et traçabilité
CREATE TABLE payments (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    stripe_session_id   VARCHAR(191) NOT NULL UNIQUE,
    passenger_id        INT NOT NULL,
    amount              INT NOT NULL,
    currency            VARCHAR(10) NOT NULL DEFAULT 'xaf',
    status              ENUM('created','paid','failed') NOT NULL DEFAULT 'created',
    metadata            JSON DEFAULT NULL,
    ride_id             INT DEFAULT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (passenger_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Revenus journaliers (vue dénormalisée pour les graphiques)
CREATE VIEW driver_daily_revenue AS
SELECT
    driver_id,
    DATE(completed_at)          AS day,
    COUNT(*)                    AS total_rides,
    COALESCE(SUM(price_fcfa),0) AS total_fcfa
FROM rides
WHERE status = 'completed' AND driver_id IS NOT NULL
GROUP BY driver_id, DATE(completed_at);

-- Reviews table: stocke avis entre utilisateurs après une course
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ride_id INT NOT NULL,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ride_id) REFERENCES rides(id) ON DELETE CASCADE,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE
 ) ENGINE=InnoDB;
