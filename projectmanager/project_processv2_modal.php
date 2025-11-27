<!-- Blueprints Modal -->
<div class="modal fade" id="blueprintsModal" tabindex="-1" aria-labelledby="blueprintsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="blueprintsModalLabel">
                    <i class="fas fa-drafting-compass"></i> Project Blueprints
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="blueprintList" class="row"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Blueprint Upload Success Modal -->
<div class="modal fade" id="blueprintUploadSuccessModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-check-circle me-2"></i>Upload Successful
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="fas fa-check-circle text-success" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                <h4 class="mb-3">Blueprint(s) Uploaded Successfully!</h4>
                <p id="uploadedFilesList" class="text-muted"></p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">
                    <i class="fas fa-check me-2"></i>OK
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Blueprint Delete Success Modal -->
<div class="modal fade" id="deleteBlueprintSuccessModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-check-circle me-2"></i>Delete Successful
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="fas fa-check-circle text-success" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                <h4 class="mb-3">Blueprint Deleted Successfully!</h4>
                <p class="text-muted">The blueprint has been removed from the system.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal" id="refreshAfterDelete">
                    <i class="fas fa-check me-2"></i>OK
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Budget Document Delete Success Modal -->
<div class="modal fade" id="deleteBudgetSuccessModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-check-circle me-2"></i>Delete Successful
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="fas fa-check-circle text-success" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                <h4 class="mb-3">Document Deleted Successfully!</h4>
                <p class="text-muted">The document has been removed from the system.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">
                    <i class="fas fa-check me-2"></i>OK
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Budget Upload Success Modal -->
<div class="modal fade" id="budgetUploadSuccessModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-check-circle me-2"></i>Upload Successful
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="fas fa-check-circle text-success" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                <h4 class="mb-3">Budget Document(s) Uploaded Successfully!</h4>
                <p id="budgetUploadedFilesList" class="text-muted"></p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">
                    <i class="fas fa-check me-2"></i>OK
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Budget Files Modal -->
<div class="modal fade" id="budgetFilesModal" tabindex="-1" aria-labelledby="budgetFilesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="budgetFilesModalLabel">
                    <i class="fas fa-file-invoice-dollar me-2"></i> Budget Documents
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Alert container -->
                <div id="alertContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 9999"></div>
                
                <!-- Drop Zone -->
                <div class="card mb-4 border-2 border-dashed" id="budgetDropZone" style="border-color: #dee2e6; min-height: 150px;">
                    <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-5">
                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                        <h5 class="mb-2">Drag & drop your files here</h5>
                        <p class="text-muted mb-3">or</p>
                        <button type="button" class="btn btn-primary" id="browseBudgetFilesBtn">
                            <i class="fas fa-folder-open me-2"></i>Browse Files
                        </button>
                        <input type="file" id="budgetFiles" class="d-none" accept=".pdf" multiple>
                        <p class="small text-muted mt-2 mb-0">Only PDF files are allowed (max 10MB each)</p>
                    </div>
                </div>

                <!-- File List -->
                <div class="mb-3" id="budgetFileList">
                    <!-- Files will be listed here -->
                </div>

                <!-- Upload Button -->
                <div class="d-flex justify-content-between align-items-center">
                    <div id="uploadStatus" class="text-muted small"></div>
                    <button type="button" class="btn btn-success" id="uploadBudgetBtn">
                        <i class="fas fa-upload me-2"></i>Upload Files
                    </button>
                </div>

                <hr class="my-4">

                <!-- Documents List -->
                <div id="budgetDocumentsList">
                    <!-- Documents will be loaded here via JavaScript -->
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 mb-0">Loading documents...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addMaterialsModal" tabindex="-1" role="dialog" aria-labelledby="addMaterialsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
            <div class="modal-content shadow">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="addMaterialsModalLabel">
                        <i class="fas fa-plus-circle me-2"></i> Add Materials
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addMaterialsForm" method="post" action="add_project_material.php">
                    <input type="hidden" name="project_id" id="project_id" value="<?php echo isset($_GET['project_id']) ? intval($_GET['project_id']) : ''; ?>">
                    <input type="hidden" name="add_estimation_material" value="1">

                    <div class="modal-body p-0">
                        <div class="table-responsive" style="max-height: 60vh; overflow-y: auto;">
                            <table class="table table-hover table-bordered table-striped mb-0" id="materialsTable">
                                <thead class="table-light sticky-top" style="position: sticky; top: 0; z-index: 10; background-color: #f8f9fa;">
                                    <tr>
                                        <th width="40" class="align-middle">
                                            <div class="form-check d-flex justify-content-center">
                                                <input class="form-check-input m-0" type="checkbox" id="selectAllMaterials">
                                            </div>
                                        </th>
                                        <th class="align-middle">Material Name</th>
                                        <th class="align-middle">Brand</th>
                                        <th class="align-middle">Supplier</th>
                                        <th class="align-middle">Specification</th>
                                        <th class="align-middle text-center" style="width: 80px;">Unit</th>
                                        <th class="align-middle text-end" style="min-width: 100px;">Price (₱)</th>
                                        <th class="align-middle" style="width: 150px;">Quantity</th>
                                        <th class="align-middle text-end" style="min-width: 120px;">Total (₱)</th>
                                    </tr>
                                </thead>
                                <tbody id="materialsTableBody" class="bg-white">
                                    <tr>
                                        <td colspan="8" class="text-center py-3">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <p class="mt-2 mb-0">Loading materials...</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="modal-footer bg-light border-top">
                        <div class="d-flex justify-content-end w-100">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i> Cancel
                            </button>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check me-1"></i> Add Selected Materials
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

   <!-- Original Contract PDF Viewer -->
   <div class="modal fade" id="originalPdfModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Original Contract</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" style="height: 80vh;">
                    <iframe id="originalPdfViewer" class="w-100 h-100" frameborder="0"></iframe>
                </div>
                <div class="modal-footer">
                    <div class="me-auto">
                        <button type="button" class="btn btn-danger delete-contract" data-contract-type="original">
                            <i class="fas fa-trash-alt me-1"></i> Delete
                        </button>
                    </div>
                    <div>
                        <a id="originalPdfDownload" href="#" class="btn btn-primary">
                            <i class="fas fa-download me-1"></i> Download
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Your Signed Contract PDF Viewer -->
    <div class="modal fade" id="yoursignedPdfModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Your Signed Contract</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" style="height: 80vh;">
                    <iframe id="yoursignedPdfViewer" class="w-100 h-100" frameborder="0"></iframe>
                </div>
                <div class="modal-footer">
                    <div class="me-auto">
                        <button type="button" class="btn btn-danger delete-contract" data-contract-type="yoursigned">
                            <i class="fas fa-trash-alt me-1"></i> Delete
                        </button>
                    </div>
                    <div>
                        <a id="yoursignedPdfDownload" href="#" class="btn btn-primary">
                            <i class="fas fa-download me-1"></i> Download
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Client Signed Contract PDF Viewer -->
    <div class="modal fade" id="clientsignedPdfModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Client Signed Contract</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" style="height: 80vh;">
                    <iframe id="clientsignedPdfViewer" class="w-100 h-100" frameborder="0"></iframe>
                </div>
                <div class="modal-footer">
                    <div class="me-auto">
                        <button type="button" class="btn btn-danger delete-contract" data-contract-type="clientsigned">
                            <i class="fas fa-trash-alt me-1"></i> Delete
                        </button>
                    </div>
                    <div>
                        <a id="clientsignedPdfDownload" href="#" class="btn btn-primary">
                            <i class="fas fa-download me-1"></i> Download
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="modal fade" id="lguModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">LGU Clearance</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" style="height: 80vh;">
                    <div id="lguPermitPreview"></div>
                </div>
                <div class="modal-footer">
                    <div class="me-auto">
                        <button type="button" class="btn btn-danger delete-permit" data-permit-type="lgu">
                            <i class="fas fa-trash-alt me-1"></i> Delete
                        </button>
                    </div>
                    <div>
                        <a id="lguPermitDownload" href="#" class="btn btn-primary" download>
                            <i class="fas fa-download me-1"></i> Download
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fire Permit Modal -->
    <div class="modal fade" id="fireModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">Fire Permit</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" style="height: 80vh;">
                    <div id="firePermitPreview"></div>
                </div>
                <div class="modal-footer">
                    <div class="me-auto">
                        <button type="button" class="btn btn-danger delete-permit" data-permit-type="fire">
                            <i class="fas fa-trash-alt me-1"></i> Delete
                        </button>
                    </div>
                    <div>
                        <a id="firePermitDownload" href="#" class="btn btn-primary" download>
                            <i class="fas fa-download me-1"></i> Download
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Zoning Clearance Modal -->
    <div class="modal fade" id="zoningModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">Zoning Clearance</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" style="height: 80vh;">
                    <div id="zoningPermitPreview"></div>
                </div>
                <div class="modal-footer">
                    <div class="me-auto">
                        <button type="button" class="btn btn-danger delete-permit" data-permit-type="zoning">
                            <i class="fas fa-trash-alt me-1"></i> Delete
                        </button>
                    </div>
                    <div>
                        <a id="zoningPermitDownload" href="#" class="btn btn-primary" download>
                            <i class="fas fa-download me-1"></i> Download
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Occupancy Permit Modal -->
    <div class="modal fade" id="occupancyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">Occupancy Permit</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" style="height: 80vh;">
                    <div id="occupancyPermitPreview"></div>
                </div>
                <div class="modal-footer">
                    <div class="me-auto">
                        <button type="button" class="btn btn-danger delete-permit" data-permit-type="occupancy">
                            <i class="fas fa-trash-alt me-1"></i> Delete
                        </button>
                    </div>
                    <div>
                        <a id="occupancyPermitDownload" href="#" class="btn btn-primary" download>
                            <i class="fas fa-download me-1"></i> Download
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Barangay Clearance Modal -->
    <div class="modal fade" id="barangayModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">Barangay Clearance</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" style="height: 80vh;">
                    <div id="barangayPermitPreview"></div>
                </div>
                <div class="modal-footer">
                    <div class="me-auto">
                        <button type="button" class="btn btn-danger delete-permit" data-permit-type="barangay">
                            <i class="fas fa-trash-alt me-1"></i> Delete
                        </button>
                    </div>
                    <div>
                        <a id="barangayPermitDownload" href="#" class="btn btn-primary" download>
                            <i class="fas fa-download me-1"></i> Download
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

 <!-- Add Schedule Modal -->
