document.addEventListener('DOMContentLoaded', function () {
    // DOM Elements
    const uploadBtn = document.getElementById('uploadBudgetBtn');
    const fileInput = document.getElementById('estimationPdfInput');
    const projectIdInput = document.getElementById('projectIdInput');
    const uploadStatus = document.getElementById('uploadStatus');
    const viewPdfBtn = document.getElementById('viewPdfBtn');
    const viewPdfContainer = document.getElementById('viewPdfContainer');
    const pdfViewer = document.getElementById('pdfViewer');
    const pdfViewerModal = new bootstrap.Modal(document.getElementById('pdfViewerModal'));
    
    // Store the current PDF path
    let currentPdfPath = '';

    // Function to show PDF in modal
    function showPdfViewer(pdfPath) {
        if (!pdfPath) return;
        
        currentPdfPath = pdfPath;
        
        // Remove any leading slashes or backslashes from the path
        const cleanPath = pdfPath.replace(/^[\\/]/, '');
        
        // Construct the full URL
        const baseUrl = window.location.origin;
        const fullPath = `${baseUrl}/Voltech-2/projectmanager/${cleanPath}`;
        
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
                // Try to parse JSON, but handle potential PHP warnings before JSON
                const jsonMatch = text.match(/\{[\s\S]*\}/);
                if (jsonMatch) {
                    try {
                        return JSON.parse(jsonMatch[0]);
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
                    if (data.budget.estimation_pdf) {
                        currentPdfPath = data.budget.estimation_pdf;
                        viewPdfBtn.disabled = false;
                        viewPdfBtn.className = 'btn btn-success';
                        viewPdfBtn.innerHTML = '<i class="fas fa-eye me-1"></i> View Uploaded PDF';
                        
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
                        currentPdfPath = '';
                        viewPdfBtn.disabled = true;
                        viewPdfBtn.className = 'btn btn-secondary';
                        viewPdfBtn.innerHTML = '<i class="fas fa-eye-slash me-1"></i> No PDF Uploaded';
                        document.getElementById('pdfStatus').innerHTML = '<i class="fas fa-info-circle text-muted me-1"></i> No PDF uploaded yet';
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
                        // Auto-show the PDF after upload
                        showPdfViewer(data.filePath);
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

