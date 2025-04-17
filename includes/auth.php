<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Enhanced Session Management
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_name('farmer_platform_sess');
    session_start();
}

// Improved Authentication Functions
function isLoggedIn(): bool {
    return isset($_SESSION['user_id'], $_SESSION['last_activity']) && 
           (time() - $_SESSION['last_activity']) < SESSION_TIMEOUT;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header("Location: " . BASE_URL . "/admin/login.php"); // Changed from /admin/login.php
        exit();
    }
    $_SESSION['last_activity'] = time();
}

function requireRole($roles): void {
    requireLogin();
    
    // Debug output (remove in production)
    error_log("Checking role for user. Session roles: " . print_r($_SESSION['role'], true));
    error_log("Required roles: " . print_r((array)$roles, true));
    
    if (!in_array($_SESSION['role'], (array)$roles)) {
        error_log("Role check failed for user {$_SESSION['user_id']}");
        header("HTTP/1.1 403 Forbidden");
        header("Location: " . BASE_URL . "/unauthorized.php"); // Changed from /admin/unauthorized.php
        exit();
    }
}

function completeLogin(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role']; // Ensure this is set correctly
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'] ?? '';
    $_SESSION['last_activity'] = time();
    
    // Debug output
    error_log("User {$user['id']} logged in with role: {$user['role']}");
}

function logout(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

// CSRF Protection
function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken(?string $token): bool {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function isUserApproved(int $userId): bool {
    global $pdo;
    $stmt = $pdo->prepare("SELECT is_approved FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    return $user && $user['is_approved'];
}