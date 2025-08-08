document.addEventListener('DOMContentLoaded', function () {
    // Permit configurations
    const permitTypes = ['lgu', 'fire', 'zoning', 'occupancy', 'barangay'];
    const permitConfig = {
        lgu: {
            fileInput: 'lguClearance',
            uploadBtn: 'uploadLguBtn',
            viewBtn: 'viewLguBtn',
            modalId: 'lguModal',
            previewId: 'lguPermitPreview',
            downloadBtn: 'lguPermitDownload',
            deleteBtn: 'lguModal',
        },
        fire: {
            fileInput: 'firePermit',
            uploadBtn: 'uploadFireBtn',
            viewBtn: 'viewFireBtn',
            modalId: 'fireModal',
            previewId: 'firePermitPreview',
            downloadBtn: 'firePermitDownload',
            deleteBtn: 'fireModal',
        },
        zoning: {
            fileInput: 'zoningClearance',
            uploadBtn: 'uploadZoningBtn',
            viewBtn: 'viewZoningBtn',
            modalId: 'zoningModal',
            previewId: 'zoningPermitPreview',
            downloadBtn: 'zoningPermitDownload',
            deleteBtn: 'zoningModal',
        },
        occupancy: {
            fileInput: 'occupancyPermit',
            uploadBtn: 'uploadOccupancyBtn',
            viewBtn: 'viewOccupancyBtn',
            modalId: 'occupancyModal',
            previewId: 'occupancyPermitPreview',
            downloadBtn: 'occupancyPermitDownload',
            deleteBtn: 'occupancyModal',
        },
        barangay: {
            fileInput: 'barangayClearance',
            uploadBtn: 'uploadBarangayBtn',
            viewBtn: 'viewBarangayBtn',
            modalId: 'barangayModal',
            previewId: 'barangayPermitPreview',
            downloadBtn: 'barangayPermitDownload',
            deleteBtn: 'barangayModal',
        },
    };

    // Track file paths for each permit
    const permitFilePaths = {};
    const projectId = document.getElementById('projectIdInput').value;
    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
    // Utility: Show alert
    function showAlert(message, type = 'info') {
        // Insert at top of step5
        const stepContent = document.getElementById('step5');
        if (!stepContent) return;
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show mt-3`;
        alertDiv.role = 'alert';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        stepContent.insertBefore(alertDiv, stepContent.firstChild);
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.classList.remove('show');
                setTimeout(() => {
                    if (alertDiv.parentNode) alertDiv.remove();
                }, 150);
            }
        }, 5000);
    }

    function showPermitPreview(type) {
        const filePath = permitFilePaths[type];
        if (!filePath) return showAlert('No file uploaded yet.', 'warning');
        
        const config = permitConfig[type];
        const previewDiv = document.getElementById(config.previewId);
        const downloadBtn = document.getElementById(config.downloadBtn);
    
        // Remove any leading slashes, dots, or duplicate path segments
        let cleanPath = filePath.replace(/^[.\/\\]+/, '');
    
        // Remove any 'uploads/permits/' or 'permits/' from the beginning of the path (case insensitive)
        cleanPath = cleanPath.replace(/^(?:uploads[\/\\])?permits[\/\\]?/i, '');
    
        // Construct the final path based on your root (like showPdfViewer)
        // If your prod path is /Voltech-2/projectmanager/, update accordingly!
        const baseUrl = window.location.origin;
        const fullPath = `${baseUrl}/Voltech-2/projectmanager/uploads/permits/${cleanPath}`;
    
        // Set download link
        downloadBtn.href = fullPath;
        downloadBtn.download = cleanPath.split('/').pop();
    
        // Preview file in modal
        if (filePath.toLowerCase().endsWith('.pdf')) {
            previewDiv.innerHTML = `<iframe src="${fullPath}" class="w-100 h-100" style="min-height:70vh;"></iframe>`;
        } else if (/\.(jpg|jpeg|png|gif|webp)$/i.test(filePath)) {
            previewDiv.innerHTML = `<img src="${fullPath}" class="img-fluid rounded border" alt="Permit Preview" style="max-height:70vh;">`;
        } else {
            previewDiv.innerHTML = `<div class="alert alert-info"><i class="fas fa-file me-2"></i> File type not previewable. Please download to view.</div>`;
        }
    
        // Get the modal element and initialize
        const modalElement = document.getElementById(config.modalId);
        const modal = new bootstrap.Modal(modalElement);
        
        // Reload page when modal is hidden (closed)
        modalElement.addEventListener('hidden.bs.modal', function() {
            window.location.reload();
        });
        
        // Handle download click - reload after download starts
    
        if (downloadBtn) {
            downloadBtn.onclick = function(e) {
                // Let the download start, then reload after a short delay
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            };
        }
        
        // Setup delete button
        const deleteBtn = modalElement.querySelector('.delete-permit');
        if (deleteBtn) {
            deleteBtn.onclick = function() { deletePermit(type, modal); };
        }
        
        // Show the modal
        modal.show();
    }

    // Utility: Delete permit
    async function deletePermit(type, modalInstance) {
        if (!confirm('Are you sure you want to delete this permit? This action cannot be undone.')) {
            return;
        }
        
        try {
            const response = await fetch('delete_permits.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `project_id=${encodeURIComponent(projectId)}&permit_type=${encodeURIComponent(type)}`
            });
            
            const result = await response.json();
            
            if (result.success) {
                showAlert('Permit deleted successfully', 'success');
                permitFilePaths[type] = '';
                updatePermitUI(type, '');
                if (modalInstance && typeof modalInstance.hide === 'function') {
                    modalInstance.hide();
                }
                // Reload the page to ensure UI is in sync
                setTimeout(() => window.location.reload(), 1000);
            } else {
                throw new Error(result.message || 'Failed to delete permit');
            }
        } catch (error) {
            console.error('Delete error:', error);
            showAlert(error.message || 'An error occurred while deleting the permit', 'danger');
        }
    }

    // Utility: Update UI per permit type
    function updatePermitUI(type, filePath) {
        const config = permitConfig[type];
        const uploadBtn = document.getElementById(config.uploadBtn);
        const viewBtn = document.getElementById(config.viewBtn);
        const fileInput = document.getElementById(config.fileInput);

        if (filePath === 'uploading') {
            // Special case: Show uploading state
            if (uploadBtn) {
                uploadBtn.disabled = true;
                uploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Uploading...';
                uploadBtn.classList.remove('btn-primary', 'btn-success');
                uploadBtn.classList.add('btn-secondary');
            }
            if (viewBtn) viewBtn.disabled = true;
            if (fileInput) fileInput.disabled = true;
        } else if (filePath) {
            permitFilePaths[type] = filePath;
            if (viewBtn) {
                viewBtn.disabled = false;
                viewBtn.classList.remove('btn-secondary');
                viewBtn.classList.add('btn-info');
                viewBtn.onclick = () => showPermitPreview(type);
            }
            if (fileInput) fileInput.disabled = true;
            if (uploadBtn) {
                uploadBtn.disabled = true;
                uploadBtn.innerHTML = '<i class="fas fa-check me-1"></i> Uploaded';
                uploadBtn.classList.remove('btn-primary');
                uploadBtn.classList.add('btn-success');
            }
        } else {
            permitFilePaths[type] = '';
            if (viewBtn) {
                viewBtn.disabled = true;
                viewBtn.classList.remove('btn-info');
                viewBtn.classList.add('btn-secondary');
                viewBtn.onclick = null;
            }
            if (fileInput) {
                fileInput.value = '';
                fileInput.disabled = false;
            }
            if (uploadBtn) {
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = '<i class="fas fa-upload me-1"></i> Upload';
                uploadBtn.classList.remove('btn-success');
                uploadBtn.classList.add('btn-primary');
            }
        }
    }

    // Upload permit (AJAX)
    async function uploadPermit(type) {
        const config = permitConfig[type];
        const fileInput = document.getElementById(config.fileInput);
        const uploadBtn = document.getElementById(config.uploadBtn);

        const file = fileInput.files[0];
        if (!file) return showAlert('Please select a file to upload.', 'warning');

        // Loading state
        const originalBtnText = uploadBtn.innerHTML;
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Uploading...';
        
        // Show loading state in the UI immediately
        updatePermitUI(type, 'uploading');

        // Prepare form data
        const formData = new FormData();
        formData.append('file_photo', file);
        formData.append('project_id', projectId);
        formData.append('permit_type', type);

        try {
            const response = await fetch('save_permits.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data && data.success) {
                // Show success alert
                showAlert('Permit uploaded successfully!', 'success');
                
                // Update UI immediately
                updatePermitUI(type, data.file_path || '');
                
                // Reload the page after a short delay to show the success message
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                throw new Error(data.message || 'Upload failed. Please try again.');
            }
        } catch (error) {
            console.error('Upload error:', error);
            showAlert(error.message || 'An error occurred during upload.', 'danger');
        } finally {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = originalBtnText;
        }
    }

    // Load permit statuses from backend
    async function loadPermitStatus() {
        try {
            const response = await fetch(`get_permits.php?project_id=${projectId}`);
            const data = await response.json();
            
            if (data && data.success && data.permits) {
                // Reset all permit UIs
                permitTypes.forEach(type => updatePermitUI(type, ''));
                
                // Update UIs for existing permits
                data.permits.forEach(permit => {
                    if (permit.permit_type && permit.file_path) {
                        updatePermitUI(permit.permit_type, permit.file_path);
                    }
                });
            }
        } catch (error) {
            console.error('Error loading permit status:', error);
            showAlert('Failed to load permits. Please refresh the page.', 'danger');
        }
    }

    // Initialize event listeners
    function initEventListeners() {
        // Set up upload buttons and file inputs for each permit type
        permitTypes.forEach(type => {
            const config = permitConfig[type];
            const uploadBtn = document.getElementById(config.uploadBtn);
            const fileInput = document.getElementById(config.fileInput);
            const viewBtn = document.getElementById(config.viewBtn);
            
            // Set up upload button
            if (uploadBtn) {
                uploadBtn.addEventListener('click', () => uploadPermit(type));
            }
            
            // Handle file selection changes
            if (fileInput && viewBtn) {
                fileInput.addEventListener('change', (e) => {
                    const file = e.target.files[0];
                    if (file) {
                        // Enable view button for local preview
                        viewBtn.disabled = false;
                        viewBtn.onclick = () => {
                            const objectUrl = URL.createObjectURL(file);
                            const previewDiv = document.getElementById(config.previewId);
                            const modal = new bootstrap.Modal(document.getElementById(config.modalId));
                            
                            // Set up preview based on file type
                            if (file.type === 'application/pdf') {
                                previewDiv.innerHTML = `<iframe src="${objectUrl}" class="w-100 h-100" style="min-height:70vh;"></iframe>`;
                            } else if (file.type.startsWith('image/')) {
                                previewDiv.innerHTML = `<img src="${objectUrl}" class="img-fluid rounded border" style="max-height:70vh;">`;
                            } else {
                                previewDiv.innerHTML = '<div class="alert alert-info">File type not previewable. Please upload to view.</div>';
                            }
                            
                            // Show the modal
                            modal.show();
                            
                            // Clean up the object URL when the modal is closed
                            document.getElementById(config.modalId).addEventListener('hidden.bs.modal', function cleanup() {
                                URL.revokeObjectURL(objectUrl);
                                document.getElementById(config.modalId).removeEventListener('hidden.bs.modal', cleanup);
                            }, { once: true });
                        };
                    } else {
                        viewBtn.disabled = true;
                        viewBtn.onclick = null;
                    }
                });
            }
        });
    }

    // Initialize the page
    async function init() {
        initEventListeners();
        
        // Set up view buttons for each permit type
        permitTypes.forEach(type => {
            const viewBtn = document.getElementById(permitConfig[type].viewBtn);
            if (viewBtn) {
                viewBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const permitPath = permitFilePaths[type];
                    if (permitPath) {
                        showPermitPreview(type);
                    } else {
                        showAlert(`No ${type} permit has been uploaded yet.`, 'warning');
                    }
                });
            }
        });
        
        // Load existing permit statuses
        if (projectId) {
            await loadPermitStatus();
        } else {
            console.error('Project ID not found');
        }
    }

    // Start the application
    init();
});

