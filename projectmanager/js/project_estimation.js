// Step 2 Materials Management
const projectMaterials = [];
const materialsTotal = 0;
let allMaterials = [];

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Ensure we're on step 2 when initializing
    if (typeof window.currentStep === 'undefined') {
        window.currentStep = 2;
    }
    

    
    initializeMaterialsModal();
    setupExportCostEstimation();
    
    // Setup event delegation for table quantity controls
    setupTableQuantityControls();
    

});

// Initialize the materials modal
function initializeMaterialsModal() {
    const modal = document.getElementById('addMaterialsModal');
    if (!modal) return;

    modal.addEventListener('show.bs.modal', function(e) {
        // Ensure we stay on step 2
        if (window.currentStep !== 2) {
            window.currentStep = 2;
            const stepDiv = document.getElementById('step2');
            if (stepDiv) {
                document.querySelectorAll('.step-content').forEach(step => {
                    step.classList.add('d-none');
                });
                stepDiv.classList.remove('d-none');
            }
        }
        
        loadMaterialsForModal();
    });

    setupMaterialsModal();
}

// Load materials for the modal table
function loadMaterialsForModal() {
    const tbody = document.getElementById('materialsTableBody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="8" class="text-center">Loading materials...</td></tr>';

    fetch('get_materials.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.materials) {
                allMaterials = data.materials;
                renderMaterialsInModal();
            } else {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Failed to load materials</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error loading materials:', error);
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error loading materials</td></tr>';
        });
}

