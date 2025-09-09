<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    session_start();
    include_once "../config.php";
    if ($con->connect_error) {
        $response = ['success' => false, 'message' => 'Database connection failed.'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    $userid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
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
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 3) {
    header("Location: ../login.php");
    exit();
}
$con = new mysqli("localhost", "root", "", "voltech");
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

// Get filter values
$search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';
$type_filter = isset($_GET['type']) ? mysqli_real_escape_string($con, $_GET['type']) : '';
$expensecategory = isset($_GET['expensecategory']) ? $_GET['expensecategory'] : '';
$amount_sort = isset($_GET['amount_sort']) ? $_GET['amount_sort'] : '';
$date_range = isset($_GET['date_range']) ? mysqli_real_escape_string($con, $_GET['date_range']) : '';

// Build WHERE clause
$where_conditions = ["user_id = '$userid'"];
if (!empty($search)) {
    $where_conditions[] = "(expensecategory LIKE '%$search%' OR description LIKE '%$search%')";
}
if (!empty($type_filter)) {
    $where_conditions[] = "expensecategory = '$type_filter'";
}
// Note: Project/Other filter removed since project_id column doesn't exist
if (!empty($date_range)) {
    switch($date_range) {
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
    }
}

// Handle amount sorting
$order_by = [];
if (!empty($amount_sort)) {
    $order_direction = ($amount_sort === 'lowest') ? 'ASC' : 'DESC';
    $order_by[] = "expense $order_direction";
}

// Default sorting if no specific sort is selected
if (empty($order_by)) {
    $order_by[] = "expensedate DESC";
}

$order_by_clause = !empty($order_by) ? "ORDER BY " . implode(", ", $order_by) : "";

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Handle sorting
$sort_field = isset($_GET['sort']) ? $_GET['sort'] : 'expensedate';
$sort_order = 'DESC';

// Validate sort field to prevent SQL injection
$valid_sort_fields = ['expensedate', 'expensecategory', 'expense', 'description'];
if (!in_array($sort_field, $valid_sort_fields)) {
    $sort_field = 'expensedate';
}

// Toggle sort order if clicking on the same field
if (isset($_GET['sort']) && isset($_GET['order']) && $_GET['order'] === 'DESC') {
    $sort_order = 'ASC';
}

// Pagination settings
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Get total number of records with filters
$total_query = "SELECT COUNT(*) as total FROM expenses $where_clause";
$total_result = mysqli_query($con, $total_query);
$total_row = mysqli_fetch_assoc($total_result);
$total_items = $total_row['total'];
$total_pages = ceil($total_items / $items_per_page);

// Expense types for filter dropdown
$expense_types = ["Materials", "Labor", "Equipment", "Transportation", "Site Costs", "Office Supplies", "Others"];

// Get total expenses and this month's total (always define before HTML)
$total_expense_query = mysqli_query($con, "SELECT COALESCE(SUM(expense), 0) as total FROM expenses WHERE user_id = '$userid'");
$total_expense = mysqli_fetch_assoc($total_expense_query);

$month_query = mysqli_query($con, "SELECT COALESCE(SUM(expense), 0) as month_total 
    FROM expenses 
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
$total_expense_query = mysqli_query($con, "SELECT COALESCE(SUM(expense), 0) as total FROM expenses WHERE user_id = '$userid'");
$total_expense = mysqli_fetch_assoc($total_expense_query);

// Total Expenses This Month
$month_expense_query = mysqli_query($con, "SELECT COALESCE(SUM(expense), 0) as total FROM expenses WHERE user_id = '$userid' AND MONTH(expensedate) = MONTH(CURDATE()) AND YEAR(expensedate) = YEAR(CURDATE())");
$month_expense = mysqli_fetch_assoc($month_expense_query);

// Total Expenses Last 7 Days (rolling week)
$week_expense_query = mysqli_query($con, "SELECT COALESCE(SUM(expense), 0) as total FROM expenses WHERE user_id = '$userid' AND expensedate >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)");
$week_expense = mysqli_fetch_assoc($week_expense_query);

// Total Expenses This Year
$year_expense_query = mysqli_query($con, "SELECT COALESCE(SUM(expense), 0) as total FROM expenses WHERE user_id = '$userid' AND YEAR(expensedate) = YEAR(CURDATE())");
$year_expense = mysqli_fetch_assoc($year_expense_query);

// Get paginated results with sorting
$query = "SELECT expense_id, expensedate, expensecategory, expense, description 
          FROM expenses $where_clause 
          $order_by_clause 
          LIMIT $offset, $items_per_page";
$result = mysqli_query($con, $query);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $expensecategory = mysqli_real_escape_string($con, $_POST['expensecategory']);
    $expenseamount = floatval($_POST['expenseamount']);
    $expensedate = mysqli_real_escape_string($con, $_POST['expensedate']);
    $description = trim(mysqli_real_escape_string($con, $_POST['description']));
    $user_id = $userid;

    // Backend validation
    if (empty($expensecategory) || empty($expensedate) || empty($description) || $expenseamount <= 0) {
        header('Location: expenses.php?error=validation');
        exit();
    }

    $insert_query = "INSERT INTO expenses (user_id, expensecategory, expense, expensedate, description) 
                     VALUES ('$user_id', '$expensecategory', '$expenseamount', '$expensedate', '$description')";
    if (mysqli_query($con, $insert_query)) {
        header('Location: expenses.php?success=1');
        exit();
    } else {
        header('Location: expenses.php?error=1');
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $expense_id = intval($_POST['expense_id']);
    $expensecategory = mysqli_real_escape_string($con, $_POST['expensecategory']);
    $expenseamount = floatval($_POST['expenseamount']);
    $expensedate = mysqli_real_escape_string($con, $_POST['expensedate']);

    $update_query = "UPDATE expenses SET expensecategory='$expensecategory', expense='$expenseamount', expensedate='$expensedate' WHERE expense_id='$expense_id' AND user_id='$userid'";
    if (mysqli_query($con, $update_query)) {
        header('Location: expenses.php?updated=1');
        exit();
    } else {
        header('Location: expenses.php?error=1');
        exit();
    }
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $expense_id = intval($_GET['id']);
    $delete_query = "DELETE FROM expenses WHERE expense_id='$expense_id' AND user_id='$userid'";
    if (mysqli_query($con, $delete_query)) {
        header('Location: expenses.php?deleted=1');
        exit();
    } else {
        header('Location: expenses.php?error=1');
        exit();
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
    <link rel="stylesheet" href="style.css" />
    <title>Project Manager Expenses</title>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div class="bg-white" id="sidebar-wrapper">
        <div class="user text-center py-4">
                <img class="img img-fluid rounded-circle mb-2 sidebar-profile-img" src="<?php echo isset($userprofile) ? $userprofile : (isset($_SESSION['userprofile']) ? $_SESSION['userprofile'] : '../uploads/default_profile.png'); ?>" width="70" alt="User Profile">
                <h5 class="mb-1 text-white"><?php echo htmlspecialchars($user_name); ?></h5>
                <p class="text-white small mb-0"><?php echo htmlspecialchars($user_email); ?></p>
                <hr style="border-top: 1px solid #fff; opacity: 0.3; margin: 12px 0 0 0;">
            </div>
            <div class="list-group list-group-flush ">
                <a href="pm_dashboard.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'pm_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>Dashboard
                </a>
                <a href="projects.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'projects.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i>Projects
                </a>
                <a href="expenses.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'expenses.php' ? 'active' : ''; ?>">
                    <i class="fas fa-wallet"></i>Expenses
                </a>
                <a href="materials.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'materials.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cubes"></i>Materials
                </a>
                <a href="equipment.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'equipment.php' ? 'active' : ''; ?>">
                    <i class="fas fa-wrench"></i>Equipment
                </a>
                <a href="employees.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'employees.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-friends"></i>Employees
                </a>
                <a href="positions.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'positions.php' ? 'active' : ''; ?>">
                    <i class="fas fa-briefcase"></i>Position
                </a>
                <a href="gantt.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'gantt.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar"></i>My Schedule
                </a>
                <a href="paymethod.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'paymethod.php' ? 'active' : ''; ?>">
                    <i class="fas fa-money-bill"></i>Payment Methods
                </a>
            </div>
        </div>
        <!-- /#sidebar-wrapper -->

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-align-left primary-text fs-4 me-3" id="menu-toggle"></i>
                    <h2 class="fs-2 m-0">Expenses</h2>
                </div>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                    <?php 
                    include 'pm_notification.php'; 
                    
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
                    $tables = ['pm_client_messages', 'pm_procurement_messages', 'admin_pm_messages'];
                    $unreadCount = countUnreadMessages($con, $tables, $_SESSION['user_id']);
                    ?>
                    <li class="nav-item ms-2">
                        <a class="nav-link position-relative" href="pm_messenger.php" title="Messages">
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
                                <li><a class="dropdown-item" href="pm_profile.php">Profile</a></li>
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal">Change Password</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="container-fluid px-4">
                <div class="card mb-5 mt-4 shadow rounded-3">
                  <div class="card-body p-4">
                    <div class="mb-3 d-flex justify-content-between align-items-center">
                      <h4 class="mb-0">Expense Management</h4>
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
                    
                    <!-- Search and Filter Row -->
                    <div class="mb-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
                      <form method="GET" class="d-flex flex-wrap gap-2 align-items-center mb-0" id="searchFilterForm" style="flex: 1; min-width: 0;">
                        <div class="input-group" style="width: 250px;">
                          <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                          <input type="text" class="form-control border-start-0" name="search" placeholder="Search expenses..." value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                        </div>
                        
                        <select class="form-select" name="type" style="width: 200px;" onchange="this.form.submit()">
                          <option value="">All Expense Types</option>
                          <option value="Project" <?php echo (isset($_GET['type']) && $_GET['type'] === 'Project') ? 'selected' : ''; ?>>Project Expenses</option>
                          <option value="Others" <?php echo (isset($_GET['type']) && $_GET['type'] === 'Others') ? 'selected' : ''; ?>>Other Expenses</option>
                        </select>
                        
                        <select class="form-select" name="amount_sort" style="width: 200px;" onchange="this.form.submit()">
                          <option value="">Sort by Amount</option>
                          <option value="lowest" <?php echo (isset($_GET['amount_sort']) && $_GET['amount_sort'] === 'lowest') ? 'selected' : ''; ?>>Lowest to Highest</option>
                          <option value="highest" <?php echo (isset($_GET['amount_sort']) && $_GET['amount_sort'] === 'highest') ? 'selected' : ''; ?>>Highest to Lowest</option>
                        </select>
                        
                        <!-- Hidden fields to preserve other filters -->
                        <?php if (!empty($date_range)): ?>
                          <input type="hidden" name="date_range" value="<?php echo htmlspecialchars($date_range); ?>">
                        <?php endif; ?>
                      </form>
                      
                      <div class="fw-bold text-success" style="font-size:1.1rem;">
                        Total Expenses: ₱<?php echo number_format($total_expense['total'], 2); ?>
                      </div>
                    </div>
                    <div class="table-responsive mb-0">
                      <table class="table table-bordered table-striped mb-0">
                        <thead class="thead-dark">
                          <tr>
                            <th>No</th>
                            <th>
                              <a href="?sort=expensedate&order=<?php echo ($sort_field == 'expensedate' && $sort_order == 'ASC') ? 'DESC' : 'ASC'; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($type_filter) ? '&type=' . urlencode($type_filter) : ''; ?><?php echo !empty($date_range) ? '&date_range=' . urlencode($date_range) : ''; ?>" class="text-white text-decoration-none">
                                Date
                                <?php if ($sort_field == 'expensedate'): ?>
                                  <i class="fas fa-sort-<?php echo ($sort_order == 'ASC') ? 'up' : 'down'; ?> ms-1"></i>
                                <?php else: ?>
                                  <i class="fas fa-sort ms-1 text-white-50"></i>
                                <?php endif; ?>
                              </a>
                            </th>
                            <th>
                              <a href="?sort=expensecategory&order=<?php echo ($sort_field == 'expensecategory' && $sort_order == 'ASC') ? 'DESC' : 'ASC'; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($type_filter) ? '&type=' . urlencode($type_filter) : ''; ?><?php echo !empty($date_range) ? '&date_range=' . urlencode($date_range) : ''; ?>" class="text-white text-decoration-none">
                                Type
                                <?php if ($sort_field == 'expensecategory'): ?>
                                  <i class="fas fa-sort-<?php echo ($sort_order == 'ASC') ? 'up' : 'down'; ?> ms-1"></i>
                                <?php else: ?>
                                  <i class="fas fa-sort ms-1 text-white-50"></i>
                                <?php endif; ?>
                              </a>
                            </th>
                            <th>
                              <a href="?sort=expense&order=<?php echo ($sort_field == 'expense' && $sort_order == 'ASC') ? 'DESC' : 'ASC'; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($type_filter) ? '&type=' . urlencode($type_filter) : ''; ?><?php echo !empty($date_range) ? '&date_range=' . urlencode($date_range) : ''; ?>" class="text-white text-decoration-none">
                                Amount
                                <?php if ($sort_field == 'expense'): ?>
                                  <i class="fas fa-sort-<?php echo ($sort_order == 'ASC') ? 'up' : 'down'; ?> ms-1"></i>
                                <?php else: ?>
                                  <i class="fas fa-sort ms-1 text-white-50"></i>
                                <?php endif; ?>
                              </a>
                            </th>
                            <th>
                              <a href="?sort=description&order=<?php echo ($sort_field == 'description' && $sort_order == 'ASC') ? 'DESC' : 'ASC'; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($type_filter) ? '&type=' . urlencode($type_filter) : ''; ?><?php echo !empty($date_range) ? '&date_range=' . urlencode($date_range) : ''; ?>" class="text-white text-decoration-none">
                                Description
                                <?php if ($sort_field == 'description'): ?>
                                  <i class="fas fa-sort-<?php echo ($sort_order == 'ASC') ? 'up' : 'down'; ?> ms-1"></i>
                                <?php else: ?>
                                  <i class="fas fa-sort ms-1 text-white-50"></i>
                                <?php endif; ?>
                              </a>
                            </th>
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
                          <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($type_filter) ? '&type=' . urlencode($type_filter) : ''; ?><?php echo !empty($date_range) ? '&date_range=' . urlencode($date_range) : ''; ?><?php echo isset($sort_field) ? '&sort=' . urlencode($sort_field) . '&order=' . urlencode($sort_order) : ''; ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                            <span class="sr-only">Previous</span>
                          </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                          <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($type_filter) ? '&type=' . urlencode($type_filter) : ''; ?><?php echo !empty($date_range) ? '&date_range=' . urlencode($date_range) : ''; ?><?php echo isset($sort_field) ? '&sort=' . urlencode($sort_field) . '&order=' . urlencode($sort_order) : ''; ?>">
                              <?php echo $i; ?>
                            </a>
                          </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                          <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($type_filter) ? '&type=' . urlencode($type_filter) : ''; ?><?php echo !empty($date_range) ? '&date_range=' . urlencode($date_range) : ''; ?><?php echo isset($sort_field) ? '&sort=' . urlencode($sort_field) . '&order=' . urlencode($sort_order) : ''; ?>" aria-label="Next">
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
                    <form action="expenses.php" method="POST">
                        <div class="modal-body">
                            <div class="form-group">
                                <label><b>Expense Type</b></label>
                                <select class="form-control" name="expensecategory" required>
                                    <option value="">Select Expense Type</option>
                                    <option value="Materials">Materials</option>
                                    <option value="Labor">Labor</option>
                                    <option value="Equipment">Equipment</option>
                                    <option value="Transportation">Transportation</option>
                                    <option value="Site Costs">Site Costs</option>
                                    <option value="Office Supplies">Office Supplies</option>
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
                    <form action="expenses.php" method="POST">
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

    <!-- Export PDF Confirmation Modal -->
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        var el = document.getElementById("wrapper");
        var toggleButton = document.getElementById("menu-toggle");

        toggleButton.onclick = function () {
            el.classList.toggle("toggled");
        };
    </script>
    <style>
    .custom-pagination-green .page-item.active .page-link,
    .custom-pagination-green .page-item .page-link:hover {
        background-color: #009d63;
        border-color: #009d63;
        color: #fff;
    }
    .custom-pagination-green .page-link {
        color: #009d63;
    }
    </style>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    var searchInput1 = document.getElementById('searchInput1');
    var searchForm1 = document.getElementById('searchFilterForm'); // Changed from searchForm1 to searchFilterForm
    var typeFilter = document.getElementById('typeFilter');
    var dateRangeFilter = document.getElementById('dateRangeFilter');
    var searchForm2 = document.getElementById('searchForm2'); // This variable is no longer used
    if (searchInput1 && searchForm1) {
        var searchTimeout;
        searchInput1.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                searchForm1.submit();
            }, 400);
        });
    }
    // Removed typeFilter and dateRangeFilter event listeners as they are no longer in the form
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
<?php if (isset($_GET['success'])): ?>
document.addEventListener('DOMContentLoaded', function() {
  showFeedbackModal(true, 'Expense added successfully!', '', 'success');
});
<?php elseif (isset($_GET['updated'])): ?>
document.addEventListener('DOMContentLoaded', function() {
  showFeedbackModal(true, 'Expense updated successfully!', '', 'updated');
});
<?php elseif (isset($_GET['deleted'])): ?>
document.addEventListener('DOMContentLoaded', function() {
  showFeedbackModal(true, 'Expense deleted successfully!', '', 'deleted');
});
<?php elseif (isset($_GET['error'])): ?>
document.addEventListener('DOMContentLoaded', function() {
  showFeedbackModal(false, 'An error occurred. Please try again.', '', 'error');
});
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'validation'): ?>
document.addEventListener('DOMContentLoaded', function() {
  showFeedbackModal(false, 'All fields are required and amount must be greater than zero.', '', 'error');
});
<?php endif; ?>
</script>
<div class="modal fade" id="deleteExpenseModal" tabindex="-1" aria-labelledby="deleteExpenseModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteExpenseModalLabel">Confirm Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to delete <strong id="expenseName"></strong>?</p>
        <p class="text-danger">This action cannot be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="#" id="confirmDeleteExpense" class="btn btn-danger">Delete</a>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.delete-expense-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      var expId = this.getAttribute('data-id');
      var expName = this.getAttribute('data-name');
      document.getElementById('expenseName').textContent = expName;
      var confirmDelete = document.getElementById('confirmDeleteExpense');
      confirmDelete.setAttribute('href', 'expenses.php?id=' + expId);
      var modal = new bootstrap.Modal(document.getElementById('deleteExpenseModal'));
      modal.show();
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
        setTimeout(function() { location.reload(); }, 1000);
      }, 300);
    });
  }
});
</script>
</body>

</html>