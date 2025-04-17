<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/auth.php';

// Only accessible if password change is required
if (!isset($_SESSION['force_password_change'])) {
    header("Location: login.php");
    exit();
}

requireRole('admin');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            
            // Update password
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            
            // Remove force password change flag
            unset($_SESSION['force_password_change']);
            
            // Log the password change
            $pdo->prepare("INSERT INTO user_logs (user_id, action) VALUES (?, 'password_change')")
                ->execute([$_SESSION['user_id']]);
            
            $success = 'Password changed successfully! Redirecting to dashboard...';
            
            // Redirect after 3 seconds
            header("Refresh: 3; url=dashboard.php");
            
        } catch (PDOException $e) {
            error_log("Password change error: " . $e->getMessage());
            $error = 'Failed to change password. Please try again.';
        }
    }
}

$csrf_token = generateCSRFToken();
$page_title = "Change Password";
$custom_css = BASE_URL . "/assets/css/auth.css";

include $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Change Password</h1>
            <p>You must change your password before continuing</p>
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
                <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
            
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" class="form-control" 
                       required minlength="8" autocomplete="new-password">
                <small class="form-text">Minimum 8 characters</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                       required minlength="8" autocomplete="new-password">
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">
                <i class="material-icons">lock_reset</i> Change Password
            </button>
        </form>
    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/footer.php'; ?>