<?php
/**
 * WiFight ISP System - Initialization Script
 *
 * Run this script to initialize the system
 * Usage: php init.php
 */

echo "\n";
echo "============================================\n";
echo "  WiFight ISP System - Initialization  \n";
echo "============================================\n\n";

// Check PHP version
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    die("ERROR: PHP 7.4 or higher is required. Current version: " . PHP_VERSION . "\n");
}

echo "[1/7] Checking PHP version... OK (" . PHP_VERSION . ")\n";

// Check required extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    die("ERROR: Missing required PHP extensions: " . implode(', ', $missingExtensions) . "\n");
}

echo "[2/7] Checking required PHP extensions... OK\n";

// Check if .env file exists
if (!file_exists(__DIR__ . '/.env')) {
    echo "[3/7] Creating .env file from .env.example...\n";
    if (copy(__DIR__ . '/.env.example', __DIR__ . '/.env')) {
        echo "      .env file created successfully!\n";
        echo "      IMPORTANT: Please edit .env file with your configuration\n";
    } else {
        die("ERROR: Could not create .env file\n");
    }
} else {
    echo "[3/7] .env file already exists... OK\n";
}

// Load environment variables
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Create necessary directories
echo "[4/7] Creating directory structure...\n";

$directories = [
    'storage/logs',
    'storage/uploads',
    'storage/backups',
    'storage/cache',
    'backend/api/v1',
    'database/migrations'
];

foreach ($directories as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (!is_dir($path)) {
        if (mkdir($path, 0755, true)) {
            echo "      Created: {$dir}\n";
        } else {
            echo "      WARNING: Could not create: {$dir}\n";
        }
    }
}

// Test database connection
echo "[5/7] Testing database connection...\n";

require_once __DIR__ . '/backend/config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    if ($conn) {
        echo "      Database connection successful!\n";

        // Ask if user wants to run migrations
        echo "\n      Do you want to initialize the database schema? (yes/no): ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);

        if (strtolower($line) === 'yes' || strtolower($line) === 'y') {
            echo "\n[6/7] Running database migrations...\n";

            $schemaFile = __DIR__ . '/database/schema.sql';

            if (file_exists($schemaFile)) {
                if ($db->executeMigration($schemaFile)) {
                    echo "      Database schema created successfully!\n";
                    echo "      Default admin credentials:\n";
                    echo "      Email: admin@wifight.local\n";
                    echo "      Password: admin123\n";
                    echo "      IMPORTANT: Change the admin password after first login!\n";
                } else {
                    echo "      WARNING: Database migration failed. Check the logs.\n";
                }
            } else {
                echo "      WARNING: Schema file not found: {$schemaFile}\n";
            }
        } else {
            echo "      Skipping database initialization.\n";
        }
    }
} catch (Exception $e) {
    echo "      ERROR: Database connection failed: " . $e->getMessage() . "\n";
    echo "      Please check your .env database configuration\n";
}

// Create .htaccess for API routing (if Apache)
echo "[7/7] Creating .htaccess file for API routing...\n";

$htaccess = <<<'HTACCESS'
# WiFight ISP System - Apache Configuration

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /

    # Redirect all API requests to backend/api/index.php
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^api/(.*)$ backend/api/index.php [QSA,L]
</IfModule>

# Security Headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "DENY"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>

# Disable directory listing
Options -Indexes

# Protect sensitive files
<FilesMatch "\.(env|log|sql)$">
    Order allow,deny
    Deny from all
</FilesMatch>
HTACCESS;

if (file_put_contents(__DIR__ . '/.htaccess', $htaccess)) {
    echo "      .htaccess file created successfully!\n";
} else {
    echo "      WARNING: Could not create .htaccess file\n";
}

// Summary
echo "\n============================================\n";
echo "  Initialization Complete!  \n";
echo "============================================\n\n";

echo "Next steps:\n";
echo "1. Edit .env file with your configuration\n";
echo "2. Configure your web server to point to this directory\n";
echo "3. Access the API at: http://localhost/api/v1/health\n";
echo "4. Login with default admin credentials (see above)\n";
echo "5. Start implementing Phase 2: Controller Integration\n\n";

echo "For documentation, see: .claude/plans/\n";
echo "For help, visit: https://github.com/wifight\n\n";
