document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const regionSelect = document.getElementById('region');
    const countySelect = document.getElementById('county');
    const subCountySelect = document.getElementById('subcounty');
    const wardSelect = document.getElementById('ward');
    const weatherForm = document.getElementById('weatherForm');
    
    // Debug: Check if elements exist
    if (!regionSelect || !countySelect || !subCountySelect || !wardSelect || !weatherForm) {
        console.error('Required elements not found');
        return;
    }

    // Initialize dropdown events
    initLocationDropdowns();

    function initLocationDropdowns() {
        // Region change event
        regionSelect.addEventListener('change', function() {
            const region = this.value;
            updateCountyDropdown(region);
            resetDownstreamDropdowns(countySelect);
        });

        // County change event
        countySelect.addEventListener('change', function() {
            const county = this.value;
            updateSubCountyDropdown(county);
            resetDownstreamDropdowns(subCountySelect);
        });

        // Sub-county change event
        subCountySelect.addEventListener('change', function() {
            const county = countySelect.value;
            const subCounty = this.value;
            updateWardDropdown(county, subCounty);
        });
    }

    // Update county dropdown based on region
    function updateCountyDropdown(region) {
        countySelect.innerHTML = '<option value="" disabled selected>-- Select County --</option>';
        countySelect.disabled = true;

        if (region) {
            // Filter counties by region
            const counties = Object.keys(KenyaData.counties).filter(
                county => KenyaData.counties[county].region === region
            );

            counties.forEach(county => {
                const option = new Option(county, county);
                countySelect.add(option);
            });

            countySelect.disabled = false;
        }
    }

    // Update sub-county dropdown based on county
    function updateSubCountyDropdown(county) {
        subCountySelect.innerHTML = '<option value="" disabled selected>-- Select Sub-County --</option>';
        subCountySelect.disabled = true;

        if (county && KenyaData.counties[county]) {
            // Get sub-counties for selected county
            const subCounties = Object.keys(KenyaData.counties[county].subcounties);

            subCounties.forEach(subCounty => {
                const option = new Option(subCounty, subCounty);
                subCountySelect.add(option);
            });

            subCountySelect.disabled = false;
        }
    }

    // Update ward dropdown based on sub-county
    function updateWardDropdown(county, subCounty) {
        wardSelect.innerHTML = '<option value="" disabled selected>-- Select Ward --</option>';
        wardSelect.disabled = true;

        if (county && subCounty && KenyaData.counties[county]) {
            // Get wards for selected sub-county
            const wards = KenyaData.counties[county].subcounties[subCounty];

            if (wards && wards.length > 0) {
                wards.forEach(ward => {
                    const option = new Option(ward, ward);
                    wardSelect.add(option);
                });

                wardSelect.disabled = false;
            }
        }
    }

    // Reset downstream dropdowns
    function resetDownstreamDropdowns(triggerElement) {
        if (triggerElement === regionSelect) {
            countySelect.innerHTML = '<option value="" disabled selected>-- Select County --</option>';
            countySelect.disabled = true;
        }
        if (triggerElement === countySelect || triggerElement === regionSelect) {
            subCountySelect.innerHTML = '<option value="" disabled selected>-- Select Sub-County --</option>';
            subCountySelect.disabled = true;
        }
        if (triggerElement === subCountySelect || triggerElement === countySelect || triggerElement === regionSelect) {
            wardSelect.innerHTML = '<option value="" disabled selected>-- Select Ward --</option>';
            wardSelect.disabled = true;
        }
    }

    // Form submission handler
    weatherForm.addEventListener('submit', function(e) {
        e.preventDefault();
        handleFormSubmission();
    });

    function handleFormSubmission() {
        // Show loading state
        showLoadingState();

        // Get form data
        const formData = {
            region: regionSelect.value,
            county: countySelect.value,
            subCounty: subCountySelect.value,
            ward: wardSelect.value,
            farmSize: document.getElementById('farmSize').value,
            soilType: document.getElementById('soilType').value,
            currentCrops: document.getElementById('currentCrops').value,
            irrigationAvailable: document.getElementById('irrigationAvailable').checked
        };

        console.log('Form data:', formData);
        
        // Here you would make actual API calls
        simulateApiCalls(formData);
    }

    function showLoadingState() {
        document.getElementById('resultsContainer').style.display = 'block';
        document.getElementById('loadingIndicator').style.display = 'flex';
        document.getElementById('predictionResults').innerHTML = '';
    }

    function simulateApiCalls(formData) {
        // Simulate API delay
        setTimeout(() => {
            document.getElementById('loadingIndicator').style.display = 'none';
            displayMockResults(formData);
        }, 2000);
    }

    function displayMockResults(formData) {
        const results = `
            <div class="recommendation-card">
                <h3>Recommendations for ${formData.county} County (${formData.subCounty} Sub-County)</h3>
                <p>Based on your ${formData.farmSize} acre farm with ${formData.soilType.replace('_', ' ')} soil</p>
                <div class="crop-recommendation">
                    <div class="crop-card">
                        <h4>Maize</h4>
                        <p>Plant in the coming season</p>
                    </div>
                    <div class="crop-card">
                        <h4>Beans</h4>
                        <p>Good intercropping option</p>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('predictionResults').innerHTML = results;
    }

    // Debug: Log initial state
    console.log('Location selector initialized');
    console.log('KenyaData:', KenyaData);
});

// ... (keep all previous dropdown code)

async function handleFormSubmission() {
    // Show loading state
    showLoadingState();

    // Get form data
    const formData = {
        region: regionSelect.value,
        county: countySelect.value,
        subcounty: subCountySelect.value,
        ward: wardSelect.value,
        farmSize: document.getElementById('farmSize').value,
        soilType: document.getElementById('soilType').value,
        currentCrops: document.getElementById('currentCrops').value,
        irrigationAvailable: document.getElementById('irrigationAvailable').checked,
        weatherCondition: document.getElementById('weatherCondition').textContent
    };

    try {
        // Call AI recommendation API
        const response = await fetch(`${KenyaData.baseUrl}/api/recommendations.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        });

        if (!response.ok) throw new Error('API request failed');

        const result = await response.json();
        
        if (result.success) {
            displayAIRecommendations(formData, result.data);
        } else {
            throw new Error(result.error || 'Unknown error');
        }
    } catch (error) {
        console.error('Error:', error);
        showError(error.message);
    } finally {
        // Hide loading state
        document.getElementById('loadingIndicator').style.display = 'none';
    }
}

