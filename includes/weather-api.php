<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function getWeatherData(float $lat, float $lon, bool $forceRefresh = false): array {
    $cacheFile = __DIR__ . "/../cache/weather_{$lat}_{$lon}.json";
    
    // Return cached data if not expired
    if (!$forceRefresh && file_exists($cacheFile) && 
        (time() - filemtime($cacheFile)) < WEATHER_CACHE_TIME) {
        return json_decode(file_get_contents($cacheFile), true);
    }

    // Fetch fresh data from API
    $url = "https://api.openweathermap.org/data/2.5/forecast?lat={$lat}&lon={$lon}&appid=" . WEATHER_API_KEY . "&units=metric";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
        error_log("Weather API request failed for coordinates: {$lat}, {$lon}");
        return [];
    }

    $data = json_decode($response, true);
    
    if (empty($data['list'])) {
        error_log("Invalid weather data received: " . $response);
        return [];
    }

    // Process and cache the data
    $processedData = [
        'city' => $data['city']['name'] ?? 'Unknown',
        'current' => processWeatherItem($data['list'][0]),
        'forecast' => []
    ];

    // Get forecast for next 5 days (every 24 hours)
    for ($i = 0; $i < 40; $i += 8) {
        if (isset($data['list'][$i])) {
            $processedData['forecast'][] = processWeatherItem($data['list'][$i]);
        }
    }

    // Save to cache
    if (!is_dir(dirname($cacheFile))) {
        mkdir(dirname($cacheFile), 0755, true);
    }
    file_put_contents($cacheFile, json_encode($processedData));

    return $processedData;
}

function processWeatherItem(array $item): array {
    return [
        'timestamp' => $item['dt'],
        'date' => date('D, M j', $item['dt']),
        'time' => date('g:i A', $item['dt']),
        'temp' => round($item['main']['temp']),
        'feels_like' => round($item['main']['feels_like']),
        'humidity' => $item['main']['humidity'],
        'wind_speed' => round($item['wind']['speed'] * 3.6), // Convert m/s to km/h
        'wind_deg' => $item['wind']['deg'],
        'weather' => $item['weather'][0]['main'],
        'description' => ucfirst($item['weather'][0]['description']),
        'icon' => $item['weather'][0]['icon'],
        'rain' => $item['rain']['3h'] ?? 0,
        'snow' => $item['snow']['3h'] ?? 0
    ];
}

function getFarmerLocationWeather(int $farmerId): array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT latitude, longitude FROM farmers WHERE user_id = ?");
        $stmt->execute([$farmerId]);
        $location = $stmt->fetch();
        
        if ($location && $location['latitude'] && $location['longitude']) {
            return getWeatherData((float)$location['latitude'], (float)$location['longitude']);
        }
    } catch (PDOException $e) {
        error_log("Failed to get farmer location: " . $e->getMessage());
    }
    
    return [];
}
?>