<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// TEMPORARY TESTING CONFIGURATION - REMOVE FOR PRODUCTION
$testAccounts = [
    'admin@test.com' => [
        'password' => 'admin123',
        'user_data' => [
            'id' => 1,
            'email' => 'admin@test.com',
            'role' => 'admin',
            'first_name' => 'Test',
            'last_name' => 'Admin'
        ]
    ],
    'super@test.com' => [
        'password' => 'super123',
        'user_data' => [
            'id' => 2,
            'email' => 'super@test.com',
            'role' => 'admin',
            'first_name' => 'Super',
            'last_name' => 'Admin'
        ]
    ]
];

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // TEMPORARY TESTING LOGIN - NO PASSWORD HASHING
    if (isset($testAccounts[$email]) && $testAccounts[$email]['password'] === $password) {
        completeLogin($testAccounts[$email]['user_data']);
        header("Location: dashboard.php");
        exit();
    } else {
        $error = 'Invalid test credentials';
    }
}

// Generate CSRF token for basic protection even in testing
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login </title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .login-container { max-width: 400px; margin: 100px auto; padding: 20px; background: white; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: rgb(32, 101, 38); text-align: center; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input[type="email"], input[type="password"] { width: 100%; padding: 8px; box-sizing: border-box; }
        button { background:rgb(14, 107, 21); color: white; border: none; padding: 10px 15px; width: 100%; cursor: pointer; }
        .error { color:rgb(32, 101, 38); margin-top: 10px; }
        .test-accounts { margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>ADMIN LOGIN</h1>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit">Login</button>
        </form>
        
        <div class="test-accounts">
            <h3>Test Accounts:</h3>
            <ul>
                <li><strong>admin@test.com</strong> / admin123</li>
                <li><strong>super@test.com</strong> / super123</li>
            </ul>
        </div>
    </div>
</body>
</html>