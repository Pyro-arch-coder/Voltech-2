<?php
    session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
    header("Location: ../login.php");
    exit();
}
    $con = new mysqli("localhost", "root", "", "voltech2");
    if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
    }
    $userid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$user_email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
$user_firstname = isset($_SESSION['firstname']) ? $_SESSION['firstname'] : '';
$user_lastname = isset($_SESSION['lastname']) ? $_SESSION['lastname'] : '';
$user_name = trim($user_firstname . ' ' . $user_lastname);
$current_page = basename($_SERVER['PHP_SELF']);

// --- Handle Add Supplier Materials (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_supplier_material'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    $supplier_id = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 0;
    $material_name = isset($_POST['material_name']) ? trim($_POST['material_name']) : '';
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
    $unit = isset($_POST['unit']) ? trim($_POST['unit']) : '';
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'Available';
    $material_price = isset($_POST['material_price']) ? floatval($_POST['material_price']) : 0;
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $low_stock_threshold = isset($_POST['low_stock_threshold']) ? intval($_POST['low_stock_threshold']) : 10;
    $lead_time = isset($_POST['lead_time']) ? intval($_POST['lead_time']) : 0;
    $labor_other = isset($_POST['labor_other']) ? floatval($_POST['labor_other']) : 0;
    
    // Validation
    if (!$supplier_id) {
        $response['message'] = 'Invalid supplier ID.';
    } elseif (empty($material_name) || strlen($material_name) < 2) {
        $response['message'] = 'Material name must be at least 2 characters long.';
    } elseif ($quantity < 0) {
        $response['message'] = 'Quantity cannot be negative.';
    } elseif (empty($unit)) {
        $response['message'] = 'Please select a unit.';
    } elseif ($material_price <= 0) {
        $response['message'] = 'Material price must be greater than 0.';
    } elseif ($low_stock_threshold < 0) {
        $response['message'] = 'Low stock threshold cannot be negative.';
    } elseif ($lead_time < 0) {
        $response['message'] = 'Lead time cannot be negative.';
    } else {
        // Check if material already exists for this supplier
        $check_sql = "SELECT id FROM suppliers_materials WHERE supplier_id = ? AND material_name = ?";
        $check_stmt = $con->prepare($check_sql);
        $check_stmt->bind_param("is", $supplier_id, $material_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $response['message'] = 'This material already exists for this supplier.';
        } else {
            // Insert into suppliers_materials table with new fields
            $insert_sql = "INSERT INTO suppliers_materials (supplier_id, material_name, category, quantity, unit, status, material_price, labor_other, low_stock_threshold, lead_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $con->prepare($insert_sql);
            $insert_stmt->bind_param("ississddii", $supplier_id, $material_name, $category, $quantity, $unit, $status, $material_price, $labor_other, $low_stock_threshold, $lead_time);
            
            if ($insert_stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Material added successfully!';
            } else {
                $response['message'] = 'Failed to add material: ' . $con->error;
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
    
    echo json_encode($response);
    exit();
}

// --- Handle Delete Supplier Material (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_supplier_material'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    $material_id = isset($_POST['material_id']) ? intval($_POST['material_id']) : 0;
    
    if (!$material_id) {
        $response['message'] = 'Invalid material ID.';
    } else {
        $delete_sql = "DELETE FROM suppliers_materials WHERE id = ?";
        $delete_stmt = $con->prepare($delete_sql);
        $delete_stmt->bind_param("i", $material_id);
        
        if ($delete_stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Material deleted successfully!';
        } else {
            $response['message'] = 'Failed to delete material: ' . $con->error;
        }
        $delete_stmt->close();
    }
    
    echo json_encode($response);
    exit();
}

// --- Handle Edit Supplier Material (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_supplier_material'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    $material_id = isset($_POST['material_id']) ? intval($_POST['material_id']) : 0;
    $material_name = isset($_POST['material_name']) ? trim($_POST['material_name']) : '';
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
    $unit = isset($_POST['unit']) ? trim($_POST['unit']) : '';
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'Available';
    $material_price = isset($_POST['material_price']) ? floatval($_POST['material_price']) : 0;
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $low_stock_threshold = isset($_POST['low_stock_threshold']) ? intval($_POST['low_stock_threshold']) : 10;
    $lead_time = isset($_POST['lead_time']) ? intval($_POST['lead_time']) : 0;
    $labor_other = isset($_POST['labor_other']) ? floatval($_POST['labor_other']) : 0;
    if (!$material_id) {
        $response['message'] = 'Invalid material ID.';
    } elseif (empty($material_name) || strlen($material_name) < 2) {
        $response['message'] = 'Material name must be at least 2 characters long.';
    } elseif ($quantity < 0) {
        $response['message'] = 'Quantity cannot be negative.';
    } elseif (empty($unit)) {
        $response['message'] = 'Please select a unit.';
    } elseif ($material_price <= 0) {
        $response['message'] = 'Material price must be greater than 0.';
    } elseif ($low_stock_threshold < 0) {
        $response['message'] = 'Low stock threshold cannot be negative.';
    } elseif ($lead_time < 0) {
        $response['message'] = 'Lead time cannot be negative.';
    } else {
        $update_sql = "UPDATE suppliers_materials SET material_name=?, category=?, quantity=?, unit=?, status=?, material_price=?, labor_other=?, low_stock_threshold=?, lead_time=? WHERE id=?";
        $update_stmt = $con->prepare($update_sql);
        $update_stmt->bind_param("issisdddii", $material_name, $category, $quantity, $unit, $status, $material_price, $labor_other, $low_stock_threshold, $lead_time, $material_id);
        if ($update_stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Material updated successfully!';
        } else {
            $response['message'] = 'Failed to update material: ' . $con->error;
        }
        $update_stmt->close();
    }
    echo json_encode($response);
    exit();
}

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

