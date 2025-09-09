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

    // Format currency function
    function formatCurrency(amount) {
        return '₱' + parseFloat(amount || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    

    
    // Budget summary functionality has been removed as requested
    
    
    // Function to fetch and display approved billing requests history
    function fetchApprovedRequestsHistory(projectId) {
        console.log('🔄 Fetching approved billing requests history for project:', projectId);
        
        // Show loading state in the modal
        const tbody = document.getElementById('approvedRequestsHistory');
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="3" class="text-center py-4">
                        <div class="d-flex justify-content-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
        }
        
        return fetch('get_approved_billing_requests.php?project_id=' + encodeURIComponent(projectId))
            .then(async response => {
                // First, get the response as text to handle both JSON and non-JSON responses
                const responseText = await response.text();
                
                // Try to parse as JSON
                try {
                    const data = JSON.parse(responseText);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}, message: ${data.message || 'Unknown error'}`);
                    }
                    return data;
                } catch (e) {
                    // If it's not valid JSON, throw an error with the raw response
                    console.error('Invalid JSON response:', responseText);
                    throw new Error(`Invalid response from server: ${responseText.substring(0, 100)}...`);
                }
            })
            .then(data => {
                console.log('✅ Approved billing requests data:', data);
                
                if (!tbody) {
                    console.error('Approved requests history table body not found');
                    return data; // Return data for chaining
                }
                
                // Clear existing rows
                tbody.innerHTML = '';
                
                if (data.success && data.data && data.data.length > 0) {
                    // Sort requests by date (newest first)
                    const sortedRequests = [...data.data].sort((a, b) => 
                        new Date(b.request_date) - new Date(a.request_date)
                    );
                    
                    // Add each approved request as a row
                    sortedRequests.forEach(request => {
                        const row = document.createElement('tr');
                        
                        // Format date
                        const requestDate = new Date(request.request_date);
                        const formattedDate = requestDate.toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        
                        // Format amount
                        const formattedAmount = '₱' + parseFloat(request.amount || 0).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                        
                        // Status badge
                        let statusClass = 'bg-secondary';
                        let statusText = request.status;
                        
                        if (request.status.toLowerCase() === 'approved') {
                            statusClass = 'bg-success';
                            statusText = 'Approved';
                        } else if (request.status.toLowerCase() === 'rejected') {
                            statusClass = 'bg-danger';
                            statusText = 'Rejected';
                        } else if (request.status.toLowerCase() === 'pending') {
                            statusClass = 'bg-warning text-dark';
                            statusText = 'Pending';
                        }
                        
                        row.innerHTML = `
                            <td>${formattedDate}</td>
                            <td class="text-end">${formattedAmount}</td>
                            <td class="text-center">
                                <span class="badge ${statusClass} px-3 py-2">
                                    ${statusText}
                                </span>
                            </td>
                        `;
                        
                        tbody.appendChild(row);
                    });
                } else {
                    // Show no requests message
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td colspan="3" class="text-center text-muted py-4">
                            <div class="d-flex flex-column align-items-center">
                                <i class="fas fa-inbox fa-2x mb-2 text-muted"></i>
                                <span>No approved requests found</span>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(row);
                }
                
                return data; // Return data for chaining
            })
            .catch(error => {
                console.error('Error fetching approved billing requests:', error);
                if (tbody) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="3" class="text-center py-4">
                                <div class="d-flex flex-column align-items-center">
                                    <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                                    <span class="text-danger">Error loading approved requests</span>
                                    <small class="text-muted mt-1">${error.message || 'Please try again later'}</small>
                                </div>
                            </td>
                        </tr>
                    `;
                }
                throw error; // Re-throw to handle in the calling code
            });
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
            console.error('Error fetching approved billing requests:', error);
            const tbody = document.getElementById('approvedRequestsHistory');
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="3" class="text-center text-danger py-3">
                            Error loading approved requests
                        </td>
                    </tr>
                `;
            }
        });
}

    // Set up approve/reject button event listeners
    const approveBtn = document.getElementById('approveBillingBtn');
    const rejectBtn = document.getElementById('rejectBillingBtn');
    const viewHistoryBtn = document.getElementById('viewApprovedHistoryBtn');
    const approvedRequestsModal = new bootstrap.Modal(document.getElementById('approvedRequestsModal'));
    let isHistoryLoading = false;

    if (approveBtn) {
        approveBtn.addEventListener('click', () => handleBillingAction('approve'));
        console.log('✅ Approve button event listener added');
    } else {
        console.log('❌ Approve button not found');
    }

    if (rejectBtn) {
        rejectBtn.addEventListener('click', () => handleBillingAction('reject'));
        console.log('✅ Reject button event listener added');
    } else {
        console.log('❌ Reject button not found');
    }

    // Set up view history button and modal events
    if (viewHistoryBtn && approvedRequestsModal) {
        // Handle modal show/hide events to manage backdrop
        const modalElement = document.getElementById('approvedRequestsModal');
        
        // When modal is about to be hidden, ensure backdrop is removed
        modalElement.addEventListener('hidden.bs.modal', function () {
            // Remove any lingering modal backdrops
            const backdrops = document.getElementsByClassName('modal-backdrop');
            while(backdrops[0]) {
                backdrops[0].parentNode.removeChild(backdrops[0]);
            }
            // Remove modal-open class from body
            document.body.classList.remove('modal-open');
            // Reset inline styles
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        });
        
        // Handle view history button click
        viewHistoryBtn.addEventListener('click', function() {
            console.log('🔄 View Approved History button clicked');
            if (!isHistoryLoading) {
                isHistoryLoading = true;
                const spinner = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Loading...';
                viewHistoryBtn.innerHTML = spinner;
                viewHistoryBtn.disabled = true;
                
                fetchApprovedRequestsHistory(projectId).finally(() => {
                    isHistoryLoading = false;
                    viewHistoryBtn.innerHTML = '<i class="fas fa-history me-2"></i>View Approved History';
                    viewHistoryBtn.disabled = false;
                    
                    // Show the modal after data is loaded
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                });
            } else {
                // If already loading, just show the modal
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            }
        });
        
        console.log('✅ View History button and modal events initialized');
    } else {
        console.log('❌ View History button or modal not found');
    }

    // Initial fetch
    if (projectId) {
        console.log('🚀 Initializing billing handler for project:', projectId);
        fetchBillingRequest(projectId);
    } else {
        console.error('❌ No project ID available for billing handler');
    }

    // Set up refresh functionality if needed
    const refreshBtn = document.getElementById('refreshBillingRequest');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            console.log('🔄 Refreshing billing request...');
            fetchBillingRequest(projectId);
        });
    }
});
