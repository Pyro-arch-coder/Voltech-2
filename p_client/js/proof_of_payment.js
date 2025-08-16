// Proof of Payment JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // File selection functionality
    const proofOfPayment = document.getElementById('proofOfPayment');
    const browsePaymentBtn = document.getElementById('browsePaymentBtn');
    const paymentFileInfo = document.getElementById('paymentFileInfo');
    const paymentStatus = document.querySelector('.payment-status');
    const paymentActionSection = document.getElementById('paymentActionSection');
    
    if (browsePaymentBtn) {
        browsePaymentBtn.addEventListener('click', function() {
            proofOfPayment.click();
        });
    }
    
    if (proofOfPayment) {
        proofOfPayment.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                // Update file info
                paymentFileInfo.textContent = `Selected: ${file.name}`;
                paymentStatus.textContent = `File selected: ${file.name}`;
                paymentStatus.className = 'text-success';
                
                // Show action buttons when file is selected
                if (paymentActionSection) {
                    paymentActionSection.classList.remove('d-none');
                }
            }
        });
    }
    
    // Upload Proof of Payment Button
    const uploadPaymentBtn = document.getElementById('uploadPaymentBtn');
    if (uploadPaymentBtn) {
        uploadPaymentBtn.addEventListener('click', function() {
            const fileInput = document.getElementById('proofOfPayment');
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                
                // Show loading state
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Uploading...';
                
                // Create FormData for file upload
                const formData = new FormData();
                formData.append('proof_of_payment', file);
                formData.append('project_id', window.currentProjectId || getProjectIdFromUrl());
                
                // Upload file to server
                fetch('upload_proof_of_payment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.innerHTML = '<i class="fas fa-check me-1"></i> Uploaded';
                        this.className = 'btn btn-success flex-fill';
                        this.disabled = true;
                        
                        // Update status
                        paymentStatus.textContent = `File uploaded: ${file.name}`;
                        paymentStatus.className = 'text-success';
                        
                        // Show success message
                        showAlert('Proof of payment uploaded successfully!', 'success');
                        
                        // Refresh proof of payment data
                        loadProofOfPayment();
                    } else {
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-upload me-1"></i> Upload';
                        showAlert(data.message || 'Upload failed', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Upload error:', error);
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-upload me-1"></i> Upload';
                    showAlert('Upload failed. Please try again.', 'danger');
                });
            } else {
                showAlert('Please select a file first.', 'warning');
            }
        });
    }
    
    // Function to get project ID from URL
    function getProjectIdFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('project_id');
    }
    
    // Function to load existing proof of payment
    function loadProofOfPayment() {
        const projectId = window.currentProjectId || getProjectIdFromUrl();
        if (!projectId) return;
        
        fetch(`get_proof_of_payment.php?project_id=${projectId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    // Update UI with existing proof of payment
                    paymentFileInfo.textContent = `Uploaded: ${data.data.file_name}`;
                    paymentStatus.textContent = `File uploaded: ${data.data.file_name}`;
                    paymentStatus.className = 'text-success';
                    
                    // Show action buttons
                    if (paymentActionSection) {
                        paymentActionSection.classList.remove('d-none');
                    }
                    
                    // Update upload button to show it's already uploaded
                    if (uploadPaymentBtn) {
                        uploadPaymentBtn.innerHTML = '<i class="fas fa-check me-1"></i> Uploaded';
                        uploadPaymentBtn.className = 'btn btn-success flex-fill';
                        uploadPaymentBtn.disabled = true;
                    }
                }
            })
            .catch(error => {
                console.error('Error loading proof of payment:', error);
            });
    }
    
    // Load existing proof of payment on page load
    loadProofOfPayment();
    
    // View Proof of Payment Button
    const viewPaymentBtn = document.getElementById('viewPaymentBtn');
    if (viewPaymentBtn) {
        viewPaymentBtn.addEventListener('click', function() {
            const fileInput = document.getElementById('proofOfPayment');
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                
                // Create a preview modal or open file
                if (file.type.startsWith('image/')) {
                    // For images, show in modal
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        showImageModal(e.target.result, file.name);
                    };
                    reader.readAsDataURL(file);
                } else if (file.type === 'application/pdf') {
                    // For PDFs, open in new tab
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const newWindow = window.open();
                        newWindow.document.write(`
                            <html>
                                <head><title>${file.name}</title></head>
                                <body style="margin:0;padding:0;">
                                    <embed src="${e.target.result}" type="application/pdf" width="100%" height="100%">
                                </body>
                            </html>
                        `);
                    };
                    reader.readAsDataURL(file);
                }
            } else {
                showAlert('Please select a file first.', 'warning');
            }
        });
    }
    
    // Function to show image modal
    function showImageModal(imageSrc, fileName) {
        // Create modal HTML
        const modalHTML = `
            <div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${fileName}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center">
                            <img src="${imageSrc}" class="img-fluid" alt="${fileName}">
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('imagePreviewModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
        modal.show();
    }
    
    // Function to show alerts
    function showAlert(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        // Insert alert at the top of the proof of payment card
        const proofOfPaymentCard = document.querySelector('.card-header').parentNode;
        proofOfPaymentCard.insertBefore(alertDiv, proofOfPaymentCard.firstChild);
        
        // Auto-remove alert after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
});
