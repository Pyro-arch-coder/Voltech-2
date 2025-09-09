// Contract Upload Functionality
const contracts = [
    {
        id: 'yoursigned',
        dropZone: document.getElementById('yourDropZone'),
        fileInput: document.getElementById('yourContract'),
        browseBtn: document.getElementById('browseYourBtn'),
        uploadBtn: document.getElementById('uploadYourBtn'),
        viewBtn: document.getElementById('viewYourBtn'),
        fileInfo: document.getElementById('yourFileInfo'),
        progress: document.getElementById('yourProgress'),
        progressBar: document.querySelector('#yourProgress .progress-bar')
    },
    {
        id: 'clientsigned',
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

// Load existing contract files when page loads
async function loadExistingContracts() {
    const projectId = document.getElementById('projectIdInput')?.value;
    if (!projectId) {
        console.log('No project ID found, skipping contract loading');
        return;
    }
    
    try {
        console.log('Loading contracts for project ID:', projectId);
        const response = await fetch(`get_contracts.php?project_id=${projectId}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        console.log('Contracts API response:', result);
        
        if (result.success && Array.isArray(result.contracts)) {
            if (result.contracts.length === 0) {
                console.log('No contracts found for this project');
                return;
            }
            
            result.contracts.forEach(contractData => {
                console.log('Processing contract data:', contractData);
                
                // Find the corresponding contract in our contracts array
                const contract = contracts.find(c => c.id === contractData.contract_type);
                if (!contract) {
                    console.warn('No matching contract found for type:', contractData.contract_type);
                    return; // skip if not found in our contracts array
                }

                // Make sure we have a valid file path
                if (!contractData.file_path) {
                    console.warn(`No file path provided for contract type: ${contractData.contract_type}`, contractData);
                    return;
                }
                
                // Create the full URL to the file
                let fileUrl = contractData.file_path;
                fileUrl = fileUrl.replace(/\\/g, '/');
                if (!fileUrl.startsWith('http') && !fileUrl.startsWith('/')) {
                    if (fileUrl.includes('projectmanager/')) {
                        fileUrl = '/' + fileUrl;
                    } else {
                        fileUrl = '../projectmanager/uploads/contracts/' + fileUrl.split('/').pop();
                    }
                }
                
                console.log(`Setting up contract ${contract.id} with URL:`, fileUrl);
                
                // Update contract object with file data
                contract.file = {
                    name: contractData.file_name || `contract_${contract.id}.pdf`,
                    size: contractData.file_size || 0
                };
                contract.fileUrl = fileUrl;
                
                // Update UI but keep upload button enabled for re-uploads
                if (contract.fileInfo) contract.fileInfo.textContent = contract.file.name;
                contract.uploadBtn.disabled = false;
                contract.uploadBtn.innerHTML = '<i class="fas fa-upload me-1"></i> Re-upload';
                contract.viewBtn.disabled = false;
                contract.dropZone.classList.add('border-success');
                
                // Enable the delete button
                const deleteBtn = document.querySelector(`.delete-contract[data-contract-type="${contract.id}"]`);
                if (deleteBtn) {
                    deleteBtn.disabled = false;
                }
                
                // Set up view button click handler
                setupViewButton(contract);
            });
        } else {
            console.error('Error in API response:', result.message || 'Unknown error');
        }
    } catch (error) {
        console.error('Error loading contracts:', error);
        showAlert('Failed to load contracts. Please refresh the page and try again.', 'danger');
    }
}

// Function to initialize view button click handler
function setupViewButton(contract) {
    // Remove any existing click handlers to prevent duplicates
    const newViewBtn = contract.viewBtn.cloneNode(true);
    contract.viewBtn.parentNode.replaceChild(newViewBtn, contract.viewBtn);
    contract.viewBtn = newViewBtn;
    
    // Enable the view button if there's a file URL
    if (contract.fileUrl) {
        contract.viewBtn.disabled = false;
    }
    
    // Add click handler to open the appropriate modal
    contract.viewBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        if (!contract.fileUrl) {
            console.error('No file URL available for contract:', contract.id);
            return;
        }
        
        // Determine which modal to show based on contract type
        let modalId, viewerId, downloadId;
        switch(contract.id) {
            case 'yoursigned':
                modalId = 'yoursignedPdfModal';
                viewerId = 'yoursignedPdfViewer';
                downloadId = 'yoursignedPdfDownload';
                break;
            case 'clientsigned':
                modalId = 'clientsignedPdfModal';
                viewerId = 'clientsignedPdfViewer';
                downloadId = 'clientsignedPdfDownload';
                break;
            default:
                console.error('Unknown contract type:', contract.id);
                return;
        }
        
        const modalElement = document.getElementById(modalId);
        if (!modalElement) {
            console.error('Modal element not found:', modalId);
            return;
        }
        
        // Open PDF in a new tab with browser's native viewer
        if (contract.fileUrl) {
            window.open(contract.fileUrl, '_blank');
            return;
        }
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
async function uploadFile(contract) {
    if (!contract.file) return;

    const formData = new FormData();
    formData.append('contract_file', contract.file);
    formData.append('project_id', document.getElementById('projectIdInput').value);
    formData.append('contract_type', contract.id);

    try {
        // Show progress bar
        contract.progress.style.display = 'block';
        contract.progressBar.style.width = '0%';
        contract.uploadBtn.disabled = true;
        contract.uploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Uploading...';

        const response = await fetch('save_contract.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            contract.uploadBtn.innerHTML = '<i class="fas fa-check me-1"></i> Uploaded';
            contract.viewBtn.disabled = false;
            contract.dropZone.classList.add('border-success');
            contract.progressBar.classList.remove('bg-warning');
            contract.progressBar.classList.add('bg-success');
            showAlert('File uploaded successfully!', 'success');
            
            // Make sure we have a file object
            if (!contract.file) {
                contract.file = {
                    name: result.fileName || `contract_${contract.id}.pdf`,
                    size: contract.file?.size || 0
                };
            }
            
            // Store the file URL for viewing
            let fileUrl = result.filePath || result.fileUrl || '';
            fileUrl = fileUrl.replace(/\\/g, '/');
            if (fileUrl && !fileUrl.startsWith('http') && !fileUrl.startsWith('/')) {
                if (fileUrl.includes('projectmanager/')) {
                    fileUrl = '/' + fileUrl;
                } else {
                    fileUrl = '/projectmanager/uploads/contracts/' + fileUrl.split('/').pop();
                }
            }
            contract.fileUrl = fileUrl;
            
            console.log(`File uploaded successfully. URL set to: ${fileUrl}`);
            
            // Update the view button handler
            setupViewButton(contract);
            
            // Show success message
            showAlert('Contract uploaded successfully', 'success');
            
            // Check if both required contracts are uploaded
            checkRequiredContracts();
            
            // Refresh the page after a short delay
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            throw new Error(result.message || 'Upload failed');
        }
    } catch (error) {
        console.error('Upload error:', error);
        showAlert('Please choose file first before uploading', 'warning');
        contract.uploadBtn.disabled = true;
        contract.uploadBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> Choose File First';
        contract.progressBar.classList.remove('bg-warning');
        contract.progressBar.classList.add('bg-danger');
        setTimeout(() => {
            window.location.reload();
        }, 1500);
    } finally {
        setTimeout(() => {
            contract.progress.style.display = 'none';
        }, 2000);
    }
}

// Show alert message
function showAlert(message, type = 'info') {
    const alertId = 'alert-' + Date.now();
    const alertHtml = `
        <div id="${alertId}" class="alert alert-${type} alert-dismissible fade show" role="alert">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    let container = document.querySelector('#alertContainer, .alert-container, .container, .container-fluid, body');
    if (!container) container = document.body;
    const alertDiv = document.createElement('div');
    alertDiv.innerHTML = alertHtml;
    const alertElement = alertDiv.firstElementChild;
    if (container === document.body) {
        alertElement.style.position = 'fixed';
        alertElement.style.top = '20px';
        alertElement.style.right = '20px';
        alertElement.style.zIndex = '9999';
        alertElement.style.minWidth = '300px';
        container.appendChild(alertElement);
    } else {
        container.insertBefore(alertElement, container.firstChild);
    }
    setTimeout(() => {
        const alertToClose = document.getElementById(alertId);
        if (alertToClose) {
            const bsAlert = new bootstrap.Alert(alertToClose);
            bsAlert.close();
            setTimeout(() => {
                if (alertToClose.parentNode) {
                    alertToClose.parentNode.removeChild(alertToClose);
                }
            }, 150);
        }
    }, 5000);
}

// Initialize the page
function initializeContractUploads() {
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap is not loaded. Please make sure Bootstrap JS is included before this script.');
        return;
    }

    contracts.forEach(contract => {
        if (!contract.dropZone) return;
        setupViewButton(contract);
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            contract.dropZone.addEventListener(eventName, preventDefaults, false);
        });
        ['dragenter', 'dragover'].forEach(eventName => {
            contract.dropZone.addEventListener(eventName, () => highlight(contract), false);
        });
        ['dragleave', 'drop'].forEach(eventName => {
            contract.dropZone.addEventListener(eventName, () => unhighlight(contract), false);
        });
        contract.dropZone.addEventListener('drop', (e) => handleDrop(e, contract), false);
        contract.browseBtn.addEventListener('click', () => contract.fileInput.click());
        contract.fileInput.addEventListener('change', (e) => handleFileSelect(e, contract));
        contract.uploadBtn.addEventListener('click', () => uploadFile(contract));
    });

    loadExistingContracts();
}

// Helper functions
function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}
function highlight(contract) {
    contract.dropZone.classList.add('border-primary', 'bg-light');
}
function unhighlight(contract) {
    contract.dropZone.classList.remove('border-primary', 'bg-light');
}
function handleDrop(e, contract) {
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
        if (file.type === 'application/pdf') {
            contract.file = file;
            if (contract.fileInfo) contract.fileInfo.textContent = `${file.name} (${formatFileSize(file.size)})`;
            contract.uploadBtn.disabled = false;
            contract.viewBtn.disabled = true; // Disable view until uploaded
            contract.dropZone.classList.add('border-success');
            contract.dropZone.classList.remove('border-danger');
        } else {
            showAlert('Please upload a valid PDF file', 'danger');
            if (contract.fileInfo) contract.fileInfo.textContent = 'Invalid file type';
            contract.uploadBtn.disabled = true;
            contract.viewBtn.disabled = true;
            contract.dropZone.classList.add('border-danger');
        }
    }
}

