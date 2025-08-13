document.addEventListener('DOMContentLoaded', function() {
    const requestBudgetBtn = document.getElementById('requestBudgetBtn');
    const budgetForm = document.getElementById('budgetForm');
    const budgetInput = document.querySelector('input[name="budget"]');
    const budgetMessage = document.getElementById('budgetMessage');
    const projectIdInput = document.querySelector('input[name="project_id"]');
    const projectId = new URLSearchParams(window.location.search).get('project_id');
    
    // Set the project ID in the hidden input field
    if (projectIdInput && projectId) {
        projectIdInput.value = projectId;
    }

    // Format budget input with commas and 2 decimal places
    budgetInput.addEventListener('input', function(e) {
        // Remove all non-digit characters except decimal point
        let value = e.target.value.replace(/[^\d.]/g, '');
        
        // Ensure only one decimal point
        const decimalSplit = value.split('.');
        if (decimalSplit.length > 2) {
            value = decimalSplit[0] + '.' + decimalSplit.slice(1).join('');
        }
        
        // Format with commas for thousands and limit to 2 decimal places
        if (value) {
            const parts = value.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            
            // Limit to 2 decimal places
            if (parts[1] && parts[1].length > 2) {
                parts[1] = parts[1].substring(0, 2);
            }
            
            e.target.value = parts.join('.');
        }
    });

    // Form submission handler
    if (requestBudgetBtn) {
        requestBudgetBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get the raw value (remove non-numeric characters)
            const rawBudgetValue = budgetInput.value.replace(/[^0-9]/g, '');
            const budgetValue = parseFloat(rawBudgetValue);
            
            // Validation
            if (!budgetValue || isNaN(budgetValue) || budgetValue <= 0) {
                showFeedback('Please enter a valid budget amount greater than zero.', 'danger');
                return;
            }
            
            if (!projectId) {
                showFeedback('Project ID is missing. Please refresh the page and try again.', 'danger');
                return;
            }
            
            // Disable button and show loading state
            requestBudgetBtn.disabled = true;
            requestBudgetBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...';
            
            // Manually create form data
            const formData = new FormData();
            formData.append('project_id', projectId);
            formData.append('budget', budgetValue);
            
            // Submit via AJAX
            fetch('save_budget_approval.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showFeedback(data.message, 'success');
                    // Reload the page after 1.5 seconds to show updated status
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    throw new Error(data.message || 'Failed to submit budget approval');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showFeedback(error.message || 'An error occurred while submitting the budget approval', 'danger');
            })
            .finally(() => {
                // Re-enable button and reset text
                requestBudgetBtn.disabled = false;
                requestBudgetBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Request Budget';
            });
        });
    }
    
    // Helper function to show feedback messages
    function showFeedback(message, type) {
        if (budgetMessage) {
            // Create a temporary div to show the message
            const tempDiv = document.createElement('div');
            tempDiv.className = `alert alert-${type} mt-2`;
            tempDiv.role = 'alert';
            tempDiv.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close float-end" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            
            // Insert the message before the button
            const buttonContainer = requestBudgetBtn.parentElement;
            buttonContainer.insertBefore(tempDiv, requestBudgetBtn.nextSibling);
            
            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(() => {
                    tempDiv.remove();
                }, 5000);
            }
            
            // Add click handler for close button
            const closeBtn = tempDiv.querySelector('.btn-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    tempDiv.remove();
                });
            }
        } else {
            // Fallback to alert if message container not found
            alert(`${type.toUpperCase()}: ${message}`);
        }
    }
    
    // Load existing budget data if any
    function loadBudgetData() {
        if (!projectId) return;
        
        fetch(`get_budget_status.php?project_id=${projectId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.budget) {
                    // If budget exists, update the form
                    if (data.budget.budget) {
                        const formattedBudget = parseFloat(data.budget.budget).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                        budgetInput.value = formattedBudget;
                        
                        // Update button based on status
                        if (data.budget.status === 'Pending') {
                            requestBudgetBtn.textContent = 'Request Pending';
                            requestBudgetBtn.disabled = true;
                            budgetInput.disabled = true;
                            showFeedback('A budget request is pending approval. Please wait for the current request to be approved or declined before making changes.', 'info');
                            
                            // Disable Next button if status is Pending
                            const nextButton = document.querySelector('.next-step[data-next="4"]');
                            if (nextButton) {
                                nextButton.disabled = true;
                                nextButton.title = 'Please wait for budget approval to continue';
                                nextButton.innerHTML = 'Waiting for Approval <i class="fas fa-clock ms-1"></i>';
                            }
                        } else if (data.budget.status === 'Approved') {
                            requestBudgetBtn.textContent = 'Budget Approved';
                            requestBudgetBtn.disabled = true;
                            budgetInput.disabled = true;
                            showFeedback('This budget has been approved.', 'success');
                        } else if (data.budget.status === 'Rejected') {
                            requestBudgetBtn.textContent = 'Resubmit Budget';
                            showFeedback('Previous budget was rejected. Please update and resubmit.', 'warning');
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error loading budget data:', error);
            });
    }
    
    // Load budget data when page loads
    loadBudgetData();
});