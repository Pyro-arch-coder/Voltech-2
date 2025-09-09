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

// Fetch warehouses for dropdown
$warehouses_query = $con->query("SELECT DISTINCT warehouse FROM warehouses WHERE warehouse IS NOT NULL AND warehouse != '' ORDER BY warehouse ASC");
$warehouses = [];
while ($w_row = $warehouses_query->fetch_assoc()) {
    $warehouses[] = $w_row['warehouse'];
}


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

$categories = [];
$res = $con->query("SELECT id, category_name FROM electrical_equipment_categories");
while ($row = $res->fetch_assoc()) {
    $categories[] = $row;
}
// Handle delete action
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $delete_sql = "DELETE FROM equipment WHERE id = $id";
    if ($con->query($delete_sql) === TRUE) {
        $_SESSION['message'] = "Equipment deleted successfully";
        $_SESSION['message_type'] = "success";
        header("Location: equipment.php?success=delete");
        exit();
    } else {
        $_SESSION['message'] = "Error deleting record: " . $con->error;
        $_SESSION['message_type'] = "danger";
    }
}

// Get filter values
$search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($con, $_GET['status']) : '';
$delivery_status_filter = isset($_GET['delivery_status']) ? mysqli_real_escape_string($con, $_GET['delivery_status']) : '';

// Get location filter from URL
$location_filter = isset($_GET['location']) ? $_GET['location'] : '';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;

