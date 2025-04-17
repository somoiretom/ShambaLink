<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$token = $_GET['token'] ?? '';
$success = false;
$message = '';

if (!empty($token)) {
    try {
        // Check token validity
        $stmt = $pdo->prepare("
            SELECT user_id 
            FROM verification_tokens 
            WHERE token = ? 
            AND expires_at > NOW() 
            AND used_at IS NULL
        ");
        $stmt->execute([$token]);
        $result = $stmt->fetch();
        
        if ($result) {
            // Mark user as verified
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                UPDATE users 
                SET is_verified = 1 
                WHERE id = ?
            ");
            $stmt->execute([$result['user_id']]);
            
            // Mark token as used
            $stmt = $pdo->prepare("
                UPDATE verification_tokens 
                SET used_at = NOW() 
                WHERE token = ?
            ");
            $stmt->execute([$token]);
            
            $pdo->commit();
            $success = true;
            $message = "Email verified successfully! You can now login.";
        } else {
            $message = "Invalid or expired verification link.";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Verification error: " . $e->getMessage());
        $message = "An error occurred during verification.";
    }
}

$page_title = "Email Verification";
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Farmer Platform" class="auth-logo">
            <h1>Email Verification</h1>
        </div>
        
        <div class="auth-message <?= $success ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        
        <div class="auth-footer">
            <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-primary">
                Go to Login
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>