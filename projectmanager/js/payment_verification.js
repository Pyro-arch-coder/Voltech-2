document.addEventListener('DOMContentLoaded', function() {
    // Function to fetch and display payment verification details
    function fetchPaymentVerification(projectId) {
        if (!projectId) return;
        
        fetch(`get_payment_verification.php?project_id=${projectId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                updatePaymentVerificationUI(data);
            })
            .catch(error => {
                console.error('Error fetching payment verification:', error);
                // Show error message in the UI
                document.getElementById('paymentImageViewer').innerHTML = `
                    <div class="d-flex flex-column align-items-center justify-content-center h-100">
                        <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                        <p class="text-danger mb-0">Failed to load payment details</p>
                    </div>
                `;
            });
    }

    // Function to update the UI with payment verification details
    function updatePaymentVerificationUI(paymentData) {
        const paymentViewer = document.getElementById('paymentImageViewer');
        const paymentAmount = document.querySelector('.payment-amount');
        const verifyBtn = document.getElementById('verifyPaymentBtn');
        const rejectBtn = document.getElementById('rejectPaymentBtn');
        
        if (!paymentData || !paymentData.length) {
            paymentViewer.innerHTML = `
                <div class="d-flex flex-column align-items-center justify-content-center h-100">
                    <i class="fas fa-image fa-3x text-muted mb-3"></i>
                    <p class="text-muted mb-0">No payment proof available</p>
                </div>
            `;
            return;
        }

        // Get the latest payment
        const latestPayment = paymentData[0];
        
        // Update payment amount
        if (paymentAmount) {
            paymentAmount.textContent = latestPayment.amount ? `₱${parseFloat(latestPayment.amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}` : 'Not specified';
            paymentAmount.className = 'badge bg-success ms-2';
        }

        // Update payment type
        const paymentType = document.querySelector('.payment-type');
        if (paymentType) {
            paymentType.textContent = latestPayment.payment_type || 'N/A';
        }

        // Update upload date
        const uploadDate = document.querySelector('.upload-date');
        if (uploadDate && latestPayment.upload_date) {
            const date = new Date(latestPayment.upload_date);
            uploadDate.textContent = date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Update payment status
        const paymentStatus = document.querySelector('.payment-status');
        if (paymentStatus) {
            const statusText = latestPayment.status.charAt(0).toUpperCase() + latestPayment.status.slice(1);
            const statusClass = latestPayment.status === 'approved' ? 'success' : 
                              latestPayment.status === 'rejected' ? 'danger' : 'warning';
            paymentStatus.className = `badge bg-${statusClass}`;
            paymentStatus.textContent = statusText;
        }

        // Update action buttons container
        if (actionButtons) {
            if (latestPayment.status === 'approved' || latestPayment.status === 'verified') {
                actionButtons.innerHTML = `
                    <button type="button" class="btn btn-success" disabled>
                        <i class="fas fa-check-circle me-2"></i>Payment Verified
                    </button>
                `;
            } else if (latestPayment.status === 'rejected') {
                actionButtons.innerHTML = `
                    <button type="button" class="btn btn-danger" disabled>
                        <i class="fas fa-times-circle me-2"></i>Payment Rejected
                    </button>
                `;
            } else {
                actionButtons.innerHTML = `
                    <button type="button" class="btn btn-success me-2" id="verifyPaymentBtn">
                        <i class="fas fa-check-circle me-2"></i>Approve
                    </button>
                    <button type="button" class="btn btn-danger" id="rejectPaymentBtn">
                        <i class="fas fa-times-circle me-2"></i>Reject
                    </button>
                `;

                // Add click handlers
                document.getElementById('verifyPaymentBtn').onclick = () => verifyPayment(latestPayment.id, 'approve');
                document.getElementById('rejectPaymentBtn').onclick = () => {
                    if (confirm('Are you sure you want to reject this payment?')) {
                        verifyPayment(latestPayment.id, 'reject');
                    }
                };
            }
        }

        // Update image viewer
        if (latestPayment.file_path) {
            const fileExt = latestPayment.file_path.split('.').pop().toLowerCase();
            const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExt);
            
            if (isImage) {
                paymentViewer.innerHTML = `
                    <div class="text-center">
                        <img src="${latestPayment.file_path}" class="img-fluid" alt="Payment Proof" style="max-height: 300px;">
                        <p class="mt-2 text-muted">${latestPayment.file_name || 'Payment Proof'} (Uploaded on ${latestPayment.upload_date})</p>
                        <a href="${latestPayment.file_path}" class="btn btn-sm btn-outline-primary mt-2" target="_blank" download>
                            <i class="fas fa-download me-1"></i> Download
                        </a>
                    </div>
                `;
            } else {
                paymentViewer.innerHTML = `
                    <div class="d-flex flex-column align-items-center justify-content-center h-100">
                        <i class="fas fa-file-pdf fa-3x text-danger mb-3"></i>
                        <p class="mb-2">${latestPayment.file_name || 'Payment Proof'} (Uploaded on ${latestPayment.upload_date})</p>
                        <a href="${latestPayment.file_path}" class="btn btn-sm btn-outline-primary" target="_blank" download>
                            <i class="fas fa-download me-1"></i> Download File
                        </a>
                    </div>
                `;
            }
        }
    }

    // Function to verify/reject a payment
    function verifyPayment(paymentId, action = 'approve') {
        // Convert form data to URL-encoded string
        const params = new URLSearchParams();
        params.append('payment_id', paymentId);
        params.append('action', action);
        
        fetch('verify_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: params.toString()
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                showAlert(`Payment ${action === 'approve' ? 'approved' : 'rejected'} successfully!`, 'success');
                
                // Refresh the payment verification data
                const urlParams = new URLSearchParams(window.location.search);
                const projectId = urlParams.get('project_id');
                if (projectId) {
                    // Force a full page reload to ensure all data is fresh
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }
            } else {
                throw new Error(data.message || `Failed to ${action} payment`);
            }
        })
        .catch(error => {
            console.error(`Error ${action}ing payment:`, error);
            showAlert(`Failed to ${action} payment: ` + error.message, 'danger');
        });
    }

    // Helper function to show alerts
    function showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.role = 'alert';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        // Insert at the top of the card body
        const cardBody = document.querySelector('.card-body');
        if (cardBody) {
            cardBody.insertBefore(alertDiv, cardBody.firstChild);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alertDiv);
                bsAlert.close();
            }, 5000);
        }
    }

    // Initialize when the page loads
    const urlParams = new URLSearchParams(window.location.search);
    const projectId = urlParams.get('project_id');
    if (projectId) {
        fetchPaymentVerification(projectId);
    }
});
