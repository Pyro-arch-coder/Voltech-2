// Permit Upload Functionality
const permits = [
    {
        id: 'lgu',
        dropZone: document.getElementById('lguDropZone'),
        fileInput: document.getElementById('lguClearance'),
        browseBtn: document.getElementById('browseLguBtn'),
        uploadBtn: document.getElementById('uploadLguBtn'),
        viewBtn: document.getElementById('viewLguBtn'),
        fileInfo: document.getElementById('lguFileInfo'),
        progress: document.getElementById('lguProgress'),
        progressBar: document.querySelector('#lguProgress .progress-bar')
    },
    {
        id: 'fire',
        dropZone: document.getElementById('fireDropZone'),
        fileInput: document.getElementById('firePermit'),
        browseBtn: document.getElementById('browseFireBtn'),
        uploadBtn: document.getElementById('uploadFireBtn'),
        viewBtn: document.getElementById('viewFireBtn'),
        fileInfo: document.getElementById('fireFileInfo'),
        progress: document.getElementById('fireProgress'),
        progressBar: document.querySelector('#fireProgress .progress-bar')
    },
    {
        id: 'zoning',
        dropZone: document.getElementById('zoningDropZone'),
        fileInput: document.getElementById('zoningClearance'),
        browseBtn: document.getElementById('browseZoningBtn'),
        uploadBtn: document.getElementById('uploadZoningBtn'),
        viewBtn: document.getElementById('viewZoningBtn'),
        fileInfo: document.getElementById('zoningFileInfo'),
        progress: document.getElementById('zoningProgress'),
        progressBar: document.querySelector('#zoningProgress .progress-bar')
    },
    {
        id: 'occupancy',
        dropZone: document.getElementById('occupancyDropZone'),
        fileInput: document.getElementById('occupancyPermit'),
        browseBtn: document.getElementById('browseOccupancyBtn'),
        uploadBtn: document.getElementById('uploadOccupancyBtn'),
        viewBtn: document.getElementById('viewOccupancyBtn'),
        fileInfo: document.getElementById('occupancyFileInfo'),
        progress: document.getElementById('occupancyProgress'),
        progressBar: document.querySelector('#occupancyProgress .progress-bar')
    },
    {
        id: 'barangay',
        dropZone: document.getElementById('barangayDropZone'),
        fileInput: document.getElementById('barangayClearance'),
        browseBtn: document.getElementById('browseBarangayBtn'),
        uploadBtn: document.getElementById('uploadBarangayBtn'),
        viewBtn: document.getElementById('viewBarangayBtn'),
        fileInfo: document.getElementById('barangayFileInfo'),
        progress: document.getElementById('barangayProgress'),
        progressBar: document.querySelector('#barangayProgress .progress-bar')
    }
];

