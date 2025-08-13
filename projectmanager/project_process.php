<?php
session_start();
require_once '../config.php';

// Fetch project name if project_id is in GET
$current_project_name = '';
$current_step = 1; // Default step

if (isset($_GET['project_id'])) {
    $pid = intval($_GET['project_id']);
    $res = $con->query("SELECT project, step_progress FROM projects WHERE project_id = $pid LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $current_project_name = $row['project'];
        $current_step = intval($row['step_progress'] ?? 1);
        if ($current_step < 1 || $current_step > 8) $current_step = 1;
    }
}

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
<script>
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const pid = urlParams.get('project_id');
    console.log('Loaded project_process.php, project_id from URL:', pid);
});
</script>
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
                <!-- Progress Bar -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: 12.5%;" aria-valuenow="12.5" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <?php 
                            $stepTitles = [
                                1 => 'Blueprint',
                                2 => 'Cost Estimation',
                                3 => 'Budget Approval',
                                4 => 'Contract Signing',
                                5 => 'Permits',
                                6 => 'Schedule',
                                7 => 'Actual',
                                8 => 'Billing'
                            ];
                            for($i = 1; $i <= 8; $i++): ?>
                                <div class="text-center" style="flex: 1; min-width: 80px;">
                                    <div class="step-number d-inline-flex align-items-center justify-content-center rounded-circle bg-success text-white" style="width: 30px; height: 30px; font-weight: bold;"><?php echo $i; ?></div>
                                    <div class="step-label small mt-1" style="font-size: 0.7rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo $stepTitles[$i]; ?></div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <!-- Step Content -->
                <div class="card shadow rounded-3">
                    <div class="card-body">
                        <form id="projectProcessForm">
                        <!-- Step 1: Blueprint Upload -->
                        <?php include 'step1_blueprint.php'; ?>
                            <!-- Step 2: Cost Estimation -->
                            <?php include 'step2_estimation.php'; ?>
                            <!-- Step 3: Budget Approval -->
                            <?php include 'step3_budget.php'; ?>
                            <!-- Step 4: Contract Signing -->
                            <?php include 'step4_contract.php'; ?>
                            <!-- Step 5: Payment & Permits -->
                            <?php include 'step5_permits.php'; ?>
                            <!-- Step 6: Navigation -->
                            <?php include 'step6_schedule.php'; ?>
                            <!-- Step 7: Schedule -->
                            <?php include 'step7_navigation.php'; ?>
                            <!-- Step 8: Billing and Retention -->
                            <div class="step-content d-none" id="step8">
                                <h4 class="mb-4">Step 8: Billing and Retention</h4>
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-secondary prev-step" data-prev="7">Previous</button>
                                    <button type="submit" class="btn btn-success">Complete Project Setup</button>
                                </div>
                            </div>
                        </form>
                    </div>
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
   <!-- Blueprints Modal -->
    <div class="modal fade" id="blueprintsModal" tabindex="-1" aria-labelledby="blueprintsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="blueprintsModalLabel">
                        <i class="fas fa-drafting-compass"></i> Project Blueprints
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="blueprintSearch" placeholder="Search blueprints...">
                            </div>
                        </div>
                    </div>
                    <div id="blueprintsList" class="row">
                        <!-- Blueprints will be loaded here -->
                    </div>
                    <div id="blueprintsLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <div id="noBlueprints" class="text-center py-4" style="display: none;">
                        <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No blueprints found</h5>
                        <p class="text-muted">Upload your first blueprint to get started.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Image View Modal -->
    <div class="modal fade" id="imageViewModal" tabindex="-1" aria-labelledby="imageViewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageViewModalLabel">Blueprint View</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" class="img-fluid" alt="Blueprint">
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <?php if (isset($_GET['add_success'])): ?>
    <div class="modal fade" id="successAddMaterialModal" tabindex="-1" aria-labelledby="successAddMaterialLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successAddMaterialLabel">
                        <i class="fas fa-check-circle me-2"></i> Success
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    Material added successfully!
                </div>
            </div>
        </div>
    </div>
   
    <?php endif; ?>
   <!-- Add Materials Modal -->
    <div class="modal fade" id="addMaterialsModal" tabindex="-1" role="dialog" aria-labelledby="addMaterialsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
            <div class="modal-content shadow">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="addMaterialsModalLabel">
                        <i class="fas fa-plus-circle me-2"></i> Add Materials
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addMaterialsForm" method="post" action="add_project_material.php">
                    <input type="hidden" name="project_id" id="project_id" value="<?php echo isset($_GET['project_id']) ? intval($_GET['project_id']) : ''; ?>">
                    <input type="hidden" name="add_estimation_material" value="1">

                    <div class="modal-body p-0">
                        <div class="table-responsive" style="max-height: 60vh; overflow-y: auto;">
                            <table class="table table-hover table-bordered table-striped mb-0" id="materialsTable">
                                <thead class="table-light sticky-top" style="position: sticky; top: 0; z-index: 10; background-color: #f8f9fa;">
                                    <tr>
                                        <th width="40" class="align-middle">
                                            <div class="form-check d-flex justify-content-center">
                                                <input class="form-check-input m-0" type="checkbox" id="selectAllMaterials">
                                            </div>
                                        </th>
                                        <th class="align-middle">Material Name</th>
                                        <th class="align-middle">Brand</th>
                                        <th class="align-middle">Supplier</th>
                                        <th class="align-middle">Specification</th>
                                        <th class="align-middle text-center" style="width: 80px;">Unit</th>
                                        <th class="align-middle text-end" style="min-width: 100px;">Price (₱)</th>
                                        <th class="align-middle" style="width: 150px;">Quantity</th>
                                        <th class="align-middle text-end" style="min-width: 120px;">Total (₱)</th>
                                    </tr>
                                </thead>
                                <tbody id="materialsTableBody" class="bg-white">
                                    <tr>
                                        <td colspan="8" class="text-center py-3">
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

                    <div class="modal-footer bg-light border-top">
                        <div class="d-flex justify-content-end w-100">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i> Cancel
                            </button>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check me-1"></i> Add Selected Materials
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/step1_blueprint.js"></script>
    <script src="js/step3_budget.js"></script>
    <script src="js/step4_contract.js"></script>
    <script src="js/step5_permits.js"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        var modal = new bootstrap.Modal(document.getElementById('successAddMaterialModal'));
        modal.show();
        setTimeout(function() {
          modal.hide();
          // Remove add_success from URL without reloading
          if (window.history.replaceState) {
            const url = new URL(window.location);
            url.searchParams.delete('add_success');
            window.history.replaceState({}, document.title, url.toString());
          }
        }, 2000);
      });
    </script>
    <style>
        .step-content { min-height: 300px; padding: 20px 0; }
        .step-number { transition: all 0.3s ease; cursor: pointer; }
        .step-number.active { transform: scale(1.2); box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.3); }
        .step-number.completed { background-color: #198754; }
        .progress { margin-bottom: 10px; }
        .step-label { font-weight: 500; }
        
        /* Hide number input arrows for all browsers */
        input[type=number]::-webkit-outer-spin-button,
        input[type=number]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        
        /* Firefox */
        input[type=number] {
            -moz-appearance: textfield;
        }
        
        /* Quantity controls */
        .quantity-controls {
            display: flex;
            align-items: center;
        }
        
        .quantity-input {
            width: 80px !important;
            text-align: center;
            -moz-appearance: textfield;
        }
        
        .quantity-btn {
            min-width: 32px !important;
            padding: 0.25rem 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Step navigation
        const form = document.getElementById('projectProcessForm');
        const nextButtons = document.querySelectorAll('.next-step');
        const prevButtons = document.querySelectorAll('.prev-step');
        const progressBar = document.querySelector('.progress-bar');
        const totalSteps = 8;

        // STEP 1: Show step from backend on page load
        let currentStep = <?php echo $current_step; ?>;
        window.currentStep = currentStep; // Make it globally accessible
        showStep(currentStep);
        updateProgress(currentStep);

        // Next button click handler
        nextButtons.forEach(button => {
            button.addEventListener('click', function() {
                const nextStep = parseInt(this.getAttribute('data-next'));
                const urlParams = new URLSearchParams(window.location.search);
                const projectId = urlParams.get('project_id');
                const btn = this;
                if (!projectId || isNaN(nextStep)) {
                    alert('Missing project ID or next step.');
                    return;
                }
                
                // Skip validation for step 6 (schedule) to allow navigation without filling the form
                if (currentStep !== 6 && !validateStep(currentStep)) {
                    return;
                }

                btn.disabled = true;
                fetch('update_project_step.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `project_id=${encodeURIComponent(projectId)}&new_step=${encodeURIComponent(nextStep)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // After backend update, fetch current step from backend
                        fetch(`get_project_step.php?project_id=${projectId}`)
                        .then(res => res.json())
                        .then(stepData => {
                            if (stepData.success && stepData.step_progress) {
                                currentStep = parseInt(stepData.step_progress, 10);
                                window.currentStep = currentStep; // Update global variable
                                showStep(currentStep);
                                updateProgress(currentStep);
                            }
                            btn.disabled = false;
                        });
                    } else {
                        alert(data.message || 'Failed to update project step.');
                        btn.disabled = false;
                    }
                })
                .catch(error => {
                    alert('Error updating project step.');
                    btn.disabled = false;
                });
            });
        });

        // Previous button click handler
        prevButtons.forEach(button => {
            button.addEventListener('click', function() {
                const prevStep = parseInt(this.getAttribute('data-prev'));
                const urlParams = new URLSearchParams(window.location.search);
                const projectId = urlParams.get('project_id');
                const btn = this;

                if (!projectId || isNaN(prevStep)) {
                    alert('Missing project ID or previous step.');
                    return;
                }

                btn.disabled = true;
                fetch('update_project_step.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `project_id=${encodeURIComponent(projectId)}&new_step=${encodeURIComponent(prevStep)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // After backend update, fetch current step from backend
                        fetch(`get_project_step.php?project_id=${projectId}`)
                        .then(res => res.json())
                        .then(stepData => {
                            if (stepData.success && stepData.step_progress) {
                                currentStep = parseInt(stepData.step_progress, 10);
                                window.currentStep = currentStep; // Update global variable
                                showStep(currentStep);
                                updateProgress(currentStep);
                            }
                            btn.disabled = false;
                        });
                    } else {
                        alert(data.message || 'Failed to update project step.');
                        btn.disabled = false;
                    }
                })
                .catch(error => {
                    alert('Error updating project step.');
                    btn.disabled = false;
                });
            });
        });

        
        // Show specific step
        function showStep(stepNumber) {
            document.querySelectorAll('.step-content').forEach(step => {
                step.classList.add('d-none');
            });
            var stepDiv = document.getElementById('step' + stepNumber);
            if (stepDiv) stepDiv.classList.remove('d-none');
        }

        // Update progress bar and step indicators
        function updateProgress(step) {
            const progress = (step / totalSteps) * 100;
            progressBar.style.width = progress + '%';
            progressBar.setAttribute('aria-valuenow', progress);

            // Update step indicators
            document.querySelectorAll('.step-number').forEach((el, index) => {
                const stepNum = index + 1;
                if (stepNum < step) {
                    el.classList.add('completed');
                    el.classList.remove('active');
                } else if (stepNum === step) {
                    el.classList.add('active');
                    el.classList.remove('completed');
                } else {
                    el.classList.remove('active', 'completed');
                }
            });
        }

        // Validate current step
        function validateStep(step) {
            const currentStepEl = document.getElementById('step' + step);
            if (!currentStepEl) return true;
            const inputs = currentStepEl.querySelectorAll('[required]');
            let isValid = true;

            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    isValid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });

            return isValid;
        }

        // Toggle sidebar
        var el = document.getElementById("wrapper");
        var toggleButton = document.getElementById("menu-toggle");
        if (toggleButton) {
            toggleButton.onclick = function () {
                el.classList.toggle("toggled");
            };
        }
    });

    function showAlert(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        const container = document.querySelector('.container-fluid');
        container.insertBefore(alertDiv, container.firstChild);
        setTimeout(() => {
            alertDiv.remove();
        }, 3000);
    }
    </script>
  </body>
</html>