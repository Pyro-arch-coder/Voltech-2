// Format number with commas and 2 decimal places
function formatNumber(num) {
    const n = Number(num);
    return isNaN(n) ? '0.00' : n.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Project Forecast Calculation
async function calculateForecast(size) {
    const forecastValueEl = document.getElementById('forecastedValue');
    const categorySelect = document.querySelector('select[name="category"]');
    
    // Check if required elements exist
    if (!forecastValueEl || !categorySelect) {
        console.error('Required DOM elements not found');
        return;
    }
    
    // Check if size is a valid number
    const parsedSize = parseFloat(size);
    if (isNaN(parsedSize) || parsedSize <= 0) {
        forecastValueEl.classList.add('d-none');
        return;
    }
    
    // Check if category is selected
    const selectedCategory = categorySelect ? categorySelect.value : '';
    if (!selectedCategory) {
        forecastValueEl.classList.add('d-none');
        return;
    }
    
    // Show loading state
    forecastValueEl.classList.remove('d-none');
    forecastValueEl.classList.add('forecast-loading');
    forecastValueEl.innerHTML = `
        <div class="forecast-amount">
            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
            <span>Calculating...</span>
        </div>
    `;
    
    try {
        const response = await fetch(`get_forecast.php?size=${encodeURIComponent(parsedSize)}&category=${encodeURIComponent(selectedCategory)}`);
        const data = await response.json();
        
        // Remove loading state
        forecastValueEl.classList.remove('forecast-loading');
        
        if (data && data.success) {
            const formattedAmount = formatNumber(data.forecasted_cost);
            const costPerSqm = formatNumber(data.cost_per_sqm || 0);
            const sampleSize = data.sample_size || 0;
            const categoryName = data.category || selectedCategory;
            
            // Store the forecasted cost in the hidden input field
            const forecastedCostInput = document.getElementById('forecasted_cost');
            if (forecastedCostInput) {
                forecastedCostInput.value = data.forecasted_cost;
            }
            
            forecastValueEl.innerHTML = `
                <div class="forecast-amount">₱${formattedAmount}</div>
                <div class="forecast-details">
                    <small class="text-muted">Based on ${sampleSize} ${sampleSize === 1 ? 'project' : 'projects'}</small>
                </div>
            `;
            
            // Add tooltip with additional details
            forecastValueEl.setAttribute('data-bs-toggle', 'tooltip');
            forecastValueEl.setAttribute('data-bs-placement', 'top');
            forecastValueEl.setAttribute('title', 
                `Project Size: ${formatNumber(parsedSize)} m²\n` +
                `Cost per m²: ₱${costPerSqm}\n` +
                `Based on ${sampleSize} ${sampleSize === 1 ? 'project' : 'projects'} in ${categoryName}`
            );
            
            // Initialize tooltip if Bootstrap tooltips are available
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                new bootstrap.Tooltip(forecastValueEl);
            }
        } else {
            forecastValueEl.innerHTML = `
                <div class="forecast-amount text-danger">N/A</div>
                <div class="forecast-details text-danger small">${data?.message || 'Unable to calculate'}</div>
            `;
            
            // Reset forecasted cost to 0 when calculation fails
            const forecastedCostInput = document.getElementById('forecasted_cost');
            if (forecastedCostInput) {
                forecastedCostInput.value = '0';
            }
        }
    } catch (error) {
        console.error('Error fetching forecast:', error);
        forecastValueEl.classList.remove('forecast-loading');
        forecastValueEl.innerHTML = `
            <div class="forecast-amount text-danger">Error</div>
            <div class="forecast-details text-danger small">Failed to load data</div>
        `;
        
        // Reset forecasted cost to 0 when there's an error
        const forecastedCostInput = document.getElementById('forecasted_cost');
        if (forecastedCostInput) {
            forecastedCostInput.value = '0';
        }
    }
}

// Initialize event listeners
document.addEventListener('DOMContentLoaded', function() {
    const sizeInput = document.getElementById('size');
    const categorySelect = document.getElementById('category');
    const forecastValueEl = document.getElementById('forecastedValue');
    
    // Only initialize if all required elements exist
    if (!sizeInput || !categorySelect || !forecastValueEl) {
        console.log('Forecast elements not found on this page');
        return;
    }
    
    // Calculate forecast when Enter is pressed or input loses focus
    sizeInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const size = parseFloat(this.value);
            if (size > 0) {
                calculateForecast(size);
            }
        }
    });
    
    // Also calculate when input loses focus (tab out or click away)
    sizeInput.addEventListener('blur', function() {
        const size = parseFloat(this.value);
        if (size > 0) {
            calculateForecast(size);
        } else if (forecastValueEl) {
            // Hide forecast if size is not valid
            forecastValueEl.style.display = 'none';
        }
    });
    
    // Recalculate forecast when category changes (if we have a valid size)
    categorySelect.addEventListener('change', function() {
        const size = parseFloat(sizeInput.value);
        if (size > 0) {
            calculateForecast(size);
        }
    });
    
    // Log that forecast is initialized
    console.log('Project forecast initialized');
    
    // Reset forecasted cost when form is reset or modal is closed
    const resetForecastedCost = () => {
        const forecastedCostInput = document.getElementById('forecasted_cost');
        if (forecastedCostInput) {
            forecastedCostInput.value = '0';
        }
    };
    
    // Listen for form reset events
    const form = document.getElementById('multiStepForm');
    if (form) {
        form.addEventListener('reset', resetForecastedCost);
    }
    
    // Listen for modal close events
    const modal = document.getElementById('addProjectModal');
    if (modal) {
        modal.addEventListener('hidden.bs.modal', resetForecastedCost);
    }
});
