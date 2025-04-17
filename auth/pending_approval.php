<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in but not approved
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: " . BASE_URL . "/auth/farmer_login.php");
    exit;
}

if (isset($_SESSION['is_approved']) && $_SESSION['is_approved']) {
    header("Location: " . BASE_URL . "/farmer/dashboard.php");
    exit;
}

$page_title = "Account Pending Approval";
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Farmers Platform" class="auth-logo">
            <h1>Account Pending Approval</h1>
        </div>
        
        <div class="auth-message">
            <p>Thank you for registering, <?= htmlspecialchars($_SESSION['first_name'] ?? 'Farmer') ?>!</p>
            <p>Your farmer account is currently under review by our team.</p>
            <p>You'll receive an email notification once your account has been approved.</p>
            <p>For any questions, please contact our support team.</p>
        </div>
        
        <div class="auth-actions">
            <a href="<?= BASE_URL ?>/auth/logout.php" class="auth-button">Logout</a>
            <a href="mailto:support@agro.com" class="auth-button secondary">Contact Support</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>