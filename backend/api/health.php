<?php
require_once '../config/database.php';
require_once '../config/cors.php';

$database = new Database();
$db = $database->getConnection();

echo json_encode([
    'success' => true,
    'message' => 'WiFight API is healthy',
    'timestamp' => date('Y-m-d H:i:s'),
    'database' => $db ? 'connected' : 'disconnected',
    'version' => '1.0.0'
]);
?>