// Load existing permit files when page loads
async function loadExistingPermits() {
    const projectId = document.getElementById('projectIdInputPermits')?.value;
    if (!projectId) {
        console.log('No project ID found, skipping permit loading');
        return;
    }
    
    try {
        console.log('Loading permits for project ID:', projectId);
        const response = await fetch(`get_permits.php?project_id=${projectId}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        console.log('Permits API response:', result);
        
        if (result.success && Array.isArray(result.permits)) {
            if (result.permits.length === 0) {
                console.log('No permits found for this project');
                return;
            }
            
            result.permits.forEach(permitData => {
                console.log('Processing permit data:', permitData);
                
                // Find the corresponding permit in our permits array
                const permit = permits.find(p => p.id === permitData.permit_type);
                
                if (permit) {
                    if (!permitData.file_path) {
                        console.warn(`No file path provided for permit type: ${permitData.permit_type}`, permitData);
                        return;
                    }
                    
                    // Create the full URL to the file
                    let fileUrl = permitData.file_path;
                    fileUrl = fileUrl.replace(/\\/g, '/');
                    
                    if (!fileUrl.startsWith('http') && !fileUrl.startsWith('/')) {
                        if (fileUrl.includes('projectmanager/')) {
                            fileUrl = '/' + fileUrl;
                        } else {
                            fileUrl = '../projectmanager/uploads/permits/' + fileUrl.split('/').pop();
                        }
                    }
                    
                    console.log(`Setting up permit ${permit.id} with URL:`, fileUrl);
                    
                    // Update permit object with file data
                    permit.file = {
                        name: permitData.file_name || `permit_${permit.id}.pdf`,
                        size: permitData.file_size || 0
                    };
                    permit.fileUrl = fileUrl;
                    
                    // Update UI
                    permit.fileInfo.textContent = permit.file.name;
                    permit.uploadBtn.disabled = false;
                    permit.uploadBtn.innerHTML = '<i class="fas fa-upload me-1"></i> Re-upload';
                    permit.viewBtn.disabled = false;
                    permit.dropZone.classList.add('border-success');
                    
                    // Set up view button click handler
                    setupViewButton(permit);
                } else {
                    console.warn('No matching permit found for type:', permitData.permit_type);
                }
            });
            
            // Check if all required permits are uploaded
            checkRequiredPermits();
            
        } else {
            console.error('Error in API response:', result.message || 'Unknown error');
        }
    } catch (error) {
        console.error('Error loading permits:', error);
        showAlert('Failed to load permits. Please refresh the page and try again.', 'danger');
    }
}

// Function to initialize view button click handler
function setupViewButton(permit) {
    // Remove any existing click handlers to prevent duplicates
    const newViewBtn = permit.viewBtn.cloneNode(true);
    permit.viewBtn.parentNode.replaceChild(newViewBtn, permit.viewBtn);
    permit.viewBtn = newViewBtn;
    
    // Enable the view button if there's a file URL
    if (permit.fileUrl) {
        permit.viewBtn.disabled = false;
    }
    
    // Add click handler to open the file in a new tab
    permit.viewBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        if (!permit.fileUrl) {
            console.error('No file URL available for permit:', permit.id);
            return;
        }
        
        // Open file in a new tab with browser's native viewer
        window.open(permit.fileUrl, '_blank');
    });
}

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Upload file to server
async function uploadFile(permit) {
    const file = permit.fileInput.files[0];
    const formData = new FormData();
    formData.append('file_photo', file);
    formData.append('project_id', document.getElementById('projectIdInputPermits').value);
    formData.append('permit_type', permit.id);
    
    // Show progress bar
    permit.progress.style.display = 'block';
    permit.progressBar.style.width = '0%';
    permit.uploadBtn.disabled = true;
    permit.uploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Uploading...';
    
    try {
        const response = await fetch('save_permits.php', {
            method: 'POST',
            body: formData,
            // Progress event for upload
            onUploadProgress: (progressEvent) => {
                const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                permit.progressBar.style.width = percentCompleted + '%';
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            // Update UI
            permit.file = file;
            permit.fileUrl = result.file_path || result.file_url;
            permit.fileInfo.textContent = `${file.name} (${formatFileSize(file.size)})`;
            permit.uploadBtn.disabled = false;
            permit.uploadBtn.innerHTML = '<i class="fas fa-upload me-1"></i> Re-upload';
            permit.viewBtn.disabled = false;
            permit.dropZone.classList.add('border-success');
            
            // Reset the file input to allow re-selection of the same file
            permit.fileInput.value = '';
            
            // Set up view button
            setupViewButton(permit);
            
            // Show success message
            showAlert('File uploaded successfully!', 'success');
            
            // Check if all required permits are uploaded
            checkRequiredPermits();
             // Refresh the page after a short delay
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            throw new Error(result.message || 'Failed to upload file');
        }
    } catch (error) {
        console.error('Upload error:', error);
        showAlert('Please choose file first before uploading', 'warning');
        permit.uploadBtn.disabled = true;
        permit.uploadBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> Choose File First';
        permit.progressBar.classList.remove('bg-warning');
        permit.progressBar.classList.add('bg-danger');
        
        // Refresh the page after a short delay to show the error message
        setTimeout(() => {
            window.location.reload();
        }, 1500);
    } finally {
        setTimeout(() => {
            permit.progress.style.display = 'none';
        }, 2000);
    }
}

// Show alert message
function showAlert(message, type = 'info') {
    // Create alert HTML
    const alertId = 'alert-' + Date.now();
    const alertHtml = `
        <div id="${alertId}" class="alert alert-${type} alert-dismissible fade show" role="alert">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    // Try to find a suitable container
    let container = document.querySelector('#alertContainer, .alert-container, .container, .container-fluid, body');
    
    // Create alert container if not found
    if (!container) container = document.body;
    
    // Create alert element
    const alertDiv = document.createElement('div');
    alertDiv.innerHTML = alertHtml;
    const alertElement = alertDiv.firstElementChild;
    
    // Position the alert based on container type
    if (container === document.body) {
        // For body, position fixed at top
        alertElement.style.position = 'fixed';
        alertElement.style.top = '20px';
        alertElement.style.right = '20px';
        alertElement.style.zIndex = '9999';
        alertElement.style.minWidth = '300px';
        container.appendChild(alertElement);
    } else {
        // For other containers, prepend to show at the top
        container.insertBefore(alertElement, container.firstChild);
    }
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const alertToClose = document.getElementById(alertId);
        if (alertToClose) {
            const closeButton = alertToClose.querySelector('.btn-close');
            if (closeButton) {
                closeButton.click();
            } else {
                // Fallback to direct removal
                alertToClose.remove();
            }
            
            // Remove from DOM after animation
            setTimeout(() => {
                if (alertToClose.parentNode) {
                    alertToClose.parentNode.removeChild(alertToClose);
                }
            }, 150);
        }
    }, 5000);
}

