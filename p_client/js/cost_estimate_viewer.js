// Cost Estimate Viewer Handler
document.addEventListener('DOMContentLoaded', function() {
    // Get both buttons
    const viewCostEstimateBtn = document.getElementById('viewCostEstimateBtn');
    const viewCostEstimateBillingBtn = document.getElementById('viewCostEstimateBillingBtn');
    
    // Add click event listeners to both buttons
    if (viewCostEstimateBtn) {
        viewCostEstimateBtn.addEventListener('click', function() {
            loadCostEstimates(viewCostEstimateBtn);
        });
    }

    if (viewCostEstimateBillingBtn) {
        viewCostEstimateBillingBtn.addEventListener('click', function() {
            loadCostEstimates(viewCostEstimateBillingBtn);
        });
    }

    // Function to load cost estimates from the server
    async function loadCostEstimates(buttonElement) {
        let originalHtml = '';
        
        try {
            // Get project ID from URL or global variable
            const projectId = window.currentProjectId || 
                             new URLSearchParams(window.location.search).get('project_id') ||
                             document.getElementById('projectId')?.value;
            
            if (!projectId) {
                showAlert('Project ID not found', 'danger');
                return;
            }

            // Show loading state
            if (buttonElement) {
                buttonElement.disabled = true;
                originalHtml = buttonElement.innerHTML;
                buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Loading...';
            }

            // Fetch cost estimates
            const response = await fetch(`get_cost_estimates.php?project_id=${encodeURIComponent(projectId)}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            // Reset button state
            if (buttonElement && originalHtml) {
                buttonElement.disabled = false;
                buttonElement.innerHTML = originalHtml;
            }

            if (data.success && Array.isArray(data.cost_estimates)) {
                if (data.cost_estimates.length === 0) {
                    showAlert('No cost estimates found for this project', 'info');
                    return;
                }
                
                // Get the most recent cost estimate (first one since they're ordered by upload_date DESC)
                const mostRecent = data.cost_estimates[0];
                
                // Directly view the PDF
                viewCostEstimatePDF(mostRecent.file_path, mostRecent.file_name);
            } else {
                throw new Error(data.message || 'Failed to load cost estimates');
            }
        } catch (error) {
            console.error('Error loading cost estimates:', error);
            showAlert(`Failed to load cost estimates: ${error.message}`, 'danger');
            
            // Reset button state on error
            if (buttonElement && originalHtml) {
                buttonElement.disabled = false;
                buttonElement.innerHTML = originalHtml;
            }
        }
    }


    // Function to view cost estimate PDF
    function viewCostEstimatePDF(filePath, fileName) {
        // Get or create PDF viewer modal
        let pdfModal = document.getElementById('costEstimatePdfModal');
        
        if (!pdfModal) {
            pdfModal = createPdfViewerModal();
            document.body.appendChild(pdfModal);
        }

        const pdfViewer = document.getElementById('costEstimatePdfViewer');
        const pdfDownload = document.getElementById('costEstimatePdfDownload');
        const pdfFileName = document.getElementById('costEstimatePdfFileName');

        if (pdfViewer) {
            // Add timestamp to prevent caching
            const timestamp = new Date().getTime();
            pdfViewer.src = filePath + (filePath.includes('?') ? '&' : '?') + 't=' + timestamp;
        }

        if (pdfDownload) {
            pdfDownload.href = filePath;
            pdfDownload.download = fileName || 'cost_estimate.pdf';
        }

        if (pdfFileName) {
            pdfFileName.textContent = fileName || 'Cost Estimate';
        }

        // Show PDF viewer modal
        const bsPdfModal = new bootstrap.Modal(pdfModal);
        bsPdfModal.show();
    }

    // Function to create PDF viewer modal
    function createPdfViewerModal() {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'costEstimatePdfModal';
        modal.setAttribute('tabindex', '-1');
        modal.setAttribute('aria-labelledby', 'costEstimatePdfModalLabel');
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML = `
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="costEstimatePdfModalLabel">
                            <i class="fas fa-file-pdf me-2"></i>
                            <span id="costEstimatePdfFileName">Cost Estimate</span>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0" style="height: 80vh;">
                        <iframe id="costEstimatePdfViewer" 
                                src="" 
                                style="width: 100%; height: 100%; border: none;">
                        </iframe>
                    </div>
                    <div class="modal-footer">
                        <a id="costEstimatePdfDownload" href="#" class="btn btn-success" download>
                            <i class="fas fa-download me-1"></i> Download
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        `;
        return modal;
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Helper function to show alert (reuse from other handlers if available)
    function showAlert(message, type = 'info') {
        // Try to use existing showAlert function if available
        if (typeof window.showAlert === 'function') {
            window.showAlert(message, type);
            return;
        }

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
        toast.className = 'toast align-items-center text-white bg-' + (type === 'success' ? 'success' : type === 'danger' ? 'danger' : 'primary') + ' border-0';
        toast.role = 'alert';
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'danger' ? 'fa-exclamation-circle' : 'fa-info-circle'} me-2"></i>
                    ${escapeHtml(message)}
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
});

