// Step 1 Blueprint Upload and Viewing Functionality

document.addEventListener('DOMContentLoaded', function() {
    // Floor Plan AJAX Upload for Step 1
    const uploadBtn = document.getElementById('uploadBtn');
    const step1NextBtn = document.getElementById('step1NextBtn');
    const uploadStatus = document.getElementById('uploadStatus');
    let uploadedFiles = []; // This array is no longer used for display, but kept for potential future use or debugging

    if (uploadBtn) {
        uploadBtn.addEventListener('click', function() {
            const form = document.getElementById('projectProcessForm');
            const planName = form.querySelector('[name="project_name"]').value;
            const fileInput = form.querySelector('[name="blueprint_files[]"]');
            
            if (!planName || fileInput.files.length === 0) {
                uploadStatus.innerHTML = '<div class="alert alert-danger">Project Name and at least one file are required.</div>';
                return;
            }
            
            // Upload each file
            const files = Array.from(fileInput.files);
            let uploadedCount = 0;
            let totalFiles = files.length;
            let hasErrors = false;
            
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
            uploadStatus.innerHTML = '<div class="alert alert-info">Uploading files...</div>';
            
            // Reset uploaded files array for this upload session
            uploadedFiles = [];
            
            files.forEach((file, index) => {
                const formData = new FormData();
                formData.append('planName', planName + (totalFiles > 1 ? ` (${index + 1})` : ''));
                formData.append('planImage', file);
                // Attach project_id if present
                const projectIdInput = form.querySelector('[name="project_id"]');
                if (projectIdInput) {
                    formData.append('project_id', projectIdInput.value);
                }
                
                fetch('save_floor_plan.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    uploadedCount++;
                    if (data.success) {
                        // Add to uploaded files array (for internal tracking, not display)
                        uploadedFiles.push({
                            name: file.name,
                            path: data.imagePath || 'uploads/floor_plans/' + data.fileName,
                            type: file.type,
                            size: file.size
                        });
                        
                        if (uploadedCount === totalFiles && !hasErrors) {
                            // Show success modal instead of alert
                            showSuccessModal();
                            // Reset upload button
                            uploadBtn.disabled = false;
                            uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload Files';
                            // Clear status
                            uploadStatus.innerHTML = '';
                            // Check blueprint approval status
                            checkBlueprintApprovalStatus();
                        }
                    } else {
                        hasErrors = true;
                        uploadStatus.innerHTML = '<div class="alert alert-danger">Error uploading ' + file.name + ': ' + data.message + '</div>';
                        uploadBtn.disabled = false;
                        uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload Files';
                        step1NextBtn.disabled = true; // Keep Next button disabled on error
                    }
                })
                .catch((error) => {
                    hasErrors = true;
                    uploadStatus.innerHTML = '<div class="alert alert-danger">An error occurred while uploading ' + file.name + '.</div>';
                    uploadBtn.disabled = false;
                    uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload Files';
                    step1NextBtn.disabled = true; // Keep Next button disabled on error
                });
            });
        });
    }

    // Blueprints Modal Functions
    const blueprintsModal = document.getElementById('blueprintsModal');
    if (blueprintsModal) {
        blueprintsModal.addEventListener('show.bs.modal', function() {
            loadBlueprints();
        });
    }

    // Search functionality
    const blueprintSearch = document.getElementById('blueprintSearch');
    if (blueprintSearch) {
        blueprintSearch.addEventListener('input', function() {
            filterBlueprints();
        });
    }

    // Check blueprint approval status on page load
    checkBlueprintApprovalStatus();
});

// Function to check if all blueprints are approved
function checkBlueprintApprovalStatus() {
    const step1NextBtn = document.getElementById('step1NextBtn');
    const fileInput = document.querySelector('[name="blueprint_files[]"]');
    const projectIdInput = document.getElementById('project_id');
    
    fetch('fetch_blueprints.php?project_id=' + projectIdInput.value)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.blueprints.length > 0) {
                const allApproved = data.blueprints.every(blueprint => blueprint.status === 'Approved');
                
                if (allApproved) {
                    step1NextBtn.disabled = false;
                    // Remove required attribute from file input
                    if (fileInput) {
                        fileInput.removeAttribute('required');
                    }
                    // Show approval status message
                    const uploadStatus = document.getElementById('uploadStatus');
                    if (uploadStatus) {
                        uploadStatus.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> All blueprints are approved! You can now proceed to the next step.</div>';
                    }
                } else {
                    step1NextBtn.disabled = true;
                    // Add required attribute back to file input
                    if (fileInput) {
                        fileInput.setAttribute('required', 'required');
                    }
                    // Clear any existing status messages
                    const uploadStatus = document.getElementById('uploadStatus');
                    if (uploadStatus && !uploadStatus.innerHTML.includes('Uploading')) {
                        uploadStatus.innerHTML = '';
                    }
                }
            } else {
                // No blueprints uploaded yet
                step1NextBtn.disabled = true;
                // Add required attribute to file input
                if (fileInput) {
                    fileInput.setAttribute('required', 'required');
                }
            }
        })
        .catch(error => {
            console.error('Error checking blueprint approval status:', error);
            step1NextBtn.disabled = true;
            // Add required attribute to file input on error
            if (fileInput) {
                fileInput.setAttribute('required', 'required');
            }
        });
}

