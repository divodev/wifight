
<?php
// backend/fix_password.php
// IMPORTANT: DELETE THIS FILE AFTER USE!

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>WiFight Password Fix</h2>";

// Direct connection
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=wifight_db;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "<p>✓ Database connected</p>";
    
    // Check current admin
    $stmt = $pdo->prepare("SELECT id, email, full_name FROM users WHERE email = 'admin@wifight.com'");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "<p>✓ Admin user found:</p>";
        echo "<ul>";
        echo "<li>ID: " . $user['id'] . "</li>";
        echo "<li>Email: " . $user['email'] . "</li>";
        echo "<li>Name: " . $user['full_name'] . "</li>";
        echo "</ul>";
        
        // Generate new password hash
        $newPassword = 'admin123';
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        
        echo "<p>New password hash generated: " . substr($hashedPassword, 0, 30) . "...</p>";
        
        // Update password
        $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = 'admin@wifight.com'");
        $updateStmt->execute([$hashedPassword]);
        
        echo "<p><strong>✓ Password updated successfully!</strong></p>";
        echo "<hr>";
        echo "<h3>Login Credentials:</h3>";
        echo "<p><strong>Email:</strong> admin@wifight.com</p>";
        echo "<p><strong>Password:</strong> admin123</p>";
        echo "<hr>";
        
        // Verify the hash works
        $verifyStmt = $pdo->prepare("SELECT password FROM users WHERE email = 'admin@wifight.com'");
        $verifyStmt->execute();
        $verifyUser = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        
        if (password_verify($newPassword, $verifyUser['password'])) {
            echo "<p>✓ Password verification TEST PASSED</p>";
        } else {
            echo "<p>✗ Password verification TEST FAILED</p>";
        }
        
        echo "<hr>";
        echo "<p style='color: red; font-weight: bold;'>⚠️ IMPORTANT: DELETE THIS FILE (fix_password.php) NOW!</p>";
        echo "<p><a href='../frontend/index.html'>Go to Login Page</a></p>";
        
    } else {
        echo "<p>✗ Admin user not found in database</p>";
        echo "<p>Creating admin user...</p>";
        
        $password = password_hash('admin123', PASSWORD_BCRYPT);
        $insertStmt = $pdo->prepare("
            INSERT INTO users (email, password, full_name, role, status, created_at) 
            VALUES ('admin@wifight.com', ?, 'Admin User', 'admin', 'active', NOW())
        ");
        $insertStmt->execute([$password]);
        
        echo "<p>✓ Admin user created!</p>";
        echo "<p><strong>Email:</strong> admin@wifight.com</p>";
        echo "<p><strong>Password:</strong> admin123</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Database Error: " . $e->getMessage() . "</p>";
}
?>