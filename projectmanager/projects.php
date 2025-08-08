<?php
    session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 3) {
    header("Location: ../login.php");
    exit();
}
require_once '../config.php';

    $userid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $user_email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
    $user_firstname = isset($_SESSION['firstname']) ? $_SESSION['firstname'] : '';
    $user_lastname = isset($_SESSION['lastname']) ? $_SESSION['lastname'] : '';
    $user_name = trim($user_firstname . ' ' . $user_lastname);
    $current_page = basename($_SERVER['PHP_SELF']);

    // --- Change Password Backend Handler (AJAX) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
        header('Content-Type: application/json');
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
        echo json_encode($response);
        exit();
    }

    // User profile image fetch block (restored)
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

    // Pagination variables (restored)
    $results_per_page = 10;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $start_from = ($page - 1) * $results_per_page;

    $form_data = [];
    if (isset($_SESSION['form_data'])) {
        $form_data = $_SESSION['form_data'];
        unset($_SESSION['form_data']);
    }
    
    // Fetch total number of projects for pagination
    $user_id = $_SESSION['user_id'];
    $total_projects = 0;
    $count_result = mysqli_query($con, "SELECT COUNT(*) as total FROM projects WHERE user_id = $user_id AND (archived IS NULL OR archived = 0)");
    if ($count_result) {
        $count_row = mysqli_fetch_assoc($count_result);
        $total_projects = $count_row['total'];
    }
    
    // Calculate total pages
    $total_pages = ceil($total_projects / $results_per_page);
    
    // Fetch projects for the current page
    $projects = [];
    $result = mysqli_query($con, "SELECT * FROM projects WHERE user_id = $user_id AND (archived IS NULL OR archived = 0) ORDER BY created_at DESC LIMIT $start_from, $results_per_page");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $projects[] = $row;
        }
    }

    if (isset($_GET['archive'])) {
        $archive_id = intval($_GET['archive']);
        if (mysqli_query($con, "UPDATE projects SET archived=1 WHERE project_id='$archive_id' AND user_id='$userid'")) {
            header("Location: projects.php?success=archive");
        } else {
            $error = urlencode(mysqli_error($con));
            header("Location: projects.php?error=$error");
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
    <link rel="stylesheet" href="style.css" />
    <title>Projects Management</title>
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
                    <h2 class="fs-2 m-0">Projects Management</h2>
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
                <div class="card mb-5 shadow rounded-3">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-2">
                            <h4 class="mb-0">Projects</h4>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-success btn-add-project" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                                    <i class="fas fa-plus me-2"></i>Add Project
                                </button>
                            </div>
                        </div>
                        <hr>
                        <form class="mb-3" method="get" action="" id="searchForm" style="max-width:400px;">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                
                            </div>
                        </form>
                        
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
                                                  <a class="btn btn-outline-primary btn-sm" href="project_process.php?project_id=<?php echo $project['project_id']; ?>" onclick="console.log('Navigating to project_process.php with project_id=<?php echo $project['project_id']; ?>')"> 
                                                        <i class="fas fa-eye"></i> Details
                                                    </a>
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

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center custom-pagination-green">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($page - 1); ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">Previous</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($page + 1); ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <!-- Feedback Modal -->
    <div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center">
          <div class="modal-body py-4">
            <span id="feedbackIcon" style="font-size: 3rem;"></span>
            <h4 id="feedbackMessage" class="mt-3"></h4>
          </div>
          <div class="modal-footer justify-content-center border-0">
            <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal">OK</button>
          </div>
        </div>
      </div>
    </div>

    
    <div class="modal fade" id="addProjectModal" tabindex="-1" aria-labelledby="addProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="add_project.php" id="multiStepForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addProjectModalLabel">Add New Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Progress Bar -->
                        <div class="progress mb-4" style="height: 10px;">
                            <div class="progress-bar" id="formProgress" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        
                        <!-- Step Indicators -->
                        <div class="d-flex justify-content-between mb-4">
                            <div class="step-indicator active" data-step="1">
                                <div class="step-number">1</div>
                                <div class="step-label">Client Info</div>
                            </div>
                            <div class="step-connector"></div>
                            <div class="step-indicator" data-step="2">
                                <div class="step-number">2</div>
                                <div class="step-label">Project Details</div>
                            </div>
                        </div>
                        
                        <!-- Step 1: Client Information -->
                        <div class="step" id="step1">
                            <h5 class="mb-4">Client Information</h5>
                            
                            <!-- Client Type Selection -->
                            <div class="mb-4">
                                <label class="form-label d-block mb-2">Client Type <span class="text-danger">*</span></label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="client_type" id="newClient" value="new" checked>
                                    <label class="form-check-label" for="newClient">New Client</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="client_type" id="existingClient" value="existing">
                                    <label class="form-check-label" for="existingClient">Existing Client</label>
                                </div>
                            </div>
                            
                            <!-- New Client Fields -->
                            <div id="newClientFields">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="firstName" class="form-label">First Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="firstName" name="first_name">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="lastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="lastName" name="last_name">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email">
                                </div>
                                <!-- Password will be auto-generated on the server side -->
                                <input type="hidden" name="password" value="auto-generated">
                            </div>
                            
                            <!-- Existing Client Fields -->
                            <div id="existingClientFields" class="d-none">
                                <div class="mb-3">
                                    <label for="clientEmail" class="form-label">Client Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="clientEmail" name="client_email">
                                </div>
                            </div>
                        </div>
                        
                       <!-- Step 2: Project Details -->
                        <div class="step d-none" id="step2">
                            <h5 class="mb-4">Project Details</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="projectName" class="form-label">Project Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="projectName" name="project_name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="size" class="form-label">Size (sqm) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="size" name="size" step="0.01" min="0" required>
                                        <span class="input-group-text">sqm</span>
                                    </div>
                                </div>

                                <!-- Region -->
                                <div class="col-md-6 mb-3">
                                    <label for="region" class="form-label">Region <span class="text-danger">*</span></label>
                                    <select class="form-control" name="region" id="region-select" required>
                                        <option value="" selected disabled>Select Region</option>
                                    </select>
                                </div>

                                <!-- Province -->
                                <div class="col-md-6 mb-3">
                                    <label for="province" class="form-label">Province <span class="text-danger">*</span></label>
                                    <select class="form-control" name="province" id="province-select" required disabled>
                                        <option value="" selected disabled>Select Region First</option>
                                    </select>
                                </div>

                                <!-- Municipality -->
                                <div class="col-md-6 mb-3">
                                    <label for="municipality" class="form-label">Municipality/City <span class="text-danger">*</span></label>
                                    <select class="form-control" name="municipality" id="municipality-select" required disabled>
                                        <option value="" selected disabled>Select Province First</option>
                                    </select>
                                </div>

                                <!-- Barangay -->
                                <div class="col-md-6 mb-3">
                                    <label for="barangay" class="form-label">Barangay <span class="text-danger">*</span></label>
                                    <select class="form-control" name="barangay" id="barangay-select" required disabled>
                                        <option value="" selected disabled>Select Municipality First</option>
                                    </select>
                                </div>

                                <!-- Hidden location input -->
                                <input type="hidden" id="location" name="location" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-outline-primary" id="prevBtn" style="display: none;">
                            <i class="fas fa-arrow-left me-1"></i> Previous
                        </button>
                        <button type="button" class="btn btn-primary" id="nextBtn">
                            Next <i class="fas fa-arrow-right ms-1"></i>
                        </button>
                        <button type="submit" name="add_project" class="btn btn-success" id="submitBtn" style="display: none;">
                            <i class="fas fa-save me-1"></i> Save Project
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Show feedback modal
    function showFeedback(type, message) {
      console.log('showFeedback called with:', type, message);
      const modalEl = document.getElementById('feedbackModal');
      
      if (!modalEl) {
        console.error('Modal element not found!');
        return;
      }
      
      const modal = new bootstrap.Modal(modalEl);
      const icon = document.getElementById('feedbackIcon');
      const msg = document.getElementById('feedbackMessage');
      
      if (!icon || !msg) {
        console.error('Modal elements not found!', {icon, msg});
        return;
      }
      
      if (type === 'success') {
        icon.className = 'fas fa-check-circle text-success';
      } else if (type === 'error') {
        icon.className = 'fas fa-times-circle text-danger';
      } else {
        icon.className = 'fas fa-info-circle text-primary';
      }
      
      msg.textContent = message;
      
      try {
        modal.show();
        console.log('Modal should be visible now');
      } catch (e) {
        console.error('Error showing modal:', e);
      }
    }
    
    // Check for success/error messages in URL
    document.addEventListener('DOMContentLoaded', function() {
      const urlParams = new URLSearchParams(window.location.search);
      
      if (urlParams.has('success')) {
        let message = 'Operation completed successfully!';
        if (urlParams.get('success') === 'add') {
          message = 'Project has been added successfully!';
        } else if (urlParams.get('success') === 'archive') {
          message = 'Project has been archived successfully!';
        }
        showFeedback('success', message);
        
        // Clean up URL
        const cleanUrl = window.location.pathname + 
          window.location.search.replace(/[?&]success=[^&]*/, '').replace(/^&/, '?');
        window.history.replaceState({}, document.title, cleanUrl);
      }
      
      if (urlParams.has('error')) {
        showFeedback('error', decodeURIComponent(urlParams.get('error')));
        
        // Clean up URL
        const cleanUrl = window.location.pathname + 
          window.location.search.replace(/[?&]error=[^&]*/, '').replace(/^&/, '?');
        window.history.replaceState({}, document.title, cleanUrl);
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

        var el = document.getElementById("wrapper");
        var toggleButton = document.getElementById("menu-toggle");

        toggleButton.onclick = function () {
            el.classList.toggle("toggled");
        };
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle client type selection
            const newClientRadio = document.getElementById('newClient');
            const existingClientRadio = document.getElementById('existingClient');
            const newClientFields = document.getElementById('newClientFields');
            const existingClientFields = document.getElementById('existingClientFields');
            
            function toggleClientFields() {
                if (newClientRadio.checked) {
                    newClientFields.classList.remove('d-none');
                    existingClientFields.classList.add('d-none');
                    // Make new client fields required
                    document.getElementById('firstName').required = true;
                    document.getElementById('lastName').required = true;
                    document.getElementById('email').required = true;
                    document.getElementById('clientEmail').required = false;
                } else {
                    newClientFields.classList.add('d-none');
                    existingClientFields.classList.remove('d-none');
                    // Make existing client email required
                    document.getElementById('firstName').required = false;
                    document.getElementById('lastName').required = false;
                    document.getElementById('email').required = false;
                    document.getElementById('clientEmail').required = true;
                }
            }
            
            newClientRadio.addEventListener('change', toggleClientFields);
            existingClientRadio.addEventListener('change', toggleClientFields);
            
            // Initialize the form state
            toggleClientFields();
            // Form validation
            function validateStep(step) {
                const currentStep = document.getElementById(`step${step}`);
                const inputs = currentStep.querySelectorAll('input[required]');
                let isValid = true;
                
                inputs.forEach(input => {
                    if (!input.value.trim()) {
                        input.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        input.classList.remove('is-invalid');
                    }
                    
                    // Email validation
                    if (input.type === 'email' && input.value.trim()) {
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailRegex.test(input.value.trim())) {
                            input.classList.add('is-invalid');
                            isValid = false;
                        }
                    }
                });
                
                return isValid;
            }
            
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Multi-step form functionality
            const form = document.getElementById('multiStepForm');
            const steps = document.querySelectorAll('.step');
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const submitBtn = document.getElementById('submitBtn');
            const progressBar = document.getElementById('formProgress');
            let currentStep = 1;
            const totalSteps = steps.length;

            // Initialize form
            updateForm();

            // Next button click handler
            nextBtn.addEventListener('click', function() {
                // Validate current step before proceeding
                if (validateStep(currentStep)) {
                    currentStep++;
                    updateForm();
                }
            });

            // Previous button click handler
            prevBtn.addEventListener('click', function() {
                currentStep--;
                updateForm();
            });

            // Toggle password visibility
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            });

            // Generate random password
            document.getElementById('generatePassword').addEventListener('click', function(e) {
                e.preventDefault();
                const randomString = Math.random().toString(36).slice(-8);
                passwordInput.value = randomString;
                // Ensure password is hidden after generation
                passwordInput.setAttribute('type', 'password');
                togglePassword.innerHTML = '<i class="fas fa-eye"></i>';
            });

            // Update form UI based on current step
            function updateForm() {
                // Hide all steps
                steps.forEach(step => step.classList.add('d-none'));
                
                // Show current step
                document.getElementById(`step${currentStep}`).classList.remove('d-none');
                
                // Update progress bar
                const progress = ((currentStep - 1) / (totalSteps - 1)) * 100;
                progressBar.style.width = `${progress}%`;
                progressBar.setAttribute('aria-valuenow', progress);
                
                // Update step indicators
                document.querySelectorAll('.step-indicator').forEach((indicator, index) => {
                    const stepNumber = parseInt(indicator.getAttribute('data-step'));
                    if (stepNumber < currentStep) {
                        indicator.classList.add('completed');
                        indicator.classList.remove('active');
                    } else if (stepNumber === currentStep) {
                        indicator.classList.add('active');
                        indicator.classList.remove('completed');
                    } else {
                        indicator.classList.remove('active', 'completed');
                    }
                });
                
                // Update button visibility
                if (currentStep === 1) {
                    prevBtn.style.display = 'none';
                    nextBtn.style.display = 'inline-block';
                    submitBtn.style.display = 'none';
                    nextBtn.innerHTML = 'Next <i class="fas fa-arrow-right ms-1"></i>';
                } else if (currentStep === totalSteps) {
                    prevBtn.style.display = 'inline-block';
                    nextBtn.style.display = 'none';
                    submitBtn.style.display = 'inline-block';
                } else {
                    prevBtn.style.display = 'inline-block';
                    nextBtn.style.display = 'inline-block';
                    submitBtn.style.display = 'none';
                    nextBtn.innerHTML = 'Next <i class="fas fa-arrow-right ms-1"></i>';
                }
                
                // Scroll to top of form
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            // Validate current step
            function validateStep(step) {
                const currentStepElement = document.getElementById(`step${step}`);
                const inputs = currentStepElement.querySelectorAll('input[required]');
                let isValid = true;
                
                inputs.forEach(input => {
                    if (!input.value.trim()) {
                        input.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        input.classList.remove('is-invalid');
                    }
                    
                    // Email validation
                    if (input.type === 'email' && input.value) {
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailRegex.test(input.value)) {
                            input.classList.add('is-invalid');
                            isValid = false;
                        }
                    }
                });
                
                return isValid;
            }

            // Clear form when modal is closed
            document.getElementById('addProjectModal').addEventListener('hidden.bs.modal', function () {
                form.reset();
                currentStep = 1;
                updateForm();
                // Reset any error states
                document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            });

            // Form submission
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validate all steps before submission
                let allValid = true;
                for (let i = 1; i <= totalSteps; i++) {
                    if (!validateStep(i)) {
                        allValid = false;
                        break;
                    }
                }
                
                if (allValid) {
                    // If client with this email already exists, just get the ID
                    // Otherwise, create a new client
                    const email = document.getElementById('email').value;
                    const clientData = {
                        first_name: document.getElementById('firstName').value,
                        last_name: document.getElementById('lastName').value,
                        email: email,
                        password: document.getElementById('password').value
                    };
                    
                    // In a real application, you would send this data to the server via AJAX
                    // For now, we'll just submit the form normally
                    this.submit();
                }
            });
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
                window.location.href = 'projects.php?archive=' + projectToArchive;
                }
                archiveModal.hide();
            });

            archiveModalEl.addEventListener('hidden.bs.modal', function() {
                projectToArchive = null;
            });
        });
    </script>
    <script>
        let phData = null;
        fetch('philippines.json')
            .then(response => response.json())
            .then(data => {
                phData = data;
                // Populate regions
                const regionSelect = document.getElementById('region-select');
                for (const regionKey in phData) {
                    const region = phData[regionKey];
                    const opt = document.createElement('option');
                    opt.value = region.region_name;
                    opt.textContent = region.region_name;
                    regionSelect.appendChild(opt);
                }
            });

        document.getElementById('region-select').addEventListener('change', function() {
            const regionName = this.value;
            const regionKey = Object.keys(phData).find(key => phData[key].region_name === regionName);
            const provinceSelect = document.getElementById('province-select');
            const municipalitySelect = document.getElementById('municipality-select');
            const barangaySelect = document.getElementById('barangay-select');
            provinceSelect.innerHTML = '<option value="" selected disabled>Select Province</option>';
            municipalitySelect.innerHTML = '<option value="" selected disabled>Select Province First</option>';
            barangaySelect.innerHTML = '<option value="" selected disabled>Select Municipality First</option>';
            provinceSelect.disabled = false;
            municipalitySelect.disabled = true;
            barangaySelect.disabled = true;
            for (const provinceName in phData[regionKey].province_list) {
                const opt = document.createElement('option');
                opt.value = provinceName;
                opt.textContent = provinceName;
                provinceSelect.appendChild(opt);
                }
            });

        document.getElementById('province-select').addEventListener('change', function() {
            const regionName = document.getElementById('region-select').value;
            const regionKey = Object.keys(phData).find(key => phData[key].region_name === regionName);
            const provinceName = this.value;
            const municipalitySelect = document.getElementById('municipality-select');
            const barangaySelect = document.getElementById('barangay-select');
            municipalitySelect.innerHTML = '<option value="" selected disabled>Select Municipality</option>';
            barangaySelect.innerHTML = '<option value="" selected disabled>Select Municipality First</option>';
            municipalitySelect.disabled = false;
            barangaySelect.disabled = true;
            for (const municipalityName in phData[regionKey].province_list[provinceName].municipality_list) {
                const opt = document.createElement('option');
                opt.value = municipalityName;
                opt.textContent = municipalityName;
                municipalitySelect.appendChild(opt);
                }
            });

        document.getElementById('municipality-select').addEventListener('change', function() {
            const regionName = document.getElementById('region-select').value;
            const regionKey = Object.keys(phData).find(key => phData[key].region_name === regionName);
            const provinceName = document.getElementById('province-select').value;
            const municipalityName = this.value;
            const barangaySelect = document.getElementById('barangay-select');
            barangaySelect.innerHTML = '<option value="" selected disabled>Select Barangay</option>';
            barangaySelect.disabled = false;
            const barangayList = phData[regionKey].province_list[provinceName].municipality_list[municipalityName].barangay_list;
            for (const barangay of barangayList) {
                const opt = document.createElement('option');
                opt.value = barangay;
                opt.textContent = barangay;
                barangaySelect.appendChild(opt);
        }
    });
    </script>
  </body>
</html>