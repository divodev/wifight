<?php
// backend/manage_users.php
// User Management Tool - Keep this file secure!

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simple password protection
$adminKey = 'wifight2024'; // Change this!

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['verify_key'])) {
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_POST['verify_key']) && $_POST['verify_key'] !== $adminKey) {
    die('Invalid access key');
}

$showForm = !isset($_POST['verify_key']);

?>
<!DOCTYPE html>
<html>
<head>
    <title>WiFight User Management</title>
    <style>
        body { font-family: Arial; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; background: #d4edda; padding: 10px; border-radius: 5px; }
        .error { color: red; background: #f8d7da; padding: 10px; border-radius: 5px; }
        input, select { padding: 8px; margin: 5px 0; width: 100%; }
        button { padding: 10px 20px; background: #4F46E5; color: white; border: none; cursor: pointer; }
        button:hover { background: #4338CA; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f4f4f4; }
        .action-btn { padding: 5px 10px; margin: 2px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>WiFight User Management</h1>

<?php if ($showForm): ?>
    <form method="POST">
        <h3>Enter Access Key:</h3>
        <input type="password" name="verify_key" required>
        <button type="submit">Access</button>
    </form>
<?php else: ?>

    <?php
    // Connect to database
    try {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=wifight_db;charset=utf8mb4',
            'root',
            '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (PDOException $e) {
        die('<div class="error">Database Error: ' . $e->getMessage() . '</div>');
    }

    // Handle actions
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create') {
            $email = $_POST['email'];
            $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $fullName = $_POST['full_name'];
            $role = $_POST['role'];
            
            $stmt = $pdo->prepare("
                INSERT INTO users (email, password, full_name, role, status, created_at) 
                VALUES (?, ?, ?, ?, 'active', NOW())
            ");
            
            try {
                $stmt->execute([$email, $password, $fullName, $role]);
                echo '<div class="success">✓ User created successfully!</div>';
            } catch (PDOException $e) {
                echo '<div class="error">✗ Error: ' . $e->getMessage() . '</div>';
            }
        }
        
        if ($_POST['action'] === 'reset_password') {
            $userId = $_POST['user_id'];
            $newPassword = $_POST['new_password'];
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $userId]);
            
            echo '<div class="success">✓ Password reset successfully!</div>';
        }
        
        if ($_POST['action'] === 'delete') {
            $userId = $_POST['user_id'];
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            
            echo '<div class="success">✓ User deleted successfully!</div>';
        }
    }
    ?>

    <h2>Create New User</h2>
    <form method="POST" style="background: #f9f9f9; padding: 20px; border-radius: 5px;">
        <input type="hidden" name="verify_key" value="<?php echo htmlspecialchars($_POST['verify_key']); ?>">
        <input type="hidden" name="action" value="create">
        
        <label>Email:</label>
        <input type="email" name="email" required>
        
        <label>Full Name:</label>
        <input type="text" name="full_name" required>
        
        <label>Password:</label>
        <input type="password" name="password" required>
        
        <label>Role:</label>
        <select name="role" required>
            <option value="admin">Admin</option>
            <option value="manager">Manager</option>
            <option value="staff">Staff</option>
            <option value="customer">Customer</option>
        </select>
        
        <button type="submit">Create User</button>
    </form>

    <h2>Existing Users</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Email</th>
            <th>Full Name</th>
            <th>Role</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        <?php
        $stmt = $pdo->query("SELECT * FROM users ORDER BY id");
        while ($user = $stmt->fetch(PDO::FETCH_ASSOC)):
        ?>
        <tr>
            <td><?php echo $user['id']; ?></td>
            <td><?php echo htmlspecialchars($user['email']); ?></td>
            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
            <td><?php echo $user['role']; ?></td>
            <td><?php echo $user['status']; ?></td>
            <td>
                <button class="action-btn" onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['email']); ?>')">
                    Reset Password
                </button>
                <?php if ($user['email'] !== 'admin@wifight.com'): ?>
                <button class="action-btn" style="background: #dc2626;" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['email']); ?>')">
                    Delete
                </button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>

    <script>
        function resetPassword(userId, email) {
            const newPassword = prompt('Enter new password for ' + email + ':');
            if (newPassword && newPassword.length >= 6) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="verify_key" value="<?php echo htmlspecialchars($_POST['verify_key']); ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" value="${userId}">
                    <input type="hidden" name="new_password" value="${newPassword}">
                `;
                document.body.appendChild(form);
                form.submit();
            } else {
                alert('Password must be at least 6 characters');
            }
        }

        function deleteUser(userId, email) {
            if (confirm('Delete user ' + email + '?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="verify_key" value="<?php echo htmlspecialchars($_POST['verify_key']); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>

<?php endif; ?>

</body>
</html>