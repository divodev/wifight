<?php
// Application Constants
define('APP_NAME', 'WiFight');
define('APP_VERSION', '1.0.0');

// JWT Settings
define('JWT_SECRET', 'fy6IWfSxV8Qf5COKjWJqsbSg8FzBcs0iOId0XnMEW83mY+zWOjcKGVb/lHGVgqgt+P5ppgHJTMWKEtMQhtTpgQ==');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRY', 3600); // 1 hour

// Paths
define('LOG_PATH', __DIR__ . '/../../storage/logs/');
define('UPLOAD_PATH', __DIR__ . '/../../storage/uploads/');
