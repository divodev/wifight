<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($db) {
    // Test query
    $query = "SELECT COUNT(*) as count FROM users";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "message" => "Database connection successful!",
        "users_count" => $result['count'],
        "server_time" => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed"
    ]);
}
