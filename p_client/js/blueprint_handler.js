// Global variables
let currentProjectId;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Use the global currentProjectId set in client_process.php
    currentProjectId = window.currentProjectId;
    console.log('Blueprint Handler - Current Project ID:', currentProjectId);
    
    // Load blueprints if we have a project ID
    if (currentProjectId) {
        loadBlueprints();
    } else {
        console.error('No project ID found');
        const statusAlert = document.getElementById('blueprintStatusAlert');
        const statusText = document.getElementById('blueprintStatusText');
        if (statusAlert && statusText) {
            statusAlert.className = 'alert alert-danger';
            statusText.textContent = 'Error: Project ID not found. Please try refreshing the page.';
        }
    }
});

// Function to update blueprint status
async function updateBlueprintStatus(blueprintId, status) {
    try {
        // Ensure we have the current project ID
        if (!currentProjectId) {
            currentProjectId = window.currentProjectId || new URLSearchParams(window.location.search).get('project_id');
            if (!currentProjectId) {
                throw new Error('Project ID not found. Please refresh the page.');
            }
        }
        
        const response = await fetch('update_blueprint_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `blueprint_id=${blueprintId}&status=${status}&project_id=${currentProjectId}`
        });

        const result = await response.json();
        if (result.success) {
            loadBlueprints(); // Reload the blueprints after update
        } else {
            alert('Failed to update blueprint: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error updating blueprint status:', error);
        alert('Error updating blueprint. Please try again.');
    }
}

// Function to handle approve/reject of selected blueprints
function handleBulkAction(action) {
    const checkboxes = document.querySelectorAll('.blueprint-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('Please select at least one blueprint to ' + action);
        return;
    }

    const actionText = action === 'approve' ? 'approve' : (action === 'reject' ? 'reject' : 'revoke');
    if (confirm(`Are you sure you want to ${actionText} the selected ${checkboxes.length} blueprint(s)?`)) {
        checkboxes.forEach(checkbox => {
            const blueprintId = checkbox.value;
            let status = 'Pending';
            if (action === 'approve') status = 'Approved';
            else if (action === 'reject') status = 'Rejected';
            updateBlueprintStatus(blueprintId, status);
        });
    }
}

