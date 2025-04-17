<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/kenya-locations.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($data['region'], $data['county'], $data['subcounty'], $data['ward'], $data['soilType'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

// Simulate AI processing (in a real system, this would call your AI model)
function getAIRecommendations($data) {
    // These would normally come from your AI model
    $recommendations = [
        'crops' => [],
        'livestock' => [],
        'practices' => [],
        'weather_advisory' => ''
    ];

    // Example logic - replace with actual AI calls
    if ($data['region'] === 'Coastal') {
        $recommendations['crops'] = ['Cassava', 'Coconut', 'Cashew Nuts'];
        $recommendations['livestock'] = ['Dairy Goats', 'Poultry'];
        $recommendations['practices'] = ['Drip irrigation recommended', 'Mulching for moisture retention'];
    } else {
        $recommendations['crops'] = ['Maize', 'Beans', 'Coffee'];
        $recommendations['livestock'] = ['Dairy Cattle', 'Sheep'];
        $recommendations['practices'] = ['Terracing for hilly areas', 'Crop rotation'];
    }

    // Add weather advisory based on season
    $recommendations['weather_advisory'] = date('m') > 3 && date('m') < 6 ? 
        'Expect long rains - plant early maturing varieties' : 
        'Dry season expected - consider irrigation';

    return $recommendations;
}

// Get recommendations from "AI"
$response = [
    'success' => true,
    'data' => getAIRecommendations($data),
    'timestamp' => time()
];

echo json_encode($response);