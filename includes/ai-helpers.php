<?php
function generateCropRecommendations($currentWeather, $forecast, $soilType, $farmSize, $county) {
    // Load crop database (in production, this would come from a database)
    $crops = require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/data/kenya_crops.php';
    
    // Analyze weather patterns
    $weatherAnalysis = analyzeWeatherPatterns($currentWeather, $forecast);
    
    // Score crops based on suitability
    $scoredCrops = [];
    foreach ($crops as $crop) {
        $score = calculateCropScore($crop, $weatherAnalysis, $soilType, $county);
        $scoredCrops[] = [
            'cropId' => $crop['id'],
            'name' => $crop['name'],
            'scientificName' => $crop['scientific_name'],
            'score' => $score,
            'suitability' => getSuitabilityLevel($score),
            'varieties' => getRecommendedVarieties($crop['id'], $county),
            'plantingAdvice' => getPlantingAdvice($crop['id'], $weatherAnalysis),
            'risks' => identifyPotentialRisks($crop['id'], $weatherAnalysis)
        ];
    }
    
    // Sort by score (descending)
    usort($scoredCrops, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    // Get top 5 crops
    $topCrops = array_slice($scoredCrops, 0, 5);
    
    // Generate farming calendar advice
    $calendarAdvice = generateFarmingCalendar($topCrops[0]['cropId'], $weatherAnalysis);
    
    return [
        'topCrops' => $topCrops,
        'farmingCalendar' => $calendarAdvice,
        'weatherAnalysis' => $weatherAnalysis,
        'farmSizeAdvice' => getFarmSizeAdvice($farmSize, $topCrops[0]['cropId'])
    ];
}

function analyzeWeatherPatterns($currentWeather, $forecast) {
    // Calculate average temperature for forecast period
    $avgTemp = array_reduce($forecast, function($carry, $day) {
        return $carry + $day['avgtemp_c'];
    }, 0) / count($forecast);
    
    // Calculate total precipitation
    $totalPrecip = array_reduce($forecast, function($carry, $day) {
        return $carry + $day['totalprecip_mm'];
    }, 0);
    
    // Determine weather trends
    $tempTrend = getTemperatureTrend($forecast);
    $precipTrend = getPrecipitationTrend($forecast);
    
    return [
        'currentTemp' => $currentWeather['temp_c'],
        'currentHumidity' => $currentWeather['humidity'],
        'currentPrecip' => $currentWeather['precip_mm'],
        'avgForecastTemp' => $avgTemp,
        'totalForecastPrecip' => $totalPrecip,
        'tempTrend' => $tempTrend,
        'precipTrend' => $precipTrend,
        'condition' => $currentWeather['condition']
    ];
}

function calculateCropScore($crop, $weatherAnalysis, $soilType, $county) {
    $score = 0;
    
    // 1. Temperature suitability (40% weight)
    $tempScore = calculateTemperatureScore($crop, $weatherAnalysis['avgForecastTemp']);
    $score += $tempScore * 0.4;
    
    // 2. Rainfall suitability (30% weight)
    $rainScore = calculateRainfallScore($crop, $weatherAnalysis['totalForecastPrecip']);
    $score += $rainScore * 0.3;
    
    // 3. Soil suitability (15% weight)
    $soilScore = calculateSoilScore($crop, $soilType);
    $score += $soilScore * 0.15;
    
    // 4. Regional suitability (10% weight)
    $regionScore = calculateRegionalScore($crop, $county);
    $score += $regionScore * 0.1;
    
    // 5. Current weather condition (5% weight)
    $conditionScore = calculateConditionScore($crop, $weatherAnalysis['condition']);
    $score += $conditionScore * 0.05;
    
    return min(100, max(0, $score));
}

// Additional helper functions would be defined here...
// (calculateTemperatureScore, calculateRainfallScore, etc.)

function generateWeatherSummary($forecast) {
    $summary = [
        'period' => count($forecast) . ' days',
        'avgTemp' => array_reduce($forecast, function($carry, $day) {
            return $carry + $day['avgtemp_c'];
        }, 0) / count($forecast),
        'totalRain' => array_reduce($forecast, function($carry, $day) {
            return $carry + $day['totalprecip_mm'];
        }, 0),
        'trends' => [
            'temperature' => getTemperatureTrend($forecast),
            'precipitation' => getPrecipitationTrend($forecast)
        ]
    ];
    
    return $summary;
}

// More helper functions...
?>