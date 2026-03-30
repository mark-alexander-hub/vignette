<?php

class Database {
    private $pdo;

    public function __construct() {
        try {
            $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . $charset,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            error_log('Vignette DB connection failed: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed');
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}