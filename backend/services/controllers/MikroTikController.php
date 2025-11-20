<?php
/**
 * WiFight ISP System - MikroTik Controller
 *
 * MikroTik RouterOS implementation using RouterOS API
 * Uses EvilFreelancer/routeros-api-php library
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;

class MikroTikController implements ControllerInterface {
    private $config;
    private $type = 'mikrotik';
    private $client = null;
    private $connected = false;

    public function __construct(array $config) {
        $this->config = $config;
    }

    /**
     * Connect to MikroTik router
     */
    public function connect(array $credentials) {
        try {
            $config = new Config([
                'host' => $credentials['host'] ?? $this->config['host'],
                'user' => $credentials['username'] ?? $this->config['username'],
                'pass' => $credentials['password'] ?? $this->config['password'],
                'port' => $credentials['port'] ?? $this->config['port'] ?? 8728,
            ]);

            $this->client = new Client($config);
            $this->connected = true;

            return true;
        } catch (Exception $e) {
            error_log('MikroTik connection error: ' . $e->getMessage());
            $this->connected = false;
            return false;
        }
    }

    /**
     * Authenticate user on the network
     * Creates Hotspot user or Queue for bandwidth limiting
     */
    public function authenticateUser(string $mac, string $username, array $plan) {
        if (!$this->ensureConnection()) {
            return false;
        }

        try {
            // Create simple queue for bandwidth limiting
            $query = new Query('/queue/simple/add');
            $query->equal('name', $username);
            $query->equal('target', $mac);
            $query->equal('max-limit', $plan['bandwidth_up'] . 'k/' . $plan['bandwidth_down'] . 'k');

            if (isset($plan['burst_up']) && isset($plan['burst_down'])) {
                $query->equal('burst-limit', $plan['burst_up'] . 'k/' . $plan['burst_down'] . 'k');
            }

            $this->client->query($query)->read();

            return true;
        } catch (Exception $e) {
            error_log('MikroTik auth error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Disconnect user from network
     * Removes queue and optionally blocks MAC
     */
    public function disconnectUser(string $mac) {
        if (!$this->ensureConnection()) {
            return false;
        }

        try {
            // Find and remove simple queue by MAC
            $query = new Query('/queue/simple/print');
            $query->where('target', $mac);
            $queues = $this->client->query($query)->read();

            foreach ($queues as $queue) {
                $removeQuery = new Query('/queue/simple/remove');
                $removeQuery->equal('.id', $queue['.id']);
                $this->client->query($removeQuery)->read();
            }

            return true;
        } catch (Exception $e) {
            error_log('MikroTik disconnect error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all active sessions/queues
     */
    public function getActiveSessions() {
        if (!$this->ensureConnection()) {
            return [];
        }

        try {
            $query = new Query('/queue/simple/print');
            $queues = $this->client->query($query)->read();

            $sessions = [];
            foreach ($queues as $queue) {
                $sessions[] = [
                    'id' => $queue['.id'],
                    'name' => $queue['name'],
                    'target' => $queue['target'],
                    'bytes_in' => $queue['bytes'] ?? 0,
                    'bytes_out' => $queue['bytes'] ?? 0,
                    'max_limit' => $queue['max-limit'] ?? null,
                    'disabled' => $queue['disabled'] === 'true'
                ];
            }

            return $sessions;
        } catch (Exception $e) {
            error_log('MikroTik get sessions error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get specific user session details
     */
    public function getUserSession(string $mac) {
        if (!$this->ensureConnection()) {
            return null;
        }

        try {
            $query = new Query('/queue/simple/print');
            $query->where('target', $mac);
            $queues = $this->client->query($query)->read();

            if (empty($queues)) {
                return null;
            }

            $queue = $queues[0];
            return [
                'id' => $queue['.id'],
                'name' => $queue['name'],
                'mac' => $queue['target'],
                'bytes_in' => $queue['bytes'] ?? 0,
                'bytes_out' => $queue['bytes'] ?? 0,
                'max_limit' => $queue['max-limit'] ?? null,
                'disabled' => $queue['disabled'] === 'true'
            ];
        } catch (Exception $e) {
            error_log('MikroTik get user session error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update user bandwidth limits in real-time
     */
    public function updateBandwidth(string $mac, int $uploadSpeed, int $downloadSpeed) {
        if (!$this->ensureConnection()) {
            return false;
        }

        try {
            // Find queue by MAC
            $query = new Query('/queue/simple/print');
            $query->where('target', $mac);
            $queues = $this->client->query($query)->read();

            if (empty($queues)) {
                return false;
            }

            // Update bandwidth limit
            $updateQuery = new Query('/queue/simple/set');
            $updateQuery->equal('.id', $queues[0]['.id']);
            $updateQuery->equal('max-limit', $uploadSpeed . 'k/' . $downloadSpeed . 'k');

            $this->client->query($updateQuery)->read();

            return true;
        } catch (Exception $e) {
            error_log('MikroTik bandwidth update error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get controller status and statistics
     */
    public function getControllerStatus() {
        if (!$this->ensureConnection()) {
            return [
                'status' => 'offline',
                'type' => $this->type,
                'connected' => false
            ];
        }

        try {
            // Get system resources
            $query = new Query('/system/resource/print');
            $resources = $this->client->query($query)->read();

            $resource = $resources[0] ?? [];

            return [
                'status' => 'online',
                'type' => $this->type,
                'connected' => true,
                'version' => $resource['version'] ?? 'unknown',
                'uptime' => $resource['uptime'] ?? 'unknown',
                'cpu_load' => $resource['cpu-load'] ?? 0,
                'free_memory' => $resource['free-memory'] ?? 0,
                'total_memory' => $resource['total-memory'] ?? 0
            ];
        } catch (Exception $e) {
            error_log('MikroTik status error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'type' => $this->type,
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create RADIUS profile on MikroTik
     */
    public function createRadiusProfile(array $profileData) {
        if (!$this->ensureConnection()) {
            return false;
        }

        try {
            // Create user manager profile
            $query = new Query('/user-manager/profile/add');
            $query->equal('name', $profileData['name']);
            $query->equal('name-for-users', $profileData['display_name'] ?? $profileData['name']);

            if (isset($profileData['validity'])) {
                $query->equal('validity', $profileData['validity']);
            }

            $this->client->query($query)->read();

            return true;
        } catch (Exception $e) {
            error_log('MikroTik RADIUS profile error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test connection to MikroTik router
     */
    public function testConnection() {
        try {
            if ($this->connected && $this->client !== null) {
                // Already connected, test with a simple query
                $query = new Query('/system/identity/print');
                $this->client->query($query)->read();
                return true;
            }

            // Not connected, try to connect
            return $this->connect($this->config);
        } catch (Exception $e) {
            error_log('MikroTik connection test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get controller type
     */
    public function getType() {
        return $this->type;
    }

    /**
     * Get controller configuration
     */
    public function getConfig() {
        // Return config without sensitive data
        $safeConfig = $this->config;
        unset($safeConfig['password']);
        return $safeConfig;
    }

    /**
     * Ensure connection is established
     */
    private function ensureConnection() {
        if (!$this->connected || $this->client === null) {
            return $this->connect($this->config);
        }
        return true;
    }

    /**
     * Disconnect from router
     */
    public function __destruct() {
        if ($this->client) {
            // Close connection if needed
            $this->client = null;
            $this->connected = false;
        }
    }
}