// Function to show success modal
function showSuccessModal() {
    // Create modal HTML if it doesn't exist
    if (!document.getElementById('uploadSuccessModal')) {
        const modalHTML = `
            <div class="modal fade" id="uploadSuccessModal" tabindex="-1" aria-labelledby="uploadSuccessModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="uploadSuccessModalLabel">
                                <i class="fas fa-check-circle"></i> Upload Successful
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center">
                            <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                            <h5>Files Uploaded Successfully!</h5>
                            <p class="text-muted">Your blueprint files have been uploaded and are pending approval.</p>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Please wait for all blueprints to be approved before proceeding to the next step.
                            </div>
                        </div>
                        <div class="modal-footer justify-content-center">
                            <button type="button" class="btn btn-success" onclick="refreshPage()">
                                <i class="fas fa-refresh"></i> Continue
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }
    
    // Show the modal
    const successModal = new bootstrap.Modal(document.getElementById('uploadSuccessModal'));
    successModal.show();
}

// Function to refresh the page
function refreshPage() {
    location.reload();
}

// Global variables for blueprints
let allBlueprints = [];
let filteredBlueprints = [];
let currentPage = 1;
const blueprintsPerPage = 4;

function loadBlueprints() {
    const blueprintsList = document.getElementById('blueprintsList');
    const blueprintsLoading = document.getElementById('blueprintsLoading');
    const noBlueprints = document.getElementById('noBlueprints');

    blueprintsLoading.style.display = 'block';
    blueprintsList.innerHTML = '';
    noBlueprints.style.display = 'none';

    const projectIdInput = document.getElementById('project_id');
    
    fetch('fetch_blueprints.php?project_id=' + projectIdInput.value)
        .then(response => response.json())
        .then(data => {
            blueprintsLoading.style.display = 'none';
            
            if (data.success) {
                allBlueprints = data.blueprints;
                filteredBlueprints = [...allBlueprints];
                currentPage = 1; // Reset to first page
                displayBlueprints();
                // Check approval status after loading blueprints
                checkBlueprintApprovalStatus();
            } else {
                blueprintsList.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> ${data.message}
                        </div>
                    </div>
                `;
            }
        })
        .catch(error => {
            blueprintsLoading.style.display = 'none';
            blueprintsList.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> Failed to load blueprints
                    </div>
                </div>
            `;
        });
}

function displayBlueprints() {
    const blueprintsList = document.getElementById('blueprintsList');
    const noBlueprints = document.getElementById('noBlueprints');

    if (filteredBlueprints.length === 0) {
        blueprintsList.innerHTML = '';
        noBlueprints.style.display = 'block';
        return;
    }

    noBlueprints.style.display = 'none';
    blueprintsList.innerHTML = '';

    // Calculate pagination
    const totalPages = Math.ceil(filteredBlueprints.length / blueprintsPerPage);
    const startIndex = (currentPage - 1) * blueprintsPerPage;
    const endIndex = startIndex + blueprintsPerPage;
    const currentBlueprints = filteredBlueprints.slice(startIndex, endIndex);

    // Display current page blueprints
    currentBlueprints.forEach(blueprint => {
        const blueprintCard = document.createElement('div');
        blueprintCard.className = 'col-md-6 col-lg-3 mb-3';
        
        const fileContent = getFileContent(blueprint);
        
        blueprintCard.innerHTML = `
            <div class="card h-100 shadow-sm">
                <div class="card-body p-3">
                    <div class="text-center mb-2">
                        ${fileContent}
                    </div>
                    <h6 class="card-title text-truncate" title="${blueprint.name}">${blueprint.name}</h6>
                    <div class="text-center">
                        <small class="text-muted">${formatDate(blueprint.created_at)}</small>
                        <br>
                        <span class="badge ${getStatusBadgeClass(blueprint.status)}">${blueprint.status}</span>
                    </div>
                </div>
            </div>
        `;
        
        blueprintsList.appendChild(blueprintCard);
    });

    // Add pagination controls
    if (totalPages > 1) {
        const paginationContainer = document.createElement('div');
        paginationContainer.className = 'col-12 mt-3';
        
        const paginationHTML = `
            <div class="d-flex justify-content-center align-items-center">
                <button type="button" class="btn btn-outline-primary btn-sm me-2" onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <span class="mx-3">
                    Page ${currentPage} of ${totalPages}
                </span>
                <button type="button" class="btn btn-outline-primary btn-sm ms-2" onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        `;
        
        paginationContainer.innerHTML = paginationHTML;
        blueprintsList.appendChild(paginationContainer);
    }
}

function changePage(page) {
    const totalPages = Math.ceil(filteredBlueprints.length / blueprintsPerPage);
    
    if (page >= 1 && page <= totalPages) {
        currentPage = page;
        displayBlueprints();
    }
}

function getStatusBadgeClass(status) {
    switch(status) {
        case 'Approved':
            return 'bg-success';
        case 'Rejected':
            return 'bg-danger';
        case 'Pending':
        default:
            return 'bg-warning';
    }
}

function getFileContent(blueprint) {
    const ext = blueprint.file_extension.toLowerCase();
    if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
        return `<div class="cursor-pointer" onclick="viewBlueprint('${blueprint.image_path}', '${blueprint.name}', '${blueprint.file_extension}')" style="cursor: pointer;">
            <img src="../${blueprint.image_path}" class="img-fluid rounded" style="height: 200px; width: 100%; object-fit: cover;" alt="${blueprint.name}">
            <br><small class="text-muted mt-2">Click to enlarge</small>
        </div>`;
    } else if (ext === 'pdf') {
        return `<div class="d-flex align-items-center justify-content-center bg-light rounded" style="height: 200px;">
            <div class="text-center">
                <i class="fas fa-file-pdf fa-4x text-danger"></i>
                <br><small class="text-muted">PDF Document</small>
                <br><button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="viewBlueprint('${blueprint.image_path}', '${blueprint.name}', '${blueprint.file_extension}')">
                    <i class="fas fa-eye"></i> View
                </button>
            </div>
        </div>`;
    } else if (ext === 'dwg') {
        return `<div class="d-flex align-items-center justify-content-center bg-light rounded" style="height: 200px;">
            <div class="text-center">
                <i class="fas fa-drafting-compass fa-4x text-info"></i>
                <br><small class="text-muted">DWG File</small>
                <br><button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="viewBlueprint('${blueprint.image_path}', '${blueprint.name}', '${blueprint.file_extension}')">
                    <i class="fas fa-download"></i> Download
                </button>
            </div>
        </div>`;
    } else {
        return `<div class="d-flex align-items-center justify-content-center bg-light rounded" style="height: 200px;">
            <div class="text-center">
                <i class="fas fa-file fa-4x text-secondary"></i>
                <br><small class="text-muted">${blueprint.file_extension.toUpperCase()} File</small>
                <br><button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="viewBlueprint('${blueprint.image_path}', '${blueprint.name}', '${blueprint.file_extension}')">
                    <i class="fas fa-download"></i> Download
                </button>
            </div>
        </div>`;
    }
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

function viewBlueprint(imagePath, name, extension) {
    const ext = extension.toLowerCase();
    
    if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
        // Show image in modal
        document.getElementById('modalImage').src = '../' + imagePath;
        document.getElementById('imageViewModalLabel').textContent = name;
        const imageModal = new bootstrap.Modal(document.getElementById('imageViewModal'));
        imageModal.show();
    } else if (ext === 'pdf') {
        // Open PDF in new window
        window.open('../' + imagePath, '_blank');
    } else {
        // Download other file types
        const link = document.createElement('a');
        link.href = '../' + imagePath;
        link.download = name;
        link.click();
    }
}

function filterBlueprints() {
    const searchTerm = document.getElementById('blueprintSearch').value.toLowerCase();
    
    filteredBlueprints = allBlueprints.filter(blueprint => {
        const matchesSearch = blueprint.name.toLowerCase().includes(searchTerm);
        return matchesSearch;
    });
    
    currentPage = 1; // Reset to first page when filtering
    displayBlueprints();
}