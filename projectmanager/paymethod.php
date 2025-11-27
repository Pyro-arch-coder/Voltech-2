<?php


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    session_start();
    include_once "../config.php";
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
    <title>Project Manager Payment Methods</title>
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

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-align-left primary-text fs-4 me-3" id="menu-toggle"></i>
                    <h2 class="fs-2 m-0">Positions</h2>
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
                <div class="row">
                    <div class="col-12">
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Payment Methods</h5>
                                <div>
                                    <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#bankTransferModal">
                                        <i class="fas fa-plus me-1"></i> Add Bank Account
                                    </button>
                                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#gcashModal">
                                        <i class="fas fa-mobile-alt me-1"></i> Add GCash
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <ul class="nav nav-tabs" id="paymentTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="bank-tab" data-bs-toggle="tab" data-bs-target="#bank-accounts" type="button" role="tab" aria-controls="bank-accounts" aria-selected="true">
                                            <i class="fas fa-university me-1"></i> Bank Accounts
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="gcash-tab" data-bs-toggle="tab" data-bs-target="#gcash-accounts" type="button" role="tab" aria-controls="gcash-accounts" aria-selected="false">
                                            <i class="fas fa-mobile-alt me-1"></i> GCash Accounts
                                        </button>
                                    </li>
                                </ul>
                                <div class="tab-content p-3 border border-top-0 rounded-bottom" id="paymentTabsContent">
                                    <!-- Bank Accounts Tab -->
                                    <div class="tab-pane fade show active" id="bank-accounts" role="tabpanel" aria-labelledby="bank-tab">
                                        <div class="table-responsive">
                                            <table class="table table-hover" id="bankAccountsTable">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Bank Name</th>
                                                        <th>Account Name</th>
                                                        <th>Account Number</th>
                                                        <th>Contact Number</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $bank_query = "SELECT * FROM bank_accounts WHERE is_active = 1 AND bank_name NOT LIKE '%gcash%' ORDER BY bank_name, account_name";
                                                    $bank_result = $con->query($bank_query);
                                                    
                                                    if ($bank_result && $bank_result->num_rows > 0) {
                                                        while ($bank = $bank_result->fetch_assoc()) {
                                                            echo "<tr>";
                                                            echo "<td>" . htmlspecialchars($bank['bank_name']) . "</td>";
                                                            echo "<td>" . htmlspecialchars($bank['account_name']) . "</td>";
                                                            echo "<td>" . htmlspecialchars($bank['account_number']) . "</td>";
                                                            echo "<td>" . (!empty($bank['contact_number']) ? htmlspecialchars($bank['contact_number']) : 'N/A') . "</td>";
                                                            echo "<td>";
                                                            echo "<button class='btn btn-sm btn-outline-primary me-1 edit-bank' data-id='" . $bank['id'] . "' data-bank='" . htmlspecialchars($bank['bank_name']) . "' data-account='" . htmlspecialchars($bank['account_name']) . "' data-number='" . htmlspecialchars($bank['account_number']) . "' data-contact='" . htmlspecialchars($bank['contact_number'] ?? '') . "'><i class='fas fa-edit'></i></button>";
                                                            echo "<button class='btn btn-sm btn-outline-danger delete-bank' data-id='" . $bank['id'] . "'><i class='fas fa-trash'></i></button>";
                                                            echo "</td>";
                                                            echo "</tr>";
                                                        }
                                                    } else {
                                                        echo "<tr><td colspan='5' class='text-center text-muted py-4'>No bank accounts found</td></tr>";
                                                    }
                                                    ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <!-- GCash Accounts Tab -->
                                    <div class="tab-pane fade" id="gcash-accounts" role="tabpanel" aria-labelledby="gcash-tab">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>GCash Number</th>
                                                        <th>Account Name</th>
                                                        <th>Date Added</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $gcash_query = "SELECT * FROM gcash_settings WHERE is_active = 1 ORDER BY created_at DESC";
                                                    $gcash_result = $con->query($gcash_query);
                                                    
                                                    if ($gcash_result && $gcash_result->num_rows > 0) {
                                                        while ($gcash = $gcash_result->fetch_assoc()) {
                                                            echo "<tr>";
                                                            echo "<td>" . htmlspecialchars($gcash['gcash_number']) . "</td>";
                                                            echo "<td>" . htmlspecialchars($gcash['account_name']) . "</td>";
                                                            echo "<td>" . date('M d, Y', strtotime($gcash['created_at'] ?? 'now')) . "</td>";
                                                            echo "<td>";
                                                            echo "<button class='btn btn-sm btn-outline-primary me-1 edit-gcash' data-id='" . $gcash['id'] . "' data-number='" . htmlspecialchars($gcash['gcash_number']) . "' data-name='" . htmlspecialchars($gcash['account_name']) . "'><i class='fas fa-edit'></i></button>";
                                                            echo "<button class='btn btn-sm btn-outline-danger delete-gcash' data-id='" . $gcash['id'] . "'><i class='fas fa-trash'></i></button>";
                                                            echo "</td>";
                                                            echo "</tr>";
                                                        }
                                                    } else {
                                                        echo "<tr><td colspan='4' class='text-center text-muted py-4'>No GCash accounts found</td></tr>";
                                                    }
                                                    ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Edit Bank Account Modal -->
            <div class="modal fade" id="editBankModal" tabindex="-1" aria-labelledby="editBankModalLabel" aria-hidden="true" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="editBankModalLabel">
                                <i class="fas fa-edit me-2"></i>Edit Bank Account
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="editBankForm" method="POST" action="save_bank_accounts.php">
                            <input type="hidden" name="bank_id" id="edit_bank_id">
                            <div class="modal-body p-4">
                                <div class="mb-3">
                                    <label for="edit_bank_name" class="form-label fw-bold">Bank Name <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-university"></i></span>
                                        <input type="text" class="form-control" id="edit_bank_name" name="bank_name" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_account_name" class="form-label fw-bold">Account Name <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="edit_account_name" name="account_name" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_account_number" class="form-label fw-bold">Account Number <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                        <input type="text" class="form-control" id="edit_account_number" name="account_number" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_contact_number" class="form-label fw-bold">Contact Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        <input type="text" class="form-control" id="edit_contact_number" name="contact_number" placeholder="Optional">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer bg-light">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i> Cancel
                                </button>
                                <button type="submit" name="update_bank" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Update Account
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Delete Confirmation Modal for Bank -->
            <div class="modal fade" id="deleteBankModal" tabindex="-1" aria-labelledby="deleteBankModalLabel" aria-hidden="true" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="deleteBankModalLabel">
                                <i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="deleteBankForm" method="POST" action="delete_bank_account.php">
                            <input type="hidden" name="bank_id" id="delete_bank_id">
                            <div class="modal-body p-4 text-center">
                                <div class="mb-4">
                                    <div class="icon-box danger-icon-box mx-auto mb-3">
                                        <i class="fas fa-trash-alt"></i>
                                    </div>
                                    <h5 class="fw-bold text-danger mb-3">Delete Bank Account?</h5>
                                    <p class="text-muted">Are you sure you want to delete this bank account? This action cannot be undone.</p>
                                    
                                    <div class="card border-0 bg-light p-3 mb-3">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-university text-primary me-2"></i>
                                            <strong>Bank:</strong> 
                                            <span class="ms-2" id="delete_bank_name"></span>
                                        </div>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-user text-primary me-2"></i>
                                            <strong>Account Name:</strong> 
                                            <span class="ms-2" id="delete_account_name"></span>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-hashtag text-primary me-2"></i>
                                            <strong>Account #:</strong> 
                                            <span class="ms-2" id="delete_account_number"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer justify-content-center border-top-0 pt-0">
                                <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i> Cancel
                                </button>
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-trash-alt me-1"></i> Delete Account
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Bank Transfer Modal -->
            <div class="modal fade" id="bankTransferModal" tabindex="-1" aria-labelledby="bankTransferModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="bankTransferModalLabel">Add Bank Account</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" action="save_bank_accounts.php" id="bankTransferForm">
                            <div class="modal-body">
                                <?php if (isset($_SESSION['bank_error'])): ?>
                                    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['bank_error']); unset($_SESSION['bank_error']); ?></div>
                                <?php endif; ?>
                                <?php if (isset($_SESSION['bank_success'])): ?>
                                    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['bank_success']); unset($_SESSION['bank_success']); ?></div>
                                <?php endif; ?>
                                <input type="hidden" id="edit_bank_id" name="id" value="">
                                <div class="mb-3">
                                    <label for="bank_name" class="form-label">Bank Name</label>
                                    <input type="text" class="form-control" id="bank_name" name="bank_name" required
                                           value="<?php echo isset($_POST['bank_name']) ? htmlspecialchars($_POST['bank_name']) : ''; ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="account_name" class="form-label">Account Name</label>
                                    <input type="text" class="form-control" id="account_name" name="account_name" required
                                           value="<?php echo isset($_POST['account_name']) ? htmlspecialchars($_POST['account_name']) : ''; ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="account_number" class="form-label">Account Number</label>
                                    <input type="text" class="form-control" id="account_number" name="account_number" required
                                           value="<?php echo isset($_POST['account_number']) ? htmlspecialchars($_POST['account_number']) : ''; ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="contact_number" class="form-label">Contact Number (Optional)</label>
                                    <input type="text" class="form-control" id="contact_number" name="contact_number"
                                           value="<?php echo isset($_POST['contact_number']) ? htmlspecialchars($_POST['contact_number']) : ''; ?>">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="save_bank" class="btn btn-primary">Save Bank Account</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Edit GCash Modal -->
            <div class="modal fade" id="editGcashModal" tabindex="-1" aria-labelledby="editGcashModalLabel" aria-hidden="true" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="editGcashModalLabel">
                                <i class="fas fa-mobile-alt me-2"></i>Edit GCash Account
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="editGcashForm" method="POST" action="save_gcash.php">
                            <input type="hidden" name="gcash_id" id="edit_gcash_id">
                            <div class="modal-body p-4">
                                <div class="mb-4">
                                    <label for="edit_gcash_number" class="form-label fw-bold">GCash Number <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        <input type="text" class="form-control" id="edit_gcash_number" name="gcash_number" required 
                                               placeholder="09XXXXXXXXX" pattern="[0-9]{11}" title="Please enter a valid 11-digit GCash number">
                                    </div>
                                    <small class="form-text text-muted">Format: 09XXXXXXXXX (11 digits)</small>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_gcash_account_name" class="form-label fw-bold">Account Name <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="edit_gcash_account_name" name="account_name" required>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer bg-light">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i> Cancel
                                </button>
                                <button type="submit" name="update_gcash" class="btn btn-success">
                                    <i class="fas fa-save me-1"></i> Update GCash
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Delete GCash Confirmation Modal -->
            <div class="modal fade" id="deleteGcashModal" tabindex="-1" aria-labelledby="deleteGcashModalLabel" aria-hidden="true" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="deleteGcashModalLabel">
                                <i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="deleteGcashForm" method="POST" action="delete_gcash.php">
                            <input type="hidden" name="gcash_id" id="delete_gcash_id">
                            <div class="modal-body p-4 text-center">
                                <div class="mb-4">
                                    <div class="icon-box danger-icon-box mx-auto mb-3">
                                        <i class="fas fa-trash-alt"></i>
                                    </div>
                                    <h5 class="fw-bold text-danger mb-3">Delete GCash Account?</h5>
                                    <p class="text-muted">Are you sure you want to delete this GCash account? This action cannot be undone.</p>
                                    
                                    <div class="card border-0 bg-light p-3">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-mobile-alt text-success me-2"></i>
                                            <strong>GCash #:</strong> 
                                            <span class="ms-2" id="delete_gcash_number"></span>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-user text-success me-2"></i>
                                            <strong>Account Name:</strong> 
                                            <span class="ms-2" id="delete_gcash_name"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer justify-content-center border-top-0 pt-0">
                                <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i> Cancel
                                </button>
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-trash-alt me-1"></i> Delete GCash
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- GCash Modal -->
            <div class="modal fade" id="gcashModal" tabindex="-1" aria-labelledby="gcashModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="gcashModalLabel">Add GCash Account</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" action="save_gcash.php" id="gcashForm">
                            <div class="modal-body">
                                <?php if (isset($_SESSION['gcash_error'])): ?>
                                    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['gcash_error']); unset($_SESSION['gcash_error']); ?></div>
                                <?php endif; ?>
                                <?php if (isset($_SESSION['gcash_success'])): ?>
                                    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['gcash_success']); unset($_SESSION['gcash_success']); ?></div>
                                <?php endif; ?>
                                <div class="mb-3">
                                    <label for="gcash_number" class="form-label">GCash Number</label>
                                    <input type="text" class="form-control" id="gcash_number" name="gcash_number" required 
                                           value="<?php echo isset($_POST['gcash_number']) ? htmlspecialchars($_POST['gcash_number']) : ''; ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="gcash_account_name" class="form-label">Account Name</label>
                                    <input type="text" class="form-control" id="gcash_account_name" name="account_name" required
                                           value="<?php echo isset($_POST['account_name']) ? htmlspecialchars($_POST['account_name']) : ''; ?>">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="save_gcash" class="btn btn-success">Save GCash Account</button>
                            </div>
                        </form>
                    </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <script>
      if (typeof feather !== 'undefined') { feather.replace(); }
    </script>
   
    <script>
        feather.replace();
        
        // Bank Transfer Form Validation
        document.addEventListener('DOMContentLoaded', function() {
            const bankTransferForm = document.getElementById('bankTransferForm');
            if (bankTransferForm) {
                bankTransferForm.addEventListener('submit', function(e) {
                    const accountName = document.getElementById('account_name').value.trim();
                    const accountNumber = document.getElementById('account_number').value.trim();
                    const contactNumber = document.getElementById('contact_number').value.trim();
                    
                    // Validate Account Name (only letters and spaces)
                    if (!/^[a-zA-Z\s]+$/.test(accountName)) {
                        e.preventDefault();
                        alert('Account name can only contain letters and spaces.');
                        document.getElementById('account_name').focus();
                        return false;
                    }
                    
                    // Validate Account Number (only numbers)
                    if (!/^\d+$/.test(accountNumber)) {
                        e.preventDefault();
                        alert('Account number can only contain numbers.');
                        document.getElementById('account_number').focus();
                        return false;
                    }
                    
                    // Validate Contact Number (only numbers, if provided)
                    if (contactNumber && !/^\d+$/.test(contactNumber)) {
                        e.preventDefault();
                        alert('Contact number can only contain numbers.');
                        document.getElementById('contact_number').focus();
                        return false;
                    }
                    
                    return true;
                });
            }
            
            // Add input validation for Account Name (only letters and spaces)
            const bankAccountNameInput = document.getElementById('account_name');
            if (bankAccountNameInput) {
                bankAccountNameInput.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
                });
            }
            
            // Add input validation for Account Number (only numbers)
            const bankAccountNumberInput = document.getElementById('account_number');
            if (bankAccountNumberInput) {
                bankAccountNumberInput.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            }
            
            // Add input validation for Contact Number (only numbers)
            const contactNumberInput = document.getElementById('contact_number');
            if (contactNumberInput) {
                contactNumberInput.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            }
        });
        
        // GCash Form Validation
        document.addEventListener('DOMContentLoaded', function() {
            const gcashForm = document.getElementById('gcashForm');
            if (gcashForm) {
                gcashForm.addEventListener('submit', function(e) {
                    const gcashNumber = document.getElementById('gcash_number').value.trim();
                    const accountName = document.getElementById('gcash_account_name').value.trim();
                    
                    // Validate GCash Number (must start with 09 and be 11 digits)
                    if (!/^09\d{9}$/.test(gcashNumber)) {
                        e.preventDefault();
                        alert('GCash number must start with 09 and be 11 digits long.');
                        document.getElementById('gcash_number').focus();
                        return false;
                    }
                    
                    // Validate Account Name (only letters and spaces)
                    if (!/^[a-zA-Z\s]+$/.test(accountName)) {
                        e.preventDefault();
                        alert('Account name can only contain letters and spaces.');
                        document.getElementById('gcash_account_name').focus();
                        return false;
                    }
                    
                    return true;
                });
            }
            
            // Add input validation for GCash number (only numbers)
            const gcashNumberInput = document.getElementById('gcash_number');
            if (gcashNumberInput) {
                gcashNumberInput.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            }
            
            // Add input validation for Account Name (only letters and spaces)
            const accountNameInput = document.getElementById('gcash_account_name');
            if (accountNameInput) {
                accountNameInput.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
                });
            }
        });
        
        // Manual dropdown handling for sidebar (vanilla JS)
        document.addEventListener('DOMContentLoaded', function() {
            // Bank Account Edit Button Click Handler
            document.querySelectorAll('.edit-bank').forEach(button => {
                button.addEventListener('click', function() {
                    const bankId = this.getAttribute('data-id');
                    const bankName = this.getAttribute('data-bank');
                    const accountName = this.getAttribute('data-account');
                    const accountNumber = this.getAttribute('data-number');
                    const contactNumber = this.getAttribute('data-contact') || '';
                    
                    document.getElementById('edit_bank_id').value = bankId;
                    document.getElementById('edit_bank_name').value = bankName;
                    document.getElementById('edit_account_name').value = accountName;
                    document.getElementById('edit_account_number').value = accountNumber;
                    document.getElementById('edit_contact_number').value = contactNumber;
                    
                    const editModal = new bootstrap.Modal(document.getElementById('editBankModal'));
                    editModal.show();
                });
            });
            
            // Bank Account Delete Button Click Handler
            document.querySelectorAll('.delete-bank').forEach(button => {
                button.addEventListener('click', function() {
                    const bankId = this.getAttribute('data-id');
                    const bankName = this.closest('tr').querySelector('td:first-child').textContent;
                    const accountName = this.closest('tr').querySelector('td:nth-child(2)').textContent;
                    const accountNumber = this.closest('tr').querySelector('td:nth-child(3)').textContent;
                    
                    document.getElementById('delete_bank_id').value = bankId;
                    document.getElementById('delete_bank_name').textContent = bankName;
                    document.getElementById('delete_account_name').textContent = accountName;
                    document.getElementById('delete_account_number').textContent = accountNumber;
                    
                    const deleteModal = new bootstrap.Modal(document.getElementById('deleteBankModal'));
                    deleteModal.show();
                });
            });
            
            // GCash Edit Button Click Handler
            document.querySelectorAll('.edit-gcash').forEach(button => {
                button.addEventListener('click', function() {
                    const gcashId = this.getAttribute('data-id');
                    const gcashNumber = this.getAttribute('data-number');
                    const accountName = this.getAttribute('data-account');
                    
                    document.getElementById('edit_gcash_id').value = gcashId;
                    document.getElementById('edit_gcash_number').value = gcashNumber;
                    document.getElementById('edit_gcash_account_name').value = accountName;
                    
                    const editModal = new bootstrap.Modal(document.getElementById('editGcashModal'));
                    editModal.show();
                });
            });
            
            // GCash Delete Button Click Handler
            document.querySelectorAll('.delete-gcash').forEach(button => {
                button.addEventListener('click', function() {
                    const gcashId = this.getAttribute('data-id');
                    const gcashNumber = this.closest('tr').querySelector('td:first-child').textContent;
                    const accountName = this.closest('tr').querySelector('td:nth-child(2)').textContent;
                    
                    document.getElementById('delete_gcash_id').value = gcashId;
                    document.getElementById('delete_gcash_number').textContent = gcashNumber;
                    document.getElementById('delete_gcash_name').textContent = accountName;
                    
                    const deleteModal = new bootstrap.Modal(document.getElementById('deleteGcashModal'));
                    deleteModal.show();
                });
            });
            
            // Reset forms when modals are closed
            const bankModal = document.getElementById('bankTransferModal');
            if (bankModal) {
                bankModal.addEventListener('hidden.bs.modal', function () {
                    this.querySelector('form').reset();
                });
            }
            
            const gcashModal = document.getElementById('gcashModal');
            if (gcashModal) {
                gcashModal.addEventListener('hidden.bs.modal', function () {
                    this.querySelector('form').reset();
                });
            }
            document.querySelectorAll('.dropdown-toggle').forEach(function(toggle) {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    // Toggle the current dropdown
                    var menu = this.nextElementSibling;
                    if (menu) menu.classList.toggle('show');
                    // Close other dropdowns
                    document.querySelectorAll('.dropdown-toggle').forEach(function(other) {
                        if (other !== toggle) {
                            var otherMenu = other.nextElementSibling;
                            if (otherMenu) otherMenu.classList.remove('show');
                        }
                    });
                });
            });
            // Close dropdowns when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.dropdown')) {
                    document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
                        menu.classList.remove('show');
                    });
                }
            });
            // Toggle sidebar
            var menuToggle = document.getElementById('menu-toggle');
            if (menuToggle) {
                menuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.getElementById('wrapper').classList.toggle('toggled');
                });
            }
        });
