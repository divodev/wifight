<?php
/**
 * JWT Secret Generator
 * Run this once, copy the secret, and delete this file
 */

echo "=== JWT Secret Generator ===\n\n";

// Method 1: Random bytes (Most Secure)
$secret1 = base64_encode(random_bytes(64));
echo "Method 1 - Base64 Random (Recommended):\n";
echo $secret1 . "\n\n";

// Method 2: Hex Random
$secret2 = bin2hex(random_bytes(32));
echo "Method 2 - Hex Random:\n";
echo $secret2 . "\n\n";

// Method 3: Alphanumeric Random
$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()_+-=[]{}|;:,.<>?';
$secret3 = '';
for ($i = 0; $i < 64; $i++) {
    $secret3 .= $characters[random_int(0, strlen($characters) - 1)];
}
echo "Method 3 - Alphanumeric:\n";
echo $secret3 . "\n\n";

// Generate update instructions
echo "=== INSTRUCTIONS ===\n";
echo "1. Copy ONE of the secrets above\n";
echo "2. Open: backend/config/constants.php\n";
echo "3. Replace the JWT_SECRET value\n";
echo "4. DELETE this file (generate_jwt_secret.php)\n\n";

echo "Example for constants.php:\n";
echo "define('JWT_SECRET', '{$secret1}');\n\n";

// Also create a ready-to-use constants.php snippet
$constantsContent = "<?php\n";
$constantsContent .= "// Application Constants\n";
$constantsContent .= "define('APP_NAME', 'WiFight');\n";
$constantsContent .= "define('APP_VERSION', '1.0.0');\n\n";
$constantsContent .= "// JWT Settings\n";
$constantsContent .= "define('JWT_SECRET', '{$secret1}');\n";
$constantsContent .= "define('JWT_ALGORITHM', 'HS256');\n";
$constantsContent .= "define('JWT_EXPIRY', 3600); // 1 hour\n\n";
$constantsContent .= "// Paths\n";
$constantsContent .= "define('LOG_PATH', __DIR__ . '/../../storage/logs/');\n";
$constantsContent .= "define('UPLOAD_PATH', __DIR__ . '/../../storage/uploads/');\n";

file_put_contents('constants_new.php', $constantsContent);
echo "✓ Created 'constants_new.php' with secret already inserted!\n";
echo "  You can rename it to 'constants.php' and move it to backend/config/\n\n";

echo "⚠ SECURITY WARNING: Delete this script after use!\n";