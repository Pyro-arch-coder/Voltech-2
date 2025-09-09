<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 3) {
    header("Location: ../login.php");
    exit();
}

include_once "../config.php";
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

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $con = new mysqli("localhost", "root", "", "voltech2");
    $response = ['success' => false, 'message' => ''];
    if ($con->connect_error) {
        $response['message'] = 'Database connection failed.';
    } else {
        $userid = $_SESSION['user_id'];
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
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
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Fetch project details
$project_query = $con->query("SELECT * FROM projects WHERE project_id='$project_id' AND user_id='$userid'");
if ($project_query->num_rows == 0) {
    header("Location: projects.php");
    exit();
}
$project = $project_query->fetch_assoc();

// Handle add division
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_division'])) {
    $task_name = trim(mysqli_real_escape_string($con, $_POST['division_name']));
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $description = $_POST['description'] ?? '';
    if ($task_name !== '' && $start_date && $end_date) {
        mysqli_query($con, "INSERT INTO project_timeline (project_id, task_name, start_date, end_date, progress, status, description, created_at, updated_at) VALUES ('$project_id', '$task_name', '$start_date', '$end_date', 0, 'Not Started', '$description', NOW(), NOW())");
    }
    header("Location: project_progress.php?id=$project_id&added=1");
    exit();
}

// Handle division progress update and rename
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_division'])) {
        $division_id = intval($_POST['division_id']);
        $progress = max(0, min(100, intval($_POST['progress'])));
        $status = $progress == 100 ? 'Completed' : ($progress > 0 ? 'In Progress' : 'Not Started');
        $updated_at = date('Y-m-d H:i:s');
        $description = $_POST['description'] ?? '';
        mysqli_query($con, "UPDATE project_timeline SET progress='$progress', status='$status', updated_at='$updated_at', description='$description' WHERE id='$division_id' AND project_id='$project_id'");
        header("Location: project_progress.php?id=$project_id&updated=1");
        exit();
    }
    if (isset($_POST['rename_division'])) {
        $division_id = intval($_POST['division_id']);
        $new_name = trim(mysqli_real_escape_string($con, $_POST['new_division_name']));
        if ($new_name !== '') {
            mysqli_query($con, "UPDATE project_timeline SET task_name='$new_name' WHERE id='$division_id' AND project_id='$project_id'");
        }
        header("Location: project_progress.php?id=$project_id&renamed=1");
        exit();
    }
    if (isset($_POST['delete_division'])) {
        $division_id = intval($_POST['division_id']);
        mysqli_query($con, "DELETE FROM project_timeline WHERE id='$division_id' AND project_id='$project_id'");
        header("Location: project_progress.php?id=$project_id&deleted=1");
        exit();
    }
    if (isset($_POST['add_subtask'])) {
        $division_id = intval($_POST['division_id']);
        $subtask_name = trim(mysqli_real_escape_string($con, $_POST['subtask_name']));
        if ($division_id > 0 && $subtask_name !== '') {
            $stmt = $con->prepare("INSERT INTO project_subtask (project_timeline_id, name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            $stmt->bind_param('is', $division_id, $subtask_name);
            $stmt->execute();
            $stmt->close();
            header("Location: project_progress.php?id=$project_id&subtask_added=1");
            exit();
        }
        header("Location: project_progress.php?id=$project_id&error=invalid_input");
        exit();
    }
}

