<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 6) {
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

// Clients cannot delete or restore projects - only view them

// --- FILTER LOGIC ---
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$where = "client_email='$user_email' AND client_archived=1 AND (client_delete IS NULL OR client_delete=0)";
if ($filter === 'month') {
    $where .= " AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
} elseif ($filter === 'year') {
    $where .= " AND YEAR(created_at) = YEAR(CURDATE())";
} elseif ($filter === 'custom' && $start_date && $end_date) {
    $where .= " AND DATE(created_at) BETWEEN '" . mysqli_real_escape_string($con, $start_date) . "' AND '" . mysqli_real_escape_string($con, $end_date) . "'";
}
// Fetch archived projects with filter for this client
$query = mysqli_query($con, "SELECT * FROM projects WHERE $where ORDER BY created_at DESC");

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
    <link rel="stylesheet" href="client_styles.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/feather-icons@4.28.0/dist/feather.min.css" />
    <title>My Archived Projects - Client Portal</title>
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
                <img class="img img-fluid rounded-circle mb-2 sidebar-profile-img" src="<?php echo isset($userprofile) ? $userprofile : (isset($_SESSION['userprofile']) ? $_SESSION['userprofile'] : '../uploads/default_profile.png'); ?>" width="70" alt="User Profile">
                <h5 class="mb-1 text-white"><?php echo htmlspecialchars($user_name); ?></h5>
                <p class="text-white small mb-0"><?php echo htmlspecialchars($user_email); ?></p>
                <hr style="border-top: 1px solid #fff; opacity: 0.3; margin: 12px 0 0 0;">
            </div>
            <div class="list-group list-group-flush ">
                <a href="clients_dashboard.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'clients_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>Dashboard
                </a>
                <a href="client_projects.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'client_projects.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i>My Projects
                </a>
                <a href="client_gantt.chart.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'client_gantt.chart.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar"></i>Gantt Chart
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
        <div class="container-fluid px-2 px-md-4 py-3">
                <div class="row">
                    <div class="col-12">
                        <div class="card mb-5 shadow rounded-3">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4 class="mb-0">List of Archived Projects</h4>
                                    <a href="client_projects.php" class="btn btn-secondary ms-2">Back to Project List</a>
                                </div>
                                <hr>
                                <form class="d-flex align-items-center gap-2 mb-3 flex-wrap" method="get" id="filterForm" style="gap: 8px;">
                                    <select name="filter" id="filter" class="form-control" style="width: 140px; min-width: 100px; max-width: 180px;">
                                        <option value="all" <?php echo ($filter === 'all') ? 'selected' : ''; ?>>All</option>
                                        <option value="month" <?php echo ($filter === 'month') ? 'selected' : ''; ?>>This Month</option>
                                        <option value="year" <?php echo ($filter === 'year') ? 'selected' : ''; ?>>This Year</option>
                                    </select>
                                    <div class="d-flex align-items-center" style="min-width: 180px;">
                                        <label for="start_date" class="mb-0 me-1" style="font-weight:normal; font-size:0.95em;">Start:</label>
                                        <input type="date" name="start_date" id="start_date" class="form-control" style="width: 130px;" value="<?php echo htmlspecialchars($start_date); ?>">
                                    </div>
                                    <div class="d-flex align-items-center" style="min-width: 180px;">
                                        <label for="end_date" class="mb-0 me-1" style="font-weight:normal; font-size:0.95em;">End:</label>
                                        <input type="date" name="end_date" id="end_date" class="form-control" style="width: 130px;" value="<?php echo htmlspecialchars($end_date); ?>">
                                    </div>
                                    <!-- No Apply Filter button -->
                                </form>
                                <div class="table-responsive mb-0">
                                <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Project</th>
                                        <th>Location</th>
                                        <th>Deadline</th>
                                        <th>Foreman</th>
                                        <th>Category</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                $no = 1;
                                $modals = '';
                                if (mysqli_num_rows($query) > 0) {
                                    mysqli_data_seek($query, 0);
                                    while ($row = mysqli_fetch_assoc($query)) {
                                        $pid = $row['project_id'];
                                        // Get foreman's name from employees table
                                        $foreman_name = '';
                                        $foreman_query = mysqli_query($con, "SELECT e.first_name, e.last_name 
                                            FROM project_add_employee pae
                                            JOIN employees e ON pae.employee_id = e.employee_id
                                            JOIN positions p ON e.position_id = p.position_id
                                            WHERE pae.project_id = '$pid' 
                                            AND p.title = 'Foreman'
                                            LIMIT 1");
                                        if ($foreman_row = mysqli_fetch_assoc($foreman_query)) {
                                            $foreman_name = htmlspecialchars($foreman_row['first_name'] . ' ' . $foreman_row['last_name']);
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo htmlspecialchars($row['project'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($row['location'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($row['deadline'] ?? ''); ?></td>
                                            <td><?php echo $foreman_name; ?></td>
                                            <td><?php echo htmlspecialchars($row['category'] ?? ''); ?></td>
             
                                            <td>
                                                <button class="btn btn-success restore-project" data-project-id="<?php echo $pid; ?>"><i data-feather="refresh-cw" class="mr-1"></i>Restore</button>
                                                <button class="btn btn-danger delete-project" data-project-id="<?php echo $pid; ?>"><i data-feather="trash-2" class="mr-1"></i>Delete</button>
                                            </td>
                                        </tr>
                                <?php
                                    } // Close the while loop
                                } else {
                                    echo '<tr><td colspan="8" class="text-center">No archived projects found.</td></tr>';
                                }
                                ?>
                                </tbody>
                                </table>
                        </div>
                    </div>
                </div>
             </div>

        <!-- Confirmation Modal for Restore/Delete -->
        <div class="modal fade" id="confirmActionModal" tabindex="-1" aria-labelledby="confirmActionModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="confirmActionModalLabel">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <p id="confirmActionText"></p>
                <p class="text-danger" id="confirmActionWarning" style="display:none;">This action cannot be undone.</p>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmActionBtn" class="btn">Confirm</a>
              </div>
            </div>
          </div>
        </div>
        <!-- Change Password Modal -->
        <div class="modal fade" id="changePasswordModalMain" tabindex="-1" aria-labelledby="changePasswordModalLabelMain" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="changePasswordModalLabelMain">Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <form id="changePasswordFormMain">
                  <div class="mb-3">
                    <label for="current_password_main" class="form-label">Current Password</label>
                    <input type="password" class="form-control" id="current_password_main" name="current_password" required>
                  </div>
                  <div class="mb-3">
                    <label for="new_password_main" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="new_password_main" name="new_password" required>
                  </div>
                  <div class="mb-3">
                    <label for="confirm_password_main" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password_main" name="confirm_password" required>
                  </div>
                  <div id="changePasswordFeedbackMain" class="mb-2"></div>
                  <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-success">Change Password</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons@4.28.0/dist/feather.min.js"></script>
    <script>
        // Initialize Feather Icons
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
            
            // Toggle custom date range fields based on filter selection
            function toggleCustomDateRange() {
                const filter = document.getElementById('filter').value;
                const dateRangeGroup = document.getElementById('dateRangeGroup');
                if (filter === 'custom') {
                    dateRangeGroup.style.display = 'flex';
                } else {
                    dateRangeGroup.style.display = 'none';
                }
            }
            
            // Initialize date range visibility
            toggleCustomDateRange();
            
            // Toggle sidebar
            var el = document.getElementById("wrapper");
            var toggleButton = document.getElementById("menu-toggle");
            
            if (toggleButton) {
                toggleButton.onclick = function () {
                    el.classList.toggle("toggled");
                };
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <script>
    var el = document.getElementById("wrapper");
    var toggleButton = document.getElementById("menu-toggle");

    toggleButton.onclick = function () {
        el.classList.toggle("toggled");
    };
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var filterSelect = document.getElementById('filter');
        var startDate = document.getElementById('start_date');
        var endDate = document.getElementById('end_date');
        var filterForm = document.getElementById('filterForm');
        if (filterSelect) {
            filterSelect.addEventListener('change', function() {
                filterForm.submit();
            });
        }
        if (startDate) {
            startDate.addEventListener('change', function() {
                filterForm.submit();
            });
        }
        if (endDate) {
            endDate.addEventListener('change', function() {
                filterForm.submit();
            });
        }

        // Confirmation modal for restore/delete
        let actionType = '';
        let actionUrl = '';
        document.querySelectorAll('.restore-project').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                actionType = 'restore';
                const pid = this.getAttribute('data-project-id');
                actionUrl = 'client_restore_project.php?restore=' + pid;
                document.getElementById('confirmActionModalLabel').textContent = 'Confirm Restore';
                document.getElementById('confirmActionText').textContent = 'Are you sure you want to restore this project?';
                document.getElementById('confirmActionWarning').style.display = 'none';
                const confirmBtn = document.getElementById('confirmActionBtn');
                confirmBtn.className = 'btn btn-success';
                confirmBtn.textContent = 'Restore';
                // Close the current project modal before showing confirm
                currentProjectModal = this.closest('.modal');
                if (currentProjectModal) {
                    var bsModal = bootstrap.Modal.getInstance(currentProjectModal);
                    if (bsModal) bsModal.hide();
                }
                setTimeout(function() {
                    var modal = new bootstrap.Modal(document.getElementById('confirmActionModal'));
                    modal.show();
                    confirmBtn.onclick = function() { window.location.href = actionUrl; };
                }, 300);
            });
        });
        document.querySelectorAll('.delete-project').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                actionType = 'delete';
                const pid = this.getAttribute('data-project-id');
                actionUrl = 'client_delete_project.php?delete=' + pid;
                document.getElementById('confirmActionModalLabel').textContent = 'Confirm Delete';
                document.getElementById('confirmActionText').textContent = 'Are you sure you want to permanently delete this project?';
                document.getElementById('confirmActionWarning').style.display = '';
                const confirmBtn = document.getElementById('confirmActionBtn');
                confirmBtn.className = 'btn btn-danger';
                confirmBtn.textContent = 'Delete';
                // Close the current project modal before showing confirm
                currentProjectModal = this.closest('.modal');
                if (currentProjectModal) {
                    var bsModal = bootstrap.Modal.getInstance(currentProjectModal);
                    if (bsModal) bsModal.hide();
                }
                setTimeout(function() {
                    var modal = new bootstrap.Modal(document.getElementById('confirmActionModal'));
                    modal.show();
                    confirmBtn.onclick = function() { window.location.href = actionUrl; };
                }, 300);
            });
        });
        // Fix close buttons in all modals for Bootstrap 5
        document.querySelectorAll('.modal .btn-close, .modal .close').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var modal = btn.closest('.modal');
                if (modal) {
                    var bsModal = bootstrap.Modal.getInstance(modal);
                    if (bsModal) bsModal.hide();
                }
            });
        });
        // Refresh feather icons after modals are shown
        document.querySelectorAll('.modal').forEach(function(modalEl) {
            modalEl.addEventListener('shown.bs.modal', function() {
                if (window.feather) feather.replace();
            });
        });
    });
    </script>
    <!-- Feedback Modal (copied from projects.php) -->
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
    function removeQueryParam(param) {
        const url = new URL(window.location);
        url.searchParams.delete(param);
        window.history.replaceState({}, document.title, url.pathname + url.search);
    }
    function showFeedbackModal(success, message, reason = '', paramToRemove = null) {
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
            msg.textContent = message + (reason ? ' Reason: ' + reason : '');
        }
        var feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
        feedbackModal.show();
        if (paramToRemove) {
            removeQueryParam(paramToRemove);
        }
    }
    <?php if (isset($_GET['restored'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showFeedbackModal(true, 'Project restored successfully.', '', 'restored');
    });
    <?php elseif (isset($_GET['deleted'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showFeedbackModal(true, 'Project deleted successfully.', '', 'deleted');
    });
    <?php endif; ?>
    </script>
<style>
.custom-pagination-green .page-item.active .page-link,
.custom-pagination-green .page-item .page-link:hover {
    background-color: #009d63;
    border-color: #009d63;
    color: #fff;
}
.custom-pagination-green .page-link {
    color: #009d63;
}
</style>
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
</body>
</html>