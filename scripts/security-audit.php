<?php
/**
 * WiFight ISP System - Security Audit Script
 *
 * Performs comprehensive security checks on the WiFight system
 * Run from command line: php scripts/security-audit.php
 */

// CLI only
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

class SecurityAudit {
    private $issues = [];
    private $warnings = [];
    private $passed = [];
    private $basePath;

    public function __construct() {
        $this->basePath = dirname(__DIR__);
    }

    /**
     * Run all security checks
     */
    public function runAudit() {
        echo "╔══════════════════════════════════════════════════════════╗\n";
        echo "║     WiFight ISP System - Security Audit Report          ║\n";
        echo "╚══════════════════════════════════════════════════════════╝\n\n";

        echo "Starting security audit...\n\n";

        $this->checkEnvironmentConfiguration();
        $this->checkFilePermissions();
        $this->checkDatabaseSecurity();
        $this->checkPHPConfiguration();
        $this->checkDependencies();
        $this->checkCodeVulnerabilities();
        $this->checkEncryption();
        $this->checkSessionSecurity();

        $this->displayReport();
    }

    /**
     * Check environment configuration
     */
    private function checkEnvironmentConfiguration() {
        echo "🔍 Checking environment configuration...\n";

        // Check if .env exists
        if (!file_exists($this->basePath . '/.env')) {
            $this->addIssue('CRITICAL', '.env file not found');
        } else {
            $this->addPassed('.env file exists');

            // Load .env and check critical variables
            $envContent = file_get_contents($this->basePath . '/.env');
            $lines = explode("\n", $envContent);
            $env = [];

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) continue;
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $env[trim($parts[0])] = trim($parts[1]);
                }
            }

            // Check JWT_SECRET
            if (!isset($env['JWT_SECRET']) || strlen($env['JWT_SECRET']) < 32) {
                $this->addIssue('CRITICAL', 'JWT_SECRET is not set or too weak (< 32 characters)');
            } else {
                $this->addPassed('JWT_SECRET is properly configured');
            }

            // Check APP_DEBUG
            if (isset($env['APP_DEBUG']) && $env['APP_DEBUG'] === 'true') {
                $this->addWarning('APP_DEBUG is enabled (should be false in production)');
            } else {
                $this->addPassed('APP_DEBUG is disabled');
            }

            // Check database password
            if (isset($env['DB_PASSWORD']) && (empty($env['DB_PASSWORD']) || $env['DB_PASSWORD'] === 'root')) {
                $this->addWarning('Database password is weak or default');
            }

            // Check ENCRYPTION_KEY
            if (!isset($env['ENCRYPTION_KEY']) || strlen($env['ENCRYPTION_KEY']) < 32) {
                $this->addWarning('ENCRYPTION_KEY is not set or too weak');
            } else {
                $this->addPassed('ENCRYPTION_KEY is properly configured');
            }
        }

        // Check .env.example
        if (!file_exists($this->basePath . '/.env.example')) {
            $this->addWarning('.env.example file not found');
        }

        echo "\n";
    }

    /**
     * Check file and directory permissions
     */
    private function checkFilePermissions() {
        echo "🔍 Checking file permissions...\n";

        $criticalPaths = [
            '.env' => 0600,
            'storage' => 0755,
            'storage/logs' => 0755,
            'storage/cache' => 0755,
            'backend/config' => 0755
        ];

        foreach ($criticalPaths as $path => $expectedPerms) {
            $fullPath = $this->basePath . '/' . $path;

            if (!file_exists($fullPath)) {
                $this->addWarning("Path not found: $path");
                continue;
            }

            $perms = fileperms($fullPath) & 0777;

            if (is_file($fullPath)) {
                if ($perms > $expectedPerms) {
                    $this->addWarning("File $path has overly permissive permissions: " . decoct($perms));
                } else {
                    $this->addPassed("File $path has appropriate permissions");
                }
            } else {
                if ($perms != $expectedPerms) {
                    $this->addWarning("Directory $path permissions: " . decoct($perms) . " (expected " . decoct($expectedPerms) . ")");
                } else {
                    $this->addPassed("Directory $path has appropriate permissions");
                }
            }
        }

        // Check for exposed sensitive files
        $sensitiveFiles = ['.env', '.git', 'composer.json', 'composer.lock'];
        foreach ($sensitiveFiles as $file) {
            if (file_exists($this->basePath . '/' . $file)) {
                $this->addWarning("Sensitive file $file exists (ensure it's not web-accessible)");
            }
        }

        echo "\n";
    }

    /**
     * Check database security
     */
    private function checkDatabaseSecurity() {
        echo "🔍 Checking database security...\n";

        // Check if database.php uses PDO with prepared statements
        $dbConfigPath = $this->basePath . '/backend/config/database.php';
        if (file_exists($dbConfigPath)) {
            $content = file_get_contents($dbConfigPath);

            if (strpos($content, 'PDO') !== false) {
                $this->addPassed('Database uses PDO');
            } else {
                $this->addIssue('HIGH', 'Database connection not using PDO');
            }

            if (strpos($content, 'prepare') !== false) {
                $this->addPassed('Database uses prepared statements');
            }
        }

        // Check for SQL injection vulnerabilities in API files
        $apiFiles = glob($this->basePath . '/backend/api/**/*.php');
        $foundVulnerability = false;

        foreach ($apiFiles as $file) {
            $content = file_get_contents($file);

            // Check for unsafe query patterns
            if (preg_match('/\$_(?:GET|POST|REQUEST)\s*\[.*?\].*?query|mysql_query|mysqli_query/i', $content)) {
                $this->addIssue('CRITICAL', "Potential SQL injection vulnerability in " . basename($file));
                $foundVulnerability = true;
            }
        }

        if (!$foundVulnerability) {
            $this->addPassed('No obvious SQL injection vulnerabilities detected');
        }

        echo "\n";
    }

    /**
     * Check PHP configuration
     */
    private function checkPHPConfiguration() {
        echo "🔍 Checking PHP configuration...\n";

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            $this->addIssue('HIGH', 'PHP version ' . PHP_VERSION . ' is outdated (7.4+ recommended)');
        } else {
            $this->addPassed('PHP version ' . PHP_VERSION . ' is adequate');
        }

        // Check display_errors
        if (ini_get('display_errors')) {
            $this->addWarning('display_errors is enabled (should be off in production)');
        } else {
            $this->addPassed('display_errors is disabled');
        }

        // Check expose_php
        if (ini_get('expose_php')) {
            $this->addWarning('expose_php is enabled (reveals PHP version)');
        } else {
            $this->addPassed('expose_php is disabled');
        }

        // Check session settings
        if (ini_get('session.cookie_httponly') != 1) {
            $this->addIssue('MEDIUM', 'session.cookie_httponly should be enabled');
        } else {
            $this->addPassed('session.cookie_httponly is enabled');
        }

        if (ini_get('session.cookie_secure') != 1) {
            $this->addWarning('session.cookie_secure should be enabled in production');
        }

        // Check file upload size
        $maxUpload = ini_get('upload_max_filesize');
        $maxPost = ini_get('post_max_size');
        $this->addPassed("Upload limits: $maxUpload (upload), $maxPost (post)");

        echo "\n";
    }

    /**
     * Check dependencies
     */
    private function checkDependencies() {
        echo "🔍 Checking dependencies...\n";

        // Check if composer.lock exists
        if (!file_exists($this->basePath . '/composer.lock')) {
            $this->addWarning('composer.lock not found - dependencies not locked');
        } else {
            $this->addPassed('Composer dependencies are locked');

            // Check for outdated dependencies
            $lockContent = file_get_contents($this->basePath . '/composer.lock');
            $lock = json_decode($lockContent, true);

            if (isset($lock['packages'])) {
                $packageCount = count($lock['packages']);
                $this->addPassed("$packageCount packages installed");
            }
        }

        // Check required PHP extensions
        $requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'json', 'curl'];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $this->addIssue('HIGH', "Required PHP extension '$ext' is not loaded");
            } else {
                $this->addPassed("PHP extension '$ext' is loaded");
            }
        }

        echo "\n";
    }

    /**
     * Check for common code vulnerabilities
     */
    private function checkCodeVulnerabilities() {
        echo "🔍 Checking for code vulnerabilities...\n";

        // Check for hardcoded credentials
        $phpFiles = glob($this->basePath . '/{backend,scripts}/**/*.php', GLOB_BRACE);
        $foundCredentials = false;

        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);

            // Check for common password patterns
            if (preg_match('/password\s*=\s*["\'](?!.*getenv|.*\$).+["\']/i', $content)) {
                $this->addWarning("Potential hardcoded password in " . basename($file));
                $foundCredentials = true;
            }
        }

        if (!$foundCredentials) {
            $this->addPassed('No hardcoded credentials detected');
        }

        // Check for dangerous functions
        $dangerousFunctions = ['eval', 'exec', 'system', 'passthru', 'shell_exec'];
        $foundDangerous = false;

        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);

            foreach ($dangerousFunctions as $func) {
                if (preg_match('/\b' . $func . '\s*\(/i', $content)) {
                    $this->addWarning("Dangerous function '$func' found in " . basename($file));
                    $foundDangerous = true;
                }
            }
        }

        if (!$foundDangerous) {
            $this->addPassed('No dangerous functions detected');
        }

        echo "\n";
    }

    /**
     * Check encryption implementation
     */
    private function checkEncryption() {
        echo "🔍 Checking encryption implementation...\n";

        // Check if Encryption utility exists
        if (file_exists($this->basePath . '/backend/utils/Encryption.php')) {
            $this->addPassed('Encryption utility exists');

            $content = file_get_contents($this->basePath . '/backend/utils/Encryption.php');

            if (strpos($content, 'AES-256') !== false) {
                $this->addPassed('Using AES-256 encryption');
            }

            if (strpos($content, 'openssl') !== false) {
                $this->addPassed('Using OpenSSL for encryption');
            }
        } else {
            $this->addIssue('MEDIUM', 'Encryption utility not found');
        }

        // Check JWT implementation
        if (file_exists($this->basePath . '/backend/utils/JWT.php')) {
            $this->addPassed('JWT utility exists');
        } else {
            $this->addIssue('HIGH', 'JWT utility not found');
        }

        echo "\n";
    }

    /**
     * Check session security
     */
    private function checkSessionSecurity() {
        echo "🔍 Checking session security...\n";

        // Check CSRF implementation
        if (file_exists($this->basePath . '/backend/middleware/CSRF.php')) {
            $this->addPassed('CSRF protection middleware exists');
        } else {
            $this->addIssue('HIGH', 'CSRF protection not implemented');
        }

        // Check rate limiting
        if (file_exists($this->basePath . '/backend/middleware/RateLimit.php')) {
            $this->addPassed('Rate limiting middleware exists');
        } else {
            $this->addIssue('MEDIUM', 'Rate limiting not implemented');
        }

        // Check security headers
        if (file_exists($this->basePath . '/backend/middleware/SecurityHeaders.php')) {
            $this->addPassed('Security headers middleware exists');
        } else {
            $this->addWarning('Security headers middleware not found');
        }

        echo "\n";
    }

    /**
     * Add critical issue
     */
    private function addIssue($severity, $message) {
        $this->issues[] = ['severity' => $severity, 'message' => $message];
    }

    /**
     * Add warning
     */
    private function addWarning($message) {
        $this->warnings[] = $message;
    }

    /**
     * Add passed check
     */
    private function addPassed($message) {
        $this->passed[] = $message;
    }

    /**
     * Display audit report
     */
    private function displayReport() {
        echo "╔══════════════════════════════════════════════════════════╗\n";
        echo "║                    AUDIT SUMMARY                          ║\n";
        echo "╚══════════════════════════════════════════════════════════╝\n\n";

        // Critical Issues
        if (!empty($this->issues)) {
            echo "❌ CRITICAL ISSUES (" . count($this->issues) . "):\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            foreach ($this->issues as $issue) {
                echo "[{$issue['severity']}] {$issue['message']}\n";
            }
            echo "\n";
        }

        // Warnings
        if (!empty($this->warnings)) {
            echo "⚠️  WARNINGS (" . count($this->warnings) . "):\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            foreach ($this->warnings as $warning) {
                echo "• $warning\n";
            }
            echo "\n";
        }

        // Passed Checks
        echo "✅ PASSED CHECKS (" . count($this->passed) . "):\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        foreach ($this->passed as $passed) {
            echo "• $passed\n";
        }
        echo "\n";

        // Overall Status
        echo "╔══════════════════════════════════════════════════════════╗\n";
        if (empty($this->issues)) {
            echo "║  Overall Status: ✅ NO CRITICAL ISSUES                   ║\n";
        } else {
            echo "║  Overall Status: ❌ CRITICAL ISSUES FOUND                ║\n";
        }
        echo "╚══════════════════════════════════════════════════════════╝\n\n";

        // Recommendations
        if (!empty($this->issues) || !empty($this->warnings)) {
            echo "📋 RECOMMENDATIONS:\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "1. Address all CRITICAL issues immediately\n";
            echo "2. Review and fix WARNINGS before deploying to production\n";
            echo "3. Keep dependencies updated regularly\n";
            echo "4. Run security audits periodically\n";
            echo "5. Review OWASP Top 10 guidelines\n";
            echo "\n";
        }

        echo "Audit completed at: " . date('Y-m-d H:i:s') . "\n";
    }
}

// Run the audit
$audit = new SecurityAudit();
$audit->runAudit();
