document.addEventListener('DOMContentLoaded', function() {
    console.log('Payment method handler loaded');
    
    const paymentMethodSelect = document.getElementById('paymentMethodSelect');
    const savePaymentMethodBtn = document.getElementById('savePaymentMethodBtn');
    const gcashDetails = document.getElementById('gcashDetails2');
    const bankTransferDetails = document.getElementById('bankTransferDetails2');
    
    console.log('Elements found:', {
        paymentMethodSelect: !!paymentMethodSelect,
        savePaymentMethodBtn: !!savePaymentMethodBtn,
        gcashDetails: !!gcashDetails,
        bankTransferDetails: !!bankTransferDetails
    });
    
    // Get project ID from URL
    const urlParams = new URLSearchParams(window.location.search);
    const projectId = urlParams.get('project_id');
    console.log('Project ID:', projectId);

    // Initialize save button as disabled
    if (savePaymentMethodBtn) {
        savePaymentMethodBtn.disabled = true;
    }

    // Helper function to get bank icon
    function getBankIcon(bankName) {
        const lowerName = bankName.toLowerCase();
        if (lowerName.includes('bpi')) return '<i class="fas fa-building fa-2x text-danger me-3"></i>';
        if (lowerName.includes('bdo')) return '<i class="fas fa-university fa-2x text-primary me-3"></i>';
        if (lowerName.includes('metrobank') || lowerName.includes('metro bank')) return '<i class="fas fa-landmark fa-2x text-red me-3"></i>';
        if (lowerName.includes('unionbank') || lowerName.includes('union bank')) return '<i class="fas fa-piggy-bank fa-2x text-purple me-3"></i>';
        return '<i class="fas fa-university fa-2x text-secondary me-3"></i>';
    }
    
    // Helper function to format account number with copy button
    function formatAccountNumber(number) {
        if (!number) return 'N/A';
        // Show full number with copy button
        return `
            <span class="d-inline-flex align-items-center">
                ${number}
                <button class="btn btn-sm btn-outline-secondary ms-2 py-0" onclick="copyToClipboard('${number}', this)" 
                        data-bs-toggle="tooltip" data-bs-placement="top" title="Copy to clipboard">
                    <i class="far fa-copy"></i>
                </button>
            </span>`;
    }
    
    // Function to copy text to clipboard
    function copyToClipboard(text, button) {
        navigator.clipboard.writeText(text).then(() => {
            // Show success tooltip
            const tooltip = new bootstrap.Tooltip(button, {
                title: 'Copied!',
                trigger: 'manual'
            });
            tooltip.show();
            
            // Change icon to checkmark temporarily
            const icon = button.querySelector('i');
            const originalClass = icon.className;
            icon.className = 'fas fa-check';
            
            // Reset after 2 seconds
            setTimeout(() => {
                tooltip.hide();
                icon.className = originalClass;
            }, 2000);
        }).catch(err => {
            console.error('Failed to copy: ', err);
        });
    }
    
    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
    
    // Function to handle payment option selection
    function selectPaymentOption(element, value) {
        // Remove highlight from all payment options
        document.querySelectorAll('.payment-option').forEach(option => {
            option.classList.remove('border-primary', 'border-2');
        });
        
        // Update radio button selection
        const radioInput = element.querySelector('input[type="radio"]');
        if (radioInput) {
            radioInput.checked = true;
            radioInput.dispatchEvent(new Event('change'));
        }
        
        // Highlight selected option
        element.classList.add('border-primary', 'border-2');
    }
    
    // Function to fetch payment details
    function fetchPaymentDetails(type) {
        if (!projectId) {
            console.error('No project ID available');
            return;
        }
        
        const endpoint = type === 'gcash' ? 'get_gcash_details.php' : 'get_bank_details.php';
        const targetElement = type === 'gcash' ? 'gcashInfo2' : 'bankTransferInfo2';
        
        console.log(`Fetching ${type} details from ${endpoint} for project ${projectId}`);
        
        fetch(`${endpoint}?project_id=${projectId}`)
            .then(response => {
                console.log(`${type} API response status:`, response.status);
                if (!response.ok) {
                    console.error(`${type} API error:`, response.statusText);
                    throw new Error('Network response was not ok');
                }
                return response.json().catch(e => {
                    console.error('Error parsing JSON response:', e);
                    throw new Error('Invalid JSON response from server');
                });
            })
            .then(data => {
                const container = document.getElementById(targetElement);
                if (container && data.success && data.data) {
                    const details = data.data;
                    let html = '';
                    
                    if (type === 'gcash') {
                        const isSelected = 'border-primary border-2';
                        const checked = 'checked';
                        html = `
                            <div class="payment-options">
                                <div class="payment-option mb-3 p-3 border rounded position-relative ${isSelected}" style="cursor: pointer;" onclick="selectPaymentOption(this, 'gcash')">
                                    <div class="form-check position-absolute top-0 end-0 p-2">
                                        <input class="form-check-input" type="radio" name="selectedPayment" value="gcash" ${checked}>
                                    </div>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-mobile-alt fa-2x text-success me-3"></i>
                                        <h5 class="mb-0">GCash</h5>
                                    </div>
                                    <div class="ps-4">
                                        <p class="mb-1"><i class="fas fa-phone-alt me-2 text-muted"></i> <strong>Number:</strong> ${details.gcash_number || 'N/A'}</p>
                                        <p class="mb-1"><i class="fas fa-user me-2 text-muted"></i> <strong>Name:</strong> ${details.account_name || 'N/A'}</p>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        // Check if we have bank accounts array
                        if (details.bankAccounts && details.bankAccounts.length > 0) {
                            // Create a section for each bank account
                            html = '<div class="bank-accounts">';
                            details.bankAccounts.forEach((bankAccount, index) => {
                                const isSelected = index === 0 ? 'border-primary border-2' : '';
                                const checked = index === 0 ? 'checked' : '';
                                const bankIcon = getBankIcon(bankAccount.bankName || '');
                                
                                html += `
                                    <div class="payment-option mb-3 p-3 border rounded position-relative ${isSelected}" style="cursor: pointer;" onclick="selectPaymentOption(this, 'bank-${index}')">
                                        <div class="form-check position-absolute top-0 end-0 p-2">
                                            <input class="form-check-input" type="radio" name="selectedPayment" value="bank-${index}" ${checked}>
                                        </div>
                                        <div class="d-flex align-items-center mb-2">
                                            ${bankIcon}
                                            <h5 class="mb-0">${bankAccount.bankName || 'Bank Account'} ${details.bankAccounts.length > 1 ? index + 1 : ''}</h5>
                                        </div>
                                        <div class="ps-4">
                                            <p class="mb-1"><i class="fas fa-university me-2 text-muted"></i> <strong>Bank:</strong> ${bankAccount.bankName || 'N/A'}</p>
                                            <p class="mb-1"><i class="fas fa-user me-2 text-muted"></i> <strong>Name:</strong> ${bankAccount.accountName || 'N/A'}</p>
                                            <p class="mb-1"><i class="fas fa-credit-card me-2 text-muted"></i> <strong>Account #:</strong> ${formatAccountNumber(bankAccount.accountNumber) || 'N/A'}</p>
                                            ${bankAccount.contactNumber ? `<p class="mb-1"><i class="fas fa-phone me-2 text-muted"></i> <strong>Contact:</strong> ${bankAccount.contactNumber}</p>` : ''}
                                        </div>
                                    </div>`;
                            });
                            html += '</div>';
                        } else {
                            html = '<div class="alert alert-warning">No bank account details found for this project manager.</div>';
                        }
                    }
                    
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<div class="alert alert-warning">No payment details found</div>';
                }
            })
            .catch(error => {
                console.error('Error fetching payment details:', error);
                const container = document.getElementById(targetElement);
                if (container) {
                    container.innerHTML = '<div class="alert alert-danger">Failed to load payment details. Please try again later.</div>';
                }
            });
    }

    // Handle payment method selection change
    if (paymentMethodSelect) {
        paymentMethodSelect.addEventListener('change', function() {
            const selectedValue = this.value;
            console.log('Payment method changed to:', selectedValue);
            
            // Hide all details first
            console.log('Hiding all payment details');
            if (gcashDetails) {
                console.log('Hiding GCash details');
                gcashDetails.classList.add('d-none');
            } else {
                console.error('GCash details element not found');
            }
            
            if (bankTransferDetails) {
                console.log('Hiding bank transfer details');
                bankTransferDetails.classList.add('d-none');
            } else {
                console.error('Bank transfer details element not found');
            }
            
            // Show relevant details based on selection
            if (selectedValue === 'gcash') {
                console.log('Showing GCash details');
                if (gcashDetails) {
                    gcashDetails.classList.remove('d-none');
                    console.log('Fetching GCash details...');
                    fetchPaymentDetails('gcash');
                } else {
                    console.error('Cannot show GCash details: element not found');
                }
            } else if (selectedValue === 'bank') {
                console.log('Showing bank transfer details');
                if (bankTransferDetails) {
                    bankTransferDetails.classList.remove('d-none');
                    console.log('Fetching bank details...');
                    fetchPaymentDetails('bank');
                } else {
                    console.error('Cannot show bank transfer details: element not found');
                }
            }
            
            // Enable/disable save button
            if (savePaymentMethodBtn) {
                savePaymentMethodBtn.disabled = !selectedValue;
            }
        });
    }

    // Function to show alert message
    function showAlert(message, type = 'success') {
        // Remove any existing alerts
        document.querySelectorAll('.payment-alert').forEach(alert => alert.remove());
        
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} mt-3 mb-0 payment-alert`;
        alertDiv.role = 'alert';
        alertDiv.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} me-2"></i>
            ${message}
        `;
        
        // Insert after the button
        if (savePaymentMethodBtn && savePaymentMethodBtn.parentNode) {
            savePaymentMethodBtn.parentNode.insertBefore(alertDiv, savePaymentMethodBtn.nextSibling);
        }
        
        // Remove alert after 5 seconds if it's a success message
        if (type === 'success') {
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
        
        return alertDiv;
    }

    // Function to validate file
    function validateFile(file) {
        const validTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!validTypes.includes(file.type)) {
            throw new Error('Invalid file type. Please upload a PDF, JPG, or PNG file.');
        }
        
        if (file.size > maxSize) {
            throw new Error('File size exceeds 5MB limit.');
        }
        
        return true;
    }

    // Handle save payment method
    if (savePaymentMethodBtn) {
        savePaymentMethodBtn.addEventListener('click', async function(e) {
            // Prevent default form submission
            if (e) e.preventDefault();
            
            const selectedMethod = paymentMethodSelect ? paymentMethodSelect.value : '';
            const proofOfPayment = document.getElementById('proofOfPayment');
            const file = proofOfPayment.files[0];
            
            if (!selectedMethod) {
                showAlert('Please select a payment method', 'warning');
                return;
            }
            
            if (!file) {
                showAlert('Please upload proof of payment', 'warning');
                return;
            }
            
            try {
                // Validate file
                validateFile(file);
            } catch (error) {
                showAlert(error.message, 'warning');
                return;
            }
            
            // Get project ID from URL
            const urlParams = new URLSearchParams(window.location.search);
            const projectId = urlParams.get('project_id');
            
            if (!projectId) {
                showAlert('Error: Missing project ID', 'danger');
                return;
            }
            
            // Get the unpaid amount from the PHP variable
            const amount = parseFloat(window.totalUnpaid || 0);
            
            if (isNaN(amount) || amount <= 0) {
                showAlert('Error: No pending payments to process', 'warning');
                return;
            }
            
            // Show loading state
            const originalText = savePaymentMethodBtn.innerHTML;
            savePaymentMethodBtn.disabled = true;
            savePaymentMethodBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
            
            try {
                // Get selected bank name if payment method is bank transfer
                let paymentType = selectedMethod;
                if (selectedMethod.startsWith('bank-')) {
                    const bankIndex = parseInt(selectedMethod.split('-')[1]);
                    const bankAccountElement = document.querySelector(`.payment-option input[value="${selectedMethod}"]`).closest('.payment-option');
                    if (bankAccountElement) {
                        const bankNameElement = bankAccountElement.querySelector('h5');
                        if (bankNameElement) {
                            // Extract bank name from the h5 element (removing 'Bank Account X' part if exists)
                            let bankName = bankNameElement.textContent.trim();
                            bankName = bankName.replace(/\s*Bank Account \d*$/, '').trim();
                            paymentType = `Bank Transfer (${bankName})`;
                        }
                    }
                }
                
                // Create form data with all fields
                const formData = new FormData();
                formData.append('project_id', projectId);
                formData.append('payment_type', paymentType);
                formData.append('amount', amount);
                
                // Append the file with the correct field name
                formData.append('proof_of_payment', file, file.name);
                
                // Log form data for debugging
                console.log('Sending form data:', {
                    project_id: projectId,
                    payment_type: selectedMethod,
                    amount: amount,
                    file: { name: file.name, size: file.size, type: file.type }
                });
                
                // Make API call to save payment method and upload proof
                const response = await fetch('save_payment_method.php', {
                    method: 'POST',
                    body: formData,
                    // Don't set Content-Type header - let the browser set it with the correct boundary
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                
                // Check if response is OK
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('Server responded with status:', response.status, 'Response:', errorText);
                    throw new Error(`Server error: ${response.status} - ${response.statusText}`);
                }
                
                // Try to parse JSON response
                let result;
                try {
                    result = await response.json();
                } catch (jsonError) {
                    console.error('Failed to parse JSON response:', jsonError);
                    const responseText = await response.text();
                    console.error('Response text:', responseText);
                    throw new Error('Invalid response from server. Please try again.');
                }
                
                if (result.success) {
                    showAlert('Payment method and proof of payment saved successfully!', 'success');
                    
                    // Disable the form after successful save
                    if (paymentMethodSelect) paymentMethodSelect.disabled = true;
                    if (proofOfPayment) proofOfPayment.disabled = true;
                    
                    // Force page reload after a short delay
                    setTimeout(() => {
                        window.location.href = window.location.href.split('?')[0] + '?project_id=' + projectId;
                    }, 1500);
                } else {
                    throw new Error(result.message || 'Failed to save payment method and proof of payment');
                }
            } catch (error) {
                console.error('Error saving payment method:', error);
                showAlert(`Error: ${error.message}`, 'danger');
                
                // Re-enable the save button on error
                savePaymentMethodBtn.disabled = false;
                savePaymentMethodBtn.innerHTML = originalText;
            }
        });
    }
});