// Get total number of records
$sql = "SELECT COUNT(id) AS total FROM suppliers";
$result = $con->query($sql);
$row = $result->fetch_assoc();
$total_records = $row['total'];
$total_pages = ceil($total_records / $results_per_page);

// Fetch suppliers with pagination
$sql = "SELECT * FROM suppliers ORDER BY id DESC LIMIT $start_from, $results_per_page";
$result = $con->query($sql);

// Get all suppliers for materials modal
$all_suppliers = $con->query("SELECT id, supplier_name FROM suppliers ORDER BY supplier_name");

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <link rel="stylesheet" href="po_styles.css" />
    <title>Procurement Officer Suppliers</title>
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
                <a href="po_dashboard.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'po_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>Dashboard
                </a>
                <a href="po_orders.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'po_orders.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-invoice"></i>Orders
                </a>
                <a class="list-group-item list-group-item-action bg-transparent second-text d-flex justify-content-between align-items-center <?php echo ($current_page == 'po_equipment.php' || $current_page == 'po_materials.php' || $current_page == 'po_warehouse_materials.php') ? 'active' : ''; ?>" data-bs-toggle="collapse" href="#inventoryCollapse" role="button" aria-expanded="<?php echo ($current_page == 'po_equipment.php' || $current_page == 'po_materials.php' || $current_page == 'po_warehouse_materials.php') ? 'true' : 'false'; ?>" aria-controls="inventoryCollapse">
                    <span><i class="fas fa-boxes"></i>Inventory</span>
                    <i class="fas fa-caret-down"></i>
                </a>
                <div class="collapse <?php echo ($current_page == 'po_equipment.php' || $current_page == 'po_materials.php' || $current_page == 'po_warehouse_materials.php') ? 'show' : ''; ?>" id="inventoryCollapse">
                    <a href="po_equipment.php" class="list-group-item list-group-item-action bg-transparent second-text ps-5 <?php echo $current_page == 'po_equipment.php' ? 'active' : ''; ?>">
                        <i class="fas fa-wrench"></i> Equipment
                    </a>
                    <a href="po_materials.php" class="list-group-item list-group-item-action bg-transparent second-text ps-5 <?php echo $current_page == 'po_materials.php' ? 'active' : ''; ?>">
                        <i class="fas fa-cubes"></i> Materials
                    </a>
                    <a href="po_warehouse_materials.php" class="list-group-item list-group-item-action bg-transparent second-text ps-5 <?php echo $current_page == 'po_warehouse_materials.php' ? 'active' : ''; ?>">
                        <i class="fas fa-warehouse"></i> Warehouse
                    </a>
                </div>
                <a href="po_suppliers.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'po_suppliers.php' ? 'active' : ''; ?>">
                    <i class="fas fa-truck"></i>Suppliers
                </a>
            </div>
        </div>
        <!-- /#sidebar-wrapper -->

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-align-left primary-text fs-4 me-3" id="menu-toggle"></i>
                    <h2 class="fs-2 m-0">Suppliers</h2>
                </div>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                        <?php include 'po_notification.php'; ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle second-text fw-bold" href="#" id="navbarDropdown"
                                role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php echo htmlspecialchars($user_name); ?>
                                <img src="<?php echo $userprofile; ?>" alt="User" class="rounded-circle" width="30" height="30" style="margin-left: 8px;">
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="po_profile.php">Profile</a></li>
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
                            <h4 class="mb-0">Supplier Management</h4>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-success ms-auto" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                                    <i class="fas fa-plus"></i> Add New Supplier
                                </button>
                                <button type="button" class="btn btn-danger exportSuppliersPdfBtn">
                                    <i class="fas fa-file-pdf"></i> Export as PDF
                                </button>
                            </div>
                        </div>
                        <hr>
                        <form class="mb-3" method="get" action="" id="searchForm" style="max-width:400px;">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0" name="search" placeholder="Search supplier, contact, or email" value="<?php echo htmlspecialchars(isset($_GET['search']) ? $_GET['search'] : ''); ?>" id="searchInput" autocomplete="off" maxlength="100" pattern="[A-Za-z0-9\s\-\.@]+" title="Search can contain letters, numbers, spaces, hyphens, dots, and @ symbol">
                            </div>
                        </form>
                       
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead class="bg-success text-white">
                                    <tr>
                                        <th>No.</th>
                                        <th>Supplier Name</th>
                                        <th>Contact Person</th>
                                        <th>Email</th>
                                        <th>Contact Number</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = $start_from + 1; ?>
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['contact_person']); ?></td>
                                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td><?php echo htmlspecialchars($row['contact_number']); ?></td>
                                                <td><?php echo htmlspecialchars($row['status']); ?></td>
                                                <td class="text-center">
                                                    <div class="action-buttons">
                                                        <button type="button" class="btn btn-info btn-sm text-white materials-btn" 
                                                            data-id="<?php echo $row['id']; ?>"
                                                            data-name="<?php echo htmlspecialchars($row['supplier_name']); ?>">
                                                            <i class="fas fa-cubes"></i> Materials
                                                        </button>
                                                        <button type="button" class="btn btn-warning btn-sm text-dark edit-supplier-btn" 
                                                            data-id="<?php echo $row['id']; ?>"
                                                            data-name="<?php echo htmlspecialchars($row['supplier_name']); ?>"
                                                            data-person="<?php echo htmlspecialchars($row['contact_person']); ?>"
                                                            data-number="<?php echo htmlspecialchars($row['contact_number']); ?>"
                                                            data-email="<?php echo htmlspecialchars($row['email']); ?>"
                                                            data-address="<?php echo htmlspecialchars($row['address']); ?>"
                                                            data-status="<?php echo htmlspecialchars($row['status']); ?>">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                        <a href="delete_supplier.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm text-white delete-supplier-btn" data-id="<?php echo $row['id']; ?>" data-name="<?php echo htmlspecialchars($row['supplier_name']); ?>">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No suppliers found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation">
                              <ul class="pagination justify-content-center">
                                <li class="page-item<?php if($page <= 1) echo ' disabled'; ?>">
                                  <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode(isset($_GET['search']) ? $_GET['search'] : ''); ?>">Previous</a>
                                </li>
                                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                  <li class="page-item<?php if($i == $page) echo ' active'; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode(isset($_GET['search']) ? $_GET['search'] : ''); ?>"><?php echo $i; ?></a>
                                  </li>
                                <?php endfor; ?>
                                <li class="page-item<?php if($page >= $total_pages) echo ' disabled'; ?>">
                                  <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode(isset($_GET['search']) ? $_GET['search'] : ''); ?>">Next</a>
                                </li>
                              </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Add Supplier Modal (same as before, but right-aligned buttons) -->
                <!-- Edit Supplier Modal (structure like Add, fields prefilled by JS) -->
                <div class="modal fade" id="editSupplierModal" tabindex="-1" aria-labelledby="editSupplierModalLabel" aria-hidden="true">
                  <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="editSupplierModalLabel">Edit Supplier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <form action="update_supplier.php" method="POST" novalidate>
                        <input type="hidden" name="edit_supplier_id" id="edit_supplier_id">
                        <div class="modal-body">
                          <div class="row">
                            <div class="col-md-6">
                              <div class="form-group mb-3">
                                <label>Supplier Name *</label>
                                <input type="text" class="form-control" name="supplier_name" id="edit_supplier_name" required minlength="2" maxlength="100" pattern="[A-Za-z0-9\s\-\.&]+" title="Supplier name can only contain letters, numbers, spaces, hyphens, dots, and ampersands">
                                <div class="invalid-feedback">Please enter a valid supplier name (2-100 characters).</div>
                              </div>
                              <div class="form-group mb-3">
                                <label>Contact Person</label>
                                <input type="text" class="form-control" name="contact_person" id="edit_contact_person" maxlength="100" pattern="[A-Za-z\s\.]+" title="Contact person name can only contain letters, spaces, and dots">
                                <div class="invalid-feedback">Please enter a valid contact person name.</div>
                              </div>
                              <div class="form-group mb-3">
                                <label>Contact Number</label>
                                <input type="tel" class="form-control" name="contact_number" id="edit_contact_number" maxlength="20" pattern="[\d\s\-\+\(\)]+" title="Contact number can only contain digits, spaces, hyphens, plus signs, and parentheses">
                                <div class="invalid-feedback">Please enter a valid contact number.</div>
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="form-group mb-3">
                                <label>Email</label>
                                <input type="email" class="form-control" name="email" id="edit_email" maxlength="100">
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                              </div>
                              <div class="form-group mb-3">
                                <label>Address</label>
                                <textarea class="form-control" name="address" id="edit_address" rows="2" maxlength="500" title="Address can contain up to 500 characters"></textarea>
                                <div class="invalid-feedback">Please enter a valid address (max 500 characters).</div>
                              </div>
                              <div class="form-group mb-3">
                                <label>Status *</label>
                                <select class="form-control" name="status" id="edit_supplier_status" required>
                                  <option value="Active">Active</option>
                                  <option value="Inactive">Inactive</option>
                                </select>
                                <div class="invalid-feedback">Please select a status.</div>
                              </div>
                            </div>
                          </div>
                        </div>
                        <div class="modal-footer justify-content-end">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                          <button type="submit" name="edit_supplier" class="btn btn-success">Update Supplier</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
                <!-- Feedback Modal (Unified for Success/Error) with higher z-index -->
                <div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true" style="z-index: 9999;">
                    <div class="modal-dialog modal-dialog-centered" style="z-index: 10000;">
                        <div class="modal-content text-center">
                            <div class="modal-body">
                                <span id="feedbackIcon" style="font-size: 3rem;"></span>
                                <h4 id="feedbackTitle"></h4>
                                <p id="feedbackMessage"></p>
                            </div>
                            <div class="modal-footer justify-content-center">
                                <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Supplier Modal -->
    <div class="modal fade" id="addSupplierModal" tabindex="-1" aria-labelledby="addSupplierModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="addSupplierModalLabel">Add New Supplier</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form action="add_supplier.php" method="POST" novalidate>
            <div class="modal-body">
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group mb-3">
                    <label>Supplier Name *</label>
                    <input type="text" class="form-control" name="supplier_name" required minlength="2" maxlength="100" pattern="[A-Za-z0-9\s\-\.&]+" title="Supplier name can only contain letters, numbers, spaces, hyphens, dots, and ampersands">
                    <div class="invalid-feedback">Please enter a valid supplier name (2-100 characters).</div>
                  </div>
                  <div class="form-group mb-3">
                    <label>Contact Person</label>
                    <input type="text" class="form-control" name="contact_person" maxlength="100" pattern="[A-Za-z\s\.]+" title="Contact person name can only contain letters, spaces, and dots">
                    <div class="invalid-feedback">Please enter a valid contact person name.</div>
                  </div>
                  <div class="form-group mb-3">
                    <label>Contact Number</label>
                    <input type="tel" class="form-control" name="contact_number" maxlength="20" pattern="[\d\s\-\+\(\)]+" title="Contact number can only contain digits, spaces, hyphens, plus signs, and parentheses">
                    <div class="invalid-feedback">Please enter a valid contact number.</div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group mb-3">
                    <label>Email</label>
                    <input type="email" class="form-control" name="email" maxlength="100">
                    <div class="invalid-feedback">Please enter a valid email address.</div>
                  </div>
                  <div class="form-group mb-3">
                    <label>Address *</label>
                    <div class="row g-2">
                      <div class="col-12 mb-2">
                        <select class="form-select" id="add_region" required><option value="">Select Region</option></select>
                        <div class="invalid-feedback">Please select a region.</div>
                      </div>
                      <div class="col-12 mb-2">
                        <select class="form-select" id="add_province" required disabled><option value="">Select Province</option></select>
                        <div class="invalid-feedback">Please select a province.</div>
                      </div>
                      <div class="col-12 mb-2">
                        <select class="form-select" id="add_city" required disabled><option value="">Select City/Municipality</option></select>
                        <div class="invalid-feedback">Please select a city/municipality.</div>
                      </div>
                      <div class="col-12 mb-2">
                        <select class="form-select" id="add_barangay" required disabled><option value="">Select Barangay</option></select>
                        <div class="invalid-feedback">Please select a barangay.</div>
                      </div>
                    </div>
                    <input type="hidden" name="address" id="add_address_hidden" required>
                  </div>
                  <div class="form-group mb-3">
                    <label>Status *</label>
                    <select class="form-control" name="status" required disabled>
                      <option value="Active" selected>Active</option>
                    </select>
                    <input type="hidden" name="status" value="Active">
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" name="add_supplier" class="btn btn-success">Add Supplier</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="supplierName"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDelete" class="btn btn-danger">Delete</a>
                </div>
            </div>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="po_suppliers.js"></script>

    <!-- Supplier Materials Modal -->
    <div class="modal fade" id="supplierMaterialsModal" tabindex="-1" aria-labelledby="supplierMaterialsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="supplierMaterialsModalLabel">Supplier Materials</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="text-primary" id="supplierNameDisplay"></h6>
                        </div>
                        <div class="col-md-6 text-end">
                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addMaterialModal">
                                <i class="fas fa-plus"></i> Add Material
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="materialsTable">
                            <thead class="bg-info text-white">
                                <tr>
                                    <th>Material Name</th>
                                    <th>Quantity</th>
                                    <th>Unit</th>
                                    <th>Status</th>
                                    <th>Price</th>
                                    <th>Low Stock</th>
                                    <th>Lead Time</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="materialsTableBody">
                                <!-- Materials will be loaded here via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Material Modal -->
    <div class="modal fade" id="addMaterialModal" tabindex="-1" aria-labelledby="addMaterialModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addMaterialModalLabel">Add Material</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addMaterialForm" novalidate>
                    <div class="modal-body">
                        <input type="hidden" id="supplierId" name="supplier_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>Material Name *</label>
                                    <input type="text" class="form-control" name="material_name" required minlength="2" maxlength="100" pattern="[A-Za-z0-9\s\-\.]+" title="Material name can only contain letters, numbers, spaces, hyphens, and dots">
                                    <div class="invalid-feedback">Please enter a valid material name (2-100 characters).</div>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Category *</label>
                                    <select class="form-control" name="category" required>
                                        <option value="">Select Category</option>
                                        <?php 
                                        $categories = $con->query("SELECT DISTINCT category FROM warehouses WHERE category IS NOT NULL AND category != '' ORDER BY category");
                                        while($cat = $categories->fetch_assoc()): 
                                        ?>
                                            <option value="<?php echo htmlspecialchars($cat['category']); ?>"><?php echo htmlspecialchars($cat['category']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a category.</div>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Quantity</label>
                                    <input type="number" min="0" max="999999" class="form-control" name="quantity" value="0">
                                    <div class="invalid-feedback">Quantity must be between 0 and 999,999.</div>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Unit *</label>
                                    <select class="form-control" name="unit" required>
                                        <option value="">Select Unit</option>
                                        <option value="kg">Kilogram (kg)</option>
                                        <option value="g">Gram (g)</option>
                                        <option value="t">Ton (t)</option>
                                        <option value="m³">Cubic Meter (m³)</option>
                                        <option value="ft³">Cubic Feet (ft³)</option>
                                        <option value="L">Liter (L)</option>
                                        <option value="mL">Milliliter (mL)</option>
                                        <option value="m">Meter (m)</option>
                                        <option value="mm">Millimeter (mm)</option>
                                        <option value="cm">Centimeter (cm)</option>
                                        <option value="ft">Feet (ft)</option>
                                        <option value="in">Inch (in)</option>
                                        <option value="pcs">Pieces (pcs)</option>
                                        <option value="bndl">Bundle (bndl)</option>
                                        <option value="rl">Roll (rl)</option>
                                        <option value="set">Set</option>
                                        <option value="sack/bag">Sack/Bag</option>
                                        <option value="m²">Square Meter (m²)</option>
                                        <option value="ft²">Square Feet (ft²)</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a unit.</div>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Status</label>
                                    <select class="form-control" name="status">
                                        <option value="Available" selected>Available</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>Material Price *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" step="0.01" min="0.01" max="999999.99" class="form-control" name="material_price" required>
                                    </div>
                                    <div class="invalid-feedback">Please enter a valid price (greater than 0).</div>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Low Stock Threshold</label>
                                    <input type="number" min="0" max="999999" class="form-control" name="low_stock_threshold" value="10">
                                    <div class="invalid-feedback">Low stock threshold must be between 0 and 999,999.</div>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Lead Time (Days)</label>
                                    <input type="number" min="0" max="365" class="form-control" name="lead_time" value="0">
                                    <div class="invalid-feedback">Lead time must be between 0 and 365 days.</div>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Labor/Other Cost</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" step="0.01" min="0" max="999999.99" class="form-control" name="labor_other" value="0">
                                    </div>
                                    <div class="invalid-feedback">Please enter a valid cost (0-999,999.99).</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Add Material</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Material Modal -->
    <div class="modal fade" id="editMaterialModal" tabindex="-1" aria-labelledby="editMaterialModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editMaterialModalLabel">Edit Material</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editMaterialForm" novalidate>
                    <input type="hidden" id="edit_material_id" name="material_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>Material Name *</label>
                                    <input type="text" class="form-control" name="material_name" id="edit_material_name" required minlength="2" maxlength="100" pattern="[A-Za-z0-9\s\-\.]+" title="Material name can only contain letters, numbers, spaces, hyphens, and dots">
                                    <div class="invalid-feedback">Please enter a valid material name (2-100 characters).</div>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Quantity</label>
                                    <input type="number" min="0" max="999999" class="form-control" name="quantity" id="edit_quantity" value="0">
                                    <div class="invalid-feedback">Quantity must be between 0 and 999,999.</div>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Unit *</label>
                                    <select class="form-control" name="unit" id="edit_unit" required>
                                        <option value="">Select Unit</option>
                                        <option value="kg">Kilogram (kg)</option>
                                        <option value="g">Gram (g)</option>
                                        <option value="t">Ton (t)</option>
                                        <option value="m³">Cubic Meter (m³)</option>
                                        <option value="ft³">Cubic Feet (ft³)</option>
                                        <option value="L">Liter (L)</option>
                                        <option value="mL">Milliliter (mL)</option>
                                        <option value="m">Meter (m)</option>
                                        <option value="mm">Millimeter (mm)</option>
                                        <option value="cm">Centimeter (cm)</option>
                                        <option value="ft">Feet (ft)</option>
                                        <option value="in">Inch (in)</option>
                                        <option value="pcs">Pieces (pcs)</option>
                                        <option value="bndl">Bundle (bndl)</option>
                                        <option value="rl">Roll (rl)</option>
                                        <option value="set">Set</option>
                                        <option value="sack/bag">Sack/Bag</option>
                                        <option value="m²">Square Meter (m²)</option>
                                        <option value="ft²">Square Feet (ft²)</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a unit.</div>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Status</label>
                                    <select class="form-control" name="status" id="edit_material_status">
                                        <option value="Available">Available</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>Material Price *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" step="0.01" min="0.01" max="999999.99" class="form-control" name="material_price" id="edit_material_price" required>
                                    </div>
                                    <div class="invalid-feedback">Please enter a valid price (greater than 0).</div>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Low Stock Threshold</label>
                                    <input type="number" min="0" max="999999" class="form-control" name="low_stock_threshold" id="edit_low_stock_threshold" value="10">
                                    <div class="invalid-feedback">Low stock threshold must be between 0 and 999,999.</div>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Lead Time (Days)</label>
                                    <input type="number" min="0" max="365" class="form-control" name="lead_time" id="edit_lead_time" value="0">
                                    <div class="invalid-feedback">Lead time must be between 0 and 365 days.</div>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Labor/Other Cost</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" step="0.01" min="0" max="999999.99" class="form-control" name="labor_other" id="edit_labor_other" value="0">
                                    </div>
                                    <div class="invalid-feedback">Please enter a valid cost (0-999,999.99).</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Update Material</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function showFeedbackModal(success, message, details, action) {
  var icon = document.getElementById('feedbackIcon');
  var title = document.getElementById('feedbackTitle');
  var msg = document.getElementById('feedbackMessage');
  if (success) {
            icon.innerHTML = '<i class="fas fa-check-circle" style="color:#28a745;"></i>';
    title.textContent = 'Success!';
    msg.textContent = message;
  } else {
            icon.innerHTML = '<i class="fas fa-times-circle" style="color:#dc3545;"></i>';
    title.textContent = 'Error!';
            msg.textContent = message;
  }
  var feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
  feedbackModal.show();
        // Remove query param from URL after showing
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    (function() {
      var params = new URLSearchParams(window.location.search);
      if (params.get('success') === '1') {
        showFeedbackModal(true, 'Supplier added successfully!', '', 'added');
      } else if (params.get('updated') === '1') {
        showFeedbackModal(true, 'Supplier updated successfully!', '', 'updated');
      } else if (params.get('deleted') === '1') {
        showFeedbackModal(true, 'Supplier deleted successfully!', '', 'deleted');
      }
    })();

    // Materials Modal Functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Handle Materials button click
        document.querySelectorAll('.materials-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const supplierId = this.getAttribute('data-id');
                const supplierName = this.getAttribute('data-name');
                
                document.getElementById('supplierId').value = supplierId;
                document.getElementById('supplierNameDisplay').textContent = supplierName;
                
                loadSupplierMaterials(supplierId);
                
                // Load categories into the add material form
                const categorySelect = document.querySelector('#addMaterialForm select[name="category"]');
                loadCategories(categorySelect);
                
                const materialsModal = new bootstrap.Modal(document.getElementById('supplierMaterialsModal'));
                materialsModal.show();
            });
        });

        // Handle Add Supplier form validation
        const addSupplierForm = document.querySelector('#addSupplierModal form');
        if (addSupplierForm) {
            addSupplierForm.addEventListener('submit', function(e) {
                if (!this.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                this.classList.add('was-validated');
            });
        }

        // Handle Edit Supplier form validation
        const editSupplierForm = document.querySelector('#editSupplierModal form');
        if (editSupplierForm) {
            editSupplierForm.addEventListener('submit', function(e) {
                if (!this.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                this.classList.add('was-validated');
            });
        }

        // Real-time validation for supplier forms
        function setupRealTimeValidation(form) {
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    if (this.checkValidity()) {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    } else {
                        this.classList.remove('is-valid');
                        this.classList.add('is-invalid');
                    }
                });
                
                input.addEventListener('blur', function() {
                    if (this.checkValidity()) {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    } else {
                        this.classList.remove('is-valid');
                        this.classList.add('is-invalid');
                    }
                });
            });
        }

        // Setup real-time validation for both forms
        setupRealTimeValidation(addSupplierForm);
        setupRealTimeValidation(editSupplierForm);

        // Handle search form validation
        const searchForm = document.getElementById('searchForm');
        const searchInput = document.getElementById('searchInput');
        
        if (searchForm && searchInput) {
            searchForm.addEventListener('submit', function(e) {
                const searchValue = searchInput.value.trim();
                if (searchValue.length < 2 && searchValue.length > 0) {
                    e.preventDefault();
                    alert('Search term must be at least 2 characters long.');
                    searchInput.focus();
                    return false;
                }
            });
            
            // Auto-submit search with delay
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const searchValue = this.value.trim();
                
                if (searchValue.length >= 2 || searchValue.length === 0) {
                    searchTimeout = setTimeout(function() {
                        searchForm.submit();
                    }, 500);
                }
            });
        }

        // Handle Add Material form submission
        document.getElementById('addMaterialForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Clear previous validation states
            this.classList.remove('was-validated');
            
            // Add custom validation for category
            const categorySelect = this.querySelector('select[name="category"]');
            if (!categorySelect.value) {
                categorySelect.setCustomValidity('Please select a category');
                categorySelect.reportValidity();
                return;
            } else {
                categorySelect.setCustomValidity('');
            }
            
            // Custom validation
            let isValid = true;
            const formData = new FormData(this);
            
            // Validate material name
            const materialName = formData.get('material_name');
            const materialNameInput = this.querySelector('[name="material_name"]');
            if (!materialName || materialName.trim().length < 2) {
                materialNameInput.classList.add('is-invalid');
                isValid = false;
            } else {
                materialNameInput.classList.remove('is-invalid');
                materialNameInput.classList.add('is-valid');
            }
            
            // Validate unit
            const unit = formData.get('unit');
            const unitSelect = this.querySelector('[name="unit"]');
            if (!unit) {
                unitSelect.classList.add('is-invalid');
                isValid = false;
            } else {
                unitSelect.classList.remove('is-invalid');
                unitSelect.classList.add('is-valid');
            }
            
            // Validate material price
            const materialPrice = parseFloat(formData.get('material_price'));
            const materialPriceInput = this.querySelector('[name="material_price"]');
            if (!materialPrice || materialPrice <= 0) {
                materialPriceInput.classList.add('is-invalid');
                isValid = false;
            } else {
                materialPriceInput.classList.remove('is-invalid');
                materialPriceInput.classList.add('is-valid');
            }
            
            // Validate quantity
            const quantity = parseInt(formData.get('quantity'));
            const quantityInput = this.querySelector('[name="quantity"]');
            if (quantity < 0) {
                quantityInput.classList.add('is-invalid');
                isValid = false;
            } else {
                quantityInput.classList.remove('is-invalid');
                quantityInput.classList.add('is-valid');
            }
            
            // Validate low stock threshold
            const lowStockThreshold = parseInt(formData.get('low_stock_threshold'));
            const lowStockInput = this.querySelector('[name="low_stock_threshold"]');
            
            if (lowStockThreshold < 0) {
                lowStockInput.classList.add('is-invalid');
                isValid = false;
            } else {
                lowStockInput.classList.remove('is-invalid');
                lowStockInput.classList.add('is-valid');
            }
            
            // Validate lead time
            const leadTime = parseInt(formData.get('lead_time'));
            const leadTimeInput = this.querySelector('[name="lead_time"]');
            if (leadTime < 0) {
                leadTimeInput.classList.add('is-invalid');
                isValid = false;
            } else {
                leadTimeInput.classList.remove('is-invalid');
                leadTimeInput.classList.add('is-valid');
            }
            
            // Validate labor/other cost
            const laborOther = parseFloat(formData.get('labor_other'));
            const laborOtherInput = this.querySelector('[name="labor_other"]');
            if (laborOther < 0) {
                laborOtherInput.classList.add('is-invalid');
                isValid = false;
            } else {
                laborOtherInput.classList.remove('is-invalid');
                laborOtherInput.classList.add('is-valid');
            }
            
            if (!isValid) {
                this.classList.add('was-validated');
                return;
            }
            
            // Add the AJAX flag
            formData.append('add_supplier_material', '1');
            
            // Get the selected category
            const category = this.querySelector('select[name="category"]').value;
            formData.set('category', category);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showFeedbackModal(true, data.message);
                    document.getElementById('addMaterialForm').reset();
                    // Clear validation states
                    document.getElementById('addMaterialForm').querySelectorAll('.is-valid, .is-invalid').forEach(el => {
                        el.classList.remove('is-valid', 'is-invalid');
                    });
                    const addMaterialModal = bootstrap.Modal.getInstance(document.getElementById('addMaterialModal'));
                    addMaterialModal.hide();
                    
                    // Reload materials table
                    const supplierId = document.getElementById('supplierId').value;
                    loadSupplierMaterials(supplierId);
                } else {
                    showFeedbackModal(false, data.message);
                }
            })
            .catch(error => {
                showFeedbackModal(false, 'An error occurred. Please try again.');
            });
        });

        // Handle Edit Material form submission
        document.getElementById('editMaterialForm').addEventListener('submit', function(e) {
            e.preventDefault();
            this.classList.remove('was-validated');
            let isValid = true;
            const formData = new FormData(this);
            // Validate material name
            const materialName = formData.get('material_name');
            const materialNameInput = this.querySelector('[name="material_name"]');
            if (!materialName || materialName.trim().length < 2) {
                materialNameInput.classList.add('is-invalid');
                isValid = false;
            } else {
                materialNameInput.classList.remove('is-invalid');
                materialNameInput.classList.add('is-valid');
            }
            // Validate unit
            const unit = formData.get('unit');
            const unitSelect = this.querySelector('[name="unit"]');
            if (!unit) {
                unitSelect.classList.add('is-invalid');
                isValid = false;
            } else {
                unitSelect.classList.remove('is-invalid');
                unitSelect.classList.add('is-valid');
            }
            // Validate material price
            const materialPrice = parseFloat(formData.get('material_price'));
            const materialPriceInput = this.querySelector('[name="material_price"]');
            if (!materialPrice || materialPrice <= 0) {
                materialPriceInput.classList.add('is-invalid');
                isValid = false;
            } else {
                materialPriceInput.classList.remove('is-invalid');
                materialPriceInput.classList.add('is-valid');
            }
            // Validate quantity
            const quantity = parseInt(formData.get('quantity'));
            const quantityInput = this.querySelector('[name="quantity"]');
            if (quantity < 0) {
                quantityInput.classList.add('is-invalid');
                isValid = false;
            } else {
                quantityInput.classList.remove('is-invalid');
                quantityInput.classList.add('is-valid');
            }
            // Validate low stock threshold
            const lowStockThreshold = parseInt(formData.get('low_stock_threshold'));
            const lowStockInput = this.querySelector('[name="low_stock_threshold"]');
            if (lowStockThreshold < 0) {
                lowStockInput.classList.add('is-invalid');
                isValid = false;
            } else {
                lowStockInput.classList.remove('is-invalid');
                lowStockInput.classList.add('is-valid');
            }
            // Validate lead time
            const leadTime = parseInt(formData.get('lead_time'));
            const leadTimeInput = this.querySelector('[name="lead_time"]');
            if (leadTime < 0) {
                leadTimeInput.classList.add('is-invalid');
                isValid = false;
            } else {
                leadTimeInput.classList.remove('is-invalid');
                leadTimeInput.classList.add('is-valid');
            }
            // Validate labor/other cost
            const laborOther = parseFloat(formData.get('labor_other'));
            const laborOtherInput = this.querySelector('[name="labor_other"]');
            if (laborOther < 0) {
                laborOtherInput.classList.add('is-invalid');
                isValid = false;
            } else {
                laborOtherInput.classList.remove('is-invalid');
                laborOtherInput.classList.add('is-valid');
            }
            if (!isValid) {
                this.classList.add('was-validated');
                return;
            }
            // Add the AJAX flag
            formData.append('edit_supplier_material', '1');
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showFeedbackModal(true, data.message);
                    // Close modal
                    const editMaterialModal = bootstrap.Modal.getInstance(document.getElementById('editMaterialModal'));
                    editMaterialModal.hide();
                    // Reload materials table
                    const supplierId = document.getElementById('supplierId').value;
                    loadSupplierMaterials(supplierId);
                } else {
                    showFeedbackModal(false, data.message);
                }
            })
            .catch(error => {
                showFeedbackModal(false, 'An error occurred. Please try again.');
            });
        });

        // Handle delete material
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('delete-material-btn')) {
                e.preventDefault();
                
                if (confirm('Are you sure you want to delete this material?')) {
                    const materialId = e.target.getAttribute('data-id');
                    
                    const formData = new FormData();
                    formData.append('delete_supplier_material', '1');
                    formData.append('material_id', materialId);
                    
                    fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showFeedbackModal(true, data.message);
                            const supplierId = document.getElementById('supplierId').value;
                            loadSupplierMaterials(supplierId);
                        } else {
                            showFeedbackModal(false, data.message);
                        }
                    })
                    .catch(error => {
                        showFeedbackModal(false, 'An error occurred. Please try again.');
                    });
                }
            }
        });
    });

    // Function to load categories into dropdown
    function loadCategories(selectElement) {
        fetch('get_categories.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear existing options except the first one
                    while (selectElement.options.length > 1) {
                        selectElement.remove(1);
                    }
                    
                    // Add new categories
                    data.categories.forEach(category => {
                        const option = new Option(category, category);
                        selectElement.add(option);
                    });
                }
            })
            .catch(error => console.error('Error loading categories:', error));
    }

    // Function to load supplier materials
    function loadSupplierMaterials(supplierId) {
        fetch(`get_supplier_materials.php?supplier_id=${supplierId}`)
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('materialsTableBody');
                tbody.innerHTML = '';
                
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center">No materials found for this supplier</td></tr>';
                } else {
                    data.forEach(material => {
                        const row = `
                            <tr>
                                <td>${material.material_name}</td>
                                <td>${material.quantity}</td>
                                <td>${material.unit}</td>
                                <td>${material.status}</td>
                                <td>₱ ${parseFloat(material.material_price).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                                <td>${material.low_stock_threshold}</td>
                                <td>${material.lead_time} days</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-warning btn-sm edit-material-btn" data-id="${material.id}" data-name="${material.material_name}" data-quantity="${material.quantity}" data-unit="${material.unit}" data-status="${material.status}" data-price="${material.material_price}" data-low_stock="${material.low_stock_threshold}" data-lead_time="${material.lead_time}" data-labor_other="${material.labor_other !== undefined && material.labor_other !== null ? material.labor_other : 0}">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm delete-material-btn" data-id="${material.id}">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        `;
                        tbody.innerHTML += row;
                    });
                }
            })
            .catch(error => {
                console.error('Error loading materials:', error);
                document.getElementById('materialsTableBody').innerHTML = 
                    '<tr><td colspan="7" class="text-center text-danger">Error loading materials</td></tr>';
            });
    }
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
      xhr.open('POST', 'change_password.php', true);
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
  document.body.addEventListener('click', function(e) {
    var btn = e.target.closest('.edit-material-btn');
    if (btn) {
      document.getElementById('edit_material_id').value = btn.getAttribute('data-id');
      document.getElementById('edit_material_name').value = btn.getAttribute('data-name');
      document.getElementById('edit_quantity').value = btn.getAttribute('data-quantity');
      document.getElementById('edit_unit').value = btn.getAttribute('data-unit');
      document.getElementById('edit_material_status').value = btn.getAttribute('data-status');
      document.getElementById('edit_material_price').value = btn.getAttribute('data-price');
      document.getElementById('edit_low_stock_threshold').value = btn.getAttribute('data-low_stock');
      document.getElementById('edit_lead_time').value = btn.getAttribute('data-lead_time');
      let laborOther = btn.getAttribute('data-labor_other');
      document.getElementById('edit_labor_other').value = (laborOther !== null && laborOther !== '') ? laborOther : 0;
      console.log('labor_other:', btn.getAttribute('data-labor_other'));
      var modal = new bootstrap.Modal(document.getElementById('editMaterialModal'));
      modal.show();
    }
  });
});
</script>

