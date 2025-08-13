
<!-- Edit Project Information Modal -->
<div class="modal fade" id="editProjectInfoModal" tabindex="-1" aria-labelledby="editProjectInfoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="editProjectInfoModalLabel">Edit Project Information</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="editProjectInfoForm" method="POST" action="update_project_info.php">
        <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
        <div class="modal-body">
          <div class="mb-3">
            <label for="editStartDate" class="form-label">Start Date</label>
            <input type="date" class="form-control" id="editStartDate" name="start_date" required 
                   value="<?php echo htmlspecialchars($project['start_date']); ?>">
          </div>
          <div class="mb-3">
            <label for="editEndDate" class="form-label">End Date</label>
            <input type="date" class="form-control" id="editEndDate" name="end_date" required
                   value="<?php echo htmlspecialchars($project['deadline']); ?>">
          </div>
          <div id="editProjectError" class="alert alert-danger d-none"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Materials Modal -->
<div class="modal fade" id="addMaterialsModal" tabindex="-1" role="dialog" aria-labelledby="addMaterialsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addMaterialsModalLabel">Add Materials to Project</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-hover table-bordered">
            <thead class="table-light">
              <tr>
                <th width="40">
                  <div class="form-check d-flex justify-content-center">
                    <input class="form-check-input" type="checkbox" id="selectAllMaterials">
                  </div>
                </th>
                <th>Material Name</th>
                <th>Brand</th>
                <th>Supplier</th>
                <th>Specification</th>
                <th>Unit</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody id="materialsTableBody">
              <tr>
                <td colspan="9" class="text-center py-4">
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
      <div class="modal-footer d-flex justify-content-between">
        <div class="d-flex align-items-center">
          <span id="selectedCount" class="badge bg-primary me-2">0</span>
          <span>items selected</span>
        </div>
        <div>
          <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-success" id="addSelectedMaterials">
            <i class="fas fa-plus-circle me-1"></i> Add Selected Materials
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Form for submitting selected materials -->
<form id="addMaterialsForm" method="post" action="save_project_materials.php" class="d-none">
  <input type="hidden" name="project_id" id="projectId" value="<?php echo htmlspecialchars($_GET['id']); ?>">
  <input type="hidden" name="materials_data" id="materialsData">
</form>


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
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="viewPermitsModalLabel">Project Permits & Clearances</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="permitsLoading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="mt-2 mb-0">Loading permits...</p>
        </div>
        <div id="permitsContainer" class="d-none">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <button id="prevPermitBtn" class="btn btn-outline-primary" disabled>
              <i class="fas fa-chevron-left me-1"></i> Previous
            </button>
            <h5 id="permitTitle" class="mb-0 text-center"></h5>
            <button id="nextPermitBtn" class="btn btn-outline-primary">
              Next <i class="fas fa-chevron-right ms-1"></i>
            </button>
          </div>
          <div id="permitViewer" class="border rounded p-3" style="min-height: 60vh; background-color: #f8f9fa; position: relative;">
            <div id="permitLoading" class="d-flex justify-content-center align-items-center h-100">
              <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
              </div>
            </div>
            <div id="permitContent" class="d-none h-100"></div>
          </div>
        </div>
        <div id="noPermits" class="text-center py-5 d-none">
          <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
          <h5>No permits found</h5>
          <p class="text-muted">There are no permits available for this project.</p>
        </div>
      </div>
      <div class="modal-footer">
        <a id="downloadPermitBtn" href="#" class="btn btn-success me-auto d-none" download>
          <i class="fas fa-download me-1"></i> Download
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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


