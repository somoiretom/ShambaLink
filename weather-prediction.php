<?php
// 1. Include necessary configuration files
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/farmer-platform/includes/kenya-locations.php';

// 2. Determine current season in Kenya
$currentMonth = date('n');
$season = ($currentMonth >= 3 && $currentMonth <= 5) ? 'Long Rains' : 
          (($currentMonth >= 10 && $currentMonth <= 12) ? 'Short Rains' : 'Dry Season');

// 3. Set page title and include header
$page_title = "AI Farming Advisor";
include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/weather-predict.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="weather-app-container">
        <header class="weather-header">
            <h1><i class="fas fa-seedling"></i> AI Farming Advisor</h1>
            <p>Get personalized farming recommendations based on your location</p>
        </header>

        <main class="container">
            <div class="grid-container">
                <!-- Location Selection Card -->
                <section class="card location-card">
                    <h2><i class="fas fa-map-marker-alt"></i> Farm Location</h2>
                    <form id="weatherForm">
                        <div class="form-group">
                            <label for="region">Region:</label>
                            <select id="region" class="form-control" required>
                                <option value="" selected disabled>Select Region</option>
                                <?php foreach ($kenyaRegions as $region => $counties): ?>
                                    <option value="<?= $region ?>"><?= $region ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="county">County:</label>
                            <select id="county" class="form-control" disabled required>
                                <option value="" selected disabled>Select County</option>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="subcounty">Sub-County:</label>
                                <select id="subcounty" class="form-control" disabled>
                                    <option value="" selected disabled>Select Sub-County</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="ward">Ward:</label>
                                <select id="ward" class="form-control" disabled>
                                    <option value="" selected disabled>Select Ward</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="farmSize">Farm Size (acres):</label>
                                <input type="number" id="farmSize" class="form-control" min="0.1" step="0.1" value="1" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="soilType">Soil Type:</label>
                                <select id="soilType" class="form-control" required>
                                    <option value="" disabled selected>Select Soil Type</option>
                                    <?php foreach ($kenyaSoilTypes as $type => $desc): ?>
                                        <option value="<?= $type ?>"><?= str_replace('_', ' ', $type) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-robot"></i> Get Recommendations
                        </button>
                    </form>
                    
                    <!-- Recommendations Container -->
                    <div id="recommendationsContainer" class="recommendations-container" style="display: none;">
                        <h3><i class="fas fa-lightbulb"></i> Farming Recommendations</h3>
                        <div id="weatherAnalysis"></div>
                        <div class="recommendation-cards">
                            <div class="recommendation-column">
                                <h4><i class="fas fa-leaf"></i> Recommended Crops</h4>
                                <div id="cropRecommendations"></div>
                            </div>
                            <div class="recommendation-column">
                                <h4><i class="fas fa-paw"></i> Recommended Livestock</h4>
                                <div id="livestockRecommendations"></div>
                            </div>
                        </div>
                        <div id="farmingTips"></div>
                    </div>
                </section>

                <!-- Weather Display Card -->
                <section class="card weather-card">
                    <h2><i class="fas fa-cloud-sun"></i> Current Weather</h2>
                    <div class="weather-widget">
                        <div class="current-weather">
                            <div class="weather-main">
                                <div class="weather-icon">
                                    <img id="weatherIcon" src="https://openweathermap.org/img/wn/01d@2x.png" alt="Weather icon">
                                </div>
                                <div class="weather-temp">
                                    <span id="tempValue" class="temp-value">N/A</span>
                                    <span class="temp-unit">°C</span>
                                </div>
                            </div>
                            <div class="weather-details">
                                <p id="weatherDescription" class="weather-description">Weather data loading...</p>
                                <div class="weather-stats">
                                    <div class="weather-stat">
                                        <i class="fas fa-tint"></i>
                                        <span id="humidityValue">N/A</span>%
                                    </div>
                                    <div class="weather-stat">
                                        <i class="fas fa-wind"></i>
                                        <span id="windSpeed">N/A</span> km/h
                                    </div>
                                    <div class="weather-stat">
                                        <i class="fas fa-compress-alt"></i>
                                        <span id="pressureValue">N/A</span> hPa
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="weather-forecast">
                            <div class="forecast-header">5-Day Forecast</div>
                            <div id="forecastItems" class="forecast-items">
                                <!-- Forecast items will be populated by JavaScript -->
                            </div>
                        </div>
                        
                        <div class="weather-footer">
                            <small>Last updated: <span id="lastUpdated"><?php echo date('M j, g:i A'); ?></span></small>
                            <button id="refreshWeather" class="btn btn-sm btn-outline">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script>
    // 1. Kenya location data from PHP
    const KenyaData = <?= json_encode([
        'counties' => $kenyaCounties,
        'regions' => $kenyaRegions,
        'soilTypes' => $kenyaSoilTypes,
        'baseUrl' => BASE_URL,
        'currentSeason' => $season
    ]) ?>;

    // 2. Comprehensive recommendation database
    const RecommendationDB = {
        crops: {
            'Coastal': [
                { 
                    name: 'Cassava', 
                    variety: 'KME 1', 
                    season: 'All Year', 
                    water: 'Low', 
                    image: 'cassava.jpg', 
                    desc: 'Drought resistant root crop suitable for coastal regions with sandy soil.',
                    idealConditions: 'Warm temperatures (25-30°C), well-drained sandy soil, pH 5.5-6.5',
                    plantingTips: 'Plant cuttings 1m apart in rows. Apply NPK fertilizer at planting. Harvest in 8-12 months.',
                    yield: '15-30 tons/acre',
                    market: 'Good local demand for fresh consumption and processing'
                },
                { 
                    name: 'Coconut', 
                    variety: 'East African Tall', 
                    season: 'All Year', 
                    water: 'Moderate', 
                    image: 'coconut.jpg', 
                    desc: 'Perennial crop ideal for coastal regions with high humidity.',
                    idealConditions: 'Tropical climate, sandy loam soil, high humidity, 1500-2500mm rainfall',
                    plantingTips: 'Plant seedlings 8-10m apart. Apply organic manure annually. First harvest in 5-7 years.',
                    yield: '80-100 nuts/palm/year',
                    market: 'High demand for coconut water, oil, and copra'
                }
            ],
            'Central': [
                { 
                    name: 'Maize', 
                    variety: 'DH04', 
                    season: KenyaData.currentSeason, 
                    water: 'Moderate', 
                    image: 'maize.jpg', 
                    desc: 'High yield hybrid maize suitable for central highlands.',
                    idealConditions: 'Moderate rainfall (500-1200mm), well-drained fertile soil, altitude 1000-2000m',
                    plantingTips: 'Plant 2-3 seeds per hole at 75cm between rows. Apply DAP at planting and CAN at 3 weeks.',
                    yield: '20-40 bags/acre',
                    market: 'Staple food with constant demand'
                }
            ]
        },
        animals: {
            'Coastal': [
                { 
                    name: 'Dairy Goats', 
                    breed: 'Galla', 
                    image: 'goat.jpg', 
                    desc: 'Heat tolerant breed good for small farms in coastal regions.',
                    management: 'Require shade, clean water, and browse plants. Deworm quarterly.',
                    housing: 'Raised floor housing (1.5m²/goat) with good ventilation',
                    feeding: 'Browse, crop residues, supplemented with dairy meal (200g/day)',
                    production: 'Milk: 1-2L/day. Kidding: 2-3 times every 2 years'
                }
            ],
            'Central': [
                { 
                    name: 'Dairy Cattle', 
                    breed: 'Friesian', 
                    image: 'cow.jpg',
                    desc: 'High milk production in highland areas with good pasture.',
                    management: 'Require 4-5 acres per cow, regular veterinary care, AI services',
                    housing: 'Zero-grazing units (12m²/cow) with proper drainage',
                    feeding: 'Napier grass (70kg/day), dairy meal (4kg/day), mineral supplements',
                    production: '15-20L milk/day with good management'
                }
            ]
        },
        weatherTips: {
            'rain': [
                'Plant quick-maturing varieties to utilize the rains',
                'Apply fertilizer after planting to maximize uptake',
                'Control weeds that compete with crops for nutrients'
            ],
            'clear': [
                'Practice conservation agriculture to retain soil moisture',
                'Consider drip irrigation for water efficiency',
                'Mulch crops to reduce evaporation'
            ],
            'clouds': [
                'Ideal conditions for planting and crop establishment',
                'Monitor for fungal diseases that thrive in humid conditions',
                'Good time for transplanting seedlings'
            ]
        },
        soilRecommendations: {
            'clay': [
                'Add organic matter to improve drainage',
                'Consider raised beds for better root development',
                'Suitable crops: Rice, cabbage, spinach'
            ],
            'sandy': [
                'Add compost to improve water retention',
                'Use mulch to reduce evaporation',
                'Suitable crops: Cassava, sweet potatoes, watermelons'
            ],
            'loam': [
                'Ideal soil type for most crops',
                'Maintain fertility with crop rotation',
                'Suitable for all crops including vegetables and fruits'
            ]
        }
    };

    // 3. Weather data cache
    let weatherDataCache = null;
    const CACHE_DURATION = 30 * 60 * 1000; // 30 minutes cache

    // 4. Fetch weather data with caching
    async function fetchWeatherData(lat, lon) {
        try {
            // Return cached data if valid
            if (weatherDataCache && (Date.now() - weatherDataCache.timestamp) < CACHE_DURATION) {
                return weatherDataCache.data;
            }
            
            // Simulate API call with consistent mock data
            const mockWeatherData = {
                city: 'Nairobi',
                temp: 22,
                description: 'Partly cloudy',
                icon: '03d',
                humidity: 65,
                wind_speed: 12.5,
                pressure: 1015,
                timestamp: Date.now(),
                forecast: [
                    { date: new Date(Date.now() + 86400000), temp_max: 24, temp_min: 14, icon: '03d', description: 'Partly cloudy' },
                    { date: new Date(Date.now() + 172800000), temp_max: 25, temp_min: 15, icon: '01d', description: 'Sunny' },
                    { date: new Date(Date.now() + 259200000), temp_max: 23, temp_min: 16, icon: '10d', description: 'Light rain' },
                    { date: new Date(Date.now() + 345600000), temp_max: 22, temp_min: 15, icon: '10d', description: 'Rain' },
                    { date: new Date(Date.now() + 432000000), temp_max: 21, temp_min: 14, icon: '03d', description: 'Cloudy' }
                ]
            };
            
            // Cache the data
            weatherDataCache = {
                data: mockWeatherData,
                timestamp: Date.now()
            };
            
            return mockWeatherData;
        } catch (error) {
            console.error('Error fetching weather data:', error);
            return null;
        }
    }

    // 5. Update weather display
    async function updateWeatherDisplay() {
        const lat = -1.286389; // Nairobi coordinates
        const lon = 36.817223;
        
        const weatherData = await fetchWeatherData(lat, lon);
        
        if (weatherData) {
            // Update current weather
            document.getElementById('tempValue').textContent = weatherData.temp;
            document.getElementById('weatherDescription').textContent = weatherData.description;
            document.getElementById('humidityValue').textContent = weatherData.humidity;
            document.getElementById('windSpeed').textContent = weatherData.wind_speed;
            document.getElementById('pressureValue').textContent = weatherData.pressure;
            document.getElementById('weatherIcon').src = `https://openweathermap.org/img/wn/${weatherData.icon}@2x.png`;
            document.getElementById('weatherIcon').alt = weatherData.description;
            
            // Update forecast
            const forecastContainer = document.getElementById('forecastItems');
            forecastContainer.innerHTML = '';
            
            weatherData.forecast.forEach(day => {
                const forecastItem = document.createElement('div');
                forecastItem.className = 'forecast-item';
                forecastItem.innerHTML = `
                    <div class="forecast-day">${day.date.toLocaleDateString('en-US', { weekday: 'short' })}</div>
                    <div class="forecast-icon">
                        <img src="https://openweathermap.org/img/wn/${day.icon}.png" alt="${day.description}">
                    </div>
                    <div class="forecast-temp">
                        <span class="temp-max">${day.temp_max}°</span>
                        <span class="temp-min">${day.temp_min}°</span>
                    </div>
                `;
                forecastContainer.appendChild(forecastItem);
            });
            
            // Update last updated time
            document.getElementById('lastUpdated').textContent = new Date().toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit'
            });
        }
    }

    // 6. Generate recommendations
    function generateRecommendations(region, county, soilType, weatherCondition) {
        const recommendationsContainer = document.getElementById('recommendationsContainer');
        const cropRecommendations = document.getElementById('cropRecommendations');
        const livestockRecommendations = document.getElementById('livestockRecommendations');
        const weatherAnalysis = document.getElementById('weatherAnalysis');
        const farmingTips = document.getElementById('farmingTips');
        
        // Clear previous recommendations
        cropRecommendations.innerHTML = '';
        livestockRecommendations.innerHTML = '';
        
        // Get recommendations
        const regionCrops = RecommendationDB.crops[region] || [];
        const regionAnimals = RecommendationDB.animals[region] || [];
        const soilTips = RecommendationDB.soilRecommendations[soilType.toLowerCase()] || [];
        
        // Determine weather type
        let weatherConditionType = 'clouds';
        if (weatherCondition.toLowerCase().includes('rain')) {
            weatherConditionType = 'rain';
        } else if (weatherCondition.toLowerCase().includes('sunny') || 
                  weatherCondition.toLowerCase().includes('clear')) {
            weatherConditionType = 'clear';
        }
        
        // Weather analysis
        weatherAnalysis.innerHTML = `
            <div class="weather-analysis-card">
                <h4><i class="fas fa-cloud-sun-rain"></i> Weather & Season Analysis for ${county}</h4>
                <p><strong>Current Conditions:</strong> ${weatherCondition}</p>
                <p><strong>Season:</strong> ${KenyaData.currentSeason}</p>
                <p><strong>Soil Type:</strong> ${soilType.replace('_', ' ')}</p>
                <p>${getWeatherAnalysisText(weatherConditionType)}</p>
            </div>
        `;
        
        // Display crop recommendations
        if (regionCrops.length > 0) {
            regionCrops.forEach(crop => {
                const cropCard = document.createElement('div');
                cropCard.className = 'recommendation-card';
                cropCard.innerHTML = `
                    <div class="recommendation-header">
                        <h5>${crop.name} (${crop.variety})</h5>
                        <span class="season-badge">${crop.season}</span>
                    </div>
                    <div class="recommendation-body">
                        <p>${crop.desc}</p>
                        <div class="recommendation-details">
                            <p><i class="fas fa-temperature-low"></i> <strong>Conditions:</strong> ${crop.idealConditions}</p>
                            <p><i class="fas fa-seedling"></i> <strong>Planting:</strong> ${crop.plantingTips}</p>
                            <p><i class="fas fa-weight-hanging"></i> <strong>Yield:</strong> ${crop.yield}</p>
                        </div>
                    </div>
                `;
                cropRecommendations.appendChild(cropCard);
            });
        }
        
        // Display livestock recommendations
        if (regionAnimals.length > 0) {
            regionAnimals.forEach(animal => {
                const animalCard = document.createElement('div');
                animalCard.className = 'recommendation-card';
                animalCard.innerHTML = `
                    <div class="recommendation-header">
                        <h5>${animal.name} (${animal.breed})</h5>
                    </div>
                    <div class="recommendation-body">
                        <p>${animal.desc}</p>
                        <div class="recommendation-details">
                            <p><i class="fas fa-home"></i> <strong>Housing:</strong> ${animal.housing}</p>
                            <p><i class="fas fa-utensils"></i> <strong>Feeding:</strong> ${animal.feeding}</p>
                        </div>
                    </div>
                `;
                livestockRecommendations.appendChild(animalCard);
            });
        }
        
        // Display farming tips
        const weatherTips = RecommendationDB.weatherTips[weatherConditionType] || [];
        farmingTips.innerHTML = `
            <div class="tips-container">
                <div class="tips-card">
                    <h4><i class="fas fa-cloud-sun"></i> Weather Tips</h4>
                    <ul>
                        ${weatherTips.map(tip => `<li><i class="fas fa-check-circle"></i> ${tip}</li>`).join('')}
                    </ul>
                </div>
                
                <div class="tips-card">
                    <h4><i class="fas fa-dirt"></i> Soil Tips</h4>
                    <ul>
                        ${soilTips.map(tip => `<li><i class="fas fa-check-circle"></i> ${tip}</li>`).join('')}
                    </ul>
                </div>
            </div>
        `;
        
        // Show recommendations
        recommendationsContainer.style.display = 'block';
    }

    // 7. Helper functions
    function getWeatherAnalysisText(weatherType) {
        const texts = {
            'rain': 'Good planting conditions. Ensure proper drainage.',
            'clear': 'Dry conditions - consider water conservation methods.',
            'clouds': 'Ideal growing conditions for most crops.'
        };
        return texts[weatherType] || 'Normal farming conditions.';
    }

    // 8. DOM event handlers
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize weather
        updateWeatherDisplay();
        
        // Region change handler
        document.getElementById('region').addEventListener('change', function() {
            const region = this.value;
            const countySelect = document.getElementById('county');
            
            countySelect.innerHTML = '<option value="" selected disabled>Select County</option>';
            countySelect.disabled = !region;
            
            if (region) {
                const counties = KenyaData.regions[region] || [];
                counties.forEach(county => {
                    const option = document.createElement('option');
                    option.value = county;
                    option.textContent = county;
                    countySelect.appendChild(option);
                });
            }
        });
        
        // Form submission handler
        document.getElementById('weatherForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const region = document.getElementById('region').value;
            const county = document.getElementById('county').value;
            const soilType = document.getElementById('soilType').value;
            const weatherCondition = document.getElementById('weatherDescription').textContent;
            
            if (!region || !county || !soilType) {
                alert('Please fill in all required fields');
                return;
            }
            
            // Show loading
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing...';
            submitBtn.disabled = true;
            
            // Generate recommendations
            generateRecommendations(region, county, soilType, weatherCondition);
            
            // Restore button
            submitBtn.innerHTML = '<i class="fas fa-robot"></i> Get Recommendations';
            submitBtn.disabled = false;
        });
        
        // Refresh weather handler
        document.getElementById('refreshWeather').addEventListener('click', async function(e) {
            e.preventDefault();
            
            const refreshBtn = this;
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
            refreshBtn.disabled = true;
            
            await updateWeatherDisplay();
            
            refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
            refreshBtn.disabled = false;
        });
    });
    </script>
    
    <script src="<?= BASE_URL ?>/assets/js/crop-recommendation.js"></script>
</body>
</html>

<?php include 'includes/footer.php'; ?>