</script>
<script>
// Validation for Position Title (Add & Edit)
function isValidTitle(title) {
  // Only allow letters (upper/lower) and spaces
  return /^[A-Za-z ]+$/.test(title.trim());
}
function showTitleError(input, msg) {
  input.setCustomValidity(msg);
  input.reportValidity();
}
document.addEventListener('DOMContentLoaded', function() {
  // Add Position Modal
  var addForm = document.querySelector('#addPositionModal form');
  if (addForm) {
    var addTitle = document.getElementById('modal_title');
    addForm.addEventListener('submit', function(e) {
      if (!isValidTitle(addTitle.value)) {
        showTitleError(addTitle, 'Position Title must only contain letters and spaces.');
        e.preventDefault();
      } else {
        addTitle.setCustomValidity('');
      }
    });
    addTitle.addEventListener('input', function() {
      addTitle.setCustomValidity('');
    });
  }
  // Edit Position Modal
  var editForm = document.getElementById('newEditPositionForm');
  if (editForm) {
    var editTitle = document.getElementById('new_edit_title');
    editForm.addEventListener('submit', function(e) {
      if (!isValidTitle(editTitle.value)) {
        showTitleError(editTitle, 'Position Title must only contain letters and spaces.');
        e.preventDefault();
      } else {
        editTitle.setCustomValidity('');
      }
    });
    editTitle.addEventListener('input', function() {
      editTitle.setCustomValidity('');
    });
  }
});
</script>
<script>
// Prevent numbers and special characters in Position Title fields (Add & Edit)
document.addEventListener('DOMContentLoaded', function() {
  function filterTitleInput(e) {
    // Only allow letters and spaces
    let value = e.target.value;
    let filtered = value.replace(/[^A-Za-z ]+/g, '');
    if (value !== filtered) {
      e.target.value = filtered;
    }
  }
  var addTitle = document.getElementById('modal_title');
  if (addTitle) {
    addTitle.addEventListener('input', filterTitleInput);
  }
  var editTitle = document.getElementById('new_edit_title');
  if (editTitle) {
    editTitle.addEventListener('input', filterTitleInput);
  }
});
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
