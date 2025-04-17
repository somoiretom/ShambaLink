<?php
declare(strict_types=1);

/**
 * Farmer Platform Utility Functions
 * Includes: Sanitization, File Uploads, Database Helpers, and Weather API
 */

// ==============================================
// Input Validation & Sanitization
// ==============================================

/**
 * Sanitizes string input
 * @param string $input Raw input string
 * @return string Sanitized string
 */
function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validates and sanitizes email address
 * @param string $email Raw email input
 * @return string|false Validated email or false
 */
function sanitizeEmail(string $email): string|false {
    $email = trim($email);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? filter_var($email, FILTER_SANITIZE_EMAIL) : false;
}

// ==============================================
// File Handling
// ==============================================

/**
 * Secure file upload with validation
 * @param array $file $_FILES array element
 * @param string $targetDir Upload directory
 * @param array $allowedTypes Allowed MIME types
 * @param int $maxSize Max file size in bytes (default: 5MB)
 * @return array ['success'=>bool, 'message'=>string, 'filename'=>string]
 */
function uploadFile(
    array $file, 
    string $targetDir, 
    array $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'],
    int $maxSize = 5242880
): array {
    // Validate upload error code
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error: ' . $file['error']];
    }

    // Verify file type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes)];
    }

    // Check file size
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File too large. Max size: ' . formatBytes($maxSize)];
    }

    // Generate secure filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = bin2hex(random_bytes(8)) . '.' . $extension;
    $targetPath = rtrim($targetDir, '/') . '/' . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'filename' => $filename];
    }

    return ['success' => false, 'message' => 'Failed to move uploaded file'];
}

/**
 * Format bytes to human-readable format
 * @param int $bytes File size in bytes
 * @param int $precision Decimal places
 * @return string Formatted size
 */
function formatBytes(int $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
}

// ==============================================
// Database Helpers
// ==============================================

/**
 * Get user by ID
 * @param int $id User ID
 * @return array|null User data or null if not found
 */
function getUserById(int $id): ?array {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        error_log("Database error in getUserById: " . $e->getMessage());
        return null;
    }
}

/**
 * Get website content by section name
 * @param string $section Content section identifier
 * @return string Content or empty string if not found
 */
function getWebsiteContent(string $section): string {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT content FROM website_content WHERE section_name = ?");
        $stmt->execute([$section]);
        return $stmt->fetchColumn() ?: '';
    } catch (PDOException $e) {
        error_log("Database error in getWebsiteContent: " . $e->getMessage());
        return '';
    }
}

// ==============================================
// Weather API Integration
// ==============================================

/**
 * Get weather data from API or cache
 * @param float $lat Latitude
 * @param float $lon Longitude
 * @param bool $forceRefresh Bypass cache
 * @return array Weather data or fallback data
 */
function getWeatherData(float $lat, float $lon, bool $forceRefresh = false): array {
    $cacheFile = __DIR__ . '/../cache/weather_' . round($lat, 4) . '_' . round($lon, 4) . '.json';
    
    // Check cache first
    if (!$forceRefresh && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < WEATHER_CACHE_TIME) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached) return $cached;
    }

    // Validate API key
    if (empty(WEATHER_API_KEY) || str_contains(WEATHER_API_KEY, 'your_api_key')) {
        error_log("Weather API key not properly configured");
        return getDefaultWeather();
    }

    // Make API request with cURL
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lon}&appid=" . WEATHER_API_KEY . "&units=metric",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FAILONERROR => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Handle API errors
    if ($httpCode !== 200 || $error) {
        error_log("Weather API failed - HTTP {$httpCode}: {$error}");
        return getDefaultWeather();
    }

    // Parse response
    $data = json_decode($response, true);
    if (empty($data['weather'])) {
        error_log("Invalid weather data received");
        return getDefaultWeather();
    }

    // Format weather data
    $weather = [
        'temp' => round($data['main']['temp']),
        'feels_like' => round($data['main']['feels_like']),
        'humidity' => $data['main']['humidity'],
        'wind_speed' => round($data['wind']['speed'] * 3.6), // Convert m/s to km/h
        'description' => ucfirst($data['weather'][0]['description']),
        'icon' => $data['weather'][0]['icon'],
        'city' => $data['name'] ?? 'Unknown location',
        'timestamp' => time()
    ];

    // Cache the result
    if (!is_dir(dirname($cacheFile))) {
        mkdir(dirname($cacheFile), 0755, true);
    }
    file_put_contents($cacheFile, json_encode($weather));
    
    return $weather;
}

