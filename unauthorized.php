<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// At the top of unauthorized.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If somehow an authenticated user gets here
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/");
    exit();
}

$page_title = "Unauthorized";
include __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="alert alert-error">
        <h1>Access Denied</h1>
        <p>You don't have permission to access this page.</p>
        <a href="<?= BASE_URL ?>/farmer-platform/auth/login.php" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            Return to Login
        </a>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>