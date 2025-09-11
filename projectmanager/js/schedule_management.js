// Function to initialize date pickers with project constraints
function initializeDatePickers() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    
    if (startDateInput && endDateInput) {
        // Function to add days to a date
        function addDays(date, days) {
            const result = new Date(date);
            result.setDate(result.getDate() + days);
            return result.toISOString().split('T')[0];
        }

        // Update end date when start date changes
        startDateInput.addEventListener('change', function() {
            if (this.value) {
                // Set end date to start date + 3 days
                const endDate = addDays(this.value, 3);
                endDateInput.value = endDate;
                endDateInput.min = this.value;
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