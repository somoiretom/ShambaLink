<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/functions.php';

header('Content-Type: application/json');

try {
    // Validate API key if needed
    if (!isset($_SERVER['HTTP_REFERER']) || strpos($_SERVER['HTTP_REFERER'], BASE_URL) === false) {
        throw new Exception("Unauthorized access");
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $county = sanitize($input['county'] ?? '');
    $subCounty = sanitize($input['subCounty'] ?? '');

    if (empty($county)) {
        throw new Exception("County is required");
    }

    // Get coordinates for the location (Kenya-specific)
    $coordinates = getKenyaCoordinates($county, $subCounty);
    
    // Fetch from weather API
    $weatherData = fetchWeatherData($coordinates['lat'], $coordinates['lng']);
    
    // Return the data
    echo json_encode([
        'success' => true,
        'data' => [
            'location' => [
                'county' => $county,
                'subCounty' => $subCounty,
                'coordinates' => $coordinates
            ],
            'currentWeather' => $weatherData['current'],
            'forecast' => $weatherData['forecast']
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function getKenyaCoordinates($county, $subCounty = '') {
    // In production, use a geocoding service with good Kenya coverage
    $kenyaLocations = [
        'Nairobi' => ['lat' => -1.286389, 'lng' => 36.817223],
        'Mombasa' => ['lat' => -4.0435, 'lng' => 39.6682],
        'Kisumu' => ['lat' => -0.1022, 'lng' => 34.7617],
        'Nakuru' => ['lat' => -0.3031, 'lng' => 36.0800],
        'Eldoret' => ['lat' => 0.5143, 'lng' => 35.2698],
        // Add all 47 counties
    ];
    
    // Add sub-county adjustments if available
    $subCountyAdjustments = [
        'Kikuyu' => ['lat' => -1.2456, 'lng' => 36.6629],
        'Rongai' => ['lat' => -1.3964, 'lng' => 36.8639],
        // Add more sub-counties
    ];
    
    if (!isset($kenyaLocations[$county])) {
        throw new Exception("Location data not available for this county");
    }
    
    $coords = $kenyaLocations[$county];
    
    // Adjust for sub-county if available
    if (!empty($subCounty) && isset($subCountyAdjustments[$subCounty])) {
        $coords['lat'] += $subCountyAdjustments[$subCounty]['lat'] * 0.1;
        $coords['lng'] += $subCountyAdjustments[$subCounty]['lng'] * 0.1;
    }
    
    return $coords;
}

function fetchWeatherData($lat, $lng) {
    $apiKey = WEATHER_API_KEY; // Defined in config.php
    
    // Current weather
    $currentUrl = "https://api.weatherapi.com/v1/current.json?key=$apiKey&q=$lat,$lng";
    $currentResponse = file_get_contents($currentUrl);
    $currentData = json_decode($currentResponse, true);
    
    // Forecast (14 days)
    $forecastUrl = "https://api.weatherapi.com/v1/forecast.json?key=$apiKey&q=$lat,$lng&days=14";
    $forecastResponse = file_get_contents($forecastUrl);
    $forecastData = json_decode($forecastResponse, true);
    
    return [
        'current' => [
            'temp_c' => $currentData['current']['temp_c'],
            'humidity' => $currentData['current']['humidity'],
            'precip_mm' => $currentData['current']['precip_mm'],
            'wind_kph' => $currentData['current']['wind_kph'],
            'condition' => $currentData['current']['condition']['text'],
            'icon' => $currentData['current']['condition']['icon']
        ],
        'forecast' => array_map(function($day) {
            return [
                'date' => $day['date'],
                'maxtemp_c' => $day['day']['maxtemp_c'],
                'mintemp_c' => $day['day']['mintemp_c'],
                'avgtemp_c' => $day['day']['avgtemp_c'],
                'totalprecip_mm' => $day['day']['totalprecip_mm'],
                'condition' => $day['day']['condition']['text'],
                'icon' => $day['day']['condition']['icon']
            ];
        }, $forecastData['forecast']['forecastday'])
    ];
}
?>