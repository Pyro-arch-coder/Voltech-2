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
            <h5 class="card-title mb-4">Budget & Cost Estimation</h5>
            <input type="hidden" id="projectIdInput" value="<?php echo isset($_GET['project_id']) ? intval($_GET['project_id']) : 0; ?>">

            <div class="row">
                <!-- Budget Details Column -->
                <div class="col-md-6 mb-4 mb-md-0">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-3 text-muted">Budget Details</h6>
                            <?php
                            // Check if budget already exists
                            $existingBudget = '';
                            $isBudgetSubmitted = false;
                            if ($project_id > 0) {
                                $budgetStmt = $con->prepare("SELECT budget, status FROM project_budget_approval WHERE project_id = ?");
                                $budgetStmt->bind_param("i", $project_id);
                                $budgetStmt->execute();
                                $budgetResult = $budgetStmt->get_result();
                                if ($budgetRow = $budgetResult->fetch_assoc()) {
                                    $existingBudget = $budgetRow['budget'];
                                    $isBudgetSubmitted = true;
                                }
                                $budgetStmt->close();
                            }
                            ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Budget Amount (₱)</label>
                                <div class="input-group mb-2">
                                    <span class="input-group-text">₱</span>
                                    <input type="text" class="form-control form-control-lg" id="budgetInput" name="budget" 
                                           value="<?php echo $existingBudget; ?>"
                                           placeholder="1" pattern="1\d{8}" inputmode="numeric" 
                                           oninput="this.value=this.value.replace(/[^0-9]/g,'').replace(/^[^1]/, '1').slice(0,9);"
                                           <?php echo $isBudgetSubmitted ? 'readonly' : ''; ?>>
                                </div>
                            </div>
                            <div class="text-center mt-3">
                                <button type="button" id="requestBudgetBtn" class="btn btn-<?php echo $isBudgetSubmitted ? 'secondary' : 'success'; ?>"
                                    <?php echo $isBudgetSubmitted ? 'disabled' : ''; ?>>
                                    <i class="fas fa-paper-plane me-1"></i> 
                                    <?php echo $isBudgetSubmitted ? 'Budget Submitted' : 'Request Budget'; ?>
                                </button>
                                <div class="form-text">
                                    <?php 
                                    if ($isBudgetSubmitted) {
                                        echo '<i class="fas fa-info-circle text-success"></i> Budget already submitted';
                                    } else {
                                        echo 'Enter 9 digits starting with 1 (e.g., 100000000)';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upload Cost Estimation Column -->
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            <h6 class="card-subtitle mb-3 text-muted">Upload Cost Estimation</h6>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Cost Estimation Document (PDF)</label>
                                <div class="input-group">
                                    <input type="file" class="form-control" id="estimationPdfInput" name="estimation_pdf" accept=".pdf">
                                    <button type="button" id="uploadBudgetBtn" class="btn btn-primary">
                                        <i class="fas fa-upload me-1"></i> Upload
                                    </button>
                                </div>
                                <div id="uploadStatus" class="text-muted small"></div>
                            </div>
                            
                            <!-- View Uploaded PDF Section -->
                            <div id="viewPdfContainer" class="text-center mt-4">
                                <button type="button" id="viewPdfBtn" class="btn btn-outline-info w-100" style="max-width: 200px;">
                                    <i class="fas fa-eye me-2"></i> View Uploaded PDF
                                </button>
                                <div id="pdfStatus" class="text-muted small mt-2">No PDF uploaded yet</div>
                            </div>
                        </div>
                    </div>
                </div>
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
                    <button type="button" id="deletePdfModalBtn" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i> Delete
                    </button>
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



<style>
/* Budget input styling */
#budgetInput {
    font-size: 1.1rem;
    font-weight: 500;
    color: #2c3e50;
}

#budgetInput:focus {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

/* Hide number input arrows for all browsers */
input[type=number]::-webkit-outer-spin-button,
input[type=number]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

/* Firefox */
input[type=number] {
    -moz-appearance: textfield;
}
</style>

