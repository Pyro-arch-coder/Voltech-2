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

// Handle add warehouse+category
$add_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_warehouse'])) {
    $warehouse = trim(mysqli_real_escape_string($con, $_POST['warehouse']));
    $category = trim(mysqli_real_escape_string($con, $_POST['category']));
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    
    // Validation
    if (empty($warehouse) || strlen($warehouse) < 2) {
        $add_error = 'Warehouse name must be at least 2 characters long.';
    } elseif (strlen($warehouse) > 100) {
        $add_error = 'Warehouse name cannot exceed 100 characters.';
    } elseif (!preg_match('/^[A-Za-z0-9\s\-\.&]+$/', $warehouse)) {
        $add_error = 'Warehouse name can only contain letters, numbers, spaces, hyphens, dots, and ampersands.';
    } elseif (empty($category) || strlen($category) < 2) {
        $add_error = 'Category must be at least 2 characters long.';
    } elseif (strlen($category) > 50) {
        $add_error = 'Category cannot exceed 50 characters.';
    } elseif (!preg_match('/^[A-Za-z0-9\s\-\.&]+$/', $category)) {
        $add_error = 'Category can only contain letters, numbers, spaces, hyphens, dots, and ampersands.';
    } else {
        // Check if warehouse already exists
        $check_sql = "SELECT id FROM warehouses WHERE warehouse = ? AND category = ?";
        $check_stmt = $con->prepare($check_sql);
        $check_stmt->bind_param("ss", $warehouse, $category);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $add_error = 'This warehouse and category combination already exists.';
        } else {
            $stmt = $con->prepare("INSERT INTO warehouses (warehouse, category, user_id, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param('ssi', $warehouse, $category, $user_id);
            if ($stmt->execute()) {
                // Insert notification for admin if Procurement Officer
                if (isset($_SESSION['user_level']) && $_SESSION['user_level'] == 4) {
                    $user_name = trim($_SESSION['firstname'] . ' ' . $_SESSION['lastname']);
                    $notif_type = "Add Warehouse";
                    $notif_message = "$user_name added a new warehouse: $warehouse (Pending approval)";
                    $stmtNotif = $con->prepare("INSERT INTO notifications_admin (user_id, notif_type, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
                    $stmtNotif->bind_param("iss", $user_id, $notif_type, $notif_message);
                    $stmtNotif->execute();
                    $stmtNotif->close();
                }
                header('Location: po_warehouse_materials.php?added=1');
                exit();
            } else {
                $add_error = 'Error adding warehouse: ' . $con->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}
// Handle delete
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $con->query("DELETE FROM warehouses WHERE id=$del_id");
    header('Location: po_warehouse_materials.php?deleted=1');
    exit();
}
// 1. PAGINATION LOGIC (after delete logic, before fetching warehouses)
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;
// Get filter values
$search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';
$category_filter = isset($_GET['category']) ? mysqli_real_escape_string($con, $_GET['category']) : '';
$warehouse_filter = isset($_GET['warehouse']) ? mysqli_real_escape_string($con, $_GET['warehouse']) : '';

// Build WHERE clause
$where_conditions = [];
if (!empty($search)) {
    $where_conditions[] = "(warehouse LIKE '%$search%' OR category LIKE '%$search%')";
}
if (!empty($category_filter)) {
    $where_conditions[] = "category = '$category_filter'";
}
if (!empty($warehouse_filter)) {
    $where_conditions[] = "warehouse = '$warehouse_filter'";
}
$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get distinct values for filters
$categories = $con->query("SELECT DISTINCT category FROM warehouses ORDER BY category");
$warehouses_list = $con->query("SELECT DISTINCT warehouse FROM warehouses ORDER BY warehouse");

// Pagination logic remains
$total_query = $con->query("SELECT COUNT(*) as total FROM warehouses $where_clause");
$total_row = $total_query->fetch_assoc();
$total_items = $total_row['total'];
$total_pages = ceil($total_items / $items_per_page);

// Fetch filtered warehouses
$warehouses = $con->query("SELECT * FROM warehouses $where_clause ORDER BY id DESC LIMIT $offset, $items_per_page");
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
    <title>Warehouse & Category Management</title>
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
                <h2 class="fs-2 m-0">Warehouse & Category Management</h2>
            </div>
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <?php include 'po_notification.php'; ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
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
        </nav>
        <div class="container-fluid px-4 py-4">
            <div class="card mb-5 shadow rounded-3">
                <div class="card-body p-4">
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
                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-2">
                        <h4 class="mb-0">Warehouse Management</h4>
                        <button type="button" class="btn btn-success ms-auto" data-bs-toggle="modal" data-bs-target="#addWarehouseModal">
                            <i class="fas fa-plus"></i> Add New
                        </button>
                    </div>
                    <hr>
                    <?php if ($add_error): ?>
                        <div class="alert alert-danger"><?php echo $add_error; ?></div>
                    <?php endif; ?>
                    <!-- Add Warehouse Modal -->
                    <div class="modal fade" id="addWarehouseModal" tabindex="-1" aria-labelledby="addWarehouseModalLabel" aria-hidden="true">
                      <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="addWarehouseModalLabel">Add Warehouse + Category</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <form method="POST" novalidate>
                            <div class="modal-body">
                              <div class="row">
                                <div class="col-md-6">
                                  <div class="form-group mb-3">
                                    <label>Warehouse *</label>
                                    <input type="text" class="form-control" name="warehouse" placeholder="Warehouse" required minlength="2" maxlength="100" pattern="[A-Za-z0-9\s\-\.&]+" title="Warehouse name can only contain letters, numbers, spaces, hyphens, dots, and ampersands">
                                    <div class="invalid-feedback">Please enter a valid warehouse name (2-100 characters).</div>
                                  </div>
                                  <div class="form-group mb-3">
                                    <label>Category *</label>
                                    <input type="text" class="form-control" name="category" placeholder="Category" required minlength="2" maxlength="50" pattern="[A-Za-z0-9\s\-\.&]+" title="Category can only contain letters, numbers, spaces, hyphens, dots, and ampersands">
                                    <div class="invalid-feedback">Please enter a valid category (2-50 characters).</div>
                                  </div>
                                </div>
                                <div class="col-md-6">
                                  
                                </div>
                              </div>
                            </div>
                            <div class="modal-footer justify-content-end">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                              <button type="submit" name="add_warehouse" class="btn btn-success">Add</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                    <!-- Remove <h4 class="mb-3">Warehouses List</h4> -->
                    <!-- Add filter/search form above the table -->
                    <form method="GET" class="d-flex flex-wrap gap-2 mb-3" id="searchForm" style="min-width:260px; max-width:900px;">
                        <div class="input-group" style="min-width:220px; max-width:320px;">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" class="form-control border-start-0" name="search" placeholder="Search warehouse/category" value="<?php echo htmlspecialchars($search); ?>" id="searchInput" autocomplete="off" maxlength="100" pattern="[A-Za-z0-9\s\-\.&]+" title="Search can contain letters, numbers, spaces, hyphens, dots, and ampersands">
                        </div>
                        <select name="category" class="form-control" style="max-width:180px;" id="categoryFilter">
                            <option value="">All Categories</option>
                            <?php if ($categories) { while($cat = $categories->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $category_filter === $cat['category'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['category']); ?></option>
                            <?php endwhile; } ?>
                        </select>
                        <select name="warehouse" class="form-control" style="max-width:180px;" id="warehouseFilter">
                            <option value="">All Warehouses</option>
                            <?php if ($warehouses_list) { while($wh = $warehouses_list->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($wh['warehouse']); ?>" <?php echo $warehouse_filter === $wh['warehouse'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($wh['warehouse']); ?></option>
                            <?php endwhile; } ?>
                        </select>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="thead-dark">
                                <tr>
                                    <th>No</th>
                                    <th>Warehouse</th>
                                    <th>Category</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i=1+$offset; while($row = $warehouses->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><?php echo htmlspecialchars($row['warehouse']); ?></td>
                                    <td><?php echo htmlspecialchars($row['category']); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-warning btn-sm me-1" data-bs-toggle="modal" data-bs-target="#editWarehouseModal<?php echo $row['id']; ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm delete-warehouse-btn" data-id="<?php echo $row['id']; ?>" data-name="<?php echo htmlspecialchars($row['warehouse']); ?>">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                                <!-- Edit Warehouse Modal -->
                                <div class="modal fade" id="editWarehouseModal<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="editWarehouseModalLabel<?php echo $row['id']; ?>" aria-hidden="true">
                                  <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content">
                                      <div class="modal-header">
                                        <h5 class="modal-title" id="editWarehouseModalLabel<?php echo $row['id']; ?>">Edit Warehouse + Category</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                      </div>
                                      <form method="POST" action="update_warehouse_material.php" novalidate>
                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="update" value="1">
                                        <div class="modal-body">
                                          <div class="row">
                                            <div class="col-md-6">
                                              <div class="form-group mb-3">
                                                <label>Warehouse *</label>
                                                <input type="text" class="form-control" name="warehouse" value="<?php echo htmlspecialchars($row['warehouse']); ?>" required minlength="2" maxlength="100" pattern="[A-Za-z0-9\s\-\.&]+" title="Warehouse name can only contain letters, numbers, spaces, hyphens, dots, and ampersands">
                                                <div class="invalid-feedback">Please enter a valid warehouse name (2-100 characters).</div>
                                              </div>
                                              <div class="form-group mb-3">
                                                <label>Category *</label>
                                                <input type="text" class="form-control" name="category" value="<?php echo htmlspecialchars($row['category']); ?>" required minlength="2" maxlength="50" pattern="[A-Za-z0-9\s\-\.&]+" title="Category can only contain letters, numbers, spaces, hyphens, dots, and ampersands">
                                                <div class="invalid-feedback">Please enter a valid category (2-50 characters).</div>
                                              </div>
                                            </div>
                                            <div class="col-md-6">
                                              
                                            </div>
                                          </div>
                                        </div>
                                        <div class="modal-footer justify-content-end">
                                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                          <button type="submit" class="btn btn-primary">Update</button>
                                        </div>
                                      </form>
                                    </div>
                                  </div>
                                </div>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- 4. PAGINATION NAVIGATION (below table, before card-body ends) -->
                    <nav aria-label="Page navigation" class="mt-3 mb-3">
                      <ul class="pagination justify-content-center custom-pagination-green mb-0">
                        <li class="page-item<?php if($page <= 1) echo ' disabled'; ?>">
                          <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&warehouse=<?php echo urlencode($warehouse_filter); ?>">Previous</a>
                        </li>
                        <?php for($j = 1; $j <= $total_pages; $j++): ?>
                          <li class="page-item<?php if($j == $page) echo ' active'; ?>">
                            <a class="page-link" href="?page=<?php echo $j; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&warehouse=<?php echo urlencode($warehouse_filter); ?>"><?php echo $j; ?></a>
                          </li>
                        <?php endfor; ?>
                        <li class="page-item<?php if($page >= $total_pages) echo ' disabled'; ?>">
                          <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&warehouse=<?php echo urlencode($warehouse_filter); ?>">Next</a>
                        </li>
                      </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
<script src="po_warehouse_materials.js"></script>
<script>
// Show feedback modal if needed
(function() {
  var params = new URLSearchParams(window.location.search);
  if (params.get('updated') === '1') {
    showFeedbackModal(true, 'Warehouse updated successfully!', '', 'updated');
  } else if (params.get('added') === '1') {
    showFeedbackModal(true, 'Warehouse added successfully!', '', 'added');
  } else if (params.get('deleted') === '1') {
    showFeedbackModal(true, 'Warehouse deleted successfully!', '', 'deleted');
  } else if (params.get('error') === '1') {
    showFeedbackModal(false, 'Something went wrong. Please try again.', '', 'error');
  }
})();
</script>
<script>
    var el = document.getElementById("wrapper");
    var toggleButton = document.getElementById("menu-toggle");
    toggleButton.onclick = function () {
        el.classList.toggle("toggled");
    };
</script>
<script>
// Change Password AJAX
// (like pm_profile.php)
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

  // Handle Add Warehouse form validation
  const addWarehouseForm = document.querySelector('#addWarehouseModal form');
  if (addWarehouseForm) {
    addWarehouseForm.addEventListener('submit', function(e) {
      if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
      }
      this.classList.add('was-validated');
    });
  }

  // Handle Edit Warehouse form validation
  const editWarehouseForms = document.querySelectorAll('[id^="editWarehouseModal"] form');
  editWarehouseForms.forEach(form => {
    form.addEventListener('submit', function(e) {
      if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
      }
      this.classList.add('was-validated');
    });
  });

  // Real-time validation for warehouse forms
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

  // Setup real-time validation for all forms
  setupRealTimeValidation(addWarehouseForm);
  editWarehouseForms.forEach(form => setupRealTimeValidation(form));

  // Handle search form validation
  const searchForm = document.getElementById('searchForm');
  const searchInput = document.getElementById('searchInput');
  const categoryFilter = document.getElementById('categoryFilter');
  const warehouseFilter = document.getElementById('warehouseFilter');
  
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

    // Auto-submit on filter changes
    if (categoryFilter) {
      categoryFilter.addEventListener('change', function() {
        searchForm.submit();
      });
    }

    if (warehouseFilter) {
      warehouseFilter.addEventListener('change', function() {
        searchForm.submit();
      });
    }
  }

  // Custom validation for warehouse forms
  function validateWarehouseForm(form) {
    const warehouse = form.querySelector('[name="warehouse"]').value.trim();
    const category = form.querySelector('[name="category"]').value.trim();
    const slots = parseInt(form.querySelector('[name="slots"]').value);

    let isValid = true;

    // Validate warehouse name
    if (warehouse.length < 2) {
      form.querySelector('[name="warehouse"]').classList.add('is-invalid');
      isValid = false;
    } else if (warehouse.length > 100) {
      form.querySelector('[name="warehouse"]').classList.add('is-invalid');
      isValid = false;
    } else if (!/^[A-Za-z0-9\s\-\.&]+$/.test(warehouse)) {
      form.querySelector('[name="warehouse"]').classList.add('is-invalid');
      isValid = false;
    } else {
      form.querySelector('[name="warehouse"]').classList.remove('is-invalid');
      form.querySelector('[name="warehouse"]').classList.add('is-valid');
    }

    // Validate category
    if (category.length < 2) {
      form.querySelector('[name="category"]').classList.add('is-invalid');
      isValid = false;
    } else if (category.length > 50) {
      form.querySelector('[name="category"]').classList.add('is-invalid');
      isValid = false;
    } else if (!/^[A-Za-z0-9\s\-\.&]+$/.test(category)) {
      form.querySelector('[name="category"]').classList.add('is-invalid');
      isValid = false;
    } else {
      form.querySelector('[name="category"]').classList.remove('is-invalid');
      form.querySelector('[name="category"]').classList.add('is-valid');
    }

    // Validate slots
    if (slots <= 0 || slots > 999999) {
      form.querySelector('[name="slots"]').classList.add('is-invalid');
      isValid = false;
    } else {
      form.querySelector('[name="slots"]').classList.remove('is-invalid');
      form.querySelector('[name="slots"]').classList.add('is-valid');
    }

    return isValid;
  }

  // Add custom validation to forms
  if (addWarehouseForm) {
    addWarehouseForm.addEventListener('submit', function(e) {
      if (!validateWarehouseForm(this)) {
        e.preventDefault();
        e.stopPropagation();
      }
      this.classList.add('was-validated');
    });
  }

  editWarehouseForms.forEach(form => {
    form.addEventListener('submit', function(e) {
      if (!validateWarehouseForm(this)) {
        e.preventDefault();
        e.stopPropagation();
      }
      this.classList.add('was-validated');
    });
  });

  // Handle delete warehouse buttons
  document.querySelectorAll('.delete-warehouse-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const warehouseId = this.getAttribute('data-id');
      const warehouseName = this.getAttribute('data-name');
      
      // Show confirmation modal
      document.getElementById('warehouseName').textContent = warehouseName;
      document.getElementById('confirmDeleteWarehouse').href = '?delete=' + warehouseId;
      
      const deleteModal = new bootstrap.Modal(document.getElementById('deleteWarehouseModal'));
      deleteModal.show();
    });
  });
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteWarehouseModal" tabindex="-1" aria-labelledby="deleteWarehouseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteWarehouseModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="warehouseName"></strong>?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteWarehouse" class="btn btn-danger">Delete</a>
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

</body>
</html> 