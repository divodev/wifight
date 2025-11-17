<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Connection Test</h2>";

// Test 1: Direct PDO connection
echo "<h3>Test 1: Direct Connection</h3>";
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=wifight_db;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
}
    echo "✓ Direct PDO connection: <strong style='color:green'>SUCCESS</strong><br>";
    
    // Test query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "
