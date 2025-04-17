<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/functions.php';

requireRole('admin');

// Handle location update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = sanitize($_POST['csrf_token'] ?? '');
    if (!verifyCSRFToken($csrf_token)) {
        die("Invalid CSRF token");
    }

    $lat = (float)$_POST['lat'];
    $lon = (float)$_POST['lon'];
    
    // Validate coordinates
    if ($lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180) {
        $_SESSION['weather_lat'] = $lat;
        $_SESSION['weather_lon'] = $lon;
        $_SESSION['success'] = "Weather location updated";
    } else {
        $_SESSION['error'] = "Invalid coordinates";
    }
    
    header("Location: weather.php");
    exit();
}

// Get current weather location
$lat = $_SESSION['weather_lat'] ?? DEFAULT_LAT;
$lon = $_SESSION['weather_lon'] ?? DEFAULT_LON;

// Get weather data
try {
    $weatherData = getWeatherData($lat, $lon);
    if (empty($weatherData)) {
        $weatherData = getDefaultWeather();
    }
} catch (Exception $e) {
    error_log("Weather error: " . $e->getMessage());
    $weatherData = getDefaultWeather();
}

$page_title = "Weather Settings";
include '../includes/header.php';
?>

<div class="container">
    <h1>Weather Settings</h1>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Current Location</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="form-group">
                            <label>Latitude</label>
                            <input type="number" step="0.0001" name="lat" class="form-control" 
                                   value="<?= htmlspecialchars($lat) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Longitude</label>
                            <input type="number" step="0.0001" name="lon" class="form-control" 
                                   value="<?= htmlspecialchars($lon) ?>" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update Location</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Current Weather</h3>
                </div>
                <div class="card-body text-center">
                    <?php if (!empty($weatherData)): ?>
                    <h4><?= htmlspecialchars($weatherData['city']) ?></h4>
                    <img src="https://openweathermap.org/img/wn/<?= htmlspecialchars($weatherData['icon']) ?>@2x.png" 
                         alt="<?= htmlspecialchars($weatherData['description']) ?>">
                    <h2><?= htmlspecialchars($weatherData['temp']) ?>°C</h2>
                    <p><?= htmlspecialchars($weatherData['description']) ?></p>
                    <p>Humidity: <?= htmlspecialchars($weatherData['humidity']) ?>%</p>
                    <p>Wind: <?= htmlspecialchars($weatherData['wind_speed']) ?> km/h</p>
                    <?php else: ?>
                    <p>Weather data not available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>