// Build WHERE clause
$where_conditions = [];
if (!empty($search)) {
    $where_conditions[] = "(equipment_name LIKE '%$search%' OR usage_purpose LIKE '%$search%')";
}
if (!empty($status_filter)) {
    $where_conditions[] = "status = '$status_filter'";
}
if (!empty($location_filter)) {
    $where_conditions[] = "location = " . ($location_filter === 'NULL' ? 'NULL' : "'$location_filter'");
}
if (!empty($delivery_status_filter)) {
    $where_conditions[] = "delivery_status = '$delivery_status_filter'";
}
if (!empty($category_filter)) {
    $where_conditions[] = "equipment_categories = $category_filter";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get distinct locations for dropdown
$locations_query = $con->query("SELECT DISTINCT location FROM equipment WHERE location IS NOT NULL AND location != '' ORDER BY location ASC");

// Get equipment categories for filter
$categories_query = $con->query("SELECT DISTINCT e.equipment_categories, c.category_name 
    FROM equipment e 
    LEFT JOIN electrical_equipment_categories c ON e.equipment_categories = c.id 
    WHERE e.equipment_categories IS NOT NULL 
    ORDER BY c.category_name ASC");
$categories = [];
while ($cat = $categories_query->fetch_assoc()) {
    $categories[] = $cat;
}

// Pagination settings
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Get total number of records with filters
$total_query = "SELECT COUNT(*) as total FROM equipment $where_clause";
$total_result = $con->query($total_query);
$total_row = $total_result->fetch_assoc();
$total_items = $total_row['total'];
$total_pages = ceil($total_items / $items_per_page);

// Add summary card queries
$total_equipment_query = $con->query("SELECT COUNT(*) as total FROM equipment");
$total_equipment = $total_equipment_query->fetch_assoc();

$available_query = $con->query("SELECT COUNT(*) as total FROM equipment WHERE status = 'Available'");
$available = $available_query->fetch_assoc();

$inuse_query = $con->query("SELECT COUNT(*) as total FROM equipment WHERE status = 'In Use'");
$inuse = $inuse_query->fetch_assoc();

$maintenance_query = $con->query("SELECT COUNT(*) as total FROM equipment WHERE status = 'Maintenance'");
$maintenance = $maintenance_query->fetch_assoc();

// Get distinct status values for filter
$statuses_query = "SELECT DISTINCT status FROM equipment ORDER BY status";
$statuses = $con->query($statuses_query);

// Fetch quipment with pagination and filters
$sql = "SELECT * FROM equipment $where_clause ORDER BY id DESC LIMIT $offset, $items_per_page";
if (!empty($where_clause)) {
    $sql = "SELECT * FROM equipment $where_clause ORDER BY id DESC LIMIT $offset, $items_per_page";
} else {
    $sql = "SELECT * FROM equipment ORDER BY id DESC LIMIT $offset, $items_per_page";
}
$result = $con->query($sql);

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
    <title>Procurement Officer Equipments</title>
    <style>
    .nav-tabs .nav-link.active {
        background-color: #28a745 !important;
        color: #fff !important;
        border-color: #28a745 #28a745 #fff !important;
    }
    .nav-tabs .nav-link {
        color: #28a745;
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
                    <h2 class="fs-2 m-0">Equipment</h2>
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
                <!-- Equipment Tabs -->
                <div class="card mb-5 shadow rounded-3">
                    <div class="card-body">
                        <div class="mb-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
                            <h4 class="mb-0">Equipment List</h4>
                            <button type="button" class="btn btn-success ms-auto" data-bs-toggle="modal" data-bs-target="#addEquipmentModal">
                                <i class="fas fa-plus"></i> Add New Equipment
                            </button>
                            <a href="#" class="btn btn-danger ms-2 exportPdfBtn">
                                <i class="fas fa-file-pdf"></i> Export as PDF
                            </a>
                        </div>
                        <hr>
                        <form class="d-flex flex-wrap gap-2 mb-3" method="get" action="" id="searchForm">
                            <div class="input-group" style="width: 250px;">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0" name="search" placeholder="Search equipment..." value="<?php echo htmlspecialchars($search); ?>" id="searchInput" autocomplete="off">
                            </div>
                            <select name="status" class="form-select" style="width: 180px;" id="statusFilter">
                                <option value="">All Status</option>
                                <?php 
                                $statuses->data_seek(0); // Reset the pointer
                                while ($status = $statuses->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($status['status']); ?>" <?php echo ($status_filter == $status['status']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($status['status']); ?></option>
                                <?php endwhile; ?>
                            </select>
                            <select name="location" class="form-select" style="width: 180px;" id="locationFilter">
                                <option value="">All Locations</option>
                                <option value="NULL" <?php echo ($location_filter === 'NULL') ? 'selected' : ''; ?>>No Location</option>
                                <?php 
                                if ($locations_query) {
                                    $locations_query->data_seek(0);
                                    while ($loc = $locations_query->fetch_assoc()): 
                                        if (!empty($loc['location'])):
                                ?>
                                    <option value="<?php echo htmlspecialchars($loc['location']); ?>" <?php echo ($location_filter === $loc['location']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($loc['location']); ?>
                                    </option>
                                <?php 
                                        endif;
                                    endwhile; 
                                }
                                ?>
                            </select>
                            <select name="category" class="form-select" style="width: 200px;" id="categoryFilter">
                                <option value="">All Categories</option>
                                <?php 
                                if (!empty($categories)) {
                                    foreach ($categories as $cat): 
                                        if (!empty($cat['equipment_categories']) && !empty($cat['category_name'])):
                                ?>
                                    <option value="<?php echo $cat['equipment_categories']; ?>" <?php echo ($category_filter == $cat['equipment_categories']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </option>
                                <?php 
                                        endif;
                                    endforeach; 
                                }
                                ?>
                            </select>
                        </form>
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var searchInput = document.getElementById('searchInput');
                            var statusFilter = document.getElementById('statusFilter');
                            var locationFilter = document.getElementById('locationFilter');
                            var deliveryStatusFilter = document.getElementById('deliveryStatusFilter');
                            var searchForm = document.getElementById('searchForm');
                            if (searchInput && searchForm) {
                                var searchTimeout;
                                searchInput.addEventListener('input', function() {
                                    clearTimeout(searchTimeout);
                                    searchTimeout = setTimeout(function() {
                                        searchForm.submit();
                                    }, 400);
                                });
                            }
                            if (statusFilter && searchForm) {
                                statusFilter.addEventListener('change', function() {
                                    searchForm.submit();
                                });
                            }
                            
                            var locationFilter = document.getElementById('locationFilter');
                            if (locationFilter && searchForm) {
                                locationFilter.addEventListener('change', function() {
                                    searchForm.submit();
                                });
                            }
                            var categoryFilter = document.getElementById('categoryFilter');
                            if (categoryFilter && searchForm) {
                                categoryFilter.addEventListener('change', function() {
                                    searchForm.submit();
                                });
                            }
                            
                            [searchInput, statusFilter, locationFilter, categoryFilter, deliveryStatusFilter].forEach(function(element) {
                                if (element) {
                                    element.addEventListener('change', function() {
                                        searchForm.submit();
                                    });
                                }
                            });
                        });
                        </script>
                        <div class="table-responsive mb-0">
                            <table class="table table-bordered table-striped mb-0">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>No.</th>
                                        <th>Equipment Name</th>
                                        <th>Location</th>
                                        <th>Equipment Price</th>
                                        <th>Depreciation</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = $offset + 1;
                                    if ($result->num_rows > 0): 
                                        while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                                <td><?php echo htmlspecialchars($row['equipment_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['location'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php
                                                    if (isset($row['equipment_price']) && $row['equipment_price'] !== '') {
                                                        echo '₱ ' . number_format($row['equipment_price'], 2);
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    if (isset($row['depreciation']) && $row['depreciation'] !== '') {
                                                        $depr = $row['depreciation'];
                                                        echo (intval($depr) == floatval($depr)) ? intval($depr) . ' years' : number_format($depr, 2) . ' years';
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo ($row['status'] == 'Available') ? 'success' : (($row['status'] == 'In Use') ? 'warning' : (($row['status'] == 'Maintenance') ? 'info' : 'secondary')); ?>">
                                                        <?php echo htmlspecialchars($row['status']); ?>
                                                    </span>
                                                </td>
                                        <td class="text-center">
                                            <div class="action-buttons">
                                                <a href="#" class="btn btn-sm btn-primary text-white font-weight-bold" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $row['id']; ?>">
                                                    <i class="fas fa-eye"></i> View More
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No equipment found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <nav aria-label="Page navigation" class="mt-3 mb-3">
                            <ul class="pagination justify-content-center custom-pagination-green mb-0">
                                <li class="page-item<?php if($page <= 1) echo ' disabled'; ?>">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($location_filter) ? '&location=' . urlencode($location_filter) : ''; ?><?php echo !empty($delivery_status_filter) ? '&delivery_status=' . urlencode($delivery_status_filter) : ''; ?>">Previous</a>
                                                </li>
                                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item<?php if($i == $page) echo ' active'; ?>">
                                        <a class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($location_filter) ? '&location=' . urlencode($location_filter) : ''; ?><?php echo !empty($delivery_status_filter) ? '&delivery_status=' . urlencode($delivery_status_filter) : ''; ?>"><?php echo $i; ?></a>
                                            </li>
                                            <?php endfor; ?>
                                <li class="page-item<?php if($page >= $total_pages) echo ' disabled'; ?>">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($location_filter) ? '&location=' . urlencode($location_filter) : ''; ?><?php echo !empty($delivery_status_filter) ? '&delivery_status=' . urlencode($delivery_status_filter) : ''; ?>">Next</a>
                                                </li>
                                        </ul>
                                    </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Equipment Modal -->
    <div class="modal fade" id="addEquipmentModal" tabindex="-1" aria-labelledby="addEquipmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addEquipmentModalLabel">Add New Equipment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="container mt-4">
                <form action="add_equipment.php" method="POST">
                    <!-- Hidden input for company/category -->
                    <input type="hidden" name="category" value="Company">
                    
                   
                    <div class="form-group mb-3">
                        <label for="equipmentNameInput">Equipment Name *</label>
                        <input type="text" class="form-control" id="equipmentNameInput" name="equipment_name" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="addLocationSelect">Location (Warehouse)</label>
                        <select class="form-control" name="location" id="addLocationSelect">
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?php echo htmlspecialchars($wh); ?>"><?php echo htmlspecialchars($wh); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label for="equipmentCategorySelect">Equipment Category *</label>
                        <select class="form-control" name="equipment_category" id="equipmentCategorySelect" required>
                            <option value="" disabled selected>-- Select Category --</option>
                            <option value="Earthmoving Equipment">Earthmoving Equipment</option>
                            <option value="Material Handling Equipment">Material Handling Equipment</option>
                            <option value="Concrete Equipment">Concrete Equipment</option>
                            <option value="Road Construction Equipment">Road Construction Equipment</option>
                            <option value="Compaction Equipment">Compaction Equipment</option>
                            <option value="Drilling & Piling Equipment">Drilling & Piling Equipment</option>
                            <option value="Tunneling & Mining Equipment">Tunneling & Mining Equipment</option>
                            <option value="Demolition Equipment">Demolition Equipment</option>
                            <option value="Lifting Equipment">Lifting Equipment</option>
                            <option value="Power Generation & Air Equipment">Power Generation & Air Equipment</option>
                            <option value="Transportation Equipment">Transportation Equipment</option>
                            <option value="Finishing Equipment">Finishing Equipment</option>
                            <option value="Safety & Support Equipment">Safety & Support Equipment</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label for="equipmentPriceInput">Equipment Price</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="equipmentPriceInput" name="equipment_price">
                    </div>
                    <div class="form-group mb-3">
                        <label for="depreciationInput">Depreciation (Years)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="depreciationInput" name="depreciation">
                    </div>
                    <div class="form-group mb-3">
                        <label for="brandInput">Brand</label>
                        <input type="text" class="form-control" id="brandInput" name="brand" placeholder="Enter brand name">
                    </div>
                    <div class="form-group mb-3">
                        <label for="specificationInput">Specification</label>
                        <textarea class="form-control" id="specificationInput" name="specification" rows="2" placeholder="Enter specifications"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="window.history.back();">Close</button>
                        <button type="submit" class="btn btn-primary">Save Equipment</button>
                    </div>
                </form>
                            </div>
            </div>
        </div>
    </div>

    <!-- View and Edit Modals -->
    <?php 
    // Reset the result pointer
    $result->data_seek(0);
    
    while ($row = $result->fetch_assoc()): 
    ?>
    <!-- View Equipment Modal -->
    <div class="modal fade" id="viewModal<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="viewModalLabel<?php echo $row['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewModalLabel<?php echo $row['id']; ?>">Equipment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="container-fluid">
                                <div class="row mb-3">
                                    <div class="col-md-8 mb-2">
                                        <h4 class="fw-bold mb-0 text-primary"><i class="fas fa-wrench me-2"></i><?php echo htmlspecialchars($row['equipment_name']); ?></h4>
                                        <div class="text-muted small"><i class="fas fa-tag me-1"></i>Brand: <?php echo htmlspecialchars($row['brand'] ?? 'N/A'); ?></div>
                                        <div class="text-muted small"><i class="fas fa-map-marker-alt me-1"></i>Location: <?php echo htmlspecialchars($row['location'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="col-md-4 mb-2 text-md-end">
                                        <span class="fw-bold text-secondary d-block mb-1"><i class="fas fa-info-circle me-1"></i>Status:</span>
                                        <span class="badge bg-<?php echo ($row['status'] == 'Available') ? 'success' : (($row['status'] == 'In Use') ? 'warning' : (($row['status'] == 'Maintenance') ? 'info' : 'secondary')); ?>">
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
                                                <h6 class="card-subtitle mb-2 text-muted"><i class="fas fa-hourglass-half me-1"></i>Depreciation</h6>
                                                <p class="card-text">
                                                    <?php 
                                                    if (isset($row['depreciation']) && $row['depreciation'] !== '') { 
                                                        $depr = $row['depreciation']; 
                                                        echo (intval($depr) == floatval($depr)) ? intval($depr) . ' years' : number_format($depr, 2) . ' years'; 
                                                    } else { 
                                                        echo 'N/A'; 
                                                    } 
                                                    ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-8 mb-2">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h6 class="card-subtitle mb-2 text-muted"><i class="fas fa-coins me-1"></i>Equipment Price</h6>
                                                <p class="card-text"><?php echo isset($row['equipment_price']) && $row['equipment_price'] !== '' ? '₱ ' . number_format($row['equipment_price'], 2) : 'N/A'; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['id']; ?>" data-bs-dismiss="modal">Edit</button>
                    <a href="#" class="btn btn-danger btn-sm text-white delete-equipment-btn" data-id="<?php echo $row['id']; ?>" data-name="<?php echo htmlspecialchars($row['equipment_name']); ?>">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Equipment Modal -->
    <div class="modal fade" id="editModal<?php echo $row['id']; ?>" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Equipment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="update_equipment.php" method="POST">
                    <input type="hidden" name="equipment_id" value="<?php echo $row['id']; ?>">
                    <input type="hidden" name="category" value="Company">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>Equipment Name *</label>
                                    <input type="text" class="form-control" name="equipment_name" value="<?php echo htmlspecialchars($row['equipment_name']); ?>" required>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Location (Warehouse)</label>
                                    <select class="form-control" name="location">
                                        <option value="">None</option>
                                        <?php foreach ($warehouses as $wh): ?>
                                            <option value="<?php echo htmlspecialchars($wh); ?>" <?php if (isset($row['location']) && $row['location'] == $wh) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($wh); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group mb-3">
                                    <label>Equipment Price</label>
                                    <input type="number" step="0.01" min="0" class="form-control" name="equipment_price" value="<?php echo isset($row['equipment_price']) ? htmlspecialchars($row['equipment_price']) : ''; ?>">
                                </div>
                                <div class="form-group mb-3">
                                    <label>Depreciation (Years)</label>
                                    <input type="number" step="0.01" min="0" class="form-control" name="depreciation" value="<?php echo isset($row['depreciation']) ? htmlspecialchars($row['depreciation']) : ''; ?>">
                                </div>
                                <div class="form-group mb-3">
                                    <label>Status</label>
                                    <select class="form-control" name="status" required>
                                        <option value="Available" <?php echo ($row['status'] == 'Available') ? 'selected' : ''; ?>>Available</option>
                                        <option value="In Use" <?php echo ($row['status'] == 'In Use') ? 'selected' : ''; ?>>In Use</option>
                                        <option value="Maintenance" <?php echo ($row['status'] == 'Maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                                        <option value="Out of Service" <?php echo ($row['status'] == 'Out of Service') ? 'selected' : ''; ?>>Out of Service</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>Brand</label>
                                    <input type="text" class="form-control" name="brand" value="<?php echo isset($row['brand']) ? htmlspecialchars($row['brand']) : ''; ?>" placeholder="Enter brand name">
                                </div>
                                <div class="form-group mb-3">
                                    <label>Specification</label>
                                    <textarea class="form-control" name="specification" rows="10" placeholder="Enter specifications"><?php echo isset($row['specification']) ? htmlspecialchars($row['specification']) : ''; ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Equipment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endwhile; ?>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteEquipmentModal" tabindex="-1" aria-labelledby="deleteEquipmentModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="deleteEquipmentModalLabel">Confirm Delete</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to delete <strong id="equipmentName"></strong>?</p>
            <p class="text-danger">This action cannot be undone.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <a href="#" id="confirmDeleteEquipment" class="btn btn-danger">Delete</a>
                </div>
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

    <div class="modal fade" id="exportPdfModal" tabindex="-1" aria-labelledby="exportPdfModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exportPdfModalLabel">Export as PDF</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to export the equipment list as PDF?</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <a href="#" id="confirmExportPdf" class="btn btn-danger">Export</a>
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
      // Remove the query param after showing the modal
      if (paramToRemove) {
        removeQueryParam(paramToRemove);
      }
    }
    // Show feedback modal if redirected after add, update, delete, or error
    <?php if (isset($_GET['success']) && $_GET['success'] === 'add'): ?>
    document.addEventListener('DOMContentLoaded', function() {
      showFeedbackModal(true, 'Equipment added successfully!', '', 'success');
    });
    <?php elseif (isset($_GET['success']) && $_GET['success'] === 'edit'): ?>
    document.addEventListener('DOMContentLoaded', function() {
      showFeedbackModal(true, 'Equipment updated successfully!', '', 'success');
    });
    <?php elseif (isset($_GET['success']) && $_GET['success'] === 'delete'): ?>
    document.addEventListener('DOMContentLoaded', function() {
      showFeedbackModal(true, 'Equipment deleted successfully!', '', 'success');
    });
    <?php elseif (isset($_GET['error'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
      showFeedbackModal(false, decodeURIComponent('<?php echo $_GET['error']; ?>'), '', 'error');
    });
    <?php endif; ?>
    <?php if (isset($_GET['approved']) && $_GET['approved'] === '1'): ?>
    document.addEventListener('DOMContentLoaded', function() {
      showFeedbackModal(true, 'Equipment request approved successfully!', '', 'approved');
    });
    <?php elseif (isset($_GET['rejected']) && $_GET['rejected'] === '1'): ?>
    document.addEventListener('DOMContentLoaded', function() {
      showFeedbackModal(true, 'Equipment request rejected successfully!', '', 'rejected');
    });
    <?php endif; ?>
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.exportPdfBtn').forEach(function(exportBtn) {
            exportBtn.addEventListener('click', function(e) {
            e.preventDefault();
            var modal = new bootstrap.Modal(document.getElementById('exportPdfModal'));
            modal.show();
            });
        });
        }); 
    </script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var changePasswordForm = document.getElementById('changePasswordForm');
  var feedbackDiv = document.getElementById('changePasswordFeedback');
  if (changePasswordForm) {
    changePasswordForm.addEventListener('submit', function(e) {
      e.preventDefault();
      feedbackDiv.innerHTML = '';
      var formData = new FormData(changePasswordForm);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', '', true); // empty string means post to same file
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
  var exportBtn = document.getElementById('exportPdfBtn');
  if (exportBtn) {
    exportBtn.addEventListener('click', function(e) {
      e.preventDefault();
      var modal = new bootstrap.Modal(document.getElementById('exportPdfModal'));
      modal.show();
    });
  }
  var confirmExportBtn = document.getElementById('confirmExportPdf');
  if (confirmExportBtn) {
    confirmExportBtn.addEventListener('click', function(e) {
      e.preventDefault();
      var modalEl = document.getElementById('exportPdfModal');
      var modalInstance = bootstrap.Modal.getInstance(modalEl);
      if (modalInstance) modalInstance.hide();
      setTimeout(function() {
        window.open('export_equipment_pdf.php', '_blank');
      }, 300);
    });
  }
});
</script>
    <script src="po_equipment.js"></script>
</body>

</html>