<?php
// --- BACKEND FETCH: Employees and Positions --- //
$positions = [];
$positions_query = "SELECT position_id, title, daily_rate FROM positions";
$positions_result = $con->query($positions_query);
if ($positions_result) {
    while ($row = $positions_result->fetch_assoc()) {
        $positions[] = $row;
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

// Fetch employees who are not currently working on any project
$employees = [];
$employees_query = "SELECT e.employee_id, e.first_name, e.last_name, e.position_id, e.contact_number, e.company_type 
                   FROM employees e
                   WHERE e.employee_id NOT IN (
                       SELECT employee_id 
                       FROM project_add_employee 
                       WHERE status = 'Working'
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
      <div class="modal-header">
        <h5 class="modal-title" id="addEmployeeModalLabel">Add Employee(s)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="save_project_employee.php" id="addEmployeeTableForm">
        <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
        <div class="modal-body">
          <!-- Filter Controls -->
          <div class="row mb-3">
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
                             class="employee-check"
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
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addEquipmentModalLabel">Add Equipment to Project</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="addEquipmentForm" method="post" action="save_project_equipment.php">
        <input type="hidden" name="project_id" value="<?php echo isset($project_id) ? $project_id : ''; ?>">
        <input type="hidden" id="projectDaysInput" value="<?php echo isset($project_days) ? $project_days : 0; ?>">
        <input type="hidden" name="category" value="Company">
        
        <div class="modal-body">
          <div class="mb-3">
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-search"></i></span>
              <input type="text" id="equipmentSearchInput" class="form-control" placeholder="Search equipment...">
            </div>
          </div>
          
          <div class="table-responsive">
            <table class="table table-hover table-bordered">
              <thead class="table-light">
                <tr>
                  <th width="50">#</th>
                  <th>Equipment Name</th>
                  <th>Status</th>
                  <th>Price</th>
                  <th>Depreciation</th>
                  <th>Total</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="equipmentTableBody">
                <!-- Equipment will be loaded here via JavaScript -->
                <tr>
                  <td colspan="7" class="text-center">
                    <div class="spinner-border text-primary" role="status">
                      <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Loading equipment...</p>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          
          <div class="selected-equipment mt-3">
            <h6>Selected Equipment</h6>
            <div id="selectedEquipmentList" class="border rounded p-2 mb-3">
              <p class="text-muted mb-0">No equipment selected</p>
            </div>
          </div>
        </div>
        
        <div class="modal-footer d-flex justify-content-between">
          <div>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
              <i class="fas fa-times me-1"></i> Cancel
            </button>
          </div>
          <div>
            <button type="button" class="btn btn-warning me-2" id="clearSelectionBtn">
              <i class="fas fa-eraser me-1"></i> Clear Selection
            </button>
            <button type="submit" class="btn btn-success" id="addEquipmentBtn" disabled>
              <i class="fas fa-plus-circle me-1"></i> Add Selected Equipment
            </button>
          </div>
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

<script>
function toggleAllEmployees(source) {
  let checkboxes = document.querySelectorAll('.employee-check');
  for (let c of checkboxes) c.checked = source.checked;
}

// JS Filter Function
function filterEmployeeTable() {
  var positionFilter = document.getElementById('filterPosition').value.toLowerCase();
  var companyTypeFilter = document.getElementById('filterCompanyType').value.toLowerCase();
  var rows = document.querySelectorAll('#employeeChecklistTable tbody .employee-row');
  rows.forEach(function(row) {
    var rowPosition = row.getAttribute('data-position').toLowerCase();
    var rowCompanyType = row.getAttribute('data-company-type').toLowerCase();
    var show = true;
    if (positionFilter && rowPosition !== positionFilter) show = false;
    if (companyTypeFilter && rowCompanyType !== companyTypeFilter) show = false;
    row.style.display = show ? '' : 'none';
  });
}
</script>

<script>
// Function to fetch and display permits
function loadPermits(projectId) {
    fetch(`get_permits.php?project_id=${projectId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const permitsContainer = document.getElementById('permitsContainer');
                if (permitsContainer) {
                    if (data.permits && data.permits.length > 0) {
                        let html = '';
                        data.permits.forEach(permit => {
                            // Construct the correct path to the PDF files in projectmanager/uploads/permits/
                            const fileName = permit.file_path.split('/').pop() || permit.file_path.split('\\').pop();
                            const filePath = `../projectmanager/uploads/permits/${fileName}`;
                            const uploadDate = new Date(permit.uploaded_at).toLocaleDateString();
                            
                            html += `
                            <div class="col-md-4 col-lg-3 mb-4">
                                <a href="${filePath}" 
                                   class="card h-100 text-center text-decoration-none text-dark"
                                   target="_blank"
                                   title="View ${permit.permit_type}">
                                    <div class="card-body">
                                        <i class="fas fa-file-pdf text-danger" style="font-size: 3rem;"></i>
                                        <h6 class="card-title mt-2">${permit.permit_type}</h6>
                                        <p class="card-text text-muted small">${fileName}</p>
                                    </div>
                                    <div class="card-footer bg-white border-0">
                                        <div class="small text-muted">Uploaded: ${uploadDate}</div>
                                        <small class="text-primary">Click to view</small>
                                    </div>
                                </a>
                            </div>`;
                        });
                        permitsContainer.innerHTML = html;
                    } else {
                        permitsContainer.innerHTML = '<div class="col-12 text-center text-muted">No permits found for this project.</div>';
                    }
                }
            } else {
                console.error('Error loading permits:', data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching permits:', error);
        });
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize view permits modal
    const viewPermitsModal = document.getElementById('viewPermitsModal');
    if (viewPermitsModal) {
        viewPermitsModal.addEventListener('shown.bs.modal', function() {
            const projectId = new URLSearchParams(window.location.search).get('id');
            if (projectId) {
                loadPermits(projectId);
            }
        });
    }
    
    // Initialize equipment modal
    const addEquipmentModal = document.getElementById('addEquipmentModal');
    if (addEquipmentModal) {
        // Load equipment when modal is shown
        addEquipmentModal.addEventListener('show.bs.modal', function() {
            loadEquipmentForModal();
        });
        
        // Clear search and selection when modal is hidden
        addEquipmentModal.addEventListener('hidden.bs.modal', function() {
            const searchInput = document.getElementById('equipmentSearchInput');
            if (searchInput) searchInput.value = '';
            
            // Clear any selected checkboxes
            const checkboxes = document.querySelectorAll('.equipment-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Reset selected equipment list
            const selectedList = document.getElementById('selectedEquipmentList');
            if (selectedList) {
                selectedList.innerHTML = '<p class="text-muted mb-0">No equipment selected</p>';
            }
            
            // Disable add button
            const addButton = document.getElementById('addEquipmentBtn');
            if (addButton) addButton.disabled = true;
        });
    }
    
    // Handle equipment form submission
    const equipmentForm = document.getElementById('addEquipmentForm');
    if (equipmentForm) {
        equipmentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get selected equipment with quantities
            const selectedEquipment = [];
            const checkboxes = document.querySelectorAll('.equipment-checkbox:checked');
            
            if (checkboxes.length === 0) {
                showFeedbackModal('Error', 'Please select at least one piece of equipment to add.');
                return;
            }
            
            // Validate quantities
            let hasInvalidQuantity = false;
            checkboxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                const quantityInput = row.querySelector('.equipment-quantity');
                const quantity = parseInt(quantityInput ? quantityInput.value : 1);
                
                if (isNaN(quantity) || quantity <= 0) {
                    hasInvalidQuantity = true;
                    return;
                }
                
                selectedEquipment.push({
                    id: checkbox.dataset.equipmentId,
                    quantity: quantity,
                    price: parseFloat(checkbox.dataset.price) || 0,
                    depreciation: parseFloat(checkbox.dataset.depreciation) || 0
                });
            });
            
            if (hasInvalidQuantity) {
                showFeedbackModal('Error', 'Please enter a valid quantity (1 or more) for all selected equipment.');
                return;
            }
            
            // Prepare form data
            const projectId = document.querySelector('input[name="project_id"]').value;
            const formData = new FormData();
            formData.append('project_id', projectId);
            formData.append('equipment_data', JSON.stringify(selectedEquipment));
            
            console.log('Sending equipment data:', {
                project_id: projectId,
                equipment_data: selectedEquipment
            });
            
            // Show loading state
            const submitBtn = document.querySelector('#addEquipmentBtn');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Adding...';
            
            // Submit form via AJAX
            fetch('save_project_equipment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Show success message
                    showFeedbackModal('Success', data.message || 'Equipment added successfully!');
                    
                    // Close the modal after a short delay
                    setTimeout(() => {
                        const modal = bootstrap.Modal.getInstance(addEquipmentModal);
                        if (modal) modal.hide();
                        
                        // Reload the page to show updated equipment list
                        location.reload();
                    }, 1500);
                } else {
                    // Show error message
                    let errorMessage = data.message || 'Failed to add equipment.';
                    if (data.errors && data.errors.length > 0) {
                        errorMessage += '\n\n' + data.errors.join('\n');
                    }
                    showFeedbackModal('Error', errorMessage);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showFeedbackModal('Error', 'An error occurred while processing your request. Please try again.\n' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        });
    }
    const addMaterialsModal = document.getElementById('addMaterialsModal');
    
    if (addMaterialsModal) {
        // Load materials when modal is shown
        addMaterialsModal.addEventListener('shown.bs.modal', function() {
            loadMaterialsForModal();
        });
        
        // Select all checkbox
        const selectAllCheckbox = document.getElementById('selectAllMaterials');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.material-checkbox');
                checkboxes.forEach(checkbox => {
                    if (!checkbox.disabled) {
                        checkbox.checked = selectAllCheckbox.checked;
                    }
                });
                updateSelectedCount();
            });
        }
        
        // Add selected materials button
        const addSelectedBtn = document.getElementById('addSelectedMaterials');
        if (addSelectedBtn) {
            addSelectedBtn.addEventListener('click', function() {
                const selectedMaterials = [];
                const checkboxes = document.querySelectorAll('.material-checkbox:checked');
                
                if (checkboxes.length === 0) {
                    showAlert('Please select at least one material', 'warning');
                    return;
                }
                
                checkboxes.forEach(checkbox => {
                    const row = checkbox.closest('tr');
                    const materialId = checkbox.value;
                    const quantity = row.querySelector('.quantity-input').value;
                    const price = row.querySelector('td:nth-child(7)').textContent.replace('₱', '');
                    const materialName = row.querySelector('td:nth-child(2)').textContent.trim();
                    const unit = row.querySelector('td:nth-child(6)').textContent.trim();
                    
                    selectedMaterials.push({
                        id: materialId,
                        name: materialName,
                        quantity: quantity,
                        price: price,
                        unit: unit
                    });
                });
                
                // Set the project ID
                const urlParams = new URLSearchParams(window.location.search);
                document.getElementById('projectId').value = urlParams.get('id');
                
                // Set the materials data
                document.getElementById('materialsData').value = JSON.stringify(selectedMaterials);
                
                // Show loading state
                const addBtn = document.getElementById('addSelectedMaterials');
                const originalBtnText = addBtn.innerHTML;
                addBtn.disabled = true;
                addBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...';
                
                // Submit via AJAX
                const form = document.getElementById('addMaterialsForm');
                const formData = new FormData(form);
                
                fetch(form.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success feedback and reload the page
                        showFeedbackModal(true, 'Materials added successfully!');
                        // Close the add materials modal
                        const addMaterialsModalInstance = bootstrap.Modal.getInstance(addMaterialsModal);
                        if (addMaterialsModalInstance) {
                            addMaterialsModalInstance.hide();
                        }
                        // Reload the page after a short delay
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        // Show error feedback
                        showFeedbackModal(false, data.message || 'Failed to add materials. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Show error feedback
                    showFeedbackModal(false, 'An error occurred while adding materials. Please try again.');
                })
                .finally(() => {
                    // Restore button state
                    addBtn.disabled = false;
                    addBtn.innerHTML = originalBtnText;
                });
            });
        }
    }
    
    // Function to load materials for the modal
    function loadMaterialsForModal() {
        const tbody = document.getElementById('materialsTableBody');
        if (!tbody) return;
        
        fetch('get_available_materials.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.materials) {
                    renderMaterialsInModal(data.materials);
                } else {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="9" class="text-center text-danger py-4">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                Failed to load materials. Please try again.
                            </td>
                        </tr>`;
                }
            })
            .catch(error => {
                console.error('Error loading materials:', error);
                tbody.innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center text-danger py-4">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading materials. Please check your connection.
                        </td>
                    </tr>`;
            });
    }
    
    // Function to render materials in the modal table
    function renderMaterialsInModal(materials) {
        const tbody = document.getElementById('materialsTableBody');
        if (!tbody) return;
        
        if (!materials || materials.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-4">
                        <i class="fas fa-box-open fa-2x text-muted mb-2"></i>
                        <p class="mb-0">No materials available</p>
                    </td>
                </tr>`;
            return;
        }
        
        let html = '';
        
        materials.forEach(material => {
            const isAvailable = material.available_quantity > 0;
            const disabledClass = isAvailable ? '' : 'text-muted';
            const disabledAttr = isAvailable ? '' : 'disabled';
            const tooltip = isAvailable ? '' : 'title="Out of stock"';
            
            html += `
                <tr class="${disabledClass}" ${tooltip}>
                    <td class="text-center align-middle">
                        <div class="form-check d-flex justify-content-center">
                            <input class="form-check-input material-checkbox" type="checkbox" 
                                   name="selected_materials[]" value="${material.id}" 
                                   ${disabledAttr}>
                        </div>
                    </td>
                    <td class="align-middle">
                        <div class="text-truncate" style="max-width: 200px;" title="${material.material_name}">
                            ${material.material_name}
                        </div>
                    </td>
                    <td class="align-middle">
                        <div class="text-truncate" style="max-width: 150px;" title="${material.brand || 'N/A'}">
                            ${material.brand || 'N/A'}
                        </div>
                    </td>
                    <td class="align-middle">
                        <div class="text-truncate" style="max-width: 150px;" title="${material.supplier_name || 'N/A'}">
                            ${material.supplier_name || 'N/A'}
                        </div>
                    </td>
                    <td class="align-middle">
                        <div class="text-truncate" style="max-width: 200px;" title="${material.specification || 'N/A'}">
                            ${material.specification || 'N/A'}
                        </div>
                    </td>
                    <td class="align-middle text-center">${material.unit || 'pcs'}</td>
                    <td class="align-middle text-end">₱${parseFloat(material.material_price || 0).toFixed(2)}</td>
                    <td class="align-middle">
                        <div class="quantity-controls d-flex align-items-center">
                            <button type="button" class="btn btn-sm btn-outline-secondary quantity-decrease quantity-btn" 
                                    ${disabledAttr}>
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" class="form-control form-control-sm text-center mx-1 quantity-input" 
                                   value="1" min="1" max="${material.available_quantity}" step="1" 
                                   data-price="${material.material_price || 0}" 
                                   data-labor="${material.labor_other || 0}"
                                   ${disabledAttr}
                                   onkeydown="return event.key !== 'e' && event.key !== 'E' && event.key !== '-' && event.key !== '+';">
                            <button type="button" class="btn btn-sm btn-outline-secondary quantity-increase quantity-btn" 
                                    ${disabledAttr}>
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <small class="text-muted d-block text-center">
                            Available: ${material.available_quantity}
                        </small>
                    </td>
                    <td class="align-middle text-end fw-bold material-total">
                        ₱${parseFloat(material.material_price || 0).toFixed(2)}
                    </td>
                </tr>`;
        });
        
        tbody.innerHTML = html;
        
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Setup quantity controls
        setupQuantityControls();
        
        // Update selected count
        updateSelectedCount();
    }
    
    // Function to load equipment for the modal
    function loadEquipmentForModal() {
        const tbody = document.getElementById('equipmentTableBody');
        if (!tbody) return;
        
        fetch('get_available_equipment.php')
            .then(response => response.json())
            .then(data => {
                renderEquipmentInModal(data);
                setupEquipmentSearch();
                setupClearSelection();
            })
            .catch(error => {
                console.error('Error loading equipment:', error);
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Failed to load equipment. Please try again.
                        </td>
                    </tr>`;
            });
    }
    
    // Function to render equipment in the modal
    function renderEquipmentInModal(equipment) {
        const tbody = document.getElementById('equipmentTableBody');
        if (!tbody) return;
        
        if (!equipment || equipment.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center">No equipment available</td>
                </tr>`;
            return;
        }
        
        let html = '';
        equipment.forEach((eq, index) => {
            const isAvailable = eq.status && !['damaged', 'damage', 'not available', 'not_available'].includes(eq.status.toLowerCase());
            const statusClass = isAvailable ? 'success' : 'danger';
            const statusText = isAvailable ? 'Available' : (eq.status || 'Not Available');
            
            html += `
                <tr data-equipment-id="${eq.id}" class="${!isAvailable ? 'table-secondary' : ''}">
                    <td class="text-center">${index + 1}</td>
                    <td>${eq.equipment_name || 'N/A'}</td>
                    <td>
                        <span class="badge bg-${statusClass}">${statusText}</span>
                    </td>
                    <td>₱${parseFloat(eq.equipment_price || 0).toFixed(2)}</td>
                    <td>${eq.depreciation || '0%'}</td>
                    <td>₱${(parseFloat(eq.equipment_price || 0) * (1 - (parseFloat(eq.depreciation) || 0) / 100)).toFixed(2)}</td>
                    <td class="text-center">
                        <div class="form-check form-switch d-inline-block">
                            <input class="form-check-input equipment-checkbox" type="checkbox" 
                                   data-equipment-id="${eq.id}" 
                                   data-name="${eq.equipment_name || ''}"
                                   data-price="${eq.equipment_price || 0}"
                                   data-depreciation="${eq.depreciation || 0}"
                                   ${!isAvailable ? 'disabled' : ''}>
                        </div>
                    </td>
                </tr>`;
        });
        
        tbody.innerHTML = html;
        setupEquipmentCheckboxes();
    }
    
    // Setup equipment checkbox event listeners
    function setupEquipmentCheckboxes() {
        const checkboxes = document.querySelectorAll('.equipment-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedEquipment);
        });
    }
    
    // Update the selected equipment list
    function updateSelectedEquipment() {
        const selectedEquipment = [];
        const checkboxes = document.querySelectorAll('.equipment-checkbox:checked');
        
        checkboxes.forEach(checkbox => {
            selectedEquipment.push({
                id: checkbox.dataset.equipmentId,
                name: checkbox.dataset.name,
                price: parseFloat(checkbox.dataset.price || 0),
                depreciation: parseFloat(checkbox.dataset.depreciation || 0)
            });
        });
        
        // Update the selected equipment list
        const selectedList = document.getElementById('selectedEquipmentList');
        const addButton = document.getElementById('addEquipmentBtn');
        
        if (selectedEquipment.length === 0) {
            selectedList.innerHTML = '<p class="text-muted mb-0">No equipment selected</p>';
            addButton.disabled = true;
        } else {
            let html = '<div class="d-flex flex-wrap gap-2">';
            selectedEquipment.forEach(eq => {
                const total = eq.price * (1 - (eq.depreciation / 100));
                html += `
                    <span class="badge bg-primary d-flex align-items-center">
                        ${eq.name} (₱${total.toFixed(2)})
                        <button type="button" class="btn-close btn-close-white ms-2" style="font-size: 0.5rem;" 
                                data-equipment-id="${eq.id}" aria-label="Remove"></button>
                    </span>`;
            });
            html += '</div>';
            selectedList.innerHTML = html;
            addButton.disabled = false;
            
            // Add event listeners to remove buttons
            document.querySelectorAll('#selectedEquipmentList .btn-close').forEach(btn => {
                btn.addEventListener('click', function() {
                    const equipmentId = this.dataset.equipmentId;
                    const checkbox = document.querySelector(`.equipment-checkbox[data-equipment-id="${equipmentId}"]`);
                    if (checkbox) {
                        checkbox.checked = false;
                        updateSelectedEquipment();
                    }
                });
            });
        }
    }
    
    // Setup equipment search functionality
    function setupEquipmentSearch() {
        const searchInput = document.getElementById('equipmentSearchInput');
        if (!searchInput) return;
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#equipmentTableBody tr');
            
            rows.forEach(row => {
                const name = row.querySelector('td:nth-child(2)')?.textContent?.toLowerCase() || '';
                const status = row.querySelector('td:nth-child(3)')?.textContent?.toLowerCase() || '';
                
                if (name.includes(searchTerm) || status.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
    
    // Setup clear selection button
    function setupClearSelection() {
        const clearBtn = document.getElementById('clearSelectionBtn');
        if (!clearBtn) return;
        
        clearBtn.addEventListener('click', function() {
            document.querySelectorAll('.equipment-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelectedEquipment();
        });
    }
    
    // Function to setup quantity controls
    function setupQuantityControls() {
        // Delegate events for quantity controls
        document.addEventListener('click', function(e) {
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
        
        // Handle direct input changes
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('quantity-input')) {
                updateRowTotal(e.target);
            }
        });
        
        // Handle checkbox changes to update selected count
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('material-checkbox')) {
                updateSelectedCount();
            }
        });
    }
    
    // Function to update row total when quantity changes
    function updateRowTotal(input) {
        const row = input.closest('tr');
        if (!row) return;
        
        const price = parseFloat(input.dataset.price) || 0;
        const labor = parseFloat(input.dataset.labor) || 0;
        const quantity = parseInt(input.value) || 0;
        const total = (price + labor) * quantity;
        
        const totalCell = row.querySelector('.material-total');
        if (totalCell) {
            totalCell.textContent = '₱' + total.toFixed(2);
        }
    }
    
    // Function to update selected materials count
    function updateSelectedCount() {
        const selectedCount = document.querySelectorAll('.material-checkbox:checked').length;
        const selectedCountElement = document.getElementById('selectedCount');
        if (selectedCountElement) {
            selectedCountElement.textContent = selectedCount;
        }
    }
    
    // Function to show alert messages
    function showAlert(message, type = 'info') {
        // Remove any existing alerts
        const existingAlert = document.querySelector('.alert-dismissible');
        if (existingAlert) {
            existingAlert.remove();
        }
        
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.role = 'alert';
        alertDiv.innerHTML = `
            <i class="fas ${type === 'danger' ? 'fa-exclamation-circle' : 'fa-info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        // Add the alert to the page (you might need to adjust the selector)
        const container = document.querySelector('.container-fluid') || document.body;
        container.insertBefore(alertDiv, container.firstChild);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            const alert = bootstrap.Alert.getOrCreateInstance(alertDiv);
            if (alert) {
                alert.close();
            }
        }, 5000);
    }
});

// Handle Edit Project Information Form Submission
document.addEventListener('DOMContentLoaded', function() {
    const editProjectForm = document.getElementById('editProjectInfoForm');
    if (editProjectForm) {
        editProjectForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const errorDiv = document.getElementById('editProjectError');
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            
            try {
                // Show loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';
                
                const response = await fetch('update_project_info.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Close the edit modal
                    const editModal = bootstrap.Modal.getInstance(document.getElementById('editProjectInfoModal'));
                    editModal.hide();
                    
                    // Create and show success modal
                    const successModalHtml = `
                        <div class="modal fade" id="projectUpdateSuccessModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-body text-center p-4">
                                        <div class="text-success mb-3">
                                            <i class="fas fa-check-circle" style="font-size: 4rem;"></i>
                                        </div>
                                        <h4 class="mb-3">Project Updated</h4>
                                        <p class="mb-4">Project information has been updated successfully!</p>
                                        <button type="button" class="btn btn-success" data-bs-dismiss="modal">
                                            <i class="fas fa-check me-2"></i>OK
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                    
                    // Add modal to the DOM
                    document.body.insertAdjacentHTML('beforeend', successModalHtml);
                    
                    // Show the modal
                    const successModal = new bootstrap.Modal(document.getElementById('projectUpdateSuccessModal'));
                    successModal.show();
                    
                    // Reload the page when modal is closed
                    document.getElementById('projectUpdateSuccessModal').addEventListener('hidden.bs.modal', function () {
                        window.location.reload();
                    });
                } else {
                    // Show error message
                    errorDiv.textContent = result.message || 'Failed to update project information. Please try again.';
                    errorDiv.classList.remove('d-none');
                }
            } catch (error) {
                console.error('Error updating project information:', error);
                errorDiv.textContent = 'An error occurred while updating project information. Please try again.';
                errorDiv.classList.remove('d-none');
            } finally {
                // Reset button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        });
        
        // Reset error message when modal is closed
        const editProjectModal = document.getElementById('editProjectInfoModal');
        if (editProjectModal) {
            editProjectModal.addEventListener('hidden.bs.modal', function() {
                const errorDiv = document.getElementById('editProjectError');
                if (errorDiv) {
                    errorDiv.textContent = '';
                    errorDiv.classList.add('d-none');
                }
            });
        }
    }
});

