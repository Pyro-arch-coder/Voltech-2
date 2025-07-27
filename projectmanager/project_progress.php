<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 3) {
    header("Location: ../login.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $con = new mysqli("localhost", "root", "", "voltech2");
    if ($con->connect_error) {
        $response = ['success' => false, 'message' => 'Database connection failed.'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    $userid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
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
$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$project_id) {
    header("Location: projects.php");
    exit();
}
// Fetch project details
$project_query = $con->query("SELECT * FROM projects WHERE project_id='$project_id' AND user_id='$userid'");
if ($project_query->num_rows == 0) {
    header("Location: projects.php");
    exit();
}
$project = $project_query->fetch_assoc();

// Fetch divisions for this project
$divisions = [];
$divisions_result = mysqli_query($con, "SELECT * FROM project_divisions WHERE project_id = '$project_id' ORDER BY id ASC");
if ($divisions_result) {
    while ($row = mysqli_fetch_assoc($divisions_result)) {
        // Ensure all required fields have default values
        $divisions[] = [
            'id' => $row['id'] ?? 0,
            'division_name' => $row['division_name'] ?? 'Unnamed Task',
            'start_date' => $row['start_date'] ?? null,
            'deadline' => $row['deadline'] ?? null,
            'status' => $row['status'] ?? 'Not Started',
            'progress' => $row['progress'] ?? 0
        ];
    }
}

// Handle division progress update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_division'])) {
    $division_id = intval($_POST['division_id']);
    $progress = max(0, min(100, intval($_POST['progress'])));
    $status = $progress == 100 ? 'Completed' : ($progress > 0 ? 'In Progress' : 'Not Started');
    $updated_at = date('Y-m-d');
    
    // Get current status for comparison
    $current_status_query = mysqli_query($con, "SELECT status FROM project_divisions WHERE id = '$division_id'");
    $current_status = '';
    if ($current_status_query && $row = mysqli_fetch_assoc($current_status_query)) {
        $current_status = $row['status'];
    }
    
    // Only update if status has changed
    if ($status !== $current_status) {
        mysqli_query($con, "UPDATE project_divisions SET 
            progress='$progress', 
            status='$status',
            updated_at='$updated_at' 
            WHERE id='$division_id' AND project_id='$project_id'");
    } else {
        mysqli_query($con, "UPDATE project_divisions SET 
            progress='$progress',
            updated_at='$updated_at' 
            WHERE id='$division_id' AND project_id='$project_id'");
    }
        
    header("Location: project_progress.php?id=$project_id&updated=1");
    exit();
}
// Handle add division
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_division'])) {
    $division_name = trim(mysqli_real_escape_string($con, $_POST['division_name']));
    if ($division_name !== '') {
        mysqli_query($con, "INSERT INTO project_divisions (project_id, division_name, progress) VALUES ('$project_id', '$division_name', 0)");
    }
    header("Location: project_progress.php?id=$project_id&added=1");
    exit();
}

// Handle add subtask
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subtask'])) {
    $division_id = intval($_POST['division_id']);
    $subtask_name = trim(mysqli_real_escape_string($con, $_POST['subtask_name']));
    
    if ($division_id > 0 && $subtask_name !== '') {
        // Insert new subtask into project_subtasks table
        $stmt = $con->prepare("INSERT INTO project_subtask  (division_id, name) VALUES (?, ?)");
        $stmt->bind_param('is', $division_id, $subtask_name);
        
        if ($stmt->execute()) {
            header("Location: project_progress.php?id=$project_id&subtask_added=1");
        } else {
            header("Location: project_progress.php?id=$project_id&error=subtask_failed");
        }
        $stmt->close();
        exit();
    }
    
    header("Location: project_progress.php?id=$project_id&error=invalid_input");
    exit();
}
// Handle rename division
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_division'])) {
    $division_id = intval($_POST['division_id']);
    $new_name = trim(mysqli_real_escape_string($con, $_POST['new_division_name']));
    if ($new_name !== '') {
        mysqli_query($con, "UPDATE project_divisions SET division_name='$new_name' WHERE id='$division_id' AND project_id='$project_id'");
    }
    header("Location: project_progress.php?id=$project_id&renamed=1");
    exit();
}
// Handle delete division
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_division'])) {
    $division_id = intval($_POST['division_id']);
    mysqli_query($con, "DELETE FROM project_divisions WHERE id='$division_id' AND project_id='$project_id'");
    header("Location: project_progress.php?id=$project_id&deleted=1");
    exit();
}
// Fetch project details with dates
$project_details = [];
$project_query = $con->query("SELECT start_date, deadline FROM projects WHERE project_id = '$project_id'");
if ($project_query && $project_query->num_rows > 0) {
    $project_details = $project_query->fetch_assoc();
}

