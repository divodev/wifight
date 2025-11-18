# WiFight ISP Billing & Management System - Development Plan (Part 1)

## Executive Summary

WiFight is an advanced Internet Service Provider (ISP) billing and management system designed to integrate with multiple network controller platforms including **MikroTik**, **TP-Link Omada**, **Ruijie Networks**, and **Cisco Meraki**. This comprehensive development plan provides a complete roadmap to build a robust, scalable, multi-vendor ISP management solution.

---

## Table of Contents

1. [System Architecture Overview](#1-system-architecture-overview)
2. [Current System Analysis](#2-current-system-analysis)
3. [Multi-Vendor Controller Integration](#3-multi-vendor-controller-integration)
4. [Database Architecture Enhancement](#4-database-architecture-enhancement)
5. [API Layer Development](#5-api-layer-development)
6. [Authentication & Security Systems](#6-authentication--security-systems)
7. [Billing System Enhancement](#7-billing-system-enhancement)
8. [Portal Development](#8-portal-development)

---

## 1. System Architecture Overview

### 1.1 High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    WiFight ISP System                       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐    │
│  │   Frontend   │  │  Admin Panel │  │ User Portal  │    │
│  │  Dashboard   │  │              │  │              │    │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘    │
│         │                  │                  │            │
│         └──────────────────┼──────────────────┘            │
│                            │                               │
│                   ┌────────▼────────┐                      │
│                   │   REST API      │                      │
│                   │   Layer         │                      │
│                   └────────┬────────┘                      │
│                            │                               │
│         ┌──────────────────┼──────────────────┐           │
│         │                  │                  │           │
│    ┌────▼─────┐   ┌───────▼────┐   ┌────────▼─────┐     │
│    │Controller│   │  Billing   │   │    User      │     │
│    │Abstraction│   │  Engine    │   │ Management   │     │
│    │  Layer   │   │            │   │              │     │
│    └────┬─────┘   └───────┬────┘   └────────┬─────┘     │
│         │                  │                  │           │
│    ┌────▼─────────────────────────────────────▼─────┐    │
│    │           Database Layer (MySQL)              │    │
│    │   - Users  - Plans  - Sessions  - Payments    │    │
│    └────────────────────────────────────────────────┘    │
│                                                           │
└───────────────────────┬───────────────────────────────────┘
                        │
        ┌───────────────┼───────────────┐
        │               │               │
   ┌────▼────┐   ┌─────▼─────┐   ┌────▼─────┐
   │MikroTik │   │  Omada    │   │  Ruijie  │
   │Controllers│  │Controllers│   │Controllers│
   └──────────┘   └───────────┘   └──────────┘
```

### 1.2 Core Components

#### **Frontend Layer**
- **Admin Dashboard**: Management interface for ISP operators
- **User Portal**: Self-service portal for customers
- **Captive Portal**: Public WiFi access portal

#### **Backend Layer**
- **REST API**: RESTful endpoints for all operations
- **Controller Abstraction Layer (CAL)**: Unified interface for multi-vendor controllers
- **Billing Engine**: Automated billing, payment processing, and plan management
- **Authentication Service**: JWT-based authentication with role-based access control
- **RADIUS Server**: Centralized authentication for network access

#### **Integration Layer**
- **MikroTik Integration**: RouterOS API for hotspot management
- **Omada Integration**: SDN Controller API for unified network management
- **Ruijie Integration**: Cloud Controller API for enterprise WiFi
- **Cisco Meraki Integration**: Dashboard API for cloud networking

#### **Data Layer**
- **MySQL Database**: Primary data storage
- **Redis Cache**: Session caching and performance optimization
- **File Storage**: Log files, receipts, and reports

---

## 2. Current System Analysis

### 2.1 Existing Features

Based on the codebase analysis, WiFight currently includes:

**✅ User Management**
- User registration and authentication
- Role-based access (Admin, Reseller, User)
- Profile management
- Password reset functionality

**✅ Basic Plan Management**
- Internet plan creation (bandwidth, duration, price)
- Plan assignment to users
- Plan expiration tracking

**✅ Controller Integration (Partial)**
- Basic MikroTik integration
- Initial Omada controller connection
- Configuration storage

**✅ Payment System (Basic)**
- Manual payment recording
- Payment history
- Transaction tracking

**✅ Session Management**
- Active session monitoring
- Basic usage tracking
- Session termination

### 2.2 Gaps & Required Enhancements

**❌ Missing Critical Features:**

1. **Incomplete Multi-Vendor Support**
   - Ruijie controller integration not implemented
   - Cisco Meraki integration missing
   - No unified controller abstraction layer

2. **Limited RADIUS Integration**
   - No centralized RADIUS server
   - Missing RADIUS accounting
   - No real-time usage tracking

3. **Basic Captive Portal**
   - No multi-vendor parameter support
   - Limited customization options
   - No voucher system integration

4. **Minimal Billing Automation**
   - No automated recurring billing
   - Missing payment gateway integration
   - No invoice generation

5. **Security Concerns**
   - Basic JWT implementation
   - No 2FA support
   - Limited audit logging

6. **Scalability Issues**
   - No caching mechanism
   - Database not optimized
   - No load balancing consideration

---

## 3. Multi-Vendor Controller Integration

### 3.1 Controller Abstraction Layer (CAL)

The CAL provides a unified interface for managing different controller types.

#### **Interface Design**

```php
<?php
// backend/services/controllers/ControllerInterface.php

interface ControllerInterface {
    /**
     * Connect to the controller
     */
    public function connect(array $credentials): bool;
    
    /**
     * Authenticate a user on the network
     */
    public function authenticateUser(string $mac, string $username, array $plan): bool;
    
    /**
     * Disconnect user from the network
     */
    public function disconnectUser(string $mac): bool;
    
    /**
     * Get active sessions
     */
    public function getActiveSessions(): array;
    
    /**
     * Get user session details
     */
    public function getUserSession(string $mac): ?array;
    
    /**
     * Update user bandwidth
     */
    public function updateBandwidth(string $mac, int $uploadSpeed, int $downloadSpeed): bool;
    
    /**
     * Get controller status and statistics
     */
    public function getControllerStatus(): array;
    
    /**
     * Create or update RADIUS profile
     */
    public function createRadiusProfile(array $profileData): bool;
    
    /**
     * Test connection to controller
     */
    public function testConnection(): bool;
}
```

#### **Factory Pattern for Controller Selection**

```php
<?php
// backend/services/controllers/ControllerFactory.php

class ControllerFactory {
    
    public static function create(string $type, array $config): ControllerInterface {
        switch (strtolower($type)) {
            case 'mikrotik':
                return new MikrotikController($config);
            
            case 'omada':
                return new OmadaController($config);
            
            case 'ruijie':
                return new RuijieController($config);
            
            case 'meraki':
            case 'cisco':
                return new MerakiController($config);
            
            default:
                throw new Exception("Unsupported controller type: {$type}");
        }
    }
    
    public static function getSupportedTypes(): array {
        return ['mikrotik', 'omada', 'ruijie', 'meraki', 'cisco'];
    }
}
```

---

### 3.2 MikroTik RouterOS Integration

#### **Overview**
MikroTik routers use the RouterOS API for programmatic access. WiFight integrates via PHP RouterOS API library.

#### **Implementation**

```php
<?php
// backend/services/controllers/MikrotikController.php

require_once __DIR__ . '/../../vendor/autoload.php';
use RouterOS\Client;
use RouterOS\Query;

class MikrotikController implements ControllerInterface {
    
    private $client;
    private $config;
    private $logger;
    
    public function __construct(array $config) {
        $this->config = $config;
        $this->logger = new Logger('MikroTik');
    }
    
    public function connect(array $credentials): bool {
        try {
            $this->client = new Client([
                'host' => $credentials['host'],
                'user' => $credentials['username'],
                'pass' => $credentials['password'],
                'port' => $credentials['port'] ?? 8728,
            ]);
            
            $this->logger->info("Connected to MikroTik at {$credentials['host']}");
            return true;
            
        } catch (Exception $e) {
            $this->logger->error("MikroTik connection failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function authenticateUser(string $mac, string $username, array $plan): bool {
        try {
            // Create user profile in User Manager
            $profileQuery = new Query('/tool/user-manager/user/add');
            $profileQuery->equal('name', $username);
            $profileQuery->equal('password', $this->generatePassword());
            $profileQuery->equal('group', $plan['group_name']);
            
            $this->client->query($profileQuery)->read();
            
            // Add active session to IP Bindings
            $bindQuery = new Query('/ip/hotspot/ip-binding/add');
            $bindQuery->equal('mac-address', $mac);
            $bindQuery->equal('address', $this->allocateIP());
            $bindQuery->equal('type', 'regular');
            $bindQuery->equal('server', $this->config['hotspot_server']);
            
            $this->client->query($bindQuery)->read();
            
            // Create bandwidth profile
            $this->createBandwidthProfile($username, $plan);
            
            $this->logger->info("User {$username} authenticated on MikroTik");
            return true;
            
        } catch (Exception $e) {
            $this->logger->error("Authentication failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function disconnectUser(string $mac): bool {
        try {
            // Find and remove active session
            $query = new Query('/ip/hotspot/active/print');
            $query->where('mac-address', $mac);
            
            $activeSessions = $this->client->query($query)->read();
            
            foreach ($activeSessions as $session) {
                $removeQuery = new Query('/ip/hotspot/active/remove');
                $removeQuery->equal('.id', $session['.id']);
                $this->client->query($removeQuery)->read();
            }
            
            // Remove IP binding
            $bindQuery = new Query('/ip/hotspot/ip-binding/print');
            $bindQuery->where('mac-address', $mac);
            
            $bindings = $this->client->query($bindQuery)->read();
            
            foreach ($bindings as $binding) {
                $removeBindQuery = new Query('/ip/hotspot/ip-binding/remove');
                $removeBindQuery->equal('.id', $binding['.id']);
                $this->client->query($removeBindQuery)->read();
            }
            
            $this->logger->info("User with MAC {$mac} disconnected from MikroTik");
            return true;
            
        } catch (Exception $e) {
            $this->logger->error("Disconnect failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function getActiveSessions(): array {
        try {
            $query = new Query('/ip/hotspot/active/print');
            $sessions = $this->client->query($query)->read();
            
            $formattedSessions = [];
            foreach ($sessions as $session) {
                $formattedSessions[] = [
                    'mac' => $session['mac-address'],
                    'ip' => $session['address'],
                    'username' => $session['user'],
                    'uptime' => $session['uptime'],
                    'bytes_in' => $session['bytes-in'],
                    'bytes_out' => $session['bytes-out'],
                    'controller_type' => 'mikrotik'
                ];
            }
            
            return $formattedSessions;
            
        } catch (Exception $e) {
            $this->logger->error("Failed to get active sessions: " . $e->getMessage());
            return [];
        }
    }
    
    public function getUserSession(string $mac): ?array {
        try {
            $query = new Query('/ip/hotspot/active/print');
            $query->where('mac-address', $mac);
            
            $sessions = $this->client->query($query)->read();
            
            if (empty($sessions)) {
                return null;
            }
            
            $session = $sessions[0];
            return [
                'mac' => $session['mac-address'],
                'ip' => $session['address'],
                'username' => $session['user'],
                'uptime' => $session['uptime'],
                'bytes_in' => $session['bytes-in'],
                'bytes_out' => $session['bytes-out'],
                'controller_type' => 'mikrotik'
            ];
            
        } catch (Exception $e) {
            $this->logger->error("Failed to get user session: " . $e->getMessage());
            return null;
        }
    }
    
    public function updateBandwidth(string $mac, int $uploadSpeed, int $downloadSpeed): bool {
        try {
            // Find user's queue
            $query = new Query('/queue/simple/print');
            $query->where('target', $this->getMacIP($mac));
            
            $queues = $this->client->query($query)->read();
            
            foreach ($queues as $queue) {
                $updateQuery = new Query('/queue/simple/set');
                $updateQuery->equal('.id', $queue['.id']);
                $updateQuery->equal('max-limit', "{$uploadSpeed}M/{$downloadSpeed}M");
                
                $this->client->query($updateQuery)->read();
            }
            
            $this->logger->info("Bandwidth updated for MAC {$mac}");
            return true;
            
        } catch (Exception $e) {
            $this->logger->error("Bandwidth update failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function getControllerStatus(): array {
        try {
            $query = new Query('/system/resource/print');
            $resources = $this->client->query($query)->read();
            
            if (empty($resources)) {
                return ['status' => 'offline'];
            }
            
            $resource = $resources[0];
            
            return [
                'status' => 'online',
                'uptime' => $resource['uptime'],
                'cpu_load' => $resource['cpu-load'],
                'free_memory' => $resource['free-memory'],
                'total_memory' => $resource['total-memory'],
                'version' => $resource['version'],
                'board_name' => $resource['board-name']
            ];
            
        } catch (Exception $e) {
            $this->logger->error("Status check failed: " . $e->getMessage());
            return ['status' => 'offline', 'error' => $e->getMessage()];
        }
    }
    
    public function createRadiusProfile(array $profileData): bool {
        try {
            $query = new Query('/radius/add');
            $query->equal('service', 'hotspot');
            $query->equal('address', $profileData['radius_server']);
            $query->equal('secret', $profileData['radius_secret']);
            $query->equal('timeout', '3000ms');
            
            $this->client->query($query)->read();
            
            $this->logger->info("RADIUS profile created");
            return true;
            
        } catch (Exception $e) {
            $this->logger->error("RADIUS profile creation failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function testConnection(): bool {
        try {
            $query = new Query('/system/identity/print');
            $this->client->query($query)->read();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    // Helper methods
    
    private function createBandwidthProfile(string $username, array $plan): void {
        $query = new Query('/queue/simple/add');
        $query->equal('name', "user-{$username}");
        $query->equal('target', $this->getUserIP($username));
        $query->equal('max-limit', "{$plan['upload_speed']}M/{$plan['download_speed']}M");
        
        $this->client->query($query)->read();
    }
    
    private function generatePassword(int $length = 12): string {
        return bin2hex(random_bytes($length / 2));
    }
    
    private function allocateIP(): string {
        // Implement IP allocation logic from your pool
        // This is a placeholder
        return '10.10.10.' . rand(2, 254);
    }
    
    private function getMacIP(string $mac): string {
        $query = new Query('/ip/hotspot/active/print');
        $query->where('mac-address', $mac);
        
        $sessions = $this->client->query($query)->read();
        return !empty($sessions) ? $sessions[0]['address'] : '';
    }
    
    private function getUserIP(string $username): string {
        $query = new Query('/ip/hotspot/active/print');
        $query->where('user', $username);
        
        $sessions = $this->client->query($query)->read();
        return !empty($sessions) ? $sessions[0]['address'] : '';
    }
}
```

#### **MikroTik Configuration Requirements**

**Router Setup:**
```bash
# Enable API
/ip service set api address=0.0.0.0/0 disabled=no

# Create API user
/user add name=wifight-api password=SecurePassword123 group=full

# Configure Hotspot
/ip hotspot add name=wifight-hotspot interface=wlan1

# Configure RADIUS
/radius add service=hotspot address=192.168.1.100 secret=radius-secret

# Create bandwidth profiles
/queue simple add name=default-1mbps max-limit=1M/1M
/queue simple add name=default-5mbps max-limit=5M/5M
```

---

### 3.3 TP-Link Omada Controller Integration

#### **Overview**
Omada SDN Controller provides centralized management for TP-Link EAPs, switches, and gateways. Integration uses the Omada Controller API v5.x.

**Official Documentation**: https://support.omadanetworks.com/en/document/13080/

#### **Implementation**

```php
<?php
// backend/services/controllers/OmadaController.php

class OmadaController implements ControllerInterface {
    
    private $baseUrl;
    private $token;
    private $siteId;
    private $logger;
    private $omadaId; // Unique controller ID
    
    public function __construct(array $config) {
        $this->baseUrl = rtrim($config['url'], '/');
        $this->siteId = $config['site_id'] ?? 'Default';
        $this->logger = new Logger('Omada');
    }
    
    public function connect(array $credentials): bool {
        try {
            // Login to Omada Controller
            $loginUrl = "{$this->baseUrl}/{$this->omadaId}/api/v2/login";
            
            $response = $this->httpPost($loginUrl, [
                'username' => $credentials['username'],
                'password' => $credentials['password']
            ]);
            
            if ($response['errorCode'] == 0) {
                $this->token = $response['result']['token'];
                $this->logger->info("Connected to Omada Controller");
                return true;
            }
            
            $this->logger->error("Omada login failed: " . $response['msg']);
            return false;
            
        } catch (Exception $e) {
            $this->logger->error("Omada connection failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function authenticateUser(string $mac, string $username, array $plan): bool {
        try {
            // Get site information first
            $sites = $this->getSites();
            $siteId = $sites[0]['key'] ?? 'Default';
            
            // Create guest authorization
            $url = "{$this->baseUrl}/{$this->omadaId}/api/v2/sites/{$siteId}/guests";
            
            $guestData = [
                'name' => $username,
                'mac' => strtoupper(str_replace([':', '-'], '', $mac)),
                'uploadLimit' => $plan['upload_speed'] * 1024, // Convert to Kbps
                'downloadLimit' => $plan['download_speed'] * 1024,
                'expireTime' => time() + ($plan['duration'] * 24 * 3600), // Days to seconds
                'authType' => 1 // MAC-based auth
            ];
            
            $response = $this->httpPost($url, $guestData, $this->token);
            
            if ($response['errorCode'] == 0) {
                $this->logger->info("User {$username} authenticated on Omada");
                return true;
            }
            
            $this->logger->error("Omada authentication failed: " . $response['msg']);
            return false;
            
        } catch (Exception $e) {
            $this->logger->error("Authentication failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function disconnectUser(string $mac): bool {
        try {
            $sites = $this->getSites();
            $siteId = $sites[0]['key'] ?? 'Default';
            
            // Get all guests
            $url = "{$this->baseUrl}/{$this->omadaId}/api/v2/sites/{$siteId}/guests";
            $response = $this->httpGet($url, $this->token);
            
            if ($response['errorCode'] == 0) {
                $guests = $response['result']['data'];
                
                foreach ($guests as $guest) {
                    if (strtoupper(str_replace([':', '-'], '', $guest['mac'])) === 
                        strtoupper(str_replace([':', '-'], '', $mac))) {
                        
                        // Delete guest
                        $deleteUrl = "{$this->baseUrl}/{$this->omadaId}/api/v2/sites/{$siteId}/guests/{$guest['id']}";
                        $deleteResponse = $this->httpDelete($deleteUrl, $this->token);
                        
                        if ($deleteResponse['errorCode'] == 0) {
                            $this->logger->info("User with MAC {$mac} disconnected from Omada");
                            return true;
                        }
                    }
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logger->error("Disconnect failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function getActiveSessions(): array {
        try {
            $sites = $this->getSites();
            $siteId = $sites[0]['key'] ?? 'Default';
            
            $url = "{$this->baseUrl}/{$this->omadaId}/api/v2/sites/{$siteId}/clients";
            $response = $this->httpGet($url, $this->token);
            
            if ($response['errorCode'] != 0) {
                return [];
            }
            
            $sessions = [];
            foreach ($response['result']['data'] as $client) {
                $sessions[] = [
                    'mac' => $client['mac'],
                    'ip' => $client['ip'],
                    'username' => $client['name'] ?? 'Guest',
                    'uptime' => $client['uptime'],
                    'bytes_in' => $client['rxBytes'],
                    'bytes_out' => $client['txBytes'],
                    'signal' => $client['signalLevel'],
                    'ssid' => $client['ssid'],
                    'controller_type' => 'omada'
                ];
            }
            
            return $sessions;
            
        } catch (Exception $e) {
            $this->logger->error("Failed to get active sessions: " . $e->getMessage());
            return [];
        }
    }
    
    public function getUserSession(string $mac): ?array {
        $sessions = $this->getActiveSessions();
        
        foreach ($sessions as $session) {
            if (strcasecmp($session['mac'], $mac) === 0) {
                return $session;
            }
        }
        
        return null;
    }
    
    public function updateBandwidth(string $mac, int $uploadSpeed, int $downloadSpeed): bool {
        try {
            // Omada requires recreating the guest with new bandwidth limits
            $session = $this->getUserSession($mac);
            
            if (!$session) {
                return false;
            }
            
            // Disconnect and reconnect with new limits
            $this->disconnectUser($mac);
            
            return $this->authenticateUser($mac, $session['username'], [
                'upload_speed' => $uploadSpeed,
                'download_speed' => $downloadSpeed,
                'duration' => 30 // Default duration
            ]);
            
        } catch (Exception $e) {
            $this->logger->error("Bandwidth update failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function getControllerStatus(): array {
        try {
            $url = "{$this->baseUrl}/{$this->omadaId}/api/v2/users/current";
            $response = $this->httpGet($url, $this->token);
            
            if ($response['errorCode'] == 0) {
                $sites = $this->getSites();
                
                return [
                    'status' => 'online',
                    'version' => $response['result']['controllerVer'] ?? 'Unknown',
                    'sites' => count($sites),
                    'type' => 'Omada SDN Controller'
                ];
            }
            
            return ['status' => 'offline'];
            
        } catch (Exception $e) {
            return ['status' => 'offline', 'error' => $e->getMessage()];
        }
    }
    
    public function createRadiusProfile(array $profileData): bool {
        try {
            $sites = $this->getSites();
            $siteId = $sites[0]['key'] ?? 'Default';
            
            $url = "{$this->baseUrl}/{$this->omadaId}/api/v2/sites/{$siteId}/setting/profiles/radiusProfiles";
            
            $radiusData = [
                'name' => 'WiFight-RADIUS',
                'authServer' => $profileData['radius_server'],
                'authPort' => $profileData['auth_port'] ?? 1812,
                'authSecret' => $profileData['radius_secret'],
                'acctServer' => $profileData['radius_server'],
                'acctPort' => $profileData['acct_port'] ?? 1813,
                'acctSecret' => $profileData['radius_secret']
            ];
            
            $response = $this->httpPost($url, $radiusData, $this->token);
            
            return $response['errorCode'] == 0;
            
        } catch (Exception $e) {
            $this->logger->error("RADIUS profile creation failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function testConnection(): bool {
        try {
            $url = "{$this->baseUrl}/{$this->omadaId}/api/v2/users/current";
            $response = $this->httpGet($url, $this->token);
            return $response['errorCode'] == 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    // Helper methods
    
    private function getSites(): array {
        $url = "{$this->baseUrl}/{$this->omadaId}/api/v2/sites";
        $response = $this->httpGet($url, $this->token);
        
        return $response['errorCode'] == 0 ? $response['result']['data'] : [];
    }
    
    private function httpGet(string $url, ?string $token = null): array {
        return $this->httpRequest('GET', $url, null, $token);
    }
    
    private function httpPost(string $url, array $data, ?string $token = null): array {
        return $this->httpRequest('POST', $url, $data, $token);
    }
    
    private function httpDelete(string $url, ?string $token = null): array {
        return $this->httpRequest('DELETE', $url, null, $token);
    }
    
    private function httpRequest(string $method, string $url, ?array $data, ?string $token): array {
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        if ($token) {
            $headers[] = "Csrf-Token: {$token}";
            curl_setopt($ch, CURLOPT_COOKIE, "TPEAP_SESSIONID={$token}");
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => $method
        ]);
        
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            throw new Exception("HTTP Error {$httpCode}");
        }
        
        return json_decode($response, true) ?? [];
    }
}
```

#### **Omada Controller Configuration**

**Controller Setup:**
1. **Enable External Portal**
   - Settings → Authentication → Portal
   - Enable External Portal Server
   - Set Portal URL: `http://your-wifight-server.com/portal/`

2. **Configure RADIUS (Optional)**
   - Settings → Authentication → RADIUS
   - Add RADIUS Profile with WiFight server details

3. **API Access**
   - Create dedicated API user with admin privileges
   - Note the Omada ID from controller URL

**Network Requirements:**
- Controller must be accessible from WiFight server
- Ports: 8043 (HTTPS) or 8088 (HTTP)
- External portal must be accessible from client network

---

### 3.4 Ruijie Networks Integration

#### **Overview**
Ruijie provides enterprise-grade wireless solutions with cloud management. Integration uses Ruijie Cloud Controller API.

#### **Implementation**

```php
<?php
// backend/services/controllers/RuijieController.php

class RuijieController implements ControllerInterface {
    
    private $baseUrl;
    private $apiKey;
    private $apiSecret;
    private $token;
    private $logger;
    
    public function __construct(array $config) {
        $this->baseUrl = rtrim($config['url'], '/');
        $this->apiKey = $config['api_key'];
        $this->apiSecret = $config['api_secret'];
        $this->logger = new Logger('Ruijie');
    }
    
    public function connect(array $credentials): bool {
        try {
            // Authenticate with Ruijie Cloud
            $authUrl = "{$this->baseUrl}/oauth/token";
            
            $response = $this->httpPost($authUrl, [
                'grant_type' => 'password',
                'username' => $credentials['username'],
                'password' => $credentials['password'],
                'client_id' => $this->apiKey,
                'client_secret' => $this->apiSecret
            ]);
            
            if (isset($response['access_token'])) {
                $this->token = $response['access_token'];
                $this->logger->info("Connected to Ruijie Controller");
                return true;
            }
            
            $this->logger->error("Ruijie authentication failed");
            return false;
            
        } catch (Exception $e) {
            $this->logger->error("Ruijie connection failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function authenticateUser(string $mac, string $username, array $plan): bool {
        try {
            // Create guest user in Ruijie
            $url = "{$this->baseUrl}/api/v1/guests";
            
            $guestData = [
                'mac_address' => $mac,
                'username' => $username,
                'bandwidth_limit' => [
                    'upload' => $plan['upload_speed'] * 1000, // Mbps to Kbps
                    'download' => $plan['download_speed'] * 1000
                ],
                'expire_time' => date('Y-m-d H:i:s', time() + ($plan['duration'] * 86400)),
                'auth_type' => 'mac',
                'enable_accounting' => true
            ];
            
            $response = $this->httpPost($url, $guestData, $this->token);
            
            if ($response['code'] == 200) {
                $this->logger->info("User {$username} authenticated on Ruijie");
                return true;
            }
            
            $this->logger->error("Ruijie authentication failed: " . $response['message']);
            return false;
            
        } catch (Exception $e) {
            $this->logger->error("Authentication failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function disconnectUser(string $mac): bool {
        try {
            // Get guest ID
            $guestId = $this->getGuestIdByMac($mac);
            
            if (!$guestId) {
                return false;
            }
            
            // Delete guest
            $url = "{$this->baseUrl}/api/v1/guests/{$guestId}";
            $response = $this->httpDelete($url, $this->token);
            
            if ($response['code'] == 200) {
                $this->logger->info("User with MAC {$mac} disconnected from Ruijie");
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logger->error("Disconnect failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function getActiveSessions(): array {
        try {
            $url = "{$this->baseUrl}/api/v1/online-users";
            $response = $this->httpGet($url, $this->token);
            
            if ($response['code'] != 200) {
                return [];
            }
            
            $sessions = [];
            foreach ($response['data'] as $user) {
                $sessions[] = [
                    'mac' => $user['mac_address'],
                    'ip' => $user['ip_address'],
                    'username' => $user['username'],
                    'uptime' => $user['online_duration'],
                    'bytes_in' => $user['bytes_received'],
                    'bytes_out' => $user['bytes_sent'],
                    'ap_name' => $user['ap_name'],
                    'ssid' => $user['ssid'],
                    'controller_type' => 'ruijie'
                ];
            }
            
            return $sessions;
            
        } catch (Exception $e) {
            $this->logger->error("Failed to get active sessions: " . $e->getMessage());
            return [];
        }
    }
    
    public function getUserSession(string $mac): ?array {
        $sessions = $this->getActiveSessions();
        
        foreach ($sessions as $session) {
            if (strcasecmp($session['mac'], $mac) === 0) {
                return $session;
            }
        }
        
        return null;
    }
    
    public function updateBandwidth(string $mac, int $uploadSpeed, int $downloadSpeed): bool {
        try {
            $guestId = $this->getGuestIdByMac($mac);
            
            if (!$guestId) {
                return false;
            }
            
            $url = "{$this->baseUrl}/api/v1/guests/{$guestId}";
            
            $updateData = [
                'bandwidth_limit' => [
                    'upload' => $uploadSpeed * 1000,
                    'download' => $downloadSpeed * 1000
                ]
            ];
            
            $response = $this->httpPut($url, $updateData, $this->token);
            
            if ($response['code'] == 200) {
                $this->logger->info("Bandwidth updated for MAC {$mac}");
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logger->error("Bandwidth update failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function getControllerStatus(): array {
        try {
            $url = "{$this->baseUrl}/api/v1/system/status";
            $response = $this->httpGet($url, $this->token);
            
            if ($response['code'] == 200) {
                return [
                    'status' => 'online',
                    'version' => $response['data']['version'],
                    'total_aps' => $response['data']['total_aps'],
                    'online_aps' => $response['data']['online_aps'],
                    'total_clients' => $response['data']['total_clients'],
                    'type' => 'Ruijie Cloud Controller'
                ];
            }
            
            return ['status' => 'offline'];
            
        } catch (Exception $e) {
            return ['status' => 'offline', 'error' => $e->getMessage()];
        }
    }
    
    public function createRadiusProfile(array $profileData): bool {
        try {
            $url = "{$this->baseUrl}/api/v1/radius-profiles";
            
            $radiusData = [
                'name' => 'WiFight-RADIUS',
                'auth_server' => $profileData['radius_server'],
                'auth_port' => $profileData['auth_port'] ?? 1812,
                'auth_secret' => $profileData['radius_secret'],
                'acct_server' => $profileData['radius_server'],
                'acct_port' => $profileData['acct_port'] ?? 1813,
                'acct_secret' => $profileData['radius_secret']
            ];
            
            $response = $this->httpPost($url, $radiusData, $this->token);
            
            return $response['code'] == 200;
            
        } catch (Exception $e) {
            $this->logger->error("RADIUS profile creation failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function testConnection(): bool {
        try {
            $url = "{$this->baseUrl}/api/v1/system/ping";
            $response = $this->httpGet($url, $this->token);
            return $response['code'] == 200;
        } catch (Exception $e) {
            return false;
        }
    }
    
    // Helper methods
    
    private function getGuestIdByMac(string $mac): ?string {
        try {
            $url = "{$this->baseUrl}/api/v1/guests?mac_address={$mac}";
            $response = $this->httpGet($url, $this->token);
            
            if ($response['code'] == 200 && !empty($response['data'])) {
                return $response['data'][0]['id'];
            }
            
            return null;
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    private function httpGet(string $url, ?string $token = null): array {
        return $this->httpRequest('GET', $url, null, $token);
    }
    
    private function httpPost(string $url, array $data, ?string $token = null): array {
        return $this->httpRequest('POST', $url, $data, $token);
    }
    
    private function httpPut(string $url, array $data, ?string $token = null): array {
        return $this->httpRequest('PUT', $url, $data, $token);
    }
    
    private function httpDelete(string $url, ?string $token = null): array {
        return $this->httpRequest('DELETE', $url, null, $token);
    }
    
    private function httpRequest(string $method, string $url, ?array $data, ?string $token): array {
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        if ($token) {
            $headers[] = "Authorization: Bearer {$token}";
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => $method
        ]);
        
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            throw new Exception("HTTP Error {$httpCode}");
        }
        
        return json_decode($response, true) ?? [];
    }
}
```

---

### 3.5 Cisco Meraki Integration

#### **Overview**
Cisco Meraki provides cloud-managed networking with powerful API capabilities. Integration uses the Meraki Dashboard API.

#### **Implementation**

```php
<?php
// backend/services/controllers/MerakiController.php

class MerakiController implements ControllerInterface {
    
    private $apiKey;
    private $baseUrl = 'https://api.meraki.com/api/v1';
    private $networkId;
    private $logger;
    
    public function __construct(array $config) {
        $this->apiKey = $config['api_key'];
        $this->networkId = $config['network_id'];
        $this->logger = new Logger('Meraki');
    }
    
    public function connect(array $credentials): bool {
        try {
            // Test connection by getting organization info
            $url = "{$this->baseUrl}/organizations";
            $response = $this->httpGet($url);
            
            if (!empty($response)) {
                $this->logger->info("Connected to Cisco Meraki");
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logger->error("Meraki connection failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function authenticateUser(string $mac, string $username, array $plan): bool {
        try {
            // Create splash authorization
            $url = "{$this->baseUrl}/networks/{$this->networkId}/splash/access";
            
            $authData = [
                'ssids' => ['0'], // Apply to all SSIDs
                'durationInMinutes' => $plan['duration'] * 1440, // Days to minutes
                'userId' => $username,
                'mac' => strtoupper($mac)
            ];
            
            $response = $this->httpPost($url, $authData);
            
            // Set group policy for bandwidth limiting
            if (!empty($response)) {
                $this->setGroupPolicy($mac, [
                    'bandwidth' => [
                        'limitDown' => $plan['download_speed'] * 1000, // Mbps to Kbps
                        'limitUp' => $plan['upload_speed'] * 1000
                    ]
                ]);
                
                $this->logger->info("User {$username} authenticated on Meraki");
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logger->error("Authentication failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function disconnectUser(string $mac): bool {
        try {
            // Remove splash authorization
            $url = "{$this->baseUrl}/networks/{$this->networkId}/clients/{$mac}/splash";
            $response = $this->httpDelete($url);
            
            $this->logger->info("User with MAC {$mac} disconnected from Meraki");
            return true;
            
        } catch (Exception $e) {
            $this->logger->error("Disconnect failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function getActiveSessions(): array {
        try {
            $url = "{$this->baseUrl}/networks/{$this->networkId}/clients?timespan=3600";
            $response = $this->httpGet($url);
            
            $sessions = [];
            foreach ($response as $client) {
                if ($client['status'] === 'Online') {
                    $sessions[] = [
                        'mac' => $client['mac'],
                        'ip' => $client['ip'],
                        'username' => $client['description'] ?? 'Guest',
                        'uptime' => $client['lastSeen'],
                        'bytes_in' => $client['usage']['recv'],
                        'bytes_out' => $client['usage']['sent'],
                        'ssid' => $client['ssid'],
                        'ap_name' => $client['recentDeviceName'],
                        'controller_type' => 'meraki'
                    ];
                }
            }
            
            return $sessions;
            
        } catch (Exception $e) {
            $this->logger->error("Failed to get active sessions: " . $e->getMessage());
            return [];
        }
    }
    
    public function getUserSession(string $mac): ?array {
        try {
            $url = "{$this->baseUrl}/networks/{$this->networkId}/clients/{$mac}";
            $client = $this->httpGet($url);
            
            if ($client && $client['status'] === 'Online') {
                return [
                    'mac' => $client['mac'],
                    'ip' => $client['ip'],
                    'username' => $client['description'] ?? 'Guest',
                    'uptime' => $client['lastSeen'],
                    'bytes_in' => $client['usage']['recv'],
                    'bytes_out' => $client['usage']['sent'],
                    'ssid' => $client['ssid'],
                    'controller_type' => 'meraki'
                ];
            }
            
            return null;
            
        } catch (Exception $e) {
            $this->logger->error("Failed to get user session: " . $e->getMessage());
            return null;
        }
    }
    
    public function updateBandwidth(string $mac, int $uploadSpeed, int $downloadSpeed): bool {
        try {
            return $this->setGroupPolicy($mac, [
                'bandwidth' => [
                    'limitDown' => $downloadSpeed * 1000,
                    'limitUp' => $uploadSpeed * 1000
                ]
            ]);
            
        } catch (Exception $e) {
            $this->logger->error("Bandwidth update failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function getControllerStatus(): array {
        try {
            $url = "{$this->baseUrl}/networks/{$this->networkId}";
            $network = $this->httpGet($url);
            
            if ($network) {
                $devicesUrl = "{$this->baseUrl}/networks/{$this->networkId}/devices";
                $devices = $this->httpGet($devicesUrl);
                
                return [
                    'status' => 'online',
                    'network_name' => $network['name'],
                    'total_devices' => count($devices),
                    'type' => 'Cisco Meraki Dashboard'
                ];
            }
            
            return ['status' => 'offline'];
            
        } catch (Exception $e) {
            return ['status' => 'offline', 'error' => $e->getMessage()];
        }
    }
    
    public function createRadiusProfile(array $profileData): bool {
        try {
            // Meraki uses RADIUS directly in SSID configuration
            $ssidsUrl = "{$this->baseUrl}/networks/{$this->networkId}/wireless/ssids";
            $ssids = $this->httpGet($ssidsUrl);
            
            if (!empty($ssids)) {
                $ssid = $ssids[0];
                $updateUrl = "{$this->baseUrl}/networks/{$this->networkId}/wireless/ssids/{$ssid['number']}";
                
                $radiusData = [
                    'radiusServers' => [
                        [
                            'host' => $profileData['radius_server'],
                            'port' => $profileData['auth_port'] ?? 1812,
                            'secret' => $profileData['radius_secret']
                        ]
                    ],
                    'radiusAccountingServers' => [
                        [
                            'host' => $profileData['radius_server'],
                            'port' => $profileData['acct_port'] ?? 1813,
                            'secret' => $profileData['radius_secret']
                        ]
                    ]
                ];
                
                $response = $this->httpPut($updateUrl, $radiusData);
                return !empty($response);
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logger->error("RADIUS profile creation failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function testConnection(): bool {
        try {
            $url = "{$this->baseUrl}/organizations";
            $response = $this->httpGet($url);
            return !empty($response);
        } catch (Exception $e) {
            return false;
        }
    }
    
    // Helper methods
    
    private function setGroupPolicy(string $mac, array $policy): bool {
        try {
            $url = "{$this->baseUrl}/networks/{$this->networkId}/clients/{$mac}/policy";
            $response = $this->httpPut($url, $policy);
            return !empty($response);
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function httpGet(string $url): array {
        return $this->httpRequest('GET', $url);
    }
    
    private function httpPost(string $url, array $data): array {
        return $this->httpRequest('POST', $url, $data);
    }
    
    private function httpPut(string $url, array $data): array {
        return $this->httpRequest('PUT', $url, $data);
    }
    
    private function httpDelete(string $url): array {
        return $this->httpRequest('DELETE', $url);
    }
    
    private function httpRequest(string $method, string $url, ?array $data = null): array {
        $ch = curl_init();
        
        $headers = [
            'X-Cisco-Meraki-API-Key: ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method
        ]);
        
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            throw new Exception("HTTP Error {$httpCode}");
        }
        
        return json_decode($response, true) ?? [];
    }
}
```

---

## 4. Database Architecture Enhancement

### 4.1 Enhanced Schema Design

```sql
-- Enhanced database schema for WiFight ISP System

-- Users table (enhanced)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    phone VARCHAR(20),
    role ENUM('admin', 'reseller', 'user') DEFAULT 'user',
    status ENUM('active', 'suspended', 'inactive') DEFAULT 'active',
    balance DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(32),
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Controllers table (multi-vendor)
CREATE TABLE controllers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    type ENUM('mikrotik', 'omada', 'ruijie', 'meraki', 'cisco') NOT NULL,
    host VARCHAR(255) NOT NULL,
    port INT,
    username VARCHAR(100),
    password VARCHAR(255),
    api_key VARCHAR(255),
    api_secret VARCHAR(255),
    network_id VARCHAR(100),
    site_id VARCHAR(100),
    status ENUM('online', 'offline', 'error') DEFAULT 'offline',
    last_check TIMESTAMP NULL,
    config JSON,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_status (status),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Plans table (enhanced)
CREATE TABLE plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    download_speed INT NOT NULL COMMENT 'Mbps',
    upload_speed INT NOT NULL COMMENT 'Mbps',
    duration INT NOT NULL COMMENT 'Days',
    price DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    data_limit BIGINT COMMENT 'Bytes, NULL for unlimited',
    simultaneous_users INT DEFAULT 1,
    controller_id INT,
    radius_profile VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    priority INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_controller_id (controller_id),
    FOREIGN KEY (controller_id) REFERENCES controllers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- User sessions table (enhanced)
CREATE TABLE sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    plan_id INT,
    controller_id INT NOT NULL,
    mac_address VARCHAR(17) NOT NULL,
    ip_address VARCHAR(45),
    session_id VARCHAR(100),
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    duration INT COMMENT 'Seconds',
    bytes_in BIGINT DEFAULT 0,
    bytes_out BIGINT DEFAULT 0,
    status ENUM('active', 'expired', 'terminated', 'suspended') DEFAULT 'active',
    termination_reason VARCHAR(255),
    INDEX idx_user_id (user_id),
    INDEX idx_mac_address (mac_address),
    INDEX idx_status (status),
    INDEX idx_controller_id (controller_id),
    INDEX idx_start_time (start_time),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE SET NULL,
    FOREIGN KEY (controller_id) REFERENCES controllers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Subscriptions table (for recurring billing)
CREATE TABLE subscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    controller_id INT,
    mac_address VARCHAR(17),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    auto_renew BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'expired', 'cancelled', 'suspended') DEFAULT 'active',
    payment_method VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_end_date (end_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE,
    FOREIGN KEY (controller_id) REFERENCES controllers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Payments table (enhanced)
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    subscription_id INT,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    payment_method VARCHAR(50) NOT NULL,
    transaction_id VARCHAR(100),
    gateway VARCHAR(50),
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    metadata JSON,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_payment_date (payment_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Vouchers table (for prepaid codes)
CREATE TABLE vouchers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    plan_id INT NOT NULL,
    batch_id VARCHAR(50),
    created_by INT,
    used_by INT,
    status ENUM('available', 'used', 'expired') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    INDEX idx_code (code),
    INDEX idx_status (status),
    INDEX idx_batch_id (batch_id),
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- RADIUS accounting table
CREATE TABLE radius_accounting (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(100) NOT NULL,
    user_id INT,
    mac_address VARCHAR(17) NOT NULL,
    nas_ip_address VARCHAR(45),
    acct_start_time TIMESTAMP NULL,
    acct_stop_time TIMESTAMP NULL,
    acct_session_time INT,
    acct_input_octets BIGINT DEFAULT 0,
    acct_output_octets BIGINT DEFAULT 0,
    acct_terminate_cause VARCHAR(50),
    INDEX idx_session_id (session_id),
    INDEX idx_mac_address (mac_address),
    INDEX idx_start_time (acct_start_time),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Audit logs table
CREATE TABLE audit_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Notifications table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    link VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- System settings table
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(20) DEFAULT 'string',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB;
```

### 4.2 Database Optimization

#### **Indexing Strategy**
- Primary keys on all tables
- Foreign key constraints for referential integrity
- Composite indexes for common query patterns
- Covering indexes for frequently accessed columns

#### **Partitioning (for large deployments)**
```sql
-- Partition sessions table by month
ALTER TABLE sessions
PARTITION BY RANGE (YEAR(start_time) * 100 + MONTH(start_time)) (
    PARTITION p202401 VALUES LESS THAN (202402),
    PARTITION p202402 VALUES LESS THAN (202403),
    -- Add partitions as needed
    PARTITION pmax VALUES LESS THAN MAXVALUE
);

-- Partition RADIUS accounting by month
ALTER TABLE radius_accounting
PARTITION BY RANGE (YEAR(acct_start_time) * 100 + MONTH(acct_start_time)) (
    PARTITION p202401 VALUES LESS THAN (202402),
    PARTITION p202402 VALUES LESS THAN (202403),
    PARTITION pmax VALUES LESS THAN MAXVALUE
);
```

---

## 5. API Layer Development

### 5.1 RESTful API Design

#### **API Structure**
```
/api/v1
├── /auth
│   ├── POST /login
│   ├── POST /register
│   ├── POST /logout
│   ├── POST /refresh
│   └── POST /forgot-password
├── /users
│   ├── GET /users
│   ├── GET /users/{id}
│   ├── POST /users
│   ├── PUT /users/{id}
│   ├── DELETE /users/{id}
│   └── GET /users/{id}/sessions
├── /controllers
│   ├── GET /controllers
│   ├── GET /controllers/{id}
│   ├── POST /controllers
│   ├── PUT /controllers/{id}
│   ├── DELETE /controllers/{id}
│   ├── POST /controllers/{id}/test
│   └── GET /controllers/{id}/status
├── /plans
│   ├── GET /plans
│   ├── GET /plans/{id}
│   ├── POST /plans
│   ├── PUT /plans/{id}
│   └── DELETE /plans/{id}
├── /sessions
│   ├── GET /sessions
│   ├── GET /sessions/{id}
│   ├── POST /sessions/start
│   ├── POST /sessions/{id}/stop
│   └── GET /sessions/active
├── /subscriptions
│   ├── GET /subscriptions
│   ├── GET /subscriptions/{id}
│   ├── POST /subscriptions
│   ├── PUT /subscriptions/{id}
│   └── POST /subscriptions/{id}/cancel
├── /payments
│   ├── GET /payments
│   ├── GET /payments/{id}
│   ├── POST /payments
│   └── POST /payments/{id}/refund
├── /vouchers
│   ├── GET /vouchers
│   ├── POST /vouchers/generate
│   ├── POST /vouchers/redeem
│   └── GET /vouchers/batch/{batchId}
└── /reports
    ├── GET /reports/revenue
    ├── GET /reports/usage
    ├── GET /reports/sessions
    └── GET /reports/controllers
```

### 5.2 API Implementation Example

```php
<?php
// backend/api/v1/controllers/index.php

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../utils/JWT.php';
require_once __DIR__ . '/../../../utils/Response.php';
require_once __DIR__ . '/../../../services/controllers/ControllerFactory.php';

$jwt = new JWT();
$response = new Response();

// Verify authentication
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

if (!$token || !$jwt->validate($token)) {
    $response->error('Unauthorized', 401);
}

$userData = $jwt->decode($token);
$method = $_SERVER['REQUEST_METHOD'];
$db = (new Database())->getConnection();

switch ($method) {
    case 'GET':
        handleGet($db, $userData, $response);
        break;
    
    case 'POST':
        handlePost($db, $userData, $response);
        break;
    
    case 'PUT':
        handlePut($db, $userData, $response);
        break;
    
    case 'DELETE':
        handleDelete($db, $userData, $response);
        break;
    
    default:
        $response->error('Method not allowed', 405);
}

function handleGet($db, $userData, $response) {
    // Check if requesting specific controller
    $controllerId = $_GET['id'] ?? null;
    
    if ($controllerId) {
        // Get single controller
        $stmt = $db->prepare("
            SELECT * FROM controllers 
            WHERE id = :id AND (created_by = :user_id OR :is_admin = 1)
        ");
        $stmt->execute([
            'id' => $controllerId,
            'user_id' => $userData['id'],
            'is_admin' => $userData['role'] === 'admin' ? 1 : 0
        ]);
        
        $controller = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$controller) {
            $response->error('Controller not found', 404);
        }
        
        // Remove sensitive data
        unset($controller['password'], $controller['api_secret']);
        
        // Get controller status
        try {
            $controllerObj = ControllerFactory::create($controller['type'], $controller);
            $status = $controllerObj->getControllerStatus();
            $controller['status'] = $status;
        } catch (Exception $e) {
            $controller['status'] = ['status' => 'error', 'error' => $e->getMessage()];
        }
        
        $response->success($controller);
        
    } else {
        // Get all controllers
        $stmt = $db->prepare("
            SELECT * FROM controllers 
            WHERE created_by = :user_id OR :is_admin = 1
            ORDER BY created_at DESC
        ");
        $stmt->execute([
            'user_id' => $userData['id'],
            'is_admin' => $userData['role'] === 'admin' ? 1 : 0
        ]);
        
        $controllers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Remove sensitive data
        foreach ($controllers as &$controller) {
            unset($controller['password'], $controller['api_secret']);
        }
        
        $response->success($controllers);
    }
}

function handlePost($db, $userData, $response) {
    if ($userData['role'] !== 'admin') {
        $response->error('Insufficient permissions', 403);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['name', 'type', 'host'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            $response->error("Missing required field: {$field}", 400);
        }
    }
    
    // Validate controller type
    $supportedTypes = ControllerFactory::getSupportedTypes();
    if (!in_array($data['type'], $supportedTypes)) {
        $response->error('Unsupported controller type', 400);
    }
    
    // Test connection before saving
    try {
        $testController = ControllerFactory::create($data['type'], $data);
        if (!$testController->testConnection()) {
            $response->error('Failed to connect to controller', 400);
        }
    } catch (Exception $e) {
        $response->error('Connection test failed: ' . $e->getMessage(), 400);
    }
    
    // Insert controller
    $stmt = $db->prepare("
        INSERT INTO controllers (
            name, type, host, port, username, password, api_key, api_secret,
            network_id, site_id, config, created_by, status
        ) VALUES (
            :name, :type, :host, :port, :username, :password, :api_key, :api_secret,
            :network_id, :site_id, :config, :created_by, 'online'
        )
    ");
    
    $stmt->execute([
        'name' => $data['name'],
        'type' => $data['type'],
        'host' => $data['host'],
        'port' => $data['port'] ?? null,
        'username' => $data['username'] ?? null,
        'password' => $data['password'] ?? null,
        'api_key' => $data['api_key'] ?? null,
        'api_secret' => $data['api_secret'] ?? null,
        'network_id' => $data['network_id'] ?? null,
        'site_id' => $data['site_id'] ?? null,
        'config' => json_encode($data['config'] ?? []),
        'created_by' => $userData['id']
    ]);
    
    $controllerId = $db->lastInsertId();
    
    // Audit log
    logAudit($db, $userData['id'], 'controller_created', 'controller', $controllerId);
    
    $response->success(['id' => $controllerId, 'message' => 'Controller added successfully'], 201);
}

function handlePut($db, $userData, $response) {
    if ($userData['role'] !== 'admin') {
        $response->error('Insufficient permissions', 403);
    }
    
    $controllerId = $_GET['id'] ?? null;
    if (!$controllerId) {
        $response->error('Controller ID required', 400);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Update controller
    $fields = [];
    $params = ['id' => $controllerId];
    
    $allowedFields = ['name', 'host', 'port', 'username', 'password', 'api_key', 'api_secret', 'network_id', 'site_id', 'config'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $fields[] = "{$field} = :{$field}";
            $params[$field] = $field === 'config' ? json_encode($data[$field]) : $data[$field];
        }
    }
    
    if (empty($fields)) {
        $response->error('No fields to update', 400);
    }
    
    $sql = "UPDATE controllers SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    // Audit log
    logAudit($db, $userData['id'], 'controller_updated', 'controller', $controllerId);
    
    $response->success(['message' => 'Controller updated successfully']);
}

function handleDelete($db, $userData, $response) {
    if ($userData['role'] !== 'admin') {
        $response->error('Insufficient permissions', 403);
    }
    
    $controllerId = $_GET['id'] ?? null;
    if (!$controllerId) {
        $response->error('Controller ID required', 400);
    }
    
    // Check if controller is in use
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM sessions WHERE controller_id = :id AND status = 'active'");
    $stmt->execute(['id' => $controllerId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        $response->error('Cannot delete controller with active sessions', 400);
    }
    
    // Delete controller
    $stmt = $db->prepare("DELETE FROM controllers WHERE id = :id");
    $stmt->execute(['id' => $controllerId]);
    
    // Audit log
    logAudit($db, $userData['id'], 'controller_deleted', 'controller', $controllerId);
    
    $response->success(['message' => 'Controller deleted successfully']);
}

function logAudit($db, $userId, $action, $entityType, $entityId) {
    $stmt = $db->prepare("
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address)
        VALUES (:user_id, :action, :entity_type, :entity_id, :ip_address)
    ");
    $stmt->execute([
        'user_id' => $userId,
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ]);
}
```

---

*Continued in Part 2...*

This completes Part 1 of the WiFight ISP Development Plan, covering system architecture, multi-vendor controller integration (MikroTik, Omada, Ruijie, Meraki), database design, and API development.
