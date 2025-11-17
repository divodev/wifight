<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/utils/logger.php';

$logger = get_logger('health');

$response = [
    'ok' => true,
    'checks' => [],
    'time' => date('c')
];

// DB check
$dbCheck = ['name' => 'database', 'ok' => false];
try {
    $database = new Database();
    $pdo = $database->getConnection();
    if ($pdo) {
        $dbCheck['ok'] = true;
        $dbCheck['message'] = 'Connected';
    } else {
        $dbCheck['message'] = 'No PDO connection';
    }
} catch (Exception $e) {
    $dbCheck['message'] = $e->getMessage();
    $logger->error('Health DB check error', ['exception' => $e->getMessage()]);
}
$response['checks'][] = $dbCheck;

// Storage writable check
$storage = __DIR__ . '/storage';
$writeCheck = ['name' => 'storage', 'ok' => false];
$temp = $storage . '/.health_write';
if (@file_put_contents($temp, 'ok') !== false) {
    unlink($temp);
    $writeCheck['ok'] = true;
    $writeCheck['message'] = 'Writable';
} else {
    $writeCheck['message'] = 'Not writable';
}
$response['checks'][] = $writeCheck;

header('Content-Type: application/json');
echo json_encode($response);