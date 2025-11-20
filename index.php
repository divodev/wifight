<?php
/**
 * WiFight ISP System - Welcome Page
 *
 * This is the main entry point for the web interface
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WiFight ISP System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 60px;
            max-width: 800px;
            text-align: center;
        }

        h1 {
            font-size: 3em;
            color: #667eea;
            margin-bottom: 20px;
        }

        .version {
            color: #888;
            font-size: 0.9em;
            margin-bottom: 40px;
        }

        .status {
            display: inline-block;
            padding: 10px 20px;
            background: #4caf50;
            color: white;
            border-radius: 25px;
            margin-bottom: 30px;
            font-weight: bold;
        }

        .status.error {
            background: #f44336;
        }

        .links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 40px;
        }

        .link-card {
            background: #f8f9fa;
            padding: 30px 20px;
            border-radius: 15px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .link-card:hover {
            transform: translateY(-5px);
            border-color: #667eea;
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.2);
        }

        .link-card h3 {
            color: #667eea;
            margin-bottom: 10px;
        }

        .link-card p {
            color: #666;
            font-size: 0.9em;
        }

        .info {
            margin-top: 40px;
            padding: 20px;
            background: #f0f4ff;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }

        .info h4 {
            color: #667eea;
            margin-bottom: 10px;
        }

        .info ul {
            text-align: left;
            list-style-position: inside;
            color: #666;
        }

        .info li {
            margin: 5px 0;
        }

        code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            color: #d63384;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>WiFight ISP</h1>
        <p class="version">Version 1.0.0 - Multi-Vendor ISP Management System</p>

        <?php
        // Check system status
        $dbConnected = false;
        try {
            require_once __DIR__ . '/backend/config/database.php';
            $db = new Database();
            $dbConnected = $db->testConnection();
        } catch (Exception $e) {
            $dbConnected = false;
        }
        ?>

        <div class="status <?php echo $dbConnected ? '' : 'error'; ?>">
            <?php echo $dbConnected ? '✓ System Online' : '✗ Database Not Connected'; ?>
        </div>

        <div class="links">
            <a href="/api/v1/health" class="link-card">
                <h3>🏥 Health Check</h3>
                <p>Check system health and status</p>
            </a>

            <a href="/frontend" class="link-card">
                <h3>🎨 Admin Dashboard</h3>
                <p>Access the admin panel</p>
            </a>

            <a href="/portal" class="link-card">
                <h3>🌐 User Portal</h3>
                <p>Customer self-service portal</p>
            </a>

            <a href="/docs" class="link-card">
                <h3>📚 Documentation</h3>
                <p>API docs and guides</p>
            </a>
        </div>

        <div class="info">
            <h4>🚀 Quick Start</h4>
            <ul>
                <li>Initialize the system: <code>php init.php</code></li>
                <li>Default admin email: <code>admin@wifight.local</code></li>
                <li>Default password: <code>admin123</code></li>
                <li>API endpoint: <code>/api/v1/</code></li>
                <li>Documentation: <code>.claude/plans/</code></li>
            </ul>
        </div>

        <div class="info" style="margin-top: 20px; background: #fff3cd; border-left-color: #ffc107;">
            <h4>⚠️ Security Notice</h4>
            <ul>
                <li>Change default admin password immediately</li>
                <li>Configure your <code>.env</code> file with secure credentials</li>
                <li>Enable HTTPS in production</li>
                <li>Review security settings in the admin panel</li>
            </ul>
        </div>
    </div>
</body>
</html>
