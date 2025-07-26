<div class="modal fade" id="editProjectModal" tabindex="-1" role="dialog" aria-labelledby="editProjectModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editProjectModalLabel">Edit Project</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="">
        <div class="modal-body">
          <div class="form-group">
            <label for="projectname">Project Name</label>
            <input type="text" class="form-control" id="projectname" name="projectname" value="<?php echo $project['project']; ?>" required>
          </div>
          <div class="form-group">
            <label for="projectlocation">Location</label>
            <input type="text" class="form-control" id="projectlocation" name="projectlocation" value="<?php echo $project['location']; ?>" required>
          </div>
          <div class="form-group">
            <label for="projectbudget">Budget (₱)</label>
            <input type="number" step="0.01" class="form-control" id="projectbudget" name="projectbudget" value="<?php echo $project['budget']; ?>" required>
          </div>
          <div class="form-group">
            <label for="projectstartdate">Start Date</label>
            <input type="date" class="form-control" id="projectstartdate" name="projectstartdate" value="<?php echo $project['start_date']; ?>" required>
          </div>
          <div class="form-group">
            <label for="projectdeadline">Deadline</label>
            <input type="date" class="form-control" id="projectdeadline" name="projectdeadline" value="<?php echo $project['deadline']; ?>" required>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="update_project" class="btn btn-primary">Save changes</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Add Materials Modal -->
