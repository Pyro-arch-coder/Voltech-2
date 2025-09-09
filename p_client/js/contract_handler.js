// Contract Handler - Drag and Drop Functionality
const contracts = [
    {
        id: 'client',
        dropZone: document.getElementById('clientDropZone'),
        fileInput: document.getElementById('clientContract'),
        browseBtn: document.getElementById('browseClientBtn'),
        uploadBtn: document.getElementById('uploadClientBtn'),
        viewBtn: document.getElementById('viewClientContractBtn'),
        fileInfo: document.getElementById('clientFileInfo'),
        progress: document.getElementById('clientProgress'),
        progressBar: document.querySelector('#clientProgress .progress-bar')
    }
];

// Prevent default drag behaviors
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    contracts.forEach(contract => {
        if (contract.dropZone) {
            contract.dropZone.addEventListener(eventName, preventDefaults, false);
        }
    });
    document.body.addEventListener(eventName, preventDefaults, false);
});

// Highlight drop zone when item is dragged over it
['dragenter', 'dragover'].forEach(eventName => {
    contracts.forEach(contract => {
        if (contract.dropZone) {
            contract.dropZone.addEventListener(eventName, () => highlight(contract), false);
        }
    });
});

['dragleave', 'drop'].forEach(eventName => {
    contracts.forEach(contract => {
        if (contract.dropZone) {
            contract.dropZone.addEventListener(eventName, () => unhighlight(contract), false);
        }
    });
});

// Handle dropped files
['drop'].forEach(eventName => {
    contracts.forEach(contract => {
        if (contract.dropZone) {
            contract.dropZone.addEventListener(eventName, (e) => handleDrop(e, contract), false);
        }
    });
});

// Initialize contract handlers
contracts.forEach(contract => {
    // Handle browse button click
    if (contract.browseBtn && contract.fileInput) {
        contract.browseBtn.addEventListener('click', (e) => {
            e.preventDefault();
            contract.fileInput.click();
        });
        contract.fileInput.addEventListener('change', (e) => handleFileSelect(e, contract));
    }
    
    // Handle upload button click
    if (contract.uploadBtn) {
        contract.uploadBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            uploadContract(contract);
        });
    }
});

// Helper functions
function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

function highlight(contract) {
    contract.dropZone.classList.add('border-primary');
}

function unhighlight(contract) {
    contract.dropZone.classList.remove('border-primary');
}

function handleDrop(e, contract) {
    preventDefaults(e);
    unhighlight(contract);
    const dt = e.dataTransfer;
    const files = dt.files;
    handleFiles(files, contract);
}

function handleFileSelect(e, contract) {
    const files = e.target.files;
    handleFiles(files, contract);
}

function handleFiles(files, contract) {
    if (files.length > 0) {
        const file = files[0];
        
        // Check if file is a PDF
        if (file.type !== 'application/pdf') {
            showAlert('Please upload a PDF file', 'danger');
            return;
        }

        // Check file size (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
            showAlert('File is too large. Maximum size is 5MB.', 'danger');
            return;
        }

        // Update UI
        contract.file = file;
        contract.fileInfo.textContent = `${file.name} (${formatFileSize(file.size)})`;
        
        if (contract.uploadBtn) {
            contract.uploadBtn.disabled = false;
        }
        
        if (contract.viewBtn) {
            contract.viewBtn.disabled = true; // Disable view until uploaded
        }
    }
}

