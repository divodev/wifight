<?php
require_once '../../config/database.php';
require_once '../../config/cors.php';
require_once '../../models/Controller.php';
require_once '../../services/OmadaAPI.php';
require_once '../../utils/JWT.php';
require_once '../../utils/Response.php';

// Authenticate user
$payload = JWT::authenticate();

$database = new Database();
$db = $database->getConnection();

// Get posted data
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->controller_id)) {
    $controller = new Controller($db);
    $controllerData = $controller->getById($data->controller_id);
    
    if (!$controllerData) {
        Response::error('Controller not found', 404);
        exit;
    }
    
    try {
        $omada = new OmadaAPI(
            $controllerData['ip_address'],
            $controllerData['port'],
            $controllerData['username'],
            $controllerData['password'],
            $controllerData['site_id']
        );
        
        $result = $omada->testConnection();
        
        if ($result['success']) {
            // Update controller version
            $controller->update($data->controller_id, [
                'status' => 'active',
                'version' => $result['data']['result']['version'] ?? 'unknown'
            ]);
            
            Response::success($result, 'Connection successful');
        } else {
            $controller->update($data->controller_id, ['status' => 'error']);
            Response::error($result['message'], 500);
        }
    } catch (Exception $e) {
        $controller->update($data->controller_id, ['status' => 'error']);
        Response::error('Connection failed: ' . $e->getMessage(), 500);
    }
} else {
    Response::error('Controller ID is required', 400);
}
?>