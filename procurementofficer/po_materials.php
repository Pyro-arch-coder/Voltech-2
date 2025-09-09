<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
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


$search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';
$category_filter = isset($_GET['category']) ? mysqli_real_escape_string($con, $_GET['category']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($con, $_GET['status']) : '';
$supplier_filter = isset($_GET['supplier']) ? mysqli_real_escape_string($con, $_GET['supplier']) : '';

// Build WHERE clause
$where_conditions = [];
if (!empty($search)) {
    $where_conditions[] = "(material_name LIKE '%$search%' OR category LIKE '%$search%' OR supplier_name LIKE '%$search%')";
}
if (!empty($category_filter)) {
    $where_conditions[] = "category = '$category_filter'";
}
if (!empty($status_filter)) {
    $where_conditions[] = "status = '$status_filter'";
}
if (!empty($supplier_filter)) {
    $where_conditions[] = "supplier_name = '$supplier_filter'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Pagination settings
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Get total number of records with filters
$total_query = "SELECT COUNT(*) as total FROM materials $where_clause";
$total_result = $con->query($total_query);
$total_row = $total_result->fetch_assoc();
$total_items = $total_row['total'];
$total_pages = ceil($total_items / $items_per_page);

// Get distinct values for filters
$categories_query = "SELECT DISTINCT category FROM materials ORDER BY category";
$statuses_query = "SELECT DISTINCT status FROM materials ORDER BY status";
$suppliers_query = "SELECT DISTINCT supplier_name FROM materials ORDER BY supplier_name";

$categories = $con->query($categories_query);
$statuses = $con->query($statuses_query);
$suppliers = $con->query($suppliers_query);

// Add these after $con = new mysqli(...);
$all_suppliers = $con->query("SELECT id, supplier_name FROM suppliers ORDER BY supplier_name");
$all_warehouses = $con->query("SELECT id, warehouse FROM warehouses ORDER BY warehouse");


// Fetch materials from database with pagination and filters
$sql = "SELECT m.* FROM materials m $where_clause";
if (!empty($where_clause)) {
    $sql = "SELECT m.* FROM materials m $where_clause LIMIT $offset, $items_per_page";
} else {
    $sql = "SELECT m.* FROM materials m LIMIT $offset, $items_per_page";
}

$result = $con->query($sql);

// Add short_number_format function for summary cards
function short_number_format($num, $precision = 1) {
    if ($num >= 1000000000000) {
        return number_format($num / 1000000000000, $precision) . 't';
    } elseif ($num >= 1000000000) {
        return number_format($num / 1000000000, $precision) . 'b';
    } elseif ($num >= 1000000) {
        return number_format($num / 1000000, $precision) . 'm';
    } elseif ($num >= 1000) {
        return number_format($num / 1000, $precision) . 'k';
    } else {
        return number_format($num, 2);
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
    <link rel="stylesheet" href="po_styles.css" />
    <title>Procurement Officer Materials</title>
    <style>
        /* Success and Error Modals */
        .modal-backdrop {
            opacity: 0.5 !important;
        }
        .fade.show {
            transition: opacity 0.15s linear;
        }
        #successModal .modal-header {
            background-color: #28a745;
            color: white;
        }
        #errorModal .modal-header {
            background-color: #dc3545;
            color: white;
        }
        .btn-close-white {
            filter: invert(1) grayscale(100%) brightness(200%);
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
            <div class="list-group list-group-flush ">
                <a href="po_dashboard.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'po_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>Dashboard
                </a>
                <a href="po_orders.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'po_orders.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-invoice"></i>Purchases
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
                    <h2 class="fs-2 m-0">Materials</h2>
                </div>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                    <?php 
                    include 'po_notification.php'; 
                    
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
                    $tables = ['pm_supplier_messages', 'pm_procurement_messages', 'admin_pm_messages'];
                    $unreadCount = countUnreadMessages($con, $tables, $_SESSION['user_id']);
                    ?>
                    <li class="nav-item ms-2">
                        <a class="nav-link position-relative" href="procurement_messenger.php" title="Messages">
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
                
                <!-- TABLE CARD -->
                <div class="card mb-5 shadow rounded-3">

                    <div class="card-body p-4">
                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">Materials Management</h4>
                            <div>
                                <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addMaterialModal">
                                    <i class="fas fa-plus"></i> Add Material
                                </button>
                                <a href="#" class="btn btn-danger exportPdfBtn">
                                    <i class="fas fa-file-pdf"></i> Export as PDF
                                </a>
                            </div>
                        </div>
                        <hr>
                        <form method="GET" class="d-flex flex-wrap gap-2 mb-3" id="searchForm" style="min-width:260px; max-width:900px;">
                                <div class="input-group" style="min-width:220px; max-width:320px;">
                                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                    <input type="text" class="form-control border-start-0" name="search" placeholder="Search material/category/supplier" value="<?php echo htmlspecialchars($search); ?>" id="searchInput" autocomplete="off">
                                </div>
                                <select name="category" class="form-control" style="max-width:180px;" id="categoryFilter">
                                        <option value="">All Categories</option>
                                    <?php $categories->data_seek(0); while($cat = $categories->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $category_filter === $cat['category'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['category']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                <select name="status" class="form-control" style="max-width:180px;" id="statusFilter">
                                        <option value="">All Status</option>
                                        <option value="Available" <?php echo $status_filter === 'Available' ? 'selected' : ''; ?>>Available</option>
                                    </select>
                                <select name="supplier" class="form-control" style="max-width:180px;" id="supplierFilter">
                                        <option value="">All Suppliers</option>
                                    <?php $suppliers->data_seek(0); while($sup = $suppliers->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($sup['supplier_name']); ?>" <?php echo $supplier_filter === $sup['supplier_name'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($sup['supplier_name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                        </form>
                        
                        <!-- Backorder Success Modal -->
                        <div class="modal fade" id="backorderSuccessModal" tabindex="-1" aria-labelledby="backorderSuccessModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header bg-success text-white">
                                        <h5 class="modal-title" id="backorderSuccessModalLabel">Backorder Created Successfully</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body text-center">
                                        <div class="mb-3">
                                            <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                                        </div>
                                        <h5>Backorder has been created successfully!</h5>
                                        <p class="mb-0">The material has been marked as "Pending Backorder" and the supplier has been notified.</p>
                                    </div>
                                    <div class="modal-footer justify-content-center">
                                        <button type="button" class="btn btn-success" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <script>
                        // Show backorder success modal if redirected from successful backorder
                        document.addEventListener('DOMContentLoaded', function() {
                            const urlParams = new URLSearchParams(window.location.search);
                            if (urlParams.has('backorder_success')) {
                                const backorderModal = new bootstrap.Modal(document.getElementById('backorderSuccessModal'));
                                backorderModal.show();
                                var searchForm = document.getElementById('searchForm');
                                
                                // Search validation
                                if (searchInput && searchForm) {
                                    var searchTimeout;
                                    searchInput.addEventListener('input', function() {
                                        // Validate search input
                                        var searchValue = this.value.trim();
                                        if (searchValue.length > 0 && searchValue.length < 2) {
                                            this.classList.add('is-invalid');
                                            return;
                                        } else {
                                            this.classList.remove('is-invalid');
                                        }
                                        
                                        clearTimeout(searchTimeout);
                                        searchTimeout = setTimeout(function() {
                                            searchForm.submit();
                                        }, 400);
                                    });
                                    
                                    // Add validation feedback for search
                                    searchInput.addEventListener('blur', function() {
                                        var searchValue = this.value.trim();
                                        if (searchValue.length > 0 && searchValue.length < 2) {
                                            this.classList.add('is-invalid');
                                        } else {
                                            this.classList.remove('is-invalid');
                                        }
                                    });
                                }
                                
                                if (categoryFilter && searchForm) {
                                    categoryFilter.addEventListener('change', function() {
                                        searchForm.submit();
                                    });
                                }
                                if (statusFilter && searchForm) {
                                    statusFilter.addEventListener('change', function() {
                                        searchForm.submit();
                                    });
                                }
                                if (supplierFilter && searchForm) {
                                    supplierFilter.addEventListener('change', function() {
                                        searchForm.submit();
                                    });
                                }
                                
                                // Field validation function
                                function validateField(field) {
                                    var isValid = true;
                                    var value = field.value.trim();
                                    
                                    // Remove existing validation classes
                                    field.classList.remove('is-valid', 'is-invalid');
                                    
                                    // Field validation logic here
                                    if (field.required && !value) {
                                        field.classList.add('is-invalid');
                                        return false;
                                    }
                                    
                                    field.classList.add('is-valid');
                                    return true;
                                }
                                
                                // Edit form validation
                                document.querySelectorAll('[id^="editForm"]').forEach(function(form) {
                                    var formId = form.id;
                                    var materialId = formId.replace('editForm', '');
                                    
                                    // Add real-time validation to form fields
                                    var inputs = form.querySelectorAll('input, select');
                                    inputs.forEach(function(input) {
                                        input.addEventListener('input', function() {
                                            validateField(this);
                                        });
                                        
                                        input.addEventListener('blur', function() {
                                            validateField(this);
                                        });
                                    });
                                    
                                    // Form submission validation
                                    form.addEventListener('submit', function(e) {
                                        e.preventDefault();
                                        
                                        var isValid = true;
                                        var firstInvalidField = null;
                                        
                                        // Validate all fields
                                        inputs.forEach(function(input) {
                                            if (!validateField(input)) {
                                                isValid = false;
                                                if (!firstInvalidField) {
                                                    firstInvalidField = input;
                                                }
                                            }
                                        });
                                        
                                        if (!isValid) {
                                            // Show error message
                                            showValidationError('Please correct the errors in the form.');
                                            if (firstInvalidField) {
                                                firstInvalidField.focus();
                                            }
                                            return false;
                                        }
                                        
                                        // If valid, submit the form
                                        this.submit();
                                    });
                                });
                                    
                                    // Check if field is required
                                    if (field.hasAttribute('required') && !value) {
                                        field.classList.add('is-invalid');
                                        isValid = false;
                                    } else if (value) {
                                        // Validate based on field type and attributes
                                        switch (field.type) {
                                            case 'text':
                                                if (field.hasAttribute('pattern')) {
                                                    var pattern = new RegExp(field.getAttribute('pattern'));
                                                    if (!pattern.test(value)) {
                                                        field.classList.add('is-invalid');
                                                        isValid = false;
                                                    }
                                                }
                                                if (field.hasAttribute('minlength') && value.length < parseInt(field.getAttribute('minlength'))) {
                                                    field.classList.add('is-invalid');
                                                    isValid = false;
                                                }
                                                if (field.hasAttribute('maxlength') && value.length > parseInt(field.getAttribute('maxlength'))) {
                                                    field.classList.add('is-invalid');
                                                    isValid = false;
                                                }
                                                break;
                                                
                                            case 'number':
                                                var numValue = parseFloat(value);
                                                if (field.hasAttribute('min') && numValue < parseFloat(field.getAttribute('min'))) {
                                                    field.classList.add('is-invalid');
                                                    isValid = false;
                                                }
                                                if (field.hasAttribute('max') && numValue > parseFloat(field.getAttribute('max'))) {
                                                    field.classList.add('is-invalid');
                                                    isValid = false;
                                                }
                                                break;
                                                
                                            case 'select-one':
                                                if (field.hasAttribute('required') && value === '') {
                                                    field.classList.add('is-invalid');
                                                    isValid = false;
                                                }
                                                break;
                                        }
                                        
                                        // If field is valid, add valid class
                                        if (isValid) {
                                            field.classList.add('is-valid');
                                        }
                                    }
                                    
                                    return isValid;
                                }
                                
                                // Show validation error message
                                function showValidationError(message) {
                                    // Create or update error message
                                    var errorDiv = document.getElementById('validationError');
                                    if (!errorDiv) {
                                        errorDiv = document.createElement('div');
                                        errorDiv.id = 'validationError';
                                        errorDiv.className = 'alert alert-danger mt-3';
                                        errorDiv.style.display = 'block';
                                        document.querySelector('.container-fluid').insertBefore(errorDiv, document.querySelector('.card'));
                                    }
                                    errorDiv.textContent = message;
                                    
                                    // Auto-hide after 5 seconds
                                    setTimeout(function() {
                                        if (errorDiv) {
                                            errorDiv.style.display = 'none';
                                        }
                                    }, 5000);
                                }
                            });
                            </script>
                    
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped small-table">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>No</th>
                                        <th>Category</th>
                                        <th>Material Name</th>
                                        <th>Quantity</th>
                                        <th>Unit</th>
                                        <th>Status</th>
                                        <th>Delivery Status</th>
                                        <th>Supplier</th>
                                        <th>Total Amount</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                    <tbody>
                                        <?php 
                                        $rownum = 1 + $offset;
                                        $result->data_seek(0); while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $rownum++; ?></td>
                                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                                            <td><?php echo htmlspecialchars($row['material_name']); ?></td>
                                            <td><?php echo $row['quantity']; ?></td>
                                            <td><?php echo htmlspecialchars($row['unit']); ?></td>
                                            <td>
                                                <?php 
                                                // Check if max_stock exists and has a value
                                                $max_stock = isset($row['max_stock']) ? $row['max_stock'] : 0;
                                                $quantity = isset($row['quantity']) ? $row['quantity'] : 0;
                                                
                                                // Calculate reorder thresholds
                                                $reorder_25 = $max_stock * 0.25;
                                                $reorder_50 = $max_stock * 0.50;
                                                
                                                // Only show badges if max_stock is valid
                                                if ($max_stock > 0) {
                                                    if ($quantity <= $reorder_25) {
                                                        echo "<span class='badge bg-danger'>Reorder (25%)</span> ";
                                                    } elseif ($quantity <= $reorder_50) {
                                                        echo "<span class='badge bg-warning'>Reorder (50%)</span> ";
                                                    }
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $row['status'] == 'Low Stock' ? 'warning' : ($row['status'] == 'Available' ? 'success' : ($row['status'] == 'In Use' ? 'primary' : 'danger')); ?>">
                                                    <?php echo $row['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo ($row['delivery_status'] == 'Delivered') ? 'success' : (($row['delivery_status'] == 'In Transit') ? 'info' : (($row['delivery_status'] == 'Cancelled') ? 'danger' : 'warning')); ?>">
                                                    <?php echo htmlspecialchars($row['delivery_status'] ?? 'N/A'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                            <td>₱ <?php echo number_format($row['total_amount'], 2); ?></td>
                                            <td class="text-center">
                                                <a href="#" class="btn btn-sm btn-primary text-white font-weight-bold" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $row['id']; ?>">
                                                    <i class="fas fa-eye"></i> View More
                                                </a>
                                                <button type="button" class="btn btn-sm btn-warning reorder-btn" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#reorderModal<?php echo $row['id']; ?>"
                                                        data-material-id="<?php echo $row['id']; ?>"
                                                        data-material-name="<?php echo htmlspecialchars($row['material_name']); ?>"
                                                        data-current-quantity="<?php echo $row['quantity']; ?>"
                                                        data-unit="<?php echo htmlspecialchars($row['unit']); ?>"
                                                        <?php 
                                                        $isDisabled = true;
                                                        $title = 'Reorder is only allowed for delivered materials';
                                                        if (isset($row['delivery_status']) && $row['delivery_status'] === 'Delivered') {
                                                            $isDisabled = false;
                                                            $title = 'Click to reorder this material';
                                                        }
                                                        if ($isDisabled) echo 'disabled';
                                                        ?>
                                                        title="<?php echo htmlspecialchars($title); ?>">
                                                    Reorder
                                                </button>
                                                    <button type="button" class="btn btn-sm btn-danger backorder-btn" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#backorderModal<?php echo $row['id']; ?>"
                                                            data-material-id="<?php echo $row['id']; ?>"
                                                            data-material-name="<?php echo htmlspecialchars($row['material_name']); ?>"
                                                            data-current-quantity="<?php echo $row['quantity']; ?>"
                                                            data-unit="<?php echo htmlspecialchars($row['unit']); ?>"
                                                            <?php 
                                                            $isBackorderDisabled = false;
                                                            $backorderTitle = '';
                                                            if (isset($row['delivery_status']) && $row['delivery_status'] !== 'Delivered') {
                                                                $isBackorderDisabled = true;
                                                                $backorderTitle = 'Backorder is only allowed when delivery status is Delivered. Current status: ' . htmlspecialchars($row['delivery_status']);
                                                            }
                                                            if ($isBackorderDisabled) {
                                                                echo 'disabled';
                                                            }
                                                            ?>
                                                            title="<?php echo $isBackorderDisabled ? htmlspecialchars($backorderTitle) : 'Create a backorder for this material'; ?>">
                                                        Backorder
                                                    </button>
                                                    <form method="POST" action="receive_material.php" style="display: inline-block;">
                                                        <input type="hidden" name="material_id" value="<?php echo $row['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-success"
                                                                <?php 
                                                                $isReceiveDisabled = true;
                                                                $receiveTitle = 'Receive is only available for Backorder Delivery, Reorder Delivery, or Material on Delivery status';
                                                                if (isset($row['delivery_status']) && in_array($row['delivery_status'], ['Backorder Delivery', 'Reorder Delivery', 'Material On Delivery'])) {
                                                                    $isReceiveDisabled = false;
                                                                    $receiveTitle = 'Mark this material as received';
                                                                }
                                                                if ($isReceiveDisabled) {
                                                                    echo 'disabled';
                                                                }
                                                                ?>
                                                                title="<?php echo htmlspecialchars($receiveTitle); ?>">
                                                            <i class="fas fa-check-circle me-1"></i> Receive
                                                        </button>
                                                    </form>
                                            </td>
                                        </tr>
                                        <!-- Reorder Modal -->
                                        <div class="modal fade" id="reorderModal<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="reorderModalLabel<?php echo $row['id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <form id="reorderForm<?php echo $row['id']; ?>" method="POST" action="reorder_material.php" onsubmit="return validateReorderForm(<?php echo $row['id']; ?>, <?php echo $row['quantity']; ?>)">
                                                        <input type="hidden" name="material_id" value="<?php echo $row['id']; ?>">
                                                        <div class="modal-header bg-warning text-white">
                                                            <h5 class="modal-title" id="reorderModalLabel<?php echo $row['id']; ?>">
                                                                <i class="fas fa-sync-alt me-2"></i>Reorder Material
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label for="quantity<?php echo $row['id']; ?>" class="form-label">
                                                                    Quantity to Reorder
                                                                    <small class="text-muted">(Current: <?php echo $row['quantity'] . ' ' . htmlspecialchars($row['unit']); ?>)</small>
                                                                </label>
                                                                <div class="input-group">
                                                                    <input type="number" class="form-control" id="quantity<?php echo $row['id']; ?>" 
                                                                           name="quantity" min="1" max="<?php echo 1000 - $row['quantity']; ?>" required 
                                                                           oninput="updateTotal(<?php echo $row['id']; ?>, <?php echo $row['quantity']; ?>, '<?php echo htmlspecialchars($row['unit']); ?>')"
                                                                           placeholder="Enter quantity">
                                                                    <span class="input-group-text"><?php echo htmlspecialchars($row['unit']); ?></span>
                                                                </div>
                                                                <div class="form-text">
                                                                    <div>Current: <?php echo $row['quantity']; ?> <?php echo htmlspecialchars($row['unit']); ?></div>
                                                                    <div>Max allowed: <?php echo 1000 - $row['quantity']; ?> <?php echo htmlspecialchars($row['unit']); ?> (Max total: 1000 <?php echo htmlspecialchars($row['unit']); ?>)</div>
                                                                    <div id="totalAfterReorder<?php echo $row['id']; ?>">Total after reorder: <?php echo $row['quantity']; ?> <?php echo htmlspecialchars($row['unit']); ?></div>
                                                                </div>
                                                            </div>

                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                                <i class="fas fa-times me-1"></i> Cancel
                                                            </button>
                                                            <button type="submit" class="btn btn-warning">
                                                                <i class="fas fa-check me-1"></i> Confirm Reorder
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- View Modal -->
                                            <div class="modal fade" id="viewModal<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="viewModalLabel<?php echo $row['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="viewModalLabel<?php echo $row['id']; ?>">Material Details</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body text-center">
                                                            <div class="container-fluid">
                                                                <div class="row mb-3">
                                                                    <div class="col-md-8 mb-2">
                                                                        <h4 class="fw-bold mb-0 text-primary"><i class="fas fa-cube me-2"></i><?php echo htmlspecialchars($row['material_name']); ?></h4>
                                                                        <div class="text-muted small"><i class="fas fa-tag me-1"></i>Brand: <?php echo htmlspecialchars($row['brand'] ?? 'N/A'); ?></div>
                                                                        <div class="text-muted small"><i class="fas fa-warehouse me-1"></i>Location: <?php echo htmlspecialchars($row['location'] ?? 'N/A'); ?></div>
                                                                        <div class="text-muted small"><i class="fas fa-truck me-1"></i>Supplier: <?php echo htmlspecialchars($row['supplier_name']); ?></div>
                                                                    </div>
                                                                    <div class="col-md-4 mb-2 text-md-end">
                                                                        <span class="fw-bold text-secondary d-block mb-1"><i class="fas fa-truck me-1"></i>Delivery Status:</span>
                                                                        <span class="badge bg-<?php echo ($row['delivery_status'] == 'Delivered') ? 'success' : (($row['delivery_status'] == 'In Transit') ? 'warning' : (($row['delivery_status'] == 'Processing') ? 'info' : 'secondary')); ?> mb-2">
                                                                            <?php echo htmlspecialchars($row['delivery_status'] ?? 'N/A'); ?>
                                                                        </span>
                                                                        
                                                                        <span class="fw-bold text-secondary d-block mb-1"><i class="fas fa-info-circle me-1"></i>Status:</span>
                                                                        <span class="badge bg-<?php echo ($row['status'] == 'Available') ? 'success' : (($row['status'] == 'Low Stock') ? 'warning' : (($row['status'] == 'In Use') ? 'primary' : 'danger')); ?>">
                                                                            <?php echo htmlspecialchars($row['status']); ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                                
                                                                <div class="row mb-3">
                                                                    <div class="col-12 mb-3">
                                                                        <h6 class="fw-bold text-secondary"><i class="fas fa-file-alt me-2"></i>Specifications</h6>
                                                                        <div class="card bg-light">
                                                                            <div class="card-body">
                                                                                <?php echo !empty($row['specification']) ? nl2br(htmlspecialchars($row['specification'])) : 'No specifications provided.'; ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="row mb-3">
                                                                    <div class="col-md-4 mb-2">
                                                                        <div class="card h-100">
                                                                            <div class="card-body">
                                                                                <h6 class="card-subtitle mb-2 text-muted"><i class="fas fa-sort-numeric-up me-1"></i>Quantity</h6>
                                                                                <p class="card-text h4"><?php echo number_format($row['quantity']); ?> <small class="text-muted"><?php echo htmlspecialchars($row['unit']); ?></small></p>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-4 mb-2">
                                                                        <div class="card h-100">
                                                                            <div class="card-body">
                                                                                <h6 class="card-subtitle mb-2 text-muted"><i class="fas fa-tags me-1"></i>Category</h6>
                                                                                <p class="card-text"><?php echo htmlspecialchars($row['category']); ?></p>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-4 mb-2">
                                                                        <div class="card h-100">
                                                                            <div class="card-body">
                                                                                <h6 class="card-subtitle mb-2 text-muted"><i class="fas fa-coins me-1"></i>Price</h6>
                                                                                <p class="card-text">₱ <?php echo number_format($row['material_price'], 2); ?></p>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer justify-content-center">
                                                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Close</button>
                                                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['id']; ?>" data-bs-dismiss="modal">Edit</button>
                                                            <a href="#" class="btn btn-danger text-white delete-material-btn" data-id="<?php echo $row['id']; ?>" data-name="<?php echo htmlspecialchars($row['material_name']); ?>">
                                                                <i class="fas fa-trash"></i> Delete
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                                                                <!-- Edit Modal -->
                                            <div class="modal fade" id="editModal<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $row['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                            <h5 class="modal-title" id="editModalLabel<?php echo $row['id']; ?>">Edit Material</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                        <form id="editForm<?php echo $row['id']; ?>" action="update_materials.php" method="POST" novalidate>
                                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                        <input type="hidden" name="update" value="1">
                                                        <div class="modal-body text-center">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group">
                                                                        <label>Material Name <span class="text-danger">*</span></label>
                                                                        <input type="text" class="form-control" name="material_name" 
                                                                               value="<?php echo htmlspecialchars($row['material_name']); ?>" 
                                                                               pattern="[A-Za-z0-9\s\-\.]+" 
                                                                               minlength="2" 
                                                                               maxlength="100" 
                                                                               required>
                                                                        <div class="invalid-feedback">
                                                                            Please enter a valid material name (2-100 characters, letters, numbers, spaces, hyphens, and dots only).
                                                                        </div>
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label>Category <span class="text-danger">*</span></label>
                                                                        <select class="form-control" name="category" required>
                                                                                <option value="">Select Category</option>
                                                                                <?php $categories->data_seek(0); while($cat = $categories->fetch_assoc()): ?>
                                                                                <option value="<?php echo htmlspecialchars($cat['category']); ?>"
                                                                                    <?php echo $row['category'] == $cat['category'] ? 'selected' : ''; ?>>
                                                                                    <?php echo htmlspecialchars($cat['category']); ?>
                                                                                </option>
                                                                            <?php endwhile; ?>
                                                                        </select>
                                                                        <div class="invalid-feedback">
                                                                            Please select a category.
                                                                        </div>
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label>Quantity <span class="text-danger">*</span></label>
                                                                        <input type="number" class="form-control" name="quantity" 
                                                                               value="<?php echo $row['quantity']; ?>" 
                                                                               min="0" 
                                                                               max="999999" 
                                                                               step="1" 
                                                                               required>
                                                                        <div class="invalid-feedback">
                                                                            Please enter a valid quantity (0-999,999).
                                                                        </div>
                                                                    </div>
                                                                        <div class="form-group">
                                                                            <label>Unit <span class="text-danger">*</span></label>
                                                                            <input type="text" class="form-control" name="unit" 
                                                                                   value="<?php echo htmlspecialchars($row['unit']); ?>" 
                                                                                   pattern="[A-Za-z\s]+" 
                                                                                   minlength="1" 
                                                                                   maxlength="20" 
                                                                                   required>
                                                                            <div class="invalid-feedback">
                                                                                Please enter a valid unit (1-20 characters, letters and spaces only).
                                                                            </div>
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label>Status <span class="text-danger">*</span></label>
                                                                        <select class="form-control" name="status" required>
                                                                            <option value="Available" <?php echo $row['status'] == 'Available' ? 'selected' : ''; ?>>Available</option>
                                                                        </select>
                                                                        <div class="invalid-feedback">
                                                                            Please select a status.
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group">
                                                                        <label>Supplier <span class="text-danger">*</span></label>
                                                                        <select class="form-control" name="supplier_name" required>
                                                                                <option value="">Select Supplier</option>
                                                                                <?php $suppliers->data_seek(0); while($sup = $suppliers->fetch_assoc()): ?>
                                                                                <option value="<?php echo htmlspecialchars($sup['supplier_name']); ?>"
                                                                                    <?php echo $row['supplier_name'] == $sup['supplier_name'] ? 'selected' : ''; ?>>
                                                                                    <?php echo htmlspecialchars($sup['supplier_name']); ?>
                                                                                </option>
                                                                            <?php endwhile; ?>
                                                                        </select>
                                                                        <div class="invalid-feedback">
                                                                            Please select a supplier.
                                                                        </div>
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label>Material Price <span class="text-danger">*</span></label>
                                                                        <input type="number" step="0.01" class="form-control" name="material_price" 
                                                                               value="<?php echo $row['material_price']; ?>" 
                                                                               min="0" 
                                                                               max="999999.99" 
                                                                               required>
                                                                        <div class="invalid-feedback">
                                                                            Please enter a valid price (0-999,999.99).
                                                                        </div>
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label>Labor/Other Cost</label>
                                                                        <input type="number" step="0.01" class="form-control" name="labor_other" 
                                                                               value="<?php echo $row['labor_other']; ?>" 
                                                                               min="0" 
                                                                               max="999999.99">
                                                                        <div class="invalid-feedback">
                                                                            Please enter a valid cost (0-999,999.99).
                                                                        </div>
                                                                    </div>
                                                                   
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer justify-content-center">
                                                                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Close</button>
                                                            <button type="submit" name="update" class="btn btn-primary">Update Material</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Backorder Modal -->
                                        <div class="modal fade" id="backorderModal<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="backorderModalLabel<?php echo $row['id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="backorderModalLabel<?php echo $row['id']; ?>">
                                                            <i class="fas fa-exclamation-triangle text-warning me-2"></i>Create Backorder
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <form id="backorderForm<?php echo $row['id']; ?>" action="backorder_material.php" method="POST" novalidate>
                                                        <input type="hidden" name="material_id" value="<?php echo $row['id']; ?>">
                                                        <div class="modal-body">
                                                            <div class="alert alert-info">
                                                                <h6 class="mb-2"><i class="fas fa-info-circle me-2"></i>Material Information</h6>
                                                                <p class="mb-1"><strong>Material:</strong> <?php echo htmlspecialchars($row['material_name']); ?></p>
                                                                <p class="mb-1"><strong>Current Quantity:</strong> <?php echo $row['quantity']; ?> <?php echo htmlspecialchars($row['unit']); ?></p>
                                                                <p class="mb-0"><strong>Status:</strong> <span class="badge bg-<?php echo $row['status'] == 'Low Stock' ? 'warning' : ($row['status'] == 'Available' ? 'success' : ($row['status'] == 'In Use' ? 'primary' : 'danger')); ?>"><?php echo $row['status']; ?></span></p>
                                                            </div>
                                                            
                                                            <div class="form-group mb-3">
                                                                <label>Quantity for Backorder <span class="text-danger">*</span></label>
                                                                <div class="input-group">
                                                                    <input type="number" class="form-control" name="deduct_quantity" 
                                                                           id="deductQuantity<?php echo $row['id']; ?>" 
                                                                           min="1" 
                                                                           max="<?php echo $row['quantity']; ?>" 
                                                                           value="1" 
                                                                           required>
                                                                    <span class="input-group-text"><?php echo htmlspecialchars($row['unit']); ?></span>
                                                                </div>
                                                                <div class="form-text">This quantity will be added to backorders. (Max: <?php echo $row['quantity']; ?> <?php echo htmlspecialchars($row['unit']); ?>)</div>
                                                                <div class="invalid-feedback">
                                                                    Please enter a valid quantity (1-<?php echo $row['quantity']; ?>).
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="form-group mb-3">
                                                                <label>Reason for Backorder <span class="text-danger">*</span></label>
                                                                <select class="form-control" name="reason" id="backorderReason<?php echo $row['id']; ?>" required>
                                                                    <option value="">Select a reason...</option>
                                                                    <option value="Damaged">Damaged/Defective</option>
                                                                    <option value="Quality Issue">Quality Issue</option>
                                                                    <option value="Wrong Specification">Wrong Specification</option>
                                                                    <option value="Expired">Expired</option>
                                                                    <option value="Lost">Lost/Misplaced</option>
                                                                    <option value="Theft">Theft</option>
                                                                    <option value="Natural Disaster">Natural Disaster</option>
                                                                    <option value="Other">Other</option>
                                                                </select>
                                                                <div class="invalid-feedback">
                                                                    Please select a reason for the backorder.
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="form-group mb-3" id="otherReasonDiv<?php echo $row['id']; ?>" style="display: none;">
                                                                <label>Other Reason <span class="text-danger">*</span></label>
                                                                <textarea class="form-control" name="other_reason" 
                                                                          id="otherReason<?php echo $row['id']; ?>" 
                                                                          rows="3" 
                                                                          maxlength="500" 
                                                                          placeholder="Please specify the reason for backorder..."></textarea>
                                                                <div class="invalid-feedback">
                                                                    Please specify the reason for backorder.
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="alert alert-warning">
                                                                <h6 class="mb-2"><i class="fas fa-exclamation-triangle me-2"></i>Summary</h6>
                                                                <p class="mb-1"><strong>Current Stock:</strong> <span id="currentStock<?php echo $row['id']; ?>"><?php echo $row['quantity']; ?></span> <?php echo htmlspecialchars($row['unit']); ?></p>
                                                                <p class="mb-0"><strong>Backorder Quantity:</strong> <span id="backorderSummary<?php echo $row['id']; ?>">1</span> <?php echo htmlspecialchars($row['unit']); ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger">
                                                                <i class="fas fa-exclamation-triangle me-2"></i>Create Backorder
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <nav aria-label="Page navigation" class="mt-3 mb-3">
                            <ul class="pagination justify-content-center custom-pagination-green mb-0">
                                <li class="page-item<?php if($page <= 1) echo ' disabled'; ?>">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category_filter) ? '&category=' . urlencode($category_filter) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($supplier_filter) ? '&supplier=' . urlencode($supplier_filter) : ''; ?>">Previous</a>
                                        </li>
                                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item<?php if($i == $page) echo ' active'; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category_filter) ? '&category=' . urlencode($category_filter) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($supplier_filter) ? '&supplier=' . urlencode($supplier_filter) : ''; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                <li class="page-item<?php if($page >= $total_pages) echo ' disabled'; ?>">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category_filter) ? '&category=' . urlencode($category_filter) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($supplier_filter) ? '&supplier=' . urlencode($supplier_filter) : ''; ?>">Next</a>
                                        </li>
                                </ul>
                            </nav>
                    </div>
                    </div>
                </div>
            </div>
                
            </div>
        </div>
    </div>
    <!-- /#page-content-wrapper -->
    </div>

      <!-- Add Material Modal -->
      <div class="modal fade" id="addMaterialModal" tabindex="-1" aria-labelledby="addMaterialModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="addMaterialModalLabel">
              <i class="fas fa-plus me-2"></i>Add New Material
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <!-- WIZARD PAGE 1: ADDING MATERIALS -->
            <div id="add-material-page-1">
             
                <!-- Column 1: Compare Suppliers -->
                <div class="col">
                  <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                      <h6 class="mb-0"><i class="fas fa-balance-scale me-2"></i>Compare Suppliers</h6>
                    </div>
                    <div class="card-body">
                      <div class="row mb-3">
                        <div class="col-md-6">
                          <label for="materialNameSelect" class="form-label">Select Material:</label>
                          <select class="form-control" id="materialNameSelect">
                            <option value="">Choose a material...</option>
                          </select>
                        </div>
                        <div class="col-md-3">
                          <label for="quantityInput" class="form-label">Quantity (max 1000):</label>
                          <input type="number" class="form-control" id="quantityInput" value="1" min="1" max="1000" oninput="validateQuantity(this)">
                          <div class="form-text">Maximum quantity: 1000</div>
                        </div>
                        <div class="col-md-3">
                          <label class="form-label">&nbsp;</label>
                          <button type="button" class="btn btn-info w-100" id="compareSuppliersBtn">
                            <i class="fas fa-search"></i> Find
                          </button>
                        </div>
                      </div>

                      <!-- Supplier Comparison Results -->
                      <div id="supplierComparisonSection" style="display: none;">
                        <div class="table-responsive">
                          <table class="table table-bordered table-striped">
                            <thead class="table-light">
                              <tr>
                                <th>
                                  <input type="checkbox" id="selectAllSuppliers">
                                  <label for="selectAllSuppliers" class="ms-1">All</label>
                                </th>
                                <th>Supplier</th>
                                <th>Price</th>
                                <th>Brand</th>
                                <th>Specs</th>
                                <th>Lead Time</th>
                                <th>Best Deal</th>
                              </tr>
                            </thead>
                            <tbody id="supplierTableBody">
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Column 2: Material Details -->
                <div class="col">
                  <!-- Material Details Form -->
                  <div id="materialDetailsForm" style="display: none;">
                    <div class="card mb-4">
                      <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Material Details</h6>
                      </div>
                      <div class="card-body">
                        <div class="row">
                          <div class="col-md-6">
                            <div class="form-group mb-3">
                              <label>Material Name</label>
                              <input type="text" class="form-control" id="materialNameInput" readonly>
                            </div>
                            <div class="form-group mb-3">
                              <label>Category <span class="text-danger">*</span></label>
                              <input type="text" class="form-control" id="categoryInput" readonly>
                            </div>
                            <div class="form-group mb-3">
                              <label>Brand</label>
                              <input type="text" class="form-control" id="brandInput" readonly>
                            </div>
                            <div class="form-group mb-3">
                            <label>Location</label>
                            <select class="form-control" id="locationSelect" required>
                                <?php if ($all_warehouses) { $all_warehouses->data_seek(0); while($wh = $all_warehouses->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($wh['warehouse']); ?>">
                                    <?php echo htmlspecialchars($wh['warehouse']); ?>
                                </option>
                                <?php endwhile; } ?>
                            </select>
                            <div class="invalid-feedback">
                                Please select a location.
                            </div>
                            </div>
                            <div class="row">
                              <div class="col-6">
                                <div class="form-group mb-3">
                                  <label>Quantity <span class="text-danger">*</span></label>
                                  <input type="number" class="form-control" id="finalQuantityInput" min="1" max="1000" required>
                                  <div class="invalid-feedback">
                                    Please enter a valid quantity (1-1000).
                                  </div>
                                  <div class="form-text">Maximum allowed quantity: 1000</div>
                                </div>
                              </div>
                              <div class="col-6">
                                <div class="form-group mb-3">
                                  <label>Unit</label>
                                  <input type="text" class="form-control" id="unitInput" readonly>
                                </div>
                              </div>
                            </div>
                          </div>
                          <div class="col-md-6">
                            <div class="form-group mb-3">
                              <label>Selected Supplier</label>
                              <input type="text" class="form-control" id="selectedSupplierInput" readonly>
                            </div>
                            <div class="form-group mb-3">
                              <label>Material Price</label>
                              <input type="number" step="0.01" class="form-control" id="materialPriceInput" readonly>
                            </div>
                            <div class="form-group mb-3">
                              <label>Specification</label>
                              <input type="text" class="form-control" id="specificationInput" readonly>
                            </div>
                            <div class="form-group mb-3">
                              <label>Labor/Other Cost</label>
                              <input type="number" step="0.01" class="form-control" id="laborOtherInput" value="0" min="0" max="999999.99">
                              <div class="invalid-feedback">
                                Please enter a valid cost (0-999,999.99).
                              </div>
                            </div>
                            <div class="form-group mb-3">
                              <label>Status <span class="text-danger">*</span></label>
                              <select class="form-control" id="statusSelect" required>
                                <option value="Available" selected>Available</option>
                              </select>
                              <div class="invalid-feedback">
                                Please select a status.
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
        
            
            <!-- WIZARD PAGE 2: VIEWING CART -->
            <div id="add-material-page-2" style="display: none;">
              <!-- Materials Selection Cart -->
              <div id="materialsCart">
                <div class="card">
                  <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Selected Materials</h6>
                  </div>
                  <div class="card-body">
                    <div class="table-responsive">
                      <table class="table table-bordered table-striped">
                        <thead class="table-light">
                          <tr>
                            <th>Material</th>
                            <th>Supplier</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Price</th>
                            <th>Total</th>
                            <th>Action</th>
                          </tr>
                        </thead>
                        <tbody id="cartTableBody">
                        </tbody>
                      </table>
                    </div>
                    <div class="d-flex justify-content-end align-items-center mt-2">
                      <div class="text-end">
                        <strong>Total Items: <span id="cartTotalItems">0</span></strong>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer d-flex justify-content-between">
            <div>
              <button type="button" class="btn btn-outline-secondary" id="wizardPrevBtn" disabled><i class="fas fa-chevron-left"></i> Back to Add</button>
            </div>
            <div>
              <button type="button" class="btn btn-warning" id="addToCartBtn" style="display: none;">
                <i class="fas fa-cart-plus"></i> Add to Cart
              </button>
              <button type="button" class="btn btn-success" id="saveAllMaterialsBtn" style="display: none;">
                <i class="fas fa-save"></i> Save All Materials
              </button>
            </div>
            <div>
              <button type="button" class="btn btn-outline-secondary" id="wizardNextBtn">View Cart <i class="fas fa-chevron-right"></i></button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- Feedback Modal (Unified for Success/Error) -->
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
    <!-- Delete Material Modal -->
    <div class="modal fade" id="deleteMaterialModal" tabindex="-1" aria-labelledby="deleteMaterialModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="deleteMaterialModalLabel">Confirm Delete</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to delete <strong id="materialName"></strong>?</p>
            <p class="text-danger">This action cannot be undone.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <a href="#" id="confirmDeleteMaterial" class="btn btn-danger">Delete</a>
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
    <!-- Export PDF Confirmation Modal (only one per page) -->
    <div class="modal fade" id="exportPdfModal" tabindex="-1" aria-labelledby="exportPdfModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exportPdfModalLabel">Export as PDF</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to export the materials list as PDF?</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <a href="export_materials_pdf.php" id="confirmExportPdf" class="btn btn-danger">Export</a>
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

    <div class="modal fade" id="backorderSuccessModal" tabindex="-1" aria-labelledby="backorderSuccessModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="backorderSuccessModalLabel">Backorder Successful!</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <p id="backorderSuccessMessage">Backorder completed and quantity updated.</p>
            </div>
            </div>
        </div>
    </div>
    
    <!-- Reorder Success Modal -->
    <div class="modal fade" id="reorderSuccessModal" tabindex="-1" aria-labelledby="reorderSuccessModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="reorderSuccessModalLabel">Reorder Successful!</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <p id="reorderSuccessMessage">Reorder completed successfully!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reorder Modal -->
    <div class="modal fade" id="reorderModal" tabindex="-1" aria-labelledby="reorderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
            <form id="reorderForm">
                <div class="modal-header">
                <h5 class="modal-title" id="reorderModalLabel">Reorder Material</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                <p id="reorderSummary"></p>
                <input type="hidden" id="reorderMaterialId" name="material_id">
                <input type="number" class="form-control" id="reorderQty" name="reorderQty" readonly>
                </div>
                <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelReorderBtn" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Confirm Reorder</button>
                </div>
            </form>
            </div>
        </div>
        </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="successModalLabel">Success</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="successMessage"></p>
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
                <div class="modal-header">
                    <h5 class="modal-title" id="errorModalLabel">Error</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="errorMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Handle success and error messages from the server
        document.addEventListener('DOMContentLoaded', function() {
            // Check for success message in the URL
            const urlParams = new URLSearchParams(window.location.search);
            const successMessage = '<?php echo isset($_SESSION["success_message"]) ? addslashes($_SESSION["success_message"]) : ""; ?>';
            const errorMessage = '<?php echo isset($_SESSION["error_message"]) ? addslashes($_SESSION["error_message"]) : ""; ?>';

            // Initialize modals
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));

            // Show success modal if there's a success message
            if (successMessage) {
                document.getElementById('successMessage').textContent = successMessage;
                successModal.show();
                
                // Clear the success message from the URL
                const newUrl = window.location.pathname;
                window.history.replaceState({}, '', newUrl);
                
                // Clear the session message
                <?php unset($_SESSION['success_message']); ?>
            }

            // Show error modal if there's an error message
            if (errorMessage) {
                document.getElementById('errorMessage').textContent = errorMessage;
                errorModal.show();
                
                // Clear the error message from the URL
                const newUrl = window.location.pathname;
                window.history.replaceState({}, '', newUrl);
                
                // Clear the session message
                <?php unset($_SESSION['error_message']); ?>
            }

            // Add confirmation to all receive forms
            document.querySelectorAll('form[action*="receive_material.php"]').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const materialName = this.querySelector('input[name="material_name"]')?.value || 'this material';
                    if (!confirm(`Are you sure you want to mark ${materialName} as received?`)) {
                        e.preventDefault();
                        return false;
                    }
                    return true;
                });
            });
        });
    </script>
    <script src="po_materials.js"></script>
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
        showFeedbackModal(true, 'Material added successfully!', '', 'added');
      } else if (params.get('updated') === '1') {
        showFeedbackModal(true, 'Material updated successfully!', '', 'updated');
      } else if (params.get('deleted') === '1') {
        showFeedbackModal(true, 'Material deleted successfully!', '', 'deleted');
      } else if (params.get('reordered') === '1') {
        showFeedbackModal(true, 'Material reordered successfully!', '', 'reordered');
      } else if (params.get('backordered') === '1') {
        showFeedbackModal(true, 'Backorder successfully!', '', 'backordered');
      } else if (params.get('error') === 'duplicate') {
        const materialName = params.get('material') || 'this material';
        const status = params.get('status');
        const supplier = params.get('supplier');
        
        if (status === 'approved') {
          const supplierInfo = supplier ? ` from supplier '${supplier}'` : "";
          showFeedbackModal(false, `Material '${materialName}'${supplierInfo} already exists and is approved. Cannot add duplicate materials from the same supplier.`, '', 'duplicate');
        } else if (status === 'pending') {
          const supplierInfo = supplier ? ` from supplier '${supplier}'` : "";
          showFeedbackModal(false, `There is already a pending request for material '${materialName}'${supplierInfo}. Please wait for approval or contact admin.`, '', 'duplicate');
        } else {
          showFeedbackModal(false, `Material '${materialName}' already exists. Cannot add duplicate materials.`, '', 'duplicate');
        }
      } else if (params.get('error')) {
        showFeedbackModal(false, decodeURIComponent(params.get('error')), '', 'error');
      }
    })();
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.exportPdfBtn').forEach(function(exportBtn) {
        exportBtn.addEventListener('click', function(e) {
        e.preventDefault();
        var modal = new bootstrap.Modal(document.getElementById('exportPdfModal'));
        modal.show();
        });
    });
    var exportBtn = document.getElementById('confirmExportPdf');
    if (exportBtn) {
        exportBtn.addEventListener('click', function(e) {
        e.preventDefault();
        var modalEl = document.getElementById('exportPdfModal');
        var modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) modalInstance.hide();
        setTimeout(function() {
            window.open('export_materials_pdf.php', '_blank');
        }, 300);
        });
    }
    });
