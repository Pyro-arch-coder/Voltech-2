// File upload and blueprint management
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('blueprintFiles');
    const browseBtn = document.getElementById('browseFilesBtn');
    const fileList = document.getElementById('fileList');
    let files = [];

    // Get project ID from URL or hidden input
    function getProjectId() {
        const urlParams = new URLSearchParams(window.location.search);
        let projectId = urlParams.get('project_id');
        
        if (!projectId) {
            const projectIdInput = document.querySelector('input[name="project_id"]');
            projectId = projectIdInput ? projectIdInput.value : null;
        }
        
        return projectId && !isNaN(projectId) ? parseInt(projectId) : null;
    }

    // Handle file selection
    if (browseBtn && fileInput) {
        browseBtn.addEventListener('click', () => fileInput.click());
    }
    
    // Handle drag and drop
    if (dropZone) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        // Handle dropped files
        dropZone.addEventListener('drop', handleDrop, false);
    }

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    function highlight() {
        dropZone.classList.add('drag-over');
    }

    function unhighlight() {
        dropZone.classList.remove('drag-over');
    }

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const droppedFiles = dt.files;
        handleFiles(droppedFiles);
    }

    // Handle file input change
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            handleFiles(this.files);
        });
    }

    function handleFiles(selectedFiles) {
        files = Array.from(selectedFiles);
        updateFileList();
    }

    function updateFileList() {
        if (!fileList) return;
        
        if (files.length === 0) {
            fileList.innerHTML = '';
            return;
        }

        fileList.innerHTML = files.map((file, index) => `
            <div class="file-item">
                <div class="file-info">
                    <i class="fas fa-file-${file.type.startsWith('image/') ? 'image' : 'pdf'} file-icon"></i>
                    <span class="file-name" title="${file.name}">${file.name}</span>
                </div>
                <button type="button" class="file-remove" data-index="${index}">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `).join('');

        // Add event listeners to remove buttons
        document.querySelectorAll('.file-remove').forEach(button => {
            button.addEventListener('click', function() {
                const index = parseInt(this.getAttribute('data-index'));
                files.splice(index, 1);
                updateFileList();
            });
        });
    }

    // Function to fetch and display blueprints
    async function fetchAndDisplayBlueprints() {
        const projectId = getProjectId();
        if (!projectId) return;

        const blueprintList = document.getElementById('blueprintList');
        if (!blueprintList) return;

        // Show loading state
        blueprintList.innerHTML = `
            <div class="text-center p-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading blueprints...</p>
            </div>`;

        try {
            const response = await fetch(`fetch_blueprints.php?project_id=${projectId}`);
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Failed to fetch blueprints');
            }

            if (data.blueprints.length === 0) {
                blueprintList.innerHTML = `
                    <div class="col-12 text-center p-5">
                        <i class="fas fa-file-upload fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No blueprints found for this project.</p>
                    </div>`;
                return;
            }

            // Clear and update blueprint list
            blueprintList.innerHTML = '';
            
            data.blueprints.forEach(blueprint => {
                const fileExt = (blueprint.file_extension || '').toLowerCase();
                const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExt);
                const isPdf = fileExt === 'pdf';
                
                const blueprintItem = document.createElement('div');
                blueprintItem.className = 'col-md-4 mb-4';
                blueprintItem.innerHTML = `
                    <div class="card h-100">
                        <div class="card-body p-2 blueprint-preview" style="cursor: pointer;" 
                             data-path="${blueprint.image_path}" 
                             data-type="${isImage ? 'image' : fileExt}">
                            ${isImage ? 
                                `<img src="${blueprint.image_path}" class="img-fluid rounded" alt="${blueprint.name}" style="height: 200px; object-fit: cover; width: 100%;">` :
                                isPdf ?
                                `<div class="text-center p-4 bg-light">
                                    <i class="fas fa-file-pdf fa-4x text-danger"></i>
                                    <p class="mt-2 mb-0">PDF Document</p>
                                </div>` :
                                `<div class="text-center p-4 bg-light">
                                    <i class="fas fa-file fa-4x text-secondary"></i>
                                    <p class="mt-2 mb-0">${fileExt.toUpperCase()} File</p>
                                </div>`
                            }
                        </div>
                        <div class="card-footer bg-white">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div class="text-truncate" title="${blueprint.name}">
                                    ${blueprint.name}
                                </div>
                                <button class="btn btn-sm btn-outline-danger delete-blueprint" data-id="${blueprint.id}" data-name="${blueprint.name}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    Uploaded: ${new Date(blueprint.created_at).toLocaleDateString()}
                                </small>
                                <span class="badge ${blueprint.status === 'Approved' ? 'bg-success' : 'bg-warning text-dark'}">
                                    ${blueprint.status || 'Pending'}
                                </span>
                            </div>
                        </div>
                    </div>
                `;
                blueprintList.appendChild(blueprintItem);
            });

            // Add event listeners for view/delete buttons
            addBlueprintEventListeners();

        } catch (error) {
            console.error('Error fetching blueprints:', error);
            blueprintList.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-danger m-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Failed to load blueprints: ${error.message || 'Unknown error'}
                    </div>
                </div>`;
        }
    }

    // Add event listeners for blueprint actions
    function addBlueprintEventListeners() {
        // Handle blueprint preview clicks (view/download)
        document.querySelectorAll('.blueprint-preview').forEach(preview => {
            preview.addEventListener('click', function(e) {
                // Don't trigger if clicking on a button inside the preview
                if (e.target.closest('button')) return;
                
                const path = this.getAttribute('data-path');
                const type = this.getAttribute('data-type');
                
                // For PDFs and images, open in a new tab
                if (type === 'pdf' || type === 'image') {
                    window.open(path, '_blank');
                } else {
                    // For other file types, trigger download
                    const link = document.createElement('a');
                    link.href = path;
                    link.download = path.split('/').pop() || 'download';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }
            });
        });

        // Delete blueprint
        document.querySelectorAll('.delete-blueprint').forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent triggering the parent click
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                if (confirm(`Are you sure you want to delete "${name}"?`)) {
                    deleteBlueprint(id);
                }
            });
        });
    }

    // View blueprint in modal
    function viewBlueprint(path, type) {
        const modal = new bootstrap.Modal(document.getElementById('viewBlueprintModal'));
        const modalBody = document.querySelector('#viewBlueprintModal .modal-body');
        
        if (type === 'image') {
            modalBody.innerHTML = `<img src="${path}" class="img-fluid" alt="Blueprint">`;
        } else if (type === 'pdf') {
            modalBody.innerHTML = `
                <div class="ratio ratio-16x9">
                    <iframe src="${path}" class="w-100" style="min-height: 70vh;"></iframe>
                </div>
            `;
        } else {
            modalBody.innerHTML = `
                <div class="text-center p-5">
                    <i class="fas fa-file fa-5x text-secondary mb-3"></i>
                    <p>This file type cannot be previewed.</p>
                    <a href="${path}" class="btn btn-primary" download>
                        <i class="fas fa-download me-2"></i>Download File
                    </a>
                </div>
            `;
        }
        
        modal.show();
    }

    // Delete blueprint
    async function deleteBlueprint(id) {
        try {
            const response = await fetch('delete_floor_plan.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${encodeURIComponent(id)}`
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Show success modal
                const successModal = new bootstrap.Modal(document.getElementById('deleteBlueprintSuccessModal'));
                successModal.show();
                
                // Refresh the page when modal is closed
                const modalElement = document.getElementById('deleteBlueprintSuccessModal');
                const refreshAfterDelete = document.getElementById('refreshAfterDelete');
                
                const refreshHandler = function() {
                    // Remove the event listener to prevent multiple refreshes
                    refreshAfterDelete.removeEventListener('click', refreshHandler);
                    // Refresh the page
                    window.location.reload();
                };
                
                refreshAfterDelete.addEventListener('click', refreshHandler);
            } else {
                throw new Error(result.message || 'Failed to delete blueprint');
            }
        } catch (error) {
            console.error('Error deleting blueprint:', error);
            showAlert(`Error: ${error.message}`, 'danger');
        }
    }

    // Handle upload button click
    const uploadBtn = document.getElementById('uploadBtn');
    if (uploadBtn) {
        uploadBtn.addEventListener('click', async function() {
            if (files.length === 0) {
                showAlert('Please select at least one file to upload.', 'warning');
                return;
            }
            
            // Get project ID from the hidden input or URL
            let projectId = document.getElementById('project_id')?.value;
            
            // If not in hidden input, try to get from URL
            if (!projectId) {
                const urlParams = new URLSearchParams(window.location.search);
                projectId = urlParams.get('project_id');
                
                if (!projectId) {
                    showAlert('Error: Could not determine project. Please refresh the page and try again.', 'danger');
                    return;
                }
            }
            
            // Validate project ID
            projectId = projectId.trim();
            if (!projectId || isNaN(projectId) || parseInt(projectId) <= 0) {
                showAlert('Error: Invalid project ID. Please refresh the page and try again.', 'danger');
                return;
            }
            
            // Show loading state
            const originalBtnText = uploadBtn.innerHTML;
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Uploading...';
            
            // Disable file input during upload
            if (fileInput) fileInput.disabled = true;
            
            try {
                const uploadPromises = [];
                const uploadedFiles = [];
                const errors = [];
                
                // Process each file
                for (const file of files) {
                    const formData = new FormData();
                    
                    // Use the filename without extension as the plan name
                    const fileName = file.name;
                    const planName = fileName.replace(/\.[^/.]+$/, ''); // Remove file extension
                    
                    // Append all required fields
                    formData.append('planImage', file);  // This matches the expected field name in PHP
                    formData.append('planName', planName);
                    formData.append('project_id', projectId);
                    
                    // Add file to upload queue
                    uploadPromises.push(
                        fetch('save_floor_plan.php', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                // Don't set Content-Type header - let the browser set it with the boundary
                            }
                        })
                        .then(async response => {
                            const result = await response.json();
                            if (!response.ok || !result.success) {
                                throw new Error(result.message || `Failed to upload ${file.name}`);
                            }
                            uploadedFiles.push(file.name);
                            return result;
                        })
                        .catch(error => {
                            console.error(`Upload failed for ${file.name}:`, error);
                            errors.push(`${file.name}: ${error.message}`);
                            return null;
                        })
                    );
                }
                
                // Wait for all uploads to complete
                await Promise.all(uploadPromises);
                
                // Show success modal and refresh after closing
                if (uploadedFiles.length > 0) {
                    const successModal = new bootstrap.Modal(document.getElementById('blueprintUploadSuccessModal'));
                    const uploadedFilesList = document.getElementById('uploadedFilesList');
                    
                    // Update the modal with the list of uploaded files
                    uploadedFilesList.innerHTML = uploadedFiles.map(file => 
                        `<div><i class="fas fa-file-pdf text-danger me-2"></i>${file}</div>`
                    ).join('');
                    
                    // Show the modal
                    successModal.show();
                    
                    // Refresh the page when modal is closed
                    const modalElement = document.getElementById('blueprintUploadSuccessModal');
                    modalElement.addEventListener('hidden.bs.modal', function () {
                        window.location.reload();
                    });
                }
                
                if (errors.length > 0) {
                    const errorMsg = `Failed to upload ${errors.length} file(s).\n${errors.join('\n')}`;
                    showAlert(errorMsg, 'warning');
                }
                
                // Clear the file list and reset the input
                if (uploadedFiles.length > 0) {
                    files = [];
                    updateFileList();
                    if (fileInput) fileInput.value = '';
                }
                
            } catch (error) {
                console.error('Upload error:', error);
                showAlert(`Error: ${error.message || 'An unexpected error occurred during upload.'}`, 'danger');
            } finally {
                // Reset button and input states
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = originalBtnText;
                if (fileInput) fileInput.disabled = false;
            }
        });
    }

    // Toggle sidebar
    const toggleButton = document.getElementById("menu-toggle");
    if (toggleButton) {
        toggleButton.onclick = function () {
            const wrapper = document.getElementById("wrapper");
            if (wrapper) {
                wrapper.classList.toggle("toggled");
            }
        };
    }
    
    // Helper function to show alerts
    function showAlert(message, type = 'info') {
        // Create or get alert container
        let alertContainer = document.getElementById('alertContainer');
        if (!alertContainer) {
            alertContainer = document.createElement('div');
            alertContainer.id = 'alertContainer';
            alertContainer.className = 'position-fixed top-0 end-0 m-3';
            alertContainer.style.zIndex = '1100';
            document.body.appendChild(alertContainer);
        }

        // Create alert element
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show mb-2`;
        alertDiv.role = 'alert';
        alertDiv.innerHTML = `
            <i class="${type === 'success' ? 'fas fa-check-circle' : type === 'danger' ? 'fas fa-exclamation-circle' : 'fas fa-info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        // Add to container
        alertContainer.appendChild(alertDiv);
        
        // Auto-remove alert after 5 seconds
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alertDiv);
            bsAlert.close();
            
            // Remove from DOM after animation
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 150);
        }, 5000);
    }
    
    // Initialize blueprint list when modal is shown
    const blueprintsModal = document.getElementById('blueprintsModal');
    if (blueprintsModal) {
        blueprintsModal.addEventListener('show.bs.modal', function() {
            fetchAndDisplayBlueprints();
        });
    }
});
