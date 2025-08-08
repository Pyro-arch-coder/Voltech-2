<!-- Step 1: Blueprint Upload -->
<div class="step-content" id="step1">
<script>
document.addEventListener('DOMContentLoaded', function() {
    var pidInput = document.getElementById('project_id');
    if (pidInput) {
        console.log('step1_blueprint.php hidden project_id value:', pidInput.value);
    } else {
        console.log('step1_blueprint.php: No project_id hidden input found');
    }
});
</script>
    <h4 class="mb-4">Step 1: Upload Blueprint</h4>
    <div class="mb-3">
        <label class="form-label">Project Name</label>
        <input type="text" class="form-control" name="project_name" required value="<?php echo isset($current_project_name) ? htmlspecialchars($current_project_name) : ''; ?>" <?php echo isset($current_project_name) ? 'readonly' : ''; ?>>
    </div>
    <div class="mb-3">
        <label class="form-label">Blueprint Files (PDF, JPG, PNG, DWG)</label>
        <input type="file" class="form-control" name="blueprint_files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.dwg" required>
        <div class="form-text">Upload all relevant blueprint files for the project.</div>
    </div>
    
    <?php if (isset($_GET['project_id'])): ?>
        <input type="hidden" name="project_id" id="project_id" value="<?php echo intval($_GET['project_id']); ?>">
    <?php endif; ?>
    <!-- Upload Button -->
    <div class="mb-3">
        <button type="button" class="btn btn-primary me-2" id="uploadBtn">
            <i class="fas fa-upload"></i> Upload Files
        </button>
    <?php if (isset($_GET['project_id'])): ?>
            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#blueprintsModal">
                <i class="fas fa-eye"></i> View Blueprints
            </button>
    <?php endif; ?>
        <div id="uploadStatus"></div>
    </div>
    
    <div class="d-flex justify-content-end mt-4">
        <button type="button" class="btn btn-primary next-step<?php echo (isset($_GET['project_id']) ? '' : ' disabled'); ?>" id="step1NextBtn" data-next="2">
            Next <i class="fas fa-arrow-right"></i>
        </button>
    </div> 
</div> 