function displayAIRecommendations(formData, aiData) {
    const resultsContainer = document.getElementById('resultsContainer');
    resultsContainer.style.display = 'block';

    // Create HTML for recommendations
    let html = `
        <div class="recommendation-card">
            <h3>AI Recommendations for ${formData.county} County</h3>
            <p class="subtitle">${formData.subcounty} Sub-County | ${formData.ward} Ward</p>
            
            <div class="weather-advisory">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Weather Advisory:</strong> ${aiData.weather_advisory}
            </div>
            
            <div class="recommendation-section">
                <h4><i class="fas fa-leaf"></i> Recommended Crops</h4>
                <div class="recommendation-grid">
                    ${aiData.crops.map(crop => `
                        <div class="recommendation-item">
                            <div class="item-icon"><i class="fas fa-seedling"></i></div>
                            <div class="item-content">
                                <h5>${crop}</h5>
                                <p>Optimal for ${formData.soilType.replace('_', ' ')} soil</p>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
            
            <div class="recommendation-section">
                <h4><i class="fas fa-paw"></i> Recommended Livestock</h4>
                <div class="recommendation-grid">
                    ${aiData.livestock.map(animal => `
                        <div class="recommendation-item">
                            <div class="item-icon"><i class="fas fa-paw"></i></div>
                            <div class="item-content">
                                <h5>${animal}</h5>
                                <p>Well-suited to local conditions</p>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
            
            <div class="recommendation-section">
                <h4><i class="fas fa-tractor"></i> Farming Practices</h4>
                <ul class="practice-list">
                    ${aiData.practices.map(practice => `
                        <li><i class="fas fa-check-circle"></i> ${practice}</li>
                    `).join('')}
                </ul>
            </div>
        </div>
    `;

    resultsContainer.innerHTML = html;
}

function showError(message) {
    const resultsContainer = document.getElementById('resultsContainer');
    resultsContainer.innerHTML = `
        <div class="error-card">
            <i class="fas fa-exclamation-circle"></i>
            <h3>Error Getting Recommendations</h3>
            <p>${message}</p>
            <button onclick="window.location.reload()">Try Again</button>
        </div>
    `;
    resultsContainer.style.display = 'block';
}