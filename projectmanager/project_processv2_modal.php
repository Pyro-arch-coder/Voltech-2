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