// Function to check if required contracts are uploaded
function checkRequiredContracts() {
    const yourContract = contracts.find(c => c.id === 'yoursigned');
    const clientContract = contracts.find(c => c.id === 'clientsigned');
    const nextButton = document.querySelector('button.next-step[data-next="5"]');
    const alertElement = document.getElementById('contractAlert');
    if (!nextButton || !alertElement) return;
    const isYourContractUploaded = yourContract && yourContract.fileUrl;
    const isClientContractUploaded = clientContract && clientContract.fileUrl;
    if (isYourContractUploaded && isClientContractUploaded) {
        nextButton.disabled = false;
        alertElement.classList.add('d-none');
    } else {
        nextButton.disabled = true;
        alertElement.classList.remove('d-none');
    }
    let message = 'Please upload ';
    if (!isYourContractUploaded && !isClientContractUploaded) {
        message += 'Your Signed Contract and the Client Signed Contract';
    } else if (!isYourContractUploaded) {
        message += 'Your Signed Contract';
    } else {
        message += 'the Client Signed Contract';
    }
    message += ' to proceed.';
    const alertMessage = alertElement.querySelector('i').nextSibling;
    if (alertMessage) {
        alertMessage.textContent = message;
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initializeContractUploads();
        setTimeout(checkRequiredContracts, 500);
    });
} else {
    initializeContractUploads();
    setTimeout(checkRequiredContracts, 100);
}