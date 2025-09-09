// Client Permit Viewing Functionality
const permitTypes = [
    {
        id: 'lgu',
        name: 'LGU Clearance',
        icon: 'fas fa-landmark',
        color: 'primary',
        description: 'Local Government Unit Clearance'
    },
    {
        id: 'fire',
        name: 'Fire Permit',
        icon: 'fas fa-fire-extinguisher',
        color: 'danger',
        description: 'Fire Safety Permit'
    },
    {
        id: 'zoning',
        name: 'Zoning Clearance',
        icon: 'fas fa-map-marked-alt',
        color: 'warning',
        description: 'Zoning Compliance Clearance'
    },
    {
        id: 'occupancy',
        name: 'Occupancy Permit',
        icon: 'fas fa-building',
        color: 'success',
        description: 'Building Occupancy Permit'
    },
    {
        id: 'barangay',
        name: 'Barangay Clearance',
        icon: 'fas fa-home',
        color: 'info',
        description: 'Barangay Clearance'
    }
];

// Load permits for client viewing
async function loadClientPermits() {
    const projectId = document.getElementById('projectIdInputPermits')?.value;
    if (!projectId) {
        console.log('No project ID found for permits');
        return;
    }
    
    try {
        console.log('Loading permits for project ID:', projectId);
        const response = await fetch(`get_client_permits.php?project_id=${projectId}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        console.log('Permits API response:', result);
        
        const permitsContainer = document.getElementById('permitsContainer');
        const noPermitsMessage = document.getElementById('noPermitsMessage');
        
        if (result.success && Array.isArray(result.permits)) {
            if (result.permits.length === 0) {
                console.log('No permits found for this project');
                permitsContainer.innerHTML = '';
                noPermitsMessage.classList.remove('d-none');
                return;
            }
            
            noPermitsMessage.classList.add('d-none');
            permitsContainer.innerHTML = '';
            
            // Create cards for all permit types
            permitTypes.forEach(permitType => {
                const permitCard = createPermitCard(permitType, result.permits);
                permitsContainer.appendChild(permitCard);
            });
        } else {
            console.error('Invalid response format:', result);
            permitsContainer.innerHTML = '';
            noPermitsMessage.classList.remove('d-none');
        }
    } catch (error) {
        console.error('Error loading permits:', error);
        const permitsContainer = document.getElementById('permitsContainer');
        const noPermitsMessage = document.getElementById('noPermitsMessage');
        permitsContainer.innerHTML = '';
        noPermitsMessage.classList.remove('d-none');
    }
}

// Create individual permit card
function createPermitCard(permitType, uploadedPermits) {
    // Find if this permit type has been uploaded
    const uploadedPermit = uploadedPermits.find(p => p.permit_type === permitType.id);
    
    const cardDiv = document.createElement('div');
    cardDiv.className = 'col-md-6 col-lg-4 mb-4';
    
    if (uploadedPermit) {
        // Permit is uploaded - show file info
        const fileUrl = uploadedPermit.file_path.replace(/\\/g, '/');
        const fileName = uploadedPermit.file_name || 'Document';
        const uploadDate = new Date(uploadedPermit.upload_date).toLocaleDateString();
        const fileSize = formatFileSize(uploadedPermit.file_size);
        
        cardDiv.innerHTML = `
            <div class="card h-100 border-success">
                <div class="card-header bg-${permitType.color} text-white text-center">
                    <i class="${permitType.icon} fa-2x mb-2"></i>
                    <h6 class="mb-0">${permitType.name}</h6>
                </div>
                <div class="card-body d-flex flex-column">
                    <div class="text-center mb-3">
                        <i class="fas fa-check-circle fa-3x text-success mb-2"></i>
                        <h6 class="text-success">Uploaded</h6>
                    </div>
                    <div class="mb-3">
                        <h6 class="card-title">${fileName}</h6>
                        <p class="card-text small text-muted">
                            <i class="fas fa-calendar me-1"></i> ${uploadDate}<br>
                            <i class="fas fa-file me-1"></i> ${fileSize}
                        </p>
                    </div>
                    <div class="mt-auto">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-${permitType.color} view-permit" 
                                    data-file-url="${fileUrl}" 
                                    data-file-name="${fileName}"
                                    data-permit-type="${permitType.name}">
                                <i class="fas fa-eye me-1"></i> View Document
                            </button>
                            <a href="${fileUrl}" class="btn btn-${permitType.color}" download="${fileName}">
                                <i class="fas fa-download me-1"></i> Download
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        `;
    } else {
        // Permit not uploaded - show pending status
        cardDiv.innerHTML = `
            <div class="card h-100 border-secondary">
                <div class="card-header bg-secondary text-white text-center">
                    <i class="${permitType.icon} fa-2x mb-2"></i>
                    <h6 class="mb-0">${permitType.name}</h6>
                </div>
                <div class="card-body d-flex flex-column">
                    <div class="text-center mb-3">
                        <i class="fas fa-clock fa-3x text-muted mb-2"></i>
                        <h6 class="text-muted">Pending</h6>
                    </div>
                    <div class="mb-3">
                        <p class="card-text small text-muted">
                            ${permitType.description}<br>
                            <i class="fas fa-info-circle me-1"></i> Waiting for project manager to upload
                        </p>
                    </div>
                    <div class="mt-auto">
                        <div class="d-grid">
                            <button type="button" class="btn btn-outline-secondary" disabled>
                                <i class="fas fa-eye me-1"></i> Not Available
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Add click event for view button if permit is uploaded
    if (uploadedPermit) {
        const viewBtn = cardDiv.querySelector('.view-permit');
        viewBtn.addEventListener('click', function() {
            const fileUrl = this.getAttribute('data-file-url');
            const fileName = this.getAttribute('data-file-name');
            const permitType = this.getAttribute('data-permit-type');
            
            // Update modal title
            document.getElementById('permitModalTitle').textContent = permitType;
            
            // Set iframe source
            document.getElementById('permitViewer').src = fileUrl;
            
            // Set download link
            const downloadLink = document.getElementById('permitDownload');
            downloadLink.href = fileUrl;
            downloadLink.download = fileName;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('permitViewModal'));
            modal.show();
        });
    }
    
    return cardDiv;
}

// Format file size
function formatFileSize(bytes) {
    if (!bytes) return 'Unknown size';
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
}

// Initialize permit viewing when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Load permits when step 4 is shown
    const step4Element = document.getElementById('step4');
    if (step4Element) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    if (!step4Element.classList.contains('d-none')) {
                        console.log('Step 4 is now visible, loading permits...');
                        loadClientPermits();
                        observer.disconnect(); // Stop observing once loaded
                    }
                }
            });
        });
        
        observer.observe(step4Element, {
            attributes: true,
            attributeFilter: ['class']
        });
    }
    
    // Also load permits if step 4 is already visible
    if (step4Element && !step4Element.classList.contains('d-none')) {
        console.log('Step 4 is already visible, loading permits...');
        loadClientPermits();
    }
});

// Export functions for global access
window.loadClientPermits = loadClientPermits;
window.createPermitCard = createPermitCard;
window.formatFileSize = formatFileSize;
