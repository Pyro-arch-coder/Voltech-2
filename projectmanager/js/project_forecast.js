// Project Forecast Calculation
async function calculateForecast(size) {
    // Get DOM elements safely
    const forecastValueEl = document.getElementById('forecastedValue');
    const forecastAmountEl = document.getElementById('forecastedAmount');
    
    if (!forecastValueEl || !forecastAmountEl) {
        console.error('Required DOM elements not found');
        return;
    }
    
    // Reset display
    forecastValueEl.style.display = 'none';
    
    // Validate input
    const parsedSize = parseFloat(size);
    if (isNaN(parsedSize) || parsedSize <= 0) {
        return;
    }
    
    try {
        // Show loading state
        forecastAmountEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        forecastValueEl.style.display = 'flex';
        
        // Get the selected category safely
        const categoryEl = document.getElementById('category');
        const category = categoryEl ? categoryEl.value : '';
        
        // Make API call to get forecast data
        const response = await fetch(`get_forecast.php?size=${parsedSize}&category=${encodeURIComponent(category)}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (!data || !data.success) {
            throw new Error(data?.message || 'Failed to get forecast data');
        }
        
        // Safely get values with defaults
        const sampleSize = Number(data.sample_size) || 0;
        const avgCost = Number(data.average_cost) || 0;
        const avgSize = Number(data.average_size) || 0;
        
        // Calculate forecast using the formula: (average_cost * new_size) / average_old_size
        let forecastedValue = 0;
        
        if (avgSize > 0) {
            forecastedValue = (avgCost * parsedSize) / avgSize;
        } else if (sampleSize > 0) {
            // Fallback: If no average size, use just the average cost
            forecastedValue = avgCost;
        }
        
        // Format the values with proper checks
        const formatNumber = (num) => {
            const n = Number(num);
            return isNaN(n) ? '0.00' : n.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        };
        
        forecastAmountEl.innerHTML = '₱' + formatNumber(forecastedValue);
        
        // Add tooltip with details
        forecastAmountEl.title = `Based on ${sampleSize} projects\n` +
                              `Avg Cost: ₱${formatNumber(avgCost)}\n` +
                              `Avg Size: ${formatNumber(avgSize)} m²`;
        
    } catch (error) {
        console.error('Error in calculateForecast:', error);
        forecastValueEl.style.display = 'none';
        
        // Show error to user if possible
        if (forecastAmountEl) {
            forecastAmountEl.innerHTML = 'Error calculating forecast';
            forecastAmountEl.title = error.message || 'An error occurred';
        }
    }
}

// Initialize event listeners
document.addEventListener('DOMContentLoaded', function() {
    const sizeInput = document.getElementById('size');
    const categorySelect = document.getElementById('category');
    
    // Calculate forecast when Enter is pressed or input loses focus
    sizeInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const size = parseFloat(this.value);
            if (size > 0) {
                calculateForecast(size);
            }
        }
    });
    
    // Also calculate when input loses focus (tab out or click away)
    sizeInput?.addEventListener('blur', function() {
        const size = parseFloat(this.value);
        if (size > 0) {
            calculateForecast(size);
        } else {
            // Hide forecast if size is not valid
            document.getElementById('forecastedValue').style.display = 'none';
        }
    });
    
    // Recalculate forecast when category changes (if we have a valid size)
    categorySelect?.addEventListener('change', function() {
        const size = parseFloat(sizeInput.value);
        if (size > 0) {
            calculateForecast(size);
        }
    });
});
