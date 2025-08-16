document.addEventListener('DOMContentLoaded', function() {
    // You can set this from PHP as a global variable
    var projectId = window.currentProjectId || null;
    var currentBillingId = null; // Store the current billing request ID

    console.log('=== BILLING HANDLER INITIALIZATION ===');
    console.log('Window currentProjectId:', window.currentProjectId);
    console.log('Project ID variable:', projectId);

    if (!projectId) {
        console.log('❌ No project ID found, billing handler not initialized');
        return;
    }

    console.log('✅ Initializing billing handler for project:', projectId);

    function fetchBillingRequest(projectId) {
        console.log('🔄 Fetching pending billing request for project:', projectId);
        
        const url = 'get_budget_request.php?project_id=' + encodeURIComponent(projectId);
        console.log('📡 Request URL:', url);
        
        fetch(url)
            .then(response => {
                console.log('📥 Response received:', response);
                console.log('📊 Response status:', response.status);
                
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.text(); // Get raw text first
            })
            .then(text => {
                console.log('📄 Raw response text:', text);
                
                try {
                    const data = JSON.parse(text);
                    console.log('✅ Parsed JSON data:', data);
                    
                    if (data.success && data.data) {
                        console.log('✅ Pending billing request data found:', data.data);
                        
                        // Store the billing ID for approve/reject actions
                        currentBillingId = data.data.id;
                        
                        // Update amount
                        const amountElement = document.getElementById('requestedAmount');
                        if (amountElement) {
                            amountElement.textContent = '₱' + data.data.amount;
                            console.log('✅ Updated amount to:', '₱' + data.data.amount);
                        } else {
                            console.log('❌ Amount element not found');
                        }
                        
                        // Update request date
                        const dateElement = document.getElementById('requestDate');
                        if (dateElement) {
                            dateElement.textContent = data.data.request_date;
                            console.log('✅ Updated date to:', data.data.request_date);
                        } else {
                            console.log('❌ Date element not found');
                        }
                        
                        // Update status with proper badge styling
                        const statusElement = document.getElementById('requestStatus');
                        if (statusElement) {
                            statusElement.textContent = data.data.status;
                            updateStatusBadge(statusElement, data.data.status);
                            console.log('✅ Updated status to:', data.data.status);
                        } else {
                            console.log('❌ Status element not found');
                        }
                        
                        // Show approve/reject buttons for pending requests
                        showActionButtons(true);
                        
                        console.log('✅ Pending billing request UI updated successfully');
                    } else {
                        console.log('⚠️ No pending billing request data or request failed:', data.message);
                        showNoRequestMessage();
                        showActionButtons(false);
                    }
                } catch (parseError) {
                    console.error('❌ JSON parse error:', parseError);
                    console.error('Raw text that failed to parse:', text);
                    showNoRequestMessage();
                    showActionButtons(false);
                }
            })
            .catch(err => {
                console.error('❌ Error fetching billing request:', err);
                showNoRequestMessage();
                showActionButtons(false);
            });
    }

    function updateStatusBadge(statusElement, status) {
        // Remove existing status classes
        statusElement.classList.remove('bg-primary', 'bg-success', 'bg-warning', 'bg-danger', 'bg-secondary');
        
        // Add appropriate status class
        switch (status.toLowerCase()) {
            case 'approved':
                statusElement.classList.add('bg-success');
                break;
            case 'pending':
                statusElement.classList.add('bg-warning');
                break;
            case 'rejected':
                statusElement.classList.add('bg-danger');
                break;
            case 'completed':
                statusElement.classList.add('bg-success');
                break;
            default:
                statusElement.classList.add('bg-secondary');
        }
    }

    function showNoRequestMessage() {
        console.log('🔄 Showing no request message');
        
        // Reset form fields to default values
        const amountElement = document.getElementById('requestedAmount');
        if (amountElement) {
            amountElement.textContent = '₱0.00';
        }

        const dateElement = document.getElementById('requestDate');
        if (dateElement) {
            dateElement.textContent = 'Not yet requested';
        }

        const statusElement = document.getElementById('requestStatus');
        if (statusElement) {
            statusElement.textContent = 'Pending';
            statusElement.classList.remove('bg-success', 'bg-warning', 'bg-danger');
            statusElement.classList.add('bg-secondary');
        }
    }

    function showActionButtons(show) {
        const approveBtn = document.getElementById('approveBillingBtn');
        const rejectBtn = document.getElementById('rejectBillingBtn');
        
        if (approveBtn && rejectBtn) {
            if (show) {
                approveBtn.style.display = 'block';
                rejectBtn.style.display = 'block';
                console.log('✅ Showing approve/reject buttons');
            } else {
                approveBtn.style.display = 'none';
                rejectBtn.style.display = 'none';
                console.log('❌ Hiding approve/reject buttons');
            }
        }
    }

    function showSuccessModal(action, message) {
        // Create modal HTML if it doesn't exist
        let modal = document.getElementById('billingSuccessModal');
        if (!modal) {
            const modalHTML = `
                <div class="modal fade" id="billingSuccessModal" tabindex="-1" aria-labelledby="billingSuccessModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header border-0 pb-0">
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-center px-5 pb-5 pt-0">
                                <i class="fas fa-check-circle mb-3" style="font-size: 5rem; color: #28a745;"></i>
                                <h4 class="mb-3 text-success">Success!</h4>
                                <p class="text-muted mb-4">${message}</p>
                                <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal">OK</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            modal = document.getElementById('billingSuccessModal');
        }

        // Update modal content
        const modalTitle = modal.querySelector('h4');
        const modalMessage = modal.querySelector('p');
        const modalIcon = modal.querySelector('i');

        if (action === 'approve') {
            modalTitle.textContent = 'Approved!';
            modalTitle.className = 'mb-3 text-success';
            modalIcon.className = 'fas fa-check-circle mb-3';
            modalIcon.style.color = '#28a745';
        } else {
            modalTitle.textContent = 'Rejected!';
            modalTitle.className = 'mb-3 text-danger';
            modalIcon.className = 'fas fa-times-circle mb-3';
            modalIcon.style.color = '#dc3545';
        }

        modalMessage.textContent = message;

        // Show the modal
        const bootstrapModal = new bootstrap.Modal(modal);
        bootstrapModal.show();
    }

    function handleBillingAction(action) {
        if (!currentBillingId || !projectId) {
            console.error('❌ Missing billing ID or project ID');
            alert('Error: Missing billing information');
            return;
        }

        console.log(`🔄 Processing ${action} for billing request:`, currentBillingId);

        const formData = new FormData();
        formData.append('billing_id', currentBillingId);
        formData.append('action', action);
        formData.append('project_id', projectId);

        fetch('update_billing_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log(`✅ Billing request ${action}d successfully:`, data.message);
                
                // Show success modal instead of alert
                const message = `Billing request has been ${action}d successfully!`;
                showSuccessModal(action, message);
                
                // Refresh the billing request data after a short delay
                setTimeout(() => {
                    fetchBillingRequest(projectId);
                }, 1500);
            } else {
                console.error(`❌ Failed to ${action} billing request:`, data.message);
                alert(`Failed to ${action} billing request: ${data.message}`);
            }
        })
        .catch(error => {
            console.error(`❌ Error ${action}ing billing request:`, error);
            alert(`Error ${action}ing billing request. Please try again.`);
        });
    }

    // Set up approve/reject button event listeners
    const approveBtn = document.getElementById('approveBillingBtn');
    const rejectBtn = document.getElementById('rejectBillingBtn');

    if (approveBtn) {
        approveBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to approve this billing request?')) {
                handleBillingAction('approve');
            }
        });
    }

    if (rejectBtn) {
        rejectBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to reject this billing request?')) {
                handleBillingAction('reject');
            }
        });
    }

    // Initialize billing request display
    console.log('🚀 Starting pending billing request fetch...');
    fetchBillingRequest(projectId);
    
    // Set up refresh functionality if needed
    const refreshBtn = document.getElementById('refreshBillingRequest');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            console.log('🔄 Refreshing billing request...');
            fetchBillingRequest(projectId);
        });
    }
});