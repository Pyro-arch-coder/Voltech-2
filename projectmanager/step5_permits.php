<div class="step-content d-none" id="step5">
    <h4 class="mb-4">Step 5: Permits</h4>

    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        Please upload the required permits below.
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Upload Permits</h5>
            <input type="hidden" id="projectIdInput" value="<?php echo $project_id; ?>">

            <div class="row">
                <!-- LGU Clearance -->
                <div class="col-md-4 mb-3">
                    <label class="form-label">LGU Clearance (PDF/Image)</label>
                    <input type="file" class="form-control mb-2" id="lguClearance" name="lgu_clearance" accept=".pdf,image/*">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary" id="uploadLguBtn">
                            <i class="fas fa-upload me-1"></i> Upload
                        </button>
                        <button type="button" class="btn btn-sm btn-info" id="viewLguBtn" disabled>
                            <i class="fas fa-eye me-1"></i> View
                        </button>
                    </div>
                </div>

                <!-- Fire Permit -->
                <div class="col-md-4 mb-3">
                    <label class="form-label">Fire Permit (PDF/Image)</label>
                    <input type="file" class="form-control mb-2" id="firePermit" name="fire_permit" accept=".pdf,image/*">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary" id="uploadFireBtn">
                            <i class="fas fa-upload me-1"></i> Upload
                        </button>
                        <button type="button" class="btn btn-sm btn-info" id="viewFireBtn" disabled>
                            <i class="fas fa-eye me-1"></i> View
                        </button>
                    </div>
                </div>

                <!-- Zoning Clearance -->
                <div class="col-md-4 mb-3">
                    <label class="form-label">Zoning Clearance (PDF/Image)</label>
                    <input type="file" class="form-control mb-2" id="zoningClearance" name="zoning_clearance" accept=".pdf,image/*">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary" id="uploadZoningBtn">
                            <i class="fas fa-upload me-1"></i> Upload
                        </button>
                        <button type="button" class="btn btn-sm btn-info" id="viewZoningBtn" disabled>
                            <i class="fas fa-eye me-1"></i> View
                        </button>
                    </div>
                </div>

                <!-- Occupancy Permit -->
                <div class="col-md-4 mb-3">
                    <label class="form-label">Occupancy Permit (PDF/Image)</label>
                    <input type="file" class="form-control mb-2" id="occupancyPermit" name="occupancy_permit" accept=".pdf,image/*">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary" id="uploadOccupancyBtn">
                            <i class="fas fa-upload me-1"></i> Upload
                        </button>
                        <button type="button" class="btn btn-sm btn-info" id="viewOccupancyBtn" disabled>
                            <i class="fas fa-eye me-1"></i> View
                        </button>
                    </div>
                </div>

                <!-- Barangay Clearance -->
                <div class="col-md-4 mb-3">
                    <label class="form-label">Barangay Clearance (PDF/Image)</label>
                    <input type="file" class="form-control mb-2" id="barangayClearance" name="barangay_clearance" accept=".pdf,image/*">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary" id="uploadBarangayBtn">
                            <i class="fas fa-upload me-1"></i> Upload
                        </button>
                        <button type="button" class="btn btn-sm btn-info" id="viewBarangayBtn" disabled>
                            <i class="fas fa-eye me-1"></i> View
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- LGU Clearance Modal -->
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
    <!-- Success Modal -->
   
    
    <div class="d-flex justify-content-between mt-4">
        <button type="button" class="btn btn-secondary prev-step" data-prev="4">
            <i class="fas fa-arrow-left me-1"></i> Previous
        </button>
        <button type="button" class="btn btn-primary next-step" data-next="6" <?php echo $canProceed ? '' : 'disabled'; ?>>
            Next <i class="fas fa-arrow-right ms-1"></i>
        </button>
    </div>
</div>
