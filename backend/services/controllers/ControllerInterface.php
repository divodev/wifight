<?php
/**
 * WiFight ISP System - Controller Interface
 *
 * Standard interface for all network controller implementations
 */

interface ControllerInterface {
    /**
     * Connect to the controller
     *
     * @param array $credentials Controller credentials
     * @return bool
     */
    public function connect(array $credentials);

    /**
     * Authenticate a user on the network
     *
     * @param string $mac MAC address
     * @param string $username Username
     * @param array $plan Plan configuration
     * @return bool
     */
    public function authenticateUser(string $mac, string $username, array $plan);

    /**
     * Disconnect user from the network
     *
     * @param string $mac MAC address
     * @return bool
     */
    public function disconnectUser(string $mac);

    /**
     * Get all active sessions
     *
     * @return array
     */
    public function getActiveSessions();

    /**
     * Get specific user session details
     *
     * @param string $mac MAC address
     * @return array|null
     */
    public function getUserSession(string $mac);

    /**
     * Update user bandwidth limits
     *
     * @param string $mac MAC address
     * @param int $uploadSpeed Upload speed in Mbps
     * @param int $downloadSpeed Download speed in Mbps
     * @return bool
     */
    public function updateBandwidth(string $mac, int $uploadSpeed, int $downloadSpeed);

    /**
     * Get controller status and statistics
     *
     * @return array
     */
    public function getControllerStatus();

    /**
     * Create or update RADIUS profile
     *
     * @param array $profileData RADIUS configuration
     * @return bool
     */
    public function createRadiusProfile(array $profileData);

    /**
     * Test connection to controller
     *
     * @return bool
     */
    public function testConnection();

    /**
     * Get controller type identifier
     *
     * @return string
     */
    public function getType();

    /**
     * Get controller configuration
     *
     * @return array
     */
    public function getConfig();
}
