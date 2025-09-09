<?php
    session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 5) {
    header("Location: ../login.php");
    exit();
}
require_once '../config.php';

// Check for success or error messages
if (isset($_SESSION['success'])) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            showFeedbackModal(true, '" . addslashes($_SESSION['success']) . "');
        });
    </script>";
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            showFeedbackModal(false, '" . addslashes($_SESSION['error']) . "');
        });
    </script>";
    unset($_SESSION['error']);
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

    // Get the logged-in user's supplier ID and name using email
    $supplier_id = 0;
    $supplier_name = '';
    $supplier_query = "SELECT id, supplier_name 
                      FROM suppliers 
                      WHERE email = ?";
    $stmt = $con->prepare($supplier_query);
    $stmt->bind_param('s', $user_email);
    $stmt->execute();
    $supplier_result = $stmt->get_result();
    
    if ($supplier_row = $supplier_result->fetch_assoc()) {
        $supplier_id = $supplier_row['id'];
        $supplier_name = $supplier_row['supplier_name'];
    }
    $stmt->close();

    // Pagination variables (restored)
    $results_per_page = 10;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $start_from = ($page - 1) * $results_per_page;
    
    // Check if there are any back_orders with this supplier's email
    $check_backorders = $con->prepare("
        SELECT COUNT(*) as total
        FROM back_orders bo
        JOIN suppliers s ON bo.supplier_name = s.supplier_name
        WHERE s.email = ?
        AND bo.status = 'Pending'
        AND bo.reason != 'Reorder'
    ");
    $check_backorders->bind_param('s', $user_email);
    $check_backorders->execute();
    $backorder_check = $check_backorders->get_result()->fetch_assoc();
    $check_backorders->close();
    
    // Fetch pending backorders (exclude 'Reorder' reason) for the logged-in supplier
    $backorder_query = "SELECT bo.*, m.material_name, m.category as category_name, u.firstname, u.lastname, u.email as requester_email 
                       FROM back_orders bo 
                       LEFT JOIN materials m ON bo.material_id = m.id 
                       JOIN users u ON bo.requested_by = u.id
                       WHERE bo.status = 'Pending' 
                       AND bo.reason != 'Reorder'
                       AND bo.supplier_name = ?
                       ORDER BY bo.created_at DESC";
    $backorder_stmt = $con->prepare($backorder_query);
    $backorder_stmt->bind_param('s', $supplier_name);
    $backorder_stmt->execute();
    $backorder_result = $backorder_stmt->get_result();
    $total_backorders = $backorder_result ? $backorder_result->num_rows : 0;
    
    // Check if there are any reorders for this supplier
    $check_reorders = $con->prepare("
        SELECT COUNT(*) as total
        FROM back_orders bo
        WHERE bo.supplier_name = ?
        AND bo.status = 'Pending'
        AND bo.reason = 'Reorder'
    ");
    $check_reorders->bind_param('s', $supplier_name);
    $check_reorders->execute();
    $reorder_check = $check_reorders->get_result()->fetch_assoc();
    $check_reorders->close();
    
    // Fetch pending reorders (only 'Reorder' reason) for the logged-in supplier
    $reorder_query = "SELECT bo.*, m.material_name, m.category as category_name, u.firstname, u.lastname, u.email as requester_email 
                     FROM back_orders bo 
                     LEFT JOIN materials m ON bo.material_id = m.id 
                     JOIN users u ON bo.requested_by = u.id
                     WHERE bo.status = 'Pending' 
                     AND bo.reason = 'Reorder'
                     AND bo.supplier_name = ?
                     ORDER BY bo.created_at DESC";
    $reorder_stmt = $con->prepare($reorder_query);
    $reorder_stmt->bind_param('s', $supplier_name);
    $reorder_stmt->execute();
    $reorder_result = $reorder_stmt->get_result();
    $total_reorders = $reorder_result ? $reorder_result->num_rows : 0;
    
    // Get supplier info by email
    $supplier_check_query = "SELECT * FROM suppliers WHERE email = ?";
    $supplier_check_stmt = $con->prepare($supplier_check_query);
    $supplier_check_stmt->bind_param('s', $user_email);
    $supplier_check_stmt->execute();
    $supplier_check_result = $supplier_check_stmt->get_result();
    
    if ($supplier_check_result->num_rows > 0) {
        $supplier = $supplier_check_result->fetch_assoc();
        $supplier_name = $supplier['supplier_name'];
        $supplier_id = $supplier['id'];
    }
    $supplier_check_stmt->close();
    
    // Fetch pending materials for the matched supplier
    if (!empty($supplier_name)) {
        $materials_query = "SELECT m.*, m.category as category_name, u.firstname, u.lastname 
                          FROM materials m 
                          LEFT JOIN users u ON m.user_id = u.id
                          WHERE m.delivery_status = 'Pending' 
                          AND m.supplier_name = ?
                          ORDER BY m.purchase_date DESC";
        $materials_stmt = $con->prepare($materials_query);
        $materials_stmt->bind_param('s', $supplier_name);
        $materials_stmt->execute();
        $materials_result = $materials_stmt->get_result();
        $total_materials = $materials_result ? $materials_result->num_rows : 0;
    } else {
        $materials_result = false;
        $total_materials = 0;
    }

    // Handle category operations
      // --- Add New Category Handler ---
   

    // Build search conditions
    $conditions = [];
    $params = [];
    $types = '';
    
    // Always filter by logged-in user's email
    $conditions[] = "email = ?";
    $params[] = $user_email;
    $types .= 's';
    
    // Add search condition if provided
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $conditions[] = "(category LIKE ? OR description LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $types .= 'ss';
    }
    
    // Build WHERE clause
    $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
    
    // Count total categories for the logged-in user
    $count_sql = "SELECT COUNT(*) AS total FROM supplier_category $where_clause";
    $count_stmt = $con->prepare($count_sql);
    if (!empty($params)) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();
    
    $total_pages = ceil($total_records / $results_per_page);

    // Fetch categories with pagination for the logged-in user
    $categories_sql = "SELECT * FROM supplier_category $where_clause ORDER BY created_at DESC LIMIT ?, ?";
    $stmt = $con->prepare($categories_sql);
    
    // Add pagination parameters
    $params[] = $start_from;
    $params[] = $results_per_page;
    $types .= 'ii';
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $categories_result = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <link rel="stylesheet" href="supplier_style.css" />
    <title>Supplier Approval Dashboard</title>
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
                <a href="supplier_dashboard.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'supplier_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>Dashboard
                </a>
                <a href="supplier_materials.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'supplier_materials.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cubes"></i>Materials
                </a>
                <a href="supplier_category.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'supplier_category.php' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i>Category
                </a>
                <a href="supplier_approval.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'supplier_approval.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-check"></i>Order Management
                </a>
                <a href="supplier_order_history.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'supplier_order_history.php' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i>Order History
                </a>
            </div>
        </div>
        <!-- /#sidebar-wrapper -->

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-align-left primary-text fs-4 me-3" id="menu-toggle"></i>
                    <h2 class="fs-2 m-0">Supplier Approval</h2>
                </div>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

              
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                    <?php 
                    include 'supplier_notification.php'; 
                    
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
                    $tables = ['pm_supplier_messages', 'pm_procurement_messages'];
                    $unreadCount = countUnreadMessages($con, $tables, $_SESSION['user_id']);
                    ?>
                    <li class="nav-item ms-2">
                        <a class="nav-link position-relative" href="supplier_profile.php" title="Messages">
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
                                <li><a class="dropdown-item" href="supplier_profile.php">Profile</a></li>
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal">Change Password</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>
            <div class="container-fluid px-4 py-4">
                <div class="card shadow rounded-3 border-0">
                    <div class="card-header bg-white py-3">
                        <h4 class="mb-0">Supplier Approval Dashboard</h4>
                        <p class="text-muted mb-0">Review and manage pending approvals</p>
                    </div>
                    <div class="card-body p-0">
                        <!-- Tabs Navigation -->
                        <ul class="nav nav-tabs nav-fill border-bottom" id="supplierTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active py-3" id="backorders-tab" data-bs-toggle="tab" data-bs-target="#backorders" type="button" role="tab" aria-controls="backorders" aria-selected="true">
                                    <i class="fas fa-shopping-cart me-2"></i>Backorders
                                    <span class="badge bg-danger ms-2"><?php echo $total_backorders; ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link py-3" id="reorders-tab" data-bs-toggle="tab" data-bs-target="#reorders" type="button" role="tab" aria-controls="reorders" aria-selected="false">
                                    <i class="fas fa-redo me-2"></i>Reorders
                                    <span class="badge bg-warning ms-2"><?php echo $total_reorders; ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link py-3" id="materials-tab" data-bs-toggle="tab" data-bs-target="#materials" type="button" role="tab" aria-controls="materials" aria-selected="false">
                                    <i class="fas fa-boxes me-2"></i>Materials
                                    <span class="badge bg-info ms-2"><?php echo $total_materials; ?></span>
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content p-4" id="supplierTabsContent">
                            <!-- Backorders Tab -->
                            <div class="tab-pane fade show active" id="backorders" role="tabpanel" aria-labelledby="backorders-tab">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="mb-0">Pending Backorder Approvals</h5>
                                    <div class="input-group" style="max-width: 300px;">
                                        <input type="text" class="form-control" placeholder="Search backorders...">
                                        <button class="btn btn-outline-secondary" type="button">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead class="bg-success text-white">
                                            <tr>
                                                <th class="text-center">No.</th>
                                                <th>Material</th>
                                                <th>Category</th>
                                                <th>Quantity</th>
                                                <th>Reason</th>
                                                <th>Requested By</th>
                                                <th>Request Date</th>
                                                <th>Status</th>
                                                <th class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($total_backorders > 0): 
                                                $counter = 1;
                                                while ($backorder = $backorder_result->fetch_assoc()): 
                                                    $requester_name = trim(($backorder['firstname'] ?? '') . ' ' . ($backorder['lastname'] ?? ''));
                                            ?>
                                            <tr>
                                                <td class="text-center"><?php echo $counter++; ?></td>
                                                <td><?php echo htmlspecialchars($backorder['material_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($backorder['category_name'] ?? 'N/A'); ?></td>
                                                <td class="text-end"><?php echo number_format($backorder['quantity']); ?></td>
                                                <td><?php echo htmlspecialchars($backorder['reason']); ?></td>
                                                <td><?php echo htmlspecialchars($requester_name ?: 'Unknown'); ?></td>
                                                <td class="text-center"><?php echo date('M d, Y', strtotime($backorder['created_at'])); ?></td>
                                                <td class="text-center">
                                                    <span class="badge bg-warning"><?php echo $backorder['status']; ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex gap-2">
                                                        <button class="btn btn-sm btn-primary view-backorder" 
                                                                data-id="<?php echo $backorder['id']; ?>"
                                                                title="View Details">
                                                            <i class="fas fa-eye me-1"></i> View
                                                        </button>
                                                        <button class="btn btn-sm btn-success approve-backorder" 
                                                                data-id="<?php echo $backorder['id']; ?>"
                                                                title="Approve">
                                                            <i class="fas fa-check me-1"></i> Approve
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php 
                                                $counter++;
                                                endwhile; 
                                            else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-4">
                                                    <div class="text-muted">No pending backorders for approval</div>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Reorders Tab -->
                            <div class="tab-pane fade" id="reorders" role="tabpanel" aria-labelledby="reorders-tab">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="mb-0">Pending Reorder Approvals</h5>
                                    <div class="input-group" style="max-width: 300px;">
                                        <input type="text" class="form-control" id="reorderSearch" placeholder="Search reorders...">
                                        <button class="btn btn-outline-secondary" type="button" id="reorderSearchBtn">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead class="bg-success text-white">
                                            <tr>
                                                <th class="text-center">No.</th>
                                                <th>Material</th>
                                                <th>Category</th>
                                                <th>Quantity</th>
                                                <th>Reason</th>
                                                <th>Supplier Name</th>
                                                <th>Requested By</th>
                                                <th>Request Date</th>
                                                <th>Status</th>
                                                <th class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($total_reorders > 0): 
                                                $reorder_counter = 1;
                                                while ($reorder = $reorder_result->fetch_assoc()): 
                                                    $requester_name = trim(($reorder['firstname'] ?? '') . ' ' . ($reorder['lastname'] ?? ''));
                                            ?>
                                            <tr>
                                                <td class="text-center"><?php echo $reorder_counter++; ?></td>
                                                <td><?php echo htmlspecialchars($reorder['material_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($reorder['category_name'] ?? 'N/A'); ?></td>
                                                <td class="text-end"><?php echo number_format($reorder['quantity']); ?></td>
                                                <td><?php echo htmlspecialchars($reorder['reason']); ?></td>
                                                <td><?php echo htmlspecialchars($requester_name ?: 'Unknown'); ?></td>
                                                <td><?php echo htmlspecialchars($reorder['supplier_name'] ?? 'N/A'); ?></td>
                                                <td class="text-center"><?php echo date('M d, Y', strtotime($reorder['created_at'])); ?></td>
                                                <td class="text-center">
                                                    <span class="badge bg-warning"><?php echo $reorder['status']; ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex gap-2">
                                                        <button class="btn btn-sm btn-primary view-reorder" 
                                                                data-id="<?php echo $reorder['id']; ?>"
                                                                title="View Details">
                                                            <i class="fas fa-eye me-1"></i> View
                                                        </button>
                                                        <button class="btn btn-sm btn-success approve-reorder" 
                                                                data-id="<?php echo $reorder['id']; ?>"
                                                                title="Approve">
                                                            <i class="fas fa-check me-1"></i> Approve
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php 
                                                $reorder_counter++;
                                                endwhile; 
                                            else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-4">
                                                    <div class="text-muted">No pending reorders for approval</div>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Materials Tab -->
                            <div class="tab-pane fade" id="materials" role="tabpanel" aria-labelledby="materials-tab">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="mb-0">Pending Material Approvals</h5>
                                    <div class="input-group" style="max-width: 300px;">
                                        <input type="text" class="form-control" id="materialSearch" placeholder="Search materials...">
                                        <button class="btn btn-outline-secondary" type="button" id="materialSearchBtn">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead class="bg-success text-white">
                                            <tr>
                                                <th class="text-center">No.</th>
                                                <th>Material</th>
                                                <th>Category</th>
                                                <th>Quantity</th>
                                                <th>Unit</th>
                                                <th>Requested By</th>
                                                <th>Request Date</th>
                                                <th>Status</th>
                                                <th class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="materialsTableBody">
                                            <?php 
                                            $counter = ($page - 1) * $results_per_page + 1;
                                            if ($materials_result && $materials_result->num_rows > 0): 
                                                while($material = $materials_result->fetch_assoc()): 
                                            ?>
                                                    <tr>
                                                        <td class="text-center"><?php echo $counter++; ?></td>
                                                        <td><?php echo htmlspecialchars($material['material_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($material['category_name'] ?? 'N/A'); ?></td>
                                                        <td class="text-end"><?php echo number_format($material['quantity']); ?></td>
                                                        <td class="text-center"><?php echo htmlspecialchars($material['unit']); ?></td>
                                                        <td><?php echo htmlspecialchars(($material['firstname'] . ' ' . $material['lastname']) ?? 'N/A'); ?></td>
                                                        <td class="text-center"><?php echo date('M d, Y', strtotime($material['purchase_date'])); ?></td>
                                                        <td class="text-center">
                                                            <span class="badge bg-warning"><?php echo ucfirst($material['delivery_status']); ?></span>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="d-flex gap-2">
                                                                <button class="btn btn-sm btn-primary view-material" 
                                                                        data-id="<?php echo $material['id']; ?>"
                                                                        title="View Details">
                                                                    <i class="fas fa-eye me-1"></i> View
                                                                </button>
                                                                <button class="btn btn-sm btn-success approve-material" 
                                                                        data-id="<?php echo $material['id']; ?>"
                                                                        title="Approve">
                                                                    <i class="fas fa-check me-1"></i> Approve
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php 
                                                endwhile; 
                                            else: 
                                            ?>
                                                <tr>
                                                    <td colspan="9" class="text-center py-4">
                                                        <div class="text-muted">No pending material approvals found</div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php 
                                $total_pages = ceil($total_materials / $results_per_page);
                                if ($total_pages > 1): 
                                ?>
                                <nav aria-label="Page navigation" class="mt-4">
                                    <ul class="pagination justify-content-center custom-pagination-green">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo ($page - 1); ?>">Previous</a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo ($page + 1); ?>">Next</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    <!-- /#wrapper -->

    <!-- Detail Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="detailModalLabel">Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="detailModalBody">
                    Loading...
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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


    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <div class="mb-3">
                        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                    </div>
                    <h5 class="modal-title mb-3" id="successModalTitle">Success!</h5>
                    <p class="mb-0" id="successModalMessage">Material request has been approved successfully.</p>
                </div>
                <div class="modal-footer justify-content-center border-0">
                    <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

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


    <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>

                <!-- Custom JavaScript -->
      

    <script>
    // Function to show feedback modal
    function showFeedbackModal(success, message) {
        const modal = new bootstrap.Modal(document.getElementById('successModal'));
        const modalTitle = document.getElementById('successModalTitle');
        const modalMessage = document.getElementById('successModalMessage');
        const modalIcon = document.querySelector('#successModal .modal-body i');
        
        if (success) {
            modalTitle.textContent = 'Success!';
            modalIcon.className = 'fas fa-check-circle text-success';
            modalIcon.style.fontSize = '4rem';
        } else {
            modalTitle.textContent = 'Error!';
            modalIcon.className = 'fas fa-exclamation-circle text-danger';
            modalIcon.style.fontSize = '4rem';
        }
        
        modalMessage.textContent = message;
        modal.show();
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        // Change Password AJAX
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

        // Search functionality for backorders and reorders
        function setupSearch(inputId, btnId, tableId) {
            const searchInput = document.getElementById(inputId);
            const searchBtn = document.getElementById(btnId);
            
            if (searchInput && searchBtn) {
                const performSearch = () => {
                    const searchTerm = searchInput.value.toLowerCase();
                    const rows = document.querySelectorAll(`#${tableId} tbody tr`);
                    let hasVisibleRows = false;
                    
                    rows.forEach(row => {
                        if (row.classList.contains('no-results')) {
                            row.remove();
                            return;
                        }
                        
                        const text = row.textContent.toLowerCase();
                        if (searchTerm === '' || text.includes(searchTerm)) {
                            row.style.display = '';
                            hasVisibleRows = true;
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    
                    // Show "no results" message if no rows are visible
                    if (!hasVisibleRows) {
                        const tbody = document.querySelector(`#${tableId} tbody`);
                        const noResultsRow = document.createElement('tr');
                        noResultsRow.className = 'no-results';
                        noResultsRow.innerHTML = `
                            <td colspan="8" class="text-center py-4">
                                <div class="text-muted">No matching records found</div>
                            </td>
                        `;
                        tbody.appendChild(noResultsRow);
                    }
                };
                
                searchBtn.addEventListener('click', performSearch);
                searchInput.addEventListener('keyup', (e) => {
                    if (e.key === 'Enter') performSearch();
                });
            }
        }
        
        // Initialize search for backorders and reorders
        setupSearch('backorderSearch', 'backorderSearchBtn', 'backorders');
        setupSearch('reorderSearch', 'reorderSearchBtn', 'reorders');
        
        // Material Search Functionality
        const materialSearch = document.getElementById('materialSearch');
        const materialSearchBtn = document.getElementById('materialSearchBtn');
        
        if (materialSearch && materialSearchBtn) {
            const performSearch = () => {
                const searchTerm = materialSearch.value.toLowerCase();
                const rows = document.querySelectorAll('#materialsTableBody tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            };
            
            materialSearchBtn.addEventListener('click', performSearch);
            materialSearch.addEventListener('keyup', (e) => {
                if (e.key === 'Enter') performSearch();
            });
        }
        
        // Function to show backorder/reorder details
        function showDetails(type, id) {
            fetch(`get_backorder_details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const item = data.data;
                        const modal = new bootstrap.Modal(document.getElementById('detailModal'));
                        const modalLabel = document.getElementById('detailModalLabel');
                        const modalBody = document.getElementById('detailModalBody');
                        
                        // Set modal title
                        modalLabel.textContent = `${type} Details`;
                        
                        // Format the details HTML
                        const detailsHtml = `
                            <div class="mb-3">
                                <h6>${item.material_name || 'N/A'}</h6>
                                <p class="mb-1"><strong>Quantity:</strong> ${item.quantity}</p>
                                <p class="mb-1"><strong>Reason:</strong> ${item.reason}</p>
                                <p class="mb-1"><strong>Status:</strong> 
                                    <span class="badge bg-${item.status === 'Approved' ? 'success' : item.status === 'Rejected' ? 'danger' : 'warning'}">
                                        ${item.status}
                                    </span>
                                </p>
                                <p class="mb-1"><strong>Requested By:</strong> ${item.firstname} ${item.lastname}</p>
                                <p class="mb-1"><strong>Email:</strong> ${item.requester_email || 'N/A'}</p>
                                <p class="mb-1"><strong>Date Requested:</strong> ${new Date(item.created_at).toLocaleString()}</p>
                                ${item.notes ? `<p class="mb-1"><strong>Notes:</strong> ${item.notes}</p>` : ''}
                            </div>
                        `;
                        
                        modalBody.innerHTML = detailsHtml;
                        modal.show();
                    } else {
                        showFeedbackModal(false, data.message || 'Failed to load details. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showFeedbackModal(false, 'An error occurred while loading details.');
                });
        }
        
        // Function to update backorder/reorder status
        function updateOrderStatus(orderId, status, type) {
            const formData = new FormData();
            formData.append('backorder_id', orderId);
            formData.append('status', status);

            fetch('update_backorder_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                // Try to parse as JSON
                let data = null;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    alert('Server returned an unexpected response:\n\n' + text);
                    return;
                }

                if (data.success) {
                    // Show success modal and reload after a delay
                    showFeedbackModal(true, data.message);
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    showFeedbackModal(false, data.message || "An unknown error occurred.");
                }
            })
            .catch(error => {
                alert("AJAX error: " + error);
            });
        }
        // Event delegation for backorder actions
        document.addEventListener('click', function(e) {
            // Backorder - View Details
            if (e.target.closest('.view-backorder')) {
                const button = e.target.closest('.view-backorder');
                const orderId = button.getAttribute('data-id');
                showDetails('Backorder', orderId);
            }
            
            // Backorder - Approve
            if (e.target.closest('.approve-backorder')) {
                const button = e.target.closest('.approve-backorder');
                const orderId = button.getAttribute('data-id');
                if (confirm('Are you sure you want to approve this backorder?')) {
                    updateOrderStatus(orderId, 'Approved', 'Backorder');
                    showFeedbackModal(true, 'Backorder approved successfully.');
                }
            }
            
            // Reorder - View Details
            if (e.target.closest('.view-reorder')) {
                const button = e.target.closest('.view-reorder');
                const orderId = button.getAttribute('data-id');
                showDetails('Reorder', orderId);
            }
            
            // Reorder - Approve
            if (e.target.closest('.approve-reorder')) {
                const button = e.target.closest('.approve-reorder');
                const orderId = button.getAttribute('data-id');
                if (confirm('Are you sure you want to approve this reorder?')) {
                    updateOrderStatus(orderId, 'Approved', 'Reorder');
                    showFeedbackModal(true, 'Reorder approved successfully.');
                }
            }
        
            // Material - View Details
            if (e.target.closest('.view-material')) {
                const button = e.target.closest('.view-material');
                const materialId = button.getAttribute('data-id');
                // TODO: Implement material details view if needed
                alert('View details for material ID: ' + materialId);
            }
            
            // Material - Approve
            if (e.target.closest('.approve-material')) {
                const button = e.target.closest('.approve-material');
                const materialId = button.getAttribute('data-id');
                if (confirm('Are you sure you want to approve this material request?')) {
                    updateMaterialStatus(materialId, 'Approved');
                }
            }
        });
        
        // Function to update material status
        function updateMaterialStatus(materialId, status) {
            const formData = new FormData();
            formData.append('id', materialId);
            formData.append('action', 'approve');
            
            fetch('update_material_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success modal
                    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                    const successMessage = document.getElementById('successModalMessage');
                    successMessage.textContent = 'Material request has been approved successfully.';
                    successModal.show();
                    
                    // Reload the page after a short delay to show the success message
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    // Show error alert if needed
                    alert(data.message || 'Failed to update material status.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the material status.');
            });
        }
        
        // Toggle sidebar
        const menuToggle = document.getElementById('menu-toggle');
        if (menuToggle) {
            menuToggle.addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('wrapper').classList.toggle('toggled');
            });
        }
    });
    </script>

    
    </script>
    
  </body>
</html>