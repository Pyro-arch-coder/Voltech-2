<?php
ob_start();
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_project_material'])) {
    $row_id = intval($_POST['row_id']);
    $project_id = intval($_GET['id']);
    mysqli_query($con, "DELETE FROM project_estimating_materials WHERE id='$row_id'");
    header("Location: project_estimation.php?id=$project_id&removemat=1");
    exit();
}

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
    $projectstartdate = $_POST['projectstartdate'];
    $projectdeadline = $_POST['projectdeadline'];
    
    // Update project
    $update_query = "UPDATE projects SET project='$projectname', location='$projectlocation', budget='$projectbudget', start_date='$projectstartdate', deadline='$projectdeadline' WHERE project_id='$project_id' AND user_id='$userid'";
    mysqli_query($con, $update_query) or die(mysqli_error($con));

    // Refresh the page to show updated data
    header("Location: project_estimation.php?id=$project_id&updated=1");
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

// Fetch unique units for dropdown
$units_result = mysqli_query($con, "SELECT DISTINCT unit FROM materials WHERE unit IS NOT NULL AND unit != '' ORDER BY unit ASC");
$units = [];
while ($row = mysqli_fetch_assoc($units_result)) {
    $units[] = $row['unit'];
}

// Fetch materials for dropdown
$materials_result = mysqli_query($con, "SELECT * FROM materials WHERE status = 'Available' ORDER BY material_name ASC");
$materials = [];
while ($row = mysqli_fetch_assoc($materials_result)) {
    $materials[] = $row;
}

// Fetch project materials (now using ONLY project_estimating_materials)
$proj_mats = [];
$mat_total = 0;
$mat_query = mysqli_query($con, "SELECT pam.*, m.supplier_name, m.labor_other, m.unit, m.material_name FROM project_estimating_materials pam LEFT JOIN materials m ON pam.material_id = m.id WHERE pam.project_id = '$project_id'");
while ($row = mysqli_fetch_assoc($mat_query)) {
    $proj_mats[] = $row;
    $mat_total += floatval($row['total']);
}

$grand_total = $mat_total;

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
    <title>Project Estimation</title>
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
                    <i class="fas fa-align-left primary-text fs-4 me-3" id="menu-toggle"></i>
                    <h2 class="fs-2 m-0">Project Estimation</h2>
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
                <h4 class="mb-0">Project Estimation Details</h4>
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
                      </div>
                      <div class="card-body">
                        <div class="row">
                          <div class="col-md-6 mb-2">
                            <div class="mb-2"><strong>Project Name:</strong> <?php echo htmlspecialchars($project['project']); ?></div>
                            <div class="mb-2"><strong>Location:</strong> <?php echo htmlspecialchars($project['location']); ?></div>
                            <div class="mb-2"><strong>Budget:</strong> <span class="text-success fw-bold">₱<?php echo number_format($project['budget'], 2); ?></span></div>
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
                          <span style="font-size:1.3em; vertical-align:middle; margin-right:4px; font-weight:bold; color:#222;"></span> Grand Total (Materials): <span style="font-weight:bold;color:#222">₱<?php echo number_format($grand_total, 2); ?></span>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    
                  </div>
                </div>

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
                            <td style="font-weight:bold;color:#222;">₱<?php echo number_format(floatval($mat['total']), 2); ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="row_id" value="<?php echo $mat['id']; ?>">
                                    <button type="submit" name="remove_project_material" class="btn btn-sm btn-danger" onclick="return confirm('Remove this material?')"><i class="fas fa-trash"></i> Remove</button>
                                </form>
                            </td>
                            </tr>
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
      <form method="post" id="addMaterialForm">
        <div class="modal-body">
          <input type="hidden" name="add_estimation_material" value="1">
          <div class="form-group">
            <label for="materialName">Material Name</label>
            <select class="form-control" id="materialName" name="materialName" required>
              <option value="" disabled selected>Select Material</option>
              <?php foreach ($materials as $mat): ?>
                <option value="<?php echo htmlspecialchars($mat['id']); ?>"
                  data-unit="<?php echo htmlspecialchars($mat['unit']); ?>"
                  data-price="<?php echo htmlspecialchars($mat['material_price']); ?>"
                  data-labor="<?php echo htmlspecialchars($mat['labor_other']); ?>"
                  data-name="<?php echo htmlspecialchars($mat['material_name']); ?>">
                  <?php echo htmlspecialchars($mat['material_name']) . ' (₱' . number_format(floatval($mat['material_price']), 2) . ')'; ?>
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
      <div class="modal-body text-center">
        <img id="permitImageModalImg" src="" alt="Permit Preview" style="max-width:100%; max-height:80vh; border-radius:8px;">
      </div>
    </div>
  </div>
