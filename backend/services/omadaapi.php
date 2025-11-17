<?php
/**
 * TP-Link Omada Controller API Integration
 * Supports Omada SDN Controller v5.x
 */
class OmadaAPI {
    private $host;
    private $port;
    private $username;
    private $password;
    private $siteId;
    private $token;
    private $omadacId;
    
    public function __construct($host, $port, $username, $password, $siteId = 'default') {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->siteId = $siteId;
    }
    
    /**
     * Login to Omada Controller
     */
    public function login() {
        $url = "https://{$this->host}:{$this->port}/{$this->omadacId}/api/v2/login";
        
        $data = [
            'username' => $this->username,
            'password' => $this->password
        ];
        
        $response = $this->makeRequest($url, 'POST', $data, false);
        
        if ($response && isset($response['result']['token'])) {
            $this->token = $response['result']['token'];
            return true;
        }
        
        return false;
    }
    
    /**
     * Get controller information
     */
    public function getControllerInfo() {
        if (!$this->token && !$this->login()) {
            return false;
        }
        
        $url = "https://{$this->host}:{$this->port}/{$this->omadacId}/api/v2/info";
        return $this->makeRequest($url, 'GET');
    }
    
    /**
     * Get all sites
     */
    public function getSites() {
        if (!$this->token && !$this->login()) {
            return false;
        }
        
        $url = "https://{$this->host}:{$this->port}/{$this->omadacId}/api/v2/sites";
        return $this->makeRequest($url, 'GET');
    }
    
    /**
     * Get connected clients
     */
    public function getClients($siteId = null) {
        if (!$this->token && !$this->login()) {
            return false;
        }
        
        $site = $siteId ?: $this->siteId;
        $url = "https://{$this->host}:{$this->port}/{$this->omadacId}/api/v2/sites/{$site}/clients";
        
        return $this->makeRequest($url, 'GET');
    }
    
    /**
     * Authorize a client (MAC address)
     */
    public function authorizeClient($macAddress, $duration = 3600, $uploadLimit = null, $downloadLimit = null) {
        if (!$this->token && !$this->login()) {
            return false;
        }
        
        $url = "https://{$this->host}:{$this->port}/{$this->omadacId}/api/v2/sites/{$this->siteId}/cmd/authorize-guest";
        
        $data = [
            'mac' => $macAddress,
            'duration' => $duration,
            'uploadLimit' => $uploadLimit,
            'downloadLimit' => $downloadLimit
        ];
        
        return $this->makeRequest($url, 'POST', $data);
    }
    
    /**
     * Unauthorize/Block a client
     */
    public function blockClient($macAddress) {
        if (!$this->token && !$this->login()) {
            return false;
        }
        
        $url = "https://{$this->host}:{$this->port}/{$this->omadacId}/api/v2/sites/{$this->siteId}/cmd/unauthorize-guest";
        
        $data = ['mac' => $macAddress];
        
        return $this->makeRequest($url, 'POST', $data);
    }
    
    /**
     * Get guest portal settings
     */
    public function getPortalSettings() {
        if (!$this->token && !$this->login()) {
            return false;
        }
        
        $url = "https://{$this->host}:{$this->port}/{$this->omadacId}/api/v2/sites/{$this->siteId}/setting/guest";
        
        return $this->makeRequest($url, 'GET');
    }
    
    /**
     * Update guest portal settings
     */
    public function updatePortalSettings($settings) {
        if (!$this->token && !$this->login()) {
            return false;
        }
        
        $url = "https://{$this->host}:{$this->port}/{$this->omadacId}/api/v2/sites/{$this->siteId}/setting/guest";
        
        return $this->makeRequest($url, 'PATCH', $settings);
    }
    
    /**
     * Get statistics
     */
    public function getStatistics() {
        if (!$this->token && !$this->login()) {
            return false;
        }
        
        $url = "https://{$this->host}:{$this->port}/{$this->omadacId}/api/v2/sites/{$this->siteId}/stat";
        
        return $this->makeRequest($url, 'GET');
    }
    
    /**
     * Get access points
     */
    public function getAccessPoints() {
        if (!$this->token && !$this->login()) {
            return false;
        }
        
        $url = "https://{$this->host}:{$this->port}/{$this->omadacId}/api/v2/sites/{$this->siteId}/eaps";
        
        return $this->makeRequest($url, 'GET');
    }
    
    /**
     * Create voucher on Omada controller
     */
    public function createVoucher($quantity, $duration, $uploadLimit = null, $downloadLimit = null) {
        if (!$this->token && !$this->login()) {
            return false;
        }
        
        $url = "https://{$this->host}:{$this->port}/{$this->omadacId}/api/v2/sites/{$this->siteId}/vouchers";
        
        $data = [
            'count' => $quantity,
            'expireTime' => $duration,
            'uploadLimit' => $uploadLimit,
            'downloadLimit' => $downloadLimit,
            'type' => 'single'
        ];
        
        return $this->makeRequest($url, 'POST', $data);
    }
    
    /**
     * Test connection to controller
     */
    public function testConnection() {
        try {
            $info = $this->getControllerInfo();
            return [
                'success' => true,
                'message' => 'Connection successful',
                'data' => $info
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Make HTTP request to Omada API
     */
    private function makeRequest($url, $method = 'GET', $data = null, $useAuth = true) {
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        if ($useAuth && $this->token) {
            $headers[] = 'Csrf-Token: ' . $this->token;
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false, // Disable in production
            CURLOPT_SSL_VERIFYHOST => false, // Disable in production
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_COOKIEFILE => '', // Enable cookie handling
            CURLOPT_COOKIEJAR => ''
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }
        
        if ($httpCode >= 400) {
            throw new Exception("HTTP Error: " . $httpCode);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Logout from controller
     */
    public function logout() {
        if (!$this->token) {
            return true;
        }
        
        $url = "https://{$this->host}:{$this->port}/{$this->omadacId}/api/v2/logout";
        
        try {
            $this->makeRequest($url, 'POST');
            $this->token = null;
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>