// Permits Viewing Functionality
document.addEventListener('DOMContentLoaded', function() {
    const viewPermitsModal = document.getElementById('viewPermitsModal');
    if (!viewPermitsModal) return;

    // Permit configurations
    const permitTypes = ['lgu', 'fire', 'zoning', 'occupancy', 'barangay'];
    const permitTypeNames = {
        'lgu': 'LGU Clearance',
        'fire': 'Fire Permit',
        'zoning': 'Zoning Clearance',
        'occupancy': 'Occupancy Permit',
        'barangay': 'Barangay Clearance'
    };

    let currentPermits = [];
    let currentPermitIndex = -1;
    const projectId = '<?php echo $project_id ?? 0; ?>';

    // Modal elements
    const permitsLoading = document.getElementById('permitsLoading');
    const permitsContainer = document.getElementById('permitsContainer');
    const noPermits = document.getElementById('noPermits');
    const permitTitle = document.getElementById('permitTitle');
    const permitContent = document.getElementById('permitContent');
    const permitLoading = document.getElementById('permitLoading');
    const downloadPermitBtn = document.getElementById('downloadPermitBtn');
    const prevPermitBtn = document.getElementById('prevPermitBtn');
    const nextPermitBtn = document.getElementById('nextPermitBtn');

    // Initialize modal event
    viewPermitsModal.addEventListener('shown.bs.modal', function() {
        loadPermits();
    });

    // Navigation buttons
    if (prevPermitBtn) {
        prevPermitBtn.addEventListener('click', showPreviousPermit);
    }
    if (nextPermitBtn) {
        nextPermitBtn.addEventListener('click', showNextPermit);
    }

    // Load permits from the server
    function loadPermits() {
        if (!projectId) {
            showError('Project ID not found');
            return;
        }

        showLoading();

        fetch(`get_permits.php?project_id=${projectId}`)
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.success && data.permits && data.permits.length > 0) {
                    currentPermits = data.permits;
                    currentPermitIndex = 0;
                    showPermit(currentPermitIndex);
                    showPermitsContainer();
                } else {
                    showNoPermits();
                }
            })
            .catch(error => {
                console.error('Error loading permits:', error);
                showError('Failed to load permits. Please try again.');
            });
    }

    // Show a specific permit by index
    function showPermit(index) {
        if (index < 0 || index >= currentPermits.length) return;
        
        currentPermitIndex = index;
        const permit = currentPermits[index];
        
        // Show loading state
        permitContent.classList.add('d-none');
        permitLoading.classList.remove('d-none');
        
        // Update navigation buttons
        updateNavigationButtons();
        
        // Set permit title
        const permitTypeName = permitTypeNames[permit.permit_type] || permit.permit_type;
        const uploadDate = new Date(permit.uploaded_at).toLocaleDateString();
        permitTitle.textContent = `${permitTypeName} - Uploaded on ${uploadDate}`;
        
        // Set download button
        const fileName = permit.file_path.split('/').pop();
        downloadPermitBtn.href = permit.file_path;
        downloadPermitBtn.download = fileName;
        downloadPermitBtn.classList.remove('d-none');
        
        // Determine file type and render accordingly
        const fileExt = getFileExtension(permit.file_path).toLowerCase();
        
        if (fileExt === 'pdf') {
            renderPdf(permit.file_path);
        } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExt)) {
            renderImage(permit.file_path, permitTypeName);
        } else {
            renderUnsupportedFile(permitTypeName);
        }
    }
    
    // Render PDF file
    function renderPdf(filePath) {
        // Remove any leading slashes, dots, or duplicate path segments
        let cleanPath = filePath.replace(/^[.\/\\]+/, '');
        
        // Remove any 'uploads/permits/' or 'permits/' from the beginning of the path (case insensitive)
        cleanPath = cleanPath.replace(/^(?:uploads[\/\\])?permits[\/\\]?/i, '');
        
        const baseUrl = window.location.origin;
        // The files are actually stored in projectmanager/uploads/permits/
        const fullPath = `${baseUrl}/Voltech-2/projectmanager/uploads/permits/${cleanPath}`;
        
        // Create PDF viewer container
        permitContent.innerHTML = `
            <div class="ratio ratio-4x3 h-100">
                <iframe src="${fullPath}" 
                        style="width: 100%; height: 100%; border: none;"
                        class="w-100 h-100">
                </iframe>
            </div>`;
        showPermitContent();
        
        // Set download link with proper filename
        const fileName = cleanPath.split('/').pop();
        downloadPermitBtn.href = fullPath;
        downloadPermitBtn.download = fileName;
        downloadPermitBtn.classList.remove('d-none');
    }
    
    // Render image file
    function renderImage(filePath, altText) {
        // Remove any leading slashes, dots, or duplicate path segments
        let cleanPath = filePath.replace(/^[.\/\\]+/, '');
        
        // Remove any 'uploads/permits/' or 'permits/' from the beginning of the path (case insensitive)
        cleanPath = cleanPath.replace(/^(?:uploads[\/\\])?permits[\/\\]?/i, '');
        
        const baseUrl = window.location.origin;
        // The files are actually stored in projectmanager/uploads/permits/
        const fullPath = `${baseUrl}/Voltech-2/projectmanager/uploads/permits/${cleanPath}`;
        
        // Create image container with loading state
        permitContent.innerHTML = `
            <div class="d-flex justify-content-center align-items-center h-100">
                <div class="text-center">
                    <div class="spinner-border text-primary mb-2" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Loading image...</p>
                </div>
            </div>`;
        
        const img = document.createElement('img');
        img.src = fullPath;
        img.alt = altText;
        img.className = 'img-fluid';
        img.style.maxHeight = '80vh';
        img.style.maxWidth = '100%';
        img.style.objectFit = 'contain';
        
        // Handle image load/error
        img.onload = function() {
            // Set download link with proper filename
            const fileName = cleanPath.split('/').pop();
            downloadPermitBtn.href = fullPath;
            downloadPermitBtn.download = fileName;
            downloadPermitBtn.classList.remove('d-none');
            
            // Show the image
            permitContent.innerHTML = '';
            permitContent.appendChild(img);
            showPermitContent();
        };
        
        img.onerror = function() {
            renderError('Failed to load image');
        };
    }
    
    // Show unsupported file message
    function renderUnsupportedFile(permitType) {
        permitContent.innerHTML = `
            <div class="d-flex flex-column justify-content-center align-items-center h-100">
                <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                <h5>Preview not available</h5>
                <p class="text-muted">This file type cannot be previewed. Please download the file to view it.</p>
                <p class="text-muted small mt-2">${permitType}</p>
            </div>`;
        
        // Hide download button for unsupported files
        downloadPermitBtn.classList.add('d-none');
        showPermitContent();
    }
    
    // Show error message
    function renderError(message) {
        permitContent.innerHTML = `
            <div class="d-flex flex-column justify-content-center align-items-center h-100">
                <i class="fas fa-exclamation-circle fa-4x text-danger mb-3"></i>
                <h5 class="text-danger">Error</h5>
                <p class="text-muted">${message}</p>
                <button class="btn btn-sm btn-outline-primary mt-2" onclick="window.location.reload()">
                    <i class="fas fa-sync-alt me-1"></i> Try Again
                </button>
            </div>`;
        
        // Hide download button on error
        downloadPermitBtn.classList.add('d-none');
        showPermitContent();
    }
    
    // Show the permit content and hide loading
    function showPermitContent() {
        permitLoading.classList.add('d-none');
        permitContent.classList.remove('d-none');
    }
    
    // Navigation functions
    function showPreviousPermit() {
        if (currentPermitIndex > 0) {
            showPermit(currentPermitIndex - 1);
        }
    }
    
    function showNextPermit() {
        if (currentPermitIndex < currentPermits.length - 1) {
            showPermit(currentPermitIndex + 1);
        }
    }
    
    // Update navigation buttons state
    function updateNavigationButtons() {
        if (prevPermitBtn) {
            prevPermitBtn.disabled = currentPermitIndex <= 0;
        }
        if (nextPermitBtn) {
            nextPermitBtn.disabled = currentPermitIndex >= currentPermits.length - 1;
        }
    }
    
    // Helper function to get file extension
    function getFileExtension(filename) {
        return filename.split('.').pop().split('?')[0];
    }
    
    // UI state functions
    function showLoading() {
        permitsLoading.classList.remove('d-none');
        permitsContainer.classList.add('d-none');
        noPermits.classList.add('d-none');
    }
    
    function showPermitsContainer() {
        permitsLoading.classList.add('d-none');
        permitsContainer.classList.remove('d-none');
        noPermits.classList.add('d-none');
    }
    
    function showNoPermits() {
        permitsLoading.classList.add('d-none');
        permitsContainer.classList.add('d-none');
        noPermits.classList.remove('d-none');
    }
    
    function showError(message) {
        console.error('Permits Error:', message);
        // You can add a more user-friendly error display here if needed
        showNoPermits();
    }
});
</script>