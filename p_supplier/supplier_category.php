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

    // Handle category operations
      // --- Add New Category Handler ---
      if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
        $category_name = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $created_at = date('Y-m-d H:i:s');
        
        if (empty($category_name)) {
            $_SESSION['error'] = 'Category name is required.';
        } else {
            // Check if category already exists
            $check_sql = "SELECT id FROM supplier_category WHERE category = ?";
            $check_stmt = $con->prepare($check_sql);
            $check_stmt->bind_param("s", $category_name);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $_SESSION['error'] = 'A category with this name already exists';
            } else {
                // Insert new category with email and created_at
                $insert_sql = "INSERT INTO supplier_category (category, description, email, created_at) VALUES (?, ?, ?, ?)";
                $insert_stmt = $con->prepare($insert_sql);
                $insert_stmt->bind_param("ssss", $category_name, $description, $user_email, $created_at);
                
                if ($insert_stmt->execute()) {
                    $_SESSION['success'] = 'Category added successfully';
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $_SESSION['error'] = 'Error adding category: ' . $con->error;
                }
                $insert_stmt->close();
            }
            $check_stmt->close();
        }
    }
    // --- Update Category Handler ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
        $category_id = (int)($_POST['category_id'] ?? 0);
        $category_name = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($category_name)) {
            $_SESSION['error'] = 'Category name is required.';
        } else if ($category_id <= 0) {
            $_SESSION['error'] = 'Invalid category ID';
        } else {
            // Check if category with this name already exists (excluding current category)
            $check_sql = "SELECT id FROM supplier_category WHERE category = ? AND id != ?";
            $check_stmt = $con->prepare($check_sql);
            $check_stmt->bind_param("si", $category_name, $category_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $_SESSION['error'] = 'A category with this name already exists';
            } else {
                // Update category
                $update_sql = "UPDATE supplier_category SET category = ?, description = ? WHERE id = ?";
                $update_stmt = $con->prepare($update_sql);
                $update_stmt->bind_param("ssi", $category_name, $description, $category_id);
                
                if ($update_stmt->execute()) {
                    $_SESSION['success'] = 'Category updated successfully';
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $_SESSION['error'] = 'Error updating category: ' . $con->error;
                }
                $update_stmt->close();
            }
            $check_stmt->close();
        }
    }

    // --- Delete Category Handler ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
        $category_id = (int)($_POST['category_id'] ?? 0);
        
        if ($category_id <= 0) {
            $_SESSION['error'] = 'Invalid category ID';
        } else {
            // Check if category is in use before deleting
            $check_usage = "SELECT COUNT(*) as count FROM supplier_materials WHERE category_id = ?";
            $check_stmt = $con->prepare($check_usage);
            $check_stmt->bind_param("i", $category_id);
            $check_stmt->execute();
            $usage_result = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();
            
            if ($usage_result['count'] > 0) {
                $_SESSION['error'] = 'Cannot delete category: It is being used by one or more materials';
            } else {
                // Delete category
                $delete_sql = "DELETE FROM supplier_category WHERE id = ?";
                $delete_stmt = $con->prepare($delete_sql);
                $delete_stmt->bind_param("i", $category_id);
                
                if ($delete_stmt->execute()) {
                    $_SESSION['success'] = 'Category deleted successfully';
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $_SESSION['error'] = 'Error deleting category: ' . $con->error;
                }
                $delete_stmt->close();
            }
        }
    }

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
    <title>Supplier Category Management</title>
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
                    <h2 class="fs-2 m-0">Category Management</h2>
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
                <div class="card mb-5 shadow rounded-3">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-2">
                            <h4 class="mb-0">Categories</h4>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-success ms-auto" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                    <i class="fas fa-plus"></i> Add New Category
                                </button>
                            </div>
                        </div>
                        <hr>
                        <form class="mb-3" method="get" action="" id="searchForm" style="max-width:400px;">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0" name="search" placeholder="Search categories..." value="<?php echo htmlspecialchars(isset($_GET['search']) ? $_GET['search'] : ''); ?>" id="searchInput" autocomplete="off">
                            </div>
                        </form>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped text-center">
                                <thead class="bg-success text-white">
                                    <tr>
                                        <th class="text-center">No.</th>
                                        <th class="text-center">Category Name</th>
                                        <th class="text-center">Description</th>
                                        <th class="text-center">Date Created</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($categories_result && $categories_result->num_rows > 0): ?>
                                        <?php $counter = ($page - 1) * $results_per_page + 1; ?>
                                        <?php while ($category = $categories_result->fetch_assoc()): ?>
                                            <tr>
                                                <td class="align-middle"><?php echo $counter++; ?></td>
                                                <td class="align-middle"><?php echo htmlspecialchars($category['category'] ?? ''); ?></td>
                                                <td class="align-middle"><?php echo htmlspecialchars($category['description'] ?? ''); ?></td>
                                                <td class="align-middle"><?php echo date('M d, Y h:i A', strtotime($category['created_at'])); ?></td>
                                                <td class="align-middle">
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-warning edit-category" 
                                                                data-id="<?php echo $category['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($category['category']); ?>"
                                                                data-description="<?php echo htmlspecialchars($category['description']); ?>"
                                                               
                                                                title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger delete-category" 
                                                                data-id="<?php echo $category['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($category['category']); ?>"
                                                                title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No categories found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center custom-pagination-green">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($page - 1); ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">Previous</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($page + 1); ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Include all modals -->
                <?php include 'modals/supplier_category_modals.php'; ?>

      
                <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>

                <!-- Custom JavaScript -->
        <script>


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
        var el = document.getElementById("wrapper");
        var toggleButton = document.getElementById("menu-toggle");

        toggleButton.onclick = function () {
            el.classList.toggle("toggled");
        };
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

    // Show feedback modal if redirected after success or error
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_GET['success']) && $_GET['success'] === 'add'): ?>
            showFeedbackModal(true, 'Category added successfully!', '', 'success');
        <?php elseif (isset($_GET['success']) && $_GET['success'] === 'edit'): ?>
            showFeedbackModal(true, 'Category updated successfully!', '', 'success');
        <?php elseif (isset($_GET['success']) && $_GET['success'] === 'delete'): ?>
            showFeedbackModal(true, 'Material deleted successfully!', '', 'success');
        <?php elseif (isset($_GET['error'])): ?>
            showFeedbackModal(false, '<?php echo htmlspecialchars($_GET['error']); ?>', '', 'error');
        <?php endif; ?>
    });
    </script>

  </body>
</html>