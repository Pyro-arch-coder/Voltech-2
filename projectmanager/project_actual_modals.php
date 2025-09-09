
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
        <!-- Filter Controls -->
        <div class="row mb-3 g-2">
          <div class="col-md-3">
            <label for="supplierFilter" class="form-label small text-muted mb-1">Supplier</label>
            <select class="form-select form-select-sm" id="supplierFilter">
              <option value="">All Suppliers</option>
            </select>
          </div>
          <div class="col-md-3">
            <label for="brandFilter" class="form-label small text-muted mb-1">Brand</label>
            <select class="form-select form-select-sm" id="brandFilter">
              <option value="">All Brands</option>
            </select>
          </div>
          <div class="col-md-3">
            <label for="specFilter" class="form-label small text-muted mb-1">Specification</label>
            <input type="text" class="form-control form-control-sm" id="specFilter" placeholder="Filter by spec...">
          </div>
          <div class="col-md-3">
            <label for="priceSort" class="form-label small text-muted mb-1">Price</label>
            <select class="form-select form-select-sm" id="priceSort">
              <option value="">Default</option>
              <option value="asc">Price: Low to High</option>
              <option value="desc">Price: High to Low</option>
            </select>
          </div>
        </div>
        
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

// Fetch employees who are not currently working on any project or are already assigned to this project
$employees = [];
$employees_query = "SELECT e.employee_id, e.first_name, e.last_name, e.position_id, e.contact_number, e.company_type 
                   FROM employees e
                   WHERE e.employee_id NOT IN (
                       SELECT employee_id 
                       FROM project_add_employee 
                       WHERE status = 'Working' AND project_id != '$project_id'
                   )
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
      <div class="modal-header">
        <h5 class="modal-title" id="addEmployeeModalLabel">Add Employee(s)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="save_project_employee.php" id="addEmployeeTableForm">
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
      <form method="post" action="project_actual.php?id=<?php echo htmlspecialchars($_GET['id']); ?>" id="finishProjectForm">
        <input type="hidden" name="update_project_status" id="projectStatusInput" value="Finished">
        <div class="modal-body">
          <p id="confirmationText">Are you sure you want to mark this project as <strong>Finished</strong>?</p>
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
          <div class="row mb-3">
            <div class="col-md-6">
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" id="equipmentSearchInput" class="form-control" placeholder="Search equipment...">
              </div>
            </div>
            <div class="col-md-6">
              <select id="categoryFilter" class="form-select">
                <option value="0">All Categories</option>
                <?php
                // Fetch categories for the filter
                $categories_query = $con->query("SELECT * FROM electrical_equipment_categories ORDER BY category_name ASC");
                if ($categories_query && $categories_query->num_rows > 0) {
                    while ($cat = $categories_query->fetch_assoc()) {
                        echo '<option value="' . $cat['id'] . '">' . htmlspecialchars($cat['category_name']) . '</option>';
                    }
                }
                ?>
              </select>
            </div>
          </div>
          
          <div class="table-responsive">
            <table class="table table-hover table-bordered">
              <thead class="table-light">
                <tr>
                  <th width="50">#</th>
                  <th>Equipment Name</th>
                  <th>Category</th>
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

