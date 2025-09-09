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
            <form id="addScheduleForm" onsubmit="return false;">
                <input type="hidden" name="project_id" value="<?php echo isset($_GET['project_id']) ? htmlspecialchars($_GET['project_id']) : ''; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="task_name" class="form-label">Task Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="task_name" name="task_name" required>
                        <div class="invalid-feedback">Please provide a task name.</div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   <?php if (isset($start_date)) echo 'min="' . $start_date . '"'; ?> 
                                   <?php if (isset($deadline)) echo 'max="' . $deadline . '"'; ?> 
                                   required>
                            <div class="invalid-feedback">Please select a start date.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   <?php if (isset($start_date)) echo 'min="' . $start_date . '"'; ?> 
                                   <?php if (isset($deadline)) echo 'max="' . $deadline . '"'; ?> 
                                   required>
                            <div class="invalid-feedback">Please select an end date after the start date.</div>
                        </div>
                    </div>
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