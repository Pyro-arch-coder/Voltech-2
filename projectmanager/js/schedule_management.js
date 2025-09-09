// Function to initialize date pickers with project constraints
function initializeDatePickers() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    
    if (startDateInput && endDateInput) {
        // Update end date min when start date changes
        startDateInput.addEventListener('change', function() {
            if (this.value) {
                endDateInput.min = this.value;
                // If current end date is before new start date, update it
                if (endDateInput.value && new Date(endDateInput.value) < new Date(this.value)) {
                    endDateInput.value = this.value;
                }
            } else if (typeof PROJECT_START_DATE !== 'undefined') {
                endDateInput.min = PROJECT_START_DATE;
            }
        });
        
        // Set initial min values if project dates are defined
        if (typeof PROJECT_START_DATE !== 'undefined') {
            startDateInput.min = PROJECT_START_DATE;
            endDateInput.min = PROJECT_START_DATE;
        }
        if (typeof PROJECT_DEADLINE !== 'undefined') {
            startDateInput.max = PROJECT_DEADLINE;
            endDateInput.max = PROJECT_DEADLINE;
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Get project ID from URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const projectId = urlParams.get('project_id');
    
    if (!projectId) {
        console.error('Project ID not found in URL');
        return;
    }
    
    // Set the project ID in the form if it exists
    const projectIdInput = document.querySelector('input[name="project_id"]');
    if (projectIdInput) {
        projectIdInput.value = projectId;
    }

    // Initialize date pickers with project constraints
    initializeDatePickers();
    
    // Load schedule items when page loads
    loadScheduleItems(projectId);

    // Handle form submission
    const scheduleForm = document.getElementById('addScheduleForm');
    if (scheduleForm) {
        scheduleForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveScheduleItem(projectId);
            return false; // Prevent default form submission
        });
    }

    // Close modal handler
    const modal = document.getElementById('addScheduleModal');
    if (modal) {
        modal.addEventListener('hidden.bs.modal', function() {
            if (scheduleForm) {
                scheduleForm.reset();
                const formInputs = scheduleForm.querySelectorAll('.is-invalid');
                formInputs.forEach(input => input.classList.remove('is-invalid'));
            }
        });
    }
});

function loadScheduleItems(projectId) {
    const tableBody = document.getElementById('timelineTableBody');
    if (!tableBody) return;

    tableBody.innerHTML = `
        <tr>
            <td colspan="7" class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading schedule items...</p>
            </td>
        </tr>`;

    fetch(`get_schedule_items.php?project_id=${projectId}`)
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(`HTTP error! status: ${response.status}, response: ${text}`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.data) {
                renderScheduleTable(Array.isArray(data.data) ? data.data : []);
            } else {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="fas fa-calendar-plus fa-2x text-muted mb-2"></i>
                            <p>No schedule items found. Add your first task to get started.</p>
                        </td>
                    </tr>`;
            }
        })
        .catch(error => {
            console.error('Error loading schedule items:', error);
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-danger py-4">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Failed to load schedule items: ${error.message}
                    </td>
                </tr>`;
        });
}

function renderScheduleTable(items) {
    const tableBody = document.getElementById('timelineTableBody');
    if (!tableBody) return;

    tableBody.innerHTML = items.map((item, index) => `
        <tr data-id="${item.id}">
            <td>${index + 1}</td>
            <td>${item.task_name}</td>
            <td>${item.description || '-'}</td>
            <td>${formatDate(item.start_date)}</td>
            <td>${formatDate(item.end_date)}</td>
            <td>
                <span class="badge ${getStatusBadgeClass(item.status)}">
                    ${item.status}
                </span>
            </td>
            <td>
                <div class="progress" style="height: 20px;">
                    <div class="progress-bar ${getProgressBarClass(item.progress)}" 
                         role="progressbar" 
                         style="width: ${item.progress}%" 
                         aria-valuenow="${item.progress}" 
                         aria-valuemin="0" 
                         aria-valuemax="100">
                        ${item.progress}%
                    </div>
                </div>
            </td>
        </tr>
    `).join('');
}

