<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 6) {
    header("Location: ../login.php");
    exit();
}
require_once '../config.php';
// Pagination settings
$results_per_page = 10; // Number of results per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Current page, default is 1
$offset = ($page - 1) * $results_per_page; // Calculate offset

// Get total number of projects for the current client
$total_projects = 0;
$total_pages = 1;

// Get user email for query
$user_email = isset($_SESSION['email']) ? $_SESSION['email'] : '';

// Build the base query
$query = "SELECT COUNT(*) as total FROM projects WHERE client_email = '$user_email' AND client_archived = 0 AND status != 'Archived'";

// Add search filter if provided
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $con->real_escape_string($_GET['search']);
    $query .= " AND (project LIKE '%$search%' OR location LIKE '%$search%')";
}

// Add status filter if provided
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status = $con->real_escape_string($_GET['status']);
    $query .= " AND status = '$status'";
}

// Execute the query to get total projects
$result = $con->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $total_projects = $row['total'];
    $total_pages = ceil($total_projects / $results_per_page);
    
    // Ensure current page is within valid range
    if ($page > $total_pages && $total_pages > 0) {
        $page = $total_pages;
        $offset = ($page - 1) * $results_per_page;
    }
}

// Get user data
$user_firstname = isset($_SESSION['firstname']) ? $_SESSION['firstname'] : '';
$user_lastname = isset($_SESSION['lastname']) ? $_SESSION['lastname'] : '';
$user_name = trim($user_firstname . ' ' . $user_lastname);

// Build the projects query with pagination
$projects_query = "SELECT * FROM projects WHERE client_email = '$user_email' AND client_archived = 0 AND status != 'Archived'";

// Add search filter if provided
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $con->real_escape_string($_GET['search']);
    $projects_query .= " AND (project LIKE '%$search%' OR location LIKE '%$search%')";
}

// Add status filter if provided
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status = $con->real_escape_string($_GET['status']);
    $projects_query .= " AND status = '$status'";
}

// Add sorting and pagination
$projects_query .= " ORDER BY project_id DESC LIMIT $offset, $results_per_page";

// Execute the query to get projects
$projects_result = $con->query($projects_query);
$projects = [];
if ($projects_result && $projects_result->num_rows > 0) {
    while ($row = $projects_result->fetch_assoc()) {
        $projects[] = $row;
    }
}
$user_email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
$user_firstname = isset($_SESSION['firstname']) ? $_SESSION['firstname'] : '';
$user_lastname = isset($_SESSION['lastname']) ? $_SESSION['lastname'] : '';
$user_name = trim($user_firstname . ' ' . $user_lastname);
$current_page = basename($_SERVER['PHP_SELF']);


$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle AJAX password change
    if (isset($_POST['change_password'])) {
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
            // Check current password
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
}

$user = null;
$userprofile = '../uploads/default_profile.png';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

if ($user_id) {
    $result = $con->query("SELECT * FROM users WHERE id = '$user_id'");
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_firstname = $user['firstname'];
        $user_lastname = $user['lastname'];
        $user_email = $user['email']; // This will be used for the projects query
        $userprofile = isset($user['profile_path']) && $user['profile_path'] ? '../uploads/' . $user['profile_path'] : '../uploads/default_profile.png';
    }
}


