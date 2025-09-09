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
$type_filter = isset($_GET['type']) ? mysqli_real_escape_string($con, $_GET['type']) : '';
$date_range = isset($_GET['date_range']) ? mysqli_real_escape_string($con, $_GET['date_range']) : '';

// Build WHERE clause
$where_conditions = ["user_id = '$userid'"];
if (!empty($search)) {
    $where_conditions[] = "(expensecategory LIKE '%$search%' OR description LIKE '%$search%' OR expense_id LIKE '%$search%')";
}

// Type filter (Equipment/Materials)
$type_filter = isset($_GET['type']) ? mysqli_real_escape_string($con, $_GET['type']) : '';
if (!empty($type_filter)) {
    $where_conditions[] = "expensecategory = '$type_filter'";
}

// Status filter (Reorder/Backorder)
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($con, $_GET['status']) : '';
if (!empty($status_filter)) {
    if ($status_filter === 'reorder') {
        $where_conditions[] = "(description LIKE '%reorder%' OR description LIKE '%re-order%' OR description LIKE '%re order%')";
    } elseif ($status_filter === 'backorder') {
        $where_conditions[] = "(description LIKE '%backorder%' OR description LIKE '%back-order%' OR description LIKE '%back order%')";
    }
}

// Date filter
$date_filter = isset($_GET['date_filter']) ? mysqli_real_escape_string($con, $_GET['date_filter']) : '';
$start_date = isset($_GET['start_date']) ? mysqli_real_escape_string($con, $_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? mysqli_real_escape_string($con, $_GET['end_date']) : '';

// If custom date range is provided, use it regardless of date_filter value
if (!empty($start_date) && !empty($end_date)) {
    $where_conditions[] = "DATE(expensedate) BETWEEN '$start_date' AND '$end_date'";
} 
// If no date range but a date filter is selected
else if (!empty($date_filter)) {
    switch($date_filter) {
        case 'today':
            $where_conditions[] = "DATE(expensedate) = CURDATE()";
            break;
        case 'week':
            $where_conditions[] = "YEARWEEK(expensedate, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'month':
            $where_conditions[] = "YEAR(expensedate) = YEAR(CURDATE()) AND MONTH(expensedate) = MONTH(CURDATE())";
            break;
        case 'year':
            $where_conditions[] = "YEAR(expensedate) = YEAR(CURDATE())";
            break;
        // 'All Time' is handled by not adding any date condition
    }
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Pagination settings
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Get total number of records with filters
$total_query = "SELECT COUNT(*) as total FROM order_expenses $where_clause";
$total_result = mysqli_query($con, $total_query);
$total_row = mysqli_fetch_assoc($total_result);
$total_items = $total_row['total'];
$total_pages = ceil($total_items / $items_per_page);

// Expense types for filter dropdown
$expense_types = ["Materials", "Labor", "Equipment", "Transportation", "Site Costs", "Office Supplies", "Others"];

// Get filter values from URL
$search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';
$type_filter = isset($_GET['type']) ? mysqli_real_escape_string($con, $_GET['type']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($con, $_GET['status']) : '';
$date_filter = isset($_GET['date_filter']) ? mysqli_real_escape_string($con, $_GET['date_filter']) : '';
$start_date = isset($_GET['start_date']) ? mysqli_real_escape_string($con, $_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? mysqli_real_escape_string($con, $_GET['end_date']) : '';

// Get total expenses and this month's total (always define before HTML)
$total_expense_query = mysqli_query($con, "SELECT COALESCE(SUM(expense), 0) as total FROM order_expenses WHERE user_id = '$userid'");
$total_expense = mysqli_fetch_assoc($total_expense_query);

$month_query = mysqli_query($con, "SELECT COALESCE(SUM(expense), 0) as month_total 
    FROM order_expenses 
    WHERE user_id = '$userid' 
    AND MONTH(expensedate) = MONTH(CURRENT_DATE())
    AND YEAR(expensedate) = YEAR(CURRENT_DATE())");
$month_total = mysqli_fetch_assoc($month_query);

// Helper function for short number formatting
function short_number_format(
    $num,
    $precision = 1
) {
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

// Total Expenses (All Time)
$total_expense_query = mysqli_query($con, "SELECT COALESCE(SUM(expense), 0) as total FROM order_expenses WHERE user_id = '$userid'");
$total_expense = mysqli_fetch_assoc($total_expense_query);

// Total Expenses This Month
$month_expense_query = mysqli_query($con, "SELECT COALESCE(SUM(expense), 0) as total FROM order_expenses WHERE user_id = '$userid' AND MONTH(expensedate) = MONTH(CURDATE()) AND YEAR(expensedate) = YEAR(CURDATE())");
$month_expense = mysqli_fetch_assoc($month_expense_query);

// Total Expenses Last 7 Days (rolling week)
$week_expense_query = mysqli_query($con, "SELECT COALESCE(SUM(expense), 0) as total FROM order_expenses WHERE user_id = '$userid' AND expensedate >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)");
$week_expense = mysqli_fetch_assoc($week_expense_query);

// Total Expenses This Year
$year_expense_query = mysqli_query($con, "SELECT COALESCE(SUM(expense), 0) as total FROM order_expenses WHERE user_id = '$userid' AND YEAR(expensedate) = YEAR(CURDATE())");
$year_expense = mysqli_fetch_assoc($year_expense_query);

// Get paginated results
$query = "SELECT expense_id, expensedate, expensecategory, expense, description 
          FROM order_expenses $where_clause 
          ORDER BY expensedate DESC 
          LIMIT $offset, $items_per_page";
$result = mysqli_query($con, $query);

// Remove inline add, update, and delete logic (to be moved to separate files)

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
    <title>Procurement Officer Purchases</title>
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
                    <h2 class="fs-2 m-0">Dashboard</h2>
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
                  <div class="card-body p-4">
                    <div class="mb-3 d-flex justify-content-between align-items-center">
                      <h4 class="mb-0">Purchase Expense</h4>
                      <div>
                        <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                          <i class="fas fa-plus"></i> Add Expense
                        </button>
                        <a href="#" class="btn btn-danger" id="exportPdfBtn">
                          <i class="fas fa-file-pdf"></i> Export as PDF
                        </a>
                      </div>
                    </div>
                    <hr>
                    <form method="GET" class="d-flex flex-wrap gap-2 mb-3" id="searchFilterForm">
                        <!-- Search Bar -->
                        <div class="input-group" style="width: 250px;">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" class="form-control border-start-0" name="search" placeholder="Search orders..." value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                        </div>
                        
                        <!-- Type Filter -->
                        <select name="type" class="form-select" style="width: 150px;">
                            <option value="">All Types</option>
                            <option value="Equipment" <?php echo ($type_filter === 'Equipment') ? 'selected' : ''; ?>>Equipment</option>
                            <option value="Material" <?php echo ($type_filter === 'Material') ? 'selected' : ''; ?>>Material</option>
                        </select>
                        
                        <!-- Status Filter -->
                        <select name="status" class="form-select" style="width: 150px;">
                            <option value="">All Status</option>
                            <option value="reorder" <?php echo ($status_filter === 'reorder') ? 'selected' : ''; ?>>Re-order</option>
                            <option value="backorder" <?php echo ($status_filter === 'backorder') ? 'selected' : ''; ?>>Backorder</option>
                        </select>
                        
                        <!-- Date Range -->
                        <div class="d-flex gap-2" style="width: 350px;">
                            <select name="date_filter" class="form-select" style="width: 150px;">
                                <option value="">All Time</option>
                                <option value="today" <?php echo ($date_filter === 'today') ? 'selected' : ''; ?>>Today</option>
                                <option value="week" <?php echo ($date_filter === 'week') ? 'selected' : ''; ?>>This Week</option>
                                <option value="month" <?php echo ($date_filter === 'month') ? 'selected' : ''; ?>>This Month</option>
                                <option value="year" <?php echo ($date_filter === 'year') ? 'selected' : ''; ?>>This Year</option>
                            </select>
                            <input type="date" name="start_date" class="form-control" style="width: 100px;" value="<?php echo $start_date; ?>" placeholder="Start">
                            <span class="d-flex align-items-center">to</span>
                            <input type="date" name="end_date" class="form-control" style="width: 100px;" value="<?php echo $end_date; ?>" placeholder="End">
                        </div>
                    </form>
                    
                    <!-- Total Expenses -->
                   
                    </div>
                    <div class="table-responsive mb-0">
                      <table class="table table-bordered table-striped mb-0">
                        <thead class="thead-dark">
                          <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Description</th>
                            <th class="text-center">Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php 
                          $count = 1;
                          while ($row = mysqli_fetch_assoc($result)): 
                          ?>
                          <tr>
                            <td><?php echo $count + $offset; ?></td>
                            <td><?php echo date('M d, Y', strtotime($row['expensedate'])); ?></td>
                            <td><?php echo htmlspecialchars($row['expensecategory']); ?></td>
                            <td>₱<?php echo number_format($row['expense'], 2); ?></td>
                            <td class="text-truncate" title="<?php echo htmlspecialchars($row['description']); ?>">
                              <?php echo htmlspecialchars($row['description']); ?>
                            </td>
                            <td class="text-center">
                              <div class="action-buttons">
                                <a href="#" class="btn btn-sm btn-primary text-white font-weight-bold" data-bs-toggle="modal" data-bs-target="#viewExpenseModal<?php echo $row['expense_id']; ?>">
                                  <i class="fas fa-eye"></i> View More
                                </a>
                              </div>
                            </td>
                          </tr>
                          <?php 
                          $count++;
                          endwhile; 
                          if (mysqli_num_rows($result) == 0): 
                          ?>
                          <tr>
                            <td colspan="6" class="text-center py-4">No expenses found</td>
                          </tr>
                          <?php endif; ?>
                        </tbody>
                      </table>
                    </div>
                    <nav aria-label="Page navigation" class="mt-4">
                      <ul class="pagination justify-content-center custom-pagination-green">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                          <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($type_filter) ? '&type=' . urlencode($type_filter) : ''; ?><?php echo !empty($date_range) ? '&date_range=' . urlencode($date_range) : ''; ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                            <span class="sr-only">Previous</span>
                          </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                          <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($type_filter) ? '&type=' . urlencode($type_filter) : ''; ?><?php echo !empty($date_range) ? '&date_range=' . urlencode($date_range) : ''; ?>">
                              <?php echo $i; ?>
                            </a>
                          </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                          <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($type_filter) ? '&type=' . urlencode($type_filter) : ''; ?><?php echo !empty($date_range) ? '&date_range=' . urlencode($date_range) : ''; ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                            <span class="sr-only">Next</span>
                          </a>
                        </li>
                      </ul>
                    </nav>
                  </div>
                </div>
            </div>
        </div>

        <!-- Add Expense Modal -->
        <div class="modal fade" id="addExpenseModal" tabindex="-1" aria-labelledby="addExpenseModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addExpenseModalLabel">Add New Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="add_order_expense.php" method="POST">
                        <div class="modal-body">
                            <div class="form-group">
                                <label><b>Expense Type</b></label>
                                <select class="form-control" name="expensecategory" required>
                                    <option value="Others">Others</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><b>Amount (₱)</b></label>
                                <input type="number" step="0.01" class="form-control" name="expenseamount" placeholder="Enter amount" required>
                            </div>
                            
                            <div class="form-group">
                                <label><b>Date</b></label>
                                <input type="date" class="form-control" name="expensedate" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label><b>Description/Remarks</b></label>
                                <textarea class="form-control" name="description" rows="3" placeholder="Enter description or remarks" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" name="add" class="btn btn-success">Add Expense</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- View and Edit Modals -->
        <?php 
        // Reset the result pointer
        mysqli_data_seek($result, 0);
        
        while ($row = mysqli_fetch_assoc($result)): 
        ?>
        <!-- View Expense Modal -->
        <div class="modal fade" id="viewExpenseModal<?php echo $row['expense_id']; ?>" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Expense Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <dl class="row">
                            <dt class="col-sm-4">Date:</dt>
                            <dd class="col-sm-8"><?php echo date('M d, Y', strtotime($row['expensedate'])); ?></dd>
                            
                            <dt class="col-sm-4">Type:</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($row['expensecategory']); ?></dd>
                            
                            <dt class="col-sm-4">Amount:</dt>
                            <dd class="col-sm-8">₱<?php echo number_format($row['expense'], 2); ?></dd>
                        </dl>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editExpenseModal<?php echo $row['expense_id']; ?>" data-bs-dismiss="modal">Edit</button>
                        <a href="#" class="btn btn-danger btn-sm text-white delete-expense-btn" data-id="<?php echo $row['expense_id']; ?>" data-name="<?php echo htmlspecialchars($row['expensecategory']); ?>">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Expense Modal -->
        <div class="modal fade" id="editExpenseModal<?php echo $row['expense_id']; ?>" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="update_order_expense.php" method="POST">
                        <input type="hidden" name="expense_id" value="<?php echo $row['expense_id']; ?>">
                        <div class="modal-body">
                            <div class="form-group">
                                <label><b>Expense Type</b></label>
                                <select class="form-control" name="expensecategory" required>
                                    <option value="Materials" <?php echo ($row['expensecategory'] == 'Materials') ? 'selected' : ''; ?>>Materials</option>
                                    <option value="Labor" <?php echo ($row['expensecategory'] == 'Labor') ? 'selected' : ''; ?>>Labor</option>
                                    <option value="Equipment" <?php echo ($row['expensecategory'] == 'Equipment') ? 'selected' : ''; ?>>Equipment</option>
                                    <option value="Transportation" <?php echo ($row['expensecategory'] == 'Transportation') ? 'selected' : ''; ?>>Transportation</option>
                                    <option value="Site Costs" <?php echo ($row['expensecategory'] == 'Site Costs') ? 'selected' : ''; ?>>Site Costs</option>
                                    <option value="Office Supplies" <?php echo ($row['expensecategory'] == 'Office Supplies') ? 'selected' : ''; ?>>Office Supplies</option>
                                    <option value="Others" <?php echo ($row['expensecategory'] == 'Others') ? 'selected' : ''; ?>>Others</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><b>Amount (₱)</b></label>
                                <input type="number" step="0.01" class="form-control" name="expenseamount" value="<?php echo htmlspecialchars($row['expense']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label><b>Date</b></label>
                                <input type="date" class="form-control" name="expensedate" value="<?php echo htmlspecialchars($row['expensedate']); ?>" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update" class="btn btn-primary">Update Expense</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endwhile; ?>

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
        <p>Are you sure you want to export the expenses as PDF?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="#" id="confirmExportPdf" class="btn btn-danger">Export</a>
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
<!-- Export PDF Confirmation Modal -->

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
        window.open('export_expenses_pdf.php', '_blank');
      }, 300);
    });
  }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-submit form when filters change
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('searchFilterForm');
    const filterInputs = filterForm.querySelectorAll('select, input');
    const dateFilterSelect = filterForm.querySelector('select[name="date_filter"]');
    const startDateInput = filterForm.querySelector('input[name="start_date"]');
    const endDateInput = filterForm.querySelector('input[name="end_date"]');
    
    // Auto-submit when any filter changes
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            // If date range is manually selected, clear the date filter dropdown
            if ((input === startDateInput || input === endDateInput) && (startDateInput.value || endDateInput.value)) {
                dateFilterSelect.value = '';
            }
            // If a date filter is selected from dropdown, clear the date inputs
            else if (input === dateFilterSelect && input.value !== '') {
                startDateInput.value = '';
                endDateInput.value = '';
            }
            
            filterForm.submit();
        });
    });
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