<?php
// Application Constants
define('APP_NAME', 'WiFight');
define('APP_VERSION', '1.0.0');

// JWT Settings
define('JWT_SECRET', 'TUz2nupyRuK8dkwzH62+s80CNSLm3v8TkDm8dl2JM2/anoshB6Nf14ajwHl3UyUwXL4wa55zgZ5CbDbUGa9rkQ==');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRY', 3600); // 1 hour

// Paths
define('LOG_PATH', __DIR__ . '/../../storage/logs/');
define('UPLOAD_PATH', __DIR__ . '/../../storage/uploads/');