</script>

<script>
// Enhanced Add Material Modal Functionality
document.addEventListener('DOMContentLoaded', function() {
    const addMaterialModal = document.getElementById('addMaterialModal');
    const wizardPage1 = document.getElementById('add-material-page-1');
    const wizardPage2 = document.getElementById('add-material-page-2');
    const wizardPrevBtn = document.getElementById('wizardPrevBtn');
    const wizardNextBtn = document.getElementById('wizardNextBtn');

    const materialNameSelect = document.getElementById('materialNameSelect');
    const quantityInput = document.getElementById('quantityInput');
    const compareSuppliersBtn = document.getElementById('compareSuppliersBtn');
    const supplierComparisonSection = document.getElementById('supplierComparisonSection');
    const materialDetailsForm = document.getElementById('materialDetailsForm');
    const materialsCart = document.getElementById('materialsCart');
    const addToCartBtn = document.getElementById('addToCartBtn');
    const saveAllMaterialsBtn = document.getElementById('saveAllMaterialsBtn');
    
    let selectedSupplier = null;
    let materialsCartArray = [];
    let currentWizardPage = 1;
    
    // --- Wizard Navigation ---
    function showWizardPage(page) {
        currentWizardPage = page;
        if (page === 1) {
            wizardPage1.style.display = 'block';
            wizardPage2.style.display = 'none';
            wizardPrevBtn.disabled = true;
            wizardNextBtn.disabled = false;
            
            // Show Add to Cart if details form is visible, otherwise hide
            addToCartBtn.style.display = materialDetailsForm.style.display === 'block' ? 'inline-block' : 'none';
            saveAllMaterialsBtn.style.display = 'none';

        } else if (page === 2) {
            wizardPage1.style.display = 'none';
            wizardPage2.style.display = 'block';
            wizardPrevBtn.disabled = false;
            wizardNextBtn.disabled = true;

            addToCartBtn.style.display = 'none';
            // Show Save button only if cart has items
            saveAllMaterialsBtn.style.display = materialsCartArray.length > 0 ? 'inline-block' : 'none';
        }
    }

    wizardNextBtn.addEventListener('click', function() {
        if (currentWizardPage < 2) {
            showWizardPage(currentWizardPage + 1);
        }
    });

    wizardPrevBtn.addEventListener('click', function() {
        if (currentWizardPage > 1) {
            showWizardPage(currentWizardPage - 1);
        }
    });
    
    // Load materials when add modal opens
    addMaterialModal.addEventListener('show.bs.modal', function() {
        loadMaterialsForAdd();
        resetForm(); // This will also reset the wizard to page 1
    });
    
    // Load available materials for add modal
    function loadMaterialsForAdd() {
        fetch('get_supplier_comparison.php?action=get_materials')
            .then(response => response.json())
            .then(data => {
                materialNameSelect.innerHTML = '<option value="">Choose a material...</option>';
                data.forEach(material => {
                    const option = document.createElement('option');
                    option.value = material;
                    option.textContent = material;
                    materialNameSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error loading materials:', error);
            });
    }
    
    // Reset form
    function resetForm() {
        materialNameSelect.value = '';
        quantityInput.value = '1';
        supplierComparisonSection.style.display = 'none';
        materialDetailsForm.style.display = 'none';
        
        selectedSupplier = null;
        materialsCartArray = [];
        updateCartDisplay(); // This will hide save button too
        showWizardPage(1); // Reset to first page
    }
    
    // Compare suppliers button click
    compareSuppliersBtn.addEventListener('click', function() {
        if (!materialNameSelect.value) {
            showFeedbackModal(false,'Please select a material first.');
            return;
        }
        compareSuppliersForAdd();
    });
    
    // Compare suppliers for add modal
    function compareSuppliersForAdd() {
        const materialName = materialNameSelect.value;
        const quantity = quantityInput.value;
        
        console.log('Comparing suppliers for:', materialName, 'quantity:', quantity);
        
        fetch(`get_supplier_comparison.php?action=compare_suppliers&material_name=${encodeURIComponent(materialName)}&quantity=${quantity}`)
            .then(response => response.json())
            .then(data => {
                console.log('Supplier data received:', data);
                
                if (data.error || !data.suppliers || data.suppliers.length === 0) {
                    showFeedbackModal(false,'No suppliers found for this material.');
                    return;
                }
                
                showSupplierComparison(data.suppliers);
                // Also show the material details form immediately
                showMaterialDetailsForm();
            })
            .catch(error => {
                console.error('Error comparing suppliers:', error);
                showFeedbackModal(false,'Error loading supplier data.');
            });
    }
    
    // Show supplier comparison in add modal
    function showSupplierComparison(suppliers) {
        console.log('Showing supplier comparison for', suppliers.length, 'suppliers');
        console.log('Suppliers data:', suppliers); // Debug: Log the full suppliers data
        
        const tbody = document.getElementById('supplierTableBody');
        console.log('supplierTableBody:', tbody);
        
        tbody.innerHTML = '';
        
        // Set unit from first supplier (they should all have the same unit for the same material)
        if (suppliers.length > 0) {
            console.log('First supplier unit:', suppliers[0].unit); // Debug: Log the unit value
            document.getElementById('unitInput').value = suppliers[0].unit || '';
        }
        
        suppliers.forEach(supplier => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <input type="checkbox" name="selectedSuppliers" value="${supplier.supplier_name}" 
                           data-price="${supplier.material_price}" data-lead-time="${supplier.lead_time}" data-unit="${supplier.unit || ''}" data-quantity="${supplier.quantity || '0'}" data-labor_other="${supplier.labor_other || 0}" data-category="${supplier.category || ''}" data-brand="${supplier.brand || ''}" 
               data-specification="${supplier.specification || ''}">
                </td>
                <td><strong>${supplier.supplier_name}</strong></td>
                <td>₱ ${supplier.material_price.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                <td>${supplier.brand}</td>
                <td>${supplier.specification}</td>
                <td>${supplier.lead_time} days</td>
                <td>
                    ${supplier.best_deal ? `<span class="badge bg-success">${supplier.best_deal}</span>` : ''}
                </td>
            `;
            tbody.appendChild(row);
        });
        
        console.log('Setting supplierComparisonSection display to block');
        supplierComparisonSection.style.display = 'block';
        
        // Add event listeners to checkboxes
        document.querySelectorAll('input[name="selectedSuppliers"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateSelectedSuppliers();
            });
        });
        
        // Add event listener for select all checkbox
        const selectAllCheckbox = document.getElementById('selectAllSuppliers');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('input[name="selectedSuppliers"]');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateSelectedSuppliers();
            });
        }
    }
    
    // Show material details form
    function showMaterialDetailsForm() {
        console.log('Showing material details form');
        
        document.getElementById('materialNameInput').value = materialNameSelect.value;
        document.getElementById('finalQuantityInput').value = quantityInput.value;
        
        // Set default supplier if available
        if (selectedSupplier) {
            document.getElementById('selectedSupplierInput').value = selectedSupplier.name;
            document.getElementById('categoryInput').value = selectedSupplier.category;
            document.getElementById('materialPriceInput').value = selectedSupplier.price;
            document.getElementById('brandInput').value = selectedSupplier.brand || '';
            document.getElementById('specificationInput').value = selectedSupplier.specification || '';
            document.getElementById('laborOtherInput').value = selectedSupplier.labor_other || '0';
            document.getElementById('laborOtherInput').readOnly = true;
        } else {
            document.getElementById('selectedSupplierInput').value = '';
            document.getElementById('categoryInput').value = '';
            document.getElementById('brandInput').value = '';
            document.getElementById('specificationInput').value = '';
            document.getElementById('materialPriceInput').value = '';
            document.getElementById('laborOtherInput').value = '';
            document.getElementById('laborOtherInput').readOnly = false;
        }
        
        console.log('Setting materialDetailsForm display to block');
        materialDetailsForm.style.display = 'block';
        addToCartBtn.style.display = 'inline-block';
        
        // Add real-time validation to form fields
        const categoryInput = document.getElementById('categoryInput');
        const finalQuantityInput = document.getElementById('finalQuantityInput');
        const statusSelect = document.getElementById('statusSelect');
        const laborOtherInput = document.getElementById('laborOtherInput');
        
        // Category validation
        if (categoryInput) {
            categoryInput.addEventListener('change', function() {
                validateAddMaterialField(this, 'category');
            });
        }
        
        // Quantity validation
        if (finalQuantityInput) {
            finalQuantityInput.addEventListener('input', function() {
                validateAddMaterialField(this, 'quantity');
            });
            finalQuantityInput.addEventListener('blur', function() {
                validateAddMaterialField(this, 'quantity');
            });
        }
        
        // Status validation
        if (statusSelect) {
            statusSelect.addEventListener('change', function() {
                validateAddMaterialField(this, 'status');
            });
        }
        
        // Labor/other cost validation
        if (laborOtherInput) {
            laborOtherInput.addEventListener('input', function() {
                validateAddMaterialField(this, 'cost');
            });
            laborOtherInput.addEventListener('blur', function() {
                validateAddMaterialField(this, 'cost');
            });
        }
    }
    
    // Validate add material form field
    function validateAddMaterialField(field, type) {
        const value = field.value.trim();
        
        // Remove existing validation classes
        field.classList.remove('is-valid', 'is-invalid');
        
        let isValid = true;
        
        switch (type) {
            case 'category':
            case 'status':
                if (!value) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.add('is-valid');
                }
                break;
                
            case 'quantity':
                const quantityValue = parseInt(value);
                if (!value || quantityValue < 1 || quantityValue > 1000) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.add('is-valid');
                }
                break;
                
            case 'cost':
                if (value) {
                    const costValue = parseFloat(value);
                    if (costValue < 0 || costValue > 999999.99) {
                        field.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        field.classList.add('is-valid');
                    }
                }
                break;
        }
        
        return isValid;
    }
    
    // Update selected suppliers
    function updateSelectedSuppliers() {
        const selectedCheckboxes = document.querySelectorAll('input[name="selectedSuppliers"]:checked');
        
        if (selectedCheckboxes.length > 0) {
            // Show material details form with first selected supplier
            const firstSupplier = selectedCheckboxes[0];
            const unitValue = firstSupplier.getAttribute('data-unit'); // Get unit from the data attribute
            console.log('Selected supplier unit from checkbox:', unitValue); // Debug: Log the unit value
            
            selectedSupplier = {
                name: firstSupplier.value,
                price: parseFloat(firstSupplier.getAttribute('data-price')),
                lead_time: parseInt(firstSupplier.getAttribute('data-lead-time')),
                unit: unitValue,
                category: firstSupplier.getAttribute('data-category') || '',
                quantity: parseInt(firstSupplier.getAttribute('data-quantity')),
                brand: firstSupplier.getAttribute('data-brand') || '',
                specification: firstSupplier.getAttribute('data-specification') || '',
                labor_other: parseFloat(firstSupplier.getAttribute('data-labor_other'))
            };
            console.log('Selected supplier object:', selectedSupplier); // Debug: Log the full selected supplier object
            
            // Update the form fields with selected supplier info
            document.getElementById('selectedSupplierInput').value = selectedSupplier.name;
            document.getElementById('materialPriceInput').value = selectedSupplier.price;
            document.getElementById('unitInput').value = selectedSupplier.unit; // Set unit in the form
            document.getElementById('categoryInput').value = selectedSupplier.category;
            document.getElementById('brandInput').value = selectedSupplier.brand;
            document.getElementById('specificationInput').value = selectedSupplier.specification;
            document.getElementById('laborOtherInput').value = selectedSupplier.labor_other;
            document.getElementById('laborOtherInput').readOnly = true;
            
            // Show the Add to Cart button when suppliers are selected
            addToCartBtn.style.display = 'inline-block';
        } else {
            // Clear supplier info but keep form visible
            document.getElementById('selectedSupplierInput').value = '';
            document.getElementById('materialPriceInput').value = '';
            document.getElementById('unitInput').value = ''; // Clear unit in the form
            selectedSupplier = null;
            
            // Hide the Add to Cart button when no suppliers are selected
            addToCartBtn.style.display = 'none';
        }
    }
    
    // Function to check if material exists in database
    function checkMaterialExists(materialName, supplierName = '') {
        let url = `check_material_exists.php?material_name=${encodeURIComponent(materialName)}`;
        if (supplierName) {
            url += `&supplier_name=${encodeURIComponent(supplierName)}`;
        }
        
        return fetch(url)
            .then(response => response.json())
            .then(data => data)
            .catch(error => {
                console.error('Error checking material existence:', error);
                return { exists: false }; // Allow adding if check fails
            });
    }
    
    // Function to validate add material form
    function validateAddMaterialForm() {
        const category = document.getElementById('categoryInput');
        const quantity = document.getElementById('finalQuantityInput');
        const status = document.getElementById('statusSelect');
        const labor_other = document.getElementById('laborOtherInput');
        
        // Clear previous validation states
        [category, quantity, status, labor_other].forEach(field => {
            if (field) {
                field.classList.remove('is-valid', 'is-invalid');
            }
        });
        
        let isValid = true;
        let errorMessage = '';
        
        // Validate category
        if (!category.value) {
            category.classList.add('is-invalid');
            isValid = false;
            errorMessage += '• Please select a category.\n';
        } else {
            category.classList.add('is-valid');
        }
        
        // Validate quantity
        const quantityValue = parseInt(quantity.value);
        if (!quantity.value || quantityValue < 1 || quantityValue > 1000) {
            quantity.classList.add('is-invalid');
            isValid = false;
            errorMessage += '• Please enter a valid quantity (1-1000).\n';
        } else {
            quantity.classList.add('is-valid');
        }
        
        // Validate status
        if (!status.value) {
            status.classList.add('is-invalid');
            isValid = false;
            errorMessage += '• Please select a status.\n';
        } else {
            status.classList.add('is-valid');
        }
        
        // Validate labor/other cost
        if (labor_other.value) {
            const laborValue = parseFloat(labor_other.value);
            if (laborValue < 0 || laborValue > 999999.99) {
                labor_other.classList.add('is-invalid');
                isValid = false;
                errorMessage += '• Please enter a valid cost (0-999,999.99).\n';
            } else {
                labor_other.classList.add('is-valid');
            }
        }
        
    
        
        return isValid;
    }
    
    // Function to add material to cart
    addToCartBtn.addEventListener('click', function() {
        const materialName = document.getElementById('materialNameInput').value;
        const category = document.getElementById('categoryInput').value;
        const brand = document.getElementById('brandInput').value;
        const specification = document.getElementById('specificationInput').value;
        const quantity = document.getElementById('finalQuantityInput');
        const status = document.getElementById('statusSelect');
        const labor_other = document.getElementById('laborOtherInput');
        
        // Validate form
        if (!validateAddMaterialForm()) {
            return;
        }
        
        // Get selected suppliers
        const selectedCheckboxes = document.querySelectorAll('input[name="selectedSuppliers"]:checked');
        if (selectedCheckboxes.length === 0) {
            showFeedbackModal(false,'Please select at least one supplier.');
            return;
        }
        
        // Check for duplicate materials in cart (by material name and supplier)
        const selectedSuppliers = Array.from(selectedCheckboxes).map(cb => cb.value);
        const duplicateInCart = materialsCartArray.some(material => 
            material.material_name === materialName && selectedSuppliers.includes(material.supplier_name)
        );
        
        if (duplicateInCart) {
            showFeedbackModal(false, `Material '${materialName}' from one of the selected suppliers is already in the cart. Cannot add duplicates.`);
            return;
        }
        
        // Check if material already exists in database for each selected supplier
        let hasExistingMaterial = false;
        let existingMaterialMessage = '';
        
        const checkPromises = selectedSuppliers.map(supplierName => 
            checkMaterialExists(materialName, supplierName)
        );
        
        Promise.all(checkPromises).then(results => {
            for (let i = 0; i < results.length; i++) {
                const result = results[i];
                const supplierName = selectedSuppliers[i];
                
                if (result.exists) {
                    hasExistingMaterial = true;
                    existingMaterialMessage += `• Material '${materialName}' from supplier '${supplierName}' already exists.\n`;
                }
            }
            
            if (hasExistingMaterial) {
                showFeedbackModal(false, `Cannot add duplicate materials:\n${existingMaterialMessage}`);
                return;
            }
            
            // Continue with adding to cart if no duplicates
            addMaterialToCart();
        });
    });
    
    // Function to add material to cart
    function addMaterialToCart() {
        const selectedCheckboxes = document.querySelectorAll('input[name="selectedSuppliers"]:checked');
        
        if (selectedCheckboxes.length === 0) {
           showFeedbackModal(false,'Please select at least one supplier.');
            return;
        }
        
        // Validate all form fields
        const category = document.getElementById('categoryInput');
        const quantity = document.getElementById('finalQuantityInput');
        const unit = document.getElementById('unitInput');
        const status = document.getElementById('statusSelect');
        const labor_other = document.getElementById('laborOtherInput');
        
        // Clear previous validation states
        [category, quantity, status, labor_other].forEach(field => {
            if (field) {
                field.classList.remove('is-valid', 'is-invalid');
            }
        });
        
        let isValid = true;
        let errorMessage = '';
        
        // Validate category
        if (!category.value) {
            category.classList.add('is-invalid');
            isValid = false;
            errorMessage += '• Please select a category.\n';
        } else {
            category.classList.add('is-valid');
        }
        
        // Validate quantity
        const quantityValue = parseInt(quantity.value);
        if (!quantity.value || quantityValue < 1 || quantityValue > 1000) {
            quantity.classList.add('is-invalid');
            isValid = false;
            errorMessage += '• Please enter a valid quantity (1-1000).\n';
        } else {
            quantity.classList.add('is-valid');
        }
        
        // Validate status
        if (!status.value) {
            status.classList.add('is-invalid');
            isValid = false;
            errorMessage += '• Please select a status.\n';
        } else {
            status.classList.add('is-valid');
        }
        
        // Validate labor/other cost
        if (labor_other.value) {
            const laborValue = parseFloat(labor_other.value);
            if (laborValue < 0 || laborValue > 999999.99) {
                labor_other.classList.add('is-invalid');
                isValid = false;
                errorMessage += '• Please enter a valid cost (0-999,999.99).\n';
            } else {
                labor_other.classList.add('is-valid');
            }
        }
        
        if (!isValid) {
            showFeedbackModal(false,'Please correct the following errors:\n\n' + errorMessage);
            return;
        }
        
        // Check if quantity exceeds available stock for any selected supplier
        const materialName = document.getElementById('materialNameInput').value;
        let hasInsufficientStock = false;
        let insufficientStockMessage = '';
        
        // Check stock for each selected supplier
        selectedCheckboxes.forEach(checkbox => {
            const supplierName = checkbox.value;
            const supplierQuantity = parseInt(checkbox.getAttribute('data-quantity') || '0');
            
            if (quantityValue > supplierQuantity) {
                hasInsufficientStock = true;
                insufficientStockMessage += `\n- ${supplierName}: Only ${supplierQuantity} available (requested: ${quantityValue})`;
            }
        });
        
        if (hasInsufficientStock) {
            showFeedbackModal(false,`Insufficient stock for the requested quantity:${insufficientStockMessage}\n\nPlease reduce the quantity or select different suppliers.`);
            return;
        }
        
        // Add material for each selected supplier
        selectedCheckboxes.forEach(checkbox => {
            const materialData = {
                material_name: document.getElementById('materialNameInput').value,
                category: document.getElementById('categoryInput').value,
                brand: document.getElementById('brandInput').value,
                specification: document.getElementById('specificationInput').value,
                quantity: quantityValue,
                unit: unit.value,
                status: status.value,
                location: document.getElementById('locationSelect').value,
                supplier_name: checkbox.value,
                purchase_date: new Date().toISOString().split('T')[0],
                material_price: parseFloat(checkbox.getAttribute('data-price')),
                labor_other: parseFloat(labor_other.value || 0),
                unit: checkbox.getAttribute('data-unit')
            };
            
            materialsCartArray.push(materialData);
        });
        
        updateCartDisplay();
        
        // Reset form for next material
        materialNameSelect.value = '';
        quantityInput.value = '1';
        supplierComparisonSection.style.display = 'none';
        materialDetailsForm.style.display = 'none';
        selectedSupplier = null;
        addToCartBtn.style.display = 'none';
        
        // Clear validation states
        [category, quantity, status, labor_other].forEach(field => {
            if (field) {
                field.classList.remove('is-valid', 'is-invalid');
            }
        });
        
        // Clear all checkboxes
        document.querySelectorAll('input[name="selectedSuppliers"]').forEach(checkbox => {
            checkbox.checked = false;
        });
        document.getElementById('selectAllSuppliers').checked = false;
        
        showFeedbackModal(true, `${selectedCheckboxes.length} material(s) added to cart!`);
        
        // Automatically move to cart page
        showWizardPage(2);
    }
    
    // Update cart display
    function updateCartDisplay() {
        const cartTableBody = document.getElementById('cartTableBody');
        const cartTotalItems = document.getElementById('cartTotalItems');

        cartTableBody.innerHTML = '';
        cartTotalItems.textContent = materialsCartArray.length;

        if (materialsCartArray.length > 0) {
            materialsCartArray.forEach((item, index) => {
                const total = (item.material_price + item.labor_other) * item.quantity;
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${item.material_name}</td>
                    <td>${item.supplier_name}</td>
                    <td>${item.quantity}</td>
                    <td>${item.unit}</td>
                    <td>₱ ${item.material_price.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td>₱ ${total.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm remove-cart-item" data-index="${index}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                cartTableBody.appendChild(row);
            });
            materialsCart.style.display = 'block'; // Ensure cart container is visible if it has items
        } else {
            materialsCart.style.display = 'none';
        }
        
        // Re-evaluate button visibility based on current page and cart status
        showWizardPage(currentWizardPage);

        document.querySelectorAll('.remove-cart-item').forEach(button => {
            button.addEventListener('click', function() {
                const itemIndex = parseInt(this.getAttribute('data-index'));
                materialsCartArray.splice(itemIndex, 1);
                updateCartDisplay();
            });
        });
    }
    
    // Save all materials button click
    saveAllMaterialsBtn.addEventListener('click', function() {
        if (materialsCartArray.length === 0) {
            showFeedbackModal(false, 'No materials in cart to save.');
            return;
        }
        
        if (confirm(`Are you sure you want to save ${materialsCartArray.length} materials?`)) {
            saveAllMaterials();
        }
    });

    function showConfirmSaveModal(materialCount) {
        // Message sa modal
        document.getElementById('confirmSaveMessage').innerHTML = 
            `Are you sure you want to save <strong>${materialCount}</strong> materials?`;

        const modalEl = document.getElementById('confirmSaveModal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();

        const confirmBtn = document.getElementById('confirmSaveBtn');

        // Reset click handler para walang duplicate events
        confirmBtn.onclick = function() {
            modal.hide();
            saveAllMaterials();
        };
    }
        
    // Save all materials
    function saveAllMaterials() {
        const formData = new FormData();
        formData.append('materials', JSON.stringify(materialsCartArray));
        
        console.log('Sending materials data:', materialsCartArray);
        
        fetch('save_materials_batch.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.text();
        })
        .then(text => {
            console.log('Raw response:', text);
            try {
                const data = JSON.parse(text);
                console.log('Parsed response:', data);
                
                if (data.success) {
                    showFeedbackModal(true, `Success! ${data.saved_count} materials saved successfully.`);
                    // Close modal and refresh page
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addMaterialModal'));
                    modal.hide();
                    location.reload();
                } else {
                    showFeedbackModal(false, 'Error: ' + data.message);
                    if (data.errors && data.errors.length > 0) {
                        console.error('Errors:', data.errors);
                    }
                }
            } catch (e) {
                console.error('Error parsing JSON:', e);
                console.error('Raw response was:', text);
                showFeedbackModal(false, 'Error: Invalid response from server');
            }
        })
        .catch(error => {
            console.error('Error saving materials:', error);
            showFeedbackModal(false, 'Error saving materials. Please try again.');
        });
    }
});

// Backorder Modal Functionality
document.addEventListener('DOMContentLoaded', function() {
    // Handle backorder modal events
    document.querySelectorAll('.backorder-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const materialId = this.getAttribute('data-material-id');
            const materialName = this.getAttribute('data-material-name');
            const currentQuantity = parseInt(this.getAttribute('data-current-quantity'));
            const unit = this.getAttribute('data-unit');
            
            // Set up the modal for this specific material
            setupBackorderModal(materialId, currentQuantity, unit);
        });
    });
    
    // Setup backorder modal functionality
    function setupBackorderModal(materialId, currentQuantity, unit) {
        const deductQuantityInput = document.getElementById(`deductQuantity${materialId}`);
        const reasonSelect = document.getElementById(`backorderReason${materialId}`);
        const otherReasonDiv = document.getElementById(`otherReasonDiv${materialId}`);
        const otherReasonInput = document.getElementById(`otherReason${materialId}`);
        const currentStockSpan = document.getElementById(`currentStock${materialId}`);
        const afterDeductionSpan = document.getElementById(`afterDeduction${materialId}`);
        const backorderSummarySpan = document.getElementById(`backorderSummary${materialId}`);
        
        // Update summary when quantity changes
        function updateSummary() {
            const deductQty = parseInt(deductQuantityInput.value) || 0;
            
            currentStockSpan.textContent = currentQuantity;
            afterDeductionSpan.textContent = currentQuantity - deductQty;
            backorderSummarySpan.textContent = deductQty; // Same as deduct quantity
        }
        
        // Handle quantity changes
        deductQuantityInput.addEventListener('input', function() {
            const value = parseInt(this.value) || 0;
            if (value > currentQuantity) {
                this.value = currentQuantity;
            }
            updateSummary();
        });
        
        // Handle reason selection
        reasonSelect.addEventListener('change', function() {
            if (this.value === 'Other') {
                otherReasonDiv.style.display = 'block';
                otherReasonInput.required = true;
            } else {
                otherReasonDiv.style.display = 'none';
                otherReasonInput.required = false;
                otherReasonInput.value = '';
            }
        });
        
        // Handle form submission
        const form = document.getElementById(`backorderForm${materialId}`);
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            this.classList.remove('was-validated');
            let isValid = true;

            const deductQty = parseInt(deductQuantityInput.value);
            if (!deductQty || deductQty < 1 || deductQty > currentQuantity) {
                deductQuantityInput.classList.add('is-invalid');
                isValid = false;
            } else {
                deductQuantityInput.classList.remove('is-invalid');
                deductQuantityInput.classList.add('is-valid');
            }

            if (!reasonSelect.value) {
                reasonSelect.classList.add('is-invalid');
                isValid = false;
            } else {
                reasonSelect.classList.remove('is-invalid');
                reasonSelect.classList.add('is-valid');
            }

            if (reasonSelect.value === 'Other' && (!otherReasonInput.value || otherReasonInput.value.trim().length < 5)) {
                otherReasonInput.classList.add('is-invalid');
                isValid = false;
            } else if (reasonSelect.value === 'Other') {
                otherReasonInput.classList.remove('is-invalid');
                otherReasonInput.classList.add('is-valid');
            }

            if (!isValid) {
                this.classList.add('was-validated');
                return;
            }

            // Confirm action
            if (confirm(`Are you sure you want to create a backorder?\n\n- Add ${deductQty} ${unit} to backorders\n- Reason: ${reasonSelect.value}${reasonSelect.value === 'Other' ? ' - ' + otherReasonInput.value : ''}`)) {
                // Submit the form
                this.submit();
            }
        });
        
        // Initialize summary
        updateSummary();
    }
});

// Reorder functionality has been removed as per user request
</script>

<script>
// Reorder modal and its functionality have been removed as per user request
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);

    // Show success then reorder modal if backorder was successful
    if (urlParams.get('backorder_success') === '1') {
        var backorderSuccessModal = new bootstrap.Modal(document.getElementById('backorderSuccessModal'));
        document.getElementById('backorderSuccessMessage').innerText =
            `Backorder completed! Quantity updated${urlParams.get('qty') ? ' (-' + urlParams.get('qty') + ')' : ''}.`;
        backorderSuccessModal.show();

        // After 5 seconds, hide success modal 
        setTimeout(function() {
            backorderSuccessModal.hide();
        }, 2000);

        // Clean up URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});
</script>
</body>

</html>