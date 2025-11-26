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

// Fetch project details if project_id is in GET
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

// Handle add division (task)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_division'])) {
    $task_name = trim(mysqli_real_escape_string($con, $_POST['division_name']));
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    if ($task_name !== '' && $start_date && $end_date && $project_id > 0) {
        mysqli_query($con, "INSERT INTO project_timeline (project_id, task_name, start_date, end_date, progress, status, created_at, updated_at) VALUES ('$project_id', '$task_name', '$start_date', '$end_date', 0, 'Not Started', NOW(), NOW())");
    }
    header("Location: project_process_v2.php?project_id=$project_id&added=1");
    exit();
}

// Handle automatic creation of standard project phases
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_standard_phases'])) {
    if ($project_id > 0) {
        // Delete existing tasks for this project
        mysqli_query($con, "DELETE FROM project_timeline WHERE project_id = $project_id");
        
        // Get project dates
        $project_query = "SELECT start_date, deadline FROM projects WHERE project_id = ?";
        if ($stmt = $con->prepare($project_query)) {
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $project = $result->fetch_assoc();
                $project_start = new DateTime($project['start_date']);
                $project_end = new DateTime($project['deadline']);
                $project_duration = $project_start->diff($project_end)->days;
                
                // Standard project phases
                $phases = [
                    'Mobilization',
                    'Planning & Preparation', 
                    'Procurement & Inspection',
                    'Installation Works',
                    'Testing & Commissioning',
                    'Turnover',
                    'Demobilization'
                ];
                
                $phase_duration = floor($project_duration / count($phases));
                $current_start = clone $project_start;
                
                foreach ($phases as $index => $phase) {
                    // Calculate end date for this phase
                    $current_end = clone $current_start;
                    $current_end->add(new DateInterval('P' . $phase_duration . 'D'));
                    
                    // For the last phase, ensure it ends on project deadline
                    if ($index === count($phases) - 1) {
                        $current_end = clone $project_end;
                    }
                    
                    // Insert phase into database
                    $phase_name = mysqli_real_escape_string($con, $phase);
                    $start_date_str = $current_start->format('Y-m-d');
                    $end_date_str = $current_end->format('Y-m-d');
                    
                    mysqli_query($con, "INSERT INTO project_timeline (project_id, task_name, start_date, end_date, progress, status, created_at, updated_at) VALUES ('$project_id', '$phase_name', '$start_date_str', '$end_date_str', 0, 'Not Started', NOW(), NOW())");
                    
                    // Set next phase start date
                    $current_start = clone $current_end;
                    $current_start->add(new DateInterval('P1D')); // Start next day
                }
            }
            $stmt->close();
        }
    }
    header("Location: project_process_v2.php?project_id=$project_id&auto_created=1");
    exit();
}

$project_name = '';
if ($project_id > 0) {
    $project_query = "SELECT project, start_date, deadline FROM projects WHERE project_id = ?";
    if ($stmt = $con->prepare($project_query)) {
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $project = $result->fetch_assoc();
            $project_name = htmlspecialchars($project['project']);
            
            // Convert dates to YYYY-MM-DD format for date inputs
            $start_date = date('Y-m-d', strtotime($project['start_date']));
            $deadline = date('Y-m-d', strtotime($project['deadline']));
        }
        $stmt->close();
    }
}

// Set success messages
$success_message = '';
if (isset($_GET['added'])) {
    $success_message = 'Task added successfully!';
}
if (isset($_GET['auto_created'])) {
    $success_message = 'Standard project phases created successfully!';
}
$current_project_name = '';
$current_step = 1; // Default step
// Output project dates as JavaScript variables for client-side validation
echo "<script>
    // Format: YYYY-MM-DD for date inputs
    const PROJECT_START_DATE = '" . $start_date . "';
    const PROJECT_DEADLINE = '" . $deadline . "';
    
    // For display purposes
    const PROJECT_START_DISPLAY = '" . date('F j, Y', strtotime($start_date)) . "';
    const PROJECT_DEADLINE_DISPLAY = '" . date('F j, Y', strtotime($deadline)) . "';