document.addEventListener('DOMContentLoaded', function() {
    // ========== EQUIPMENT MODAL ==========

    // Initialize equipment modal
    const equipmentModal = new bootstrap.Modal(document.getElementById('addEquipmentModal'));
    if (equipmentModal) {
        document.getElementById('addEquipmentModal').addEventListener('shown.bs.modal', function() {
            loadEquipmentForModal();
        });
    }

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
                    showFeedbackModal('Success', data.message || 'Equipment added successfully!');
                    setTimeout(() => {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('addEquipmentModal'));
                        if (modal) modal.hide();
                        location.reload();
                    }, 1500);
                } else {
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

    // ========== MATERIALS MODAL ==========

    const addMaterialsModal = document.getElementById('addMaterialsModal');
    if (addMaterialsModal) {
        addMaterialsModal.addEventListener('shown.bs.modal', function() {
            loadMaterialsForModal();
        });

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
                        // Show success modal
                        const successModalHtml = `
                            <div class="modal fade" id="materialSuccessModal" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-body text-center p-4">
                                            <div class="mb-3">
                                                <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                                            </div>
                                            <h5 class="mb-3">Success!</h5>
                                            <p class="mb-4">${data.message || 'Materials have been added successfully.'}</p>
                                            <button type="button" class="btn btn-success" data-bs-dismiss="modal">
                                                <i class="fas fa-check me-1"></i> OK
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>`;
                        
                        // Add modal to the DOM
                        document.body.insertAdjacentHTML('beforeend', successModalHtml);
                        
                        // Show the modal
                        const successModal = new bootstrap.Modal(document.getElementById('materialSuccessModal'));
                        successModal.show();
                        
                        // Reload the page when modal is closed
                        document.getElementById('materialSuccessModal').addEventListener('hidden.bs.modal', function () {
                            window.location.reload();
                        });
                        
                        // Close the add materials modal
                        const addMaterialsModalInstance = bootstrap.Modal.getInstance(addMaterialsModal);
                        if (addMaterialsModalInstance) addMaterialsModalInstance.hide();
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showFeedbackModal(false, data.message || 'Failed to add materials. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showFeedbackModal(false, 'An error occurred while adding materials. Please try again.');
                })
                .finally(() => {
                    addBtn.disabled = false;
                    addBtn.innerHTML = originalBtnText;
                });
            });
        }
    }

    // ========== MATERIALS FILTERS AND TABLE ==========

    let allMaterials = [];

    function resetFilters() {
        document.getElementById('supplierFilter').value = '';
        document.getElementById('brandFilter').value = '';
        document.getElementById('specFilter').value = '';
        document.getElementById('priceSort').value = '';
        applyFilters();
    }

    function loadMaterialsForModal() {
        const tbody = document.getElementById('materialsTableBody');
        if (!tbody) return;
        fetch('get_available_materials.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.materials) {
                    allMaterials = data.materials;
                    updateFilters(allMaterials);
                    applyFilters();
                } else {
                    showError('Failed to load materials. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error loading materials:', error);
                showError('Error loading materials. Please check your connection.');
            });
    }

    function updateFilters(materials) {
        const suppliers = [...new Set(materials.map(m => m.supplier_name).filter(Boolean))];
        const supplierSelect = document.getElementById('supplierFilter');
        updateSelectOptions(supplierSelect, suppliers);

        const brands = [...new Set(materials.map(m => m.brand).filter(Boolean))];
        const brandSelect = document.getElementById('brandFilter');
        updateSelectOptions(brandSelect, brands);

        document.getElementById('supplierFilter').addEventListener('change', applyFilters);
        document.getElementById('brandFilter').addEventListener('change', applyFilters);
        document.getElementById('specFilter').addEventListener('input', applyFilters);
        document.getElementById('priceSort').addEventListener('change', applyFilters);
    }

    function updateSelectOptions(selectElement, options) {
        const currentValue = selectElement.value;
        while (selectElement.options.length > 1) selectElement.remove(1);
        options.sort().forEach(option => {
            const opt = document.createElement('option');
            opt.value = option;
            opt.textContent = option;
            selectElement.appendChild(opt);
        });
        if (options.includes(currentValue)) {
            selectElement.value = currentValue;
        }
    }

    function applyFilters() {
        if (!allMaterials || allMaterials.length === 0) return;
        const supplier = document.getElementById('supplierFilter').value;
        const brand = document.getElementById('brandFilter').value;
        const spec = document.getElementById('specFilter').value.toLowerCase();
        const priceSort = document.getElementById('priceSort').value;

        let filtered = [...allMaterials];
        if (supplier) filtered = filtered.filter(m => m.supplier_name === supplier);
        if (brand) filtered = filtered.filter(m => m.brand === brand);
        if (spec) {
            filtered = filtered.filter(m =>
                (m.specification && m.specification.toLowerCase().includes(spec)) ||
                (m.material_name && m.material_name.toLowerCase().includes(spec))
            );
        }
        if (priceSort === 'asc') {
            filtered.sort((a, b) => (parseFloat(a.material_price) || 0) - (parseFloat(b.material_price) || 0));
        } else if (priceSort === 'desc') {
            filtered.sort((a, b) => (parseFloat(b.material_price) || 0) - (parseFloat(a.material_price) || 0));
        }
        renderMaterialsInModal(filtered);
    }

    function showError(message) {
        const tbody = document.getElementById('materialsTableBody');
        if (!tbody) return;
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center text-danger py-4">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    ${message}
                </td>
            </tr>`;
    }

    function renderMaterialsInModal(materials) {
        const tbody = document.getElementById('materialsTableBody');
        if (!tbody) return;

        if (!materials || materials.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-4">
                        <i class="fas fa-box-open fa-2x text-muted mb-2"></i>
                        <p class="mb-0">No materials match the selected filters</p>
                        <button class="btn btn-sm btn-outline-secondary mt-2" onclick="resetFilters()">
                            <i class="fas fa-undo me-1"></i> Reset Filters
                        </button>
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

        setupQuantityControls();
        updateSelectedCount();
    }

    // ========== EQUIPMENT TABLE ==========

    function loadEquipmentForModal() {
        const tbody = document.getElementById('equipmentTableBody');
        if (!tbody) return;

        const categoryFilter = document.getElementById('categoryFilter');
        const categoryId = categoryFilter ? categoryFilter.value : 0;

        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Loading equipment...</p>
                </td>
            </tr>`;

        fetch(`get_available_equipment.php?category=${categoryId}`)
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
                    <td>${eq.category_name || 'Uncategorized'}</td>
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

    function setupEquipmentCheckboxes() {
        const checkboxes = document.querySelectorAll('.equipment-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedEquipment);
        });
    }

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

    function setupEquipmentSearch() {
        const searchInput = document.getElementById('equipmentSearchInput');
        const categoryFilter = document.getElementById('categoryFilter');

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('#equipmentTableBody tr[data-equipment-id]');
                rows.forEach(row => {
                    const equipmentName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    row.style.display = equipmentName.includes(searchTerm) ? '' : 'none';
                });
            });
        }

        if (categoryFilter) {
            categoryFilter.addEventListener('change', function() {
                loadEquipmentForModal();
            });
        }
    }

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

    // ========== QUANTITY CONTROLS ==========

    function setupQuantityControls() {
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

        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('quantity-input')) {
                updateRowTotal(e.target);
            }
        });

        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('material-checkbox')) {
                updateSelectedCount();
            }
        });
    }

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

    function updateSelectedCount() {
        const selectedCount = document.querySelectorAll('.material-checkbox:checked').length;
        const selectedCountElement = document.getElementById('selectedCount');
        if (selectedCountElement) {
            selectedCountElement.textContent = selectedCount;
        }
    }

    // ========== ALERTS & FEEDBACK ==========

    function showAlert(message, type = 'info') {
        const existingAlert = document.querySelector('.alert-dismissible');
        if (existingAlert) existingAlert.remove();
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.role = 'alert';
        alertDiv.innerHTML = `
            <i class="fas ${type === 'danger' ? 'fa-exclamation-circle' : 'fa-info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        const container = document.querySelector('.container-fluid') || document.body;
        container.insertBefore(alertDiv, container.firstChild);
        setTimeout(() => {
            const alert = bootstrap.Alert.getOrCreateInstance(alertDiv);
            if (alert) alert.close();
        }, 5000);
    }

    // You must define showFeedbackModal for the above code to work.
    // Example:
    window.showFeedbackModal = function(title, message) {
        // Implement your modal display logic here
        alert((title ? title + '\n\n' : '') + message);
    }

    // ========== END DOMContentLoaded ==========
});

// ========== EDIT PROJECT INFORMATION ==========

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
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';
                const response = await fetch('update_project_info.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    const editModal = bootstrap.Modal.getInstance(document.getElementById('editProjectInfoModal'));
                    editModal.hide();
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
                    document.body.insertAdjacentHTML('beforeend', successModalHtml);
                    const successModal = new bootstrap.Modal(document.getElementById('projectUpdateSuccessModal'));
                    successModal.show();
                    document.getElementById('projectUpdateSuccessModal').addEventListener('hidden.bs.modal', function () {
                        window.location.reload();
                    });
                } else {
                    errorDiv.textContent = result.message || 'Failed to update project information. Please try again.';
                    errorDiv.classList.remove('d-none');
                }
            } catch (error) {
                console.error('Error updating project information:', error);
                errorDiv.textContent = 'An error occurred while updating project information. Please try again.';
                errorDiv.classList.remove('d-none');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        });

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


</script>