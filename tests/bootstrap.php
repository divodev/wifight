<?php
/**
 * PHPUnit Bootstrap File
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables for testing
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_DATABASE'] = 'wifight_isp_test';
$_ENV['DB_USERNAME'] = 'root';
$_ENV['DB_PASSWORD'] = '';
$_ENV['JWT_SECRET'] = 'test_secret_key_for_phpunit_testing_only';
$_ENV['JWT_EXPIRATION'] = '3600';
$_ENV['APP_DEBUG'] = 'true';

// Set up test database connection
putenv('DB_HOST=localhost');
putenv('DB_DATABASE=wifight_isp_test');
putenv('DB_USERNAME=root');
putenv('DB_PASSWORD=');

// Helper function to get test database connection
function getTestDatabase() {
    try {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=wifight_isp_test",
            'root',
            '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return $pdo;
    } catch (PDOException $e) {
        echo "Test database connection failed: " . $e->getMessage() . "\n";
        echo "Make sure to create the test database: CREATE DATABASE wifight_isp_test;\n";
        exit(1);
    }
}

// Create test database if it doesn't exist
try {
    $pdo = new PDO("mysql:host=localhost", 'root', '');
    $pdo->exec("CREATE DATABASE IF NOT EXISTS wifight_isp_test");
    echo "Test database ready\n";
} catch (PDOException $e) {
    echo "Warning: Could not create test database: " . $e->getMessage() . "\n";
}

echo "PHPUnit bootstrap complete\n";
