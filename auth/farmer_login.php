<?php
declare(strict_types=1);

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400, // 1 day
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if already logged in
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'farmer') {
    header("Location: " . BASE_URL . "/farmer/dashboard.php");
    exit;
}

// Default test credentials (for development only)
const TEST_FARMER_EMAIL = 'testfarmer@agro.com';
const TEST_FARMER_PASSWORD = 'password';

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Validate inputs
    if (empty($email)) {
        $errors[] = "Email address is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }

    if (empty($password)) {
        $errors[] = "Password is required";
    }

    if (empty($errors)) {
        // Check for test farmer account (development only)
        if (APP_ENV === 'development' && $email === TEST_FARMER_EMAIL && $password === TEST_FARMER_PASSWORD) {
            // Create test farmer session
            $_SESSION = [
                'user_id' => 1,
                'email' => TEST_FARMER_EMAIL,
                'first_name' => 'Test',
                'last_name' => 'Farmer',
                'farm_name' => 'Test Farm',
                'role' => 'farmer',
                'is_approved' => true,
                'profile_pic' => null,
                'last_activity' => time()
            ];
            
            header("Location: " . BASE_URL . "/farmer/dashboard.php");
            exit;
        }

        // Real farmer login (in production)
        if (APP_ENV === 'production') {
            try {
                $stmt = $pdo->prepare("
                    SELECT u.id, u.email, u.first_name, u.last_name, u.profile_pic, 
                           f.farm_name, f.is_approved 
                    FROM users u
                    JOIN farmers f ON u.id = f.user_id
                    WHERE u.email = ? AND u.role = 'farmer'
                ");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && $password === $user['password']) { // Plain text comparison for testing
                    $_SESSION = [
                        'user_id' => $user['id'],
                        'email' => $user['email'],
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name'],
                        'farm_name' => $user['farm_name'],
                        'role' => 'farmer',
                        'is_approved' => (bool)$user['is_approved'],
                        'profile_pic' => $user['profile_pic'],
                        'last_activity' => time()
                    ];

                    header("Location: " . BASE_URL . "/farmer/dashboard.php");
                    exit;
                } else {
                    $errors[] = "Invalid email or password";
                }
            } catch (PDOException $e) {
                error_log("Login error: " . $e->getMessage());
                $errors[] = "System error. Please try again later.";
            }
        } else {
            $errors[] = "Invalid email or password";
        }
    }
}

$page_title = "Farmer Login";
include __DIR__ . '/../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> | <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="<?= SITE_NAME ?>" class="auth-logo">
                <h1>Farmer Login</h1>
                <p>Access your farmer dashboard</p>
            </div>

            <?php if (APP_ENV === 'development'): ?>
            <div class="test-credentials alert alert-info">
                <h4><i class="fas fa-flask"></i> Development Mode</h4>
                <p>Use these test credentials:</p>
                <p><strong>Email:</strong> <?= TEST_FARMER_EMAIL ?></p>
                <p><strong>Password:</strong> <?= TEST_FARMER_PASSWORD ?></p>
            </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
            <div class="auth-error alert alert-danger">
                <?php foreach ($errors as $error): ?>
                <p><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        </div>
                        <input type="email" id="email" name="email" 
                               value="<?= htmlspecialchars($email) ?>" 
                               class="form-control" required autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        </div>
                        <input type="password" id="password" name="password" class="form-control" required>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary toggle-password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="form-group form-check">
                    <input type="checkbox" id="remember" name="remember" class="form-check-input">
                    <label for="remember" class="form-check-label">Remember me</label>
                </div>
                
                <button type="submit" class="auth-button btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
                
                <div class="auth-links mt-3">
                    <a href="<?= BASE_URL ?>/auth/forgot_password.php" class="btn btn-link">
                        <i class="fas fa-question-circle"></i> Forgot Password?
                    </a>
                    <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-link">
                        <i class="fas fa-user-plus"></i> Create Account
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle password visibility
        document.querySelector('.toggle-password').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });

        // Auto-fill test credentials in development
        <?php if (APP_ENV === 'development'): ?>
        document.getElementById('email').value = '<?= TEST_FARMER_EMAIL ?>';
        document.getElementById('password').value = '<?= TEST_FARMER_PASSWORD ?>';
        <?php endif; ?>
    });
    </script>
</body>
</html>

<?php include __DIR__ . '/../includes/footer.php'; ?>