async function uploadContract(contract) {
    if (!contract.file) {
        showAlert('Please select a file first', 'warning');
        return false;
    }

    const formData = new FormData();
    formData.append('contract_file', contract.file);
    
    const projectId = document.getElementById('projectIdInput')?.value;
    if (!projectId) {
        showAlert('Project ID not found', 'danger');
        return false;
    }
    formData.append('project_id', projectId);

    try {
        // Show loading state
        if (contract.uploadBtn) {
            contract.uploadBtn.disabled = true;
            contract.uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Uploading...';
        }
        if (contract.progress) {
            contract.progress.style.display = 'block';
        }

        const response = await fetch('client_contract.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            showAlert('Contract uploaded successfully! Reloading page...', 'success');
            
            // Update UI immediately
            const contractType = formData.get('contract_type');
            updateContractUI(contractType, result.filePath);
            
            // Reload the page after 1.5 seconds
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            throw new Error(result.message || 'Upload failed');
        }
    } catch (error) {
        console.error('Upload error:', error);
        showAlert(`Upload failed: ${error.message}`, 'danger');
    } finally {
        // Reset button state
        if (contract.uploadBtn) {
            contract.uploadBtn.disabled = false;
            contract.uploadBtn.innerHTML = '<i class="fas fa-upload me-1"></i> Upload';
        }
        // Hide progress bar after delay
        if (contract.progress) {
            setTimeout(() => {
                contract.progress.style.display = 'none';
            }, 1000);
        }
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function showAlert(message, type = 'info') {
    // Create toast container if it doesn't exist
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.style.position = 'fixed';
        toastContainer.style.top = '20px';
        toastContainer.style.right = '20px';
        toastContainer.style.zIndex = '1100';
        document.body.appendChild(toastContainer);
    }
    
    // Create toast element
    const toastId = 'toast-' + Date.now();
    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = 'toast align-items-center text-white bg-primary border-0';
    toast.role = 'alert';
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'danger' ? 'fa-exclamation-circle' : 'fa-info-circle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    // Initialize and show toast
    const bsToast = new bootstrap.Toast(toast, {
        autohide: true,
        delay: 5000
    });
    
    bsToast.show();
    
    // Remove toast from DOM after it's hidden
    toast.addEventListener('hidden.bs.toast', function() {
        toast.remove();
    });
}

function initializeContractHandler() {
    console.log('Contract handler initialized');
    
    // Prevent form submission on enter key
    const form = document.getElementById('projectProcessForm');
    if (form) {
        form.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    }
    
    // Initialize view button if file is already uploaded
    const viewBtn = document.getElementById('viewClientContractBtn');
    if (viewBtn) {
        viewBtn.disabled = true; // Disable by default until a file is uploaded
    }
    
    // Initialize upload button
    const uploadBtn = document.getElementById('uploadClientBtn');
    if (uploadBtn) {
        uploadBtn.disabled = true; // Disable until file is selected
    }
}

// Function to load and display contracts
async function loadContracts() {
    try {
        const projectId = document.getElementById('projectIdInput')?.value;
        if (!projectId) {
            console.error('Project ID not found');
            return;
        }

        const response = await fetch(`client_get_contract.php?project_id=${projectId}`);
        const data = await response.json();
        
        if (data.success && Array.isArray(data.contracts)) {
            data.contracts.forEach(contract => {
                // Store contract data in the container for later use
                const container = document.querySelector(`[data-contract-type="${contract.contract_type}"]`);
                if (container) {
                    container.setAttribute('data-contract-id', contract.id || '');
                }
                updateContractUI(contract.contract_type, contract.file_path, contract.id);
            });
        }
    } catch (error) {
        console.error('Error loading contracts:', error);
        showAlert('Failed to load contracts', 'danger');
    }
}

// Map contract types from database to UI elements
const contractTypeMap = {
    'original': 'original',
    'yoursigned': 'yoursigned',     // Project Manager Contract in UI
    'clientsigned': 'clientsigned'  // Your Signed Contract in UI
};

// Function to check if all required contracts are uploaded
function checkRequiredContracts() {
    const nextBtn = document.querySelector('button.next-step[data-next="4"]');
    const contractAlert = document.getElementById('contractAlert');
    
    if (!nextBtn || !contractAlert) return;
    
    const yourSigned = document.querySelector('[data-contract-type="yoursigned"]');
    const clientSigned = document.querySelector('[data-contract-type="clientsigned"]');
    
    const yourSignedHasFile = yourSigned && yourSigned.hasAttribute('data-file-path');
    const clientSignedHasFile = clientSigned && clientSigned.hasAttribute('data-file-path');
    
    // If either contract is missing, show alert and disable next button
    if (!yourSignedHasFile || !clientSignedHasFile) {
        nextBtn.disabled = true;
        contractAlert.classList.remove('d-none');
        
        // Update alert message based on which contracts are missing
        if (!yourSignedHasFile && !clientSignedHasFile) {
            contractAlert.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i> Please upload both Your Signed Contract and Client Signed Contract to proceed.';
        } else if (!yourSignedHasFile) {
            contractAlert.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i> Please upload Client Signed Contract to proceed.';
        } else {
            contractAlert.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i> Please upload Your Signed Contract to proceed.';
        }
    } else {
        // All required contracts are uploaded
        nextBtn.disabled = false;
        contractAlert.classList.add('d-none');
    }
}

// Function to update contract UI based on contract type, file path, and contract ID
function updateContractUI(contractType, filePath, contractId = '') {
    // Map the contract type to the correct UI element
    const uiContractType = contractTypeMap[contractType] || contractType;
    const container = document.querySelector(`[data-contract-type="${uiContractType}"]`);
    
    if (!container) {
        console.warn(`No container found for contract type: ${contractType} (mapped to: ${uiContractType})`);
        return;
    }

    const statusText = container.querySelector('.contract-status') || container.querySelector('.text-muted');
    const viewBtn = container.querySelector('.view-contract');
    
    if (filePath) {
        // Update status text
        if (statusText) {
            statusText.textContent = 'File available';
            statusText.className = 'text-success mb-2';
            // Store the file path in a data attribute for later use
            container.setAttribute('data-file-path', filePath);
            // Store the contract ID if provided
            if (contractId) {
                container.setAttribute('data-contract-id', contractId);
            }
        }
        
        // Enable and set up view button
        if (viewBtn) {
            viewBtn.disabled = false;
            viewBtn.setAttribute('data-contract-type', contractType);
            viewBtn.onclick = () => {
                const contractId = container.getAttribute('data-contract-id') || '';
                viewContract(contractType, filePath, contractId);
            };
        }
    } else {
        // No file available
        if (statusText) {
            statusText.textContent = 'No file available';
            statusText.className = 'text-muted mb-2';
            container.removeAttribute('data-file-path');
        }
        if (viewBtn) {
            viewBtn.disabled = true;
            viewBtn.removeAttribute('data-contract-type');
        }
    }
    
    // Function to check if all required contracts are uploaded
    function checkRequiredContracts() {
        const nextBtn = document.querySelector('button.next-step[data-next="4"]');
        const contractAlert = document.getElementById('contractAlert');
        
        if (!nextBtn || !contractAlert) return;
        
        const yourSigned = document.querySelector('[data-contract-type="yoursigned"]');
        const clientSigned = document.querySelector('[data-contract-type="clientsigned"]');
        
        const yourSignedHasFile = yourSigned && yourSigned.hasAttribute('data-file-path');
        const clientSignedHasFile = clientSigned && clientSigned.hasAttribute('data-file-path');
        
        // If either contract is missing, show alert and disable next button
        if (!yourSignedHasFile || !clientSignedHasFile) {
            nextBtn.disabled = true;
            contractAlert.classList.remove('d-none');
            
            // Update alert message based on which contracts are missing
            if (!yourSignedHasFile && !clientSignedHasFile) {
                contractAlert.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i> Please upload both Your Signed Contract and Client Signed Contract to proceed.';
            } else if (!yourSignedHasFile) {
                contractAlert.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i> Please upload Your Signed Contract to proceed.';
            } else {
                contractAlert.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i> Please upload Client Signed Contract to proceed.';
            }
        } else {
            // All required contracts are uploaded
            nextBtn.disabled = false;
            contractAlert.classList.add('d-none');
        }
    }
    
    // Check if all required contracts are uploaded
    if (contractType === 'yoursigned' || contractType === 'clientsigned') {
        checkRequiredContracts();
    }
}

// Function to view contract in modal
function viewContract(contractType, filePath, contractId = '') {
    // Map the contract type to the correct modal ID
    const modalSuffix = contractType === 'yoursigned' ? 'projectmanager' : contractType;
    const modalId = `${modalSuffix}PdfModal`;
    const viewerId = `${modalSuffix}PdfViewer`;
    const downloadId = `${modalSuffix}PdfDownload`;
    
    const modalElement = document.getElementById(modalId);
    const modal = new bootstrap.Modal(modalElement);
    const viewer = document.getElementById(viewerId);
    const downloadLink = document.getElementById(downloadId);
    
    // Set the contract ID in the delete button if this is the clientsigned contract
    if (contractType === 'clientsigned' && contractId) {
        const deleteBtn = modalElement.querySelector('.delete-contract');
        if (deleteBtn) {
            deleteBtn.setAttribute('data-contract-id', contractId);
        }
    }
    
    // Normalize the file path
    let normalizedPath = filePath;
    
    // If the path contains 'p_client', remove it
    if (normalizedPath.includes('p_client/')) {
        normalizedPath = normalizedPath.replace('p_client/', '');
    }
    
    // If the path already has projectmanager/ at the start, ensure it has the correct relative path
    if (normalizedPath.startsWith('projectmanager/')) {
        normalizedPath = '../' + normalizedPath;
    }
    // Handle paths that might be in uploads/contracts/ directly
    else if (normalizedPath.includes('uploads/contracts/')) {
        if (!normalizedPath.startsWith('../projectmanager/')) {
            normalizedPath = '../projectmanager/' + normalizedPath.split('uploads/contracts/').pop();
        }
    }
    // Handle bare filenames
    else if (!normalizedPath.includes('/')) {
        normalizedPath = '../projectmanager/uploads/contracts/' + normalizedPath;
    }
    
    console.log('Viewing contract:', { 
        contractType, 
        originalPath: filePath, 
        normalizedPath,
        resolvedPath: new URL(normalizedPath, window.location.href).href,
        contractId
    });
    
    if (viewer) {
        // Add timestamp to prevent caching issues
        viewer.src = normalizedPath + (normalizedPath.includes('?') ? '&' : '?') + 't=' + new Date().getTime();
    }
    
    if (downloadLink) {
        downloadLink.href = normalizedPath;
        downloadLink.download = normalizedPath.split('/').pop() || 'contract.pdf';
    }
    
    modal.show();
}

// Function to handle contract deletion
async function deleteContract(contractId) {
    if (!contractId) {
        console.error('No contract ID provided for deletion');
        showAlert('Error: Missing contract information', 'danger');
        return;
    }

    if (!confirm('Are you sure you want to delete this contract? This action cannot be undone.')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('id', contractId);
        
        const response = await fetch('client_delete_contract.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('Contract deleted successfully! Reloading page...', 'success');
            // Close the modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('clientsignedPdfModal'));
            if (modal) modal.hide();
            // Reload the page after a short delay
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            throw new Error(result.message || 'Failed to delete contract');
        }
    } catch (error) {
        console.error('Error deleting contract:', error);
        showAlert(error.message || 'Failed to delete contract', 'danger');
    }
}

// Initialize contract handler
function initializeContractHandler() {
    const projectId = document.getElementById('projectIdInput')?.value;
    if (!projectId) {
        console.error('Project ID not found');
        return;
    }

    // Set up delete button in the clientsigned modal
    const deleteBtn = document.querySelector('#clientsignedPdfModal .delete-contract');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', () => {
            // Get the contract ID from the data attribute
            const contractId = deleteBtn.getAttribute('data-contract-id');
            if (!contractId) {
                console.error('No contract ID found for deletion');
                showAlert('Error: Could not find contract information', 'danger');
                return;
            }
            deleteContract(contractId);
        });
    }

    // Load existing contracts
    loadContracts().then(() => {
        // Run initial validation after contracts are loaded
        checkRequiredContracts();
    });

    // Set up drag and drop for client contract
    const clientDropZone = document.getElementById('clientDropZone');
    if (clientDropZone) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            clientDropZone.addEventListener(eventName, preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            clientDropZone.addEventListener(eventName, () => highlight(clientDropZone), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            clientDropZone.addEventListener(eventName, () => unhighlight(clientDropZone), false);
        });

        clientDropZone.addEventListener('drop', (e) => handleDrop(e, { id: 'client' }), false);
    }

    // Set up file input change handler
    const clientContractInput = document.getElementById('clientContract');
    if (clientContractInput) {
        clientContractInput.addEventListener('change', (e) => {
            handleFileSelect(e, { id: 'client' });
        });
    }

    // Set up browse button
    const browseClientBtn = document.getElementById('browseClientBtn');
    if (browseClientBtn) {
        browseClientBtn.addEventListener('click', () => {
            clientContractInput?.click();
        });
    }
    
    // Add click handler for view buttons
    document.addEventListener('click', (e) => {
        if (e.target.closest('.view-contract')) {
            const button = e.target.closest('.view-contract');
            const contractType = button.getAttribute('data-contract-type');
            const container = button.closest('[data-contract-type]');
            const filePath = container?.getAttribute('data-file-path');
            
            if (filePath) {
                viewContract(contractType, filePath);
            }
        }
    });
}

// Initialize when DOM is loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeContractHandler);
} else {
    initializeContractHandler();
}
    // Additional initialization code can go here}