<div class="modal fade" id="addScheduleModal" tabindex="-1" aria-labelledby="addScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="addScheduleModalLabel">Add New Schedule Item</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addScheduleForm" onsubmit="return validateAndSubmit(this);">
                <input type="hidden" name="project_id" value="<?php echo isset($_GET['project_id']) ? htmlspecialchars($_GET['project_id']) : ''; ?>">
                <input type="hidden" name="tasks_data" id="tasks_data">
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Select</th>
                                    <th>Task Name</th>
                                    <th>Start Date</th>
                                </tr>
                            </thead>
                            <tbody id="tasksTableBody">
                                <tr>
                                    <td colspan="3" class="text-center">Loading tasks...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <input type="hidden" name="selected_task_id" id="selected_task_id" required>
                    <div class="invalid-feedback">Please select a task.</div>
                    
                    <script>
                    // Fetch tasks when modal is shown
                    document.getElementById('addScheduleModal').addEventListener('shown.bs.modal', function() {
                        const tbody = document.getElementById('tasksTableBody');
                        
                        fetch('get_tasks.php')
                            .then(response => response.json())
                            .then(data => {
                                if (data.success && data.tasks && data.tasks.length > 0) {
                                    // Clear loading message
                                    tbody.innerHTML = '';
                                    
                                    // Add tasks to table
                                    data.tasks.forEach(task => {
                                        const row = document.createElement('tr');
                                        
                                        // Generate unique IDs for date inputs
                                        const startDateId = `start_date_${task.id}`;
                                        const endDateId = `end_date_${task.id}`;
                                        
                                        row.innerHTML = `
                                            <td class="text-center">
                                                <input type="checkbox" 
                                                       name="selected_tasks[]" 
                                                       value="${task.id}" 
                                                       class="form-check-input task-checkbox"
                                                       data-start-date-id="${startDateId}"
                                                       data-task-name="${task.name.replace(/"/g, '&quot;')}">
                                            </td>
                                            <td>${task.name}</td>
                                            <td>
                                                <input type="date" 
                                                       id="${startDateId}" 
                                                       class="form-control form-control-sm start-date"
                                                       onchange="updateTaskDates(this)"
                                                       <?php if (isset($start_date)) echo 'min="' . $start_date . '"'; ?>
                                                       <?php if (isset($deadline)) echo 'max="' . $deadline . '"'; ?>>
                                            </td>
                                        `;
                                        
                                        tbody.appendChild(row);
                                    });
                                } else {
                                    tbody.innerHTML = '<tr><td colspan="4" class="text-center">No tasks available</td></tr>';
                                    if (data.error) console.error('Error:', data.error);
                                }
                            })
                            .catch(error => {
                                console.error('Error fetching tasks:', error);
                                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error loading tasks</td></tr>';
                            });
                    });
                    

                    
                    // Function to update end date min value when start date changes
                    function updateDateValidation(startDateInput, endDateId) {
                        const endDateInput = document.getElementById(endDateId);
                        endDateInput.min = startDateInput.value;
                        
                        // If end date is before start date, clear it
                        if (endDateInput.value && new Date(endDateInput.value) < new Date(startDateInput.value)) {
                            endDateInput.value = '';
                        }
                        
                        // Update the hidden form field if this row is selected
                        const radio = startDateInput.closest('tr').querySelector('input[type="radio"]');
                        if (radio && radio.checked) {
                            document.getElementById('start_date').value = startDateInput.value;
                            document.getElementById('end_date').value = endDateInput.value;
                        }
                    }

                    
                    // Function to calculate end date (start date + 3 days)
                    function calculateEndDate(startDate) {
                        if (!startDate) return '';
                        
                        const date = new Date(startDate);
                        date.setDate(date.getDate() + 3);
                        return date.toISOString().split('T')[0];
                    }
                    
                    // Function to update task dates when start date changes
                    function updateTaskDates(startDateInput) {
                        const row = startDateInput.closest('tr');
                        const checkbox = row.querySelector('.task-checkbox');
                        
                        if (checkbox.checked) {
                            // If the task is checked, update the tasks data
                            updateTasksData();
                        }
                    }
                    
                    // Function to update the tasks data in the hidden input
                    function updateTasksData() {
                        const tasks = [];
                        const checkboxes = document.querySelectorAll('.task-checkbox:checked');
                        
                        checkboxes.forEach(checkbox => {
                            const startDateId = checkbox.dataset.startDateId;
                            const startDateInput = document.getElementById(startDateId);
                            
                            if (startDateInput && startDateInput.value) {
                                tasks.push({
                                    task_id: checkbox.value,
                                    task_name: checkbox.dataset.taskName,
                                    start_date: startDateInput.value,
                                    end_date: calculateEndDate(startDateInput.value)
                                });
                            }
                        });
                        
                        // Update the hidden input with JSON data
                        document.getElementById('tasks_data').value = JSON.stringify(tasks);
                        
                        // Log for debugging
                        console.log('Updated tasks data:', tasks);
                        return tasks;
                    }
                    
                    // Function to update end date min value when start date changes
                    function updateDateValidation(startDateInput, endDateId) {
                        const endDateInput = document.getElementById(endDateId);
                        endDateInput.min = startDateInput.value;
                        
                        // If end date is before start date, clear it
                        if (endDateInput.value && new Date(endDateInput.value) < new Date(startDateInput.value)) {
                            endDateInput.value = '';
                        }
                        
                        // Update the hidden form field if this row is selected
                        const radio = startDateInput.closest('tr').querySelector('input[type="radio"]');
                        if (radio && radio.checked) {
                            document.getElementById('start_date').value = startDateInput.value;
                            document.getElementById('end_date').value = endDateInput.value;
                        }
                    }
                    
                    // Function to validate and submit the form
                    function validateAndSubmit(form) {
                        // Update tasks data before submission
                        const tasks = updateTasksData();
                        
                        // Validate at least one task is selected
                        if (tasks.length === 0) {
                            alert('Please select at least one task');
                            return false;
                        }
                        
                        // Validate all selected tasks have a start date
                        const validTasks = [];
                        for (const task of tasks) {
                            if (!task.start_date) {
                                alert(`Please select a start date for task: ${task.task_name}`);
                                return false;
                            }
                            // Ensure all required fields are present
                            validTasks.push({
                                task_id: task.task_id,
                                task_name: task.task_name,
                                start_date: task.start_date,
                                end_date: task.end_date || calculateEndDate({value: task.start_date}),
                                description: task.description || ''
                            });
                        }
                        
                        // Update the hidden input with the validated tasks
                        document.getElementById('tasks_data').value = JSON.stringify(validTasks);
                        
                        // Submit the form data using fetch API
                        const formData = new FormData();
                        formData.append('project_id', '<?php echo isset($_GET['project_id']) ? htmlspecialchars($_GET['project_id']) : ''; ?>');
                        formData.append('tasks_data', JSON.stringify(validTasks));

                        fetch('save_schedule.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) {
                                return response.json().then(err => { throw err; });
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                // Show success toast
                                const toastHTML = `
                                    <div class="toast align-items-center text-white bg-success border-0 position-fixed top-0 end-0 m-3" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
                                        <div class="d-flex">
                                            <div class="toast-body">
                                                <i class="fas fa-check-circle me-2"></i> ${data.message || 'Schedule saved successfully!'}
                                            </div>
                                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                                        </div>
                                    </div>
                                `;
                                
                                showToast(toastHTML);
                                
                                // Close the modal
                                const modal = bootstrap.Modal.getInstance(document.getElementById('addScheduleModal'));
                                modal.hide();
                                
                                // Reload the page after a short delay to show the toast
                                setTimeout(() => {
                                    location.reload();
                                }, 1000);
                            } else {
                                throw new Error(data.message || 'Failed to save schedule');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            const errorMessage = error.message || 'An error occurred while saving the schedule';
                            const toastHTML = `
                                <div class="toast align-items-center text-white bg-danger border-0 position-fixed top-0 end-0 m-3" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
                                    <div class="d-flex">
                                        <div class="toast-body">
                                            <i class="fas fa-exclamation-circle me-2"></i> ${errorMessage}
                                        </div>
                                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                                    </div>
                                </div>
                            `;
                            showToast(toastHTML);
                        });
                        
                        // Prevent the original form submission
                        return false;
                    }
                    
                    
                    // Function to show toast notification
                    function showToast(toastHTML) {
                        // Create and show toast
                        document.body.insertAdjacentHTML('beforeend', toastHTML);
                        const toastEl = document.querySelector('.toast:last-child');
                        const toast = new bootstrap.Toast(toastEl);
                        toast.show();
                        
                        // Remove toast after it hides
                        toastEl.addEventListener('hidden.bs.toast', () => {
                            toastEl.remove();
                        });
                    }
                    </script>
                    
                    <!-- Hidden fields to store the selected task's data -->
                    <input type="hidden" id="selected_task_id" name="selected_task_id">
                    <input type="hidden" id="task_name" name="task_name">
                    <input type="hidden" id="start_date" name="start_date">
                    <input type="hidden" id="end_date" name="end_date">
                    <input type="hidden" name="status" value="Not Started">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="downpaymentModal" tabindex="-1" aria-labelledby="downpaymentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form id="downpaymentForm" class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="downpaymentModalLabel"><i class="fas fa-money-check-alt me-2"></i>Request Downpayment</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label for="downAmount" class="form-label">Downpayment Amount</label>
        <input type="number" class="form-control mb-3" id="downAmount" name="down_amount" min="1" required placeholder="Enter amount">
        <label for="downFile" class="form-label">Upload Supporting Document (optional)</label>
        <input type="file" class="form-control" id="downFile" name="down_file" accept="application/pdf,image/*">
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Send Request</button>
      </div>
    </form>
  </div>
