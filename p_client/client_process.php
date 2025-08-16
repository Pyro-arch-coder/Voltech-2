<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 6) {
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
    <title>Client Process - Project Portal</title>
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
            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    <!-- Step Progress Bar (Single, with Labels) -->
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated" role="progressbar" style="width: 12.5%;" aria-valuenow="12.5" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <?php 
                                                 $stepTitles = [
                             1 => 'Blue Print Approval',
                             2 => 'Budget Approval',
                             3 => 'Contract Signing',
                             4 => 'Permits Viewing',
                             5 => 'Schedule',
                             6 => 'Billing Approval'
                         ];
                         for($i = 1; $i <= 6; $i++): ?>
                             <div class="text-center" style="flex: 1; min-width: 80px;">
                                 <div class="step-number d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white" style="width: 30px; height: 30px; font-weight: bold;"><?php echo $i; ?></div>
                                 <div class="step-label small mt-1" style="font-size: 0.7rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo $stepTitles[$i]; ?></div>
                             </div>
                         <?php endfor; ?>
                    </div>
                </div>
            </div>



            <div class="card shadow rounded-3 border-0">
                <div class="card-body px-4 py-4">
                    <form id="projectProcessForm" autocomplete="off">
                        <!-- Step 1: Blue Print Approval -->
                        <div class="step-content" id="step1">
                            <div class="d-flex align-items-center mb-3">
                                <span class="badge rounded-pill bg-primary me-2">1</span>
                                <h5 class="mb-0 text-primary fw-bold">
                                    <i class="fas fa-file-alt me-2"></i>Blue Print Approval
                                </h5>
                            </div>
                            
                            <!-- Blueprint Status Alert -->
                            <div class="alert alert-info mb-4" id="blueprintStatusAlert">
                                <i class="fas fa-info-circle me-2"></i>
                                <span id="blueprintStatusText">Checking blueprint status...</span>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-flex justify-content-between align-items-center mb-3 d-none" id="blueprintActionButtons">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="selectAllBlueprints">
                                    <label class="form-check-label" for="selectAllBlueprints">
                                        Select All
                                    </label>
                                </div>
                                <div class="btn-group gap-2" >
                                    <button type="button" class="btn btn-success btn-sm" onclick="handleBulkAction('approve')">
                                        <i class="fas fa-check me-1"></i> Approve Selected
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="handleBulkAction('reject')">
                                        <i class="fas fa-times me-1"></i> Reject Selected
                                    </button>
                                    <button type="button" class="btn btn-warning btn-sm d-none" id="revokeSelectedBtn" onclick="handleBulkAction('revoke')">
                                        <i class="fas fa-undo me-1"></i> Revoke Selected
                                    </button>
                                </div>
                            </div>

                            <div class="card shadow rounded-3 border-0 p-4" style="overflow-x: auto;">
                                <!-- Blueprints Container -->
                                <div class="d-flex flex-nowrap gap-4" id="blueprintsContainer">
                                    <!-- Blueprint cards will be inserted here by JavaScript -->
                                </div>
                                
                                <!-- No Blueprints Message -->
                                <div class="text-center py-4 d-none" id="noBlueprintsMessage">
                                    <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                    <h5>No blueprints available</h5>
                                    <p class="text-muted">Please wait for the project manager to upload the blueprints.</p>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <div></div> <!-- Empty div for flex spacing -->
                                <button type="button" class="btn btn-primary next-step" data-next="2" id="nextStepBtn" disabled>
                                    Next <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: Budget Approval -->
                        <div class="step-content d-none" id="step2">
                            <div class="d-flex align-items-center mb-3">
                                <span class="badge rounded-pill bg-primary me-2">2</span>
                                <h5 class="mb-0 text-primary fw-bold">
                                    <i class="fas fa-file-invoice-dollar me-2"></i>Budget Approval
                                </h5>
                            </div>
                            
                            <?php
                                // Keep the project_id variable for other functionality
                                $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
                            ?>
                            
                            <div class="row justify-content-center">
                                <!-- Budget Card -->
                                <div class="col-md-8 col-lg-6">
                                    <div class="card h-100 shadow" style="min-height: 500px;">
                                        <div class="card-header bg-light py-3">
                                            <h6 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Request Budget</h6>
                                        </div>
                                        <div class="card-body d-flex flex-column justify-content-center align-items-center text-center">
                                            <div class="w-100">
                                                <div class="text-muted mb-2">Total Project Cost</div>
                                                <div class="display-4 fw-bold text-primary mb-2" id="projectTotal">₱0.00</div>
                                                <div class="text-muted small">inclusive of all charges</div>
                                            </div>
                                            <div class="d-flex justify-content-center gap-3 mt-4">
                                                <button type="button" class="btn btn-success px-3" id="approveBudget">
                                                    <i class="fas fa-check me-1"></i> Approve
                                                </button>
                                                <button type="button" class="btn btn-outline-danger px-3" id="rejectBudget">
                                                    <i class="fas fa-times me-1"></i> Reject
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <script>
                                // Set the current project ID for the budget viewer
                                window.currentProjectId = <?php echo isset($_GET['project_id']) ? intval($_GET['project_id']) : 'null'; ?>;
                            </script>

                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-secondary prev-step" data-prev="1">
                                    <i class="fas fa-arrow-left me-1"></i> Previous
                                </button>
                                <button type="button" class="btn btn-primary next-step" data-next="3">
                                    Next <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 3: Contract Signing -->
                        <div class="step-content d-none" id="step3">
                            <div class="d-flex align-items-center mb-3">
                                <span class="badge rounded-pill bg-primary me-2">3</span>
                                <h5 class="mb-0 text-primary fw-bold">
                                    <i class="fas fa-pen-fancy me-2"></i>Contract Signing
                                </h5>
                            </div>
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
                                                <div class="card h-100" data-contract-type="original">
                                                    <div class="card-body d-flex flex-column align-items-center justify-content-center p-4 text-center" style="min-height: 200px;">
                                                        <i class="fas fa-file-pdf fa-3x text-primary mb-3"></i>
                                                        <h6 class="mb-2">Original Contract</h6>
                                                        <p class="small text-muted mb-3">The original contract will be provided</p>
                                                        <div class="d-flex flex-column align-items-center">
                                                            <small class="contract-status text-muted mb-2">No file available</small>
                                                            <button type="button" class="btn btn-sm btn-outline-primary view-contract" data-contract-type="original" disabled>
                                                                <i class="fas fa-eye me-1"></i> View
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Project Manager Contract -->
                                            <div class="col-md-4">
                                                <div class="card h-100" data-contract-type="yoursigned">
                                                    <div class="card-body d-flex flex-column align-items-center justify-content-center p-4 text-center" style="min-height: 200px;">
                                                        <i class="fas fa-user-tie fa-3x text-success mb-3"></i>
                                                        <h6 class="mb-2">Project Manager Contract</h6>
                                                        <p class="small text-muted mb-3">Project manager's contract will be available here</p>
                                                        <div class="d-flex flex-column align-items-center">
                                                            <small class="contract-status text-muted mb-2">No file available</small>
                                                            <button type="button" class="btn btn-sm btn-outline-success view-contract" data-contract-type="yoursigned" disabled>
                                                                <i class="fas fa-eye me-1"></i> View
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Your Signed Contract -->
                                            <div class="col-md-4">
                                                <div class="card h-100 border-2 border-dashed" id="clientDropZone" data-contract-type="clientsigned">
                                                    <div class="card-body d-flex flex-column align-items-center justify-content-center p-4 text-center" style="min-height: 200px;">
                                                        <i class="fas fa-signature fa-3x text-info mb-3"></i>
                                                        <h6 class="mb-2">Your Signed Contract</h6>
                                                        <p class="small text-muted mb-3">Drag & drop your PDF here</p>
                                                        <p class="small text-muted mb-0">or</p>
                                                        <button type="button" class="btn btn-sm btn-outline-info mt-2" id="browseClientBtn">
                                                            <i class="fas fa-folder-open me-1"></i> Browse Files
                                                        </button>
                                                        <input type="file" class="d-none" id="clientContract" name="client_contract" accept=".pdf">
                                                        <div class="d-flex flex-column align-items-center mt-2">
                                                            <small class="contract-status text-muted mb-2">No file available</small>
                                                            <button type="button" class="btn btn-sm btn-outline-info view-contract" data-contract-type="clientsigned" disabled>
                                                                <i class="fas fa-eye me-1"></i> View
                                                            </button>
                                                        </div>
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
                                <button type="button" class="btn btn-outline-secondary prev-step" data-prev="2">
                                    <i class="fas fa-arrow-left me-1"></i> Previous
                                </button>
                                <button type="button" class="btn btn-primary next-step" data-next="4">
                                    Next <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                                                 </div>
 
                         <!-- Step 4: Permits Viewing -->
                         <div class="step-content d-none" id="step4">
                             <div class="d-flex align-items-center mb-3">
                                 <span class="badge rounded-pill bg-primary me-2">4</span>
                                 <h5 class="mb-0 text-primary fw-bold">
                                     <i class="fas fa-file-alt me-2"></i>Permits Viewing
                                 </h5>
                             </div>
                             
                             <div class="alert alert-info mb-4">
                                 <i class="fas fa-info-circle me-2"></i>
                                 View and download project permits. All permits will be displayed here once uploaded by the project manager.
                             </div>
                             
                             <!-- Hidden input for project ID -->
                             <input type="hidden" id="projectIdInputPermits" value="<?php echo isset($_GET['project_id']) ? intval($_GET['project_id']) : ''; ?>">
                             
                             <!-- Permits Container -->
                             <div class="row" id="permitsContainer">
                                 <!-- Permit cards will be dynamically generated here -->
                             </div>
                             
                             <!-- No Permits Message -->
                             <div class="text-center py-5 d-none" id="noPermitsMessage">
                                 <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                 <h5>No permits available</h5>
                                 <p class="text-muted">Please wait for the project manager to upload the required permits.</p>
                             </div>
                             
                    <div class="d-flex justify-content-between mt-4">
                                  <button type="button" class="btn btn-outline-secondary prev-step" data-prev="3">
                                      <i class="fas fa-arrow-left me-1"></i> Previous
                                  </button>
                                  <button type="button" class="btn btn-primary next-step" data-next="5">
                                      Next <i class="fas fa-arrow-right"></i>
                                  </button>
                              </div>
                         </div>
 
                         <!-- Step 5: Schedule -->
                         <div class="step-content d-none" id="step5">
                                                         <div class="d-flex align-items-center mb-3">
                                 <span class="badge rounded-pill bg-primary me-2">5</span>
                                 <h5 class="mb-0 text-primary fw-bold">
                                     <i class="fas fa-calendar-alt me-2"></i>Schedule
                                 </h5>
                             </div>
                            <?php
                                $timeline = [];

                                // Siguraduhin na may project_id sa URL o variable
                                if (isset($_GET['project_id']) && is_numeric($_GET['project_id'])) {
                                    $project_id = (int) $_GET['project_id'];

                                    $stmt = $con->prepare("SELECT * FROM project_timeline WHERE project_id = ? ORDER BY start_date ASC");
                                    $stmt->bind_param("i", $project_id);
                                    $stmt->execute();
                                    $timeline = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                    $stmt->close();
                                }
                                ?>

                            <div class="col-12 mb-4">
                                        <div class="card">
                                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                                <h5 class="card-title mb-0"><i class="fas fa-tasks me-2"></i>Project Timeline</h5>
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
                                                            <?php if (empty($timeline)) { ?>
                                                                <tr>
                                                                    <td colspan="7" class="text-center py-4 text-muted">
                                                                        No schedule items found.
                                                                    </td>
                                                                </tr>
                                                            <?php } else {
                                                                $i = 1;
                                                                foreach ($timeline as $row) { ?>
                                                                <tr>
                                                                    <td><?php echo $i++; ?></td>
                                                                    <td><?php echo htmlspecialchars($row['task_name']); ?></td>
                                                                    <td><?php echo htmlspecialchars($row['description']); ?></td>
                                                                    <td><?php echo htmlspecialchars($row['start_date']); ?></td>
                                                                    <td><?php echo htmlspecialchars($row['end_date']); ?></td>
                                                                    <td>
                                                                        <span class="badge bg-<?php
                                                                            echo ($row['status'] === 'Completed') ? 'success' :
                                                                                (($row['status'] === 'Not Started') ? 'secondary' : 'warning');
                                                                        ?>">
                                                                            <?php echo htmlspecialchars($row['status']); ?>
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <div class="progress" style="height: 20px;">
                                                                            <div class="progress-bar" role="progressbar" style="width: <?php echo (int)$row['progress']; ?>%" aria-valuenow="<?php echo (int)$row['progress']; ?>" aria-valuemin="0" aria-valuemax="100">
                                                                                <?php echo (int)$row['progress']; ?>%
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php } } ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Gantt Chart Row (Moved below Project Timeline) -->
                                    <div class="col-12 mb-4">
                                        <div class="card">
                                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
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
                                                                    
                                                                    // Use Bootstrap primary color for all tasks
                                                                    $statusColor = '#0d6efd'; // Bootstrap primary color
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
                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-secondary prev-step" data-prev="4">
                                    <i class="fas fa-arrow-left me-1"></i> Previous
                                </button>
                                <button type="button" class="btn btn-primary next-step" data-next="6">
                                    Next <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>

                                                                          <!-- Step 6: Billing Approval -->
                         <div class="step-content d-none" id="step6">
                             <div class="d-flex align-items-center mb-3">
                                 <span class="badge rounded-pill bg-primary me-2">6</span>
                                 <h5 class="mb-0 text-primary fw-bold">
                                     <i class="fas fa-money-bill-wave me-2"></i>Billing Approval
                                 </h5>
                             </div>
                             
                                                           <!-- Task Progress Chart Card (Full Width at Top) -->
                              <div class="card shadow-sm mb-4">
                                  <div class="card-header bg-primary text-white">
                                      <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Task Progress Overview</h6>
                                  </div>
                                  <div class="card-body">
                                      <div class="mb-3" style="height: 300px;">
                                          <canvas id="taskProgressChartStep6"></canvas>
                                      </div>
                                      <div class="text-center">
                                          <small class="text-muted">
                                              <i class="fas fa-info-circle me-1"></i>
                                              Progress of all tasks and subtasks from project timeline
                                          </small>
                                      </div>
                                  </div>
                              </div>
                              
                              <!-- Two Cards Side by Side Below -->
                              <div class="row g-4">
                                  <!-- Billing Request Card -->
                                  <div class="col-md-6">
                                    <!-- Expose userId for JS -->
                                    
                                      <div class="card shadow-sm h-100">
                                          <div class="card-header bg-primary text-white">
                                              <h6 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Billing Request</h6>
                                          </div>
                                          <div class="card-body">
                                              <!-- Project Manager Amount Request -->
                                              <div class="mb-4">
                                                  <h6 class="text-primary mb-3">
                                                      <i class="fas fa-user-tie me-2"></i>Project Manager Request
                                                  </h6>
                                                  <div class="bg-light p-3 rounded mb-3">
                                                      <div class="d-flex justify-content-between align-items-center mb-2">
                                                          <span class="text-muted">Requested Amount:</span>
                                                          <span class="fw-bold text-primary" id="requestedAmount">₱0.00</span>
                                                      </div>
                                                      <div class="d-flex justify-content-between align-items-center mb-2">
                                                          <span class="text-muted">Request Date:</span>
                                                          <span id="requestDate">Not yet requested</span>
                                                      </div>
                                                      <div class="d-flex justify-content-between align-items-center">
                                                          <span class="text-muted">Status:</span>
                                                          <span class="badge bg-primary" id="requestStatus">Pending</span>
                                                      </div>
                                                  </div>
                                                  <div class="alert alert-primary">
                                                      <i class="fas fa-info-circle me-2"></i>
                                                      The project manager will submit the billing amount request here.
                                                  </div>
                                                  
                                                  <!-- Action Buttons for Billing Request -->
                                                  <div class="d-flex gap-2 mt-3">
                                                      <button type="button" class="btn btn-success flex-fill" id="approveBillingBtn">
                                                          <i class="fas fa-check me-1"></i> Approve
                                                      </button>
                                                      <button type="button" class="btn btn-outline-danger flex-fill" id="rejectBillingBtn">
                                                          <i class="fas fa-times me-1"></i> Reject
                                                      </button>
                                                  </div>
                                              </div>
                                          </div>
                                      </div>
                                  </div>
                                  
                                  <!-- Proof of Payment Upload Card -->
                                  <div class="col-md-6">
                                      <div class="card shadow-sm h-100">
                                          <div class="card-header bg-primary text-white">
                                              <h6 class="mb-0"><i class="fas fa-upload me-2"></i>Proof of Payment</h6>
                                          </div>
                                          <div class="card-body">
                                              <div class="border-2 border-dashed border-primary rounded p-4 text-center" id="paymentDropZone">
                                                  <i class="fas fa-file-pdf fa-3x text-primary mb-3"></i>
                                                  <h6 class="mb-2">Upload Proof of Payment</h6>
                                                  <p class="small text-muted mb-3">Drag & drop your payment receipt here</p>
                                                  <p class="small text-muted mb-0">or</p>
                                                  <button type="button" class="btn btn-outline-primary mt-2" id="browsePaymentBtn">
                                                      <i class="fas fa-folder-open me-1"></i> Browse Files
                                                  </button>
                                                  <input type="file" class="d-none" id="proofOfPayment" name="proof_of_payment" accept=".pdf,.jpg,.jpeg,.png">
                                                  <div class="mt-3">
                                                      <small class="text-muted payment-status">No file uploaded</small>
                                                  </div>
                                              </div>
                                              <div class="mt-3">
                                                  <small class="text-muted file-info" id="paymentFileInfo">No file selected</small>
                                              </div>
                                              
                                                                                             <!-- Upload and View Buttons for Proof of Payment -->
                                               <div class="mt-3 d-none" id="paymentActionSection">
                                                   <div class="d-flex gap-2">
                                                       <button type="button" class="btn btn-success flex-fill" id="uploadPaymentBtn">
                                                           <i class="fas fa-upload me-1"></i> Upload
                                                       </button>
                                                       <button type="button" class="btn btn-info flex-fill" id="viewPaymentBtn">
                                                           <i class="fas fa-eye me-1"></i> View
                                                       </button>
                                                   </div>
                                               </div>
                                          </div>
                                      </div>
                                  </div>
                              </div>
                             
                                                           <div class="alert alert-primary mt-4">
                                  <i class="fas fa-info-circle me-2"></i>
                                  Final review for billing approval. Please confirm all previous steps and upload proof of payment before submitting.
                              </div>
                             
                             <div class="d-flex justify-content-between mt-4">
                                 <button type="button" class="btn btn-outline-secondary prev-step" data-prev="5">
                                     <i class="fas fa-arrow-left me-1"></i> Previous
                                 </button>
                                 <button type="submit" class="btn btn-success">
                                     Finish <i class="fas fa-check ms-1"></i>
                                 </button>
                             </div>
                         </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    </div>

       <!-- Original Contract PDF Viewer -->
   <div class="modal fade" id="originalPdfModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Original Contract</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" style="height: 80vh;">
                    <iframe id="originalPdfViewer" class="w-100 h-100" frameborder="0"></iframe>
                </div>
                <div class="modal-footer">
                    <div>
                        <a id="originalPdfDownload" href="#" class="btn btn-primary">
                            <i class="fas fa-download me-1"></i> Download
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Project Manager Contract PDF Viewer -->
    <div class="modal fade" id="projectmanagerPdfModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Project Manager Contract</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" style="height: 80vh;">
                    <iframe id="projectmanagerPdfViewer" class="w-100 h-100" frameborder="0"></iframe>
                </div>
                <div class="modal-footer">
                    <div>
                        <a id="projectmanagerPdfDownload" href="#" class="btn btn-primary">
                            <i class="fas fa-download me-1"></i> Download
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="clientsignedPdfModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Client Signed Contract</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" style="height: 80vh;">
                    <iframe id="clientsignedPdfViewer" class="w-100 h-100" frameborder="0"></iframe>
                </div>
                <div class="modal-footer">
                    <div class="me-auto">
                        <button type="button" class="btn btn-danger delete-contract" data-contract-type="clientsigned" data-contract-id="">
                            <i class="fas fa-trash-alt me-1"></i> Delete
                        </button>
                    </div>
                    <div>
                        <a id="clientsignedPdfDownload" href="#" class="btn btn-primary">
                            <i class="fas fa-download me-1"></i> Download
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Permit Viewer Modal -->
    <div class="modal fade" id="permitViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="permitModalTitle">Permit Viewer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" style="height: 80vh;">
                    <iframe id="permitViewer" class="w-100 h-100" frameborder="0"></iframe>
                </div>
                <div class="modal-footer">
                    <div>
                        <a id="permitDownload" href="#" class="btn btn-primary" download>
                            <i class="fas fa-download me-1"></i> Download
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
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

    <!-- Success/Error Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center px-5 pb-5 pt-0">
                    <i id="successIcon" class="fas fa-check-circle mb-3" style="font-size: 5rem;"></i>
                    <h4 id="successTitle" class="mb-3">Success!</h4>
                    <p id="successMessage" class="text-muted mb-4"></p>
                    <div class="d-flex justify-content-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <style>
        #successIcon {
            font-size: 5rem !important;
        }
        .spinner-border {
            width: 2rem;
            height: 2rem;
            border-width: 0.2em;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
      <script src="js/blueprint_handler.js"></script>
      <script src="js/budget_handler.js"></script>
      <script src="js/contract_handler.js"></script>
      <script src="js/client_viewpermits.js"></script>
      <script src="js/proof_of_payment.js"></script>
      <script src="js/billing_handler.js"></script>

            <script>
                window.currentUserId = <?php echo isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 'null'; ?>;
            </script>

    <script>
    // Make currentProjectId globally available
    window.currentProjectId = <?php echo isset($_GET['project_id']) ? (int)$_GET['project_id'] : 'null'; ?>;
    console.log('Current Project ID:', window.currentProjectId);
    

    
    document.addEventListener('DOMContentLoaded', function() {
        // Get the current step from the server when the page loads
        function fetchCurrentStep() {
            // Get project_id from URL
            const urlParams = new URLSearchParams(window.location.search);
            const projectId = urlParams.get('project_id') || window.currentProjectId;
            
            let url = 'get_client_step.php';
            if (projectId) {
                url += `?project_id=${encodeURIComponent(projectId)}`;
            }
            
            return fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Fetched step:', data.current_step, 'for project:', projectId);
                        return data.current_step;
                    } else {
                        console.error('Failed to fetch current step:', data.message);
                        return 1; // Default to step 1 if there's an error
                    }
                })
                .catch(error => {
                    console.error('Error fetching current step:', error);
                    return 1; // Default to step 1 if there's an error
                });
        }

        // Save the current step to the server
        function saveCurrentStep(step) {
            // Get project_id from URL
            const urlParams = new URLSearchParams(window.location.search);
            const projectId = urlParams.get('project_id') || window.currentProjectId;
            
            const formData = new FormData();
            formData.append('new_step', step);
            if (projectId) {
                formData.append('project_id', projectId);
            }
            
            console.log('Saving step', step, 'for project:', projectId);
            
            return fetch('update_client_step.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Failed to save step:', data.message);
                } else {
                    console.log('Successfully saved step', step, 'for project:', projectId);
                }
                return data.success;
            })
            .catch(error => {
                console.error('Error saving step:', error);
                return false;
            });
        }
        // Get all step elements
        const steps = document.querySelectorAll('.step-content');
        const totalSteps = steps.length;
        let currentStep = 1;
        
        // Update the step indicators
        function updateStepIndicator() {
            // Update the progress bar - Fixed calculation for 6 steps
            const progress = ((currentStep - 1) / (totalSteps - 1)) * 100;
            console.log('Current step:', currentStep, 'Total steps:', totalSteps, 'Progress:', progress + '%');
            document.querySelector('.progress-bar').style.width = progress + '%';
            
            // Update active step indicators
            document.querySelectorAll('.step-number').forEach((step, index) => {
                // Reset all steps to default first
                step.classList.remove('bg-primary', 'bg-light', 'text-dark', 'text-white');
                
                if (index + 1 < currentStep) {
                    // Completed steps
                    step.classList.add('bg-primary', 'text-white');
                } else if (index + 1 === currentStep) {
                    // Current step
                    step.classList.add('bg-primary', 'text-white');
                } else {
                    // Upcoming steps
                    step.classList.add('bg-light', 'text-dark');
                }
            });
            
            // Update step labels
            document.querySelectorAll('.step-label').forEach((label, index) => {
                if (index + 1 === currentStep) {
                    label.classList.add('fw-bold', 'text-dark');
                } else {
                    label.classList.remove('fw-bold', 'text-dark');
                    label.classList.add('text-muted');
                }
            });
            
            // Show/hide navigation buttons
            document.querySelectorAll('.next-step').forEach(btn => {
                btn.style.display = currentStep < totalSteps ? 'block' : 'none';
            });
            
            document.querySelectorAll('.prev-step').forEach(btn => {
                btn.style.display = currentStep > 1 ? 'block' : 'none';
            });
        }
        
        // Function to show a specific step
        function showStep(stepNumber) {
            console.log('Showing step:', stepNumber);
            // Hide all steps
            document.querySelectorAll('.step-content').forEach(step => {
                step.classList.add('d-none');
            });
            
            // Show the requested step
            const stepElement = document.getElementById('step' + stepNumber);
            if (stepElement) {
                stepElement.classList.remove('d-none');
                console.log('Step element found and shown:', stepElement.id);
            } else {
                console.error('Step element not found for step:', stepNumber);
            }
            
            // Update the UI
            updateStepIndicator();
        }

        // Handle next step button clicks
        document.querySelectorAll('.next-step').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Get the next step from data attribute
                const nextStep = parseInt(this.getAttribute('data-next') || (currentStep + 1));
                console.log('Next button clicked. Current step:', currentStep, 'Next step:', nextStep);
                
                // Validate current step before proceeding
                if (validateStep(currentStep)) {
                    // Save the step first, then update UI
                    saveCurrentStep(nextStep).then(() => {
                        currentStep = nextStep;
                        console.log('Moving to step:', currentStep);
                        showStep(currentStep);
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    });
                }
            });
        });
        
        // Handle previous step button clicks
        document.querySelectorAll('.prev-step').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Get the previous step from data attribute
                const prevStep = parseInt(this.getAttribute('data-prev') || (currentStep - 1));
                
                // Save the step first, then update UI
                saveCurrentStep(prevStep).then(() => {
                    currentStep = prevStep;
                    showStep(currentStep);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            });
        });
        
        // Function to validate the current step
        function validateStep(step) {
            // Add your validation logic here for each step
            // Return true if validation passes, false otherwise
            return true; // For now, always return true
        }
        
                 // Initialize the form to show the saved step or first step
         fetchCurrentStep().then(savedStep => {
             currentStep = savedStep;
             showStep(currentStep);
             updateStepIndicator();
             
             // If we're on step 1, load the blueprints
             if (currentStep === 1) {
                 console.log('Initial load of step 1, loading blueprints...');
                 loadBlueprints();
             }
             
             // Also add event listener for when step changes to 1
             document.addEventListener('stepChanged', function(e) {
                 if (e.detail.step === 1) {
                     console.log('Step changed to 1, loading blueprints...');
                     loadBlueprints();
                 }
             });
             
             // If we're on step 6, initialize the task progress chart
             if (currentStep === 6) {
                 console.log('Initial load of step 6, initializing task progress chart...');
                 initializeTaskProgressChart();
             }
         });
         
         // Function to initialize task progress chart for step 6
         function initializeTaskProgressChart() {
             const ctx = document.getElementById('taskProgressChartStep6');
             if (!ctx) return;
             
             // Fetch task data from project_timeline
             const projectId = window.currentProjectId;
             if (!projectId) return;
             
             fetch(`get_project_tasks.php?project_id=${projectId}`)
                 .then(response => response.json())
                 .then(data => {
                     if (data.success && data.tasks) {
                         createTaskProgressChart(ctx, data.tasks);
                     } else {
                         // Show no data message
                         ctx.parentElement.innerHTML = `
                             <div class="text-center py-5">
                                 <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                                 <h6 class="text-muted">No tasks found</h6>
                                 <p class="small text-muted">No tasks have been added to the project timeline yet.</p>
                             </div>
                         `;
                     }
                 })
                 .catch(error => {
                     console.error('Error fetching task data:', error);
                     ctx.parentElement.innerHTML = `
                         <div class="text-center py-5">
                             <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                             <h6 class="text-warning">Error loading tasks</h6>
                             <p class="small text-muted">Failed to load task data. Please try again.</p>
                         </div>
                     `;
                 });
         }
         
         // Function to create the task progress chart
         function createTaskProgressChart(ctx, tasks) {
             const taskNames = tasks.map(task => task.task_name);
             const taskProgress = tasks.map(task => parseInt(task.progress) || 0);
             const taskStatus = tasks.map(task => task.status || 'Not Started');
             
             // Color coding based on status
             const backgroundColor = taskStatus.map(status => {
                 switch(status.toLowerCase()) {
                     case 'completed': return 'rgba(40, 167, 69, 0.7)'; // Green
                     case 'in progress': return 'rgba(255, 193, 7, 0.7)'; // Yellow
                     case 'not started': return 'rgba(108, 117, 125, 0.7)'; // Gray
                     default: return 'rgba(13, 110, 253, 0.7)'; // Blue
                 }
             });
             
             const borderColor = taskStatus.map(status => {
                 switch(status.toLowerCase()) {
                     case 'completed': return 'rgba(40, 167, 69, 1)';
                     case 'in progress': return 'rgba(255, 193, 7, 1)';
                     case 'not started': return 'rgba(108, 117, 125, 1)';
                     default: return 'rgba(13, 110, 253, 1)';
                 }
             });
             
             new Chart(ctx, {
                 type: 'bar',
                 data: {
                     labels: taskNames,
                     datasets: [{
                         label: 'Task Progress (%)',
                         data: taskProgress,
                         backgroundColor: backgroundColor,
                         borderColor: borderColor,
                         borderWidth: 1,
                         borderRadius: 4
                     }]
                 },
                 options: {
                     indexAxis: 'y',
                     responsive: true,
                     maintainAspectRatio: false,
                     scales: {
                         x: {
                             beginAtZero: true,
                             max: 100,
                             title: {
                                 display: true,
                                 text: 'Progress (%)',
                                 font: { weight: 'bold' }
                             },
                             grid: { display: false }
                         },
                         y: {
                             grid: { display: false },
                             ticks: { autoSkip: false }
                         }
                     },
                     plugins: {
                         legend: { display: false },
                         tooltip: {
                             callbacks: {
                                 label: function(context) {
                                     const taskIndex = context.dataIndex;
                                     const status = taskStatus[taskIndex];
                                     return `${context.raw}% (${status})`;
                                 }
                             }
                         }
                     }
                 }
             });
         }
         
                  // Event listener for when step changes to 6
         document.addEventListener('stepChanged', function(e) {
             if (e.detail.step === 6) {
                 console.log('Step changed to 6, initializing task progress chart...');
                 setTimeout(initializeTaskProgressChart, 100); // Small delay to ensure DOM is ready
             }
         });
     });
     </script>
     
           <!-- JavaScript files are now loaded separately for better organization -->
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

</body>

</html>