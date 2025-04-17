// auth/login.php
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $errors[] = "Invalid CSRF token";
    }
    
    // Default password check (temporary measure)
    $default_password = "Farmer@123"; // Default password that farmers should change
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'farmer'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Check if using default password or hashed password
        if ($password === $default_password || password_verify($password, $user['password'])) {
            // Force password change if using default
            if ($password === $default_password) {
                $_SESSION['force_password_change'] = true;
                $_SESSION['temp_user_id'] = $user['id'];
                header("Location: " . BASE_URL . "/auth/change-password.php");
                exit();
            }
            
            // Complete login
            completeLogin([
                'id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name']
            ]);
            
            // Redirect to dashboard
            header("Location: " . BASE_URL . "/farmer-dashboard/");
            exit();
        } else {
            $errors[] = "Invalid email or password";
        }
    } else {
        $errors[] = "Invalid email or password";
    }
}

// ... rest of login form HTML ...
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Logo" class="auth-logo">
            <h1>Login to Your Account</h1>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" 
                       value="<?= htmlspecialchars($email) ?>" 
                       required
                       autofocus
                       autocomplete="email">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-input">
                    <input type="password" id="password" name="password" 
                           required
                           autocomplete="current-password">
                    <button type="button" class="toggle-password" aria-label="Show password">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-block">
                    Sign In
                </button>
            </div>
            
            <div class="auth-footer">
                <a href="<?= BASE_URL ?>/auth/forgot-password.php">Forgot password?</a>
                <span>•</span>
                <a href="<?= BASE_URL ?>/auth/register.php">Create account</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            this.classList.toggle('active');
            this.setAttribute('aria-label', 
                type === 'password' ? 'Show password' : 'Hide password');
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>