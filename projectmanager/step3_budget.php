<?php
// Get project ID
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

// Get status from DB
$status = '';
if ($project_id > 0) {
    require_once '../config.php';
    $stmt = $con->prepare("SELECT status FROM project_budget_approval WHERE project_id = ?");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $status = strtolower($row['status']);
    }
    $stmt->close();
}
?>


<div class="step-content d-none" id="step3">
    <h4 class="mb-4">Step 3: Budget Approval</h4>

    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        Please upload the cost estimation PDF for budget approval. The next button will be enabled only after the budget is approved.
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Upload Budget Estimation</h5>
            <input type="hidden" id="projectIdInput" value="<?php echo isset($_GET['project_id']) ? intval($_GET['project_id']) : 0; ?>">

            <div class="mb-3">
                <label class="form-label">Cost Estimation Document (PDF)</label>
                <input type="file" class="form-control" id="estimationPdfInput" name="estimation_pdf" accept=".pdf">
            </div>

            <button type="button" id="uploadBudgetBtn" class="btn btn-primary">
                <i class="fas fa-upload me-1"></i> Upload
            </button>
            <div id="uploadStatus" class="mt-2"></div>
            
            <!-- View Uploaded PDF Button -->
            <div id="viewPdfContainer" class="mt-3">
                <button type="button" id="viewPdfBtn" class="btn btn-info" style="min-width: 180px;">
                    <i class="fas fa-eye me-1"></i> View Uploaded PDF
                </button>
                <div id="pdfStatus" class="form-text mt-1">No PDF uploaded yet</div>
            </div>
        </div>
    </div>

    <!-- PDF Viewer Modal -->
    <div class="modal fade" id="pdfViewerModal" tabindex="-1" aria-labelledby="pdfViewerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pdfViewerModalLabel">Budget Estimation PDF</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <iframe id="pdfViewer" src="" width="100%" height="600px" style="border: none;"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a id="downloadPdfBtn" href="#" class="btn btn-primary" download>
                        <i class="fas fa-download me-1"></i> Download
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mt-4">
        <button type="button" class="btn btn-secondary prev-step" data-prev="2">
            <i class="fas fa-arrow-left me-1"></i> Previous
        </button>
        <button type="button" class="btn btn-primary next-step" data-next="4"
            <?php echo ($status == 'pending') ? 'disabled' : ''; ?>>
            Next <i class="fas fa-arrow-right ms-1"></i>
        </button>
    </div>
</div>
