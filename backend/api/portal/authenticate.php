<?php
require_once '../../config/database.php';
require_once '../../config/cors.php';
require_once '../../models/Session.php';
require_once '../../services/OmadaAPI.php';
require_once '../../utils/Response.php';
require_once '../../utils/Logger.php';

$database = new Database();
$db = $database->getConnection();

$session = new Session($db);

// Get posted data
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->access_type)) {
    
    $accessType = $data->access_type; // voucher, free, social, payment
    
    switch ($accessType) {
        case 'free':
            // Free email-based access
            if (empty($data->email)) {
                Response::error('Email is required for free access', 400);
                exit;
            }
            
            // Create session for free access
            $session->mac_address = getMacAddress();
            $session->ip_address = $_SERVER['REMOTE_ADDR'];
            $session->username = $data->email;
            $session->controller_id = getDefaultController($db);
            $session->plan_id = getFreePlanId($db);
            $session->device_info = json_encode([
                'name' => $data->name ?? 'Guest',
                'email' => $data->email,
                'phone' => $data->phone ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            if ($session->create()) {
                // Authorize on Omada controller
                authorizeOnController($db, $session->controller_id, $session->mac_address, 30 * 60); // 30 minutes
                
                Logger::info('Free access granted', [
                    'session_id' => $session->id,
                    'email' => $data->email
                ]);
                
                Response::success([
                    'session_id' => $session->id,
                    'duration' => 30, // minutes
                    'data_limit' => 100, // MB
                    'message' => 'Free access granted for 30 minutes'
                ], 'Authentication successful');
            } else {
                Response::error('Failed to create session', 500);
            }
            break;
            
        case 'voucher':
            // Voucher-based access (handled separately in vouchers/redeem.php)
            Response::error('Use vouchers/redeem.php for voucher authentication', 400);
            break;
            
        case 'social':
            // Social login (Facebook/Google)
            if (empty($data->provider) || empty($data->access_token)) {
                Response::error('Provider and access token required', 400);
                exit;
            }
            
            // Verify social token and get user info
            $socialUser = verifySocialToken($data->provider, $data->access_token);
            
            if (!$socialUser) {
                Response::error('Invalid social login token', 401);
                exit;
            }
            
            // Create session
            $session->mac_address = getMacAddress();
            $session->ip_address = $_SERVER['REMOTE_ADDR'];
            $session->username = $socialUser['email'];
            $session->controller_id = getDefaultController($db);
            $session->plan_id = getFreePlanId($db);
            $session->device_info = json_encode([
                'name' => $socialUser['name'],
                'email' => $socialUser['email'],
                'provider' => $data->provider
            ]);
            
            if ($session->create()) {
                authorizeOnController($db, $session->controller_id, $session->mac_address, 60 * 60); // 1 hour
                
                Logger::info('Social login access granted', [
                    'session_id' => $session->id,
                    'provider' => $data->provider
                ]);
                
                Response::success([
                    'session_id' => $session->id,
                    'duration' => 60, // minutes
                    'message' => 'Access granted via ' . $data->provider
                ], 'Authentication successful');
            } else {
                Response::error('Failed to create session', 500);
            }
            break;
            
        default:
            Response::error('Invalid access type', 400);
    }
} else {
    Response::error('Access type is required', 400);
}

// Helper functions
function getMacAddress() {
    // In production, this should come from the network layer
    // For now, generate a pseudo MAC from IP
    $ip = $_SERVER['REMOTE_ADDR'];
    return strtoupper(substr(md5($ip), 0, 2) . ':' . 
           substr(md5($ip), 2, 2) . ':' . 
           substr(md5($ip), 4, 2) . ':' . 
           substr(md5($ip), 6, 2) . ':' . 
           substr(md5($ip), 8, 2) . ':' . 
           substr(md5($ip), 10, 2));
}

function getDefaultController($db) {
    $query = "SELECT id FROM controllers WHERE status = 'active' ORDER BY id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['id'] ?? 1;
}

function getFreePlanId($db) {
    $query = "SELECT id FROM plans WHERE name LIKE '%free%' OR price = 0 ORDER BY id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['id'] ?? null;
}

function authorizeOnController($db, $controllerId, $macAddress, $duration) {
    // Get controller details
    $query = "SELECT * FROM controllers WHERE id = :id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $controllerId);
    $stmt->execute();
    $controller = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($controller) {
        try {
            $omada = new OmadaAPI(
                $controller['ip_address'],
                $controller['port'],
                $controller['username'],
                base64_decode($controller['password']),
                $controller['site_id']
            );
            
            $omada->authorizeClient($macAddress, $duration);
        } catch (Exception $e) {
            Logger::error('Failed to authorize on controller', [
                'controller_id' => $controllerId,
                'error' => $e->getMessage()
            ]);
        }
    }
}

function verifySocialToken($provider, $token) {
    // This is a simplified version. In production, use proper OAuth verification
    
    if ($provider === 'facebook') {
        // Verify Facebook token
        $url = "https://graph.facebook.com/me?fields=id,name,email&access_token={$token}";
        $response = file_get_contents($url);
        $data = json_decode($response, true);
        
        if (isset($data['email'])) {
            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'email' => $data['email']
            ];
        }
    } elseif ($provider === 'google') {
        // Verify Google token
        $url = "https://www.googleapis.com/oauth2/v1/userinfo?access_token={$token}";
        $response = file_get_contents($url);
        $data = json_decode($response, true);
        
        if (isset($data['email'])) {
            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'email' => $data['email']
            ];
        }
    }
    
    return null;
}
?>