// Render materials in the modal table
function renderMaterialsInModal() {
    const tbody = document.getElementById('materialsTableBody');
    if (!tbody) return;

    if (allMaterials.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-4">
                    <div class="text-muted">
                        <i class="fas fa-box-open fa-2x mb-2"></i>
                        <p class="mb-0">No materials available</p>
                    </div>
                </td>
            </tr>`;
        return;
    }

    tbody.innerHTML = '';
    allMaterials.forEach((material, index) => {
        const row = document.createElement('tr');
        const price = parseFloat(material.material_price) || 0;
        const labor = parseFloat(material.labor_other) || 0;
        const quantity = 1;
        const total = (price + labor) * quantity;
        const materialName = material.material_name || 'N/A';
        const brand = material.brand || 'N/A';
        const supplier = material.supplier_name || 'N/A';
        const spec = material.specification || 'N/A';
        const unit = material.unit || 'N/A';

        row.setAttribute('data-material-id', material.id);

        row.innerHTML = `
            <td class="text-center align-middle">
                <div class="form-check d-flex justify-content-center">
                    <input class="form-check-input estimation-material-checkbox" type="checkbox" name="selected_materials[]" value="${material.id}">
                </div>
            </td>
            <td class="align-middle">
                <div class="text-truncate" style="max-width: 200px;" title="${materialName}">${materialName}</div>
            </td>
            <td class="align-middle">
                <div class="text-truncate" style="max-width: 150px;" title="${brand}">${brand}</div>
            </td>
            <td class="align-middle">
                <div class="text-truncate" style="max-width: 150px;" title="${supplier}">${supplier}</div>
            </td>
            <td class="align-middle">
                <div class="text-truncate" style="max-width: 200px;" title="${spec}">${spec}</div>
            </td>
            <td class="align-middle text-center">${unit}</td>
            <td class="align-middle text-end">₱${price.toFixed(2)}</td>
            <td class="align-middle">
                <div class="quantity-controls">
                    <button type="button" class="btn btn-sm btn-outline-secondary quantity-decrease quantity-btn">
                        <i class="fas fa-minus"></i>
                    </button>
                    <input type="number" class="form-control form-control-sm text-center mx-1 quantity-input"
                           value="${quantity}" min="1" step="1" data-price="${price}"
                           onkeydown="return event.key !== 'e' && event.key !== 'E' && event.key !== '-' && event.key !== '+';">
                    <button type="button" class="btn btn-sm btn-outline-secondary quantity-increase quantity-btn">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </td>
            <td class="align-middle text-end fw-bold material-total">₱${total.toFixed(2)}</td>
        `;

        tbody.appendChild(row);
    });

    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Setup event listeners for the materials modal
function setupMaterialsModal() {
    const modal = document.getElementById('addMaterialsModal');
    if (!modal) return;
    
    // Delegate events for quantity controls
    modal.addEventListener('click', function(e) {
        const target = e.target.closest('.quantity-increase, .quantity-decrease');
        if (!target) return;

        const input = target.closest('.quantity-controls').querySelector('.quantity-input');
        if (!input) return;

        if (target.classList.contains('quantity-increase')) {
            input.stepUp();
            updateRowTotal(input);
        } else if (target.classList.contains('quantity-decrease')) {
            const currentValue = parseFloat(input.value);
            if (currentValue > 1) {
                input.stepDown();
                updateRowTotal(input);
            }
        }
        e.preventDefault();
        e.stopPropagation();
    });

    modal.addEventListener('input', function(e) {
        if (e.target.classList.contains('quantity-input')) {
            updateRowTotal(e.target);
        }
    });

    // No select all functionality as per user request

    // Handle form submission
    const form = modal.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();

            // Get all selected materials with quantities
            const selectedMaterials = [];
            const checkboxes = modal.querySelectorAll('.estimation-material-checkbox:checked');
            if (checkboxes.length === 0) {
                showAlert('Please select at least one material', 'warning');
                return;
            }

            checkboxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                if (row) {
                    const materialId = row.getAttribute('data-material-id');
                    const quantityInput = row.querySelector('.quantity-input');
                    if (quantityInput && materialId) {
                        const quantity = parseFloat(quantityInput.value) || 1;
                        selectedMaterials.push({
                            material_id: materialId,
                            quantity: quantity
                        });
                    }
                }
            });

            // Build the payload object
            const payload = {
                add_estimation_material: '1',
                project_id: form.querySelector('[name="project_id"]').value,
                materials: selectedMaterials
            };

            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...';

            // Submit the data as JSON
            fetch(form.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(async response => {
                const responseData = await response.text();
                if (!response.ok) {
                    let errorMessage = 'Server error';
                    try { errorMessage = JSON.parse(responseData).message || errorMessage; } catch(e){ errorMessage = responseData || errorMessage; }
                    throw new Error(`Server error (${response.status}): ${errorMessage}`);
                }
                try {
                    return JSON.parse(responseData);
                } catch (e) {
                    throw new Error('Invalid response from server');
                }
            })
            .then(data => {
                if (data && data.success) {
                    const modalInstance = bootstrap.Modal.getInstance(modal);
                    if (modalInstance) modalInstance.hide();
                    showAlert(data.message || 'Materials added successfully', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    throw new Error(data?.message || 'Failed to add materials');
                }
            })
            .catch(error => {
                showAlert(error.message || 'An error occurred while adding materials', 'danger');
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            });
        });
    }
}

// Update the total for a row when quantity changes
function updateRowTotal(input) {
    if (!input || !input.closest) return;
    const row = input.closest('tr');
    if (!row) return;

    const price = parseFloat(input.dataset.price) || 0;
    const labor = parseFloat(input.dataset.labor) || 0;
    let quantity = parseFloat(input.value);
    if (isNaN(quantity) || quantity <= 0) {
        quantity = 1;
        input.value = quantity;
    }
    const total = (price + labor) * quantity;
    const totalCell = row.querySelector('.material-total');
    if (totalCell) {
        totalCell.textContent = `₱${total.toFixed(2)}`;
    }
    return total;
}

// Function to open materials modal (keeping for compatibility)
function openMaterialsModal() {
    // This function is kept for compatibility but not used
    // The modal is now opened via Bootstrap's data attributes
}



// Function to update grand total
function updateGrandTotal() {
    // Get project_id from URL
    const urlParams = new URLSearchParams(window.location.search);
    const projectId = urlParams.get('project_id');
    
    if (!projectId) return;
    
    // Fetch the grand total from the database
    fetch(`get_project_grand_total.php?project_id=${projectId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const grandTotalElement = document.getElementById('materialsTotal');
                if (grandTotalElement) {
                    grandTotalElement.textContent = `₱${parseFloat(data.grand_total).toFixed(2)}`;
                }
                
                // Trigger VAT recalculation after materials total is updated
                if (typeof calculateVAT === 'function') {
                    calculateVAT();
                }
            }
        })
        .catch(error => {
            console.error('Error fetching grand total:', error);
        });
}

// Setup event delegation for table quantity controls (same as modal)
function setupTableQuantityControls() {
    // Delegate events for quantity controls in the table
    document.addEventListener('click', function(e) {
        const target = e.target.closest('.quantity-increase, .quantity-decrease');
        if (!target) return;
        
        // Check if it's in the table (not in modal)
        const table = target.closest('table');
        if (!table || table.id === 'materialsTable') return; // Skip if it's the modal table

        const input = target.closest('.quantity-controls').querySelector('.quantity-input');
        if (!input) return;

        if (target.classList.contains('quantity-increase')) {
            input.stepUp();
            updateTableRowTotal(input);
        } else if (target.classList.contains('quantity-decrease')) {
            const currentValue = parseFloat(input.value);
            if (currentValue > 1) {
                input.stepDown();
                updateTableRowTotal(input);
            }
        }
        e.preventDefault();
        e.stopPropagation();
    });

    // Handle input changes in table
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('quantity-input')) {
            const table = e.target.closest('table');
            if (table && table.id !== 'materialsTable') { // Only for main table, not modal
                updateTableRowTotal(e.target);
            }
        }
    });
}

