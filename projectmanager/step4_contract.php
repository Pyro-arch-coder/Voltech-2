<?php
// Get project ID
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

// Check if both required contracts exist
$hasYourSigned = false;
$hasClientSigned = false;

if ($project_id > 0) {
    require_once '../config.php';
    
    // Check for your signed contract
    $stmt = $con->prepare("SELECT COUNT(*) as count FROM project_contracts WHERE project_id = ? AND contract_type = 'yoursigned'");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $hasYourSigned = ($row['count'] > 0);
    }
    $stmt->close();
    
    // Check for client signed contract
    $stmt = $con->prepare("SELECT COUNT(*) as count FROM project_contracts WHERE project_id = ? AND contract_type = 'clientsigned'");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $hasClientSigned = ($row['count'] > 0);
    }
    $stmt->close();
}

// Check if both contracts are present
$canProceed = $hasYourSigned && $hasClientSigned;
?>

<div class="step-content d-none" id="step4">
    <h4 class="mb-4">Step 4: Contract Signing</h4>

    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        Please upload the required contract PDFs for signing.
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Upload Contracts</h5>
            <input type="hidden" id="projectIdInput" value="<?php echo $project_id; ?>">

            <div class="row">
                <!-- Original Contract -->
                <div class="col-md-4">
                    <label class="form-label">Original Contract (PDF)</label>
                    <input type="file" class="form-control mb-2" id="originalContract" name="original_contract" accept=".pdf">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary" id="uploadOriginalBtn">
                            <i class="fas fa-upload me-1"></i> Upload
                        </button>
                        <button type="button" class="btn btn-sm btn-info" id="viewOriginalBtn" disabled>
                            <i class="fas fa-eye me-1"></i> View
                        </button>
                    </div>
                </div>

                <!-- Your Contract -->
                <div class="col-md-4">
                    <label class="form-label">Your Signed Contract (PDF)</label>
                    <input type="file" class="form-control mb-2" id="yourContract" name="your_contract" accept=".pdf">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-success" id="uploadYourBtn">
                            <i class="fas fa-upload me-1"></i> Upload
                        </button>
                        <button type="button" class="btn btn-sm btn-info" id="viewYourBtn" disabled>
                            <i class="fas fa-eye me-1"></i> View
                        </button>
                    </div>
                </div>

                <!-- Client Contract -->
                <div class="col-md-4">
                    <label class="form-label">Client Signed Contract (PDF)</label>
                    <input type="file" class="form-control mb-2" id="clientContract" name="client_contract" accept=".pdf">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-success" id="uploadClientBtn">
                            <i class="fas fa-upload me-1"></i> Upload
                        </button>
                        <button type="button" class="btn btn-sm btn-info" id="viewClientContractBtn" disabled>
                            <i class="fas fa-eye me-1"></i> View
                        </button>
                    </div>
                </div>
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

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i> Success</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Contract uploaded successfully!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>


    <div class="d-flex justify-content-between mt-4">
        <button type="button" class="btn btn-secondary prev-step" data-prev="3">
            <i class="fas fa-arrow-left me-1"></i> Previous
        </button>
        <button type="button" class="btn btn-primary next-step" data-next="5" <?php echo $canProceed ? '' : 'disabled'; ?>>
            Next <i class="fas fa-arrow-right ms-1"></i>
        </button>
    </div>
    
    <?php if (!$canProceed): ?>
    <div class="alert alert-warning mt-3">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php 
        if (!$hasYourSigned && !$hasClientSigned) {
            echo 'Please upload Your Signed Contract and the Client Signed Contract to proceed.';
        } elseif (!$hasYourSigned) {
            echo 'Please upload Your Signed Contract to proceed.';
        } elseif (!$hasClientSigned) {
            echo 'Please upload the Client Signed Contract or wait for the client to upload it.';
        }
        ?>
    </div>
    <?php endif; ?>
</div>