// Fetch divisions/tasks
$divisions = [];
$divisions_result = mysqli_query($con, "SELECT * FROM project_timeline WHERE project_id = '$project_id' ORDER BY id ASC");
while ($row = mysqli_fetch_assoc($divisions_result)) {
    $row['subtasks'] = [];
    $subtask_result = mysqli_query($con, "SELECT * FROM project_subtask WHERE project_timeline_id = '" . $row['id'] . "' ORDER BY created_at ASC");
    while ($subtask = mysqli_fetch_assoc($subtask_result)) {
        $row['subtasks'][] = $subtask;
    }
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
            <a href="gantt.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'gantt.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar"></i>My Schedule
            </a>
            <a href="paymethod.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'paymethod.php' ? 'active' : ''; ?>">
                    <i class="fas fa-money-bill"></i>Payment Methods
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
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                    <?php 
                    include 'pm_notification.php'; 
                    
                    // Function to count unread messages
                    function countUnreadMessages($con, $tables, $userId) {
                        $total = 0;
                        foreach ($tables as $table) {
                            $query = "SELECT COUNT(*) as count FROM $table WHERE receiver_id = ? AND is_read = 0";
                            if ($stmt = $con->prepare($query)) {
                                $stmt->bind_param("i", $userId);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $row = $result->fetch_assoc();
                                $total += $row['count'];
                            }
                        }
                        return $total;
                    }
                    
                    // Get total unread messages
                    $tables = ['pm_client_messages', 'pm_procurement_messages', 'admin_pm_messages'];
                    $unreadCount = countUnreadMessages($con, $tables, $_SESSION['user_id']);
                    ?>
                    <li class="nav-item ms-2">
                        <a class="nav-link position-relative" href="pm_messenger.php" title="Messages">
                            <i class="fas fa-comment-dots fs-5"></i>
                            <?php if ($unreadCount > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.6em;">
                                    <?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
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
                    <div class="d-flex gap-2">
                        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addDivisionModal">
                            <i class="fas fa-plus me-1"></i> Add Tasks
                        </button>
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
                                    <td><?php echo htmlspecialchars($div['task_name'] ?? $div['division_name']); ?></td>
                                    <td><?php echo !empty($div['start_date']) ? date('F d, Y', strtotime($div['start_date'])) : '-'; ?></td>
                                    <td><?php echo !empty($div['end_date']) ? date('F d, Y', strtotime($div['end_date'])) : '-'; ?></td>
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
                                          if (is_array($div['subtasks']) && count($div['subtasks']) > 0) {
                                            echo '<div class="list-unstyled mb-0">';
                                            $taskNumber = 1;
                                            foreach ($div['subtasks'] as $subtask) {
                                                $checked = !empty($subtask['is_completed']) ? 'checked' : '';
                                                echo '<div class="form-check mb-1">
                                                    <input class="form-check-input subtask-checkbox" type="checkbox" data-subtask-id="' . $subtask['id'] . '" ' . $checked . '>
                                                    <label class="form-check-label">' . $taskNumber . '. ' . htmlspecialchars($subtask['name']) . '</label>
                                                </div>';
                                                $taskNumber++;
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
                                  <div class="modal-dialog modal-dialog-centered">
                                    <form method="post">
                                      <input type="hidden" name="division_id" value="<?php echo $div['id']; ?>">
                                      <div class="modal-content">
                                        <div class="modal-header">
                                          <h5 class="modal-title">Edit Division: <?php echo htmlspecialchars($div['task_name'] ?? $div['division_name']); ?></h5>
                                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                          <div class="mb-3">
                                            <label>Division Name</label>
                                            <input type="text" name="new_division_name" class="form-control" value="<?php echo htmlspecialchars($div['task_name'] ?? $div['division_name']); ?>" required>
                                          </div>
                                          <div class="mb-3">
                                            <label>Progress (%)</label>
                                            <input type="number" name="progress" class="form-control" min="0" max="100" value="<?php echo intval($div['progress']); ?>" required>
                                          </div>
                                          <div class="mb-3">
                                            <label>Description</label>
                                            <textarea name="description" class="form-control"><?php echo htmlspecialchars($div['description'] ?? ''); ?></textarea>
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
<div class="modal fade" id="addDivisionModal" tabindex="-1" aria-labelledby="addDivisionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="addDivisionModalLabel">Add Tasks</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <input type="hidden" name="add_division" value="1">
                    <div class="mb-3">
                        <label class="form-label">Task Name</label>
                        <input type="text" name="division_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control"></textarea>
                    </div>
                    <div class="modal-footer px-0 pb-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <?php 
// Set success messages
$success_message = '';
if (isset($_GET['added'])) {
    $success_message = 'Task added successfully!';
} elseif (isset($_GET['updated'])) {
    $success_message = 'Task updated successfully!';
} elseif (isset($_GET['renamed'])) {
    $success_message = 'Task renamed successfully!';
} elseif (isset($_GET['deleted'])) {
    $success_message = 'Task deleted successfully!';
} elseif (isset($_GET['subtask_added'])) {
    $success_message = 'Subtask added successfully!';
}

// Set error message
$error_message = '';
if (isset($_GET['error'])) {
    $error_message = 'Error adding subtask. Please try again.';
    if ($_GET['error'] === 'subtask_failed') {
        $error_message = 'Failed to save subtask. Please try again.';
    } elseif ($_GET['error'] === 'invalid_input') {
        $error_message = 'Please enter a valid subtask name and select a task.';
    }
}
?>
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
    <div class="modal-dialog modal-dialog-centered">
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
                                <option value="<?php echo $div['id']; ?>"><?php echo htmlspecialchars($div['task_name'] ?? $div['division_name']); ?></option>
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

<!-- Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center">
            <div class="modal-body py-4">
                <span id="feedbackIcon" style="font-size: 3rem;"></span>
                <h5 class="mt-3" id="feedbackMessage"></h5>
            </div>
            <div class="modal-footer justify-content-center border-0">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<script>
// Show feedback modal if there's a success or error message
$(document).ready(function() {
    <?php if (!empty($success_message)): ?>
        showFeedback('<?php echo addslashes($success_message); ?>', 'success');
    <?php elseif (!empty($error_message)): ?>
        showFeedback('<?php echo addslashes($error_message); ?>', 'error');
    <?php endif; ?>
});

function showFeedback(message, type) {
    const feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
    const feedbackMessage = document.getElementById('feedbackMessage');
    const feedbackIcon = document.getElementById('feedbackIcon');
    
    feedbackMessage.textContent = message;
    
    if (type === 'success') {
        feedbackIcon.className = 'fas fa-check-circle text-success';
        feedbackMessage.className = 'mt-3 text-success';
    } else {
        feedbackIcon.className = 'fas fa-exclamation-circle text-danger';
        feedbackMessage.className = 'mt-3 text-danger';
    }
    
    // Remove the success/error parameters from URL without reloading the page
    const url = new URL(window.location);
    const params = new URLSearchParams(url.search);
    
    // List of parameters to remove
    const paramsToRemove = ['added', 'updated', 'renamed', 'deleted', 'subtask_added', 'error'];
    let hasChanges = false;
    
    paramsToRemove.forEach(param => {
        if (params.has(param)) {
            params.delete(param);
            hasChanges = true;
        }
    });
    
    if (hasChanges) {
        const newUrl = params.toString() ? `${url.pathname}?${params.toString()}` : url.pathname;
        window.history.replaceState({}, '', newUrl);
    }
    
    feedbackModal.show();
}
</script>

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
$(document).on('change', '.subtask-checkbox', function(e) {
    e.preventDefault();
    
    const $checkbox = $(this);
    const subtaskId = $checkbox.data('subtask-id');
    const isCompleted = $checkbox.is(':checked') ? 1 : 0;
    const $row = $checkbox.closest('tr');
    const $progressBar = $row.find('.progress-bar');
    
    // Show loading state
    $checkbox.prop('disabled', true);
    $progressBar.text('Updating...');
    
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
            
            // Always re-enable the checkbox
            $checkbox.prop('disabled', false);
            
            if (response.success) {
                // Update the checkbox state based on server response
                const newIsCompleted = response.is_completed === 1 || isCompleted === 1;
                $checkbox.prop('checked', newIsCompleted);
                
                // Add visual feedback
                const $label = $checkbox.next('label');
                $label.css('text-decoration', newIsCompleted ? 'line-through' : 'none')
                      .css('opacity', newIsCompleted ? '0.7' : '1');
                
                // Show success message briefly
                const $feedback = $('<span class="text-success ms-2"><i class="fas fa-check"></i> Updated</span>');
                $label.after($feedback);
                setTimeout(() => $feedback.fadeOut(500, () => $feedback.remove()), 2000);
                
                // Update progress bar if we got a new progress value
                if (response.new_progress !== undefined && response.new_progress !== null) {
                    const progressValue = parseInt(response.new_progress) || 0; // Ensure we have a number
                    const progressClass = progressValue == 100 ? 'success' : 
                                       (progressValue > 50 ? 'primary' : 
                                       (progressValue > 0 ? 'warning' : 'secondary'));
                    
                    console.log('Updating progress bar:', { progress: progressValue, class: progressClass });
                    
                    // Update progress bar
                    $progressBar
                        .removeClass('bg-success bg-primary bg-warning bg-secondary')
                        .addClass('bg-' + progressClass)
                        .css('width', progressValue + '%')
                        .attr('aria-valuenow', progressValue)
                        .text(progressValue + '%');
                        
                    // Update status badge
                    const $badge = $row.find('.badge');
                    if (progressValue === 100) {
                        $badge
                            .removeClass('bg-warning bg-primary')
                            .addClass('bg-success')
                            .text('Completed');
                    } else if (progressValue > 0) {
                        $badge
                            .removeClass('bg-success bg-secondary')
                            .addClass('bg-primary')
                            .text('In Progress');
                    } else {
                        $badge
                            .removeClass('bg-success bg-primary')
                            .addClass('bg-secondary')
                            .text('Not Started');
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