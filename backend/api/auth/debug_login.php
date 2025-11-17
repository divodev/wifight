<?php
// Turn on ALL error display
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

echo "=== LOGIN DEBUG START ===<br><br>";

// Test 1: PHP Version
echo "PHP Version: " . phpversion() . "<br><br>";

// Test 2: Check files exist
$files = [
    'database.php' => __DIR__ . '/../../config/database.php',
    'constants.php' => __DIR__ . '/../../config/constants.php',
    'User.php' => __DIR__ . '/../../models/User.php',
    'JWT.php' => __DIR__ . '/../../utils/JWT.php',
    'Response.php' => __DIR__ . '/../../utils/Response.php',
];

echo "=== FILE CHECK ===<br>";
foreach ($files as $name => $path) {
    if (file_exists($path)) {
        echo "✓ $name EXISTS<br>";
    } else {
        echo "✗ $name MISSING at: $path<br>";
    }
}
echo "<br>";

// Test 3: Try to include files
echo "=== INCLUDE TEST ===<br>";
try {
    require_once __DIR__ . '/../../config/database.php';
    echo "✓ database.php included<br>";
} catch (Exception $e) {
    echo "✗ database.php ERROR: " . $e->getMessage() . "<br>";
    exit();
}

try {
    require_once __DIR__ . '/../../config/constants.php';
    echo "✓ constants.php included<br>";
} catch (Exception $e) {
    echo "✗ constants.php ERROR: " . $e->getMessage() . "<br>";
    exit();
}

try {
    require_once __DIR__ . '/../../models/User.php';
    echo "✓ User.php included<br>";
} catch (Exception $e) {
    echo "✗ User.php ERROR: " . $e->getMessage() . "<br>";
    exit();
}

try {
    require_once __DIR__ . '/../../utils/JWT.php';
    echo "✓ JWT.php included<br>";
} catch (Exception $e) {
    echo "✗ JWT.php ERROR: " . $e->getMessage() . "<br>";
    exit();
}

echo "<br>";

// Test 4: Database connection
echo "=== DATABASE TEST ===<br>";
try {
    $database = new Database();
    $db = $database->getConnection();
    if ($db) {
        echo "✓ Database connected<br>";
        
        // Test query
        $stmt = $db->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "✓ Users table accessible. Count: " . $result['count'] . "<br>";
    } else {
        echo "✗ Database connection returned NULL<br>";
    }
} catch (Exception $e) {
    echo "✗ Database ERROR: " . $e->getMessage() . "<br>";
}

echo "<br>";

// Test 5: Find admin user
echo "=== USER TEST ===<br>";
try {
    $user = new User($db);
    $found = $user->findByEmail('admin@wifight.com');
    
    if ($found) {
        echo "✓ Admin user found<br>";
        echo "  ID: " . $user->id . "<br>";
        echo "  Name: " . $user->name . "<br>";
        echo "  Email: " . $user->email . "<br>";
        echo "  Role: " . $user->role . "<br>";
        echo "  Password hash: " . substr($user->password, 0, 20) . "...<br>";
    } else {
        echo "✗ Admin user NOT found<br>";
    }
} catch (Exception $e) {
    echo "✗ User lookup ERROR: " . $e->getMessage() . "<br>";
}

echo "<br>";

// Test 6: Password verification
echo "=== PASSWORD TEST ===<br>";
if (isset($user) && $found) {
    $testPass = 'admin123';
    if (password_verify($testPass, $user->password)) {
        echo "✓ Password 'admin123' is CORRECT<br>";
    } else {
        echo "✗ Password 'admin123' is WRONG<br>";
        echo "  Trying to rehash...<br>";
        $newHash = password_hash($testPass, PASSWORD_BCRYPT);
        echo "  New hash would be: " . substr($newHash, 0, 20) . "...<br>";
    }
}

echo "<br>";

// Test 7: JWT
echo "=== JWT TEST ===<br>";
try {
    $testPayload = ['user_id' => 1, 'exp' => time() + 3600];
    $token = JWT::encode($testPayload, JWT_SECRET, JWT_ALGORITHM);
    echo "✓ JWT encode works<br>";
    echo "  Token: " . substr($token, 0, 50) . "...<br>";
    
    $decoded = JWT::decode($token, JWT_SECRET);
    echo "✓ JWT decode works<br>";
    echo "  Decoded user_id: " . $decoded->user_id . "<br>";
} catch (Exception $e) {
    echo "✗ JWT ERROR: " . $e->getMessage() . "<br>";
}

echo "<br>=== DEBUG END ===";
?>
```
