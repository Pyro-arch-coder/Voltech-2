<?php
session_start();
require_once '../config.php';

// Fetch project details if project_id is in GET
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
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

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 3) {
    header("Location: ../login.php");
    exit();
}

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
                                <!-- Project Materials Section -->
                                <div class="card mb-3 shadow-sm">
                                    <div class="card-header bg-success text-white d-flex align-items-center">
                                        <span class="flex-grow-1">Project Materials</span>
                                        <button type="button" class="btn btn-light btn-sm ml-auto" id="addMaterialsBtn" data-bs-toggle="modal" data-bs-target="#addMaterialsModal">
                                            <i class="fas fa-plus-square me-1"></i> Add Materials
                                        </button>
                                        <button type="button" class="btn btn-light btn-sm ms-2" id="exportCostEstimationBtn">
                                            <i class="fas fa-file-export"></i> Export PDF
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
                                                    $records_per_page = 5;
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
                                                        <th colspan="7" class="text-end">Grand Total (All Materials)</th>
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
                                <!-- Navigation Buttons -->
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-secondary prev-step" data-prev="1">Previous</button>
                                    <button type="button" class="btn btn-primary next-step" data-next="3">Next <i class="fas fa-arrow-right"></i></button>
                                </div>
                            </div>
                            <div class="step-content d-none" id="step3">
                                <h4 class="mb-4 fw-bold text-success">Step 3: Budget Approval</h4>

                                <div class="alert alert-info d-flex align-items-center mb-4">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <span>Please upload the budget documents and specify the budget amount below. The next button will be enabled only after the budget is approved.</span>
                                </div>

                                <form id="budgetForm" autocomplete="off">
                                    <input type="hidden" name="project_id" value="">

                                    <div class="row g-4">
                                        <div class="col-lg-8">
                                            <div class="card mb-4 shadow-sm h-100">
                                                <div class="card-body">
                                                    <h5 class="card-title mb-4 text-primary">
                                                        <i class="fas fa-file-invoice-dollar me-2"></i>Budget Documents
                                                    </h5>
                                                    <label class="form-label fw-bold mb-2">Budget Files</label>
                                                    <div class="card border-2 border-dashed rounded-3"
                                                        id="budgetDropZone" style="min-height: 170px; cursor: pointer; transition: border .2s;">
                                                        <div class="card-body d-flex flex-column align-items-center justify-content-center p-4 text-center">
                                                            <i class="fas fa-file-upload fa-3x text-primary mb-3"></i>
                                                            <h5 class="mb-2">Drag &amp; drop your budget files here</h5>
                                                            <p class="text-muted mb-3">or</p>
                                                            <button type="button" class="btn btn-outline-primary px-4 mb-2" id="browseBudgetFilesBtn" tabindex="0">
                                                                <i class="fas fa-folder-open me-2"></i>Browse Files
                                                            </button>
                                                            <input type="file" class="d-none" name="budget_files[]" id="budgetFiles" multiple accept=".pdf">
                                                            <p class="small text-muted mt-3 mb-0">Supported format: <strong>PDF only</strong> (Max 10MB per file)</p>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                                        <div id="budgetFileList" class="flex-grow-1"></div>
                                                        <div class="d-flex">
                                                            <!-- View Files button -->
                                                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#budgetFilesModal">
                                                                <i class="fas fa-eye me-2"></i>View Budget Files
                                                            </button>
                                                            <button type="button"
                                                                class="btn <?= $budget_doc_exists ? 'btn-primary' : 'btn-primary' ?> ms-2"
                                                                id="uploadBudgetBtn">
                                                                <i class="fas fa-cloud-upload-alt me-2"></i>
                                                                <?= $budget_doc_exists ? 'Re-upload File' : 'Upload Files' ?>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <!-- Upload status message -->
                                                    <div id="uploadStatus" class="text-muted small mt-2"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Budget Amount Approval Card -->
                                        <div class="col-lg-4">
                                            <div class="card mb-4 shadow-sm h-100">
                                                <div class="card-body">
                                                    <h5 class="card-title mb-4 text-success">
                                                        <i class="fas fa-money-bill-wave me-2"></i>Budget Approval
                                                    </h5>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Budget Amount (₱)</label>
                                                        <div class="input-group mb-2">
                                                            <span class="input-group-text">₱</span>
                                                            <input type="text" class="form-control form-control-lg" name="budget"
                                                                placeholder="100000"
                                                                pattern="1\d{5}" inputmode="numeric"
                                                                aria-describedby="budgetMessage"
                                                                oninput="this.value=this.value.replace(/[^0-9]/g,'').replace(/^[^1]/, '1').slice(0,6);">
                                                        </div>
                                                        <div class="form-text text-muted" id="budgetMessage">
                                                            Enter 6 digits starting with 1 (e.g., <strong>100000</strong>)
                                                        </div>
                                                    </div>
                                                    <div class="text-center mt-4">
                                                        <button type="button" id="requestBudgetBtn" class="btn btn-success w-100" tabindex="0">
                                                            <i class="fas fa-paper-plane me-1"></i>
                                                            Request Budget Approval
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>

                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-secondary prev-step" data-prev="2">
                                        <i class="fas fa-arrow-left me-1"></i> Previous
                                    </button>
                                    <button type="button" class="btn btn-primary next-step" data-next="4" id="nextBudgetStepBtn">
                                        Next <i class="fas fa-arrow-right ms-1"></i>
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
                                                <div class="card h-100 border-2 border-dashed" id="originalDropZone">
                                                    <div class="card-body d-flex flex-column align-items-center justify-content-center p-4 text-center" style="min-height: 200px;">
                                                        <i class="fas fa-file-pdf fa-3x text-primary mb-3"></i>
                                                        <h6 class="mb-2">Original Contract</h6>
                                                        <p class="small text-muted mb-3">Drag & drop your PDF here</p>
                                                        <p class="small text-muted mb-0">or</p>
                                                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="browseOriginalBtn">
                                                            <i class="fas fa-folder-open me-1"></i> Browse Files
                                                        </button>
                                                        <input type="file" class="d-none" id="originalContract" name="original_contract" accept=".pdf">
                                                    </div>
                                                    <div class="card-footer bg-transparent border-top-0 pt-0">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small class="text-muted file-info" id="originalFileInfo">No file selected</small>
                                                            <div class="btn-group">
                                                                <button type="button" class="btn btn-sm btn-outline-primary" id="uploadOriginalBtn" disabled>
                                                                    <i class="fas fa-upload me-1"></i> Upload
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-outline-info" id="viewOriginalBtn" disabled>
                                                                    <i class="fas fa-eye"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="progress mt-2" style="height: 5px; display: none;" id="originalProgress">
                                                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                                        </div>
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
                                                        <h6 class="mb-2">LGU Clearance</h6>
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
                                        This is the latest schedule of the project
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Project Timeline Row (Moved to top) -->
                                    <div class="col-12 mb-4">
                                        <div class="card">
                                            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                                                <h5 class="card-title mb-0"><i class="fas fa-tasks me-2"></i>Project Timeline</h5>
                                                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                                                    <i class="fas fa-plus me-1"></i> Add Time Schedule
                                                </button>
                                            </div>
                                            <div class="card-body p-3">
                                                <div class="table-responsive">
                                                    <table class="table table-hover mb-0" id="scheduleTable">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>#</th>
                                                                <th>Task Name</th>
                                                                <th>Description</th>
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
                            <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-secondary prev-step" data-prev="7">Previous</button>
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

   <?php include 'project_processv2_modal.php'; ?>
    <!-- Load Bootstrap first -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Then load our custom scripts -->
    <script src="js/contract_uploads.js" type="module"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const startProjectBtn = document.getElementById('startProjectBtn');
        const confirmStartBtn = document.getElementById('confirmStartProject');
        const startDateWarning = document.getElementById('startDateWarning');
        const dateMessage = document.getElementById('dateMessage');
        const modalHeader = document.getElementById('modalHeader');
        const projectStartDate = new Date('<?php echo $project['start_date']; ?>');
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
        
        startProjectBtn.addEventListener('click', function() {
            // Get current date in Philippines timezone
            const today = new Date();
            const phDate = new Date(today.toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
            const formattedToday = phDate.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
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
        
        confirmStartBtn.addEventListener('click', function() {
            // Update project status to 'Ongoing'
            const projectId = <?php echo $project_id; ?>;
            
            fetch('update_project_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'project_id=' + projectId + '&status=Ongoing'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect to project_actual.php on success
                    window.location.href = 'project_actual.php?id=' + projectId;
                } else {
                    alert('Error: ' + (data.message || 'Failed to start project'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while starting the project');
            });
        });
    });
    </script>
    <script src="js/permits_uploads.js" type="module"></script>
    <script src="js/project_blueprint.js"></script>
    <script src="js/project_budget.js"></script>
    <script src="js/project_budget_docs.js"></script>
    <script src="js/project_estimation.js"></script>
    <script src="js/schedule_management.js"></script>
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

        // Function to validate blueprints before proceeding
        async function validateBlueprints(projectId) {
            try {
                const response = await fetch(`fetch_blueprints.php?project_id=${projectId}`);
                const data = await response.json();
                
                if (!data.success || !data.blueprints || data.blueprints.length === 0) {
                    return { isValid: false, message: 'Please upload at least one blueprint before proceeding.' };
                }
                
                const pendingBlueprint = data.blueprints.find(blueprint => blueprint.status === 'Pending');
                if (pendingBlueprint) {
                    return { 
                        isValid: false, 
                        message: 'Cannot proceed: There are pending blueprints that need to be approved first.' 
                    };
                }
                
                return { isValid: true };
            } catch (error) {
                console.error('Error validating blueprints:', error);
                return { 
                    isValid: false, 
                    message: 'An error occurred while validating blueprints. Please try again.' 
                };
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
                    const validation = await validateBlueprints(projectId);
                    if (!validation.isValid) {
                        alert(validation.message);
                        return;
                    }
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
  </body>
</html>