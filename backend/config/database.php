<?php
/**
 * WiFight ISP System - Database Configuration
 *
 * This file handles database connection and configuration
 */

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    private $conn;

    public function __construct() {
        $this->host = getenv('DB_HOST') ?: '127.0.0.1';
        $this->db_name = getenv('DB_DATABASE') ?: 'wifight_isp';
        $this->username = getenv('DB_USERNAME') ?: 'root';
        $this->password = getenv('DB_PASSWORD') ?: '';
        $this->port = getenv('DB_PORT') ?: '3306';
    }

    /**
     * Get database connection
     *
     * @return PDO|null
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset=utf8mb4";

            $this->conn = new PDO($dsn, $this->username, $this->password);

            // Set PDO attributes
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        } catch(PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed: " . $e->getMessage());
        }

        return $this->conn;
    }

    /**
     * Test database connection
     *
     * @return bool
     */
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            return $conn !== null;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get database statistics
     *
     * @return array
     */
    public function getStats() {
        try {
            $conn = $this->getConnection();

            $stats = [
                'database' => $this->db_name,
                'host' => $this->host,
                'port' => $this->port,
                'status' => 'connected',
                'tables' => []
            ];

            // Get table list
            $stmt = $conn->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                $countStmt = $conn->query("SELECT COUNT(*) as count FROM `{$table}`");
                $count = $countStmt->fetch(PDO::FETCH_ASSOC);
                $stats['tables'][$table] = $count['count'];
            }

            return $stats;

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Execute migration file
     *
     * @param string $sqlFile Path to SQL file
     * @return bool
     */
    public function executeMigration($sqlFile) {
        try {
            if (!file_exists($sqlFile)) {
                throw new Exception("Migration file not found: {$sqlFile}");
            }

            $sql = file_get_contents($sqlFile);
            $conn = $this->getConnection();

            // Split by semicolon and execute each statement
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                function($stmt) { return !empty($stmt); }
            );

            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $conn->exec($statement);
                }
            }

            return true;

        } catch (Exception $e) {
            error_log("Migration Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit() {
        return $this->conn->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->conn->rollBack();
    }
}