</div>

<?php
// --- BACKEND FETCH: Employees and Positions --- //
$positions = [];
$foreman_position_ids = [];
$positions_query = "SELECT position_id, title, daily_rate FROM positions";
$positions_result = $con->query($positions_query);
if ($positions_result) {
    while ($row = $positions_result->fetch_assoc()) {
        $positions[] = $row;
        if (isset($row['title']) && strcasecmp($row['title'], 'Foreman') === 0) {
            $foreman_position_ids[] = (int)$row['position_id'];
        }
    }
}

// Fetch distinct company types for filter dropdown
$company_types = [];
$company_type_query = "SELECT DISTINCT company_type FROM employees";
$company_type_result = $con->query($company_type_query);
if ($company_type_result) {
    while ($row = $company_type_result->fetch_assoc()) {
        $company_types[] = $row['company_type'];
    }
}

// Fetch employees who are not currently working on any project or are already assigned to this project
$employees = [];
$availability_condition = "e.employee_id NOT IN (
                       SELECT employee_id 
                       FROM project_add_employee 
                       WHERE status = 'Working' AND project_id != '$project_id'
                   )";
if (!empty($foreman_position_ids)) {
    $foreman_ids_list = implode(',', $foreman_position_ids);
    $availability_condition = "($availability_condition OR e.position_id IN ($foreman_ids_list))";
}
$employees_query = "SELECT e.employee_id, e.first_name, e.last_name, e.position_id, e.contact_number, e.company_type 
                   FROM employees e
                   WHERE $availability_condition
                   AND e.employee_id NOT IN (
                       SELECT employee_id 
                       FROM project_add_employee 
                       WHERE project_id = '$project_id' AND status = 'Working'
                   )";
