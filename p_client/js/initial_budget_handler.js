// PAYMENT PROOF / INITIAL BUDGET HANDLER

document.addEventListener('DOMContentLoaded', function () {
    // --- Payment Proof File Upload ---
    const paymentProofDropZone = document.getElementById('paymentProofDropZone');
    const paymentProofFile = document.getElementById('paymentProofFile');
    const browsePaymentProofBtn = document.getElementById('browsePaymentProofBtn');
    const paymentProofPreview = document.getElementById('paymentProofPreview');
    const paymentProofFileName = document.getElementById('paymentProofFileName');
    const paymentProofFileSize = document.getElementById('paymentProofFileSize');
    const viewPaymentProofBtn = document.getElementById('viewPaymentProofBtn');
    const removePaymentProofBtn = document.getElementById('removePaymentProofBtn');
    const saveInitialBudgetBtn = document.getElementById('saveInitialBudget');

    let currentFile = null;

    // File selection via button
    browsePaymentProofBtn.addEventListener('click', () => paymentProofFile.click());

    // Dropzone click triggers file input (only when background/icon/label is clicked)
    paymentProofDropZone.addEventListener('click', (e) => {
        if (
            e.target === paymentProofDropZone ||
            e.target.tagName === 'P' ||
            e.target.tagName === 'I'
        ) {
            paymentProofFile.click();
        }
    });

    // File selection
    paymentProofFile.addEventListener('change', function () {
        if (this.files && this.files[0]) {
            handleFileSelection(this.files[0]);
        }
    });

    // Drag and drop events
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((eventName) => {
        paymentProofDropZone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    // Highlight dropzone
    ['dragenter', 'dragover'].forEach((eventName) => {
        paymentProofDropZone.addEventListener(eventName, highlight, false);
    });

    // Remove highlight
    ['dragleave', 'drop'].forEach((eventName) => {
        paymentProofDropZone.addEventListener(eventName, unhighlight, false);
    });

    function highlight() {
        paymentProofDropZone.classList.add('bg-light');
        paymentProofDropZone.style.borderColor = '#0d6efd';
    }

    function unhighlight() {
        paymentProofDropZone.classList.remove('bg-light');
        paymentProofDropZone.style.borderColor = '#0d6efd';
    }

    // Handle dropped files
    paymentProofDropZone.addEventListener(
        'drop',
        function (e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length) handleFileSelection(files[0]);
        },
        false
    );

    function handleFileSelection(file) {
        const validTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!validTypes.includes(file.type)) {
            showAlert('danger', 'Invalid file type. Please upload a PDF, JPG, or PNG file.');
            return;
        }
        // Validate file size (5MB max)
        const maxSize = 5 * 1024 * 1024;
        if (file.size > maxSize) {
            showAlert('danger', 'File is too large. Maximum size is 5MB.');
            return;
        }
        currentFile = file;

        // File size in MB
        const fileSize = (file.size / (1024 * 1024)).toFixed(2);

        // Update preview
        const fileIcon =
            file.type === 'application/pdf'
                ? 'fa-file-pdf text-danger'
                : 'fa-file-image text-primary';
        const fileIconElement = paymentProofPreview.querySelector('i');
        fileIconElement.className = `fas ${fileIcon} me-2`;

        paymentProofFileName.textContent = file.name;
        paymentProofFileSize.textContent = `${fileSize} MB`;
        paymentProofPreview.classList.remove('d-none');
        paymentProofDropZone.classList.add('d-none');
        saveInitialBudgetBtn.disabled = false;
    }

    // View file
    viewPaymentProofBtn.addEventListener('click', function () {
        if (currentFile) {
            const fileURL = URL.createObjectURL(currentFile);
            window.open(fileURL, '_blank');
        }
    });

    // Remove file
    removePaymentProofBtn.addEventListener('click', function () {
        currentFile = null;
        paymentProofFile.value = '';
        paymentProofPreview.classList.add('d-none');
        paymentProofDropZone.classList.remove('d-none');
        saveInitialBudgetBtn.disabled = true;
    });

    // Show alert
    function showAlert(type, message) {
        // Remove existing alerts
        const existingAlert = document.querySelector('.alert-dismissible');
        if (existingAlert) existingAlert.remove();

        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show mt-3" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;
        const cardHeader = document.querySelector('.card-header');
        if (cardHeader) {
            cardHeader.insertAdjacentHTML('afterend', alertHtml);

            setTimeout(() => {
                const alert = document.querySelector('.alert-dismissible');
                if (alert) alert.remove();
            }, 5000);
        }
    }

    saveInitialBudgetBtn.disabled = true;

    // --- Initial Budget Percentage Buttons & Display ---
    // Function to format as PHP money
    function formatMoney(amount) {
        const n = parseFloat(amount) || 0;
        return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function updateInitialBudgetDisplay() {
        const percentageBtn = document.querySelector('.percentage-btn.active');
        const percentage = percentageBtn ? parseFloat(percentageBtn.dataset.percentage) : 0;
        const projectTotalEl = document.getElementById('projectTotal');
        let projectTotal = 0;
        if (projectTotalEl) {
            projectTotal = parseFloat(projectTotalEl.textContent.replace(/[^0-9.-]+/g, "")) || 0;
        }
        const initialBudget = ((projectTotal * percentage) / 100).toFixed(2);

        const initialBudgetInput = document.getElementById('initialBudget');
        const initialBudgetDisplay = document.getElementById('initialBudgetDisplay');
        if (initialBudgetInput) initialBudgetInput.value = initialBudget;
        if (initialBudgetDisplay) initialBudgetDisplay.textContent = formatMoney(initialBudget);
    }

    // Set up percentage buttons
    const percentageButtons = document.querySelectorAll('.percentage-btn');
    percentageButtons.forEach(button => {
        button.addEventListener('click', function () {
            // Remove active from all, add to this
            percentageButtons.forEach(btn => {
                btn.classList.remove('active', 'btn-primary');
                btn.classList.add('btn-outline-primary');
            });
            this.classList.add('active', 'btn-primary');
            this.classList.remove('btn-outline-primary');
            updateInitialBudgetDisplay();
        });
    });

    // Set initial project total in hidden field
    const projectTotal = document.getElementById('projectTotal')?.textContent || "0";
    const projectTotalBudgetInput = document.getElementById('projectTotalBudget');
    if (projectTotalBudgetInput) projectTotalBudgetInput.value = projectTotal;

    // Update display when project total changes (if needed)
    const projectTotalEl = document.getElementById('projectTotal');
    if (projectTotalEl) {
        new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (
                    mutation.type === 'characterData' ||
                    mutation.type === 'childList'
                ) {
                    const newTotal = document.getElementById('projectTotal').textContent;
                    if (projectTotalBudgetInput) projectTotalBudgetInput.value = newTotal;
                    updateInitialBudgetDisplay();
                }
            });
        }).observe(projectTotalEl, {
            childList: true,
            characterData: true,
            subtree: true,
        });
    }

    // Function to validate form inputs
    function validateForm(isFixedAmount) {
        if (isFixedAmount) {
            const fixedAmountInput = document.getElementById('fixedAmount');
            if (!fixedAmountInput) return false;
            
            const amount = parseFloat(fixedAmountInput.value) || 0;
            const minAmount = parseFloat(fixedAmountInput.min) || 0;
            const maxAmount = parseFloat(fixedAmountInput.max) || 0;
            
            if (isNaN(amount) || amount <= 0) {
                showAlert('danger', 'Please enter a valid amount.');
                return false;
            }
            
            if (amount < minAmount) {
                showAlert('danger', `Amount must be at least 10% of the total budget (₱${minAmount.toLocaleString('en-US', {minimumFractionDigits: 2})})`);
                fixedAmountInput.focus();
                return false;
            }
            
            if (amount > maxAmount) {
                showAlert('danger', `Amount cannot exceed the total budget of ₱${maxAmount.toLocaleString('en-US', {minimumFractionDigits: 2})}`);
                fixedAmountInput.value = maxAmount.toFixed(2);
                fixedAmountInput.focus();
                return false;
            }
        } else {
            const percentageBtn = document.querySelector('.percentage-btn.active');
            if (!percentageBtn) {
                showAlert('warning', 'Please select a budget percentage');
                return false;
            }
        }
        
        if (!currentFile) {
            showAlert('warning', 'Please upload proof of payment.');
            return false;
        }
        
        return true;
    }

    // Save button click handler
    saveInitialBudgetBtn.addEventListener('click', async function () {
        console.log('Save button clicked');
        const isFixedAmount = document.getElementById('fixedType').checked;
        const percentageBtn = document.querySelector('.percentage-btn.active');
        const projectId = document.querySelector('input[name="project_id"]')?.value;
        const originalBtnText = saveInitialBudgetBtn.innerHTML;

        // Validate form inputs
        if (!validateForm(isFixedAmount)) {
            console.log('Form validation failed');
            return;
        }
        
        // Additional validation
        if (!projectId) {
            console.log('Project ID not found');
            showAlert('danger', 'Error: Project ID not found');
            return;
        }
        
        // Additional validation for fixed amount
        if (isFixedAmount) {
            const fixedAmountInput = document.getElementById('fixedAmount');
            const amount = parseFloat(fixedAmountInput.value) || 0;
            const minAmount = parseFloat(fixedAmountInput.min) || 0;
            const maxAmount = parseFloat(fixedAmountInput.max) || 0;
            
            if (amount < minAmount || amount > maxAmount) {
                console.log('Amount validation failed:', { amount, minAmount, maxAmount });
                showAlert('danger', 'Please enter a valid amount within the allowed range.');
                return;
            }
        }

        // Get payment method
        const paymentMethod = document.getElementById('paymentMethod').value;
        if (!paymentMethod) {
            showAlert('warning', 'Please select a payment method.');
            return;
        }

        // Get selected bank details if payment method is bank transfer
        let paymentType = paymentMethod;
        if (paymentMethod === 'bank_transfer') {
            const selectedBankRadio = document.querySelector('input[name="selectedBankAccount"]:checked');
            if (selectedBankRadio) {
                // Get the bank name from the selected bank account
                const [bankName] = selectedBankRadio.value.split('|');
                if (bankName) {
                    paymentType = bankName.trim();
                }
            }
        }

        // Create FormData and set endpoint based on input type
        const formData = new FormData();
        formData.append('project_id', projectId);
        formData.append('payment_proof', currentFile);
        formData.append('payment_method', paymentType);
        
        // Set endpoint and add amount data - using absolute paths to avoid 404
        const endpoint = isFixedAmount ? '/Voltech-2/p_client/fixed_budget_handler.php' : '/Voltech-2/p_client/initial_budget_handler.php';
        
        if (isFixedAmount) {
            const fixedAmount = document.getElementById('fixedAmount').value;
            if (!fixedAmount || parseFloat(fixedAmount) <= 0) {
                showAlert('danger', 'Please enter a valid fixed amount.');
                return;
            }
            formData.append('fixed_amount', fixedAmount);
        } else {
            formData.append('percentage', percentageBtn.dataset.percentage);
        }

        // Log FormData contents
        for (let [key, value] of formData.entries()) {
            console.log(key, value);
        }

        // Show loading state
        saveInitialBudgetBtn.disabled = true;
        saveInitialBudgetBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving...';

        console.log('Sending request to initial_budget_handler.php');
        
        // Show confirmation dialog
        const confirmation = confirm('Are you sure you want to submit the initial budget? This action cannot be undone.');
        
        if (!confirmation) {
            // Restore button state if user cancels
            saveInitialBudgetBtn.disabled = false;
            saveInitialBudgetBtn.innerHTML = originalBtnText;
            return;
        }
        
        // Show loading state in the button
        saveInitialBudgetBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Processing...';
        
        // Show upload status message
        const uploadStatus = document.getElementById('uploadStatus');
        uploadStatus.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-2"></i> Uploading your payment proof. Please wait...</div>';
        
        // Add error handling for the fetch request
        console.log('Sending request to:', endpoint);
        console.log('Form data:', Array.from(formData.entries()));
        
        fetch(endpoint, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(async response => {
            console.log('Response status:', response.status);
            const responseText = await response.text();
            console.log('Raw response:', responseText);
            
            if (!response.ok) {
                let errorMsg = `Server error: ${response.status} ${response.statusText}`;
                try {
                    const errorData = JSON.parse(responseText);
                    errorMsg = errorData.message || errorMsg;
                } catch (e) {
                    errorMsg = responseText || errorMsg;
                }
                throw new Error(errorMsg);
            }
            
            try {
                return JSON.parse(responseText);
            } catch (e) {
                console.error('Failed to parse JSON:', responseText);
                throw new Error('Invalid JSON response from server');
            }
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                // Show success message
                uploadStatus.innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        ${data.message || 'Initial budget saved successfully!'}
                    </div>
                `;
                
                // Update UI to show success state
                saveInitialBudgetBtn.innerHTML = '<i class="fas fa-check me-1"></i> Saved Successfully';
                saveInitialBudgetBtn.classList.remove('btn-primary');
                saveInitialBudgetBtn.classList.add('btn-success');
                
                // Disable the button after successful save
                saveInitialBudgetBtn.disabled = true;
                
                // Reload the page after a delay to show the success message
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                const errorMsg = data.message || 'Failed to save initial budget. Please try again.';
                console.error('Server error:', errorMsg);
                uploadStatus.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        ${errorMsg}
                    </div>
                `;
                throw new Error(errorMsg);
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            uploadStatus.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    ${error.message || 'An error occurred while processing your request. Please try again.'}
                </div>
            `;
            
            // Only re-enable the button on error
            saveInitialBudgetBtn.disabled = false;
            saveInitialBudgetBtn.innerHTML = originalBtnText;
        });
    });

        // Function to check for pending payments
    function checkForPendingPayments() {
        const projectId = document.querySelector('input[name="project_id"]')?.value;
        if (!projectId) return;

        fetch(`/Voltech-2/p_client/check_pending_payment.php?project_id=${projectId}`)
            .then(response => response.json())
            .then(data => {
                const uploadStatus = document.getElementById('uploadStatus');
                if (!uploadStatus) return;
                
                if (data.status === 'pending') {
                    // Disable Save Initial Budget button
                    saveInitialBudgetBtn.disabled = true;
                    saveInitialBudgetBtn.innerHTML = 'Payment Pending Approval';
                    
                    // Disable Next button
                    const nextButtons = document.querySelectorAll('.next-step');
                    nextButtons.forEach(btn => {
                        btn.disabled = true;
                        btn.title = 'Please wait for payment approval to continue';
                    });
                    
                    // Show warning message
                    uploadStatus.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            A payment proof is already pending approval. Please wait for it to be processed before submitting another one or proceeding to the next step.
                        </div>
                    `;
                } else if (data.status === 'approved') {
                    // Disable Save Initial Budget button
                    saveInitialBudgetBtn.disabled = true;
                    saveInitialBudgetBtn.innerHTML = 'Payment Approved';
                    
                    // Show success message for approved payment
                    uploadStatus.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            Payment has been approved! You can now proceed to the next step.
                        </div>
                    `;
                    
                    // Enable Next button
                    const nextButtons = document.querySelectorAll('.next-step');
                    nextButtons.forEach(btn => {
                        btn.disabled = false;
                        btn.title = '';
                    });
                } else if (data.status === 'rejected') {
                    // Show error message for rejected payment
                    uploadStatus.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle me-2"></i>
                            Your payment has been rejected. Please upload a new payment proof.
                        </div>
                    `;
                    
                    // Re-enable the save button for new submission
                    saveInitialBudgetBtn.disabled = false;
                    saveInitialBudgetBtn.innerHTML = 'Save Initial Budget';
                }
            })
            .catch(error => {
                console.error('Error checking payment status:', error);
                const uploadStatus = document.getElementById('uploadStatus');
                if (uploadStatus) {
                    uploadStatus.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Error checking payment status. Please refresh the page and try again.
                        </div>
                    `;
                }
            });
    }

    // Initialize display on load
    updateInitialBudgetDisplay();
    
    // Check for pending payments when page loads
    checkForPendingPayments();
});

// Function to handle payment method change (must be in global scope)
window.handlePaymentMethodChange = function(method) {
    console.log('handlePaymentMethodChange called with method:', method);
    
    // Get all payment detail containers and hide them first
    const paymentContainers = document.querySelectorAll('.payment-details');
    paymentContainers.forEach(container => {
        container.classList.add('d-none');
    });
    
    // Get project ID
    const projectId = document.querySelector('input[name="project_id"]')?.value;
    if (!projectId) {
        console.error('Project ID not found');
        return;
    }
    
    // Handle GCash
    if (method === 'gcash') {
        const gcashDetails = document.getElementById('gcashDetails');
        const gcashInfo = document.getElementById('gcashInfo');
        
        if (!gcashDetails || !gcashInfo) {
            console.warn('GCash details container not found');
            return;
        }
        
        // Show loading state
        gcashInfo.innerHTML = `
            <div class="d-flex justify-content-center align-items-center">
                <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <span>Loading GCash details...</span>
            </div>
        `;
        
        // Show the container
        gcashDetails.classList.remove('d-none');
        
        // Fetch GCash details
        fetch(`/Voltech-2/p_client/get_gcash_details.php?project_id=${projectId}`)
            .then(response => response.json())
            .then(data => {
                console.log('GCash details response:', data);
                
                if (data.success && data.data) {
                    // Handle both array and object responses
                    const paymentData = Array.isArray(data.data) ? data.data[0] : data.data;
                    
                    gcashInfo.innerHTML = `
                        <div class="text-start">
                           
                            <p class="mb-2"><strong>Account Name:</strong> ${paymentData.account_name || 'N/A'}</p>
                            <p class="mb-2"><strong>GCash Number:</strong> ${paymentData.gcash_number || 'N/A'}</p>
                            <p class="mb-0"><strong>Project Manager:</strong> ${paymentData.name || 'N/A'}</p>
                            <p class="small text-muted mt-3 mb-0">
                                <i class="fas fa-info-circle me-1"></i>
                                Please send the payment to the GCash account above and upload the proof of payment.
                            </p>
                        </div>
                    `;
                } else {
                    gcashInfo.innerHTML = `
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ${data.message || 'No active GCash account found for the project manager.'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error fetching GCash details:', error);
                gcashInfo.innerHTML = `
                    <div class="alert alert-danger mb-0">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Failed to load GCash details. Please try again later.
                    </div>
                `;
            });
    } 
    // Handle Bank Transfer
    else if (method === 'bank_transfer') {
        const bankTransferDetails = document.getElementById('bankTransferDetails');
        const bankTransferInfo = document.getElementById('bankTransferInfo');
        
        if (!bankTransferDetails || !bankTransferInfo) {
            console.warn('Bank transfer details container not found');
            return;
        }
        
        // Show loading state
        bankTransferInfo.innerHTML = `
            <div class="d-flex justify-content-center align-items-center">
                <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <span>Loading bank details...</span>
            </div>
        `;
        
        // Show the container
        bankTransferDetails.classList.remove('d-none');
        
        // Fetch Bank Transfer details
        fetch(`/Voltech-2/p_client/get_bank_details.php?project_id=${projectId}`)
            .then(response => response.json())
            .then(data => {
                console.log('Bank details response:', data);
                
                if (data.success && data.data && data.data.bankAccounts && data.data.bankAccounts.length > 0) {
                    const bankAccounts = data.data.bankAccounts;
                    let bankDetailsHTML = `
                        <div class="text-start">
                           
                            <p class="mb-2"><strong>Project Manager:</strong> ${data.data.projectManager || 'N/A'}</p>
                            <p class="mb-3">Please transfer the payment to one of the following bank accounts:</p>
                    `;
                    
                    // Add radio button selection for bank accounts
                    bankAccounts.forEach((account, index) => {
                        const accountId = `bankAccount${index}`;
                        bankDetailsHTML += `
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="form-check">
                                        <input class="form-check-input bank-account-radio" 
                                               type="radio" 
                                               name="selectedBankAccount" 
                                               id="${accountId}"
                                               value="${account.bankName || ''}|${account.accountName || ''}|${account.accountNumber || ''}"
                                               ${index === 0 ? 'checked' : ''}>
                                        <label class="form-check-label w-100" for="${accountId}">
                                            <h6 class="card-subtitle mb-2 text-muted">
                                                <i class="fas fa-university me-2"></i>${account.bankName || 'Bank Account'}
                                            </h6>
                                            <div class="ms-4">
                                                <p class="mb-1"><strong>Account Name:</strong> ${account.accountName || 'N/A'}</p>
                                                <p class="mb-1"><strong>Account Number:</strong> ${account.accountNumber || 'N/A'}</p>
                                                ${account.contactNumber ? `<p class="mb-0"><strong>Contact:</strong> ${account.contactNumber}</p>` : ''}
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    // Add a hidden input to store the selected bank account
                    bankDetailsHTML += `
                        <input type="hidden" id="selectedBankAccountDetails" name="selected_bank_account">
                        <script>
                            // Update hidden input when selection changes
                            document.querySelectorAll('.bank-account-radio').forEach(radio => {
                                radio.addEventListener('change', function() {
                                    if (this.checked) {
                                        document.getElementById('selectedBankAccountDetails').value = this.value;
                                    }
                                });
                                
                                // Initialize the hidden input with the first selected value
                                if (radio.checked) {
                                    document.getElementById('selectedBankAccountDetails').value = radio.value;
                                }
                            });
                        </script>
                    `;
                    
                    bankDetailsHTML += `
                        <p class="small text-muted mt-2 mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            Please include the Project ID (${projectId}) in the transaction reference.
                        </p>
                    </div>`;
                    
                    bankTransferInfo.innerHTML = bankDetailsHTML;
                } else {
                    bankTransferInfo.innerHTML = `
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ${data.message || 'No active bank accounts found for the project manager.'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error fetching bank details:', error);
                bankTransferInfo.innerHTML = `
                    <div class="alert alert-danger mb-0">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Failed to load bank details. Please try again later.
                    </div>
                `;
            });
    }
} // Close the window.handlePaymentMethodChange function