// Function to load blueprints for the current project
async function loadBlueprints() {
    console.log('Loading blueprints...');
    const container = document.getElementById('blueprintsContainer');
    const noBlueprintsMsg = document.getElementById('noBlueprintsMessage');
    const statusAlert = document.getElementById('blueprintStatusAlert');
    const statusText = document.getElementById('blueprintStatusText');
    const nextBtn = document.getElementById('nextStepBtn');
    const actionButtons = document.getElementById('blueprintActionButtons');
    
    // Reset UI
    container.innerHTML = '';
    noBlueprintsMsg.classList.add('d-none');
    statusAlert.className = 'alert alert-info';
    statusText.textContent = 'Loading blueprints...';
    
    if (actionButtons) {
        actionButtons.classList.add('d-none');
    }
    
    if (!currentProjectId) {
        console.error('No project ID found in URL');
        statusAlert.className = 'alert alert-danger';
        statusText.textContent = 'Error: Project ID not found. Please try refreshing the page.';
        return;
    }
    
    try {
        console.log(`Fetching blueprints for project ID: ${currentProjectId}`);
        const response = await fetch(`get_blueprints.php?project_id=${currentProjectId}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        console.log('Blueprint API response:', result);
        
        if (!result.success) {
            throw new Error(result.message || 'Failed to load blueprints');
        }
        const statusText = document.getElementById('blueprintStatusText');
        const nextBtn = document.getElementById('nextStepBtn');
        
        // Clear previous content
        container.innerHTML = '';
        
        if (!result.success) {
            throw new Error(result.message || 'Failed to load blueprints');
        }
        
        const blueprints = result.data || [];
        
        if (blueprints.length === 0) {
            // No blueprints found
            noBlueprintsMsg.classList.remove('d-none');
            statusAlert.className = 'alert alert-warning';
            statusText.textContent = 'No blueprints have been uploaded for your review yet.';
            nextBtn.disabled = true;
            return;
        }
        
        // Check blueprint statuses
        const allApproved = blueprints.every(bp => bp.status === 'Approved');
        const hasPending = blueprints.some(bp => bp.status === 'Pending');
        const hasRejected = blueprints.some(bp => bp.status === 'Rejected');
        const totalBlueprints = blueprints.length;
        const approvedCount = blueprints.filter(bp => bp.status === 'Approved').length;
        
        if (allApproved) {
            statusAlert.className = 'alert alert-success';
            statusText.innerHTML = `<i class="fas fa-check-circle me-2"></i>All blueprints (${approvedCount}/${totalBlueprints}) have been approved. You may proceed to the next step.`;
            nextBtn.disabled = false;
        } else if (hasPending) {
            statusAlert.className = 'alert alert-warning';
            statusText.textContent = `Please review and approve all blueprints to proceed (${approvedCount}/${totalBlueprints} approved).`;
            nextBtn.disabled = true;
        } else if (hasRejected) {
            statusAlert.className = 'alert alert-danger';
            statusText.textContent = 'Some blueprints have been rejected. Please contact the project manager for assistance.';
            nextBtn.disabled = true;
        } else {
            statusAlert.className = 'alert alert-info';
            statusText.textContent = 'Please review and approve the blueprints below to proceed.';
            nextBtn.disabled = true;
        }
        
        // Show/hide Revoke Selected button based on approved blueprints
        const revokeSelectedBtn = document.getElementById('revokeSelectedBtn');
        if (revokeSelectedBtn) {
            const hasApproved = blueprints.some(bp => bp.status === 'Approved');
            revokeSelectedBtn.classList.toggle('d-none', !hasApproved);
        }

        // Add blueprints to the container
        blueprints.forEach(blueprint => {
            const statusClass = blueprint.status === 'Approved' ? 'success' : 
                              (blueprint.status === 'Rejected' ? 'danger' : 'warning');
            
            const card = `
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="form-check">
                                    <input class="form-check-input blueprint-checkbox" type="checkbox" value="${blueprint.id}" id="bp-${blueprint.id}">
                                    <label class="form-check-label" for="bp-${blueprint.id}">
                                        <span class="text-truncate">${blueprint.name}</span>
                                    </label>
                                </div>
                                <span class="badge bg-${statusClass}">${blueprint.status}</span>
                            </div>
                            <div class="d-flex gap-2">
                                <!-- Action buttons removed from here -->
                            </div>
                        </div>
                        <div class="card-body p-0" style="height: 500px; overflow: hidden;">
                            <iframe 
                                src="${blueprint.image_path}" 
                                class="w-100 h-100"
                                style="border: none;"
                                onerror="this.onerror=null; this.src='../assets/img/document-placeholder.png'"
                            ></iframe>
                        </div>
                        <div class="card-footer bg-white">
                                <small class="text-muted">
                                    <i class="far fa-calendar-alt me-1"></i>
                                    ${new Date(blueprint.created_at).toLocaleDateString()}
                                </small>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', card);
        });
        
        // Add action buttons if there are blueprints
        if (blueprints.length > 0 && actionButtons) {
            actionButtons.classList.remove('d-none');
        }
        
        // Add event listeners for approve/reject/revoke buttons
        document.querySelectorAll('.approve-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const blueprintId = e.currentTarget.getAttribute('data-id');
                updateBlueprintStatus(blueprintId, 'Approved');
            });
        });
        
        document.querySelectorAll('.reject-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const blueprintId = e.currentTarget.getAttribute('data-id');
                updateBlueprintStatus(blueprintId, 'Rejected');
            });
        });
        
        // Add event listener for revoke approval button
        document.querySelectorAll('.revoke-approval').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const blueprintId = e.currentTarget.getAttribute('data-id');
                if (confirm('Are you sure you want to revoke approval for this blueprint? It will return to pending status.')) {
                    updateBlueprintStatus(blueprintId, 'Pending');
                }
            });
        });
        
        // Add select all/none functionality
        const selectAllCheckbox = document.getElementById('selectAllBlueprints');
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.blueprint-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
        }
    } catch (error) {
        console.error('Error loading blueprints:', error);
        const statusAlert = document.getElementById('blueprintStatusAlert');
        statusAlert.className = 'alert alert-danger';
        document.getElementById('blueprintStatusText').textContent = 'Error loading blueprints: ' + (error.message || 'Please try again later.');
    }
}