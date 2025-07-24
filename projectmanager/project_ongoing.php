<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 3) {
    header("Location: ../login.php");
    exit();
}
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}
$userid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$user_email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
$user_firstname = isset($_SESSION['firstname']) ? $_SESSION['firstname'] : '';
$user_lastname = isset($_SESSION['lastname']) ? $_SESSION['lastname'] : '';
$user_name = trim($user_firstname . ' ' . $user_lastname);
$current_page = basename($_SERVER['PHP_SELF']);
// Handle AJAX password change (like pm_profile.php) - MUST BE BEFORE ANY OUTPUT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $response = ['success' => false, 'message' => ''];
    $current = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    if (!$current || !$new || !$confirm) {
        $response['message'] = 'All fields are required.';
    } elseif ($new !== $confirm) {
        $response['message'] = 'New passwords do not match.';
    } elseif (strlen($new) < 6) {
        $response['message'] = 'New password must be at least 6 characters.';
    } else {
        $user_row = $con->query("SELECT password FROM users WHERE id = '$userid'");
        if ($user_row && $user_row->num_rows > 0) {
            $user_data = $user_row->fetch_assoc();
            if (password_verify($current, $user_data['password'])) {
                $hashed = password_hash($new, PASSWORD_DEFAULT);
                $update = $con->query("UPDATE users SET password = '$hashed' WHERE id = '$userid'");
                if ($update) {
                    $response['success'] = true;
                    $response['message'] = 'Password changed successfully!';
                } else {
                    $response['message'] = 'Failed to update password.';
                }
            } else {
                $response['message'] = 'Current password is incorrect.';
            }
        } else {
            $response['message'] = 'User not found.';
        }
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

require_once 'project_add_functions.php';
require_once 'projects_update.php';
require_once 'projects_remove.php';

if (!isset($_GET['id'])) {
    header("Location: project_list.php");
    exit();
}

$project_id = $_GET['id'];

// Fetch project details
$project_query = mysqli_query($con, "SELECT * FROM projects WHERE project_id='$project_id' AND user_id='$userid'");

// If project not found or doesn't belong to user, redirect
if (mysqli_num_rows($project_query) == 0) {
    header("Location: project_list.php");
    exit();
}

$project = mysqli_fetch_assoc($project_query);

// Handle project update
if (isset($_POST['update_project'])) {
    $projectname = $_POST['projectname'];
    $projectlocation = $_POST['projectlocation'];
    $projectbudget = floatval($_POST['projectbudget']);
    $projectdeadline = $_POST['projectdeadline'];
    $projectstatus = $_POST['projectstatus'];
    $old_status = $project['status'];
    // Update project
    $update_query = "UPDATE projects SET project='$projectname', location='$projectlocation', budget='$projectbudget', deadline='$projectdeadline', status='$projectstatus' WHERE project_id='$project_id' AND user_id='$userid'";
    mysqli_query($con, $update_query) or die(mysqli_error($con));
    // If changing from Estimating (4) to On going (1), transfer reserved to quantity
    if ($old_status == '4' && $projectstatus == '1') {
        // Deduct all assigned materials from inventory
        $materials = mysqli_query($con, "SELECT material_id, quantity FROM project_add_materials WHERE project_id='$project_id'");
        while ($row = mysqli_fetch_assoc($materials)) {
            $material_id = intval($row['material_id']);
            $qty = intval($row['quantity']);
            mysqli_query($con, "UPDATE materials SET quantity = GREATEST(quantity - $qty, 0) WHERE id = '$material_id'");
        }
        $equipments = mysqli_query($con, "SELECT equipment_id FROM project_add_equipment WHERE project_id='$project_id' AND status='Planning'");
        while ($row = mysqli_fetch_assoc($equipments)) {
            $equipment_id = intval($row['equipment_id']);
            // Bawasan ang reserved_quantity, bawasan din ang quantity (actual use)
            mysqli_query($con, "UPDATE equipment SET reserved_quantity = GREATEST(reserved_quantity - 1, 0), quantity = GREATEST(quantity - 1, 0) WHERE id = '$equipment_id'");
            // Update status field as well
            $status_check = mysqli_query($con, "SELECT quantity, reserved_quantity FROM equipment WHERE id = '$equipment_id'");
            $eqrow = mysqli_fetch_assoc($status_check);
            $available = intval($eqrow['quantity']) - intval($eqrow['reserved_quantity']);
            $new_status = ($available <= 0) ? 'Not Available' : 'Available';
            mysqli_query($con, "UPDATE equipment SET status = '$new_status' WHERE id = '$equipment_id'");
        }
    }
    // Always insert project total into expenses (no more status check)
    // Calculate grand total for this project
      $emp_total = 0;
      $emp_query = mysqli_query($con, "SELECT total FROM project_add_employee WHERE project_id='$project_id'");
      while ($erow = mysqli_fetch_assoc($emp_query)) {
          $emp_total += floatval($erow['total']);
      }
      $mat_total = 0;
      $mat_query = mysqli_query($con, "SELECT total FROM project_add_materials WHERE project_id='$project_id'");
      while ($mrow = mysqli_fetch_assoc($mat_query)) {
          $mat_total += floatval($mrow['total']);
      }
      $equip_total = 0;
      $equip_query = mysqli_query($con, "SELECT pae.*, e.equipment_name, e.location, e.equipment_price AS price, e.depreciation, e.rental_fee FROM project_add_equipment pae LEFT JOIN equipment e ON pae.equipment_id = e.id WHERE pae.project_id = '$project_id'");
      while ($row = mysqli_fetch_assoc($equip_query)) {
          $equip_total += floatval($row['total']);
          $proj_equipments[] = $row;
      }
      $grand_total = $emp_total + $mat_total + $equip_total;
      $today = date('Y-m-d');
      $expense_sql = "INSERT INTO expenses (user_id, expense, expensedate, expensecategory, project_name, description) VALUES ('$userid', '$grand_total', '$today', 'Project', '$projectname', 'finished ang project')";
      mysqli_query($con, $expense_sql);
      // Refresh the page to show updated data
      header("Location: project_details.php?id=$project_id&updated=1");
      exit();
  }

// Handle project deletion
if (isset($_GET['delete'])) {
    // Delete the project
    mysqli_query($con, "DELETE FROM projects WHERE project_id='$project_id' AND user_id='$userid'") 
        or die(mysqli_error($con));
    
    // Redirect to project list
    header("Location: project_list.php?deleted=1");
    exit();
}


// Fetch positions for dropdown
$positions_result = mysqli_query($con, "SELECT position_id, title FROM positions ORDER BY title ASC");
$positions = [];
while ($row = mysqli_fetch_assoc($positions_result)) {
    $positions[] = $row;
}
// Fetch unique units for dropdown
$units_result = mysqli_query($con, "SELECT DISTINCT unit FROM materials WHERE unit IS NOT NULL AND unit != '' ORDER BY unit ASC");
$units = [];
while ($row = mysqli_fetch_assoc($units_result)) {
    $units[] = $row['unit'];
}
// Fetch employees for dropdown (for this user, exclude Foreman)
$employees_result = mysqli_query($con, "SELECT e.employee_id, e.first_name, e.last_name, e.contact_number, p.title as position_title, p.daily_rate FROM employees e LEFT JOIN positions p ON e.position_id = p.position_id WHERE e.user_id='$userid' AND LOWER(p.title) != 'foreman' ORDER BY e.last_name, e.first_name");
$employees = [];
while ($row = mysqli_fetch_assoc($employees_result)) {
    $employees[] = $row;
}
// Fetch materials for dropdown
$materials_result = mysqli_query($con, "SELECT * FROM materials WHERE status = 'Available' ORDER BY material_name ASC");
$materials = [];
while ($row = mysqli_fetch_assoc($materials_result)) {
    $materials[] = $row;
}
// Fetch project employees
$proj_emps = [];
$emp_total = 0;
$emp_query = mysqli_query($con, "SELECT pae.*, e.first_name, e.last_name FROM project_add_employee pae LEFT JOIN employees e ON pae.employee_id = e.employee_id WHERE pae.project_id = '$project_id'");
while ($row = mysqli_fetch_assoc($emp_query)) {
    $proj_emps[] = $row;
    $emp_total += floatval($row['total']);
}
// Fetch project materials
$proj_mats = [];
$mat_total = 0;
$mat_query = mysqli_query($con, "SELECT pam.*, m.supplier_name, m.material_price, m.labor_other, m.unit, m.material_name FROM project_add_materials pam LEFT JOIN materials m ON pam.material_id = m.id WHERE pam.project_id = '$project_id'");
while ($row = mysqli_fetch_assoc($mat_query)) {
    $proj_mats[] = $row;
    $mat_total += floatval($row['total']) + (isset($row['additional_cost']) ? floatval($row['additional_cost']) : 0);
}
// Fetch project equipments
$proj_equipments = [];
$equip_total = 0;
$equip_query = mysqli_query($con, "SELECT pae.*, e.equipment_name, e.location, e.equipment_price AS price, e.depreciation, e.rental_fee, e.status as equipment_status FROM project_add_equipment pae LEFT JOIN equipment e ON pae.equipment_id = e.id WHERE pae.project_id = '$project_id'");
while ($row = mysqli_fetch_assoc($equip_query)) {
    // Only add to total if equipment is not damaged
    if (strtolower($row['equipment_status']) !== 'damaged') {
        $equip_total += floatval($row['total']);
    }
    $proj_equipments[] = $row;
}
$grand_total = $emp_total + $mat_total + $equip_total;

// Fetch division progress for chart
$div_chart_labels = [];
$div_chart_data = [];
$div_chart_query = mysqli_query($con, "SELECT division_name, progress FROM project_divisions WHERE project_id='$project_id'");
while ($row = mysqli_fetch_assoc($div_chart_query)) {
    $div_chart_labels[] = $row['division_name'];
    $div_chart_data[] = (int)$row['progress'];
}
// Calculate overall project progress (average of all divisions)
$overall_progress = 0;
if (count($div_chart_data) > 0) {
    $avg = array_sum($div_chart_data) / count($div_chart_data);
    $all_full = (min($div_chart_data) == 100);
    $overall_progress = $all_full ? 100 : floor($avg);
}

$user = null;
$userprofile = '../uploads/default_profile.png';
if ($userid) {
    $result = $con->query("SELECT * FROM users WHERE id = '$userid'");
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_firstname = $user['firstname'];
        $user_lastname = $user['lastname'];
        $user_email = $user['email'];
        $userprofile = isset($user['profile_path']) && $user['profile_path'] ? '../uploads/' . $user['profile_path'] : '../uploads/default_profile.png';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <link rel="stylesheet" href="style.css" />
    <title>Ongoing Project</title>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div class="bg-white" id="sidebar-wrapper">
        <div class="user text-center py-4">
                <img class="img img-fluid rounded-circle mb-2 sidebar-profile-img" src="<?php echo isset($userprofile) ? $userprofile : (isset($_SESSION['userprofile']) ? $_SESSION['userprofile'] : '../uploads/default_profile.png'); ?>" width="70" alt="User Profile">
                <h5 class="mb-1 text-white"><?php echo htmlspecialchars($user_name); ?></h5>
                <p class="text-white small mb-0 text wh"><?php echo htmlspecialchars($user_email); ?></p>
                <hr style="border-top: 1px solid #fff; opacity: 0.3; margin: 12px 0 0 0;">
            </div>
            <div class="list-group list-group-flush ">
                <a href="pm_dashboard.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'pm_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>Dashboard
                </a>
                <a href="projects.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'projects.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i>Projects
                </a>
                <a href="expenses.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'expenses.php' ? 'active' : ''; ?>">
                    <i class="fas fa-wallet"></i>Expenses
                </a>
                <a href="materials.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'materials.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cubes"></i>Materials
                </a>
                <a href="equipment.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'equipment.php' ? 'active' : ''; ?>">
                    <i class="fas fa-wrench"></i>Equipment
                </a>
                <a href="suppliers.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'suppliers.php' ? 'active' : ''; ?>">
                    <i class="fas fa-truck"></i>Suppliers
                </a>
                <a href="employees.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'employees.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-friends"></i>Employees
                </a>
                <a href="positions.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'positions.php' ? 'active' : ''; ?>">
                    <i class="fas fa-briefcase"></i>Position
                </a>
            </div>
        </div>
        <!-- /#sidebar-wrapper -->

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-align-left success-text fs-4 me-3" id="menu-toggle"></i>
                    <h2 class="fs-2 m-0">Ongoing Project</h2>
                </div>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <?php include 'pm_notification.php'; ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle second-text fw-bold" href="#" id="navbarDropdown"
                                role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php echo htmlspecialchars($user_name); ?>
                                <img src="<?php echo $userprofile; ?>" alt="User" class="rounded-circle" width="30" height="30" style="margin-left: 8px;">
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="pm_profile.php">Profile</a></li>
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal">Change Password</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="container-fluid px-4">
            <!-- START CARD WRAPPER -->
            <div class="card shadow-sm mb-4">
              <div class="card-header bg-success text-white d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Ongoing Project Details</h4>
                <div class="d-flex gap-2">
                  <a href="projects.php" class="btn btn-light btn-sm">
                    <i class="fa fa-arrow-left"></i> Back to Projects
                  </a>
                  <a href="#" class="btn btn-danger btn-sm" id="exportProjectPdfBtn">
                    <i class="fas fa-file-export"></i> Generate
                  </a>
                </div>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-6">
                    <!-- Project Information Card -->
                    <div class="card mb-4 shadow-sm">
                      <div class="card-header bg-success text-white d-flex align-items-center">
                        <h5 class="mb-0 flex-grow-1">Project Information</h5>
                        <button type="button" class="btn btn-light btn-sm ml-auto" data-bs-toggle="modal" data-bs-target="#editProjectModal"><i class="fas fa-edit me-1"></i> Edit Project</button>
                        <button type="button" class="btn btn-light btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#viewPermitsModal"><i class="fas fa-file-alt me-1"></i> View Permits</button>
                        <button type="button" class="btn btn-light btn-sm ms-2 upload-files-btn" data-project-id="<?php echo $project_id; ?>" data-bs-toggle="modal" data-bs-target="#uploadFilesModal"><i class="fas fa-upload me-1"></i> Upload</button>
                      </div>
                      <div class="card-body">
                        <div class="row">
                          <div class="col-md-6 mb-2">
                            <div class="mb-2"><strong>Project Name:</strong> <?php echo htmlspecialchars($project['project']); ?></div>
                            <div class="mb-2"><strong>Location:</strong> <?php echo htmlspecialchars($project['location']); ?></div>
                            <div class="mb-2"><strong>Budget:</strong> <span class="text-success fw-bold">₱<?php echo number_format($project['budget'], 2); ?></span></div>
                            <div class="mb-2"><strong>Labor/Cost:</strong> <span class="text-primary fw-bold">₱<?php echo number_format($emp_total, 2); ?></span></div>
                            <div class="mb-2"><strong>Deadline:</strong> <span class="text-danger"><?php echo date("F d, Y", strtotime($project['deadline'])); ?></span></div>
                          </div>
                          <div class="col-md-6 mb-2">
                            <div class="mb-2"><strong>Foreman:</strong> <?php echo htmlspecialchars($project['foreman'] ?? ''); ?></div>
                            <div class="mb-2"><strong>Created:</strong> <?php echo date("F d, Y", strtotime($project['created_at'])); ?></div>
                          </div>
                        </div>
                        <div class="mb-2"><strong>Category:</strong> <?php echo htmlspecialchars($project['category']); ?></div>
                        <hr>
                        <div class="text-end font-weight-bold mt-3" style="font-size:1.3em; color:#222;">
                          <span style="font-size:1.3em; vertical-align:middle; margin-right:4px; font-weight:bold; color:#222;"></span> Grand Total (Employees + Materials + Equipment): <span style="font-weight:bold;color:#222">₱<?php echo number_format($grand_total, 2); ?></span>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <!-- Project Progress Card -->
                    <div class="card mb-4 shadow-sm">
                      <div class="card-header bg-success text-white d-flex align-items-center">
                        <h5 class="mb-0 flex-grow-1">Project Progress</h5>
                        <a href="project_progress.php?id=<?php echo $project_id; ?>" class="btn btn-light btn-sm ml-auto"><i class="fas fa-angle-double-right me-1"></i> Show more</a>
                      </div>
                      <div class="card-body">
                        <!-- Overall project progress bar -->
                        <div class="progress mb-3" style="height: 28px;">
                          <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $overall_progress; ?>%; font-size:1.1em;" aria-valuenow="<?php echo $overall_progress; ?>" aria-valuemin="0" aria-valuemax="100">
                            <?php echo $overall_progress; ?>%
                          </div>
                        </div>
                        <!-- Per-division chart -->
                        <div class="mb-3">
                          <canvas id="divisionProgressChart" height="180"></canvas>
                        </div>
                        <p><strong>Note:</strong> The progress bar shows the overall project progress (average of all divisions). The chart shows the progress of each division for this project.</p>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- Tabs for Employees, Materials, Equipments -->
                <ul class="nav nav-tabs mt-4" id="projectTabs" role="tablist">
                  <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="employees-tab" data-bs-toggle="tab" data-bs-target="#employees" type="button" role="tab" aria-controls="employees" aria-selected="true">Project Employees</button>
                  </li>
                  <li class="nav-item" role="presentation">
                    <button class="nav-link" id="materials-tab" data-bs-toggle="tab" data-bs-target="#materials" type="button" role="tab" aria-controls="materials" aria-selected="false">Project Materials</button>
                  </li>
                  <li class="nav-item" role="presentation">
                    <button class="nav-link" id="equipments-tab" data-bs-toggle="tab" data-bs-target="#equipments" type="button" role="tab" aria-controls="equipments" aria-selected="false">Project Equipments</button>
                  </li>
                </ul>
                <div class="tab-content" id="projectTabsContent">
                  <div class="tab-pane fade show active" id="employees" role="tabpanel" aria-labelledby="employees-tab">
                    <div class="card mb-3 shadow-sm mt-3">
                      <div class="card-header bg-success text-white d-flex align-items-center">
                        <span class="flex-grow-1">Project Employees</span>
                        <button class="btn btn-light btn-sm ml-auto" data-bs-toggle="modal" data-bs-target="#addEmployeeModal"><i class="fas fa-user-plus me-1"></i> Add Employee</button>
                      </div>
                      <div class="card-body p-0">
                        <div class="table-responsive">
                          <table class="table table-bordered mb-0">
                            <thead class="thead-light">
                              <tr>
                                <th>No.</th>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Daily Rate</th>
                                <th>Project Days</th>
                                <th>Total</th>
                                <th>Action</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php
                                // Compute project days once
                                $start_date = $project['start_date'];
                                $end_date = $project['deadline'];
                                $start = new DateTime($start_date);
                                $end = new DateTime($end_date);
                                $interval = $start->diff($end);
                                $project_days = $interval->days + 1;
                              ?>
                              <?php if (count($proj_emps) > 0): $i = 1; foreach ($proj_emps as $emp): ?>
                              <tr>
                                <td><?php echo $i++; ?></td>
                                <td style="font-weight:bold;color:#222;"><?php echo htmlspecialchars(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($emp['position']); ?></td>
                                <td><?php echo number_format($emp['daily_rate'], 2); ?></td>
                                <td><?php echo $project_days; ?></td>
                                <td style="font-weight:bold;color:#222;">₱<?php echo number_format($emp['daily_rate'] * $project_days, 2); ?></td>
                                <td>
                                  <form method="post" style="display:inline;">
                                    <input type="hidden" name="row_id" value="<?php echo $emp['id']; ?>">
                                    <button type="submit" name="remove_project_employee" class="btn btn-sm btn-danger" onclick="return confirm('Remove this employee?')"><i class="fas fa-trash"></i> Remove</button>
                                  </form>
                                </td>
                              </tr>
                              <?php endforeach; else: ?>
                              <tr><td colspan="7" class="text-center">No employees added</td></tr>
                              <?php endif; ?>
                            </tbody>
                            <tfoot>
                              <tr>
                                <th colspan="5" class="text-right">Total</th>
                                <th colspan="2" style="font-weight:bold;color:#222;">₱<?php
                                  $emp_total = 0;
                                  foreach ($proj_emps as $emp) {
                                    $emp_total += $emp['daily_rate'] * $project_days;
                                  }
                                  echo number_format($emp_total, 2);
                                ?></th>
                              </tr>
                            </tfoot>
                          </table>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="tab-pane fade" id="materials" role="tabpanel" aria-labelledby="materials-tab">
                    <div class="card mb-3 shadow-sm mt-3">
                      <div class="card-header bg-success text-white d-flex align-items-center">
                        <span class="flex-grow-1">Project Materials</span>
                        <button class="btn btn-light btn-sm ml-auto" data-bs-toggle="modal" data-bs-target="#addMaterialsModal"><i class="fas fa-plus-square me-1"></i> Add Materials</button>
                      </div>
                      <div class="card-body p-0">
                        <div class="table-responsive">
                          <table class="table table-bordered mb-0">
                            <thead class="table-secondary">
                              <tr>
                                <th>No.</th>
                                <th>Name</th>
                                <th>Unit</th>
                                <th>Material Price</th>
                                <th>Labor/Other</th>
                                <th>Quantity</th>
                                <th>Supplier</th>
                                <th>Additional Cost</th>
                                <th>Total</th>
                                <th>Action</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php if (count($proj_mats) > 0): $i = 1; foreach ($proj_mats as $mat): ?>
                              <tr>
                                <td><?php echo $i++; ?></td>
                                <td style="font-weight:bold;color:#222;"><?php echo htmlspecialchars($mat['material_name']); ?></td>
                                <td><?php echo htmlspecialchars($mat['unit']); ?></td>
                                <td><?php echo number_format($mat['material_price'], 2); ?></td>
                                <td><?php echo number_format($mat['labor_other'], 2); ?></td>
                                <td><?php echo $mat['quantity']; ?></td>
                                <td><?php echo isset($mat['supplier_name']) && $mat['supplier_name'] ? htmlspecialchars($mat['supplier_name']) : 'N/A'; ?></td>
                                <td>
                                  <?php $add_cost = isset($mat['additional_cost']) ? floatval($mat['additional_cost']) : 0; ?>
                                  <span class="text-primary">₱<?php echo number_format($add_cost, 2); ?></span>
                                  <button type="button" class="btn btn-link btn-sm p-0 ms-1" data-bs-toggle="modal" data-bs-target="#addCostModal<?php echo $mat['id']; ?>" title="Add/Edit Additional Cost"><i class="fas fa-plus-circle"></i></button>
                                </td>
                                <td style="font-weight:bold;color:#222;">₱<?php echo number_format(floatval($mat['total']) + $add_cost, 2); ?></td>
                                <td>
                                  <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#returnMaterialModal" data-row-id="<?php echo $mat['id']; ?>" data-max-qty="<?php echo $mat['quantity']; ?>"><i class="fas fa-undo"></i> Return</button>
                                </td>
                              </tr>
                              <!-- Add/Edit Additional Cost Modal -->
                              <div class="modal fade" id="addCostModal<?php echo $mat['id']; ?>" tabindex="-1" aria-labelledby="addCostModalLabel<?php echo $mat['id']; ?>" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                  <div class="modal-content">
                                    <form method="post">
                                      <div class="modal-header">
                                        <h5 class="modal-title" id="addCostModalLabel<?php echo $mat['id']; ?>">Add/Edit Additional Cost</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                      </div>
                                      <div class="modal-body">
                                        <input type="hidden" name="add_cost_row_id" value="<?php echo $mat['id']; ?>">
                                        <div class="form-group mb-3">
                                          <label for="additionalCostInput<?php echo $mat['id']; ?>">Additional Cost (₱)</label>
                                          <input type="number" step="0.01" min="0" class="form-control" id="additionalCostInput<?php echo $mat['id']; ?>" name="additional_cost" value="<?php echo $add_cost; ?>" required>
                                          <div class="form-text">Enter any extra cost incurred for this material (e.g. rush fee, extra delivery, etc).</div>
                                        </div>
                                      </div>
                                      <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="save_additional_cost" class="btn btn-success">Save</button>
                                      </div>
                                    </form>
                                  </div>
                                </div>
                              </div>
                              <?php endforeach; else: ?>
                              <tr><td colspan="10" class="text-center">No materials added</td></tr>
                              <?php endif; ?>
                            </tbody>
                            <tfoot>
                              <tr>
                                <th colspan="8" class="text-right">Total</th>
                                <th colspan="2" style="font-weight:bold;color:#222;">₱<?php
                                  $mat_total = 0;
                                  foreach ($proj_mats as $mat) {
                                    $mat_total += floatval($mat['total']) + (isset($mat['additional_cost']) ? floatval($mat['additional_cost']) : 0);
                                  }
                                  echo number_format($mat_total, 2);
                                ?></th>
                              </tr>
                            </tfoot>
                          </table>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="tab-pane fade" id="equipments" role="tabpanel" aria-labelledby="equipments-tab">
                    <div class="card mb-3 shadow-sm mt-3">
                      <div class="card-header bg-success text-white d-flex align-items-center">
                        <span class="flex-grow-1">Project Equipments</span>
                        <button class="btn btn-light btn-sm ml-auto" data-bs-toggle="modal" data-bs-target="#addEquipmentModal"><i class="fas fa-truck-loading me-1"></i> Add Equipment</button>
                      </div>
                      <div class="card-body p-0">
                        <div class="table-responsive">
                          <table class="table table-bordered mb-0">
                            <thead class="table-secondary">
                              <tr>
                                <th>No.</th>
                                <th>Name</th>
                                <th>Location</th>
                                <th>Price</th>
                                <th>Depreciation</th>
                                <th>Project Days</th>
                                <th>Total</th>
                                <th>Action</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php
                                // Compute project days once (already computed above, reuse if possible)
                                $start_date = $project['start_date'];
                                $end_date = $project['deadline'];
                                $start = new DateTime($start_date);
                                $end = new DateTime($end_date);
                                $interval = $start->diff($end);
                                $project_days = $interval->days + 1;
                              ?>
                              <?php if (count($proj_equipments) > 0): $i = 1; foreach ($proj_equipments as $eq): ?>
                              <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($eq['equipment_name']); ?></td>
                                <td><?php echo htmlspecialchars($eq['location'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($eq['price'], 2); ?></td>
                                <td>
                                  <?php
                                    if (is_numeric($eq['depreciation'])) {
                                      echo intval($eq['depreciation']) . ' years';
                                    } elseif (!empty($eq['depreciation'])) {
                                      echo htmlspecialchars($eq['depreciation']);
                                    } else {
                                      echo '-';
                                    }
                                  ?>
                                </td>
                                <td><?php echo $project_days; ?></td>
                                <td>
                                  <?php
                                    // Use the stored total value from the database instead of recalculating
                                    echo number_format(floatval($eq['total']), 2);
                                  ?>
                                </td>
                                <td>
                                  <?php if ($eq['status'] === 'damage'): ?>
                                    <span class="badge bg-danger">Damaged</span>
                                  <?php elseif ($eq['status'] === 'returned'): ?>
                                    <span class="badge bg-success">Returned</span>
                                  <?php else: ?>
                                    <form method="post" style="display:inline;">
                                      <input type="hidden" name="row_id" value="<?php echo $eq['id']; ?>">
                                      <button type="submit" name="return_project_equipment" class="btn btn-sm btn-warning" onclick="return confirm('Mark this equipment as returned?')">
                                        <i class="fas fa-undo"></i> Return
                                      </button>
                                    </form>
                                    <form method="post" style="display:inline; margin-left: 4px;">
                                      <input type="hidden" name="report_equipment" value="1">
                                      <input type="hidden" name="report_row_id" value="<?php echo $eq['id']; ?>">
                                      <input type="hidden" name="report_remarks" value="Damage Equipment">
                                      <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Mark this equipment as damaged?')"><i class="fas fa-exclamation-triangle"></i> Report Damage</button>
                                    </form>
                                  <?php endif; ?>
                                </td>
                              </tr>
                              <?php endforeach; else: ?>
                  
                                <tr><td colspan="8" class="text-center">No equipment added</td></tr>
                              <?php endif; ?>
                            </tbody>
                            <tfoot>
                              <tr>
                                <th colspan="6" class="text-right">Total</th>
                                <th colspan="2" style="font-weight:bold;color:#222;">₱<?php
                                  $equip_total = 0;
                                  foreach ($proj_equipments as $eq) {
                                    // Only add to total if equipment is not damaged
                                    if (strtolower($eq['equipment_status'] ?? '') !== 'damaged') {
                                        $equip_total += floatval($eq['total']);
                                    }
                                  }
                                  echo number_format($equip_total, 2);
                                ?></th>
                              </tr>
                            </tfoot>
                          </table>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!-- END CARD WRAPPER -->

            </div>
        </div>
    </div>
    <!-- /#page-content-wrapper -->
    </div>

<!-- Modals moved outside the card/container for proper Bootstrap modal functionality -->
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
          <div class="form-group">
            <label for="projectstatus">Status</label>
            <select class="form-control" id="projectstatus" name="projectstatus" required>
              <option value="1" <?php if($project['io'] == '1') echo 'selected'; ?>>On going</option>
              <option value="2" <?php if($project['io'] == '2') echo 'selected'; ?>>Finished</option>
              <option value="3" <?php if($project['io'] == '3') echo 'selected'; ?>>Canceled</option>
            </select>
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
          <div class="form-group">
            <label for="employeeName">Employee Name</label>
            <select class="form-control" id="employeeName" name="employeeName" required>
              <option value="" disabled selected>Select Employee</option>
              <?php foreach ($employees as $emp): ?>
                <option value="<?php echo htmlspecialchars($emp['employee_id']); ?>"
                  data-position="<?php echo htmlspecialchars($emp['position_title']); ?>"
                  data-contact="<?php echo htmlspecialchars($emp['contact_number']); ?>"
                  data-rate="<?php echo htmlspecialchars($emp['daily_rate']); ?>"
                ><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></option>
              <?php endforeach; ?>
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

<!-- Add Materials Modal -->
<div class="modal fade" id="addMaterialsModal" tabindex="-1" role="dialog" aria-labelledby="addMaterialsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addMaterialsModalLabel">Add Materials</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" id="addMaterialForm">
        <div class="modal-body">
          <input type="hidden" name="add_project_material" value="1">
          <div class="form-group">
            <label for="materialName">Material Name</label>
            <select class="form-control" id="materialName" name="materialName" required>
              <option value="" disabled selected>Select Material</option>
              <?php foreach ($materials as $mat): ?>
                <?php $qty = isset($mat['quantity']) ? intval($mat['quantity']) : 0; ?>
                <option value="<?php echo htmlspecialchars($mat['id']); ?>"
                  data-unit="<?php echo htmlspecialchars($mat['unit']); ?>"
                  data-price="<?php echo htmlspecialchars($mat['material_price']); ?>"
                  data-name="<?php echo htmlspecialchars($mat['material_name']); ?>"
                  data-qty="<?php echo $qty; ?>"
                  <?php echo $qty <= 0 ? 'disabled' : ''; ?>>
                  <?php echo htmlspecialchars($mat['material_name']) . ' (' . ($qty > 0 ? $qty . ' left' : 'Not Available') . ')'; ?>
                </option>
              <?php endforeach; ?>
            </select>
            <input type="hidden" id="materialNameText" name="materialNameText">
          </div>
          <div class="form-group">
            <label for="materialQty">Quantity</label>
            <input type="number" class="form-control" id="materialQty" name="materialQty" required>
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

<!-- Quantity Error Modal -->
<div class="modal fade" id="quantityErrorModal" tabindex="-1" aria-labelledby="quantityErrorModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-body">
        <span style="font-size: 3rem; color: #dc3545;">
          <i class="fas fa-times-circle"></i>
        </span>
        <h4 id="quantityErrorModalLabel">Invalid Quantity</h4>
        <p id="quantityErrorMsg">Your estimated quantity is not allowed. Please enter a value less than or equal to the available stock.</p>
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- Add Equipment Modal -->
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
              $all_equipment = mysqli_query($con, "SELECT * FROM equipment WHERE approval = 'Approved' ORDER BY equipment_name ASC");
              while ($eq = mysqli_fetch_assoc($all_equipment)) {
                $status = $eq['status'];
                $label = htmlspecialchars($eq['equipment_name']);
                if ($status === 'Not Available') {
                  $label .= ' (Need for Rent)';
                }
                echo '<option value="' . $eq['id'] . '" data-status="' . $status . '" data-price="' . htmlspecialchars($eq['equipment_price']) . '" data-depreciation="' . htmlspecialchars($eq['depreciation']) . '">' . $label . '</option>';
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
<script>
document.querySelectorAll('.permit-thumb').forEach(function(img) {
  img.addEventListener('click', function() {
    var modalImg = document.getElementById('permitImageModalImg');
    modalImg.src = this.getAttribute('data-img');
  });
});
</script>

<!-- Add a modal for reporting equipment -->
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
<script>
// Fill report modal with row_id when Report button is clicked
$(document).on('click', '.report-btn', function() {
  var rowId = $(this).data('row-id');
  $('#report_row_id').val(rowId);
});
</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        var el = document.getElementById("wrapper");
        var toggleButton = document.getElementById("menu-toggle");

        toggleButton.onclick = function () {
            el.classList.toggle("toggled");
        };
    </script>
     <script>
        feather.replace()
    </script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
  // Employee auto-fill and total
  var employeeName = document.getElementById('employeeName');
  var employeePosition = document.getElementById('employeePosition');
  var employeeContact = document.getElementById('employeeContact');
  var employeeRate = document.getElementById('employeeRate');
  var employeeDays = document.getElementById('employeeDays');
  var employeeTotal = document.getElementById('employeeTotal');

  function updateEmployeeTotal() {
    var rate = parseFloat(employeeRate.value) || 0;
    var days = parseInt(employeeDays.value) || 0;
    var total = rate * days;
    employeeTotal.value = total > 0 ? total.toFixed(2) : '';
  }

  if (employeeName) {
    employeeName.addEventListener('change', function() {
      var selected = employeeName.options[employeeName.selectedIndex];
      employeePosition.value = selected.getAttribute('data-position') || '';
      employeeContact.value = selected.getAttribute('data-contact') || '';
      employeeRate.value = selected.getAttribute('data-rate') || '';
      updateEmployeeTotal();
    });
  }
  if (employeeDays) {
    employeeDays.addEventListener('input', updateEmployeeTotal);
  }

  // Material auto-fill and total
  var materialName = document.getElementById('materialName');
  var materialUnit = document.getElementById('materialUnit');
  var materialPrice = document.getElementById('materialPrice');
  var materialNameText = document.getElementById('materialNameText');
  var materialQty = document.getElementById('materialQty');
  var materialTotal = document.getElementById('materialTotal');

  function updateMaterialTotal() {
    var qty = parseFloat(materialQty.value) || 0;
    var price = parseFloat(materialPrice.value) || 0;
    var total = qty * price;
    materialTotal.value = total > 0 ? total.toFixed(2) : '';
  }

  if (materialName) {
    materialName.addEventListener('change', function() {
      var selected = materialName.options[materialName.selectedIndex];
      materialUnit.value = selected.getAttribute('data-unit') || '';
      materialPrice.value = selected.getAttribute('data-price') || '';
      materialNameText.value = selected.getAttribute('data-name') || '';
      updateMaterialTotal();
    });
  }
  if (materialQty) {
    materialQty.addEventListener('input', updateMaterialTotal);
  }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var equipmentSelect = document.getElementById('equipmentSelect');
  var equipmentPriceInput = document.getElementById('equipmentPriceInput');
  var depreciationInput = document.getElementById('depreciationInput');
  var totalInput = document.getElementById('equipmentTotalInput');
  var projectDays = parseInt(document.getElementById('projectDaysInput').value) || 0;

  if (equipmentSelect) {
    equipmentSelect.addEventListener('change', function() {
      var selected = equipmentSelect.options[equipmentSelect.selectedIndex];
      var price = parseFloat(selected.getAttribute('data-price')) || 0;
      var depreciation = selected.getAttribute('data-depreciation');
      equipmentPriceInput.value = price.toFixed(2);
      // Remove decimal for depreciation if numeric
      if (depreciation && !isNaN(depreciation)) {
        depreciationInput.value = parseInt(depreciation);
      } else {
        depreciationInput.value = depreciation;
      }
      var deprYears = parseFloat(depreciation);
      if (deprYears > 0) {
        var deprPerDay = price / (deprYears * 365);
        totalInput.value = (deprPerDay * projectDays).toFixed(2);
      } else {
        totalInput.value = 'N/A';
      }
    });
  }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var equipmentSelect = document.getElementById('equipmentSelect');
  var addBtn = document.getElementById('addEquipmentBtn');
  var rentBtn = document.getElementById('requestForRentBtn');
  if (equipmentSelect) {
    equipmentSelect.addEventListener('change', function() {
      var selected = equipmentSelect.options[equipmentSelect.selectedIndex];
      if (selected && selected.getAttribute('data-status') === 'Not Available') {
        addBtn.style.display = 'none';
        rentBtn.style.display = '';
      } else {
        addBtn.style.display = '';
        rentBtn.style.display = 'none';
      }
    });
  }
});
</script>
<!-- Equipment Not Available Modal -->
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
document.addEventListener('DOMContentLoaded', function() {
  var params = new URLSearchParams(window.location.search);
  if (params.get('error') === 'equipment_not_available') {
    var modal = new bootstrap.Modal(document.getElementById('equipmentNotAvailableModal'));
    modal.show();
    // Remove the error param after showing
    params.delete('error');
    window.history.replaceState({}, document.title, window.location.pathname + (params.toString() ? '?' + params.toString() : ''));
  }
});
</script>
<!-- Equipment Return Success Modal -->
<div class="modal fade" id="equipmentReturnSuccessModal" tabindex="-1" aria-labelledby="equipmentReturnSuccessModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-body">
        <span style="font-size: 3rem; color: #28a745;">
          <i class="fas fa-check-circle"></i>
        </span>
        <h4 id="equipmentReturnSuccessModalLabel">Success!</h4>
        <p>Equipment returned successfully!</p>
        <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var params = new URLSearchParams(window.location.search);
  if (params.get('equipreturned') === '1') {
    var modal = new bootstrap.Modal(document.getElementById('equipmentReturnSuccessModal'));
    modal.show();
    // Remove the param after showing
    params.delete('equipreturned');
    window.history.replaceState({}, document.title, window.location.pathname + (params.toString() ? '?' + params.toString() : ''));
  }
});
</script>
<script>
// Store all equipment data in JS for filtering
var allEquipment = <?php
  $all_equipment = mysqli_query($con, "SELECT * FROM equipment WHERE approval = 'Approved' ORDER BY equipment_name ASC");
  $equipment_js = [];
  while ($eq = mysqli_fetch_assoc($all_equipment)) {
    $equipment_js[] = [
      'id' => $eq['id'],
      'name' => $eq['equipment_name'],
      'category' => $eq['category'],
      'price' => $eq['equipment_price'],
      'depreciation' => $eq['depreciation'],
      'rental_fee' => $eq['rental_fee'],
      'quantity' => $eq['quantity']
    ];
  }
  echo json_encode($equipment_js);
?>;
</script>
<!-- Return Material Modal -->
<div class="modal fade" id="returnMaterialModal" tabindex="-1" aria-labelledby="returnMaterialModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h5 class="modal-title" id="returnMaterialModalLabel">Return Material</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="row_id" id="returnMaterialRowId">
          <div class="mb-3">
            <label for="returnMaterialQty" class="form-label">Quantity to return</label>
            <input type="number" class="form-control" id="returnMaterialQty" name="return_quantity" min="1" value="1" required>
            <div class="form-text" id="returnMaterialMaxInfo"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="return_project_material" class="btn btn-success">Return</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var returnModal = document.getElementById('returnMaterialModal');
  var rowIdInput = document.getElementById('returnMaterialRowId');
  var qtyInput = document.getElementById('returnMaterialQty');
  var maxInfo = document.getElementById('returnMaterialMaxInfo');
  var returnButtons = document.querySelectorAll('button[data-bs-target="#returnMaterialModal"]');
  returnButtons.forEach(function(btn) {
    btn.addEventListener('click', function() {
      var rowId = btn.getAttribute('data-row-id');
      var maxQty = btn.getAttribute('data-max-qty');
      rowIdInput.value = rowId;
      qtyInput.value = 1;
      qtyInput.max = maxQty;
      maxInfo.textContent = 'Max: ' + maxQty;
    });
  });
});
</script>
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