// Function to update table row total and save to database
function updateTableRowTotal(input) {
    if (!input || !input.closest) return;
    const row = input.closest('tr');
    if (!row) return;

    const price = parseFloat(input.dataset.price) || 0;
    const labor = parseFloat(input.dataset.labor) || 0;
    let quantity = parseFloat(input.value);
    if (isNaN(quantity) || quantity <= 0) {
        quantity = 1;
        input.value = quantity;
    }
    const total = (price + labor) * quantity;
    
    // Update the total cell in the same row
    const totalCell = row.querySelector('td:nth-child(8)'); // Total column
    if (totalCell) {
        totalCell.innerHTML = `₱${total.toFixed(2)}`;
    }
    
    // Update grand total
    updateGrandTotal();
    
    // Save to database
    const pemId = input.dataset.pemId;
    if (pemId) {
        const formData = new FormData();
        formData.append('pem_id', pemId);
        formData.append('quantity', quantity);
        formData.append('update_quantity', '1');
        
        fetch('update_material_quantity.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Refresh the page after successful update
                window.location.reload();
            } else {
                showAlert(data.message || 'Error updating quantity', 'danger');
            }
        })
        .catch(error => {
            showAlert('An error occurred while updating quantity', 'danger');
        });
    }
    
    return total;
}



// Export Cost Estimation PDF
function setupExportCostEstimation() {
    const exportBtn = document.getElementById('exportCostEstimationBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const projectId = new URLSearchParams(window.location.search).get('project_id');
            window.open('export_estimation_materials.php?project_id=' + projectId, '_blank');
        });
    }
}

function showAlert(message, type) {
    const container = document.querySelector('.container-fluid');
    if (!container) return;
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    container.insertBefore(alertDiv, container.firstChild);
    setTimeout(() => { alertDiv.remove(); }, 3000);
}

// Material select loading (for other estimation features)
function loadMaterials() {
    fetch('get_materials.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const materialSelect = document.getElementById('materialName');
                materialSelect.innerHTML = '<option value="" disabled selected>Select Material</option>';
                data.materials.forEach(material => {
                    const option = document.createElement('option');
                    option.value = material.id;
                    option.setAttribute('data-unit', material.unit || '');
                    option.setAttribute('data-price', material.material_price || '0');
                    option.setAttribute('data-labor', material.labor_other || '0');
                    option.setAttribute('data-name', material.material_name || '');
                    option.setAttribute('data-supplier', material.supplier_name || '');
                    option.textContent = `${material.material_name} (₱${parseFloat(material.material_price || 0).toFixed(2)})`;
                    materialSelect.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error loading materials:', error));
}

// Setup material form events (for single material add)
function setupMaterialForm() {
    const materialName = document.getElementById('materialName');
    const materialUnit = document.getElementById('materialUnit');
    const materialPrice = document.getElementById('materialPrice');
    const laborOther = document.getElementById('laborOther');
    const materialNameText = document.getElementById('materialNameText');
    const materialQty = document.getElementById('materialQty');
    const materialTotal = document.getElementById('materialTotal');

    function updateMaterialTotal() {
        const qty = parseFloat(materialQty.value) || 0;
        const price = parseFloat(materialPrice.value) || 0;
        const labor = parseFloat(laborOther.value) || 0;
        const total = (price + labor) * qty;
        materialTotal.value = total > 0 ? total.toFixed(2) : '';
    }

    if (materialName) {
        materialName.addEventListener('change', function() {
            const selected = materialName.options[materialName.selectedIndex];
            materialUnit.value = selected.getAttribute('data-unit') || '';
            materialPrice.value = selected.getAttribute('data-price') || '';
            laborOther.value = selected.getAttribute('data-labor') || '';
            materialNameText.value = selected.getAttribute('data-name') || '';
            updateMaterialTotal();
        });
    }

    if (materialQty) {
        materialQty.addEventListener('input', updateMaterialTotal);
    }
}

// Remove material from project estimation
function removeMaterial(pemId) {
    if (confirm('Remove this material?')) {
        const formData = new FormData();
        formData.append('pem_id', pemId);

        fetch('remove_project_material.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                showAlert(data.message || 'Error removing material', 'danger');
            }
        })
        .catch(error => {
            showAlert('An error occurred while removing material', 'danger');
        });
    }
}

 // Export Cost Estimation PDF
 function setupExportCostEstimation() {
    const exportBtn = document.getElementById('exportCostEstimationBtn');

    if (exportBtn) {
        exportBtn.addEventListener('click', function (e) {
            e.preventDefault();

            // Get project_id from the URL
            const projectId = new URLSearchParams(window.location.search).get('project_id');

            // Just open export_estimation_materials.php directly with project_id
            window.open('export_estimation_materials.php?project_id=' + projectId, '_blank');
        });
    }
}