</script>";
if (isset($_GET['project_id'])) {
    $pid = intval($_GET['project_id']);
    $res = $con->query("SELECT project, step_progress, status FROM projects WHERE project_id = $pid LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $current_project_name = $row['project'];
        $current_step = intval($row['step_progress'] ?? 1);
        $project_status = $row['status'];
        if ($current_step < 1 || $current_step > 8) $current_step = 1;
        
        // Calculate project progress based on tasks
        $progress_query = "SELECT 
            COUNT(*) as total_tasks,
            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
            COALESCE(AVG(progress), 0) as avg_progress
            FROM project_timeline 
            WHERE project_id = $pid";
            
        $progress_result = $con->query($progress_query);
        if ($progress_result && $progress_result->num_rows > 0) {
            $progress_data = $progress_result->fetch_assoc();
            $total_tasks = $progress_data['total_tasks'];
            $completed_tasks = $progress_data['completed_tasks'];
            $progress_percent = $progress_data['avg_progress'];
        } else {
            $progress_percent = 0;
            $total_tasks = 0;
            $completed_tasks = 0;
        }
    }
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
$blueprint_count = 0;
if ($project_id > 0) {
    $stmt = $con->prepare("SELECT COUNT(*) FROM blueprints WHERE project_id = ?");
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $stmt->bind_result($blueprint_count);
    $stmt->fetch();
    $stmt->close();
}
$budget_doc_exists = false;
if ($project_id > 0) {
    $stmt = $con->prepare("SELECT COUNT(*) FROM project_pdf_approval WHERE project_id = ?");
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $stmt->bind_result($doc_count);
    $stmt->fetch();
    $stmt->close();
    $budget_doc_exists = $doc_count > 0;
}
// 1. Get project total budget and initial payment
$project_budget = 0;
$initial_budget = 0;
$stmt = $con->prepare("SELECT budget, initial_budget FROM projects WHERE project_id = ?");
$stmt->bind_param('i', $project_id);
$stmt->execute();
$stmt->bind_result($project_budget, $initial_budget);
$stmt->fetch();
$stmt->close();
// 2. Get total completed payments from approved_payments (only count completed status)
$total_completed_payments = 0;
$stmt = $con->prepare("SELECT COALESCE(SUM(amount),0) FROM approved_payments WHERE project_id = ? AND status = 'completed'");
$stmt->bind_param('i', $project_id);
$stmt->execute();
$stmt->bind_result($total_completed_payments);
$stmt->fetch();
$stmt->close();
// 3. Calculate total payments (initial + completed payments only)
$total_payments = $initial_budget + $total_completed_payments;
// 4. Calculate remaining budget and usage percentage
$remaining_budget = $project_budget - $total_payments;
$budget_percentage = $project_budget > 0 ? min(($total_payments / $project_budget) * 100, 100) : 0;
// 5. Formatting helper
function peso($amount) {
    return '₱' . number_format($amount, 2);
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
    <style>
        /* Contract alert transition */
        #contractAlert {
            transition: opacity 0.3s ease-in-out;
            opacity: 1;
        }
        
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
        #totalProjectCard {
            transition: background 0.4s ease, transform 0.3s ease;
        }
        #totalProjectCard.over-forecast-card {
            background: linear-gradient(135deg, #c0392b, #e74c3c);
            transform: translateY(-2px);
        }
        #totalProjectCost {
            transition: color 0.3s ease, text-shadow 0.3s ease;
        }
        #totalProjectCost.over-forecast-text {
            color: #fff;
            text-shadow: 0 1px 3px rgba(0,0,0,0.35);
        }
        .forecast-warning {
            opacity: 0;
            transform: translateY(-6px);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        .forecast-warning.show {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
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
                <a href="gantt.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'gantt.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar"></i>My Schedule
                </a>
                <a href="paymethod.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'paymethod.php' ? 'active' : ''; ?>">
                    <i class="fas fa-money-bill"></i>Payment Methods
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
                        <form id="projectProcessForm" method="post" action="">
                            <div class="step-content" id="step1">
                                <div class="row justify-content-center">
                                    <div class="col-lg-8">
                                        <div class="card border-0 shadow-sm mb-4">
                                            <div class="card-header bg-primary text-white">
                                                <h5 class="card-title mb-0"><i class="fas fa-upload me-2"></i>Step 1: Upload Blueprint</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-4">
                                                    <label class="form-label fw-bold">Blueprint Files</label>
                                                    <div class="card border-2 border-dashed" id="dropZone" style="min-height: 150px; cursor: pointer;">
                                                        <div class="card-body d-flex flex-column align-items-center justify-content-center p-4 text-center">
                                                            <i class="fas fa-file-upload fa-3x text-primary mb-3"></i>
                                                            <h5 class="mb-2">Drag & drop your files here</h5>
                                                            <p class="text-muted mb-3">or</p>
                                                            <button type="button" class="btn btn-primary px-4" id="browseFilesBtn">
                                                                <i class="fas fa-folder-open me-2"></i>Browse Files
                                                            </button>
                                                            <input type="file" class="d-none" name="blueprint_files[]" id="blueprintFiles" multiple accept=".pdf,.jpg,.jpeg,.png,.dwg">
                                                            <p class="small text-muted mt-3 mb-0">Supported formats: PDF, JPG, PNG, DWG</p>
                                                        </div>
                                                    </div>
                                                    <div id="fileList" class="mt-3"></div>
                                                </div>
                                                
                                                <input type="hidden" name="project_id" id="project_id" value="<?php echo htmlspecialchars($project_id); ?>">
                                                
                                                <div class="d-flex justify-content-between align-items-center mt-4">
                                                    <div class="text-muted small">
                                                        <strong><?= $blueprint_count ?></strong> blueprint<?= $blueprint_count == 1 ? '' : 's' ?> uploaded
                                                    </div>
                                                    <div>
                                                        <button type="button" class="btn btn-primary me-2" id="uploadBtn">
                                                            <i class="fas fa-upload me-2"></i>Upload Files
                                                        </button>
                                                        <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#blueprintsModal">
                                                            <i class="fas fa-eye me-2"></i>View Blueprints
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end mt-4">
                                    <button type="button" class="btn btn-primary next-step" id="step1NextBtn" data-next="2">
                                        Next <i class="fas fa-arrow-right"></i>
                                    </button>
                                </div>
                            </div>
                                                         <div class="step-content d-none" id="step2">
                                 <h4 class="mb-4">Step 2: Cost Estimation</h4>
                                 
                                 <!-- Cost Summary Cards -->
                                 <div class="row g-3 mb-4">
                                     <!-- Forecasted Cost Card -->
                                     <div class="col-md-3">
                                         <div class="card border-0 shadow-sm h-100">
                                             <div class="card-header bg-primary text-white py-2">
                                                 <div class="d-flex align-items-center">
                                                     <i class="fas fa-chart-line me-2"></i>
                                                     <h6 class="mb-0">Forecasted Budget</h6>
                                                 </div>
                                             </div>
                                             <div class="card-body text-center py-3">
                                                 <h3 class="text-primary fw-bold mb-0">
                                                     ₱<?php 
                                                     $forecasted_cost = 0;
                                                     if ($project_id > 0) {
                                                         $forecast_query = "SELECT forecasted_cost FROM projects WHERE project_id = ?";
                                                         if ($stmt = $con->prepare($forecast_query)) {
                                                             $stmt->bind_param("i", $project_id);
                                                             $stmt->execute();
                                                             $result = $stmt->get_result();
                                                             if ($result && $result->num_rows > 0) {
                                                                 $row = $result->fetch_assoc();
                                                                 $forecasted_cost = $row['forecasted_cost'];
                                                             }
                                                             $stmt->close();
                                                         }
                                                     }
                                                     echo number_format($forecasted_cost, 2);
                                                     ?>
                                                 </h3>
                                             </div>
                                         </div>
                                     </div>
                                     
                                     <!-- Labor Budget Card -->
                                     <div class="col-md-3">
                                         <div class="card border-0 shadow-sm h-100">
                                             <div class="card-header bg-warning text-white py-2">
                                                 <div class="d-flex align-items-center">
                                                     <i class="fas fa-wallet me-2"></i>
                                                     <h6 class="mb-0">Labor Budget</h6>
                                                 </div>
                                             </div>
                                             <div class="card-body text-center py-3">
                                                 <?php 
                                                 $labor_budget = 0;
                                                 if ($project_id > 0) {
                                                     // Get total labor/other costs from project materials
                                                     $labor_budget_query = "SELECT COALESCE(SUM(pem.quantity * COALESCE(m.labor_other, 0)), 0) as total 
                                                                            FROM project_estimating_materials pem
                                                                            LEFT JOIN materials m ON pem.material_id = m.id
                                                                            WHERE pem.project_id = ?";
                                                     if ($stmt = $con->prepare($labor_budget_query)) {
                                                         $stmt->bind_param("i", $project_id);
                                                         $stmt->execute();
                                                         $result = $stmt->get_result();
                                                         if ($result && $row = $result->fetch_assoc()) {
                                                             $labor_budget = $row['total'];
                                                         }
                                                         $stmt->close();
                                                     }
                                                 }
                                                 ?>
                                                 <h3 class="text-warning fw-bold mb-0" id="laborBudget">₱<?php echo number_format($labor_budget, 2); ?></h3>
                                             </div>
                                         </div>
                                     </div>
                                     
                                     <!-- Labor Budget Card -->
                                     <div class="col-md-3">
                                         <div class="card border-0 shadow-sm h-100">
                                             <div class="card-header bg-info text-white py-2">
                                                 <div class="d-flex align-items-center">
                                                     <i class="fas fa-users me-2"></i>
                                                     <h6 class="mb-0">Labor Costs</h6>
                                                 </div>
                                             </div>
                                             <div class="card-body text-center py-3">
                                                 <?php 
                                                 $labor_total = 0;
                                                 if ($project_id > 0) {
                                                     $labor_query = "SELECT COALESCE(SUM(total), 0) as total 
                                                                   FROM project_estimation_employee 
                                                                   WHERE project_id = ?";
                                                     if ($stmt = $con->prepare($labor_query)) {
                                                         $stmt->bind_param("i", $project_id);
                                                         $stmt->execute();
                                                         $result = $stmt->get_result();
                                                         if ($result && $row = $result->fetch_assoc()) {
                                                             $labor_total = $row['total'];
                                                         }
                                                         $stmt->close();
                                                     }
                                                 }
                                                 ?>
                                                 <h3 class="text-info fw-bold mb-0" id="laborTotal">₱<?php echo number_format($labor_total, 2); ?></h3>
                                                 <?php if ($labor_total > $labor_budget): ?>
                                                 <div class="mt-2">
                                                     <span class="badge bg-danger">
                                                         <i class="fas fa-exclamation-triangle me-1"></i>
                                                         Over Budget by ₱<?php echo number_format($labor_total - $labor_budget, 2); ?>
                                                     </span>
                                                 </div>
                                                 <?php endif; ?>
                                             </div>
                                         </div>
                                     </div>
                                     
                                     <!-- Materials Cost Card -->
                                     <div class="col-md-3">
                                         <div class="card border-0 shadow-sm h-100">
                                             <div class="card-header bg-success text-white py-2">
                                                 <div class="d-flex align-items-center">
                                                     <i class="fas fa-boxes me-2"></i>
                                                     <h6 class="mb-0">Materials Cost</h6>
                                                 </div>
                                             </div>
                                             <div class="card-body text-center py-3">
                                                 <?php 
                                                 $materials_total = 0;
                                                 if ($project_id > 0) {
                                                     $materials_query = "SELECT COALESCE(SUM(pem.material_price * pem.quantity), 0) as total 
                                                                       FROM project_estimating_materials pem
                                                                       WHERE pem.project_id = ?";
                                                     if ($stmt = $con->prepare($materials_query)) {
                                                         $stmt->bind_param("i", $project_id);
                                                         $stmt->execute();
                                                         $result = $stmt->get_result();
                                                         if ($result && $row = $result->fetch_assoc()) {
                                                             $materials_total = $row['total'];
                                                         }
                                                         $stmt->close();
                                                     }
                                                 }
                                                 ?>
                                                 <h3 class="text-success fw-bold mb-0" id="materialsTotal">₱<?php echo number_format($materials_total, 2); ?></h3>
                                             </div>
                                         </div>
                                     </div>
                                     
                                     <!-- Overhead Cost Card -->
                                     <div class="col-md-3">
                                         <div class="card border-0 shadow-sm h-100">
                                             <div class="card-header bg-warning text-dark py-2">
                                                 <div class="d-flex align-items-center">
                                                     <i class="fas fa-calculator me-2"></i>
                                                     <h6 class="mb-0">Overhead Costs</h6>
                                                 </div>
                                             </div>
                                             <div class="card-body text-center py-3">
                                                 <?php 
                                                 $overhead_total = 0;
                                                 if ($project_id > 0) {
                                                     // Exclude VAT from overhead total for this card
                                                     $overhead_query = "SELECT COALESCE(SUM(price), 0) as total 
                                                                     FROM overhead_costs 
                                                                     WHERE project_id = ? AND name <> 'VAT'";
                                                     $stmt = $con->prepare($overhead_query);
                                                     $stmt->bind_param("i", $project_id);
                                                     $stmt->execute();
                                                     $result = $stmt->get_result();
                                                     if ($result && $row = $result->fetch_assoc()) {
                                                         $overhead_total = $row['total'];
                                                     }
                                                     $stmt->close();
                                                 }
                                                 ?>
                                                 <h3 class="text-warning fw-bold mb-0" id="overheadSummary">₱<?php echo number_format($overhead_total, 2); ?></h3>
                                             </div>
                                         </div>
                                     </div>
                                     
                                     <!-- VAT Card -->
                                     <div class="col-md-3">
                                         <div class="card border-0 shadow-sm h-100">
                                             <div class="card-header bg-purple text-white py-2" style="background-color:#6f42c1!important;">
                                                 <div class="d-flex align-items-center">
                                                     <i class="fas fa-receipt me-2"></i>
                                                     <h6 class="mb-0">VAT (12%)</h6>
                                                 </div>
                                             </div>
                                             <div class="card-body text-center py-3">
                                                 <?php 
                                                 $vat_total = 0;
                                                 if ($project_id > 0) {
                                                     $vat_query = "SELECT COALESCE(SUM(price), 0) as total FROM overhead_costs WHERE project_id = ? AND name = 'VAT'";
                                                     $stmt = $con->prepare($vat_query);
                                                     $stmt->bind_param("i", $project_id);
                                                     $stmt->execute();
                                                     $result = $stmt->get_result();
                                                     if ($result && $row = $result->fetch_assoc()) {
                                                         $vat_total = $row['total'];
                                                     }
                                                     $stmt->close();
                                                 }
                                                 ?>
                                                 <h3 class="fw-bold mb-0" id="vatSummary" style="color:#6f42c1;">₱<?php echo number_format($vat_total, 2); ?></h3>
                                             </div>
                                         </div>
                                     </div>
                                     
                                     <!-- Total Cost Card -->
                            <div class="col-12">
                                <div class="card border-0 shadow-sm bg-gradient-primary text-white" id="totalProjectCard">
                                    <div class="card-body text-center p-3">
                                        <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
                                            <h5 class="mb-0 d-flex align-items-center gap-2">
                                                <i class="fas fa-calculator"></i>
                                                <span>Total Project Estimation</span>
                                                <span class="forecast-warning badge bg-warning text-dark d-flex align-items-center gap-1 d-none" id="forecastWarning">
                                                    <i class="fas fa-exclamation-triangle"></i>Over Forecasted Budget
                                                </span>
                                            </h5>
                                            <h2 class="fw-bold mb-0" id="totalProjectCost">
                                                ₱<?php 
                                                if (!isset($vat_total)) {
                                                    $vat_total = 0;
                                                }
                                                $total_cost = $materials_total + $labor_total + $overhead_total + $vat_total;
                                                echo number_format($total_cost, 2);
                                                ?>
                                            </h2>
                                        </div>
                                        <div class="mt-2 small">
                                            <span class="badge bg-light text-dark me-1">
                                                <i class="fas fa-boxes me-1"></i> Materials: <span id="materialsBadge">₱<?php echo number_format($materials_total, 2); ?></span>
                                            </span>
                                            <span class="badge bg-light text-dark me-1">
                                                <i class="fas fa-users me-1"></i> Labor Budget: <span id="laborBadge">₱<?php echo number_format($labor_budget, 2); ?></span>
                                            </span>
                                            <span class="badge bg-light text-dark me-1">
                                                <i class="fas fa-calculator me-1"></i> Overhead: <span id="overheadBadge">₱<?php echo number_format($overhead_total, 2); ?></span>
                                            </span>
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-receipt me-1"></i> VAT: <span id="vatBadge">₱<?php echo number_format($vat_total, 2); ?></span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                                 </div>
                                 
                                 <!-- Project Materials Section -->
                                 <div class="card mb-3 shadow-sm">
                                    <div class="card-header bg-success text-white d-flex align-items-center">
                                        <span class="flex-grow-1">Project Materials</span>
                                        <button type="button" class="btn btn-light btn-sm ml-auto" id="addMaterialsBtn" data-bs-toggle="modal" data-bs-target="#addMaterialsModal">
                                            <i class="fas fa-plus-square me-1"></i> Add Materials
                                        </button>
                                       
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
                                                    <?php
                                                    require_once __DIR__ . '/../config.php';
                                                    $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
                                                    $materials = [];
                                                    $total = 0;
                                                    $total_records = 0;
                                                    $records_per_page = 10;
                                                    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                                                    $offset = ($page - 1) * $records_per_page;
                                                    
                                                    if ($project_id) {
                                                        // Get total number of records and calculate grand total from ALL materials
                                                        $count_sql = "SELECT COUNT(*) as total, 
                                                                    COALESCE(SUM((pem.material_price + COALESCE(m.labor_other, 0)) * pem.quantity), 0) as grand_total 
                                                                    FROM project_estimating_materials pem
                                                                    LEFT JOIN materials m ON pem.material_id = m.id
                                                                    WHERE pem.project_id = $project_id";
                                                        $count_result = $con->query($count_sql);
                                                        $count_data = $count_result->fetch_assoc();
                                                        $total_records = $count_data['total'];
                                                        $grand_total = $count_data['grand_total'];
                                                        $total_pages = ceil($total_records / $records_per_page);
                                                        
                                                        // Get records for the current page
                                                        $sql = "SELECT pem.*, m.supplier_name, m.labor_other 
                                                                FROM project_estimating_materials pem
                                                                LEFT JOIN materials m ON pem.material_id = m.id
                                                                WHERE pem.project_id = $project_id
                                                                ORDER BY pem.id DESC
                                                                LIMIT $offset, $records_per_page";
                                                                
                                                        $result = $con->query($sql);
                                                        
                                                        if ($result && $result->num_rows > 0) {
                                                            $i = $offset + 1; // This will make the numbering continue from where the previous page left off
                                                            while ($row = $result->fetch_assoc()) {
                                                                $materials[] = $row;
                                                                echo '<tr>';
                                                                echo '<td>' . $i++ . '</td>';
                                                                echo '<td style="font-weight:bold;color:#222;">' . htmlspecialchars($row['material_name']) . '</td>';
                                                                echo '<td>' . htmlspecialchars($row['unit']) . '</td>';
                                                                echo '<td>' . number_format($row['material_price'], 2) . '</td>';
                                                                echo '<td>' . (isset($row['labor_other']) ? number_format($row['labor_other'], 2) : '0.00') . '</td>';
                                                                echo '<td>
                                                                        <div class="quantity-controls">
                                                                            <button type="button" class="btn btn-sm btn-outline-secondary quantity-decrease quantity-btn">
                                                                                <i class="fas fa-minus"></i>
                                                                            </button>
                                                                            <input type="number" class="form-control form-control-sm text-center mx-1 quantity-input" 
                                                                                value="' . $row['quantity'] . '" min="1" step="1" 
                                                                                data-pem-id="' . $row['id'] . '" 
                                                                                data-price="' . $row['material_price'] . '" 
                                                                                data-labor="' . (isset($row['labor_other']) ? $row['labor_other'] : '0') . '"
                                                                                onchange="updateMaterialQuantity(' . $row['id'] . ', this.value)"
                                                                                onkeydown="return event.key !== \'e\' && event.key !== \'E\' && event.key !== \'-\' && event.key !== \'+\';">
                                                                            <button type="button" class="btn btn-sm btn-outline-secondary quantity-increase quantity-btn">
                                                                                <i class="fas fa-plus"></i>
                                                                            </button>
                                                                        </div>
                                                                    </td>';
                                                                echo '<td>' . (isset($row['supplier_name']) ? htmlspecialchars($row['supplier_name']) : 'N/A') . '</td>';
                                                                echo '<td style="font-weight:bold;color:#222;">₱' . number_format((($row['material_price'] + (isset($row['labor_other']) ? $row['labor_other'] : 0)) * $row['quantity']), 2) . '</td>';
                                                                echo '<td><button class="btn btn-danger btn-sm remove-material" onclick="removeMaterial(' . $row['id'] . ')"><i class="fas fa-trash"></i> Remove</button></td>';
                                                                echo '</tr>';
                                                            }
                                                        } else {
                                                            echo '<tr><td colspan="9" class="text-center">No materials added</td></tr>';
                                                        }
                                                    } else {
                                                        echo '<tr><td colspan="9" class="text-center">No project selected</td></tr>';
                                                    }
                                                    ?>
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <th colspan="7" class="text-end">Grand Total (Material Price + Labor/Other * Quantity)</th>
                                                        <th colspan="2" style="font-weight:bold; color:#222;" id="materialsTotal">₱<?= number_format($grand_total, 2) ?></th>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                        <!-- Pagination -->
                                        <?php if ($project_id && $total_records > $records_per_page): ?>
                                        <div class="card-footer bg-white">
                                            <nav aria-label="Materials pagination">
                                                <ul class="pagination justify-content-center mb-0">
                                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                                        <a class="page-link" href="?project_id=<?= $project_id ?>&page=<?= $page - 1 ?>#step2" aria-label="Previous">
                                                            <span aria-hidden="true">&laquo;</span>
                                                        </a>
                                                    </li>
                                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                            <a class="page-link" href="?project_id=<?= $project_id ?>&page=<?= $i ?>#step2"><?= $i ?></a>
                                                        </li>
                                                    <?php endfor; ?>
                                                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                                        <a class="page-link" href="?project_id=<?= $project_id ?>&page=<?= $page + 1 ?>#step2" aria-label="Next">
                                                            <span aria-hidden="true">&raquo;</span>
                                                        </a>
                                                    </li>
                                                </ul>
                                            </nav>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <!-- Error Message Display -->
                                <?php if (isset($_GET['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?php echo htmlspecialchars($_GET['error']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php endif; ?>
                                <!-- Project Employees Section -->
                                <div class="card mb-3 shadow-sm">
                                    <div class="card-header bg-success text-white d-flex align-items-center">
                                        <span class="flex-grow-1">Project Team</span>
                                        <button type="button" class="btn btn-light btn-sm ml-auto" id="addEmployeeBtn" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                                            <i class="fas fa-user-plus me-1"></i> Add Employee
                                        </button>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-bordered mb-0">
                                                <thead class="table-secondary">
                                                    <tr>
                                                        <th>No.</th>
                                                        <th>Name</th>
                                                        <th>Position</th>
                                                        <th>Employee Type</th>
                                                        <th>Daily Rate</th>
                                                        <th>Project Days</th>
                                                        <th>Total</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $project_days = 0; // Default value set to 0 for manual entry
                                                    $emp_total = 0;
                                                    $proj_emps = [];
                                                    
                                                    if ($project_id) {
                                                        // Project days are now manually entered
                                                        // No automatic calculation of project days
                                                        
                                                        // Fetch estimation employees for this project
                                                        $emp_query = $con->prepare("SELECT pee.*, e.first_name, e.last_name, e.company_type
                                                                                FROM project_estimation_employee pee
                                                                                JOIN employees e ON pee.employee_id = e.employee_id
                                                                                WHERE pee.project_id = ?
                                                                                ORDER BY e.last_name, e.first_name");
                                                        $emp_query->bind_param("i", $project_id);
                                                        $emp_query->execute();
                                                        $result = $emp_query->get_result();
                                                        
                                                        while ($row = $result->fetch_assoc()) {
                                                            $proj_emps[] = $row;
                                                        }
                                                        $emp_query->close();
                                                    }
                                                    
                                                    if (count($proj_emps) > 0): 
                                                        $i = 1;
                                                        foreach ($proj_emps as $emp): 
                                                            $emp_cost = $emp['daily_rate'] * $project_days;
                                                            $emp_total += $emp['total'];
                                                    ?>
                                                    <tr>
                                                        <td><?php echo $i++; ?></td>
                                                        <td style="font-weight:bold;color:#222;">
                                                            <?php echo htmlspecialchars(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')); ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($emp['position'] ?? 'N/A'); ?></td>
                                                        <td><?php echo htmlspecialchars($emp['company_type'] ?? 'N/A'); ?></td>
                                                        <td>₱<?php echo number_format($emp['daily_rate'] ?? 0, 2); ?></td>
                                                        <td>
                                                            <input type="number" 
                                                                   class="form-control form-control-sm project-days-input" 
                                                                   value="<?php echo isset($emp['project_days']) ? $emp['project_days'] : '0'; ?>" 
                                                                   min="0" 
                                                                   data-record-id="<?php echo $emp['id']; ?>" 
                                                                   style="width: 80px;"
                                                                   onchange="updateEmployeeTotal(this)"
                                                                   onkeydown="if(event.key === 'Enter') { event.preventDefault(); this.blur(); }">
                                                        </td>
                                                        <td>₱<?php echo number_format($emp['total'] ?? 0, 2); ?></td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-danger remove-employee" 
                                                                    data-id="<?php echo $emp['id'] ?? ''; ?>"
                                                                    data-is-estimation="1">
                                                                <i class="fas fa-trash"></i> Remove
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <?php 
                                                        endforeach; 
                                                    else: 
                                                    ?>
                                                    <tr>
                                                        <td colspan="8" class="text-center">No employees added</td>
                                                    </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <th colspan="5" class="text-end">Grand Total</th>
                                                        <th colspan="2" style="font-weight:bold;color:#222;">₱<?php echo number_format($emp_total, 2); ?></th>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Overhead Costs Section -->
                                <div class="card mb-3 shadow-sm">
                                    <div class="card-header bg-success text-white d-flex align-items-center">
                                        <span class="flex-grow-1">Overhead Costs</span>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-bordered mb-0">
                                                <thead class="table-success">
                                                    <tr>
                                                        <th>No.</th>
                                                        <th>Name</th>
                                                        <th>Price (₱)</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="overheadCostsBody">
                                                    <?php
                                                    $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
                                                    $overhead_total = 0;
                                                    $counter = 1;
                                                    
                                                    // Hardcoded overhead cost names
                                                    // Note: "Profit" row removed as requested
                                                    $overhead_names = [
                                                        'Mobilization/Demobilization',
                                                        'Others',
                                                        'Misc. Items',
                                                        'Profit Overhead & Supervision',
                                                        'Accommodation (Food, Housing)',
                                                        'VAT'
                                                    ];
                                                    
                                                    // Get project-specific prices if project_id is valid
                                                    $project_prices = [];
                                                    if ($project_id > 0) {
                                                        $price_query = "SELECT id, name, price FROM overhead_costs WHERE project_id = ?";
                                                        $stmt = $con->prepare($price_query);
                                                        $stmt->bind_param("i", $project_id);
                                                        $stmt->execute();
                                                        $price_result = $stmt->get_result();
                                                        
                                                        while ($price_row = $price_result->fetch_assoc()) {
                                                            $project_prices[$price_row['name']] = $price_row['price'];
                                                        }
                                                        $stmt->close();
                                                    }
                                                    
                                                    // Display all overhead costs with project-specific prices or 0
                                                    foreach ($overhead_names as $index => $name) {
                                                        $price = isset($project_prices[$name]) ? $project_prices[$name] : 0;
                                                        $overhead_total += $price;
                                                        
                                                        echo '<tr data-name="' . htmlspecialchars($name) . '">';
                                                        echo '<td>' . $counter++ . '</td>';
                                                        echo '<td>' . htmlspecialchars($name) . '</td>';
                                                        $is_vat_row = ($name === 'VAT');
                                                        $readOnlyAttr = $is_vat_row ? ' readonly style="background-color:#f8f9fa; cursor:not-allowed;" title="VAT is automatically calculated as 12% of the project estimation"' : '';
                                                        echo '<td class="editable-price">';
                                                        echo '<input type="number" class="form-control form-control-sm price-input' . ($is_vat_row ? ' vat-price-input' : '') . '" value="' . $price . '" step="0.01" min="0" oninput="debouncedUpdateOverheadPrice(\'' . htmlspecialchars($name, ENT_QUOTES) . '\', this)" onchange="updateOverheadPrice(\'' . htmlspecialchars($name, ENT_QUOTES) . '\', this)" onkeydown="if(event.key === \'Enter\') { event.preventDefault(); this.blur(); }"' . $readOnlyAttr . '>';
                                                        echo '</td>';
                                                        echo '</tr>';
                                                    }
                                                    
                                                    if (empty($overhead_names)) {
                                                        echo '<tr><td colspan="3" class="text-center">No overhead costs found</td></tr>';
                                                    }
                                                    ?>
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <th colspan="2" class="text-end">Total Overhead Costs:</th>
                                                        <th id="overheadTotal">₱<?php echo number_format($overhead_total, 2); ?></th>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <script>
                                function updateOverheadPrice(id, inputElement) {
                                    const price = inputElement.value;
                                    const projectId = <?php echo isset($_GET['project_id']) ? intval($_GET['project_id']) : 0; ?>;
                                    
                                    // Don't do anything if price is not a valid number
                                    if (isNaN(price) || price === '') {
                                        return;
                                    }
                                    
                                    if (projectId === 0) {
                                        alert('Error: Project ID not found');
                                        return;
                                    }
                                    
                                    // Send AJAX request to update the price
                                    fetch('save_overhead_costs.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded',
                                        },
                                        body: 'name=' + encodeURIComponent(id) + '&price=' + price + '&project_id=' + projectId
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            // Update total if needed
                                            updateOverheadTotal();
                                        } else {
                                           
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                       
                                    });
                                }
                                // Debounced saver: updates UI immediately and saves after a short pause
                                const overheadDebounceTimers = {};
                                function debouncedUpdateOverheadPrice(id, inputElement) {
                                    // Update UI totals live
                                    updateOverheadTotal();
                                    // Debounce backend save per item name
                                    const key = String(id);
                                    if (overheadDebounceTimers[key]) {
                                        clearTimeout(overheadDebounceTimers[key]);
                                    }
                                    overheadDebounceTimers[key] = setTimeout(() => {
                                        updateOverheadPrice(id, inputElement);
                                    }, 500);
                                }
                                function updateOverheadTotal() {
                                    // Recalculate total (exclude VAT row)
                                    let total = 0;
                                    document.querySelectorAll('.price-input').forEach(input => {
                                        const row = input.closest('tr');
                                        const name = row ? row.getAttribute('data-name') : '';
                                        if (name && name.toLowerCase() !== 'vat') {
                                            const price = parseFloat(input.value) || 0;
                                            total += price;
                                        }
                                    });
                                    document.getElementById('overheadTotal').textContent = '₱' + total.toFixed(2);
                                    calculateVAT();
                                }
                                
                                function updateLaborBudgetStatus() {
                                    const laborTotalElement = document.getElementById('laborTotal');
                                    const laborBudgetElement = document.getElementById('laborBudget');
                                    const laborCard = laborTotalElement.closest('.card-body');
                                    
                                    if (!laborTotalElement || !laborBudgetElement) return;
                                    
                                    // Extract numeric values from the text (remove ₱ and commas)
                                    const laborTotalText = laborTotalElement.textContent.replace(/₱|,/g, '');
                                    const laborBudgetText = laborBudgetElement.textContent.replace(/₱|,/g, '');
                                    
                                    const laborTotal = parseFloat(laborTotalText) || 0;
                                    const laborBudget = parseFloat(laborBudgetText) || 0;
                                    
                                    // Remove existing status badge if any
                                    const existingBadge = laborCard.querySelector('.badge');
                                    if (existingBadge) {
                                        existingBadge.remove();
                                    }
                                    
                                    // Add over-budget warning if needed
                                    if (laborTotal > laborBudget) {
                                        const overBudgetAmount = laborTotal - laborBudget;
                                        const warningDiv = document.createElement('div');
                                        warningDiv.className = 'mt-2';
                                        warningDiv.innerHTML = `<span class="badge bg-danger">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            Over Budget by ₱${overBudgetAmount.toFixed(2)}
                                        </span>`;
                                        laborCard.appendChild(warningDiv);
                                        
                                        // Change card header to danger color
                                        const cardHeader = laborCard.closest('.card').querySelector('.card-header');
                                        cardHeader.className = 'card-header bg-danger text-white py-2';
                                    } else {
                                        // Reset card header to normal color
                                        const cardHeader = laborCard.closest('.card').querySelector('.card-header');
                                        cardHeader.className = 'card-header bg-info text-white py-2';
                                    }
                                }
                                
                                const FORECASTED_COST = <?php echo isset($forecasted_cost) ? (float)$forecasted_cost : 0; ?>;
                                let vatSaveTimer = null;
                                function scheduleVatSave(inputElement) {
                                    if (!inputElement) return;
                                    if (vatSaveTimer) {
                                        clearTimeout(vatSaveTimer);
                                    }
                                    vatSaveTimer = setTimeout(() => {
                                        updateOverheadPrice('VAT', inputElement);
                                    }, 400);
                                }

                                function formatCurrency(value) {
                                    const number = Number(value) || 0;
                                    return '₱' + number.toLocaleString('en-PH', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }

                                function updateBudgetSuggestion(totalProjectCost) {
                                    const budgetInput = document.getElementById('budgetAmount');
                                    if (!budgetInput) return;
                                    const minValue = parseFloat(totalProjectCost) || 0;
                                    const minValueFormatted = minValue.toFixed(2);
                                    budgetInput.dataset.minBudget = minValueFormatted;
                                    budgetInput.setAttribute('placeholder', minValueFormatted);
                                    if (budgetInput.min !== undefined) {
                                        budgetInput.setAttribute('min', minValueFormatted);
                                    }
                                    if (!budgetInput.readOnly && budgetInput.dataset.userEdited !== '1') {
                                        budgetInput.value = minValueFormatted;
                                    }
                                    const budgetMessage = document.getElementById('budgetMessage');
                                    if (budgetMessage) {
                                        budgetMessage.innerHTML = `Suggested budget based on Step 2 estimation: <strong>${formatCurrency(minValue)}</strong>`;
                                    }
                                }

                                function updateForecastWarning() {
                                    const warning = document.getElementById('forecastWarning');
                                    const totalEl = document.getElementById('totalProjectCost');
                                    const card = document.getElementById('totalProjectCard');
                                    if (!warning || !totalEl || !card) return;
                                    if (!FORECASTED_COST || FORECASTED_COST <= 0) {
                                        warning.classList.add('d-none');
                                        totalEl.classList.remove('over-forecast-text');
                                        card.classList.remove('over-forecast-card');
                                        return;
                                    }
                                    warning.classList.remove('d-none');
                                    const totalValue = parseFloat(totalEl.textContent.replace(/[^0-9.-]+/g, '')) || 0;
                                    if (totalValue > FORECASTED_COST) {
                                        warning.classList.add('show');
                                        totalEl.classList.add('over-forecast-text');
                                        card.classList.add('over-forecast-card');
                                        warning.setAttribute('title', `Forecasted Budget: ${formatCurrency(FORECASTED_COST)}`);
                                    } else {
                                        warning.classList.remove('show');
                                        totalEl.classList.remove('over-forecast-text');
                                        card.classList.remove('over-forecast-card');
                                    }
                                }

                                function calculateVAT() {
                                    // Calculate total project estimation (materials + labor budget + overhead excluding VAT)
                                    const materialsTotal = parseFloat(document.getElementById('materialsTotal').textContent.replace('₱', '').replace(/,/g, '')) || 0;
                                    const laborBudget = parseFloat(document.getElementById('laborBudget').textContent.replace('₱', '').replace(/,/g, '')) || 0;
    
                                    // Get all overhead costs except VAT
                                    let overheadTotal = 0;
                                    document.querySelectorAll('.price-input').forEach(input => {
                                        const row = input.closest('tr');
                                        const name = row.getAttribute('data-name');
                                        // Exclude VAT from overhead total calculation
                                        if (name !== 'VAT') {
                                            overheadTotal += parseFloat(input.value) || 0;
                                        }
                                    });
    
                                    // Calculate total project estimation (materials + labor budget + overhead excluding VAT)
                                    const totalProjectEstimation = materialsTotal + laborBudget + overheadTotal;
    
                                    // Calculate VAT as 12% of total project estimation
                                    const vatAmount = totalProjectEstimation * 0.12;
    
                                    // Update VAT input field
                                    const vatInput = document.querySelector('tr[data-name="VAT"] .price-input');
                                    if (vatInput) {
                                        vatInput.value = vatAmount.toFixed(2);
                                        scheduleVatSave(vatInput);
                                    }
                                    // Update VAT card and badge
                                    const vatSummaryEl = document.getElementById('vatSummary');
                                    if (vatSummaryEl) {
                                        vatSummaryEl.textContent = formatCurrency(vatAmount);
                                    }
                                    const vatBadgeEl = document.getElementById('vatBadge');
                                    if (vatBadgeEl) {
                                        vatBadgeEl.textContent = formatCurrency(vatAmount);
                                    }
                                    // Keep Overhead totals excluding VAT (already updated by updateOverheadTotal)
                                    const overheadBadgeExcl = document.getElementById('overheadBadge');
                                    if (overheadBadgeExcl) {
                                        overheadBadgeExcl.textContent = formatCurrency(overheadTotal);
                                    }
                                        
                                    // Update total project cost
                                    const totalProjectCost = totalProjectEstimation + vatAmount;
                                    const totalCostElement = document.getElementById('totalProjectCost');
                                    if (totalCostElement) {
                                        totalCostElement.textContent = formatCurrency(totalProjectCost);
                                    }
                                    updateForecastWarning();
                                    updateBudgetSuggestion(totalProjectCost);
                                    // Also mirror into Step 3 card if present
                                    const step3TotalEl = document.getElementById('step3TotalEstimation');
                                    if (step3TotalEl) {
                                        step3TotalEl.textContent = formatCurrency(totalProjectCost);
                                    }
                                }
                                
                                // Function to update employee total via AJAX
                                function updateEmployeeTotal(input) {
                                    const row = input.closest('tr');
                                    if (!row) return;
                                    
                                    const recordId = input.dataset.recordId;
                                    if (!recordId) {
                                        console.error('Record ID not found');
                                        return;
                                    }
                                    
                                    const projectDays = parseInt(input.value) || 0;
                                    
                                    // Show loading state
                                    const totalCell = row.querySelector('td:nth-child(7)');
                                    const originalTotal = totalCell.textContent;
                                    totalCell.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                                    
                                    // Send AJAX request to update project days and get new total
                                    fetch('update_employee_project_days.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded',
                                        },
                                        body: `record_id=${recordId}&project_days=${projectDays}`
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            // Update the total cell with the new value
                                            totalCell.textContent = '₱' + parseFloat(data.total).toFixed(2);
                                            
                                            // Update the grand total
                                            updateGrandTotal();
                                            // Refresh the page so all related totals and summaries are updated
                                            window.location.reload();
                                        } else {
                                            alert(data.message || 'Failed to update employee data');
                                            totalCell.textContent = originalTotal;
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                        alert('An error occurred while updating the employee data');
                                        totalCell.textContent = originalTotal;
                                    });
                                }
                                
                                // Function to update the grand total
                                function updateGrandTotal() {
                                    let grandTotal = 0;
                                    document.querySelectorAll('table tbody tr').forEach(row => {
                                        const totalCell = row.querySelector('td:nth-child(7)');
                                        if (totalCell) {
                                            const totalText = totalCell.textContent.replace(/[^0-9.-]+/g, '');
                                            grandTotal += parseFloat(totalText) || 0;
                                        }
                                    });
                                    
                                    const totalRow = document.querySelector('table tfoot tr:first-child th:last-child');
                                    if (totalRow) {
                                        totalRow.textContent = '₱' + grandTotal.toFixed(2);
                                    }
                                }
                                
                                // Add event listeners after the page loads
                                document.addEventListener('DOMContentLoaded', function() {
                                    // Handle Enter key press for price inputs and project days
                                    document.addEventListener('keydown', function(e) {
                                        if ((e.target.classList.contains('price-input') || e.target.classList.contains('project-days-input')) && e.key === 'Enter') {
                                            e.preventDefault();
                                            e.target.blur(); // This will trigger the onchange event
                                        }
                                    });
                                    
                                    // Handle project days input changes
                                    document.querySelectorAll('.project-days-input').forEach(input => {
                                        input.addEventListener('change', function() {
                                            updateEmployeeTotal(this);
                                        });
                                        
                                        // Also handle blur event in case the user clicks away
                                        input.addEventListener('blur', function() {
                                            if (this.value === '') {
                                                this.value = '0';
                                                updateEmployeeTotal(this);
                                            }
                                        });
                                    });
                                    
                                    const budgetInputField = document.getElementById('budgetAmount');
                                    if (budgetInputField) {
                                        budgetInputField.addEventListener('input', function() {
                                            this.dataset.userEdited = '1';
                                        });
                                    }
                                    
                                    // Prevent form submission when pressing Enter in inputs
                                    document.querySelectorAll('.price-input, .project-days-input').forEach(input => {
                                        input.addEventListener('keypress', function(e) {
                                            if (e.key === 'Enter') {
                                                e.preventDefault();
                                                return false;
                                            }
                                        });
                                        
                                        // For project days, update the total when changed
                                        if (input.classList.contains('project-days-input')) {
                                            input.addEventListener('change', function() {
                                                const row = this.closest('tr');
                                                if (row) {
                                                    const dailyRate = parseFloat(row.querySelector('td:nth-child(5)').textContent.replace('₱', '').replace(/,/g, '')) || 0;
                                                    const days = parseFloat(this.value) || 0;
                                                    const total = dailyRate * days;
                                                    const totalCell = row.querySelector('td:nth-child(7)');
                                                    if (totalCell) {
                                                        totalCell.textContent = '₱' + total.toFixed(2);
                                                        
                                                        // Update the grand total
                                                        let grandTotal = 0;
                                                        document.querySelectorAll('table tbody tr').forEach(row => {
                                                            const totalText = row.querySelector('td:nth-child(7)')?.textContent || '₱0';
                                                            grandTotal += parseFloat(totalText.replace('₱', '').replace(/,/g, '')) || 0;
                                                        });
                                                        
                                                        const totalRow = document.querySelector('table tfoot tr:first-child th:last-child');
                                                        if (totalRow) {
                                                            totalRow.textContent = '₱' + grandTotal.toFixed(2);
                                                        }
                                                    }
                                                }
                                            });
                                        }
                                    });
                                    
                                    // Initialize VAT calculation on page load
                                    setTimeout(function() {
                                        // Recompute overhead (ex-VAT) first, then VAT
                                        if (typeof updateOverheadTotal === 'function') {
                                            updateOverheadTotal();
                                        }
                                        calculateVAT();
                                        updateForecastWarning();
                                        // Update labor budget status
                                        if (typeof updateLaborBudgetStatus === 'function') {
                                            updateLaborBudgetStatus();
                                        }
                                        // Initial sync of Step 3 total from Step 2 card if available
                                        const totalCostElement = document.getElementById('totalProjectCost');
                                        const step3TotalEl = document.getElementById('step3TotalEstimation');
                                        if (totalCostElement && step3TotalEl) {
                                            step3TotalEl.textContent = totalCostElement.textContent.trim();
                                        }
                                        
                                        // Make VAT input field read-only and style it
                                        const vatInput = document.querySelector('tr[data-name="VAT"] .price-input');
                                        if (vatInput) {
                                            vatInput.setAttribute('readonly', true);
                                            vatInput.style.backgroundColor = '#f8f9fa';
                                            vatInput.style.cursor = 'not-allowed';
                                            vatInput.title = 'VAT is automatically calculated as 12% of total project estimation';
                                        }
                                        
                                        // Set up MutationObserver to monitor laborBudget changes
                                        const laborBudgetElement = document.getElementById('laborBudget');
                                        if (laborBudgetElement) {
                                            const observer = new MutationObserver(function(mutations) {
                                                mutations.forEach(function(mutation) {
                                                    if (mutation.type === 'childList' || mutation.type === 'characterData') {
                                                        // Labor budget has changed, recalculate VAT
                                                        calculateVAT();
                                                    }
                                                });
                                            });
                                            
                                            // Configure the observer to watch for changes to the content
                                            observer.observe(laborBudgetElement, {
                                                childList: true,    // Watch for addition/removal of child nodes
                                                characterData: true, // Watch for changes to text content
                                                subtree: true       // Watch all descendants
                                            });
                                        }
                                        
                                        // Also observe materialsTotal changes
                                        const materialsTotalElement = document.getElementById('materialsTotal');
                                        if (materialsTotalElement) {
                                            const matObserver = new MutationObserver(function(mutations) {
                                                mutations.forEach(function(mutation) {
                                                    if (mutation.type === 'childList' || mutation.type === 'characterData') {
                                                        calculateVAT();
                                                    }
                                                });
                                            });
                                            matObserver.observe(materialsTotalElement, {
                                                childList: true,
                                                characterData: true,
                                                subtree: true
                                            });
                                        }
                                        
                                        // Also observe laborTotal changes
                                        const laborTotalElement = document.getElementById('laborTotal');
                                        if (laborTotalElement) {
                                            const laborObserver = new MutationObserver(function(mutations) {
                                                mutations.forEach(function(mutation) {
                                                    if (mutation.type === 'childList' || mutation.type === 'characterData') {
                                                        // Labor total has changed, update status and recalculate VAT
                                                        updateLaborBudgetStatus();
                                                        calculateVAT();
                                                    }
                                                });
                                            });
                                            laborObserver.observe(laborTotalElement, {
                                                childList: true,
                                                characterData: true,
                                                subtree: true
                                            });
                                        }
                                    }, 500); // Small delay to ensure all elements are loaded
                                });
                                </script>
                                
                                <!-- Navigation Buttons -->
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-secondary prev-step" data-prev="1">
                                        <i class="fas fa-arrow-left me-1"></i> Previous
                                    </button>
                                    <div class="d-flex align-items-center">
                                        <button type="button" class="btn btn-outline-primary me-2 d-flex align-items-center" id="exportCostEstimationBtn" 
                                                style="border-color: #0d6efd; font-weight: 500;">
                                            <i class="fas fa-file-pdf me-1 text-danger"></i> 
                                            <span>Save & Export PDF</span>
                                        </button>
                                        <button type="button" class="btn btn-primary next-step d-flex align-items-center" data-next="3">
                                            <span>Next</span>
                                            <i class="fas fa-arrow-right ms-1"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="step-content d-none" id="step3">
                                <h4 class="mb-4 fw-bold text-success">Step 3: Initial Budget Request</h4>
                                <!-- Total Project Estimation (from Step 2) -->
                                <div class="row g-3 mb-3">
                                    <div class="col-12">
                                        <div class="card border-0 shadow-sm bg-gradient-primary text-white">
                                            <div class="card-body d-flex align-items-center justify-content-between p-3">
                                                <h5 class="mb-0 d-flex align-items-center">
                                                    <i class="fas fa-calculator me-2"></i>
                                                    <?php 
                                                                // Fetch total_estimation_cost from projects table
                                                                $total_estimation_cost = 0;
                                                                $stmt = $con->prepare("SELECT total_estimation_cost FROM projects WHERE project_id = ?");
                                                                $stmt->bind_param('i', $project_id);
                                                                $stmt->execute();
                                                                $stmt->bind_result($total_estimation_cost);
                                                                $stmt->fetch();
                                                                $stmt->close();
                                                            
                                                                ?>
                                                    Total Project Estimation (Step 2)
                                                </h5>
                                                <h2 class="fw-bold mb-0">₱<?php echo number_format($total_estimation_cost ?? 0, 2); ?></h2>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                   <?php
                                    // Get latest budget input (whether pending or approved)
                                    $budget_val = "";
                                    $pending = false;
                                    $approved = false;
                                    $sql = "SELECT budget, status FROM project_budget_approval WHERE project_id = ? ORDER BY id DESC LIMIT 1";
                                    $stmt = $con->prepare($sql);
                                    $stmt->bind_param("i", $project_id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($row = $result->fetch_assoc()) {
                                        $budget_val = $row['budget'];
                                        if ($row['status'] === "Pending") {
                                            $pending = true;
                                        }
                                        if ($row['status'] === "Approved") {
                                            $approved = true;
                                        }
                                    }
                                    $stmt->close();
                                    ?>
                                    <div class="row g-4">
                                        <!-- Budget Approval Column -->
                                        <div class="col-lg-6">
                                            <form id="budgetForm" autocomplete="off" class="h-100">
                                                <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project_id); ?>">
                                                <div class="card shadow-sm h-100">
                                                    <div class="card-header bg-light">
                                                        <h5 class="card-title text-success mb-0">
                                                            <i class="fas fa-money-bill-wave me-2"></i>Budget Approval
                                                        </h5>
                                                    </div>
                                                    <div class="card-body d-flex flex-column">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Budget Amount (₱)</label>
                                                            <div class="input-group mb-2">
                                                                <span class="input-group-text">₱</span>
                                                                <?php 
                                                                // Fetch total_estimation_cost from projects table
                                                                $total_estimation_cost = 0;
                                                                $stmt = $con->prepare("SELECT total_estimation_cost FROM projects WHERE project_id = ?");
                                                                $stmt->bind_param('i', $project_id);
                                                                $stmt->execute();
                                                                $stmt->bind_result($total_estimation_cost);
                                                                $stmt->fetch();
                                                                $stmt->close();
                                                                
                                                                $min_budget = max(0, (float)$total_estimation_cost);
                                                                $min_budget_value = number_format($min_budget, 2, '.', '');
                                                                $min_budget_display = number_format($min_budget, 2);
                                                                ?>
                                                                <input type="text" 
                                                                    class="form-control form-control-lg" 
                                                                    name="budget" 
                                                                    id="budgetAmount" 
                                                                    placeholder="<?php echo $min_budget_value; ?>" 
                                                                    inputmode="numeric" 
                                                                    aria-describedby="budgetMessage"
                                                                    data-min-budget="<?php echo $min_budget_value; ?>" 
                                                                    value="<?php echo $min_budget_value; ?>" 
                                                                    <?php if ($pending || $approved) echo 'readonly'; ?>>
                                                            </div>
                                                            <div class="form-text text-muted" id="budgetMessage">
                                                                Suggested budget based on Step 2 estimation: <strong>₱<?php echo $min_budget_display; ?></strong>
                                                            </div>
                                                            <div class="invalid-feedback d-block text-danger" id="budgetError" style="display:none;"></div>
                                                        </div>
                                                    
                                                        <div class="mt-auto text-center">
                                                            <button type="button" id="requestBudgetBtn" class="btn btn-primary w-100"
                                                                <?php if ($pending || $approved) echo 'disabled'; ?>>
                                                                <?php
                                                                if ($pending) {
                                                                    echo '<i class="fas fa-hourglass-half me-1"></i> Waiting for Approval';
                                                                } elseif ($approved) {
                                                                    echo '<i class="fas fa-check-circle me-1"></i> Approved';
                                                                } else {
                                                                    echo '<i class="fas fa-exclamation-triangle me-1"></i> Request Budget Approval';
                                                                }
                                                                ?>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                        <!-- Payment Verification Column -->
                                        <div class="col-lg-6">
                                            <div class="card shadow-sm h-100">
                                                <div class="card-header bg-light">
                                                    <h5 class="card-title text-success mb-0">
                                                        <i class="fas fa-money-bill-wave me-2"></i>Payment Verification
                                                    </h5>
                                                </div>
                                                <div class="card-body d-flex flex-column">
                                                    <!-- Payment Details -->
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <div class="d-flex justify-content-between mb-2">
                                                                <span class="text-muted">Payment Type:</span>
                                                                <span class="fw-bold payment-type">N/A</span>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-2">
                                                                <span class="text-muted">Status:</span>
                                                                <span class="badge bg-secondary payment-status">Pending</span>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="d-flex justify-content-between mb-2">
                                                                <span class="text-muted">Uploaded On:</span>
                                                                <span class="text-muted upload-date">-</span>
                                                            </div>
                                                            <div class="d-flex justify-content-between">
                                                                <span class="text-muted">Amount:</span>
                                                                <span class="fw-bold payment-amount">₱0.00</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Image Viewer Box -->
                                                    <div class="border rounded p-3 text-center mb-3 flex-grow-1 d-flex align-items-center justify-content-center" style="background-color: #f8f9fa; min-height: 200px;">
                                                        <div id="paymentImageViewer" class="w-100">
                                                            <div class="d-flex flex-column align-items-center justify-content-center h-100">
                                                                <i class="fas fa-image fa-3x text-muted mb-3"></i>
                                                                <p class="text-muted mb-0">No payment proof available</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                                        <div>
                                                            <span class="fw-bold">Verification</span>
                                                        </div>
                                                        <div id="actionButtons">
                                                            <button type="button" class="btn btn-success" id="verifyPaymentBtn">
                                                                <i class="fas fa-check-circle me-2"></i>Verify Payment
                                                            </button>
                                                            <button type="button" class="btn btn-danger" id="rejectPaymentBtn">
                                                                <i class="fas fa-times-circle me-2"></i>Reject
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
<!-- End of Two Column Layout -->
                                        <div class="alert alert-info d-flex align-items-center mb-4 mt-4">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <span>
                                                Please upload the budget documents and specify the budget amount below.
                                                <?php if ($approved): ?>
                                                    The budget has been <strong>approved</strong>. You may proceed to the next step.
                                                <?php else: ?>
                                                    The next button will be enabled only after the budget is approved.
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    <div class="d-flex justify-content-between mt-4">
                                        <button type="button" class="btn btn-secondary prev-step" data-prev="2">
                                            <i class="fas fa-arrow-left me-1"></i> Previous
                                        </button>
                                        <button type="button" class="btn btn-primary next-step" data-next="4" id="nextBudgetStepBtn"
                                            <?php if (!$approved) echo 'disabled'; ?>>
                                            Next <i class="fas fa-arrow-right"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="step-content d-none" id="step4">
                                <h4 class="mb-4">Step 4: Contract Signing</h4>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Please upload the required contract PDFs for signing.
                                </div>
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title mb-4">Upload Contracts</h5>
                                        <input type="hidden" id="projectIdInput" value="<?php echo isset($project_id) ? $project_id : ''; ?>">
                                        <div class="row g-4">
                                            <!-- Original Contract -->
                                            <div class="col-md-4">
                                                <div class="card h-100">
                                                    <div class="card-body d-flex flex-column align-items-center justify-content-center p-4 text-center" style="min-height: 200px;">
                                                        <i class="fas fa-file-contract fa-3x text-primary mb-3"></i>
                                                        <h6 class="mb-3">Generate Contract</h6>
                                                        <button type="button" class="btn btn-primary" id="generateContractBtn">
                                                            <i class="fas fa-magic me-1"></i> Generate Contract
                                                        </button>
                                                    </div>
                                                    <div class="card-footer bg-transparent border-top-0 pt-0 text-center">
                                                        <small class="text-muted">Click to generate the contract document</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Your Contract -->
                                            <div class="col-md-4">
                                                <div class="card h-100 border-2 border-dashed" id="yourDropZone">
                                                    <div class="card-body d-flex flex-column align-items-center justify-content-center p-4 text-center" style="min-height: 200px;">
                                                        <i class="fas fa-signature fa-3x text-success mb-3"></i>
                                                        <h6 class="mb-2">Your Signed Contract</h6>
                                                        <p class="small text-muted mb-3">Drag & drop your PDF here</p>
                                                        <p class="small text-muted mb-0">or</p>
                                                        <button type="button" class="btn btn-sm btn-outline-success mt-2" id="browseYourBtn">
                                                            <i class="fas fa-folder-open me-1"></i> Browse Files
                                                        </button>
                                                        <input type="file" class="d-none" id="yourContract" name="your_contract" accept=".pdf">
                                                    </div>
                                                    <div class="card-footer bg-transparent border-top-0 pt-0">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small class="text-muted file-info" id="yourFileInfo">No file selected</small>
                                                            <div class="btn-group">
                                                                <button type="button" class="btn btn-sm btn-outline-success" id="uploadYourBtn" disabled>
                                                                    <i class="fas fa-upload me-1"></i> Upload
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-outline-info" id="viewYourBtn" disabled>
                                                                    <i class="fas fa-eye"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="progress mt-2" style="height: 5px; display: none;" id="yourProgress">
                                                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Client Contract -->
                                            <div class="col-md-4">
                                                <div class="card h-100 border-2 border-dashed" id="clientDropZone">
                                                    <div class="card-body d-flex flex-column align-items-center justify-content-center p-4 text-center" style="min-height: 200px;">
                                                        <i class="fas fa-user-check fa-3x text-info mb-3"></i>
                                                        <h6 class="mb-2">Client Signed Contract</h6>
                                                        <p class="small text-muted mb-3">Drag & drop your PDF here</p>
                                                        <p class="small text-muted mb-0">or</p>
                                                        <button type="button" class="btn btn-sm btn-outline-info mt-2" id="browseClientBtn">
                                                            <i class="fas fa-folder-open me-1"></i> Browse Files
                                                        </button>
                                                        <input type="file" class="d-none" id="clientContract" name="client_contract" accept=".pdf">
                                                    </div>
                                                    <div class="card-footer bg-transparent border-top-0 pt-0">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small class="text-muted file-info" id="clientFileInfo">No file selected</small>
                                                            <div class="btn-group">
                                                                <button type="button" class="btn btn-sm btn-outline-info" id="uploadClientBtn" disabled>
                                                                    <i class="fas fa-upload me-1"></i> Upload
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-outline-primary" id="viewClientContractBtn" disabled>
                                                                    <i class="fas fa-eye"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="progress mt-2" style="height: 5px; display: none;" id="clientProgress">
                                                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div id="contractAlert" class="alert alert-warning mt-3 d-none">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Please upload Your Signed Contract and the Client Signed Contract to proceed.
                                </div>
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-secondary prev-step" data-prev="3">
                                        <i class="fas fa-arrow-left me-1"></i> Previous
                                    </button>
                                    <button type="button" class="btn btn-primary next-step" data-next="5">
                                        Next <i class="fas fa-arrow-right ms-1"></i>
                                    </button>
                                </div>
                             
                            </div>
                            <div class="step-content d-none" id="step5">
                                <h4 class="mb-4">Step 5: Permits</h4>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Please upload the required permits below. You can drag and drop files or click to browse.
                                </div>
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title mb-4">Upload Permits</h5>
                                        <input type="hidden" id="projectIdInputPermits" value="<?php echo $project_id; ?>">
                                        <div class="row g-4">
                                            <!-- LGU Clearance -->
                                            <div class="col-md-4">
                                                <div class="card h-100 border-2 border-dashed" id="lguDropZone">
                                                    <div class="card-body d-flex flex-column align-items-center justify-content-center p-4 text-center" style="min-height: 200px;">
                                                        <i class="fas fa-landmark fa-3x text-primary mb-3"></i>
                                                        <h6 class="mb-2">LGU Clearance <span class="badge bg-danger ms-1">Required</span></h6>
                                                        <p class="small text-muted mb-3">Drag & drop your file here</p>
                                                        <p class="small text-muted mb-0">or</p>
                                                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="browseLguBtn">
                                                            <i class="fas fa-folder-open me-1"></i> Browse Files
                                                        </button>
                                                        <input type="file" class="d-none" id="lguClearance" name="lgu_clearance" accept=".pdf,image/*">
                                                    </div>
                                                    <div class="card-footer bg-transparent border-top-0 pt-0">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small class="text-muted file-info" id="lguFileInfo">No file selected</small>
                                                            <div class="btn-group">
                                                                <button type="button" class="btn btn-sm btn-outline-primary" id="uploadLguBtn" disabled>
                                                                    <i class="fas fa-upload me-1"></i> Upload
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-outline-info" id="viewLguBtn" disabled>
                                                                    <i class="fas fa-eye"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="progress mt-2" style="height: 5px; display: none;" id="lguProgress">
                                                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Fire Permit -->
                                            <div class="col-md-4">
                                                <div class="card h-100 border-2 border-dashed" id="fireDropZone">
                                                    <div class="card-body d-flex flex-column align-items-center justify-content-center p-4 text-center" style="min-height: 200px;">
                                                        <i class="fas fa-fire-extinguisher fa-3x text-danger mb-3"></i>
                                                        <h6 class="mb-2">Fire Permit</h6>
                                                        <p class="small text-muted mb-3">Drag & drop your file here</p>
                                                        <p class="small text-muted mb-0">or</p>
                                                        <button type="button" class="btn btn-sm btn-outline-danger mt-2" id="browseFireBtn">
                                                            <i class="fas fa-folder-open me-1"></i> Browse Files
                                                        </button>
                                                        <input type="file" class="d-none" id="firePermit" name="fire_permit" accept=".pdf,image/*">
                                                    </div>
                                                    <div class="card-footer bg-transparent border-top-0 pt-0">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small class="text-muted file-info" id="fireFileInfo">No file selected</small>
                                                            <div class="btn-group">
                                                                <button type="button" class="btn btn-sm btn-outline-danger" id="uploadFireBtn" disabled>
                                                                    <i class="fas fa-upload me-1"></i> Upload
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-outline-info" id="viewFireBtn" disabled>
                                                                    <i class="fas fa-eye"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="progress mt-2" style="height: 5px; display: none;" id="fireProgress">
                                                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Zoning Clearance -->
                                            <div class="col-md-4">
                                                <div class="card h-100 border-2 border-dashed" id="zoningDropZone">
                                                    <div class="card-body d-flex flex-column align-items-center justify-content-center p-4 text-center" style="min-height: 200px;">
                                                        <i class="fas fa-map-marked-alt fa-3x text-warning mb-3"></i>
                                                        <h6 class="mb-2">Zoning Clearance</h6>
                                                        <p class="small text-muted mb-3">Drag & drop your file here</p>
                                                        <p class="small text-muted mb-0">or</p>
                                                        <button type="button" class="btn btn-sm btn-outline-warning mt-2" id="browseZoningBtn">
                                                            <i class="fas fa-folder-open me-1"></i> Browse Files
                                                        </button>
                                                        <input type="file" class="d-none" id="zoningClearance" name="zoning_clearance" accept=".pdf,image/*">
                                                    </div>
                                                    <div class="card-footer bg-transparent border-top-0 pt-0">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small class="text-muted file-info" id="zoningFileInfo">No file selected</small>
                                                            <div class="btn-group">
                                                                <button type="button" class="btn btn-sm btn-outline-warning" id="uploadZoningBtn" disabled>
                                                                    <i class="fas fa-upload me-1"></i> Upload
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-outline-info" id="viewZoningBtn" disabled>
                                                                    <i class="fas fa-eye"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="progress mt-2" style="height: 5px; display: none;" id="zoningProgress">
                                                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Occupancy Permit -->
                                            <div class="col-md-4">
                                                <div class="card h-100 border-2 border-dashed" id="occupancyDropZone">
                                                    <div class="card-body d-flex flex-column align-items-center justify-content-center p-4 text-center" style="min-height: 200px;">
                                                        <i class="fas fa-building fa-3x text-success mb-3"></i>
                                                        <h6 class="mb-2">Occupancy Permit</h6>
                                                        <p class="small text-muted mb-3">Drag & drop your file here</p>
                                                        <p class="small text-muted mb-0">or</p>
                                                        <button type="button" class="btn btn-sm btn-outline-success mt-2" id="browseOccupancyBtn">
                                                            <i class="fas fa-folder-open me-1"></i> Browse Files
                                                        </button>
                                                        <input type="file" class="d-none" id="occupancyPermit" name="occupancy_permit" accept=".pdf,image/*">
                                                    </div>
                                                    <div class="card-footer bg-transparent border-top-0 pt-0">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small class="text-muted file-info" id="occupancyFileInfo">No file selected</small>
                                                            <div class="btn-group">
                                                                <button type="button" class="btn btn-sm btn-outline-success" id="uploadOccupancyBtn" disabled>
                                                                    <i class="fas fa-upload me-1"></i> Upload
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-outline-info" id="viewOccupancyBtn" disabled>
                                                                    <i class="fas fa-eye"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="progress mt-2" style="height: 5px; display: none;" id="occupancyProgress">
                                                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Barangay Clearance -->
                                            <div class="col-md-4">
                                                <div class="card h-100 border-2 border-dashed" id="barangayDropZone">
                                                    <div class="card-body d-flex flex-column align-items-center justify-content-center p-4 text-center" style="min-height: 200px;">
                                                        <i class="fas fa-home fa-3x text-info mb-3"></i>
                                                        <h6 class="mb-2">Barangay Clearance</h6>
                                                        <p class="small text-muted mb-3">Drag & drop your file here</p>
                                                        <p class="small text-muted mb-0">or</p>
                                                        <button type="button" class="btn btn-sm btn-outline-info mt-2" id="browseBarangayBtn">
                                                            <i class="fas fa-folder-open me-1"></i> Browse Files
                                                        </button>
                                                        <input type="file" class="d-none" id="barangayClearance" name="barangay_clearance" accept=".pdf,image/*">
                                                    </div>
                                                    <div class="card-footer bg-transparent border-top-0 pt-0">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small class="text-muted file-info" id="barangayFileInfo">No file selected</small>
                                                            <div class="btn-group">
                                                                <button type="button" class="btn btn-sm btn-outline-info" id="uploadBarangayBtn" disabled>
                                                                    <i class="fas fa-upload me-1"></i> Upload
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-outline-primary" id="viewBarangayBtn" disabled>
                                                                    <i class="fas fa-eye"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="progress mt-2" style="height: 5px; display: none;" id="barangayProgress">
                                                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div id="permitsAlert" class="alert alert-warning mt-3 d-none">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Please upload all required permits to proceed.
                                </div>
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-secondary prev-step" data-prev="4">
                                        <i class="fas fa-arrow-left me-1"></i> Previous
                                    </button>
                                    <button type="button" class="btn btn-primary next-step" data-next="6">
                                        Next <i class="fas fa-arrow-right ms-1"></i>
                                    </button>
                                </div>
                            </div>
                            <!-- Step 6: Schedule -->
                            <div class="step-content d-none" id="step6">
                                <h4 class="mb-4">Step 6: Schedule</h4>
                                <div class="alert alert-success d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-info-circle me-2"></i>
                                        Schedule of the project
                                    </div>
                                </div>
                                <div class="row">
                                    <!-- Project Timeline Row (Moved to top) -->
                                    <div class="col-12 mb-4">
                                        <div class="card">
                                            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                                                <h5 class="card-title mb-0"><i class="fas fa-tasks me-2"></i>Project Timeline</h5>
                                                <div>
                                                    <button type="button" class="btn btn-light btn-sm me-2" data-bs-toggle="modal" data-bs-target="#addDivisionModal">
                                                        <i class="fas fa-plus me-1"></i> Add Task
                                                    </button>
                                                    <form method="post" style="display: inline;">
                                                        <button type="submit" name="create_standard_phases" class="btn btn-info btn-sm me-2">
                                                            <i class="fas fa-magic me-1"></i> Auto-Create Phases
                                                        </button>
                                                    </form>
                                                    <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                                                        <i class="fas fa-plus me-1"></i> Add Time Schedule
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="card-body p-3">
                                                <div class="table-responsive">
                                                    <table class="table table-hover mb-0" id="scheduleTable">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>#</th>
                                                                <th>Task Name</th>
                                                                <th>Start Date</th>
                                                                <th>End Date</th>
                                                                <th>Status</th>
                                                                <th>Progress</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="timelineTableBody">
                                                            <tr>
                                                                <td colspan="7" class="text-center py-4">
                                                                    <div class="spinner-border text-primary" role="status">
                                                                        <span class="visually-hidden">Loading...</span>
                                                                    </div>
                                                                    <p class="mt-2">Loading schedule items...</p>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Gantt Chart Row (Moved below Project Timeline) -->
                                    <div class="col-12 mb-4">
                                        <div class="card">
                                            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                                                <h5 class="card-title mb-0"><i class="fas fa-project-diagram me-2"></i>Project Gantt Chart</h5>
                                            </div>
                                            <div class="card-body">
                                                <?php
                                                // Get current year and months for the Gantt chart
                                                $currentYear = date('Y');
                                                $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                                                
                                                // Function to get month index (0-11) from a date string
                                                function getMonthIndex($date) {
                                                    return (int)date('n', strtotime($date)) - 1;
                                                }
                                                
                                                // Function to get year from a date string
                                                function getYearOf($date) {
                                                    return (int)date('Y', strtotime($date));
                                                }
                                                
                                                // Fetch schedule items for the current project
                                                $schedule_items = [];
                                                if (isset($project_id) && $project_id) {
                                                    $query = "SELECT * FROM project_timeline WHERE project_id = ? ORDER BY start_date ASC";
                                                    $stmt = $con->prepare($query);
                                                    $stmt->bind_param("i", $project_id);
                                                    $stmt->execute();
                                                    $result = $stmt->get_result();
                                                    
                                                    while ($row = $result->fetch_assoc()) {
                                                        $schedule_items[] = [
                                                            'task_name' => $row['task_name'],
                                                            'start_date' => $row['start_date'],
                                                            'end_date' => $row['end_date'],
                                                            'status' => $row['status'] ?? 'Not Started'
                                                        ];
                                                    }
                                                }
                                                ?>
                                                <div class="table-responsive">
                                                    <table class="table table-bordered align-middle text-center" id="ganttTable" style="min-width: 900px;">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th style="width: 220px;">Task</th>
                                                                <?php foreach ($months as $month): ?>
                                                                    <th style="width: 56px;"><?php echo $month; ?></th>
                                                                <?php endforeach; ?>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if (empty($schedule_items)): ?>
                                                                <tr>
                                                                    <td colspan="13" class="text-center py-3">
                                                                        <div class="alert alert-info mb-0">
                                                                            <i class="fas fa-info-circle me-2"></i> No schedule items found. Add tasks to see them in the Gantt chart.
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php else: ?>
                                                                <?php foreach ($schedule_items as $item): ?>
                                                                    <?php 
                                                                    if (empty($item['start_date']) || empty($item['end_date'])) continue;
                                                                    
                                                                    $startIdx = getMonthIndex($item['start_date']);
                                                                    $endIdx = getMonthIndex($item['end_date']);
                                                                    $startYear = getYearOf($item['start_date']);
                                                                    $endYear = getYearOf($item['end_date']);
                                                                        
                                                                    // Calculate bar start and end for the current year
                                                                    $barStart = ($startYear < $currentYear) ? 0 : $startIdx;
                                                                    $barEnd = ($endYear > $currentYear) ? 11 : $endIdx;
                                                                    
                                                                    // Use consistent green color for all tasks
                                                                    $statusColor = '#198754'; // Green color
                                                                    ?>
                                                                    <tr>
                                                                        <td class="text-start fw-bold"><?php echo htmlspecialchars($item['task_name']); ?></td>
                                                                        <?php 
                                                                        // Left empty cells
                                                                        for ($i = 0; $i < $barStart; $i++) { 
                                                                            echo '<td></td>';
                                                                        }
                                                                        
                                                                        // Bar cell
                                                                        $colspan = $barEnd - $barStart + 1;
                                                                        echo '<td colspan="' . $colspan . '" style="padding:0;vertical-align:middle;">';
                                                                        echo '<div style="height:32px;background:' . $statusColor . ';border-radius:4px;width:100%;position:relative;display:flex;align-items:center;justify-content:center;">';
                                                                        $start_fmt = date('m-d-Y', strtotime($item['start_date']));
                                                                        $end_fmt = date('m-d-Y', strtotime($item['end_date']));
                                                                        echo '<span style="color:#fff;font-size:0.6em;font-weight:bold;text-shadow:0 1px 2px #0008;">' . $start_fmt . ' to ' . $end_fmt . '</span>';
                                                                        echo '</div>';
                                                                        echo '</td>';
                                                                        
                                                                        // Right empty cells
                                                                        for ($i = $barEnd + 1; $i < 12; $i++) { 
                                                                            echo '<td></td>';
                                                                        }
                                                                        ?>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-secondary prev-step" data-prev="5"><i class="fas fa-arrow-left me-1"></i>Previous</button>
                                    <button type="button" class="btn btn-primary next-step" data-next="7">Next <i class="fas fa-arrow-right ms-1"></i></button>
                                </div>
                            </div>
                            <div class="step-content d-none" id="step7">
                                <h4 class="mb-4">Step 7: Actual</h4>
                                <div class="container-fluid px-4 py-4">
                                        <div class="row justify-content-center">
                                            <div class="col-12 col-md-8 col-lg-6">
                                                    <?php if (
                                                        $project_status === 'Ongoing' ||
                                                        $project_status === 'Overdue' ||
                                                        $project_status === 'Overdue Finished' ||
                                                        $project_status === 'Finished' ||
                                                        $project_status === 'Cancelled'
                                                    ): ?>
                                                    <div class="card shadow-sm">
                                                        <div class="card-header bg-info text-white">
                                                            <h4 class="mb-0">Project Progress</h4>
                                                        </div>
                                                        <div class="card-body text-center py-5">
                                                            <div class="mb-4">
                                                                <i class="fas fa-chart-line fa-4x text-info mb-3"></i>
                                                                <h3>Track your progress!</h3>
                                                                <p class="text-muted">Below is the current progress of this project. Click the button to view and manage full project details.</p>
                                                            </div>
                                                            <div class="mb-4">
                                                                <!-- Progress bar -->
                                                                <div class="progress" style="height: 30px;">
                                                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-info"
                                                                        role="progressbar"
                                                                        style="width: <?= $progress_percent ?? 0 ?>%; font-size:1.2rem;"
                                                                        aria-valuenow="<?= $progress_percent ?? 0 ?>"
                                                                        aria-valuemin="0" aria-valuemax="100">
                                                                        <?= round($progress_percent ?? 0) ?>%
                                                                    </div>
                                                                    <div class="text-muted small mt-2">
                                                                        <?= $completed_tasks ?? 0 ?> of <?= $total_tasks ?? 0 ?> tasks completed
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="d-flex justify-content-center gap-3 mt-4">
                                                                <a href="project_actual.php?id=<?= $project_id ?>" class="btn btn-info text-white">
                                                                    <i class="fas fa-arrow-right me-2"></i> Go to Project
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- Show "Ready to start actual project work" card for Pending/other status -->
                                                    <div class="card shadow-sm">
                                                        <div class="card-header bg-success text-white">
                                                            <h4 class="mb-0">Project Actual</h4>
                                                        </div>
                                                        <div class="card-body text-center py-5">
                                                            <div class="mb-4">
                                                                <i class="fas fa-tasks fa-4x text-primary mb-3"></i>
                                                                <h3>Ready to start actual project work?</h3>
                                                                <p class="text-muted">Click the button below to go to the actual project page where you can track and manage your project's progress.</p>
                                                            </div>
                                                            <div class="d-flex justify-content-center gap-3 mt-4">
                                                                <button type="button" id="startProjectBtn" class="btn btn-primary">
                                                                    <i class="fas fa-play me-2"></i> Go to Actual Project
                                                                </button>
                                                            </div>
                                                            <div class="modal fade" id="startProjectModal" tabindex="-1" 
                                                                aria-labelledby="startProjectModalLabel" aria-hidden="true">
                                                                <div class="modal-dialog modal-dialog-centered">
                                                                    <div class="modal-content">
                                                                        
                                                                        <div class="modal-header text-dark" id="modalHeader">
                                                                            <h5 class="modal-title" id="startProjectModalLabel">
                                                                                Confirm Project Start
                                                                            </h5>
                                                                            <button type="button" class="btn-close" 
                                                                                    data-bs-dismiss="modal" aria-label="Close"></button>
                                                                        </div>
                                                                        
                                                                        <div class="modal-body">
                                                                            <div id="startDateWarning" class="alert">
                                                                                <i class="fas fa-calendar-day me-2"></i>
                                                                                <span id="dateMessage"></span>
                                                                            </div>
                                                                            <p class="mb-0">
                                                                                Starting the project will mark it as 
                                                                                <span class="fw-bold">'Ongoing'</span> 
                                                                                and update the start date to today's date.
                                                                            </p>
                                                                        </div>
                                                                        
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary" 
                                                                                    data-bs-dismiss="modal">Cancel</button>
                                                                            <button type="button" id="confirmStartProject" 
                                                                                    class="btn btn-primary">Yes, Start Project</button>
                                                                        </div>
                                                                        
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-secondary prev-step" data-prev="6">Previous</button>
                                    <button type="button" class="btn btn-primary next-step" data-next="8">Next</button>
                                </div>
                            </div>
                            <div class="step-content d-none" id="step8">
                                <h4 class="mb-4 fw-bold text-success">Step 8: Billing & Payment Management
                                    <?php if ($budget_percentage >= 100): ?>
                                        <span class="badge bg-success ms-2">Completed</span>
                                    <?php endif; ?>
                                </h4>

                                <div class="row g-4">
                                    <!-- Budget Request / Completed Card -->
                                    <div class="col-md-4">
                                        <?php if ($budget_percentage >= 100): ?>
                                            <div class="card mb-4 shadow-sm border-success">
                                                <div class="card-header bg-success text-white">
                                                    <h5 class="card-title mb-0">
                                                        <i class="fas fa-check-circle me-2"></i>Billing Completed
                                                    </h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="alert alert-success mb-0">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        All payments are fully settled. No further budget requests are available.
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="card mb-4 shadow-sm">
                                                <div class="card-header bg-success text-white">
                                                    <h5 class="card-title mb-0">
                                                        <i class="fas fa-money-bill-wave me-2"></i>Submit Budget Request
                                                    </h5>
                                                </div>
                                                <div class="card-body">
                                                    <form id="budgetRequestForm">
                                                        <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project_id); ?>">
                                                        <?php 
                                                        // Fetch total_estimation_cost from projects table
                                                        $total_estimation_cost = 0;
                                                        $stmt = $con->prepare("SELECT total_estimation_cost FROM projects WHERE project_id = ?");
                                                        $stmt->bind_param('i', $project_id);
                                                        $stmt->execute();
                                                        $stmt->bind_result($total_estimation_cost);
                                                        $stmt->fetch();
                                                        $stmt->close();
                                                        ?>
                                                        <?php 
                                                        $fixed_budget_amount = max(($project_budget ?? 0) - ($total_payments ?? 0), 0);
                                                        ?>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Budget Amount (₱)</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text">₱</span>
                                                                <input type="number" class="form-control" name="budget_amount" id="budgetAmountStep8" 
                                                                    placeholder="<?php echo number_format($fixed_budget_amount, 2, '.', ''); ?>" 
                                                                    step="0.01" 
                                                                    value="<?php echo number_format($fixed_budget_amount, 2, '.', ''); ?>" 
                                                                    readonly 
                                                                    required>
                                                            </div>
                                                            <div class="form-text">
                                                                Request amount is locked to the remaining balance of 
                                                                <strong>₱<?php echo number_format($fixed_budget_amount, 2); ?></strong>.
                                                            </div>
                                                        </div>
                                                        <div class="d-grid">
                                                            <button type="submit" class="btn btn-primary" id="submitBudgetBtn">
                                                                <i class="fas fa-paper-plane me-2"></i>Submit Budget Request
                                                            </button>
                                                        </div>
                                                    </form>
                                                    <!-- Budget Request Status -->
                                                    <div class="mt-3" id="budgetStatusSection" style="display: block;">
                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                            <h6 class="mb-0">Budget Request Status</h6>
                                                            <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#budgetHistoryModal">
                                                                <i class="fas fa-history me-1"></i>View History
                                                            </button>
                                                        </div>
                                                        <div class="alert alert-info mb-0">
                                                            <div class="d-flex align-items-center">
                                                                <i class="fas fa-info-circle me-2"></i>
                                                                <span>Total Budget Requests: <strong id="totalBudgetRequests">0</strong></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Budget Summary Card -->
                                    <div class="col-md-4">
                                        <div class="card shadow-sm h-100">
                                            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center py-2">
                                                <h6 class="mb-0"><i class="fas fa-calculator me-2"></i>Budget Summary</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-4">
                                                    <!-- Project Total Budget -->
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span class="text-muted"><i class="fas fa-tag me-2 text-primary"></i>Project Budget:</span>
                                                        <span class="fw-bold"><?php echo peso($project_budget); ?></span>
                                                    </div>
                                                    <!-- Initial Payment -->
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span class="text-muted"><i class="fas fa-hand-holding-usd me-2 text-success"></i>Initial Payment:</span>
                                                        <span class="fw-medium"><?php echo peso($initial_budget); ?></span>
                                                    </div>
                                                    <!-- Completed Payments -->
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span class="text-muted"><i class="fas fa-check-circle me-2 text-success"></i>Completed Payments:</span>
                                                        <span class="fw-medium"><?php echo peso($total_completed_payments); ?></span>
                                                    </div>
                                                    <!-- Total Paid -->
                                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                                        <span class="text-muted"><i class="fas fa-calculator me-2 text-primary"></i>Total Paid:</span>
                                                        <span class="fw-bold"><?php echo peso($total_payments); ?></span>
                                                    </div>
                                                    <!-- Remaining Balance -->
                                                    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
                                                        <span class="text-muted">
                                                            <i class="fas fa-wallet me-2 text-<?php echo $remaining_budget >= 0 ? 'success' : 'danger'; ?>"></i>
                                                            Remaining Balance:
                                                        </span>
                                                        <span class="fw-bold text-<?php echo $remaining_budget >= 0 ? 'success' : 'danger'; ?>">
                                                            <?php echo peso($remaining_budget); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <!-- Budget Usage Progress Bar -->
                                                <div class="mb-2">
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <small class="text-muted">Budget Usage</small>
                                                        <small class="text-<?php echo $budget_percentage > 90 ? 'danger' : 'muted'; ?> fw-bold">
                                                            <?php echo number_format($budget_percentage, 1); ?>%
                                                        </small>
                                                    </div>
                                                    <div class="progress" style="height: 8px;">
                                                        <div class="progress-bar bg-<?php echo $remaining_budget >= 0 ? 'success' : 'danger'; ?>" 
                                                            role="progressbar" 
                                                            style="width: <?php echo min($budget_percentage, 100); ?>%" 
                                                            aria-valuenow="<?php echo $budget_percentage; ?>" 
                                                            aria-valuemin="0" 
                                                            aria-valuemax="100">
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php if($remaining_budget < 0): ?>
                                                <div class="alert alert-warning mt-3 mb-0 p-3">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-exclamation-triangle me-3 fa-lg"></i>
                                                        <div>
                                                            <strong>Budget Exceeded</strong>
                                                            <p class="mb-0 mt-1">This request will exceed the project budget by <?php echo peso(abs($remaining_budget)); ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Client Proof of Payment Card -->
                                    <div class="col-lg-4">
                                            <div class="card shadow-sm h-100">
                                                <div class="card-header bg-light">
                                                    <h5 class="card-title text-success mb-0">
                                                        <i class="fas fa-money-bill-wave me-2"></i>Payment Verification
                                                    </h5>
                                                </div>
                                                <div class="card-body d-flex flex-column">
                                                    <!-- Payment Details -->
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <div class="d-flex justify-content-between mb-2">
                                                                <span class="text-muted">Payment Type:</span>
                                                                <span class="fw-bold payment-type2">Waiting for payment</span>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-2">
                                                                <span class="text-muted">Status:</span>
                                                                <span class="badge bg-secondary payment-status2">Waiting for payment</span>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="d-flex justify-content-between mb-2">
                                                                <span class="text-muted">Uploaded On:</span>
                                                                <span class="text-muted upload-date2">Waiting for payment</span>
                                                            </div>
                                                            <div class="d-flex justify-content-between">
                                                                <span class="text-muted">Amount:</span>
                                                                <span class="fw-bold payment-amount2">Waiting for payment</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Image Viewer Box -->
                                                    <div class="border rounded p-3 text-center mb-3 flex-grow-1 d-flex align-items-center justify-content-center" style="background-color: #f8f9fa; min-height: 200px;">
                                                        <div id="paymentImageViewer2" class="w-100">
                                                            <div class="d-flex flex-column align-items-center justify-content-center h-100">
                                                                <i class="fas fa-image fa-3x text-muted mb-3"></i>
                                                                <p class="text-muted mb-0">Waiting for payment</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                                        <div>
                                                            <span class="fw-bold">Verification</span>
                                                            <div class="text-muted small mt-1">Paid by: <span class="payer-name">-</span></div>
                                                        </div>
                                                        <div id="actionButtons2">
                                                            <button type="button" class="btn btn-success" id="verifyPaymentBtn2">
                                                                <i class="fas fa-check-circle me-2"></i>Verify Payment
                                                            </button>
                                                            <button type="button" class="btn btn-danger" id="rejectPaymentBtn2">
                                                                <i class="fas fa-times-circle me-2"></i>Reject
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <!-- Payment Proofs Modal has been removed -->
                                </div>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-secondary prev-step" data-prev="7">
                                        <i class="fas fa-arrow-left me-1"></i> Previous
                                    </button>
                                    
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
       <?php include 'project_processv2_modal.php'; ?>
    
    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">
                        <i class="fas fa-check-circle me-2"></i>Success!
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="successMessage">Operation completed successfully!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
        <!-- Error Modal -->
     <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
         <div class="modal-dialog modal-dialog-centered">
             <div class="modal-content">
                 <div class="modal-header bg-danger text-white">
                     <h5 class="modal-title" id="errorModalLabel">
                         <i class="fas fa-exclamation-triangle me-2"></i>Error!
                     </h5>
                     <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                 </div>
                 <div class="modal-body">
                     <p id="errorMessage">An error occurred. Please try again.</p>
                 </div>
                 <div class="modal-footer">
                     <button type="button" class="btn btn-danger" data-bs-dismiss="modal">OK</button>
                 </div>
             </div>
         </div>
     </div>
     
     <!-- Budget Exceed Modal -->
     <div class="modal fade" id="budgetExceedModal" tabindex="-1" aria-labelledby="budgetExceedModalLabel" aria-hidden="true">
         <div class="modal-dialog modal-dialog-centered">
             <div class="modal-content">
                 <div class="modal-header bg-warning text-dark">
                     <h5 class="modal-title" id="budgetExceedModalLabel">
                         <i class="fas fa-exclamation-triangle me-2"></i>Budget Exceeded
                     </h5>
                     <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                 </div>
                 <div class="modal-body">
                     <p id="budgetExceedMessage">Sorry, you need to lower your request.</p>
                 </div>
                 <div class="modal-footer">
                     <button type="button" class="btn btn-warning" data-bs-dismiss="modal">OK</button>
                 </div>
             </div>
         </div>
     </div>
     
     <!-- Add Task Modal -->
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
                         <div class="modal-footer px-0 pb-0">
                             <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                             <button type="submit" class="btn btn-success">Save</button>
                         </div>
                     </form>
                 </div>
             </div>
         </div>
     </div>
     
     
     <!-- Budget History Modal -->
     <div class="modal fade" id="budgetHistoryModal" tabindex="-1" aria-labelledby="budgetHistoryModalLabel" aria-hidden="true">
         <div class="modal-dialog modal-lg modal-dialog-centered">
             <div class="modal-content">
                 <div class="modal-header bg-primary text-white">
                     <h5 class="modal-title" id="budgetHistoryModalLabel">
                         <i class="fas fa-history me-2"></i>Budget Request History
                     </h5>
                     <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                 </div>
                 <div class="modal-body">
                     <div class="row mb-3">
                         <div class="col-md-6">
                             <div class="card border-primary">
                                 <div class="card-body text-center py-3">
                                     <h6 class="mb-1 text-primary">Total Budget Requests</h6>
                                     <div class="display-6 fw-bold text-primary">
                                         <span id="modalTotalBudgetRequests">0</span>
                                     </div>
                                 </div>
                             </div>
                         </div>
                         <div class="col-md-6">
                             <div class="card border-info">
                                 <div class="card-body text-center py-3">
                                     <h6 class="mb-1 text-info">Latest Request</h6>
                                     <div class="h4 fw-bold text-info" id="modalLatestAmount">₱0</div>
                                     <small class="text-muted" id="modalLatestDate">No requests</small>
                                 </div>
                             </div>
                         </div>
                     </div>
                     
                     <div class="budget-requests-container" style="max-height: 400px; overflow-y: auto;">
                         <div id="modalBudgetRequestsList">
                             <!-- Budget requests will be loaded here -->
                         </div>
                     </div>
                 </div>
                 <div class="modal-footer">
                     <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                 </div>
             </div>
         </div>
     </div>
    
    <!-- Load Bootstrap first -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Then load our custom scripts -->
    <script src="js/contract_uploads.js" type="module"></script>
    <script src="js/payment_verification2.js" type="module"></script>
   
    
    <script>
    // Define showSuccessModal function
    function showSuccessModal(message) {
        document.getElementById('successMessage').textContent = message;
        const modal = new bootstrap.Modal(document.getElementById('successModal'));
        modal.show();
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        // Show success message if task was added
        <?php if (!empty($success_message)): ?>
            showSuccessModal('<?php echo addslashes($success_message); ?>');
        <?php endif; ?>
        
        const startProjectBtn = document.getElementById('startProjectBtn');
        const confirmStartBtn = document.getElementById('confirmStartProject');
        const startDateWarning = document.getElementById('startDateWarning');
        const dateMessage = document.getElementById('dateMessage');
        const modalHeader = document.getElementById('modalHeader');
        const projectStartDate = new Date('<?php echo isset($start_date) ? $start_date : date('Y-m-d'); ?>');
        const today = new Date();
        
        // Reset time part for accurate date comparison
        today.setHours(0, 0, 0, 0);
        projectStartDate.setHours(0, 0, 0, 0);
        
        // Calculate date difference in days
        const timeDiff = projectStartDate - today;
        const daysDiff = Math.ceil(timeDiff / (1000 * 60 * 60 * 24));
        
        // Check date conditions
        const isStartDateToday = daysDiff === 0;
        const isStartDatePast = daysDiff < 0;
        
        if (startProjectBtn) {
        startProjectBtn.addEventListener('click', function() {
            console.log('Start Project button clicked');
            // Get current date in Philippines timezone
            const today = new Date();
            const phDate = new Date(today.toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
            const formattedToday = phDate.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            console.log('Project start date:', projectStartDate);
            console.log('Today:', today);
            console.log('Days diff:', daysDiff);
            
            // Configure modal based on date
            if (isStartDatePast) {
                // For past dates
                modalHeader.className = 'modal-header bg-success text-white';
                startDateWarning.className = 'alert alert-success';
                dateMessage.innerHTML = `The project start date was <strong>${projectStartDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</strong> (${Math.abs(daysDiff)} days ago). The start date will be updated to today: <strong>${formattedToday}</strong>`;
            } else if (!isStartDateToday) {
                // For future dates
                modalHeader.className = 'modal-header bg-warning text-dark';
                startDateWarning.className = 'alert alert-warning';
                dateMessage.innerHTML = `The project start date is set to <strong>${projectStartDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</strong> (in ${daysDiff} days). The start date will be updated to today: <strong>${formattedToday}</strong>`;
            } else {
                // For today
                dateMessage.innerHTML = `The project start date will be set to today: <strong>${formattedToday}</strong>`;
            }
            
            // Show/hide warning based on whether it's today or not
            startDateWarning.classList.toggle('d-none', false); // Always show the message
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('startProjectModal'));
            modal.show();
        });
        }
        
        if (confirmStartBtn) {
        confirmStartBtn.addEventListener('click', function() {
            console.log('Confirm Start Project button clicked');
            // Update project status to 'Ongoing'
            const projectId = <?php echo $project_id; ?>;
            console.log('Project ID:', projectId);
            
            fetch('update_project_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'project_id=' + projectId + '&status=Ongoing'
            })
            .then(response => response.json())
            .then(data => {
                console.log('Fetch response:', data);
                if (data.success) {
                    console.log('Project status updated successfully, redirecting...');
                    // Redirect to project_actual.php on success
                    window.location.href = 'project_actual.php?id=' + projectId;
                } else {
                    console.log('Error response:', data.message);
                    alert('Error: ' + (data.message || 'Failed to start project'));
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('An error occurred while starting the project');
            });
        });
        }
    });
    </script>
    <script src="js/permits_uploads.js" type="module"></script>
    <script src="js/project_blueprint.js"></script>
    <script src="js/project_estimation.js"></script>
    <script src="js/employee_removal.js"></script>
    <script src="js/schedule_management.js"></script>
    <script src="js/payment_verification.js"></script>
    <style>
        #dropZone {
            transition: all 0.3s ease;
        }
        #dropZone.drag-over {
            background-color: #f8f9fa;
            border-color: #0d6efd !important;
        }
        .file-item {
            background: #f8f9fa;
            border-radius: 4px;
            padding: 8px 12px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .file-item .file-info {
            display: flex;
            align-items: center;
            gap: 8px;
            max-width: 80%;
        }
        .file-item .file-icon {
            color: #6c757d;
            min-width: 20px;
        }
        .file-item .file-name {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .file-item .file-remove {
            color: #dc3545;
            cursor: pointer;
            background: none;
            border: none;
            padding: 4px;
        }
        
        /* Permit validation styles */
        #lguDropZone.border-danger {
            animation: shake 0.5s ease-in-out;
            border-color: #dc3545 !important;
            box-shadow: 0 0 10px rgba(220, 53, 69, 0.3);
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
                 .badge.bg-danger {
             font-size: 0.6rem;
             padding: 0.2rem 0.4rem;
         }
         
         /* Forecasted Cost Card Styles */
         .bg-gradient-primary {
             background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
         }
         
         .bg-gradient-primary:hover {
             transform: translateY(-2px);
             box-shadow: 0 8px 25px rgba(0,0,0,0.15);
             transition: all 0.3s ease;
         }
         
         .display-6 {
             font-size: 2.5rem;
             font-weight: 700;
         }
         
         .opacity-75 {
             opacity: 0.75;
         }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Define showErrorModal function at the beginning
        function showErrorModal(message) {
            document.getElementById('errorMessage').textContent = message;
            const modal = new bootstrap.Modal(document.getElementById('errorModal'));
            modal.show();
        }
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
        // Function to validate blueprints before proceeding
        async function validateBlueprints(projectId) {
            try {
                const response = await fetch(`fetch_blueprints.php?project_id=${projectId}`);
                const data = await response.json();
                
                if (!data.success || !data.blueprints || data.blueprints.length === 0) {
                    showErrorModal('Please upload at least one blueprint before proceeding.');
                    return false;
                }
                
                const pendingBlueprint = data.blueprints.find(blueprint => blueprint.status === 'Pending');
                if (pendingBlueprint) {
                    showErrorModal('Cannot proceed: There are pending blueprints that need to be approved first.');
                    return false;
                }
                
                return true;
            } catch (error) {
                console.error('Error validating blueprints:', error);
                showErrorModal('An error occurred while validating blueprints. Please try again.');
                return false;
            }
        }
        // Next button click handler
        nextButtons.forEach(button => {
            button.addEventListener('click', async function() {
                const nextStep = parseInt(this.getAttribute('data-next'));
                const urlParams = new URLSearchParams(window.location.search);
                const projectId = urlParams.get('project_id');
                const btn = this;
                
                if (!projectId || isNaN(nextStep)) {
                    alert('Missing project ID or next step.');
                    return;
                }
                
                // For step 1, validate blueprints
                if (currentStep === 1) {
                    const isValid = await validateBlueprints(projectId);
                    if (!isValid) {
                        return;
                    }
                }
                
                // Skip validation for step 6 (schedule) to allow navigation without filling the form
                if (currentStep !== 6) {
                    const validationResult = await validateStep(currentStep);
                    if (!validationResult) {
                        return;
                    }
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
                                
                                // If navigating to step 3, refresh budget button state
                                if (currentStep === 3 && typeof window.refreshBudgetButtonState === 'function') {
                                    setTimeout(() => {
                                        window.refreshBudgetButtonState();
                                    }, 200);
                                }
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
                                
                                // If navigating to step 3, refresh budget button state
                                if (currentStep === 3 && typeof window.refreshBudgetButtonState === 'function') {
                                    setTimeout(() => {
                                        window.refreshBudgetButtonState();
                                    }, 200);
                                }
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
            
            // If showing step 3, refresh the budget button state
            if (stepNumber === 3 && typeof window.refreshBudgetButtonState === 'function') {
                setTimeout(() => {
                    window.refreshBudgetButtonState();
                }, 100);
            }
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
            
            // Special validation for step 5 (permits)
            if (step === 5) {
                return validatePermitsStep();
            }
            
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
        
        // Validate permits step - require at least LGU permit
        async function validatePermitsStep() {
            const projectId = new URLSearchParams(window.location.search).get('project_id');
            if (!projectId) {
                alert('Project ID not found. Please refresh the page.');
                return false;
            }
            
            try {
                const response = await fetch(`get_permits.php?project_id=${projectId}`);
                const result = await response.json();
                
                if (result.success && Array.isArray(result.permits)) {
                    // Check if LGU permit exists
                    const lguPermit = result.permits.find(permit => permit.permit_type === 'lgu');
                    
                    if (!lguPermit || !lguPermit.file_path) {
                        // Show alert and highlight LGU drop zone
                        const lguDropZone = document.getElementById('lguDropZone');
                        if (lguDropZone) {
                            lguDropZone.classList.add('border-danger');
                            setTimeout(() => {
                                lguDropZone.classList.remove('border-danger');
                            }, 3000);
                        }
                        
                        alert('Please upload the LGU Clearance permit before proceeding to the next step.');
                        return false;
                    }
                    
                    return true;
                } else {
                    alert('Please upload the LGU Clearance permit before proceeding to the next step.');
                    return false;
                }
            } catch (error) {
                console.error('Error validating permits:', error);
                alert('Error checking permits. Please try again.');
                return false;
            }
        }
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
    });
    </script>
    <!-- Budget Approval Script -->
    <script src="js/budget_approval.js"></script>
    <!-- Export Contract Functionality -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const generateContractBtn = document.getElementById('generateContractBtn');
        
        if (generateContractBtn) {
            generateContractBtn.addEventListener('click', function() {
                const projectId = document.getElementById('projectIdInput')?.value;
                
                if (!projectId) {
                    showAlert('Project ID not found', 'danger');
                    return;
                }
                
                // Show loading state
                const originalText = this.innerHTML;
                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Generating...';
                
                // Open the export URL in a new tab
                const exportUrl = `export_contract_pdf.php?project_id=${encodeURIComponent(projectId)}`;
                const newTab = window.open(exportUrl, '_blank');
                
                // Reset button state after a short delay
                setTimeout(() => {
                    this.disabled = false;
                    this.innerHTML = originalText;
                    
                    // Check if the new tab was blocked
                    if (!newTab || newTab.closed || typeof newTab.closed === 'undefined') {
                        // Show a message if popup was blocked
                        const alertHtml = `
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Please allow popups for this site to download the contract
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        `;
                        
                        // Find the contract card and insert the alert after it
                        const contractCard = document.querySelector('.card.h-100');
                        if (contractCard) {
                            contractCard.insertAdjacentHTML('afterend', alertHtml);
                        }
                    }
                }, 2000);
            });
        }
        
        // Helper function to show alerts
        function showAlert(message, type = 'info') {
            const alertId = 'alert-' + Date.now();
            const alertHtml = `
                <div id="${alertId}" class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            
            // Try to find a suitable container
            let container = document.querySelector('#alertContainer, .alert-container, .container, .container-fluid, body');
            
            // Create alert container if not found
            if (!container) container = document.body;
            
            // Create alert element
            const alertDiv = document.createElement('div');
            alertDiv.innerHTML = alertHtml;
            const alertElement = alertDiv.firstElementChild;
            
            // Position the alert based on container type
            if (container === document.body) {
                // For body, position fixed at top
                alertElement.style.position = 'fixed';
                alertElement.style.top = '20px';
                alertElement.style.right = '20px';
                alertElement.style.zIndex = '9999';
                alertElement.style.minWidth = '300px';
                container.appendChild(alertElement);
            } else {
                // For other containers, prepend to show at the top
                container.insertBefore(alertElement, container.firstChild);
            }
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                const alertToClose = document.getElementById(alertId);
                if (alertToClose) {
                    const bsAlert = new bootstrap.Alert(alertToClose);
                    bsAlert.close();
                    
                    // Remove from DOM after animation
                    setTimeout(() => {
                        if (alertToClose.parentNode) {
                            alertToClose.parentNode.removeChild(alertToClose);
                        }
                    }, 150);
                }
            }, 5000);
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
    
    <!-- Step 8: Budget Request and Proof of Payment JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Ensure showErrorModal is available globally (fallback for Step 8)
        if (typeof window.showErrorModal !== 'function') {
            window.showErrorModal = function(message) {
                const errorModalEl = document.getElementById('errorModal');
                const errorMessageEl = document.getElementById('errorMessage');
                if (errorModalEl && errorMessageEl && window.bootstrap) {
                    errorMessageEl.textContent = message;
                    const modal = new bootstrap.Modal(errorModalEl);
                    modal.show();
                } else {
                    alert(message);
                }
            };
        }
        // Budget Request Form
        const budgetRequestForm = document.getElementById('budgetRequestForm');
        const submitBudgetBtn = document.getElementById('submitBudgetBtn');
        const budgetStatusSection = document.getElementById('budgetStatusSection');
        const projectBudget = <?php echo $project_budget ?? 0; ?>;
        const totalPaid = <?php echo $total_payments ?? 0; ?>;
        const remainingBalance = Math.max(projectBudget - totalPaid, 0);
        const budgetAmountInputStep8 = document.getElementById('budgetAmountStep8');
        if (budgetAmountInputStep8) {
            budgetAmountInputStep8.value = remainingBalance.toFixed(2);
            budgetAmountInputStep8.placeholder = remainingBalance.toFixed(2);
            budgetAmountInputStep8.readOnly = true;
        }
        
        // Debug: Check if elements are found
        console.log('budgetRequestForm:', budgetRequestForm);
        console.log('submitBudgetBtn:', submitBudgetBtn);
        console.log('budgetStatusSection:', budgetStatusSection);
        
        if (budgetRequestForm) {
            budgetRequestForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const projectId = formData.get('project_id');
                const budgetAmount = parseFloat(formData.get('budget_amount'));
                
                if (!projectId || !budgetAmount) {
                    showErrorModal('Please fill in all fields');
                    return;
                }
                
                if (remainingBalance <= 0) {
                    showErrorModal('There is no remaining balance left to request.');
                    return;
                }
                
                if (Math.abs(budgetAmount - remainingBalance) > 0.01) {
                    showErrorModal('Budget request amount must match the remaining balance (₱' + remainingBalance.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ').');
                    return;
                }
                
                // Validation: Check if request will cause negative remaining balance (utang)
                const newTotalPaid = totalPaid + budgetAmount;
                const newRemainingBalance = projectBudget - newTotalPaid;
                
                if (newRemainingBalance < 0) {
                    const excessAmount = Math.abs(newRemainingBalance);
                    showBudgetExceedModal('Sorry, you need to lower your request. This request will exceed the project budget by ₱' + excessAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' and will result in a negative remaining balance.');
                    return;
                }
                
                // Disable submit button
                submitBudgetBtn.disabled = true;
                submitBudgetBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
                
                // Submit budget request
                fetch('submit_budget_request.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showSuccessModal('Budget request submitted successfully!');
                        budgetRequestForm.reset();
                        // Refresh budget status to show the new pending request
                        setTimeout(() => {
                            loadBudgetStatus(projectId);
                        }, 500);
                    } else {
                        showErrorModal('Error: ' + (data.message || 'Failed to submit budget request'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showErrorModal('An error occurred while submitting the budget request');
                })
                .finally(() => {
                    submitBudgetBtn.disabled = false;
                    submitBudgetBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Budget Request';
                });
            });
        }
        
                 // Load budget status
         function loadBudgetStatus(projectId) {
             console.log('loadBudgetStatus called with projectId:', projectId);
             if (!projectId) {
                 console.log('No projectId provided, returning');
                 return;
             }
             
             console.log('Fetching budget status from get_budget_status.php...');
             fetch(`get_budget_status.php?project_id=${projectId}`)
                 .then(response => {
                     console.log('Response received:', response);
                     return response.json();
                 })
                 .then(data => {
                     console.log('Budget status data:', data);
                     if (data.success && data.data && data.data.length > 0) {
                         const totalRequestsSpan = document.getElementById('totalBudgetRequests');
                         const submitBudgetBtn = document.getElementById('submitBudgetBtn');
                         
                         console.log('Found budget requests, updating UI...');
                         
                         // Update total count
                         totalRequestsSpan.textContent = data.total_requests;
                         
                         // Check if there's a pending request and update button state
                         if (data.has_pending_request) {
                             submitBudgetBtn.disabled = true;
                             submitBudgetBtn.innerHTML = '<i class="fas fa-clock me-2"></i>Pending Request Exists';
                             submitBudgetBtn.className = 'btn btn-warning';
                             
                             // Add warning message
                             if (!document.getElementById('pendingWarning')) {
                                 const warningDiv = document.createElement('div');
                                 warningDiv.id = 'pendingWarning';
                                 warningDiv.className = 'alert alert-warning mt-3';
                                 warningDiv.innerHTML = `
                                     <i class="fas fa-exclamation-triangle me-2"></i>
                                     <strong>Note:</strong> You cannot submit a new budget request while there is already a pending request for this project.
                                 `;
                                 budgetRequestForm.appendChild(warningDiv);
                             }
                         } else {
                             submitBudgetBtn.disabled = false;
                             submitBudgetBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Budget Request';
                             submitBudgetBtn.className = 'btn btn-primary';
                             
                             // Remove warning message if it exists
                             const warningDiv = document.getElementById('pendingWarning');
                             if (warningDiv) {
                                 warningDiv.remove();
                             }
                         }
                         
                         console.log('Showing budget status section');
                         budgetStatusSection.style.display = 'block';
                     } else {
                         // No budget requests found
                         console.log('No budget requests found, showing empty state');
                         document.getElementById('totalBudgetRequests').textContent = '0';
                         budgetStatusSection.style.display = 'block';
                         
                         // Enable submit button if no requests exist
                         const submitBudgetBtn = document.getElementById('submitBudgetBtn');
                         submitBudgetBtn.disabled = false;
                         submitBudgetBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Budget Request';
                         submitBudgetBtn.className = 'btn btn-primary';
                         
                         // Remove warning message if it exists
                         const warningDiv = document.getElementById('pendingWarning');
                         if (warningDiv) {
                             warningDiv.remove();
                         }
                     }
                 })
                 .catch(error => {
                     console.error('Error loading budget status:', error);
                     budgetStatusSection.style.display = 'block';
                 });
         }
         
         // Load budget history for modal
         function loadBudgetHistory(projectId) {
             if (!projectId) return;
             
             fetch(`get_budget_status.php?project_id=${projectId}`)
                 .then(response => response.json())
                 .then(data => {
                     if (data.success && data.data && data.data.length > 0) {
                         const modalTotalRequests = document.getElementById('modalTotalBudgetRequests');
                         const modalLatestAmount = document.getElementById('modalLatestAmount');
                         const modalLatestDate = document.getElementById('modalLatestDate');
                         const modalBudgetRequestsList = document.getElementById('modalBudgetRequestsList');
                         
                         // Update modal summary cards
                         modalTotalRequests.textContent = data.total_requests;
                         
                         if (data.data.length > 0) {
                             const latestRequest = data.data[0];
                             modalLatestAmount.textContent = '₱' + number_format(latestRequest.requested_amount);
                             modalLatestDate.textContent = formatDate(latestRequest.request_date);
                         }
                         
                         // Build HTML for modal budget requests list
                         let html = '';
                         data.data.forEach((request, index) => {
                             const isLatest = index === 0;
                             const badgeClass = getStatusBadgeClass(request.status);
                             const latestBadge = isLatest ? '<span class="badge bg-primary ms-2">Latest</span>' : '';
                             
                             html += `
                                 <div class="card mb-3 ${isLatest ? 'border-primary' : 'border-light'}">
                                     <div class="card-body p-3">
                                         <div class="d-flex justify-content-between align-items-start">
                                             <div class="flex-grow-1">
                                                 <div class="d-flex align-items-center mb-2">
                                                     <h6 class="mb-0 me-2">₱${number_format(request.requested_amount)}</h6>
                                                     ${latestBadge}
                                                 </div>
                                                 <p class="mb-1">
                                                     <strong>Status:</strong> <span class="badge ${badgeClass}">${request.status}</span>
                                                 </p>
                                                 <p class="mb-0 small text-muted">
                                                     <i class="fas fa-calendar me-1"></i>${formatDate(request.request_date)}
                                                 </p>
                                             </div>
                                         </div>
                                     </div>
                                 </div>
                             `;
                         });
                         
                         modalBudgetRequestsList.innerHTML = html;
                     } else {
                         // No budget requests found
                         document.getElementById('modalTotalBudgetRequests').textContent = '0';
                         document.getElementById('modalLatestAmount').textContent = '₱0';
                         document.getElementById('modalLatestDate').textContent = 'No requests';
                         document.getElementById('modalBudgetRequestsList').innerHTML = '<p class="text-muted text-center mb-0">No budget requests found</p>';
                     }
                 })
                 .catch(error => {
                     console.error('Error loading budget history:', error);
                     document.getElementById('modalBudgetRequestsList').innerHTML = '<p class="text-danger text-center mb-0">Error loading budget requests</p>';
                 });
         }
        
        // Proof of payments functionality removed as per user request
        
        // Complete project button
        const completeProjectBtn = document.getElementById('completeProjectBtn');
        if (completeProjectBtn) {
            completeProjectBtn.addEventListener('click', function() {
                if (confirm('Are you sure you want to mark this project as complete?')) {
                    const projectId = new URLSearchParams(window.location.search).get('project_id');
                    if (projectId) {
                        // Update project status to completed
                        fetch('update_project_status.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `project_id=${projectId}&status=Completed`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showSuccessModal('Project marked as completed successfully!');
                                setTimeout(() => {
                                    window.location.reload();
                                }, 2000);
                            } else {
                                showErrorModal('Error: ' + (data.message || 'Failed to complete project'));
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showErrorModal('An error occurred while completing the project');
                        });
                    }
                }
            });
        }
        
        // Helper functions
        function number_format(number) {
            return new Intl.NumberFormat('en-PH').format(number);
        }
        
        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('en-PH', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        function getStatusBadgeClass(status) {
            switch(status.toLowerCase()) {
                case 'approved': return 'bg-success';
                case 'rejected': return 'bg-danger';
                case 'pending': return 'bg-warning';
                default: return 'bg-secondary';
            }
        }
        
        // Function to show budget exceed modal
        function showBudgetExceedModal(message) {
            const modalMessage = document.getElementById('budgetExceedMessage');
            if (modalMessage) {
                modalMessage.textContent = message;
            }
            const modal = new bootstrap.Modal(document.getElementById('budgetExceedModal'));
            modal.show();
        }
        
        // Load data when step 8 is shown
        if (window.currentStep === 8) {
            const projectId = new URLSearchParams(window.location.search).get('project_id');
            if (projectId) {
                loadBudgetStatus(projectId);
            }
        }
        
        // Always load budget status if we have a project ID (for debugging)
        const projectId = new URLSearchParams(window.location.search).get('project_id');
        if (projectId) {
            console.log('Project ID found:', projectId);
            console.log('Current step:', window.currentStep);
            
            // Load budget status immediately
            loadBudgetStatus(projectId);
        }
        
        // Add modal event listeners
        const budgetHistoryModal = document.getElementById('budgetHistoryModal');
        if (budgetHistoryModal) {
            budgetHistoryModal.addEventListener('show.bs.modal', function() {
                const projectId = new URLSearchParams(window.location.search).get('project_id');
                if (projectId) {
                    loadBudgetHistory(projectId);
                }
            });
        }
        
        
        
                 // Global function for viewing proof files
         window.viewProofFile = function(filePath, fileName) {
             // Debug logging
             console.log('Viewing file:', filePath, fileName);
             
             // Use the proper backend route to view files
             const viewUrl = `view_proof_file.php?file_path=${encodeURIComponent(filePath)}&file_name=${encodeURIComponent(fileName)}`;
             console.log('View URL:', viewUrl);
             window.open(viewUrl, '_blank');
         };
         
    });
    </script>
  </body>
</html>