if (isset($_GET['archive'])) {
    $archive_id = intval($_GET['archive']);
    $client_email = mysqli_real_escape_string($con, $user_email);
    if (mysqli_query($con, "UPDATE projects SET client_archived=1 WHERE project_id='$archive_id' AND client_email='$client_email'")) {
        header("Location: client_projects.php?success=archive");
    } else {
        $error = urlencode(mysqli_error($con));
        header("Location: client_projects.php?error=$error");
    }
    exit();
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
    <link rel="stylesheet" href="client_styles.css" />
    <title>My Projects</title>
    <style>
        /* General Styles */
        body {
            background-color: #f8f9fa;
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: 10px;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-bottom: 1.5rem;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
        }

        /* Status Badges */
        .badge {
            padding: 0.5em 0.8em;
            font-weight: 500;
        }

        /* Progress Bar */
        .progress {
            border-radius: 10px;
            background-color: #e9ecef;
        }

        /* Sidebar Styles */
        #sidebar-wrapper {
            background: linear-gradient(180deg, #4e73df 0%, #224abe 100%);
            min-height: 100vh;
            transition: all 0.3s;
        }

        .sidebar-profile-img {
            border: 3px solid rgba(255, 255, 255, 0.2);
        }

        .list-group-item {
            border: none;
            padding: 0.8rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
            transition: all 0.3s;
        }

        .list-group-item:hover,
        .list-group-item.active {
            background-color: rgba(255, 255, 255, 0.1) !important;
            color: #fff !important;
            border-left: 4px solid #fff;
        }

        /* Navbar Styles */
        .navbar {
            background-color: transparent !important;
            box-shadow: none !important;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            #sidebar-wrapper {
                margin-left: -15rem;
            }
            #wrapper.toggled #sidebar-wrapper {
                margin-left: 0;
            }
            #page-content-wrapper {
                min-width: 100%;
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div class="bg-white" id="sidebar-wrapper">
            <div class="user text-center py-4">
                <img class="img img-fluid rounded-circle mb-2 sidebar-profile-img" src="<?php echo $userprofile; ?>" width="70" alt="User Profile">
                <h5 class="mb-1 text-white"><?php echo htmlspecialchars($user_name); ?></h5>
                <p class="text-white small mb-0"><?php echo htmlspecialchars($user_email); ?></p>
                <hr style="border-top: 1px solid #fff; opacity: 0.3; margin: 12px 0 0 0;">
            </div>
            <div class="list-group list-group-flush">
                <a href="clients_dashboard.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'clients_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="client_projects.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'clients_projects.php' ? 'active' : ''; ?>">
                    <i class="fas fa-project-diagram"></i> Projects
                </a>
                <a href="client_gantt.chart.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'clients_gantt.chart.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar"></i> Gantt Chart
                </a>
            </div>
        </div>
        <!-- /#sidebar-wrapper -->

        <!-- Page Content -->
        <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-align-left primary-text fs-4 me-3" id="menu-toggle"></i>
                    <h2 class="fs-2 m-0">Dashboard</h2>
                </div>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                    <?php 
                    include 'clients_notification.php'; 
                    
                    // Function to count unread messages
                    function countUnreadMessages($con, $userId) {
                        $query = "SELECT COUNT(*) as count FROM pm_client_messages WHERE receiver_id = ? AND is_read = 0";
                        $stmt = $con->prepare($query);
                        $stmt->bind_param("i", $userId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $row = $result->fetch_assoc();
                        $stmt->close();
                        return $row['count'];
                    }
                    
                    // Get total unread messages
                    $unreadCount = countUnreadMessages($con, $_SESSION['user_id']);
                    ?>
                    <li class="nav-item ms-2">
                        <a class="nav-link position-relative" href="client_messenger.php" title="Messages">
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
                                <li><a class="dropdown-item" href="client_profile.php">Profile</a></li>
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal">Change Password</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>
         <div class="container-fluid px-4 py-4">
            <div class="card mb-5 shadow rounded-3">
                    <div class="card-body">
                        <div class="mb-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
                            <h4 class="mb-0">Projects</h4>
                            <div class="d-flex gap-2">
                                <a href="client_archieved.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-archive me-1"></i>Archived
                                </a>
                            </div>
                        </div>
                        <hr>
                        <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
                            <form class="d-flex flex-grow-1" method="get" action="" id="searchForm" style="min-width:260px; max-width:400px;">
                                <div class="input-group w-100">
                                    <span class="input-group-text bg-white border-end-0">
                                        <i class="fas fa-search text-muted"></i>
                                    </span>
                                    <input type="text" class="form-control border-start-0" name="search" placeholder="Search projects..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" autocomplete="off">
                                </div>
                            </form>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="statusFilter" data-bs-toggle="dropdown" aria-expanded="false">
                                    <?php 
                                    if (isset($_GET['status']) && !empty($_GET['status'])) {
                                        echo 'Status: ' . htmlspecialchars($_GET['status']);
                                    } else {
                                        echo 'All Status';
                                    }
                                    ?>
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="statusFilter">
                                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['status' => ''])); ?>">All Status</a></li>
                                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'Pending'])); ?>">Pending</a></li>
                                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'Ongoing'])); ?>">Ongoing</a></li>
                                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'Finished'])); ?>">Finished</a></li>
                                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'Cancelled'])); ?>">Cancelled</a></li>
                                </ul>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="sizeFilter" data-bs-toggle="dropdown" aria-expanded="false">
                                    <?php 
                                    if (isset($_GET['size_filter']) && !empty($_GET['size_filter'])) {
                                        echo 'Size: ' . htmlspecialchars($_GET['size_filter']);
                                    } else {
                                        echo 'All Sizes';
                                    }
                                    ?>
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="sizeFilter">
                                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['size_filter' => ''])); ?>">All Sizes</a></li>
                                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['size_filter' => '0-50'])); ?>">0 - 50 sqm</a></li>
                                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['size_filter' => '51-100'])); ?>">51 - 100 sqm</a></li>
                                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['size_filter' => '101-200'])); ?>">101 - 200 sqm</a></li>
                                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['size_filter' => '201'])); ?>">201+ sqm</a></li>
                                </ul>
                            </div>
                            <div class="d-flex gap-2">
                            </div>
                        </div>
                        
                        <?php 
                        // Calculate starting number based on current page and items per page
                        $no = (($page - 1) * $results_per_page) + 1;
                        ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped text-center">
                                <thead class="bg-success text-white">
                                    <tr>
                                        <th class="text-center">No.</th>
                                        <th>Project Name</th>
                                        <th>Location</th>
                                        <th>Size (sqm)</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($projects)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4">
                                                <div class="text-muted">No projects found. Add your first project to get started.</div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($projects as $project): ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo htmlspecialchars($project['project']); ?></td>
                                                <td><?php echo htmlspecialchars($project['location']); ?></td>
                                                <td class="text-end"><?php echo number_format($project['size'], 2); ?></td>
                                                <td class="text-nowrap">
                                                  <button class="btn btn-outline-primary btn-sm view-details" data-project-id="<?php echo $project['project_id']; ?>">
                                                        <i class="fas fa-eye"></i> Details
                                                    </button>
                                                    <button class="btn btn-sm btn-danger text-white font-weight-bold archive-project" data-project-id="<?php echo $project['project_id']; ?>">
                                                        <i class="fas fa-trash"></i> Archive
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Debug Info -->
                        <div class="alert alert-info d-none">
                            Total Projects: <?php echo $total_projects; ?><br>
                            Results Per Page: <?php echo $results_per_page; ?><br>
                            Total Pages: <?php echo $total_pages; ?><br>
                            Current Page: <?php echo $page; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php 
                        error_log("Pagination Debug - Total Projects: " . $total_projects);
                        error_log("Pagination Debug - Total Pages: " . $total_pages);
                        // Always show pagination for testing
                        // if ($total_pages > 1): 
                        ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center custom-pagination-green">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status=' . urlencode($status) : ''; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                        <span class="sr-only">Previous</span>
                                    </a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status=' . urlencode($status) : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status=' . urlencode($status) : ''; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                        <span class="sr-only">Next</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        <?php // endif; ?>
                    </div>
                </div>
        </div>
    </div>
    <!-- /#page-content-wrapper -->
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

    <div class="modal fade" id="archiveModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Archive Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to archive this project?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmArchive">Archive</button>
            </div> <!-- <-- THIS WAS MISSING -->
        </div> <!-- <-- THIS WAS MISSING -->
    </div>
</div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/client_project_overdue.js"></script>
    <script>
        var el = document.getElementById("wrapper");
        var toggleButton = document.getElementById("menu-toggle");

        toggleButton.onclick = function () {
            el.classList.toggle("toggled");
        };
    </script>
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
            let projectToArchive = null;
            const archiveModalEl = document.getElementById('archiveModal');
            const archiveModal = new bootstrap.Modal(archiveModalEl);

            document.querySelectorAll('.archive-project').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                e.preventDefault();
                projectToArchive = this.getAttribute('data-project-id');
                archiveModal.show();
                });
            });

            document.getElementById('confirmArchive').addEventListener('click', function() {
                if (projectToArchive) {
                window.location.href = 'client_projects.php?archive=' + projectToArchive;
                }
                archiveModal.hide();
            });

            archiveModalEl.addEventListener('hidden.bs.modal', function() {
                projectToArchive = null;
            });
        });
    </script>
</body>

</html>