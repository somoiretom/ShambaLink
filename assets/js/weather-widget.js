document.addEventListener('DOMContentLoaded', function() {
    // Refresh weather data every hour
    const weatherWidget = document.querySelector('.weather-widget');
    if (weatherWidget) {
        setInterval(() => {
            fetch(`${BASE_URL}/admin/weather.php?refresh=1`)
                .then(response => response.json())
                .then(data => {
                    updateWeatherWidget(data);
                })
                .catch(error => {
                    console.error('Error refreshing weather:', error);
                });
        }, 3600000); // 1 hour
    }

    function updateWeatherWidget(data) {
        // Implementation to update the DOM with new weather data
        if (data && data.current) {
            const currentWeather = document.querySelector('.current-weather');
            if (currentWeather) {
                // Update current weather
                document.querySelector('.temp-value').textContent = data.current.temp;
                document.querySelector('.weather-description').textContent = data.current.description;
                document.querySelector('.weather-icon img').src = `https://openweathermap.org/img/wn/${data.current.icon}@2x.png`;
                document.querySelector('.weather-icon img').alt = data.current.description;
                
                // Update stats
                document.querySelectorAll('.weather-stat')[0].querySelector('span').textContent = `${data.current.humidity}%`;
                document.querySelectorAll('.weather-stat')[1].querySelector('span').textContent = `${data.current.wind_speed} km/h`;
                document.querySelectorAll('.weather-stat')[2].querySelector('span').textContent = `${data.current.rain} mm`;
                
                // Update forecast
                const forecastItems = document.querySelectorAll('.forecast-item');
                data.forecast.forEach((forecast, index) => {
                    if (forecastItems[index]) {
                        forecastItems[index].querySelector('.forecast-day').textContent = forecast.date;
                        forecastItems[index].querySelector('.forecast-icon img').src = `https://openweathermap.org/img/wn/${forecast.icon}.png`;
                        forecastItems[index].querySelector('.forecast-temp span').textContent = `${forecast.temp}°`;
                    }
                });
                
                // Update timestamp
                document.querySelector('.weather-footer small').textContent = `Last updated: ${new Date().toLocaleString('en-US', { 
                    month: 'short', 
                    day: 'numeric', 
                    hour: 'numeric', 
                    minute: 'numeric',
                    hour12: true 
                })}`;
            }
        }
    }
});