// Function to check required permits (not blocking next step)
function checkRequiredPermits() {
    const requiredPermits = ['lgu', 'fire', 'zoning', 'occupancy', 'barangay'];
    const uploadedPermits = permits.filter(p => p.fileUrl).map(p => p.id);
    const missingPermits = requiredPermits.filter(p => !uploadedPermits.includes(p));
    
    // Always enable next button
    const nextButton = document.querySelector('#step5 .next-step');
    if (nextButton) {
        nextButton.disabled = false;
    }
    
    // Show warning for missing permits but don't block
    const alertDiv = document.getElementById('permitsAlert');
    if (alertDiv) {
        if (missingPermits.length === 0) {
            alertDiv.classList.add('d-none');
        } else {
            alertDiv.classList.remove('d-none');
            alertDiv.innerHTML = `
                <i class="fas fa-info-circle me-2"></i>
                The following permits are recommended but not required: ${missingPermits.map(p => p.toUpperCase()).join(', ')}
            `;
        }
    }
    
    return missingPermits.length === 0;
}

// Initialize the page
function initializePermitUploads() {
    // Set up event listeners for each permit
    permits.forEach(permit => {
        if (!permit.dropZone || !permit.fileInput) return;
        
        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            permit.dropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        // Highlight drop zone when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            permit.dropZone.addEventListener(eventName, () => highlight(permit), false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            permit.dropZone.addEventListener(eventName, () => unhighlight(permit), false);
        });
        
        // Handle dropped files
        permit.dropZone.addEventListener('drop', (e) => handleDrop(e, permit), false);
        
        // Handle file selection via button
        permit.browseBtn.addEventListener('click', () => permit.fileInput.click(), false);
        
        // Handle file selection
        permit.fileInput.addEventListener('change', (e) => handleFileSelect(e, permit), false);
        
        // Handle upload button click
        permit.uploadBtn.addEventListener('click', () => uploadFile(permit), false);
    });
    
    // Load existing permits
    loadExistingPermits();
}

// Helper functions
function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

function highlight(permit) {
    permit.dropZone.classList.add('border-primary');
}

function unhighlight(permit) {
    permit.dropZone.classList.remove('border-primary');
}

function handleDrop(e, permit) {
    const dt = e.dataTransfer;
    const files = dt.files;
    handleFiles(files, permit);
}

function handleFileSelect(e, permit) {
    const files = e.target.files;
    handleFiles(files, permit);
}

function handleFiles(files, permit) {
    if (files.length > 0) {
        const file = files[0];
        const fileSize = formatFileSize(file.size);
        
        // Update UI
        permit.file = file;
        permit.fileInfo.textContent = `${file.name} (${fileSize})`;
        permit.uploadBtn.disabled = false;
        permit.viewBtn.disabled = true;
        
        // Update button text based on whether this is a re-upload
        if (permit.uploadBtn.textContent.includes('Re-upload')) {
            permit.uploadBtn.innerHTML = '<i class="fas fa-upload me-1"></i> Upload';
        }
        
        // Update drop zone styling
        permit.dropZone.classList.add('border-primary');
        permit.dropZone.classList.remove('border-success');
        
        // Show file info
        permit.fileInfo.textContent = `${file.name} (${fileSize})`;
    }
}

// Call initialization when DOM is fully loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initializePermitUploads();
    });
} else {
    // DOMContentLoaded has already fired
    initializePermitUploads();
}

// Export functions for testing
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        formatFileSize,
        checkRequiredPermits,
        initializePermitUploads
    };
}
