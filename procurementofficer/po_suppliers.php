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
                    <h2 class="fs-2 m-0">Suppliers</h2>
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
                                                <td><?php echo htmlspecialchars($row['firstname'] . ' ' . $row['lastname']); ?></td>
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
                                                            data-firstname="<?php echo htmlspecialchars($row['firstname']); ?>"
                                                            data-lastname="<?php echo htmlspecialchars($row['lastname']); ?>"
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
             
    <!-- /#page-content-wrapper -->
    </div>

    <!-- Include all modals -->
    <?php include 'modals/modal_suppliers.php'; ?>

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

    // Global variables for pagination
    let currentPage = 1;
    const itemsPerPage = 10;
    let allMaterials = [];
    let currentSupplierId = null;

    // Function to display materials for the current page
    function displayMaterials() {
        const tbody = document.getElementById('materialsTableBody');
        tbody.innerHTML = '';
        
        if (allMaterials.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">No materials found for this supplier</td></tr>';
            return;
        }
        
        // Calculate pagination
        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = Math.min(startIndex + itemsPerPage, allMaterials.length);
        const paginatedItems = allMaterials.slice(startIndex, endIndex);
        
        // Populate table
        paginatedItems.forEach(material => {
            const row = `
                <tr>
                    <td>${material.material_name || '-'}</td>
                    <td>${material.brand || '-'}</td>
                    <td>${material.unit || '-'}</td>
                    <td>₱ ${parseFloat(material.material_price || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td>${material.specification || '-'}</td>
                    <td>${material.lead_time || '0'} days</td>
                </tr>
            `;
            tbody.innerHTML += row;
        });
        
        // Update pagination info
        updatePaginationInfo();
    }
    
    // Function to update pagination information
    function updatePaginationInfo() {
        const startIndex = (currentPage - 1) * itemsPerPage + 1;
        const endIndex = Math.min(startIndex + itemsPerPage - 1, allMaterials.length);
        const totalItems = allMaterials.length;
        const totalPages = Math.ceil(totalItems / itemsPerPage);
        
        // Update pagination info text
        document.getElementById('startItem').textContent = startIndex;
        document.getElementById('endItem').textContent = endIndex;
        document.getElementById('totalItems').textContent = totalItems;
        document.getElementById('pageInfo').textContent = `Page ${currentPage} of ${totalPages}`;
        
        // Update pagination buttons
        const prevBtn = document.getElementById('prevPage');
        const nextBtn = document.getElementById('nextPage');
        
        prevBtn.classList.toggle('disabled', currentPage === 1);
        nextBtn.classList.toggle('disabled', currentPage >= totalPages);
    }
    
    // Function to load supplier materials with pagination
    function loadSupplierMaterials(supplierId) {
        currentSupplierId = supplierId;
        currentPage = 1; // Reset to first page when loading new supplier
        
        fetch(`get_supplier_materials.php?supplier_id=${supplierId}`)
            .then(response => response.json())
            .then(data => {
                allMaterials = data;
                displayMaterials();
                
                // Add event listeners for pagination buttons
                document.getElementById('prevPage').addEventListener('click', (e) => {
                    e.preventDefault();
                    if (currentPage > 1) {
                        currentPage--;
                        displayMaterials();
                    }
                });
                
                document.getElementById('nextPage').addEventListener('click', (e) => {
                    e.preventDefault();
                    const totalPages = Math.ceil(allMaterials.length / itemsPerPage);
                    if (currentPage < totalPages) {
                        currentPage++;
                        displayMaterials();
                    }
                });
            })
            .catch(error => {
                console.error('Error loading materials:', error);
                document.getElementById('materialsTableBody').innerHTML = 
                    '<tr><td colspan="6" class="text-center text-danger">Error loading materials</td></tr>';
            });
    }

});

    document.querySelectorAll('.edit-supplier-btn').forEach(function(button) {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const name = this.getAttribute('data-name');
        const firstname = this.getAttribute('data-firstname');
        const lastname = this.getAttribute('data-lastname');
        const number = this.getAttribute('data-number');
        const email = this.getAttribute('data-email');
        const address = this.getAttribute('data-address');
        const status = this.getAttribute('data-status');

        document.getElementById('edit_supplier_id').value = id;
        document.getElementById('edit_supplier_name').value = name;
        document.getElementById('edit_contact_firstname').value = firstname;
        document.getElementById('edit_contact_lastname').value = lastname;
        document.getElementById('edit_contact_number').value = number;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_address').value = address;
        document.getElementById('edit_supplier_status').value = status;

        var modal = new bootstrap.Modal(document.getElementById('editSupplierModal'));
        modal.show();
    });
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