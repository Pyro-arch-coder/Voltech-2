document.addEventListener('DOMContentLoaded', function () {
    // DOM Elements
    const projectIdInput = document.getElementById('projectIdInput');
    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
    
    // Initialize modals for each contract type
    const modals = {
        'original': new bootstrap.Modal(document.getElementById('originalPdfModal')),
        'yoursigned': new bootstrap.Modal(document.getElementById('yoursignedPdfModal')),
        'clientsigned': new bootstrap.Modal(document.getElementById('clientsignedPdfModal'))
    };
    
    // Contract type configurations
    const contractConfigs = {
        'original': {
            uploadBtn: document.getElementById('uploadOriginalBtn'),
            fileInput: document.getElementById('originalContract'),
            viewBtn: document.getElementById('viewOriginalBtn')
        },
        'yoursigned': {
            uploadBtn: document.getElementById('uploadYourBtn'),
            fileInput: document.getElementById('yourContract'),
            viewBtn: document.getElementById('viewYourBtn')
        },
        'clientsigned': {
            uploadBtn: document.getElementById('uploadClientBtn'),
            fileInput: document.getElementById('clientContract'),
            viewBtn: document.getElementById('viewClientContractBtn')
        }
    };

    // Contract file paths (for reference only, not used for viewing)
    const contractFilePaths = {
        original: '',
        yoursigned: '',
        clientsigned: ''
    };


    

    // Function to update UI for a contract type
    function updateContractUI(contractType, filePath) {
        const config = contractConfigs[contractType];
        if (!config) return;

        if (filePath) {
            contractFilePaths[contractType] = filePath;
            
            // Safely update view button if it exists
            if (config.viewBtn) {
                try {
                    config.viewBtn.disabled = false;
                    if (config.viewBtn.classList) {
                        config.viewBtn.classList.remove('btn-secondary');
                        config.viewBtn.classList.add('btn-info');
                    }
                    
                    // Set click handler for view button
                    config.viewBtn.onclick = (e) => {
                        e.preventDefault();
                        // Format the title based on contract type
                        let title = '';
                        switch(contractType) {
                            case 'original':
                                title = 'Original Contract';
                                break;
                            case 'yoursigned':
                                title = 'Your Signed Contract';
                                break;
                            case 'clientsigned':
                                title = 'Client Signed Contract';
                                break;
                            default:
                                title = 'Contract PDF';
                        }
                        showPdfViewer(filePath, title);
                    };
                } catch (e) {
                    console.error('Error updating view button:', e);
                }
            }
            
            // Disable file input if it exists
            if (config.fileInput) {
                try {
                    config.fileInput.disabled = true;
                } catch (e) {
                    console.error('Error disabling file input:', e);
                }
            }
            
            // Update upload button if it exists
            if (config.uploadBtn) {
                try {
                    config.uploadBtn.disabled = true;
                    config.uploadBtn.innerHTML = '<i class="fas fa-check me-1"></i> Uploaded';
                    if (config.uploadBtn.classList) {
                        config.uploadBtn.classList.remove('btn-primary');
                        config.uploadBtn.classList.add('btn-success');
                    }
                } catch (e) {
                    console.error('Error updating upload button:', e);
                }
            }
        } else {
            // Reset UI if no file
            if (config.viewBtn) {
                try {
                    config.viewBtn.disabled = true;
                    if (config.viewBtn.classList) {
                        config.viewBtn.classList.remove('btn-success');
                        config.viewBtn.classList.add('btn-secondary');
                    }
                } catch (e) {
                    console.error('Error resetting view button:', e);
                }
            }
            
            // Reset file input if it exists
            if (config.fileInput) {
                try {
                    config.fileInput.disabled = false;
                } catch (e) {
                    console.error('Error resetting file input:', e);
                }
            }
            
            // Reset upload button if it exists
            if (config.uploadBtn) {
                try {
                    config.uploadBtn.disabled = false;
                    config.uploadBtn.innerHTML = '<i class="fas fa-upload me-1"></i> Upload';
                    if (config.uploadBtn.classList) {
                        config.uploadBtn.classList.remove('btn-success');
                        config.uploadBtn.classList.add('btn-primary');
                    }
                } catch (e) {
                    console.error('Error resetting upload button:', e);
                }
            }
        }
    }

    // Function to upload a contract
    async function uploadContract(contractType) {
        const config = contractConfigs[contractType];
        if (!config || !config.fileInput) return;

        const file = config.fileInput.files[0];
        if (!file) {
            showAlert('Please select a file to upload.', 'warning');
            return;
        }

        // Show loading state
        const originalBtnText = config.uploadBtn ? config.uploadBtn.innerHTML : '';
        if (config.uploadBtn) {
            config.uploadBtn.disabled = true;
            config.uploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Uploading...';
        }

        // Prepare form data
        const formData = new FormData();
        formData.append('contract_file', file);
        formData.append('project_id', projectIdInput.value);
        formData.append('contract_type', contractType);

        try {
            const response = await fetch('save_contract.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data && data.success) {
                // Show success modal
                successModal.show();
                
                // Reload the page after modal is closed
                successModal._element.addEventListener('hidden.bs.modal', function onModalClose() {
                    successModal._element.removeEventListener('hidden.bs.modal', onModalClose);
                    window.location.reload();
                }, { once: true });
                
                // Update the contract file path
                contractFilePaths[contractType] = data.filePath || '';
                
                // Update UI
                updateContractUI(contractType, data.filePath || '');
            } else {
                throw new Error(data.message || 'Upload failed. Please try again.');
            }
        } catch (error) {
            console.error('Upload error:', error);
            showAlert(error.message || 'An error occurred during upload.', 'danger');
        } finally {
            // Reset button state
            if (config.uploadBtn) {
                config.uploadBtn.disabled = false;
                config.uploadBtn.innerHTML = originalBtnText;
            }
        }
    }

    // Function to load contract statuses
    async function loadContractStatus() {
        const projectId = projectIdInput ? projectIdInput.value : 0;
        if (!projectId) return;

        try {
            const response = await fetch(`get_contracts.php?project_id=${projectId}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data && data.success && data.contracts) {
                // Reset all contract UIs
                Object.keys(contractConfigs).forEach(type => {
                    updateContractUI(type, '');
                });
                
                // Update UIs for existing contracts
                data.contracts.forEach(contract => {
                    if (contract.contract_type && contract.file_path) {
                        updateContractUI(
                            contract.contract_type, 
                            contract.file_path, 
                            contract.uploaded_at
                        );
                    }
                });
            }
        } catch (error) {
            console.error('Error loading contract status:', error);
        }
    }

    // Initialize event listeners
    function initEventListeners() {
        // Set up upload buttons and file inputs
        Object.entries(contractConfigs).forEach(([type, config]) => {
            if (config.uploadBtn) {
                config.uploadBtn.addEventListener('click', () => uploadContract(type));
            }
            
            // Handle file selection changes to update view button
            if (config.fileInput) {
                config.fileInput.addEventListener('change', (e) => {
                    const file = e.target.files[0];
                    if (file) {
                        // Update the view button
                        config.viewBtn.disabled = false;
                        config.viewBtn.onclick = () => {
                            const objectUrl = URL.createObjectURL(file);
                            showFileViewer(objectUrl, file.name);
                            // Clean up the object URL when the modal is closed
                            fileViewerModal._element.addEventListener('hidden.bs.modal', function cleanup() {
                                URL.revokeObjectURL(objectUrl);
                                fileViewerModal._element.removeEventListener('hidden.bs.modal', cleanup);
                            }, { once: true });
                        };
                    } else {
                        config.viewBtn.disabled = true;
                    }
                });
            }
        });
    }

    // Function to show PDF in the appropriate modal
    function showPdfViewer(contractType, pdfPath) {
        console.log(`Opening ${contractType} PDF:`, pdfPath);
        
        if (!pdfPath) {
            console.error('Error: No PDF path provided for', contractType);
            return;
        }
        
        // Remove any leading slashes or backslashes from the path
        const cleanPath = pdfPath.replace(/^[\\/]/, '');
        
        // Construct the full URL
        const baseUrl = window.location.origin;
        const fullPath = `${baseUrl}/Voltech-2/projectmanager/${cleanPath}`;
        
        console.log('Full path:', fullPath);
        
        // Get the appropriate elements based on contract type
        const viewer = document.getElementById(`${contractType}PdfViewer`);
        const downloadBtn = document.getElementById(`${contractType}PdfDownload`);
        const deleteBtn = document.querySelector(`#${contractType}PdfModal .delete-contract`);
        const modal = new bootstrap.Modal(document.getElementById(`${contractType}PdfModal`));
        
        if (!viewer || !downloadBtn || !modal) {
            console.error(`Error: Could not find elements for ${contractType} PDF viewer`);
            return;
        }
        
        // Set up error and load handlers
        viewer.onerror = function() {
            console.error(`Error loading ${contractType} PDF:`, fullPath);
            alert(`Error loading ${contractType} PDF. Please check the console for details.`);
        };
        
        viewer.onload = function() {
            console.log(`${contractType} PDF loaded successfully`);
        };
        
        // Set PDF source
        viewer.src = fullPath;
        
        // Update download button
        downloadBtn.href = fullPath;
        downloadBtn.download = pdfPath.split('/').pop() || `${contractType}_contract.pdf`;
        
        // Update delete button
        if (deleteBtn) {
            deleteBtn.onclick = (e) => {
                e.preventDefault();
                deleteContract(contractType, modal);
            };
        }
        
        // Show the modal
        modal.show();
        
        console.log(`Showing ${contractType} PDF viewer with source:`, fullPath);
    }
    
    // Function to delete a contract
    async function deleteContract(contractType, modal) {
        if (!confirm('Are you sure you want to delete this contract? This action cannot be undone.')) {
            return;
        }

        const projectId = projectIdInput ? projectIdInput.value : 0;
        if (!projectId) {
            showAlert('Error: Project ID not found', 'danger');
            return;
        }

        try {
            const response = await fetch('delete_contract.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `project_id=${encodeURIComponent(projectId)}&contract_type=${encodeURIComponent(contractType)}`
            });

            const result = await response.json();
            
            if (result.success) {
                // Close the modal first
                if (modal && typeof modal.hide === 'function') {
                    modal.hide();
                }
                
                // Show success message
                showAlert(result.message || 'Contract deleted successfully', 'success');
                
                // Update UI to reflect deletion
                updateContractUI(contractType, null);
                
                // If there was a file input, reset it
                const config = contractConfigs[contractType];
                if (config && config.fileInput) {
                    config.fileInput.value = '';
                }
                
                // Reload the page to refresh the UI state
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                throw new Error(result.message || 'Failed to delete contract');
            }
        } catch (error) {
            console.error('Delete error:', error);
            showAlert(error.message || 'An error occurred while deleting the contract', 'danger');
        }
    }
    
    // Function to show alert messages
    function showAlert(message, type = 'info') {
        // Create alert element
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show mt-3`;
        alertDiv.role = 'alert';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;

        // Insert alert at the top of the step content
        const stepContent = document.getElementById('step4');
        if (stepContent) {
            stepContent.insertBefore(alertDiv, stepContent.firstChild);
            
            // Auto-remove alert after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.classList.remove('show');
                    setTimeout(() => {
                        if (alertDiv.parentNode) {
                            alertDiv.remove();
                        }
                    }, 150);
                }
            }, 5000);
        }
    }

    // Function to check for existing contract PDFs
    function checkExistingPdfs() {
        const projectId = projectIdInput ? projectIdInput.value : 0;
        if (!projectId) return Promise.resolve();

        return fetch(`get_contracts.php?project_id=${projectId}`)
            .then(response => response.json())
            .then(data => {
                console.log('Received contract data:', data);
                if (data && data.contracts) {
                    // Update each contract type UI
                    data.contracts.forEach(contract => {
                        if (contract.contract_type && contract.file_path) {
                            console.log(`Updating UI for ${contract.contract_type}:`, contract.file_path);
                            updateContractUI(contract.contract_type, contract.file_path);
                            // Store the file path for later use
                            contractFilePaths[contract.contract_type] = contract.file_path;
                        }
                    });
                }
                return data;
            })
            .catch(error => {
                console.error('Error checking for existing contracts:', error);
                return null;
            });
    }

    // Initialize the page
    async function init() {
        initEventListeners();
        
        // Set up view buttons for each contract type
        Object.entries(contractConfigs).forEach(([type, config]) => {
            if (config.viewBtn) {
                config.viewBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const contractPath = contractFilePaths[type];
                    if (contractPath) {
                        showPdfViewer(type, contractPath);
                    } else {
                        showAlert(`No ${type.replace(/([A-Z])/g, ' $1').toLowerCase()} contract has been uploaded yet.`, 'warning');
                    }
                });
            }
        });
        
        // Load existing contract statuses
        if (projectIdInput && projectIdInput.value) {
            await loadContractStatus();
            await checkExistingPdfs();
        }
    }

    // Start the application
    init();
});

