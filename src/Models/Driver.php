<?php

namespace Models;

use Config\Database;
use PDO;

class Driver {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll() {
        $stmt = $this->db->query("SELECT * FROM drivers ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    public function getCount() {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM drivers");
        return $stmt->fetch()['total'];
    }
}
