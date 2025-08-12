document.addEventListener('DOMContentLoaded', function () {
    // DOM Elements
    const uploadBtn = document.getElementById('uploadBudgetBtn');
    const fileInput = document.getElementById('estimationPdfInput');
    const projectIdInput = document.getElementById('projectIdInput');
    const uploadStatus = document.getElementById('uploadStatus');
    const viewPdfBtn = document.getElementById('viewPdfBtn');
    const deletePdfBtn = document.getElementById('deletePdfBtn');
    const viewPdfContainer = document.getElementById('viewPdfContainer');
    const pdfViewer = document.getElementById('pdfViewer');
    const pdfViewerModal = new bootstrap.Modal(document.getElementById('pdfViewerModal'));
    const budgetInput = document.getElementById('budgetInput');
    const requestBudgetBtn = document.getElementById('requestBudgetBtn');
    
    // Store the current PDF path
    let currentPdfPath = '';

    // Function to show/hide delete button
    function toggleDeleteButton(show) {
        if (deletePdfBtn) {
            deletePdfBtn.style.display = show ? 'inline-block' : 'none';
        }
    }

    // Function to show PDF in modal
    function showPdfViewer(pdfPath) {
        if (!pdfPath) return;
        
        currentPdfPath = pdfPath;
        
        // Remove any leading slashes or backslashes from the path
        const cleanPath = pdfPath.replace(/^[\\/]/, '');
        
        // Construct the full URL - use the correct path
        const baseUrl = window.location.origin;
        const fullPath = `${baseUrl}/Voltech4.2/projectmanager/${cleanPath}`;
        
        console.log('PDF Path:', pdfPath);
        console.log('Full URL:', fullPath);
        
        // Set PDF source
        pdfViewer.src = fullPath;
        
        // Extract file name for the modal title
        const fileName = pdfPath.split(/[\\/]/).pop();
        
        // Update modal title
        document.getElementById('pdfViewerModalLabel').textContent = fileName || 'Budget Estimation PDF';
        
        // Show the modal
        pdfViewerModal.show();
    }

    // View PDF button click handler
    if (viewPdfBtn) {
        viewPdfBtn.addEventListener('click', function() {
            if (currentPdfPath) {
                showPdfViewer(currentPdfPath);
            } else {
                uploadStatus.innerHTML = '<div class="text-warning">No PDF has been uploaded yet.</div>';
            }
        });
    }

    // Delete PDF button in modal click handler
    const deletePdfModalBtn = document.getElementById('deletePdfModalBtn');
    if (deletePdfModalBtn) {
        deletePdfModalBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to delete the uploaded PDF?')) {
                const projectId = projectIdInput ? projectIdInput.value : 0;
                
                // Send delete request
                fetch('delete_budget_pdf.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `project_id=${projectId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close the modal
                        pdfViewerModal.hide();
                        
                        // Hide delete button and update status
                        toggleDeleteButton(false);
                        viewPdfBtn.disabled = true;
                        viewPdfBtn.className = 'btn btn-secondary';
                        viewPdfBtn.innerHTML = '<i class="fas fa-eye-slash me-1"></i> View Uploaded PDF';
                        document.getElementById('pdfStatus').innerHTML = '<i class="fas fa-info-circle text-muted me-1"></i> No PDF uploaded yet';
                        
                        // Clear current PDF path
                        currentPdfPath = '';
                        
                        // Show success message
                        uploadStatus.innerHTML = '<div class="text-success">PDF deleted successfully!</div>';
                        
                        // Re-enable upload functionality
                        if (uploadBtn) {
                            uploadBtn.disabled = false;
                            uploadBtn.innerHTML = '<i class="fas fa-upload me-1"></i> Upload';
                            uploadBtn.className = 'btn btn-primary';
                        }
                        if (fileInput) fileInput.disabled = false;
                        
                        // Refresh the page after a short delay to update the UI
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        alert(data.message || 'Error deleting PDF');
                    }
                })
                .catch(error => {
                    console.error('Delete error:', error);
                    alert('An error occurred while deleting PDF');
                });
            }
        });
    }

    // Delete PDF button click handler
    if (deletePdfBtn) {
        deletePdfBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to delete the uploaded PDF?')) {
                const projectId = projectIdInput ? projectIdInput.value : 0;
                
                // Send delete request
                fetch('delete_budget_pdf.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `project_id=${projectId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Hide delete button and update status
                        toggleDeleteButton(false);
                        viewPdfBtn.disabled = true;
                        viewPdfBtn.className = 'btn btn-secondary';
                        viewPdfBtn.innerHTML = '<i class="fas fa-eye-slash me-1"></i> View Uploaded PDF';
                        document.getElementById('pdfStatus').innerHTML = '<i class="fas fa-info-circle text-muted me-1"></i> No PDF uploaded yet';
                        
                        // Clear current PDF path
                        currentPdfPath = '';
                        
                        // Show success message
                        uploadStatus.innerHTML = '<div class="text-success">PDF deleted successfully!</div>';
                        
                        // Re-enable upload functionality
                        if (uploadBtn) {
                            uploadBtn.disabled = false;
                            uploadBtn.innerHTML = '<i class="fas fa-upload me-1"></i> Upload';
                            uploadBtn.className = 'btn btn-primary';
                        }
                        if (fileInput) fileInput.disabled = false;
                        
                        // Refresh the page after a short delay to update the UI
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        uploadStatus.innerHTML = `<div class="text-danger">${data.message || 'Error deleting PDF'}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Delete error:', error);
                    uploadStatus.innerHTML = '<div class="text-danger">An error occurred while deleting PDF</div>';
                });
            }
        });
    }

    // Check for existing PDF on page load
    function checkExistingPdf() {
        const projectId = projectIdInput ? projectIdInput.value : 0;
        if (!projectId) return;

        return fetch(`get_budget_status.php?project_id=${projectId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(text => {
                console.log('Raw response:', text);
                // Try to parse JSON, but handle potential PHP warnings before JSON
                const jsonMatch = text.match(/\{[\s\S]*\}/);
                if (jsonMatch) {
                    try {
                        const data = JSON.parse(jsonMatch[0]);
                        console.log('Parsed data:', data);
                        return data;
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        console.error('Response text:', text);
                        throw new Error('Invalid server response');
                    }
                }
                throw new Error('No valid JSON in response');
            })
            .then(data => {
                if (data && data.budget) {
                    console.log('Budget data:', data.budget);
                    if (data.budget.estimation_pdf) {
                        console.log('PDF path from database:', data.budget.estimation_pdf);
                        currentPdfPath = data.budget.estimation_pdf;
                        viewPdfBtn.disabled = false;
                        viewPdfBtn.className = 'btn btn-success';
                        viewPdfBtn.innerHTML = '<i class="fas fa-eye me-1"></i> View Uploaded PDF';
                        
                        // Show delete button when PDF exists
                        toggleDeleteButton(true);
                        
                        // Check status and update UI
                        const status = data.budget.status ? data.budget.status.toLowerCase() : '';
                        if (status === 'pending') {
                            document.getElementById('pdfStatus').innerHTML = '<i class="fas fa-clock text-warning me-1"></i> Waiting for approval';
                        } else if (status === 'Approved') {
                            document.getElementById('pdfStatus').innerHTML = '<i class="fas fa-check-circle text-success me-1"></i> Budget approved';
                        } else {
                            document.getElementById('pdfStatus').innerHTML = '<i class="fas fa-check-circle text-success me-1"></i> PDF ready for viewing';
                        }
                        
                        // Update UI based on status
                        updateUIForUploadStatus(status);
                    } else {
                        console.log('No PDF found in database');
                        currentPdfPath = '';
                        viewPdfBtn.disabled = true;
                        viewPdfBtn.className = 'btn btn-secondary';
                        viewPdfBtn.innerHTML = '<i class="fas fa-eye-slash me-1"></i> No PDF Uploaded';
                        document.getElementById('pdfStatus').innerHTML = '<i class="fas fa-info-circle text-muted me-1"></i> No PDF uploaded yet';
                        toggleDeleteButton(false);
                        updateUIForUploadStatus('');
                    }
                }
            })
            .catch(error => {
                console.error('Error checking for existing PDF:', error);
            });
    }

    // Function to update UI based on upload status
    function updateUIForUploadStatus(status) {
        const uploadButton = document.getElementById('uploadBudgetBtn');
        
        if (status === 'pending' || status === 'approved') {
            // Disable upload if already uploaded
            if (uploadButton) {
                uploadButton.disabled = true;
                uploadButton.innerHTML = '<i class="fas fa-check me-1"></i> Already Uploaded';
                uploadButton.className = 'btn btn-success';
            }
            if (fileInput) fileInput.disabled = true;
        }
    }

    // Initialize
    if (projectIdInput && viewPdfBtn) {
        // Always show the view button but disable it initially
        viewPdfBtn.disabled = true;
        
        // Handle budget request
        if (requestBudgetBtn && budgetInput) {
            requestBudgetBtn.addEventListener('click', function() {
                const budgetAmount = budgetInput.value.trim();
                const projectId = projectIdInput ? projectIdInput.value : 0;
                
                if (!budgetAmount) {
                    return; // Do nothing if empty
                }
                
                // Disable button during submission
                requestBudgetBtn.disabled = true;
                requestBudgetBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...';
                
                // Send request to server
                fetch('save_budget_amount.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `project_id=${projectId}&budget_amount=${budgetAmount}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        if (uploadStatus) {
                            uploadStatus.innerHTML = '<div class="alert alert-success">Budget amount saved successfully!</div>';
                        }
                        // Clear the input
                        budgetInput.value = '';
                        
                        // Refresh the page after a short delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        throw new Error(data.message || 'Failed to save budget amount');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (uploadStatus) {
                        uploadStatus.innerHTML = `<div class="alert alert-danger">${error.message || 'An error occurred while saving the budget amount'}</div>`;
                    }
                })
                .finally(() => {
                    // Re-enable the button
                    requestBudgetBtn.disabled = false;
                    requestBudgetBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Request Budget';
                });
            });
        }
        viewPdfBtn.innerHTML = '<i class="fas fa-eye-slash me-1"></i> View Uploaded PDF';
        // Check for existing PDF
        checkExistingPdf();
    }

    // Upload button click handler
    if (uploadBtn && fileInput && projectIdInput) {
        uploadBtn.addEventListener('click', function () {
            // Check if already uploaded
            if (document.querySelector('#uploadBudgetBtn').disabled) {
                uploadStatus.innerHTML = '<div class="alert alert-warning">A budget has already been uploaded and is pending approval.</div>';
                return;
            }
            
            // Check if file is selected
            const file = fileInput.files[0];
            if (!file) {
                uploadStatus.innerHTML = '<div class="alert alert-warning">Please select a PDF file first.</div>';
                return;
            }

            // Show loading state
            const originalBtnText = uploadBtn.innerHTML;
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Uploading...';

            const formData = new FormData();
            formData.append('estimation_pdf', file);
            formData.append('project_id', projectIdInput.value);

            fetch('save_budget_estimation.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text().then(text => {
                    // Try to parse JSON, but handle potential PHP warnings before JSON
                    const jsonMatch = text.match(/\{[\s\S]*\}/);
                    if (jsonMatch) {
                        try {
                            return JSON.parse(jsonMatch[0]);
                        } catch (e) {
                            console.error('JSON parse error:', e);
                            console.error('Response text:', text);
                        }
                    }
                    throw new Error('Invalid server response');
                });
            })
            .then(data => {
                if (data && data.success) {
                    uploadStatus.innerHTML = '<div class="text-success">Budget uploaded successfully!</div>';
                    if (data.filePath) {
                        currentPdfPath = data.filePath;
                        viewPdfContainer.classList.remove('d-none');
                        
                        // Show delete button after successful upload
                        toggleDeleteButton(true);
                        
                        // Update view button
                        viewPdfBtn.disabled = false;
                        viewPdfBtn.className = 'btn btn-success';
                        viewPdfBtn.innerHTML = '<i class="fas fa-eye me-1"></i> View Uploaded PDF';
                        
                        // Update status
                        document.getElementById('pdfStatus').innerHTML = '<i class="fas fa-check-circle text-success me-1"></i> PDF ready for viewing';
                        
                        // Auto-show the PDF after upload
                        showPdfViewer(data.filePath);
                        
                        // Refresh the page after a short delay to update the UI
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    }
                } else {
                    const errorMsg = data && data.message ? data.message : 'Upload failed. Please try again.';
                    uploadStatus.innerHTML = `<div class="text-danger">${errorMsg}</div>`;
                }
            })
            .catch(error => {
                console.error('Upload error:', error);
                uploadStatus.innerHTML = `<div class="text-danger">${error.message || 'An error occurred during upload.'}</div>`;
            })
            .finally(() => {
                // Reset button state
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = originalBtnText;
            });
        });
    }
});