<?php
// Handle save additional cost
if (isset($_POST['save_additional_cost']) && isset($_POST['add_cost_row_id'])) {
  $row_id = intval($_POST['add_cost_row_id']);
  $additional_cost = floatval($_POST['additional_cost']);
  mysqli_query($con, "UPDATE project_add_materials SET additional_cost='$additional_cost' WHERE id='$row_id'");
  // Optionally, update the total column as well if you want to store it
  echo '<script>window.location.href = "project_details.php?id=' . $project_id . '&addmat=1";</script>';
  exit();
}
?>

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
<script>
document.addEventListener('DOMContentLoaded', function() {
  var exportBtn = document.getElementById('exportProjectPdfBtn');
  if (exportBtn) {
    exportBtn.addEventListener('click', function(e) {
      e.preventDefault();
      var modal = new bootstrap.Modal(document.getElementById('exportProjectPdfModal'));
      modal.show();
    });
  }
  var confirmExportBtn = document.getElementById('confirmExportProjectPdf');
  if (confirmExportBtn) {
    confirmExportBtn.addEventListener('click', function(e) {
      e.preventDefault();
      var modalEl = document.getElementById('exportProjectPdfModal');
      var modalInstance = bootstrap.Modal.getInstance(modalEl);
      if (modalInstance) modalInstance.hide();
      setTimeout(function() {
        window.open('export_project_pdf.php?id=<?php echo $project_id; ?>', '_blank');
        setTimeout(function() { location.reload(); }, 1000);
      }, 300);
    });
  }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Activate tab based on URL hash
  var hash = window.location.hash;
  if (hash) {
    var tabTrigger = document.querySelector('button[data-bs-target="' + hash + '"]');
    if (tabTrigger) {
      var tab = new bootstrap.Tab(tabTrigger);
      tab.show();
    }
  }
  // Update hash when tab is changed
  var tabButtons = document.querySelectorAll('#projectTabs button[data-bs-toggle="tab"]');
  tabButtons.forEach(function(btn) {
    btn.addEventListener('shown.bs.tab', function(e) {
      var target = btn.getAttribute('data-bs-target');
      if (target) {
        history.replaceState({}, document.title, target);
      }
    });
  });
});
</script>
</body>

</html>