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

    // Pagination variables (restored)
    $results_per_page = 10;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $start_from = ($page - 1) * $results_per_page;

    // Get the supplier's materials with search and pagination
    $search_condition = "";
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = mysqli_real_escape_string($con, $_GET['search']);
        $search_condition = " AND (material_name LIKE '%$search%' OR brand LIKE '%$search%' OR category LIKE '%$search%')";
    }

    // Get supplier_id based on user's email
    $supplier_query = "SELECT s.id as supplier_id FROM suppliers s 
                    INNER JOIN users u ON s.email = u.email 
                    WHERE u.id = ?";
    $supplier_stmt = $con->prepare($supplier_query);
    $supplier_stmt->bind_param("i", $_SESSION['user_id']);
    $supplier_stmt->execute();
    $supplier_result = $supplier_stmt->get_result();
    $supplier_data = $supplier_result->fetch_assoc();
    $supplier_id = $supplier_data['supplier_id'];

    // Count total materials for this supplier
    $count_sql = "SELECT COUNT(*) AS total FROM suppliers_materials WHERE supplier_id = ?$search_condition";
    $count_stmt = $con->prepare($count_sql);
    $count_stmt->bind_param("i", $supplier_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_records = $count_result->fetch_assoc()['total'];
    $total_pages = ceil($total_records / $results_per_page);

    // Fetch supplier's materials with pagination
    $materials_sql = "SELECT * FROM suppliers_materials 
                    WHERE supplier_id = ?$search_condition 
                    ORDER BY id DESC LIMIT ?, ?";
    $materials_stmt = $con->prepare($materials_sql);
    $materials_stmt->bind_param("iii", $supplier_id, $start_from, $results_per_page);
    $materials_stmt->execute();
    $materials_result = $materials_stmt->get_result();

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
    <link rel="stylesheet" href="supplier_style.css" />
    <title>Supplier Materials Management</title>
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
                    <h2 class="fs-2 m-0">Materials Management</h2>
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
                        <a class="nav-link position-relative" href="supplier_messenger.php" title="Messages">
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
                <div class="card mb-5 shadow rounded-3">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-2">
                            <h4 class="mb-0">Materials Management</h4>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-success ms-auto" data-bs-toggle="modal" data-bs-target="#addMaterialModal">
                                    <i class="fas fa-plus"></i> Add New Material
                                </button>
                                <button type="button" class="btn btn-danger exportMaterialsPdfBtn">
                                    <i class="fas fa-file-pdf"></i> Export as PDF
                                </button>
                            </div>
                        </div>
                        <hr>
                        <form class="mb-3" method="get" action="" id="searchForm" style="max-width:400px;">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0" name="search" placeholder="Search materials by name, brand, or category" value="<?php echo htmlspecialchars(isset($_GET['search']) ? $_GET['search'] : ''); ?>" id="searchInput" autocomplete="off" maxlength="100" pattern="[A-Za-z0-9\s\-\.@]+" title="Search can contain letters, numbers, spaces, hyphens, dots, and @ symbol">
                            </div>
                        </form>
                       
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead class="bg-success text-white">
                                    <tr>
                                        <th>No.</th>
                                        <th>Material Name</th>
                                        <th>Brand</th>
                                        <th>Category</th>
                                        <th>Unit</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($materials_result->num_rows > 0): ?>
                                        <?php $counter = ($page - 1) * $results_per_page + 1; ?>
                                        <?php while ($material = $materials_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $counter++; ?></td>
                                                <td><?php echo htmlspecialchars($material['material_name']); ?></td>
                                                <td><?php echo htmlspecialchars($material['brand']); ?></td>
                                                <td><?php echo htmlspecialchars($material['category']); ?></td>
                                                <td><?php echo htmlspecialchars($material['unit']); ?></td>
                                                <td>₱<?php echo number_format($material['material_price'], 2); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $material['status'] == 'Available' ? 'bg-success' : 'bg-danger'; ?>">
                                                        <?php echo htmlspecialchars($material['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-info view-material" 
                                                                data-id="<?php echo $material['id']; ?>"
                                                                data-material-name="<?php echo htmlspecialchars($material['material_name']); ?>"
                                                                data-brand="<?php echo htmlspecialchars($material['brand']); ?>"
                                                                data-specification="<?php echo htmlspecialchars($material['specification']); ?>"
                                                                data-category="<?php echo htmlspecialchars($material['category']); ?>"
                                                                data-quantity="<?php echo htmlspecialchars($material['quantity']); ?>"
                                                                data-unit="<?php echo htmlspecialchars($material['unit']); ?>"
                                                                data-price="<?php echo htmlspecialchars($material['material_price']); ?>"
                                                                data-low-stock="<?php echo htmlspecialchars($material['low_stock_threshold']); ?>"
                                                                data-lead-time="<?php echo htmlspecialchars($material['lead_time']); ?>"
                                                                data-labor-other="<?php echo htmlspecialchars($material['labor_other']); ?>"
                                                                data-status="<?php echo htmlspecialchars($material['status']); ?>"
                                                                title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-warning edit-material" 
                                                                data-id="<?php echo $material['id']; ?>"
                                                                data-material-name="<?php echo htmlspecialchars($material['material_name']); ?>"
                                                                data-brand="<?php echo htmlspecialchars($material['brand']); ?>"
                                                                data-specification="<?php echo htmlspecialchars($material['specification']); ?>"
                                                                data-category="<?php echo htmlspecialchars($material['category']); ?>"
                                                                data-quantity="<?php echo htmlspecialchars($material['quantity']); ?>"
                                                                data-unit="<?php echo htmlspecialchars($material['unit']); ?>"
                                                                data-price="<?php echo htmlspecialchars($material['material_price']); ?>"
                                                                data-low-stock="<?php echo htmlspecialchars($material['low_stock_threshold']); ?>"
                                                                data-lead-time="<?php echo htmlspecialchars($material['lead_time']); ?>"
                                                                data-labor-other="<?php echo htmlspecialchars($material['labor_other']); ?>"
                                                                data-status="<?php echo htmlspecialchars($material['status']); ?>"
                                                                title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger delete-material" 
                                                                data-id="<?php echo $material['id']; ?>"
                                                                data-material-name="<?php echo htmlspecialchars($material['material_name']); ?>"
                                                                title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No materials found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center custom-pagination-green">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Include all modals -->
                <?php include 'modals/supplier_materials_modals.php'; ?>

      
                <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>

                <!-- Custom JavaScript -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
        
        // Initialize all modals
        // Initialize modals only if they exist
        const addMaterialModalEl = document.getElementById('addMaterialModal');
        const editMaterialModalEl = document.getElementById('editMaterialModal');
        const viewMaterialModalEl = document.getElementById('viewMaterialModal');
        const deleteMaterialModalEl = document.getElementById('deleteMaterialModal');
        const feedbackModalEl = document.getElementById('feedbackModal');
        const logoutModalEl = document.getElementById('logoutModal');
        const changePasswordModalEl = document.getElementById('changePasswordModal');
        
        // Initialize modals only if elements exist
        const addMaterialModal = addMaterialModalEl ? new bootstrap.Modal(addMaterialModalEl) : null;
        const editMaterialModal = editMaterialModalEl ? new bootstrap.Modal(editMaterialModalEl) : null;
        const viewModal = viewMaterialModalEl ? new bootstrap.Modal(viewMaterialModalEl) : null;
        const deleteModal = deleteMaterialModalEl ? new bootstrap.Modal(deleteMaterialModalEl) : null;
        const feedbackModal = feedbackModalEl ? new bootstrap.Modal(feedbackModalEl) : null;
        const logoutModal = logoutModalEl ? new bootstrap.Modal(logoutModalEl) : null;
        const changePasswordModal = changePasswordModalEl ? new bootstrap.Modal(changePasswordModalEl) : null;

        
        // Function to populate form fields from data attributes
        function populateForm(form, data) {
            for (const [key, value] of Object.entries(data)) {
                const input = form.querySelector(`[name="${key}"]`);
                if (input) {
                    if (input.type === 'checkbox') {
                        input.checked = value === 'true' || value === true;
                    } else if (input.type === 'radio') {
                        const radio = form.querySelector(`input[name="${key}"][value="${value}"]`);
                        if (radio) radio.checked = true;
                    } else if (input.tagName === 'SELECT') {
                        const option = input.querySelector(`option[value="${value}"]`);
                        if (option) option.selected = true;
                    } else {
                        input.value = value;
                    }
                }
            }
        }

        // Handle view material functionality
        document.querySelectorAll('.view-material').forEach(function(button) {
            button.addEventListener('click', function() {
                var data = this.dataset;
                
                // Populate modal with material data
                document.getElementById('view_material_name').textContent = data.materialName || '';
                document.getElementById('view_brand').textContent = data.brand || '';
                document.getElementById('view_category').textContent = data.category || '';
                document.getElementById('view_status').textContent = data.status || '';
                document.getElementById('view_quantity').textContent = data.quantity || '';
                document.getElementById('view_unit').textContent = data.unit || '';
                document.getElementById('view_material_price').textContent = data.price ? '₱' + parseFloat(data.price).toFixed(2) : '';
                document.getElementById('view_labor_other').textContent = data.laborOther ? '₱' + parseFloat(data.laborOther).toFixed(2) : '';
                document.getElementById('view_low_stock_threshold').textContent = data.lowStock || '';
                document.getElementById('view_lead_time').textContent = data.leadTime || '';
                document.getElementById('view_specification').textContent = data.specification || '';
                
                viewModal.show();
            });
        });

        // Handle add material form submission
        const addMaterialForm = document.querySelector('#addMaterialModal form');
        if (addMaterialForm) {
            addMaterialForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch('process_add_material.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message before reloading
                        const feedbackMessage = document.getElementById('feedbackMessage');
                        const feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
                        
                        feedbackMessage.textContent = data.message || 'Material added successfully!';
                        feedbackMessage.className = 'text-success';
                        
                        // Show the feedback modal
                        feedbackModal.show();
                        
                        // Reload the page after the modal is shown
                        feedbackModal._element.addEventListener('hidden.bs.modal', function () {
                            window.location.reload();
                        });
                        
                        // Close the add material modal
                        const addMaterialModal = bootstrap.Modal.getInstance(document.getElementById('addMaterialModal'));
                        if (addMaterialModal) {
                            addMaterialModal.hide();
                        }
                    } else {
                        // Show error message in feedback modal
                        const feedbackMessage = document.getElementById('feedbackMessage');
                        const feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
                        
                        if (data.message) {
                            feedbackMessage.textContent = data.message;
                            feedbackMessage.className = 'text-danger';
                        } else {
                            feedbackMessage.textContent = 'An error occurred while adding the material.';
                            feedbackMessage.className = 'text-danger';
                        }
                        
                        feedbackModal.show();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    const feedbackMessage = document.getElementById('feedbackMessage');
                    const feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
                    feedbackMessage.textContent = 'An error occurred while processing your request.';
                    feedbackMessage.className = 'text-danger';
                    feedbackModal.show();
                });
            });
        }

        // Handle edit material functionality
        document.querySelectorAll('.edit-material').forEach(function(button) {
            button.addEventListener('click', function() {
                var data = this.dataset;
                
                // Set the material ID in the form
                document.getElementById('edit_material_id').value = data.id;
                
                // Populate the form with the material data
                const form = document.querySelector('#editMaterialModal form');
                if (form) {
                    // Convert data attributes to form field names
                const formData = {
                    'material_name': data.materialName,
                        'brand': data.brand,
                        'specification': data.specification,
                        'category': data.category,
                        'quantity': data.quantity,
                        'unit': data.unit,
                        'material_price': data.price,
                        'low_stock_threshold': data.lowStock,
                        'lead_time': data.leadTime,
                        'labor_other': data.laborOther,
                        'status': data.status
                };
                
                populateForm(form, formData);
                }
                
                // Show the edit modal
                if (editMaterialModal) {
                    editMaterialModal.show();
                }
            });
        });

        // Handle delete material functionality
        document.querySelectorAll('.delete-material').forEach(function(button) {
            button.addEventListener('click', function() {
                var data = this.dataset;
                
                // Set the material ID and name in the delete confirmation modal
                document.getElementById('delete_material_id').value = data.id;
                document.getElementById('delete_material_name').textContent = data.materialName;
                
                // Show the delete confirmation modal
                if (deleteModal) {
                    deleteModal.show();
                }
            });
        });

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
                    }, 5000);
                }
            });
        }
    });

    </script>

    <script>
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
        

    <script>
            // Export PDF functionality
            document.querySelectorAll('.exportMaterialsPdfBtn').forEach(function(exportBtn) {
                exportBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.open('export_supplier_materials_pdf.php', '_blank');
                });
            });
    </script>

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
            icon.innerHTML = '<i class="fas fa-check-circle" style="color:#28a745; font-size: 3em;"></i>';
            title.textContent = 'Success!';
            msg.textContent = message;
            
            // For success messages, set a timeout to refresh the page
            setTimeout(function() {
                window.location.reload();
            }, 1500); // Refresh after 1.5 seconds
        } else {
            icon.innerHTML = '<i class="fas fa-times-circle" style="color:#dc3545; font-size: 3em;"></i>';
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

    // Reset form when modal is hidden
    document.querySelectorAll('#addMaterialModal, #editMaterialModal').forEach(function(modal) {
        modal.addEventListener('hidden.bs.modal', function() {
            const form = this.querySelector('form');
            if (form) {
                form.reset();
            }
        });
    });

    // Show feedback modal if redirected after add, update, delete, or error
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_GET['success']) && $_GET['success'] === 'add'): ?>
            showFeedbackModal(true, 'Material added successfully!', '', 'success');
        <?php elseif (isset($_GET['success']) && $_GET['success'] === 'edit'): ?>
            showFeedbackModal(true, 'Material updated successfully!', '', 'success');
        <?php elseif (isset($_GET['success']) && $_GET['success'] === 'delete'): ?>
            showFeedbackModal(true, 'Material deleted successfully!', '', 'success');
        <?php elseif (isset($_GET['error'])): ?>
            showFeedbackModal(false, '<?php echo htmlspecialchars($_GET['error']); ?>', '', 'error');
        <?php endif; ?>
    });
    </script>

<script>
        var el = document.getElementById("wrapper");
        var toggleButton = document.getElementById("menu-toggle");

        toggleButton.onclick = function () {
            el.classList.toggle("toggled");
        };
    </script>

  </body>
</html>