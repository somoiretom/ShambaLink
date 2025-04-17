<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectBasedOnRole();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        try {
            // Check if admin exists
            $stmt = $pdo->prepare("SELECT id, first_name FROM users WHERE email = ? AND role = 'admin' LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate reset token (valid for 1 hour)
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600);
                
                // Store token in database
                $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)")
                    ->execute([$user['id'], $token, $expires]);
                
                // Send email (in a real implementation)
                $resetLink = BASE_URL . "/admin/reset-password.php?token=$token";
                
                // In production, you would send an actual email here
                $success = "Password reset link has been sent to your email. (Demo: <a href='$resetLink'>$resetLink</a>)";
                
                // Log the reset request
                $pdo->prepare("INSERT INTO user_logs (user_id, action) VALUES (?, 'password_reset_request')")
                    ->execute([$user['id']]);
            } else {
                // Generic success message to prevent email enumeration
                $success = "If an account exists with this email, a reset link has been sent.";
            }
        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            $error = 'A system error occurred. Please try again later.';
        }
    }
}

$csrf_token = generateCSRFToken();
$page_title = "Forgot Password";
$custom_css = BASE_URL . "/assets/css/auth.css";

include $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Forgot Password</h1>
            <p>Enter your email to receive a reset link</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="material-icons">error</i>
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="material-icons">check_circle</i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" 
                       required autofocus autocomplete="username">
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">
                <i class="material-icons">email</i> Send Reset Link
            </button>
            
            <div class="text-center mt-3">
                <a href="login.php" class="btn btn-link">Back to Login</a>
            </div>
        </form>
    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/footer.php'; ?>