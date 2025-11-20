<?php
/**
 * WiFight ISP System - Controller Factory
 *
 * Factory pattern for creating controller instances
 */

require_once __DIR__ . '/ControllerInterface.php';
require_once __DIR__ . '/MikroTikController.php';
require_once __DIR__ . '/OmadaController.php';
require_once __DIR__ . '/RuijieController.php';
require_once __DIR__ . '/MerakiController.php';

class ControllerFactory {

    /**
     * Create controller instance based on type
     *
     * @param string $type Controller type (mikrotik, omada, ruijie, meraki)
     * @param array $config Controller configuration
     * @return ControllerInterface
     * @throws Exception
     */
    public static function create(string $type, array $config) {
        $type = strtolower($type);

        switch ($type) {
            case 'mikrotik':
                return new MikroTikController($config);

            case 'omada':
            case 'tplink':
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

    /**
     * Get list of supported controller types
     *
     * @return array
     */
    public static function getSupportedTypes() {
        return [
            'mikrotik' => [
                'name' => 'MikroTik RouterOS',
                'description' => 'MikroTik routers and access points',
                'requires' => ['host', 'username', 'password', 'port']
            ],
            'omada' => [
                'name' => 'TP-Link Omada SDN',
                'description' => 'TP-Link Omada Controller',
                'requires' => ['host', 'username', 'password', 'site_id']
            ],
            'ruijie' => [
                'name' => 'Ruijie Networks',
                'description' => 'Ruijie Cloud Controller',
                'requires' => ['host', 'api_key', 'api_secret']
            ],
            'meraki' => [
                'name' => 'Cisco Meraki',
                'description' => 'Cisco Meraki Dashboard',
                'requires' => ['api_key', 'network_id']
            ]
        ];
    }

    /**
     * Validate controller configuration
     *
     * @param string $type Controller type
     * @param array $config Configuration to validate
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validateConfig(string $type, array $config) {
        $errors = [];
        $supportedTypes = self::getSupportedTypes();

        if (!isset($supportedTypes[$type])) {
            return [
                'valid' => false,
                'errors' => ["Unsupported controller type: {$type}"]
            ];
        }

        $required = $supportedTypes[$type]['requires'];

        foreach ($required as $field) {
            if (empty($config[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Create controller instance from database record
     *
     * @param array $dbRecord Database record from controllers table
     * @return ControllerInterface
     * @throws Exception
     */
    public static function createFromDatabase(array $dbRecord) {
        $config = [
            'id' => $dbRecord['id'],
            'name' => $dbRecord['name'],
            'host' => $dbRecord['host'],
            'port' => $dbRecord['port'],
            'username' => $dbRecord['username'],
            'password' => $dbRecord['password'],
            'api_key' => $dbRecord['api_key'],
            'api_secret' => $dbRecord['api_secret'],
            'network_id' => $dbRecord['network_id'],
            'site_id' => $dbRecord['site_id']
        ];

        // Merge additional config from JSON field
        if (!empty($dbRecord['config'])) {
            $additionalConfig = is_string($dbRecord['config'])
                ? json_decode($dbRecord['config'], true)
                : $dbRecord['config'];

            $config = array_merge($config, $additionalConfig ?? []);
        }

        return self::create($dbRecord['type'], $config);
    }

    /**
     * Test connection for all controllers in database
     *
     * @param PDO $db Database connection
     * @return array Results for each controller
     */
    public static function testAllControllers($db) {
        $stmt = $db->query("SELECT * FROM controllers WHERE status != 'offline'");
        $controllers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];

        foreach ($controllers as $controller) {
            try {
                $instance = self::createFromDatabase($controller);
                $status = $instance->testConnection();

                $results[$controller['id']] = [
                    'name' => $controller['name'],
                    'type' => $controller['type'],
                    'status' => $status ? 'online' : 'offline',
                    'tested_at' => date('Y-m-d H:i:s')
                ];

                // Update status in database
                $updateStmt = $db->prepare("
                    UPDATE controllers
                    SET status = :status, last_check = NOW()
                    WHERE id = :id
                ");
                $updateStmt->execute([
                    'status' => $status ? 'online' : 'offline',
                    'id' => $controller['id']
                ]);

            } catch (Exception $e) {
                $results[$controller['id']] = [
                    'name' => $controller['name'],
                    'type' => $controller['type'],
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'tested_at' => date('Y-m-d H:i:s')
                ];

                // Update status to error
                $updateStmt = $db->prepare("
                    UPDATE controllers
                    SET status = 'error', last_check = NOW()
                    WHERE id = :id
                ");
                $updateStmt->execute(['id' => $controller['id']]);
            }
        }

        return $results;
    }
}
