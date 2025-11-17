<?php
class Database {
    private $host = "127.0.0.1";  // Changed from "localhost"
    private $db_name = "wifight_db";
    private $username = "root";
    private $password = "";  // Add your MySQL password if you set one
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch(PDOException $exception) {
            error_log("DB Connection error: " . $exception->getMessage());
            return null;
        }
        return $this->conn;
    }
}