<!-- Export PDF Confirmation Modal (only one per page) -->
<div class="modal fade" id="exportSuppliersPdfModal" tabindex="-1" aria-labelledby="exportSuppliersPdfModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exportSuppliersPdfModalLabel">Export as PDF</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to export the suppliers list and their materials as PDF?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="#" id="confirmExportSuppliersPdf" class="btn btn-danger">Export</a>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.exportSuppliersPdfBtn').forEach(function(exportBtn) {
    exportBtn.addEventListener('click', function(e) {
      e.preventDefault();
      var modal = new bootstrap.Modal(document.getElementById('exportSuppliersPdfModal'));
      modal.show();
    });
  });

  // Add handler for Export button in confirmation modal
  var exportBtn = document.getElementById('confirmExportSuppliersPdf');
  if (exportBtn) {
    exportBtn.addEventListener('click', function(e) {
      e.preventDefault();
      // Close the modal first
      var modalEl = document.getElementById('exportSuppliersPdfModal');
      var modalInstance = bootstrap.Modal.getInstance(modalEl);
      if (modalInstance) modalInstance.hide();
      // Open the PDF in a new tab after a short delay to allow modal to close
      setTimeout(function() {
        window.open('export_suppliers_pdf.php', '_blank');
      }, 300);
    });
  }
});
</script>

</body>

</html>