/**
 * Provides default weather data when API fails
 * @return array Default weather data
 */
function getDefaultWeather(): array {
    return [
        'temp' => 24,
        'feels_like' => 26,
        'humidity' => 65,
        'wind_speed' => 12,
        'description' => 'Partly cloudy',
        'icon' => '03d',
        'city' => 'Demo City',
        'timestamp' => time()
    ];
}

/**
 * Get weather icon URL
 * @param string $iconCode Icon code from API
 * @param string $size '@2x' for larger version
 * @return string Complete icon URL
 */
function getWeatherIcon(string $iconCode, string $size = ''): string {
    return "https://openweathermap.org/img/wn/{$iconCode}{$size}.png";
}

// ==============================================
// Security Helpers
// ==============================================

/**
 * Generate a random token
 * @param int $length Token length in bytes
 * @return string Hexadecimal token
 */
function generateToken(int $length = 16): string {
    return bin2hex(random_bytes($length));
}

/**
 * Send verification email to new users
 */
function sendVerificationEmail(string $email, string $name, int $userId): bool {
    // Generate verification token
    $token = bin2hex(random_bytes(32));
    $expires = time() + 3600; // 1 hour expiration
    
    // Store token in database
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO verification_tokens 
            (user_id, token, expires_at) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $token, date('Y-m-d H:i:s', $expires)]);
    } catch (PDOException $e) {
        error_log("Failed to store verification token: " . $e->getMessage());
        return false;
    }

    // Create verification link
    $verificationUrl = BASE_URL . "/auth/verify-email.php?token=" . urlencode($token);
    
    // Email content
    $subject = "Verify Your Farmer Platform Account";
    $message = "
        <html>
        <head>
            <title>Email Verification</title>
        </head>
        <body>
            <h2>Hello $name,</h2>
            <p>Thank you for registering with Farmer Platform!</p>
            <p>Please click the link below to verify your email address:</p>
            <p><a href='$verificationUrl'>Verify My Email</a></p>
            <p>This link will expire in 1 hour.</p>
            <p>If you didn't request this, please ignore this email.</p>
        </body>
        </html>
    ";
    
    // Email headers
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Farmer Platform <no-reply@farmerplatform.com>\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // Send email
    return mail($email, $subject, $message, $headers);
}

function handle_file_upload(string $field, string $type, array $allowed_types, int $max_size_mb): string {
    if (!isset($_FILES[$field])) {
        throw new Exception("No file uploaded");
    }
    
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("File upload error");
    }
    
    // Validate file type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed_types)) {
        throw new Exception("Invalid file type. Allowed: " . implode(', ', $allowed_types));
    }
    
    // Validate file size
    $max_size = $max_size_mb * 1024 * 1024;
    if ($file['size'] > $max_size) {
        throw new Exception("File too large. Max size: $max_size_mb MB");
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = __DIR__ . "/../uploads/$type/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('prod_', true) . '.' . $ext;
    $destination = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception("Failed to save uploaded file");
    }
    
    return $filename;
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function truncateDescription($text, $length = 100) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}



function isNewProduct($dateAdded) {
    $now = new DateTime();
    $addedDate = new DateTime($dateAdded);
    $interval = $now->diff($addedDate);
    
    return $interval->days <= 7; // New if added within last 7 days
}

function buildPaginationUrl($page, $search, $category) {
    $params = ['page' => $page];
    if (!empty($search)) $params['search'] = $search;
    if (!empty($category)) $params['category'] = $category;
    
    return 'products.php?' . http_build_query($params);
}





// CSRF protection functions


function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}


    // Fallback to placeholder if image doesn't exist
?>