</div>

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
  // Material auto-fill and total
  var materialName = document.getElementById('materialName');
  var materialUnit = document.getElementById('materialUnit');
  var materialPrice = document.getElementById('materialPrice');
  var laborOther = document.getElementById('laborOther');
  var materialNameText = document.getElementById('materialNameText');
  var materialQty = document.getElementById('materialQty');
  var materialTotal = document.getElementById('materialTotal');

  function updateMaterialTotal() {
    var qty = parseFloat(materialQty.value) || 0;
    var price = parseFloat(materialPrice.value) || 0;
    var labor = parseFloat(laborOther.value) || 0;
    var total = (price + labor) * qty;
    materialTotal.value = total > 0 ? total.toFixed(2) : '';
  }

  if (materialName) {
    materialName.addEventListener('change', function() {
      var selected = materialName.options[materialName.selectedIndex];
      materialUnit.value = selected.getAttribute('data-unit') || '';
      materialPrice.value = selected.getAttribute('data-price') || '';
      laborOther.value = selected.getAttribute('data-labor') || '';
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
// Change Password AJAX (like pm_profile.php)
document.addEventListener('DOMContentLoaded', function() {
  var changePasswordForm = document.getElementById('changePasswordForm');
  var feedbackDiv = document.getElementById('changePasswordFeedback');
  if (changePasswordForm) {
    changePasswordForm.addEventListener('submit', function(e) {
      e.preventDefault();
      feedbackDiv.innerHTML = '';
      var formData = new FormData(changePasswordForm);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', '', true);
      xhr.onload = function() {
        try {
          var res = JSON.parse(xhr.responseText);
          if (res.success) {
            feedbackDiv.innerHTML = '<div class="alert alert-success">' + res.message + '</div>';
            changePasswordForm.reset();
            setTimeout(function() {
              var modal = bootstrap.Modal.getInstance(document.getElementById('changePasswordModal'));
              if (modal) modal.hide();
            }, 1200);
          } else {
            feedbackDiv.innerHTML = '<div class="alert alert-danger">' + res.message + '</div>';
          }
        } catch (err) {
          feedbackDiv.innerHTML = '<div class="alert alert-danger">Unexpected error. Please try again.</div>';
        }
      };
      formData.append('change_password', '1');
      xhr.send(formData);
    });
  }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Material auto-fill and total
  var materialName = document.getElementById('materialName');
  var materialUnit = document.getElementById('materialUnit');
  var materialPrice = document.getElementById('materialPrice');
  var laborOther = document.getElementById('laborOther');
  var materialNameText = document.getElementById('materialNameText');
  var materialQty = document.getElementById('materialQty');
  var materialTotal = document.getElementById('materialTotal');

  function updateMaterialTotal() {
    var qty = parseFloat(materialQty.value) || 0;
    var price = parseFloat(materialPrice.value) || 0;
    var labor = parseFloat(laborOther.value) || 0;
    var total = (price + labor) * qty;
    materialTotal.value = total > 0 ? total.toFixed(2) : '';
  }

  if (materialName) {
    materialName.addEventListener('change', function() {
      var selected = materialName.options[materialName.selectedIndex];
      materialUnit.value = selected.getAttribute('data-unit') || '';
      materialPrice.value = selected.getAttribute('data-price') || '';
      laborOther.value = selected.getAttribute('data-labor') || '';
      materialNameText.value = selected.getAttribute('data-name') || '';
      updateMaterialTotal();
    });
  }
  if (materialQty) {
    materialQty.addEventListener('input', updateMaterialTotal);
  }
});
</script>
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
<script>
function showFeedbackModal(success, message, error_code = '', query_param = '') {
  var icon = document.getElementById('feedbackIcon');
  var title = document.getElementById('feedbackTitle');
  var msg = document.getElementById('feedbackMessage');
  if (success) {
    icon.innerHTML = '<i class="fas fa-check-circle" style="color:#28a745"></i>';
    title.textContent = 'Success!';
    msg.textContent = message;
  } else {
    icon.innerHTML = '<i class="fas fa-times-circle" style="color:#dc3545"></i>';
    title.textContent = 'Error!';
    msg.textContent = message;
  }
  var feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
  feedbackModal.show();
  // Remove the query param after showing the modal
  window.history.replaceState({}, document.title, window.location.pathname + window.location.search.replace(/([&?](addmat|removemat|returnmat|error)=[^&]*)/, ''));
}
document.addEventListener('DOMContentLoaded', function() {
  var params = new URLSearchParams(window.location.search);
  if (params.get('addmat') === '1') {
    showFeedbackModal(true, 'Material added successfully!');
    params.delete('addmat');
    window.history.replaceState({}, document.title, window.location.pathname + (params.toString() ? '?' + params.toString() : ''));
  } else if (params.get('removemat') === '1') {
    showFeedbackModal(true, 'Material removed successfully!');
    params.delete('removemat');
    window.history.replaceState({}, document.title, window.location.pathname + (params.toString() ? '?' + params.toString() : ''));
  }
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
        window.open('export_estimation_materials.php?id=<?php echo $project_id; ?>', '_blank');
        setTimeout(function() { location.reload(); }, 1000);
      }, 300);
    });
  }
});
</script>

<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_estimation_material'])) {
    $material_id = intval($_POST['materialName']);
    $material_name = isset($_POST['materialNameText']) ? $_POST['materialNameText'] : '';
    $unit = isset($_POST['materialUnit']) ? $_POST['materialUnit'] : '';
    $material_price = floatval($_POST['materialPrice']);
    $labor_other = floatval($_POST['laborOther']);
    $quantity = intval($_POST['materialQty']);
    $project_id = intval($_GET['id']);
    $total = ($material_price + $labor_other) * $quantity;
    $sql = "INSERT INTO project_estimating_materials (project_id, material_id, material_name, unit, material_price, quantity, total) VALUES ('$project_id', '$material_id', '$material_name', '$unit', '$material_price', '$quantity', '$total')";
    mysqli_query($con, $sql);
    header("Location: project_estimation.php?id=$project_id&addmat=1");
    exit();
}
?>
</body>

</html>