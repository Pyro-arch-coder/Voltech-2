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
        
        // Validate and update button state
        validateBudgetInput(e.target);
    });
    
    // Also handle when input is cleared
    budgetInput.addEventListener('change', function(e) {
        validateBudgetInput(e.target);
    });
    
    // Handle paste events
    budgetInput.addEventListener('paste', function(e) {
        setTimeout(() => {
            validateBudgetInput(e.target);
        }, 100);
    });
    
    // Function to validate budget input and update button state
    function validateBudgetInput(input) {
        // Get the raw value (remove non-numeric characters)
        const rawValue = input.value.replace(/[^0-9]/g, '');
        const requestBtn = document.getElementById('requestBudgetBtn');
        const budgetMessage = document.getElementById('budgetMessage');
        
        // Check if input is valid (at least 6 digits, maximum 9 digits, and greater than 0)
        const isValid = rawValue.length >= 6 && rawValue.length <= 9 && parseInt(rawValue) > 0;
        
        // Update help text and button state based on input
        if (!rawValue || rawValue.length === 0) {
            budgetMessage.innerHTML = 'Enter a budget amount (6-9 digits, e.g., <strong>100000</strong>)';
            budgetMessage.className = 'form-text text-muted';
            requestBtn.disabled = true;
            requestBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> Enter Budget Amount First';
            requestBtn.className = 'btn btn-secondary w-100';
        } else if (rawValue.length < 6) {
            budgetMessage.innerHTML = '<span class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i> Budget amount must be at least 6 digits</span>';
            budgetMessage.className = 'form-text text-warning';
            requestBtn.disabled = true;
            requestBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> Minimum 6 Digits Required';
            requestBtn.className = 'btn btn-warning w-100';
        } else if (rawValue.length > 9) {
            budgetMessage.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle me-1"></i> Budget amount cannot exceed 9 digits</span>';
            budgetMessage.className = 'form-text text-danger';
            requestBtn.disabled = true;
            requestBtn.innerHTML = '<i class="fas fa-times-circle me-1"></i> Maximum 9 Digits Allowed';
            requestBtn.className = 'btn btn-danger w-100';
        } else if (parseInt(rawValue) === 0) {
            budgetMessage.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle me-1"></i> Budget amount cannot be 0</span>';
            budgetMessage.className = 'form-text text-danger';
            requestBtn.disabled = true;
            requestBtn.innerHTML = '<i class="fas fa-times-circle me-1"></i> Amount Cannot Be Zero';
            requestBtn.className = 'btn btn-danger w-100';
        } else {
            budgetMessage.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i> Valid budget amount</span>';
            budgetMessage.className = 'form-text text-success';
            requestBtn.disabled = false;
            requestBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Request Budget Approval';
            requestBtn.className = 'btn btn-success w-100';
        }
    }

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
                    
                    // Force refresh budget data to update button state
                    setTimeout(() => {
                        loadBudgetData();
                    }, 500);
                    
                    // Reload the page after 2 seconds to show updated status
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    throw new Error(data.message || 'Failed to submit budget approval');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showFeedback(error.message || 'An error occurred while submitting the budget approval', 'danger');
            })
            .finally(() => {
                // Re-enable button and reset text based on current state
                if (budgetInput.value.trim()) {
                    requestBudgetBtn.disabled = false;
                    requestBudgetBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Request Budget Approval';
                    requestBudgetBtn.className = 'btn btn-success w-100';
                } else {
                    requestBudgetBtn.disabled = true;
                    requestBudgetBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> Enter Budget Amount First';
                    requestBudgetBtn.className = 'btn btn-secondary w-100';
                }
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
                if (data.success) {
                    if (data.budget) {
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
                                requestBudgetBtn.className = 'btn btn-warning w-100';
                                budgetInput.disabled = true;
                                
                                // Remove any existing feedback messages
                                const existingFeedback = document.querySelector('.alert');
                                if (existingFeedback) {
                                    existingFeedback.remove();
                                }
                                
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
                                requestBudgetBtn.className = 'btn btn-success w-100';
                                budgetInput.disabled = true;
                                
                                // Remove any existing feedback messages
                                const existingFeedback = document.querySelector('.alert');
                                if (existingFeedback) {
                                    existingFeedback.remove();
                                }
                                
                                showFeedback('This budget has been approved.', 'success');
                                
                                // Enable Next button if status is Approved
                                const nextButton = document.querySelector('.next-step[data-next="4"]');
                                if (nextButton) {
                                    nextButton.disabled = false;
                                    nextButton.title = 'Budget approved, you can proceed to next step';
                                    nextButton.innerHTML = 'Next <i class="fas fa-arrow-right ms-1"></i>';
                                }
                            } else if (data.budget.status === 'Rejected') {
                                requestBudgetBtn.textContent = 'Resubmit Budget';
                                requestBudgetBtn.disabled = false;
                                requestBudgetBtn.className = 'btn btn-danger w-100';
                                budgetInput.disabled = false;
                                
                                // Remove any existing feedback messages
                                const existingFeedback = document.querySelector('.alert');
                                if (existingFeedback) {
                                    existingFeedback.remove();
                                }
                                
                                showFeedback('Previous budget was rejected. Please update and resubmit.', 'warning');
                                
                                // Disable Next button if status is Rejected
                                const nextButton = document.querySelector('.next-step[data-next="4"]');
                                if (nextButton) {
                                    nextButton.disabled = true;
                                    nextButton.title = 'Please resubmit budget for approval';
                                    nextButton.innerHTML = 'Budget Rejected <i class="fas fa-times ms-1"></i>';
                                }
                            }
                        }
                    } else {
                        // No budget exists - disable button and show message
                        requestBudgetBtn.textContent = 'Enter Budget Amount First';
                        requestBudgetBtn.disabled = true;
                        requestBudgetBtn.className = 'btn btn-secondary w-100';
                        budgetInput.disabled = false;
                        
                        // Remove any existing feedback messages
                        const existingFeedback = document.querySelector('.alert');
                        if (existingFeedback) {
                            existingFeedback.remove();
                        }
                        
                        // Disable Next button if no budget
                        const nextButton = document.querySelector('.next-step[data-next="4"]');
                        if (nextButton) {
                            nextButton.disabled = true;
                            nextButton.title = 'Please submit budget for approval first';
                            nextButton.innerHTML = 'Submit Budget First <i class="fas fa-exclamation-triangle ms-1"></i>';
                        }
                        
                        showFeedback('Please enter a budget amount and submit for approval before proceeding to the next step.', 'info');
                    }
                } else {
                    console.error('Failed to load budget data:', data.message);
                }
            })
            .catch(error => {
                console.error('Error loading budget data:', error);
                // On error, disable button and show error message
                requestBudgetBtn.textContent = 'Error Loading Budget Data';
                requestBudgetBtn.disabled = true;
                requestBudgetBtn.className = 'btn btn-danger w-100';
                
                // Disable Next button on error
                const nextButton = document.querySelector('.next-step[data-next="4"]');
                if (nextButton) {
                    nextButton.disabled = true;
                    nextButton.title = 'Error loading budget data';
                    nextButton.innerHTML = 'Error <i class="fas fa-exclamation-triangle ms-1"></i>';
                }
            });
    }
    
    // Load budget data when page loads
    loadBudgetData();
    
    // Also validate the input on page load to set initial button state
    if (budgetInput) {
        validateBudgetInput(budgetInput);
        
        // If no value on page load, ensure button is disabled
        if (!budgetInput.value.trim()) {
            requestBudgetBtn.textContent = 'Enter Budget Amount First';
            requestBudgetBtn.disabled = true;
            requestBudgetBtn.className = 'btn btn-secondary w-100';
        }
    }
    
    // Force check budget status after a short delay to ensure proper state
    setTimeout(() => {
        console.log('Forcing budget status check...');
        loadBudgetData();
    }, 1000);
    
    // Check if there's already a budget approval for this project
    function checkInitialBudgetStatus() {
        if (!projectId) return;
        
        console.log('Checking initial budget status for project:', projectId);
        
        fetch(`get_budget_status.php?project_id=${projectId}`)
            .then(response => response.json())
            .then(data => {
                console.log('Budget status response:', data);
                if (data.success && data.budget) {
                    // If budget exists, update the form and button state
                    if (data.budget.budget) {
                        const formattedBudget = parseFloat(data.budget.budget).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                        budgetInput.value = formattedBudget;
                        
                        // Update button based on status
                        if (data.budget.status === 'Pending') {
                            console.log('Budget is pending, updating button state...');
                            requestBudgetBtn.textContent = 'Request Pending';
                            requestBudgetBtn.disabled = true;
                            requestBudgetBtn.className = 'btn btn-warning w-100';
                            budgetInput.disabled = true;
                            
                            // Disable Next button if status is Pending
                            const nextButton = document.querySelector('.next-step[data-next="4"]');
                            if (nextButton) {
                                nextButton.disabled = true;
                                nextButton.title = 'Please wait for budget approval to continue';
                                nextButton.innerHTML = 'Waiting for Approval <i class="fas fa-clock ms-1"></i>';
                            }
                        } else if (data.budget.status === 'Approved') {
                            console.log('Budget is approved, updating button state...');
                            requestBudgetBtn.textContent = 'Budget Approved';
                            requestBudgetBtn.disabled = true;
                            requestBudgetBtn.className = 'btn btn-success w-100';
                            budgetInput.disabled = true;
                            
                            // Enable Next button if status is Approved
                            const nextButton = document.querySelector('.next-step[data-next="4"]');
                            if (nextButton) {
                                nextButton.disabled = false;
                                nextButton.title = 'Budget approved, you can proceed to next step';
                                nextButton.innerHTML = 'Next <i class="fas fa-arrow-right ms-1"></i>';
                            }
                        } else if (data.budget.status === 'Rejected') {
                            console.log('Budget is rejected, updating button state...');
                            requestBudgetBtn.textContent = 'Resubmit Budget';
                            requestBudgetBtn.disabled = false;
                            requestBudgetBtn.className = 'btn btn-danger w-100';
                            budgetInput.disabled = false;
                            
                            // Disable Next button if status is Rejected
                            const nextButton = document.querySelector('.next-step[data-next="4"]');
                            if (nextButton) {
                                nextButton.disabled = true;
                                nextButton.title = 'Please resubmit budget for approval';
                                nextButton.innerHTML = 'Budget Rejected <i class="fas fa-times ms-1"></i>';
                            }
                        }
                    }
                } else {
                    console.log('No budget found or error:', data);
                }
            })
            .catch(error => {
                console.error('Error checking initial budget status:', error);
            });
    }
    
    // Check initial budget status
    checkInitialBudgetStatus();
    
    // Function to refresh button state (can be called from other scripts)
    window.refreshBudgetButtonState = function() {
        if (budgetInput && requestBudgetBtn) {
            validateBudgetInput(budgetInput);
        }
    };
    
    // Listen for step changes to refresh button state
    document.addEventListener('DOMContentLoaded', function() {
        // Check if we're on step 3
        const step3 = document.getElementById('step3');
        if (step3 && !step3.classList.contains('d-none')) {
            // We're on step 3, refresh the button state
            setTimeout(() => {
                refreshBudgetButtonState();
            }, 100);
        }
        
        // Listen for step navigation
        const nextButtons = document.querySelectorAll('.next-step');
        const prevButtons = document.querySelectorAll('.prev-step');
        
        nextButtons.forEach(button => {
            button.addEventListener('click', function() {
                const nextStep = this.getAttribute('data-next');
                if (nextStep === '3') {
                    // User is navigating to step 3, refresh button state after a delay
                    setTimeout(() => {
                        refreshBudgetButtonState();
                    }, 200);
                }
            });
        });
        
        prevButtons.forEach(button => {
            button.addEventListener('click', function() {
                const prevStep = this.getAttribute('data-prev');
                if (prevStep === '3') {
                    // User is navigating to step 3, refresh button state after a delay
                    setTimeout(() => {
                        refreshBudgetButtonState();
                    }, 200);
                }
            });
        });
        
        // Check if page was loaded with step 3 active
        const urlParams = new URLSearchParams(window.location.search);
        const projectIdFromUrl = urlParams.get('project_id');
        if (projectIdFromUrl) {
            // Check if we're on step 3 by looking at the current step
            const currentStep = window.currentStep || 1;
            if (currentStep === 3) {
                // We're on step 3, refresh the button state
                setTimeout(() => {
                    refreshBudgetButtonState();
                }, 300);
            }
        }
    });
});