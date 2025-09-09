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

    // Get supplier name from email
    $supplier_check_query = "SELECT * FROM suppliers WHERE email = ?";
    $supplier_check = $con->prepare($supplier_check_query);
    $supplier_check->bind_param('s', $user_email);
    $supplier_check->execute();
    $supplier = $supplier_check->get_result()->fetch_assoc();
    $supplier_check->close();
    
    $supplier_name = $supplier ? $supplier['supplier_name'] : '';
    
    // Pagination settings
    $results_per_page = 10;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $page = $page < 1 ? 1 : $page;
    $offset = ($page - 1) * $results_per_page;
    
    // Fetch order history for the logged-in supplier from suppliers_orders_approved
    $order_history_query = "SELECT 
        soa.id, 
        soa.material_name,
        soa.quantity,
        soa.approve_date as created_at,
        soa.type,
        u.firstname,
        u.lastname
    FROM suppliers_orders_approved soa
    JOIN users u ON soa.user_id = u.id
    WHERE soa.user_id = ?
    ORDER BY soa.approve_date DESC
    LIMIT ? OFFSET ?";
    
    $order_history_stmt = $con->prepare($order_history_query);
    $order_history_stmt->bind_param('iii', $userid, $results_per_page, $offset);
    $order_history_stmt->execute();
    $order_history_result = $order_history_stmt->get_result();
    $total_orders = $order_history_result->num_rows;
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total FROM suppliers_orders_approved WHERE user_id = ?";
    $count_stmt = $con->prepare($count_query);
    $count_stmt->bind_param('i', $userid);
    $count_stmt->execute();
    $total_count = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();
    
    $total_pages = ceil($total_count / $results_per_page);

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
                    <i class="fas fa-bar"></i>Order History
                </a>
            </div>
        </div>
        <!-- /#sidebar-wrapper -->

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-align-left primary-text fs-4 me-3" id="menu-toggle"></i>
                    <h2 class="fs-2 m-0">Order History</h2>
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
                <div class="card shadow rounded-3 border-0">
                    <div class="card-header bg-white py-3">
                        <h4 class="mb-0">Order History</h4>
                        <p class="text-muted mb-0">View all your past orders and their status</p>
                    </div>
                    <div class="card-body p-0">
                        <div class="p-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="mb-0">Order History</h5>
                                <div class="input-group" style="max-width: 300px;">
                                    <input type="text" class="form-control" id="orderSearch" placeholder="Search orders...">
                                    <button class="btn btn-outline-secondary" type="button" id="searchOrderBtn">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead class="bg-success text-white">
                                        <tr>
                                            <th class="text-center">No.</th>
                                            <th>Material Name</th>
                                            <th class="text-end">Quantity</th>
                                            <th>Type</th>
                                            <th>Approved By</th>
                                            <th class="text-center">Date Approved</th>
                                        </tr>
                                    </thead>
                                    <tbody id="orderTableBody">
                                        <?php if ($total_orders > 0): 
                                            $counter = 1;
                                            while ($order = $order_history_result->fetch_assoc()): 
                                                $requester_name = trim(($order['firstname'] ?? '') . ' ' . ($order['lastname'] ?? ''));
                                        ?>
                                        <tr>
                                            <td class="text-center"><?php echo $counter++; ?></td>
                                            <td><?php echo htmlspecialchars($order['material_name'] ?? 'N/A'); ?></td>
                                            <td class="text-end"><?php echo number_format($order['quantity']); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst($order['type'] ?? 'N/A')); ?></td>
                                            <td><?php echo htmlspecialchars($requester_name ?: 'Unknown'); ?></td>
                                            <td class="text-center"><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                                        </tr>
                                        <?php 
                                            endwhile; 
                                        else: 
                                        ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">
                                                <div class="text-muted">No order history found</div>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <nav aria-label="Order history pagination" class="mt-4">
                                <ul class="pagination justify-content-center custom-pagination-green">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo ($page - 1); ?>" data-page="<?php echo ($page - 1); ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>" data-page="<?php echo $i; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo ($page + 1); ?>" data-page="<?php echo ($page + 1); ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
    <!-- /#wrapper -->

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
    
    document.addEventListener('DOMContentLoaded', function() {
        // Maintain active tab after page reload
        if (window.location.hash) {
            const hash = window.location.hash;
            const tab = document.querySelector(`button[data-bs-target="${hash}"][data-bs-toggle="tab"]`);
            if (tab) {
                const tabInstance = new bootstrap.Tab(tab);
                tabInstance.show();
            }
        }
        
        // Handle tab changes to update URL hash
        const tabLinks = document.querySelectorAll('button[data-bs-toggle="tab"]');
        tabLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                const target = this.getAttribute('data-bs-target');
                if (target.startsWith('#')) {
                    // Get current URL without hash
                    const url = new URL(window.location);
                    // Remove existing page parameter if it exists
                    const searchParams = new URLSearchParams(url.search);
                    searchParams.set('page', '1');
                    // Update URL with new hash and page=1
                    window.history.pushState({}, '', `${url.pathname}?${searchParams.toString()}${target}`);
                }
            });
        });

        // Order history search functionality
        const orderSearch = document.getElementById('orderSearch');
        const searchOrderBtn = document.getElementById('searchOrderBtn');
        
        if (orderSearch && searchOrderBtn) {
            const performSearch = () => {
                const searchTerm = orderSearch.value.toLowerCase();
                const rows = document.querySelectorAll('#orderTableBody tr');
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
                const tbody = document.getElementById('orderTableBody');
                if (!hasVisibleRows && tbody) {
                    const noResultsRow = document.createElement('tr');
                    noResultsRow.className = 'no-results';
                    noResultsRow.innerHTML = `
                        <td colspan="8" class="text-center py-4">
                            <div class="text-muted">No orders found matching your search</div>
                        </td>
                    `;
                    tbody.appendChild(noResultsRow);
                }
            };
            
            // Search on button click
            searchOrderBtn.addEventListener('click', performSearch);
            
            // Search on Enter key
            orderSearch.addEventListener('keyup', function(e) {
                if (e.key === 'Enter') {
                    performSearch();
                }
            });
        }
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