<div class="modal fade" id="addMaterialsModal" tabindex="-1" role="dialog" aria-labelledby="addMaterialsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addMaterialsModalLabel">Add Materials</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" id="addMaterialForm" action="project_actual.php?id=<?php echo htmlspecialchars($_GET['id']); ?>">
        <div class="modal-body">
          <input type="hidden" name="add_project_material" value="1">
          <div class="form-group">
            <label for="materialName">Material Name</label>
            <select class="form-control" id="materialName" name="materialName" required>
              <option value="" disabled selected>Select Material</option>
              <?php 
              $materials_available = [];
              $materials_query = mysqli_query($con, "SELECT m.*, 
                  (SELECT COALESCE(SUM(pam.quantity), 0) 
                   FROM project_add_materials pam 
                   WHERE pam.material_id = m.id) as used_quantity
                FROM materials m 
                WHERE m.status = 'Available' 
                ORDER BY m.material_name ASC");
              
              while ($mat = mysqli_fetch_assoc($materials_query)) {
                  $available_qty = $mat['quantity'] - $mat['used_quantity'];
                  $materials_available[] = [
                      'id' => $mat['id'],
                      'material_name' => $mat['material_name'],
                      'material_price' => $mat['material_price'],
                      'labor_other' => $mat['labor_other'],
                      'unit' => $mat['unit'],
                      'available_quantity' => $available_qty
                  ];
                  
                  $is_available = $available_qty > 0;
                  $qty_display = $is_available ? "(Available: $available_qty)" : "(Out of stock)";
                  $disabled = !$is_available ? 'disabled' : '';
                  $style = !$is_available ? 'color: #999;' : '';
              ?>
                <option value="<?php echo htmlspecialchars($mat['id']); ?>"
                  data-unit="<?php echo htmlspecialchars($mat['unit']); ?>"
                  data-price="<?php echo htmlspecialchars($mat['material_price']); ?>"
                  data-labor="<?php echo htmlspecialchars($mat['labor_other']); ?>"
                  data-name="<?php echo htmlspecialchars($mat['material_name']); ?>"
                  data-available-qty="<?php echo $available_qty; ?>"
                  <?php echo $disabled; ?> 
                  style="<?php echo $style; ?>">
                  <?php echo htmlspecialchars($mat['material_name']) . ' (₱' . number_format(floatval($mat['material_price']), 2) . ') ' . $qty_display; ?>
                </option>
              <?php } ?>
            </select>
            <small id="availableQtyHelp" class="form-text text-muted">
              Available quantity is shown in parentheses
            </small>
            <input type="hidden" id="materialNameText" name="materialNameText">
          </div>
          <div class="form-group">
            <label for="materialQty">Quantity</label>
            <div class="input-group">
              <input type="number" class="form-control" id="materialQty" name="materialQty" min="1" required>
              <span class="input-group-text" id="maxQtyDisplay">Max: 0</span>
            </div>
            <small id="qtyHelp" class="form-text text-danger d-none">
              <i class="fas fa-exclamation-circle"></i> Quantity exceeds available stock
            </small>
          </div>
          <div class="form-group">
            <label for="materialUnit">Unit</label>
            <input type="text" class="form-control" id="materialUnit" name="materialUnit" readonly>
          </div>
          <div class="form-group">
            <label for="materialPrice">Material Price</label>
            <input type="text" class="form-control" id="materialPrice" name="materialPrice" readonly>
          </div>
          <div class="form-group">
            <label for="laborOther">Labor/Other</label>
            <input type="text" class="form-control" id="laborOther" name="laborOther" readonly>
          </div>
          <div class="form-group">
            <label for="materialTotal">Total Price</label>
            <input type="text" class="form-control" id="materialTotal" name="materialTotal" readonly>
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Add Material</button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="changePasswordModalLabel">Change Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="changePasswordForm">
          <div class="mb-3">
            <label for="current_password" class="form-label">Current Password</label>
            <input type="password" class="form-control" id="current_password" name="current_password" required>
          </div>
          <div class="mb-3">
            <label for="new_password" class="form-label">New Password</label>
            <input type="password" class="form-control" id="new_password" name="new_password" required>
          </div>
          <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm New Password</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
          </div>
          <div id="changePasswordFeedback" class="mb-2"></div>
          <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-success">Change Password</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<!-- View Permits Modal -->
<div class="modal fade" id="viewPermitsModal" tabindex="-1" aria-labelledby="viewPermitsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewPermitsModalLabel">Project Permits & Clearances</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-4">
          <div class="col-md-6 col-lg-3 text-center">
            <div class="mb-2 fw-bold">LGU Permit</div>
            <?php if (!empty($project['file_photo_lgu'])): ?>
              <img src="../uploads/project_files/<?php echo htmlspecialchars($project['file_photo_lgu']); ?>" class="img-fluid rounded border mb-2 permit-thumb" style="width:200px; height:200px; object-fit:cover; cursor:pointer;" data-bs-toggle="modal" data-bs-target="#permitImageModal" data-img="../uploads/project_files/<?php echo htmlspecialchars($project['file_photo_lgu']); ?>">
            <?php else: ?>
              <div class="text-muted">Not uploaded</div>
            <?php endif; ?>
          </div>
          <div class="col-md-6 col-lg-3 text-center">
            <div class="mb-2 fw-bold">Barangay Clearance</div>
            <?php if (!empty($project['file_photo_barangay'])): ?>
              <img src="../uploads/project_files/<?php echo htmlspecialchars($project['file_photo_barangay']); ?>" class="img-fluid rounded border mb-2 permit-thumb" style="width:200px; height:200px; object-fit:cover; cursor:pointer;" data-bs-toggle="modal" data-bs-target="#permitImageModal" data-img="../uploads/project_files/<?php echo htmlspecialchars($project['file_photo_barangay']); ?>">
            <?php else: ?>
              <div class="text-muted">Not uploaded</div>
            <?php endif; ?>
          </div>
          <div class="col-md-6 col-lg-3 text-center">
            <div class="mb-2 fw-bold">Fire Clearance</div>
            <?php if (!empty($project['file_photo_fire'])): ?>
              <img src="../uploads/project_files/<?php echo htmlspecialchars($project['file_photo_fire']); ?>" class="img-fluid rounded border mb-2 permit-thumb" style="width:200px; height:200px; object-fit:cover; cursor:pointer;" data-bs-toggle="modal" data-bs-target="#permitImageModal" data-img="../uploads/project_files/<?php echo htmlspecialchars($project['file_photo_fire']); ?>">
            <?php else: ?>
              <div class="text-muted">Not uploaded</div>
            <?php endif; ?>
          </div>
          <div class="col-md-6 col-lg-3 text-center">
            <div class="mb-2 fw-bold">Occupancy Permit</div>
            <?php if (!empty($project['file_photo_occupancy'])): ?>
              <img src="../uploads/project_files/<?php echo htmlspecialchars($project['file_photo_occupancy']); ?>" class="img-fluid rounded border mb-2 permit-thumb" style="width:200px; height:200px; object-fit:cover; cursor:pointer;" data-bs-toggle="modal" data-bs-target="#permitImageModal" data-img="../uploads/project_files/<?php echo htmlspecialchars($project['file_photo_occupancy']); ?>">
            <?php else: ?>
              <div class="text-muted">Not uploaded</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Permit Image Preview Modal -->
<div class="modal fade" id="permitImageModal" tabindex="-1" aria-labelledby="permitImageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="permitImageModalLabel">Permit Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <img id="permitImageModalImg" src="" alt="Permit Preview" style="max-width:100%; max-height:80vh; border-radius:8px;">
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="uploadFilesModal" tabindex="-1" aria-labelledby="uploadFilesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="uploadFilesModalLabel">Upload Project Permits & Clearances</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info py-2 mb-3" style="font-size: 0.97rem;">
          Upload images for the following required permits and clearances for this project:
          <ul class="mb-0 ps-3">
            <li>LGU Permit</li>
            <li>Barangay Clearance</li>
            <li>Fire Clearance</li>
            <li>Occupancy Permit</li>
          </ul>
        </div>
        <form class="mb-3 single-upload-form" method="POST" action="upload_lgu.php" enctype="multipart/form-data">
          <input type="hidden" name="project_id" id="modal_project_id_lgu" value="<?php echo $project_id; ?>">
          <img id="preview_file_photo_lgu" class="img-thumbnail mb-2 d-none" style="max-width: 220px; max-height: 220px; display:block; margin:auto;" />
          <label class="form-label">LGU Permit</label>
          <input type="file" class="form-control file-input-preview" name="file_photo" accept="image/*">
          <div class="invalid-feedback">Please select a photo before uploading.</div>
          <button type="submit" class="btn btn-success mt-2">Upload LGU Permit</button>
        </form>
        <form class="mb-3 single-upload-form" method="POST" action="upload_barangay.php" enctype="multipart/form-data">
          <input type="hidden" name="project_id" id="modal_project_id_barangay" value="<?php echo $project_id; ?>">
          <img id="preview_file_photo_barangay" class="img-thumbnail mb-2 d-none" style="max-width: 220px; max-height: 220px; display:block; margin:auto;" />
          <label class="form-label">Barangay Clearance</label>
          <input type="file" class="form-control file-input-preview" name="file_photo" accept="image/*">
          <div class="invalid-feedback">Please select a photo before uploading.</div>
          <button type="submit" class="btn btn-success mt-2">Upload Barangay Clearance</button>
        </form>
        <form class="mb-3 single-upload-form" method="POST" action="upload_fire.php" enctype="multipart/form-data">
          <input type="hidden" name="project_id" id="modal_project_id_fire" value="<?php echo $project_id; ?>">
          <img id="preview_file_photo_fire" class="img-thumbnail mb-2 d-none" style="max-width: 220px; max-height: 220px; display:block; margin:auto;" />
          <label class="form-label">Fire Clearance</label>
          <input type="file" class="form-control file-input-preview" name="file_photo" accept="image/*">
          <div class="invalid-feedback">Please select a photo before uploading.</div>
          <button type="submit" class="btn btn-success mt-2">Upload Fire Clearance</button>
        </form>
        <form class="mb-3 single-upload-form" method="POST" action="upload_occupancy.php" enctype="multipart/form-data">
          <input type="hidden" name="project_id" id="modal_project_id_occupancy" value="<?php echo $project_id; ?>">
          <img id="preview_file_photo_occupancy" class="img-thumbnail mb-2 d-none" style="max-width: 220px; max-height: 220px; display:block; margin:auto;" />
          <label class="form-label">Occupancy Permit</label>
          <input type="file" class="form-control file-input-preview" name="file_photo" accept="image/*">
          <div class="invalid-feedback">Please select a photo before uploading.</div>
          <button type="submit" class="btn btn-success mt-2">Upload Occupancy Permit</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Feedback Modal (Unified for Success/Error) -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-body">
        <span id="feedbackIcon" style="font-size: 3rem;"></span>
        <h4 id="feedbackTitle"></h4>
        <p id="feedbackMessage"></p>
        <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>


<!-- Add Employee Modal -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1" role="dialog" aria-labelledby="addEmployeeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addEmployeeModalLabel">Add Employee</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post">
        <div class="modal-body">
          <input type="hidden" name="add_project_employee" value="1">
          <div class="form-group mb-3">
            <label for="employeeType">Employee Type</label>
            <select class="form-control" id="employeeType" name="employeeType" required onchange="filterEmployees(this.value)">
              <option value="" disabled selected>Select Employee Type</option>
              <option value="Company Employee">Company Employee</option>
              <option value="Outsourced Personnel">Outsourced Personnel</option>
            </select>
          </div>
          <div class="form-group">
            <label for="employeeName">Employee Name</label>
            <select class="form-control" id="employeeName" name="employeeName" required>
              <option value="" disabled selected>Select Employee Type First</option>
              <?php 
              // Group employees by company_type
              $grouped_employees = [
                'Company Employee' => [],
                'Outsourced Personnel' => []
              ];
              
              foreach ($employees as $emp) {
                  $type = $emp['company_type'] ?? 'Company Employee';
                  if (isset($grouped_employees[$type])) {
                      $grouped_employees[$type][] = $emp;
                  } else {
                      $grouped_employees['Company Employee'][] = $emp;
                  }
              }
              
              // Store the grouped employees in a JavaScript variable
              echo "<script>
                const employeesByType = {
                  'Company Employee': " . json_encode($grouped_employees['Company Employee'] ?? []) . ",
                  'Outsourced Personnel': " . json_encode($grouped_employees['Outsourced Personnel'] ?? []) . "
                };
              </script>";
              ?>
            </select>
          </div>
          <div class="form-group">
            <label for="employeePosition">Position</label>
            <input type="text" class="form-control" id="employeePosition" name="employeePosition" readonly>
          </div>
          <div class="form-group">
            <label for="employeeContact">Contact Number</label>
            <input type="text" class="form-control" id="employeeContact" name="employeeContact" readonly>
          </div>
          <div class="form-group">
            <label for="employeeRate">Daily Rate</label>
            <input type="text" class="form-control" id="employeeRate" name="employeeRate" readonly>
          </div>
          <div class="form-group">
            <label for="employeeTotal">Total</label>
            <input type="text" class="form-control" id="employeeTotal" name="employeeTotal" readonly>
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Add Employee</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to log out?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="../logout.php" class="btn btn-danger">Logout</a>
      </div>
    </div>
  </div>
</div>
<!-- Finish Project Confirmation Modal -->
<div class="modal fade" id="finishProjectModal" tabindex="-1" aria-labelledby="finishProjectModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="finishProjectModalLabel"><i class="fas fa-check-circle me-2"></i>Finish Project</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="project_actual.php?id=<?php echo htmlspecialchars($_GET['id']); ?>">
        <input type="hidden" name="update_project_status" value="Finished">
        <div class="modal-body">
          <p>Are you sure you want to mark this project as <strong>Finished</strong>?</p>
          <p class="text-muted">This action cannot be undone. The project will be moved to the completed projects list.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">
            <i class="fas fa-check-circle me-1"></i> Mark as Finished
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Cancel Project Confirmation Modal -->
<div class="modal fade" id="cancelProjectModal" tabindex="-1" aria-labelledby="cancelProjectModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="cancelProjectModalLabel"><i class="fas fa-times-circle me-2"></i>Cancel Project</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="project_actual.php?id=<?php echo htmlspecialchars($_GET['id']); ?>">
        <input type="hidden" name="update_project_status" value="Cancelled">
        <div class="modal-body">
          <p>Are you sure you want to <strong>cancel</strong> this project?</p>
          <p class="text-muted">This action cannot be undone. The project will be marked as cancelled and moved to the cancelled projects list.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-danger">
            <i class="fas fa-times-circle me-1"></i> Cancel Project
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Export Project PDF Confirmation Modal -->
<div class="modal fade" id="exportProjectPdfModal" tabindex="-1" aria-labelledby="exportProjectPdfModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exportProjectPdfModalLabel">Export Project as PDF</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to export this project as PDF?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="#" id="confirmExportProjectPdf" class="btn btn-danger">Export</a>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addEquipmentModal" tabindex="-1" aria-labelledby="addEquipmentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addEquipmentModalLabel">Add Equipment to Project</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post">
        <div class="modal-body">
          <input type="hidden" name="add_project_equipment" value="1">
          <input type="hidden" id="projectDaysInput" value="<?php echo $project_days; ?>">
          <input type="hidden" name="category" value="Company">
          <div class="form-group mb-2">
            <label for="equipmentSelect">Equipment</label>
            <select class="form-control" id="equipmentSelect" name="equipment_id" required>
              <option value="" disabled selected>Select Equipment</option>
              <?php 
              $all_equipment = mysqli_query($con, "SELECT * FROM equipment WHERE LOWER(COALESCE(status, '')) NOT IN ('damaged', 'damage') ORDER BY equipment_name ASC");
              $damaged_equipment = mysqli_query($con, "SELECT * FROM equipment WHERE LOWER(COALESCE(status, '')) IN ('damaged', 'damage') ORDER BY equipment_name ASC");
              
              // First, show available equipment
              while ($eq = mysqli_fetch_assoc($all_equipment)) {
                $status = $eq['status'];
                $label = htmlspecialchars($eq['equipment_name']);
                if ($status === 'Not Available') {
                  $label .= ' (Not Available)';
                  echo '<option value="" disabled data-status="' . $status . '" data-price="' . htmlspecialchars($eq['equipment_price']) . '" data-depreciation="' . htmlspecialchars($eq['depreciation']) . '" style="color: #999;">' . $label . ' - Currently Unavailable</option>';
                } else {
                  echo '<option value="' . $eq['id'] . '" data-status="' . $status . '" data-price="' . htmlspecialchars($eq['equipment_price']) . '" data-depreciation="' . htmlspecialchars($eq['depreciation']) . '">' . $label . '</option>';
                }
              }
              
              // Then show disabled damaged equipment (for reference)
              if (mysqli_num_rows($damaged_equipment) > 0) {
                echo '<optgroup label="-- Damaged Equipment (Not Available) --">';
                mysqli_data_seek($damaged_equipment, 0);
                while ($eq = mysqli_fetch_assoc($damaged_equipment)) {
                  $label = htmlspecialchars($eq['equipment_name']) . ' (Damaged)';
                  echo '<option value="" disabled style="color: #999; font-style: italic;">' . $label . ' - ' . ucfirst($eq['status']) . '</option>';
                }
                echo '</optgroup>';
              }
              ?>
            </select>
          </div>
          <div class="form-group mb-2">
            <label>Equipment Price</label>
            <input type="text" class="form-control" id="equipmentPriceInput" readonly>
          </div>
          <div class="form-group mb-2">
            <label>Depreciation</label>
            <input type="text" class="form-control" id="depreciationInput" readonly>
          </div>
          <div class="form-group mb-2">
            <label>Total</label>
            <input type="text" class="form-control" id="equipmentTotalInput" name="total" readonly>
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success" id="addEquipmentBtn">Add Equipment</button>
          <button type="button" class="btn btn-warning" id="requestForRentBtn" style="display:none;">Request for Rent</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="reportEquipmentModal" tabindex="-1" aria-labelledby="reportEquipmentModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="reportEquipmentModalLabel">Report Equipment Issue</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post">
        <div class="modal-body">
          <input type="hidden" name="report_equipment" value="1">
          <input type="hidden" id="report_row_id" name="report_row_id">
          <div class="mb-3">
            <label for="report_message" class="form-label">Message (reason for report):</label>
            <textarea class="form-control" id="report_message" name="report_message" rows="3" required></textarea>
          </div>
          <div class="mb-3">
            <label for="report_remarks" class="form-label">Remarks (optional):</label>
            <textarea class="form-control" id="report_remarks" name="report_remarks" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Submit Report</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="equipmentNotAvailableModal" tabindex="-1" aria-labelledby="equipmentNotAvailableModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-body">
        <span style="font-size: 3rem; color: #dc3545;">
          <i class="fas fa-times-circle"></i>
        </span>
        <h4 id="equipmentNotAvailableModalLabel">Equipment Not Available</h4>
        <p id="equipmentNotAvailableMsg">This equipment is not available. Please select another equipment.</p>
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

