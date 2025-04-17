<?php
declare(strict_types=1);

function farmerAuthGuard(): void {
    // Initialize session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 86400,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => (defined('APP_ENV') && APP_ENV === 'production'),
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        session_start();
    }

    // Check if user is authenticated as a farmer
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
        header("Location: " . BASE_URL . "/auth/farmer_login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }

    // Verify account approval status
    if (!isset($_SESSION['is_approved']) || !$_SESSION['is_approved']) {
        header("Location: " . BASE_URL . "/auth/pending_approval.php");
        exit;
    }

    // Check for session timeout
    if (isset($_SESSION['last_activity']) && defined('SESSION_TIMEOUT')) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            header("Location: " . BASE_URL . "/auth/farmer_login.php?timeout=1");
            exit;
        }
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();

    // Additional security headers
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header_remove("X-Powered-By");
}