function saveScheduleItem(projectId) {
    const form = document.getElementById('addScheduleForm');
    if (!form) {
        console.error('Form not found');
        return;
    }
    
    // Get form data
    const formData = new FormData(form);
    const data = {
        project_id: projectId,
        task_name: formData.get('task_name')?.trim() || '',
        description: formData.get('description')?.trim() || '',
        start_date: formData.get('start_date') || '',
        end_date: formData.get('end_date') || '',
        status: formData.get('status') || 'Not Started'
    };
    
    // Basic validation
    let isValid = true;
    
    // Clear previous errors
    const errorElements = form.querySelectorAll('.invalid-feedback');
    errorElements.forEach(el => el.remove());
    
    const inputs = form.querySelectorAll('input, select');
    inputs.forEach(input => input.classList.remove('is-invalid'));
    
    // Validate required fields
    if (!data.task_name) {
        showValidationError('task_name', 'Task name is required');
        isValid = false;
    }
    
    if (!data.start_date) {
        showValidationError('start_date', 'Start date is required');
        isValid = false;
    } else if (typeof PROJECT_START_DATE !== 'undefined' && new Date(data.start_date) < new Date(PROJECT_START_DATE)) {
        const formattedDate = typeof PROJECT_START_DISPLAY !== 'undefined' ? PROJECT_START_DISPLAY : new Date(PROJECT_START_DATE).toLocaleDateString();
        showValidationError('start_date', `Start date cannot be before project start date (${formattedDate})`);
        isValid = false;
    }
    
    if (!data.end_date) {
        showValidationError('end_date', 'End date is required');
        isValid = false;
    } else if (data.start_date && new Date(data.end_date) < new Date(data.start_date)) {
        showValidationError('end_date', 'End date must be after start date');
        isValid = false;
    } else if (typeof PROJECT_DEADLINE !== 'undefined' && new Date(data.end_date) > new Date(PROJECT_DEADLINE)) {
        const formattedDate = typeof PROJECT_DEADLINE_DISPLAY !== 'undefined' ? PROJECT_DEADLINE_DISPLAY : new Date(PROJECT_DEADLINE).toLocaleDateString();
        showValidationError('end_date', `End date cannot be after project deadline (${formattedDate})`);
        isValid = false;
    }
    
    if (!isValid) {
        return false;
    }
    
    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';

    // Log the data being sent
    console.log('Sending data to server:', data);
    
    // Send the request
    fetch('save_schedule.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(async response => {
        const responseText = await response.text();
        
        // Log the raw response for debugging
        console.log('Response status:', response.status, response.statusText);
        console.log('Response headers:', Object.fromEntries(response.headers.entries()));
        console.log('Raw response:', responseText);
        
        try {
            // Try to parse as JSON
            const responseData = responseText ? JSON.parse(responseText) : {};
            
            if (!response.ok) {
                // Log detailed error information
                console.error('Server returned error response:', {
                    status: response.status,
                    statusText: response.statusText,
                    data: responseData
                });
                
                // Construct a detailed error message
                let errorMessage = 'Server error';
                if (responseData && responseData.message) {
                    errorMessage = responseData.message;
                } else if (response.status === 500) {
                    errorMessage = 'Internal server error occurred';
                } else if (response.status === 401) {
                    errorMessage = 'Session expired. Please refresh the page and try again.';
                } else if (response.status === 400) {
                    errorMessage = 'Invalid request. Please check your input and try again.';
                }
                
                const error = new Error(errorMessage);
                error.response = response;
                error.data = responseData;
                throw error;
            }
            
            return responseData;
        } catch (e) {
            // If parsing as JSON fails, it's likely an HTML error page or empty response
            console.error('Failed to parse JSON response:', e);
            
            // Try to extract error message from HTML if possible
            let errorMessage = 'Server returned an invalid response';
            if (responseText.includes('<b>Fatal error</b>')) {
                const fatalErrorMatch = responseText.match(/<b>Fatal error<\/b>:\s*([^<]+)/i);
                if (fatalErrorMatch && fatalErrorMatch[1]) {
                    errorMessage = `Server error: ${fatalErrorMatch[1].trim()}`;
                }
            } else if (responseText.includes('<b>Warning</b>') || responseText.includes('<b>Notice</b>')) {
                const warningMatch = responseText.match(/<b>(Warning|Notice)<\/b>:\s*([^<]+)/i);
                if (warningMatch && warningMatch[2]) {
                    errorMessage = `Server ${warningMatch[1].toLowerCase()}: ${warningMatch[2].trim()}`;
                }
            } else if (responseText.trim() === '') {
                errorMessage = 'Server returned an empty response (500 Internal Server Error)';
            }
            
            const error = new Error(errorMessage);
            error.rawResponse = responseText;
            throw error;
        }
    })
    .then(data => {
        if (data && data.success) {
            // Close the modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('addScheduleModal'));
            if (modal) {
                modal.hide();
                // Reset form after successful submission
                form.reset();
            }
            
            // Show success message
            showAlert('Schedule item saved successfully! Refreshing page...', 'success');
            
            // Reload the schedule items and refresh page after 1.5 seconds
            loadScheduleItems(projectId);
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            throw new Error(data?.message || 'Failed to save schedule item');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error: ' + (error.message || 'Failed to save schedule. Please try again.'), 'danger');
    })
    .finally(() => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText || 'Save Schedule';
        }
    });
    
    return false; // Prevent form submission
}

// Helper functions
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
}

function getStatusBadgeClass(status) {
    const statusClasses = {
        'Completed': 'bg-success',
        'In Progress': 'bg-primary',
        'On Hold': 'bg-warning',
        'Not Started': 'bg-secondary'
    };
    return statusClasses[status] || 'bg-secondary';
}

function getProgressBarClass(progress) {
    if (progress >= 100) return 'bg-success';
    if (progress >= 75) return 'bg-primary';
    if (progress >= 50) return 'bg-info';
    if (progress >= 25) return 'bg-warning';
    return 'bg-secondary';
}

function showValidationError(fieldName, message) {
    const input = document.querySelector(`[name="${fieldName}"]`);
    if (input) {
        input.classList.add('is-invalid');
        let feedback = input.nextElementSibling;
        if (!feedback || !feedback.classList.contains('invalid-feedback')) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            input.parentNode.insertBefore(feedback, input.nextSibling);
        }
        feedback.textContent = message;
    }
}

function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.role = 'alert';
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '9999';
    alertDiv.style.minWidth = '300px';
    alertDiv.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';

    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;

    // Add animation styles
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .alert {
            animation: slideInRight 0.3s ease-out;
        }
    `;
    document.head.appendChild(style);

    // Add to body if no container exists
    const container = document.getElementById('alert-container') || createAlertContainer();
    container.appendChild(alertDiv);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        alertDiv.style.opacity = '1';
        const fadeOut = () => {
            if ((alertDiv.style.opacity -= 0.1) < 0) {
                alertDiv.remove();
            } else {
                requestAnimationFrame(fadeOut);
            }
        };
        fadeOut();
    }, 5000);
}

function createAlertContainer() {
    const container = document.createElement('div');
    container.id = 'alert-container';
    document.body.appendChild(container);
    return container;
}