// Fetch divisions for this project
$divisions = [];
$res = mysqli_query($con, "SELECT id, division_name, progress, status, updated_at FROM project_divisions WHERE project_id = '$project_id'");
while ($row = mysqli_fetch_assoc($res)) {
    // Add project dates to each division
    $row['start_date'] = $project_details['start_date'] ?? null;
    $row['deadline'] = $project_details['deadline'] ?? null;
    // Always calculate status based on progress
    $row['status'] = $row['progress'] == 100 ? 'Completed' : ($row['progress'] > 0 ? 'In Progress' : 'Not Started');
    
    // Update status in database if it's different
    if (empty($row['status']) || $row['status'] != $row['status']) {
        mysqli_query($con, "UPDATE project_divisions SET status = '{$row['status']}' WHERE id = '{$row['id']}'");
    }
    $row['subtasks'] = '0'; // Default subtasks count
    $divisions[] = $row;
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
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <title>Project Progress</title>
</head>
<body>
<div class="d-flex" id="wrapper">
    <!-- Sidebar -->
    <div class="bg-white" id="sidebar-wrapper">
        <div class="user text-center py-4">
            <img class="img img-fluid rounded-circle mb-2 sidebar-profile-img" src="<?php echo isset($userprofile) ? $userprofile : (isset($_SESSION['userprofile']) ? $_SESSION['userprofile'] : '../uploads/default_profile.png'); ?>" width="70" alt="User Profile">
            <h5 class="mb-1 text-white"><?php echo htmlspecialchars($user_name); ?></h5>
            <p class="text-white small mb-0"><?php echo htmlspecialchars($user_email); ?></p>
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
    <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4">
            <div class="d-flex align-items-center">
                <i class="fas fa-align-left primary-text fs-4 me-3" id="menu-toggle"></i>
                <h2 class="fs-2 m-0">Project Progress</h2>
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
        <div class="container-fluid px-4 py-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white d-flex align-items-center justify-content-between">
                    <h4 class="mb-0">Progress for: <?php echo htmlspecialchars($project['project']); ?></h4>
                    <div>
                        <button  class="btn btn-light btn-sm ml-auto" data-bs-toggle="modal" data-bs-target="#addDivisionModal"><i class="fas fa-plus"></i> Add Tasks</button>
                        <a href="project_actual.php?id=<?php echo $project_id; ?>" class="btn btn-light btn-sm">
                            <i class="fa fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (isset($_GET['updated'])): ?>
                        <div class="alert alert-success">Progress updated successfully!</div>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-secondary">
                                <tr>
                                    <th style="width: 30px;">#</th>
                                    <th>Tasks</th>
                                    <th>Start Date </th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Progress</th>
                                    <th class="d-flex justify-content-between align-items-center">
                                        <span>Subtasks</span>
                                        <button type="button" class="btn btn-sm btn-success ms-2" data-bs-toggle="modal" data-bs-target="#addSubtaskModal">
                                            <i class="fas fa-plus"></i> Add Subtask
                                        </button>
                                    </th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $taskCounter = 1;
                                foreach ($divisions as $div): 
                                ?>
                                <tr>
                                    <td><?php echo $taskCounter++; ?></td>
                                    <td><?php echo htmlspecialchars($div['division_name']); ?></td>
                                    <td><?php echo $div['start_date'] ? date('F d, Y', strtotime($div['start_date'])) : '-'; ?></td>
                                    <td><?php echo $div['deadline'] ? date('F d, Y', strtotime($div['deadline'])) : '-'; ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            $status = strtolower($div['status']);
                                            echo ($status == 'completed') ? 'success' : (($status == 'in progress') ? 'primary' : 'warning');
                                        ?>">
                                            <?php echo $div['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-<?php 
                                                $progress = intval($div['progress']);
                                                echo $progress == 100 ? 'success' : ($progress > 50 ? 'primary' : ($progress > 0 ? 'warning' : 'secondary'));
                                            ?>" role="progressbar" 
                                                style="width: <?php echo $progress; ?>%;" 
                                                aria-valuenow="<?php echo $progress; ?>" 
                                                aria-valuemin="0" 
                                                aria-valuemax="100">
                                                <?php echo $progress; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                  // Get subtasks for this division from project_subtasks table
                                          $subtasks = [];
                                          $subtask_result = mysqli_query($con, "SELECT * FROM project_subtask WHERE division_id = '" . $div['id'] . "' ORDER BY created_at ASC");
                                          if ($subtask_result) {
                                              $taskNumber = 1;
                                              while ($subtask = mysqli_fetch_assoc($subtask_result)) {
                                                  $subtasks[] = [
                                                      'id' => $subtask['id'],
                                                      'name' => $taskNumber . '. ' . $subtask['name'],
                                                      'is_completed' => (bool)$subtask['is_completed'],
                                                      'created_at' => $subtask['created_at']
                                                  ];
                                                  $taskNumber++;
                                              }
                                          }  if (is_array($subtasks) && count($subtasks) > 0) {
                                            echo '<div class="list-unstyled mb-0">';
                                            foreach ($subtasks as $subtask) {
                                                $checked = $subtask['is_completed'] ? 'checked' : '';
                                                echo '<div class="form-check mb-1">
                                                    <input class="form-check-input subtask-checkbox" type="checkbox" data-subtask-id="' . $subtask['id'] . '" ' . $checked . '>
                                                    <label class="form-check-label">' . htmlspecialchars($subtask['name']) . '</label>
                                                </div>';
                                            }
                                            echo '</div>';
                                        } else {
                                            echo '<span class="text-muted">No subtasks</span>';
                                        }
                                        ?>

                                    </td>
                                    <td>
                                        <button class="btn btn-warning btn-sm text-dark" data-bs-toggle="modal" data-bs-target="#editDivisionModal<?php echo $div['id']; ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="delete_division" value="1">
                                            <input type="hidden" name="division_id" value="<?php echo $div['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this division?')"><i class="fas fa-trash"></i> Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <!-- Edit Modal for this division -->
                                <div class="modal fade" id="editDivisionModal<?php echo $div['id']; ?>" tabindex="-1">
                                  <div class="modal-dialog">
                                    <form method="post">
                                      <input type="hidden" name="division_id" value="<?php echo $div['id']; ?>">
                                      <div class="modal-content">
                                        <div class="modal-header">
                                          <h5 class="modal-title">Edit Division: <?php echo htmlspecialchars($div['division_name']); ?></h5>
                                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                          <div class="mb-3">
                                            <label>Division Name</label>
                                            <input type="text" name="new_division_name" class="form-control" value="<?php echo htmlspecialchars($div['division_name']); ?>" required>
                                          </div>
                                          <div class="mb-3">
                                            <label>Progress (%)</label>
                                            <input type="number" name="progress" class="form-control" min="0" max="100" value="<?php echo intval($div['progress']); ?>" required>
                                          </div>
                                        </div>
                                        <div class="modal-footer">
                                          <button type="submit" name="update_division" class="btn btn-success">Save</button>
                                          <button type="submit" name="rename_division" class="btn btn-info">Rename</button>
                                        </div>
                                      </div>
                                    </form>
                                  </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Add Division Modal -->
<div class="modal fade" id="addDivisionModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post">
      <input type="hidden" name="add_division" value="1">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Tasks</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label>Task Name</label>
            <input type="text" name="division_name" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Add</button>
        </div>
      </div>
    </form>
  </div>
</div>
<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <?php if (isset($_GET['added'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Division added successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['subtask_added'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Subtask added successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php 
if (isset($_GET['error'])): 
    $error_message = 'Error adding subtask. Please try again.';
    if ($_GET['error'] === 'subtask_failed') {
        $error_message = 'Failed to save subtask. Please try again.';
    } elseif ($_GET['error'] === 'invalid_input') {
        $error_message = 'Please enter a valid subtask name and select a task.';
    }
?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
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

<script>
// Make sure jQuery is loaded
if (typeof jQuery == 'undefined') {
    document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
}

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

<!-- Add Subtask Modal -->
<div class="modal fade" id="addSubtaskModal" tabindex="-1" aria-labelledby="addSubtaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="addSubtaskModalLabel">Add New Subtask</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addSubtaskForm" method="post" action="">
                    <input type="hidden" name="add_subtask" value="1">
                    <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                    <div class="mb-3">
                        <label for="taskSelect" class="form-label">Select Task</label>
                        <select class="form-select" id="taskSelect" name="division_id" required>
                            <option value="">-- Select Task --</option>
                            <?php foreach ($divisions as $div): ?>
                                <option value="<?php echo $div['id']; ?>"><?php echo htmlspecialchars($div['division_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="subtaskName" class="form-label">Subtask Name</label>
                        <input type="text" class="form-control" id="subtaskName" name="subtask_name" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="saveSubtask"><i class="fas fa-save"></i> Save Subtask</button>
            </div>
        </div>
    </div>
</div>

<script>
// Handle save subtask
$('#saveSubtask').on('click', function() {
    const taskSelect = document.getElementById('taskSelect');
    const subtaskName = $('#subtaskName').val().trim();
    
    if (taskSelect.value === '') {
        alert('Please select a task');
        return;
    }
    
    if (subtaskName === '') {
        alert('Please enter a subtask name');
        return;
    }
    
        // Set the form action with project_id
    const form = $('#addSubtaskForm');
    form.attr('action', 'project_progress.php?id=<?php echo $project_id; ?>');
    // Submit the form
    form.submit();
});

// Reset form when modal is closed
$('#addSubtaskModal').on('hidden.bs.modal', function () {
    $('#addSubtaskForm')[0].reset();
});
</script>

<script>
// Handle subtask checkbox changes
$(document).on('change', '.subtask-checkbox', function() {
    const $checkbox = $(this);
    const subtaskId = $checkbox.data('subtask-id');
    const isCompleted = $checkbox.is(':checked') ? 1 : 0;
    
    // Show loading state
    $checkbox.prop('disabled', true);
    
    console.log('Sending request to update subtask:', { subtaskId, isCompleted });
    
    $.ajax({
        url: 'update_subtask_status.php',
        type: 'POST',
        data: {
            subtask_id: subtaskId,
            is_completed: isCompleted
        },
        dataType: 'json',
        success: function(response) {
            console.log('Server response:', response);
            
            if (response.success) {
                // Update UI to show success
                $checkbox.prop('checked', isCompleted === 1);
                
                // Add visual feedback
                const $label = $checkbox.next('label');
                $label.css('text-decoration', isCompleted ? 'line-through' : 'none')
                      .css('opacity', isCompleted ? '0.7' : '1');
                
                // Show success message briefly
                const $feedback = $('<span class="text-success ms-2"><i class="fas fa-check"></i> Updated</span>');
                $label.after($feedback);
                setTimeout(() => $feedback.fadeOut(500, () => $feedback.remove()), 2000);
                
                // Update progress bar if we got a new progress value
                if (response.new_progress !== undefined && response.new_progress !== null) {
                    const $row = $checkbox.closest('tr');
                    const $progressBar = $row.find('.progress-bar');
                    const $progressText = $progressBar.text();
                    const progressClass = response.new_progress == 100 ? 'success' : 
                                       (response.new_progress > 50 ? 'primary' : 
                                       (response.new_progress > 0 ? 'warning' : 'secondary'));
                    
                    // Update progress bar
                    $progressBar
                        .removeClass('bg-success bg-primary bg-warning bg-secondary')
                        .addClass('bg-' + progressClass)
                        .css('width', response.new_progress + '%')
                        .attr('aria-valuenow', response.new_progress)
                        .text(response.new_progress + '%');
                        
                    // If the division is now completed, update its status
                    if (response.new_progress === 100) {
                        $row.find('.badge')
                            .removeClass('bg-warning bg-primary')
                            .addClass('bg-success')
                            .text('Completed');
                    } else {
                        $row.find('.badge')
                            .removeClass('bg-success')
                            .addClass('bg-primary')
                            .text('In Progress');
                    }
                }
                
                console.log('Subtask status updated successfully');
            } else {
                // Show error message
                console.error('Server reported an error:', response.message || 'Unknown error');
                showAlert('Error: ' + (response.message || 'Failed to update subtask status'), 'danger');
                
                // Revert checkbox state
                $checkbox.prop('checked', !isCompleted);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            console.error('Response text:', xhr.responseText);
            
            // Revert checkbox on error
            $checkbox.prop('checked', !isCompleted);
            
            // Show error message
            showAlert('Error: Could not connect to server. Please try again.', 'danger');
        },
        complete: function() {
            // Re-enable the checkbox
            $checkbox.prop('disabled', false);
        }
    });
});

// Helper function to show alert messages
function showAlert(message, type = 'info') {
    const $alert = $(`
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `);
    
    // Add to the top of the page
    $('.container-fluid.px-4.py-4').prepend($alert);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        $alert.alert('close');
    }, 5000);
}

// Make sure checkboxes reflect the initial state when the page loads
$(document).ready(function() {
    $('.subtask-checkbox').each(function() {
        const $checkbox = $(this);
        const $label = $checkbox.next('label');
        if ($checkbox.is(':checked')) {
            $label.css('text-decoration', 'line-through').css('opacity', '0.7');
        }
    });
});

// When the Add Subtask button is clicked, set the division ID in the modal
$(document).on('click', '.add-subtask-btn', function(e) {
    e.preventDefault();
    const divisionId = $(this).data('division-id');
    $('#subtaskDivisionId').val(divisionId);
    $('#addSubtaskModal').modal('show');
});

// Handle save subtask
$('#saveSubtask').on('click', function() {
    const divisionId = $('#subtaskDivisionId').val();
    const subtaskName = $('#subtaskName').val().trim();
    
    if (subtaskName) {
        // Submit the form
        $('<input>').attr({
            type: 'hidden',
            name: 'add_subtask',
            value: '1'
        }).appendTo('#addSubtaskForm');
        
        // Show success message after form submission
        showSuccessModal('Subtask added successfully!');
        
        // Submit the form
        $('#addSubtaskForm').submit();
    } else {
        showAlert('Please enter a subtask name', 'danger');
    }
});

// Success Modal
const successModal = `
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Success!</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="fas fa-check-circle text-success" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                <p id="successMessage" class="mb-0 h5">Operation completed successfully!</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>`;

// Add success modal to the page
$('body').append(successModal);

// Function to show success modal
function showSuccessModal(message) {
    if (message) {
        $('#successMessage').text(message);
    }
    const modal = new bootstrap.Modal(document.getElementById('successModal'));
    modal.show();
}

// Handle form submissions
$('form[action*="project_progress.php"]').on('submit', function(e) {
    e.preventDefault();
    const form = $(this);
    const formData = form.serialize();
    
    $.ajax({
        url: form.attr('action') || 'project_progress.php',
        type: 'POST',
        data: formData,
        success: function(response) {
            showSuccessModal('Operation completed successfully!');
            // Reload the page after a short delay to show the modal
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        },
        error: function(xhr, status, error) {
            showAlert('An error occurred. Please try again.', 'danger');
        }
    });
});

// Update the subtask checkbox change handler to show success modal
$(document).on('change', '.subtask-checkbox', function() {
    const $checkbox = $(this);
    const subtaskId = $checkbox.data('subtask-id');
    const isCompleted = $checkbox.is(':checked') ? 1 : 0;
    
    // Show loading state
    $checkbox.prop('disabled', true);
    
    $.ajax({
        url: 'update_subtask_status.php',
        type: 'POST',
        data: {
            subtask_id: subtaskId,
            is_completed: isCompleted
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showSuccessModal('Subtask updated successfully!');
                // Update UI
                $checkbox.prop('checked', isCompleted === 1);
                const $label = $checkbox.next('label');
                $label.css('text-decoration', isCompleted ? 'line-through' : 'none')
                      .css('opacity', isCompleted ? '0.7' : '1');
                
                // Update progress bar if needed
                if (response.new_progress !== undefined) {
                    const $row = $checkbox.closest('tr');
                    const $progressBar = $row.find('.progress-bar');
                    const progressClass = response.new_progress === 100 ? 'bg-success' : 
                                       (response.new_progress > 50 ? 'bg-primary' : 'bg-warning');
                    
                    $progressBar
                        .removeClass('bg-success bg-warning bg-primary')
                        .addClass(progressClass)
                        .css('width', response.new_progress + '%')
                        .text(response.new_progress + '%');
                }
            } else {
                showAlert(response.message || 'Failed to update subtask', 'danger');
                $checkbox.prop('checked', !isCompleted);
            }
        },
        error: function() {
            showAlert('Error updating subtask. Please try again.', 'danger');
            $checkbox.prop('checked', !isCompleted);
        },
        complete: function() {
            $checkbox.prop('disabled', false);
        }
    });
});
</script>

</body>
</html> 