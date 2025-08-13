// Budget Documents Management
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('budgetDropZone');
    const fileInput = document.getElementById('budgetFiles');
    const browseBtn = document.getElementById('browseBudgetFilesBtn');
    const fileList = document.getElementById('budgetFileList');
    const uploadBtn = document.getElementById('uploadBudgetBtn');
    const uploadStatus = document.getElementById('uploadStatus');
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
        // Clear previous files (only one file allowed at a time)
        files = [];
        
        // Only take the first file
        const file = selectedFiles[0];
        
        // Check file type (only PDF)
        if (file.type !== 'application/pdf') {
            showAlert('Only PDF files are allowed', 'danger');
            return;
        }
        
        // Check file size (max 10MB)
        if (file.size > 10 * 1024 * 1024) {
            showAlert(`File ${file.name} is too large. Maximum size is 10MB.`, 'danger');
            return;
        }
        
        files = [file];
        updateFileList();
    }

    function updateFileList() {
        if (!fileList) return;
        
        if (files.length === 0) {
            fileList.innerHTML = '<div class="text-muted small">No files selected</div>';
            return;
        }

        fileList.innerHTML = files.map((file, index) => `
            <div class="file-item d-flex align-items-center mb-2">
                <i class="fas fa-file-pdf text-danger me-2"></i>
                <span class="file-name text-truncate" style="max-width: 300px;" title="${file.name}">${file.name}</span>
                <small class="text-muted ms-2">(${(file.size / 1024 / 1024).toFixed(2)} MB)</small>
                <button type="button" class="btn btn-link text-danger p-0 ms-auto" data-index="${index}" title="Remove file">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `).join('');

        // Add event listeners to remove buttons
        document.querySelectorAll('.file-item button').forEach(button => {
            button.addEventListener('click', function() {
                const index = parseInt(this.getAttribute('data-index'));
                files.splice(index, 1);
                updateFileList();
            });
        });
    }

    // Handle upload button click
    if (uploadBtn) {
        uploadBtn.addEventListener('click', async function() {
            if (files.length === 0) {
                showAlert('Please select at least one file to upload', 'warning');
                return;
            }

            const projectId = getProjectId();
            if (!projectId) {
                showAlert('Project ID is missing', 'danger');
                return;
            }

            // Disable button and show loading state
            const originalText = uploadBtn.innerHTML;
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Uploading...';
            
            if (uploadStatus) {
                uploadStatus.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Uploading files...';
                uploadStatus.className = 'text-primary small mt-2';
            }

            try {
                // Upload files one by one
                for (const file of files) {
                    const formData = new FormData();
                    formData.append('file', file);
                    formData.append('project_id', projectId);
                    formData.append('upload_budget_doc', '1');

                    const response = await fetch('upload_budget_document.php', {
                        method: 'POST',
                        body: formData
                    });

                    // First, get the response as text to check if it's valid JSON
                    const responseText = await response.text();
                    let result;
                    
                    try {
                        result = JSON.parse(responseText);
                    } catch (e) {
                        console.error('Failed to parse server response:', responseText);
                        throw new Error('Invalid response from server. Please check console for details.');
                    }
                    
                    if (!response.ok || !result.success) {
                        const errorMessage = result.message || 
                                          response.statusText || 
                                          `HTTP error! Status: ${response.status}`;
                        throw new Error(`Failed to upload file ${file.name}: ${errorMessage}`);
                    }
                }

                // Show success modal
                const successModal = new bootstrap.Modal(document.getElementById('budgetUploadSuccessModal'));
                const uploadedFilesList = document.getElementById('budgetUploadedFilesList');
                const modalElement = document.getElementById('budgetUploadSuccessModal');
                
                // Remove any existing event listeners to prevent duplicates
                const newModalElement = modalElement.cloneNode(true);
                modalElement.parentNode.replaceChild(newModalElement, modalElement);
                
                // Update the modal with the list of uploaded files
                const fileNames = Array.from(files).map(file => file.name);
                document.getElementById('budgetUploadedFilesList').innerHTML = fileNames.map(file => 
                    `<div><i class="fas fa-file-pdf text-danger me-2"></i>${file}</div>`
                ).join('');
                
                // Clear the file list
                files = [];
                updateFileList();
                
                // Add event listener for when modal is closed
                newModalElement.addEventListener('hidden.bs.modal', function onModalHidden() {
                    // Remove this event listener
                    newModalElement.removeEventListener('hidden.bs.modal', onModalHidden);
                    // Refresh the documents list
                    fetchBudgetDocuments();
                });
                
                // Show the modal
                new bootstrap.Modal(newModalElement).show();
                
            } catch (error) {
                console.error('Upload error:', error);
                showAlert(error.message || 'An error occurred while uploading files', 'danger');
            } finally {
                // Reset button state
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = originalText;
                
                if (uploadStatus) {
                    setTimeout(() => {
                        uploadStatus.textContent = '';
                        uploadStatus.className = 'text-muted small mt-2';
                    }, 3000);
                }
            }
        });
    }

    // Fetch and display budget document
    async function fetchBudgetDocuments() {
        const projectId = getProjectId();
        if (!projectId) {
            console.error('No project ID found');
            return;
        }

        const budgetFilesModal = document.getElementById('budgetFilesModal');
        if (!budgetFilesModal) {
            console.error('Budget files modal not found');
            return;
        }

        const modalBody = budgetFilesModal.querySelector('.modal-body');
        if (!modalBody) {
            console.error('Modal body not found');
            return;
        }

        // Show loading state
        modalBody.innerHTML = `
            <div class="text-center p-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading budget document...</p>
            </div>`;

        try {
            const response = await fetch(`fetch_budget_documents.php?project_id=${projectId}`);
            
            // First, get the response as text to check if it's valid JSON
            const responseText = await response.text();
            let data;
            
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error('Failed to parse server response:', responseText);
                throw new Error('Invalid response from server. Please check console for details.');
            }
            
            if (!response.ok) {
                const errorMessage = data?.message || response.statusText || `HTTP error! Status: ${response.status}`;
                throw new Error(`Failed to fetch documents: ${errorMessage}`);
            }

            if (!data.success) {
                throw new Error(data.message || 'Failed to fetch budget document');
            }

            if (data.documents.length === 0) {
                modalBody.innerHTML = `
                    <div class="text-center p-5">
                        <i class="fas fa-file-pdf fa-4x text-muted mb-3"></i>
                        <p class="text-muted">No budget document uploaded for this project.</p>
                    </div>`;
                return;
            }

            // Since we're only showing one document, take the first one
            const doc = data.documents[0];
            const fileSize = doc.file_size ? `(${(doc.file_size / 1024 / 1024).toFixed(2)} MB)` : '';
            const uploadDate = doc.upload_date ? new Date(doc.upload_date).toLocaleString() : '';
            const isVisible = doc.status === 'Show';
            
            let html = `
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-file-pdf fa-3x text-danger me-3"></i>
                            <div class="flex-grow-1">
                                <h5 class="mb-1">${doc.original_name || 'Budget Document'}</h5>
                                <div class="text-muted small">
                                    Uploaded: ${uploadDate}<br>
                                    Size: ${fileSize}
                                </div>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input visibility-toggle" type="checkbox" 
                                    role="switch" id="visibilityToggle" data-id="${doc.id}" 
                                    ${isVisible ? 'checked' : ''}>
                                <label class="form-check-label" for="visibilityToggle">
                                    ${isVisible ? 'Visible to Client' : 'Hidden from Client'}
                                </label>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="form-text">
                                <i class="fas ${isVisible ? 'fa-eye text-success' : 'fa-eye-slash text-muted'} me-1"></i>
                                ${isVisible ? 'Document is visible to client' : 'Document is hidden from client'}
                            </div>
                            <div class="btn-group">
                                <a href="${doc.file_path}" target="_blank" class="btn btn-outline-primary">
                                    <i class="fas fa-eye me-1"></i> View
                                </a>
                                <a href="${doc.file_path}" download class="btn btn-outline-secondary">
                                    <i class="fas fa-download me-1"></i> Download
                                </a>
                                <button class="btn btn-outline-danger delete-document" data-id="${doc.id}">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>`;
                        
            html += `
                    </div>
                </div>`;
                
            modalBody.innerHTML = html;

            // Add event listeners for delete buttons
            document.querySelectorAll('.delete-document').forEach(button => {
                button.addEventListener('click', function() {
                    const docId = this.getAttribute('data-id');
                    if (confirm('Are you sure you want to delete this document?')) {
                        deleteBudgetDocument(docId);
                    }
                });
            });

            // Add event listeners for visibility toggle
            document.querySelectorAll('.visibility-toggle').forEach(toggle => {
                toggle.addEventListener('change', function() {
                    const docId = this.getAttribute('data-id');
                    const isVisible = this.checked;
                    updateDocumentVisibility(docId, isVisible);
                });
            });

        } catch (error) {
            console.error('Error fetching budget documents:', error);
            modalBody.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Failed to load budget documents: ${error.message || 'Unknown error'}
                </div>`;
        }
    }

    // Update document visibility status
    async function updateDocumentVisibility(docId, isVisible) {
        try {
            const response = await fetch('update_document_visibility.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${docId}&status=${isVisible ? 'Show' : 'Not Show'}&project_id=${getProjectId()}`
            });

            const data = await response.json();
            
            if (data.success) {
                showAlert(`Document is now ${isVisible ? 'visible' : 'hidden'} to client`, 'success');
                // Refresh the documents list to show updated status
                fetchBudgetDocuments();
            } else {
                throw new Error(data.message || 'Failed to update document visibility');
            }
        } catch (error) {
            console.error('Error updating document visibility:', error);
            showAlert(error.message || 'An error occurred while updating document visibility', 'danger');
            // Refresh to reset the toggle if there was an error
            fetchBudgetDocuments();
        }
    }

    // Delete budget document
    async function deleteBudgetDocument(docId) {
    
        try {
            console.log('Attempting to delete document ID:', docId);
            console.log('Project ID:', getProjectId());
            
            const response = await fetch('delete_budget_document.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${docId}&project_id=${getProjectId()}`
            });

            // First get the response as text
            let responseText;
            try {
                responseText = await response.text();
                console.log('Raw response text:', responseText);
                
                // Try to parse as JSON
                const data = JSON.parse(responseText);
                console.log('Parsed response data:', data);
                
                if (!response.ok) {
                    throw new Error(data.message || `Server returned status ${response.status}`);
                }
                
                // If we get here, we have a successful JSON response
                if (data && data.success) {
                    // Show success alert
                    showAlert('Document deleted successfully', 'success');
                    
                    // Close the modal if it's open
                    const viewFilesModal = bootstrap.Modal.getInstance(document.getElementById('budgetFilesModal'));
                    if (viewFilesModal) {
                        viewFilesModal.hide();
                    }
                    
                    // Refresh the documents list
                    fetchBudgetDocuments();
                    
                    // Also update the upload button text if needed
                    const uploadBtn = document.getElementById('uploadBudgetBtn');
                    if (uploadBtn && !document.querySelector('#budgetFilesModal .card')) {
                        uploadBtn.innerHTML = '<i class="fas fa-upload me-1"></i> Upload Files';
                    }
                    
                    return;
                } else {
                    throw new Error(data ? (data.message || 'Failed to delete document') : 'Invalid server response');
                }
                
            } catch (e) {
                console.error('Error parsing response:', e);
                
                // If we have a response but it's not JSON, try to extract a meaningful error
                if (responseText) {
                    // Check if it's an HTML error page
                    const errorMatch = responseText.match(/<title>(.*?)<\/title>/i) || 
                                     responseText.match(/<h1.*?>(.*?)<\/h1>/i) ||
                                     responseText.match(/<b>(.*?)<\/b>/i);
                    
                    if (errorMatch && errorMatch[1]) {
                        throw new Error(`Server error: ${errorMatch[1].trim()}`);
                    }
                    
                    // If it's not HTML but still not JSON, show a portion of the response
                    if (responseText.length > 200) {
                        responseText = responseText.substring(0, 200) + '...';
                    }
                    throw new Error(`Unexpected server response: ${responseText}`);
                } else {
                    throw new Error('No response from server');
                }
            }
        } catch (error) {
            console.error('Error deleting document:', error);
            showAlert(error.message || 'An error occurred while deleting the document', 'danger');
        }
    }

    // Show alert message
    function showAlert(message, type = 'success') {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;
        
        // Create a container for alerts if it doesn't exist
        let alertContainer = document.getElementById('alertContainer');
        if (!alertContainer) {
            alertContainer = document.createElement('div');
            alertContainer.id = 'alertContainer';
            alertContainer.style.position = 'fixed';
            alertContainer.style.top = '20px';
            alertContainer.style.right = '20px';
            alertContainer.style.zIndex = '1100';
            alertContainer.style.maxWidth = '400px';
            document.body.appendChild(alertContainer);
        }
        
        // Create and append the new alert
        const alertDiv = document.createElement('div');
        alertDiv.innerHTML = alertHtml;
        alertContainer.appendChild(alertDiv);
        
        // Get the alert element
        const alertElement = alertDiv.firstElementChild;
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            // For Bootstrap 5.0.0, we'll just remove the alert manually
            const alert = new bootstrap.Alert(alertElement);
            alert.close();
            
            // Remove the alert from DOM after animation
            setTimeout(() => {
                if (alertElement.parentNode) {
                    alertElement.parentNode.removeChild(alertElement);
                }
                // Remove container if empty
                if (alertContainer && alertContainer.children.length === 0) {
                    alertContainer.parentNode.removeChild(alertContainer);
                }
            }, 150); // Match this with your CSS transition time
        }, 5000);
    }

    // Initialize event listeners when the budget files modal is shown
    const budgetFilesModal = document.getElementById('budgetFilesModal');
    if (budgetFilesModal) {
        budgetFilesModal.addEventListener('show.bs.modal', function() {
            fetchBudgetDocuments();
        });
    }

    // Initialize file list
    updateFileList();
});
