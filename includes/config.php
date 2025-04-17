<?php
declare(strict_types=1);

// Base Configuration
define('BASE_URL', 'http://localhost/farmer-platform');
define('APP_ENV', 'development'); // Change to 'production' when live

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'farmer_platform');
define('DB_USER', 'root');
define('DB_PASS', '');
// In includes/config.php
define('SITE_NAME', 'Farmer Platform'); // Add this line near other constants

// Session Configuration
define('SESSION_TIMEOUT', 1800); // 30 minutes

// Weather API Configuration
define('WEATHER_API_KEY', '4fe2094ba52d776f0afa3cb7f7997a88');
define('WEATHER_API_URL', 'https://api.weatherapi.com/v1');

define('WEATHER_CACHE_TIME', 3600); // 1 hour cache
define('DEFAULT_LAT', -1.2921); // Nairobi coordinates
define('DEFAULT_LON', 36.8219);

// Create database connection
try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("System temporarily unavailable. Please try again later.");
}

// Debug mode - set to false in production
define('DEBUG_MODE', true);



// Database configurat

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