$employees_result = $con->query($employees_query);
if ($employees_result) {
    while ($row = $employees_result->fetch_assoc()) {
        $employees[] = $row;
    }
}
?>

<!-- Add Employee Modal -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1" role="dialog" aria-labelledby="addEmployeeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header d-flex align-items-center">
        <h5 class="modal-title me-auto" id="addEmployeeModalLabel">Add Employee(s)</h5>
        <button type="button"
                class="btn btn-success btn-sm me-2"
                data-bs-toggle="modal"
                data-bs-target="#quickAddEmployeeModal">
          <i class="fas fa-user-plus me-1"></i> Register New Employee
        </button>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="save_project_employee_estimation.php" id="addEmployeeTableForm">
        <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
        <div class="modal-body">
          <!-- Filter Controls -->
          <div class="row mb-3 g-3">
            <div class="col-12">
              <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control border-start-0" id="employeeSearchInput" placeholder="Search employees by name..." onkeyup="filterEmployeeTable()">
              </div>
            </div>
            <div class="col-md-6">
              <label for="filterPosition" class="form-label fw-bold">Filter by Position</label>
              <select class="form-select" id="filterPosition" onchange="filterEmployeeTable()">
                <option value="">All Positions</option>
                <?php foreach ($positions as $pos): ?>
                  <option value="<?php echo htmlspecialchars($pos['title']); ?>"><?php echo htmlspecialchars($pos['title']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label for="filterCompanyType" class="form-label fw-bold">Filter by Company Type</label>
              <select class="form-select" id="filterCompanyType" onchange="filterEmployeeTable()">
                <option value="">All Company Types</option>
                <?php foreach ($company_types as $type): ?>
                  <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle" id="employeeChecklistTable">
              <thead class="table-light">
                <tr>
                  <th style="width:5%;"><input type="checkbox" id="checkAll" onclick="toggleAllEmployees(this)"></th>
                  <th>Full Name</th>
                  <th>Last Name</th>
                  <th>Company Type</th>
                  <th>Position</th>
                  <th>Contact Number</th>
                  <th>Daily Rate</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $positions_map = [];
                foreach ($positions as $pos) {
                  $positions_map[$pos['position_id']] = [
                    'title' => $pos['title'],
                    'daily_rate' => $pos['daily_rate']
                  ];
                }
                
                foreach ($employees as $emp): 
                  $pos_id = $emp['position_id'];
                  $position_title = $positions_map[$pos_id]['title'] ?? 'N/A';
                  $daily_rate = isset($positions_map[$pos_id]) ? number_format($positions_map[$pos_id]['daily_rate'],2) : 'N/A';
                  $is_available = $employee_availability[$emp['employee_id']]['is_available'] ?? true;
                  $assigned_projects = $employee_availability[$emp['employee_id']]['assigned_projects'] ?? '';
                  $status_class = $is_available ? 'success' : 'warning';
                  $status_text = $is_available ? 'Available' : 'Assigned to Project(s)';
                ?>
                  <tr class="employee-row" 
                      data-position="<?php echo htmlspecialchars($position_title); ?>" 
                      data-company-type="<?php echo htmlspecialchars($emp['company_type']); ?>">
                    <td>
                      <input type="checkbox" name="selected_employees[]" 
                             value="<?php echo $emp['employee_id']; ?>" 
                             class="estimation-employee-check"
                             <?php echo !$is_available ? 'disabled' : ''; ?>>
                    </td>
                    <td><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($emp['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($emp['company_type']); ?></td>
                    <td><?php echo htmlspecialchars($position_title); ?></td>
                    <td><?php echo htmlspecialchars($emp['contact_number']); ?></td>
                    <td><?php echo $daily_rate; ?></td>
                    <td>
                      <span class="badge bg-<?php echo $status_class; ?>" 
                            data-bs-toggle="<?php echo !$is_available ? 'tooltip' : ''; ?>" 
                            title="<?php echo !$is_available ? htmlspecialchars($assigned_projects) : ''; ?>">
                        <?php echo $status_text; ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php if (empty($employees)): ?>
              <div class="alert alert-info mt-3">No available employees found.</div>
            <?php endif; ?>
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Add Selected Employee(s)</button>
        </div>
      </form>
    </div>
  </div>
<!-- Quick Add Employee Modal -->
<div class="modal fade" id="quickAddEmployeeModal" tabindex="-1" aria-labelledby="quickAddEmployeeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="quickAddEmployeeModalLabel">Register New Employee</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="quickAddEmployeeForm">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-bold">First Name</label>
              <input type="text" name="first_name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Last Name</label>
              <input type="text" name="last_name" class="form-control" required>
            </div>
          </div>
          <div class="row g-3 mt-2">
            <div class="col-md-6">
              <label class="form-label fw-bold">Position</label>
              <select name="position_id" class="form-select" required>
                <option value="">Select Position</option>
                <?php foreach ($positions as $pos): ?>
                  <option value="<?php echo htmlspecialchars($pos['position_id']); ?>">
                    <?php echo htmlspecialchars($pos['title']); ?> (₱<?php echo number_format($pos['daily_rate'], 2); ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Employee Type</label>
              <select name="company_type" class="form-select" required>
                <option value="">Select Employee Type</option>
                <option value="Company Employee">Company Employee</option>
                <option value="Outsourced Personnel">Outsourced Personnel</option>
              </select>
            </div>
          </div>
          <div class="row g-3 mt-2">
            <div class="col-md-6">
              <label class="form-label fw-bold">Contact Number</label>
              <input type="text" name="contact_number" class="form-control" placeholder="09XXXXXXXXX">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Facebook Link (Optional)</label>
              <input type="url" name="fb_link" class="form-control" placeholder="https://facebook.com/username">
            </div>
          </div>
          <div class="mt-3">
            <small class="text-muted">
              New employees will be saved to your employee list and will appear in this table after the page reloads.
            </small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">
            <i class="fas fa-save me-1"></i> Save Employee
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function toggleAllEmployees(source) {
  let checkboxes = document.querySelectorAll('.estimation-employee-check');
  for (let c of checkboxes) c.checked = source.checked;
}

// JS Filter Function
function filterEmployeeTable() {
  var searchTerm = document.getElementById('employeeSearchInput').value.toLowerCase();
  var position = document.getElementById('filterPosition').value.toLowerCase();
  var companyType = document.getElementById('filterCompanyType').value.toLowerCase();
  
  var rows = document.querySelectorAll('#employeeChecklistTable tbody tr');
  
  rows.forEach(function(row) {
    var rowPosition = row.getAttribute('data-position') ? row.getAttribute('data-position').toLowerCase() : '';
    var rowCompanyType = row.getAttribute('data-company-type') ? row.getAttribute('data-company-type').toLowerCase() : '';
    var employeeName = row.cells[1].textContent.toLowerCase(); // Full name is in the second cell (index 1)
    var lastName = row.cells[2].textContent.toLowerCase(); // Last name is in the third cell (index 2)
    
    var searchMatch = searchTerm === '' || 
                     employeeName.includes(searchTerm) || 
                     lastName.includes(searchTerm);
    var positionMatch = position === '' || rowPosition.includes(position);
    var companyTypeMatch = companyType === '' || rowCompanyType.includes(companyType);
    
    if (searchMatch && positionMatch && companyTypeMatch) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
}
</script>

<script>
// Quick add employee via AJAX
document.addEventListener('DOMContentLoaded', function() {
  const quickForm = document.getElementById('quickAddEmployeeForm');
  if (!quickForm) return;

  quickForm.addEventListener('submit', async function(e) {
    e.preventDefault();

    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn ? submitBtn.innerHTML : '';

    const formData = new FormData(this);

    try {
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';
      }

      const response = await fetch('quick_add_employee.php', {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (!data.success) {
        alert(data.message || 'Failed to add employee.');
        return;
      }

      alert('Employee registered successfully. The list will now refresh.');

      const modalEl = document.getElementById('quickAddEmployeeModal');
      if (modalEl && window.bootstrap) {
        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.hide();
      }

      window.location.reload();
    } catch (error) {
      console.error('Error adding employee:', error);
      alert('An error occurred while adding the employee. Please try again.');
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
      }
    }
  });
});
</script>