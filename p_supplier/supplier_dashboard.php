<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 5) {
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


// Get count of approved materials from suppliers_orders_approved
$approved_materials_count = 0;
$approved_materials_query = mysqli_query($con, "SELECT COUNT(*) as total FROM suppliers_orders_approved WHERE user_id = '$userid' AND type = 'material'");
if ($approved_materials_query) {
    $approved_materials_result = mysqli_fetch_assoc($approved_materials_query);
    $approved_materials_count = intval($approved_materials_result['total'] ?? 0);
}

// For Total Reorder count (from suppliers_orders_approved)
$reorder_count = 0;
$reorder_count_query = mysqli_query($con, "SELECT COUNT(*) as count FROM suppliers_orders_approved WHERE user_id = '$userid' AND type = 'reorder'");
if ($reorder_count_query) {
    $reorder_count_result = mysqli_fetch_assoc($reorder_count_query);
    $reorder_count = intval($reorder_count_result['count'] ?? 0);
}

// For Total Backorder count (from suppliers_orders_approved)
$backorder_count = 0;
$backorder_count_query = mysqli_query($con, "SELECT COUNT(*) as count FROM suppliers_orders_approved WHERE user_id = '$userid' AND type = 'backorder'");
if ($backorder_count_query) {
    $backorder_count_result = mysqli_fetch_assoc($backorder_count_query);
    $backorder_count = intval($backorder_count_result['count'] ?? 0);
}

// Get total approved orders for the logged-in user
$total_approved_orders = 0;
$approved_orders_query = mysqli_query($con, "SELECT COUNT(*) as total FROM suppliers_orders_approved WHERE user_id = '$userid'");
if ($approved_orders_query) {
    $approved_orders_result = mysqli_fetch_assoc($approved_orders_query);
    $total_approved_orders = intval($approved_orders_result['total'] ?? 0);
}

// Get data for bar graph (monthly orders for the current year)
$monthly_orders = array_fill(0, 12, 0);
$monthly_query = mysqli_query($con, "SELECT 
    MONTH(approve_date) as month, 
    COUNT(*) as count 
    FROM suppliers_orders_approved 
    WHERE user_id = '$userid' 
    AND YEAR(approve_date) = YEAR(CURDATE())
    GROUP BY MONTH(approve_date)");
while ($row = mysqli_fetch_assoc($monthly_query)) {
    $monthly_orders[$row['month'] - 1] = (int)$row['count'];
}

// Get data for line graph (order types over time)
$order_types = [];
$order_type_query = mysqli_query($con, "SELECT 
    DATE_FORMAT(approve_date, '%Y-%m') as month,
    type,
    COUNT(*) as count
    FROM suppliers_orders_approved 
    WHERE user_id = '$userid'
    AND approve_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(approve_date, '%Y-%m'), type
    ORDER BY month, type");

$line_labels = [];
$line_datasets = [
    'material' => [],
    'reorder' => [],
    'backorder' => []
];
$months = [];

while ($row = mysqli_fetch_assoc($order_type_query)) {
    if (!in_array($row['month'], $months)) {
        $months[] = $row['month'];
    }
    $line_datasets[$row['type']][$row['month']] = (int)$row['count'];
}

// Fill in missing months with 0
foreach ($line_datasets as $type => $data) {
    foreach ($months as $month) {
        if (!isset($data[$month])) {
            $line_datasets[$type][$month] = 0;
        }
    }
    ksort($line_datasets[$type]);
}

// Prepare table data (recent orders)
$recent_orders = [];
$recent_orders_query = mysqli_query($con, "SELECT 
    id,
    type,
    approve_date
    FROM suppliers_orders_approved 
    WHERE user_id = '$userid'
    ORDER BY approve_date DESC
    LIMIT 10");

while ($row = mysqli_fetch_assoc($recent_orders_query)) {
    $recent_orders[] = $row;
}

// Handle AJAX password change (like pm_profile.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
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
    <title>Supplier Dashboard</title>
    <style>
.card-link { cursor: pointer; text-decoration: none; color: inherit; }
.card-link:hover { box-shadow: 0 0 0 2px #009d6333; }
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

            <div class="container-fluid px-4">
                <div class="card shadow d-flex px-4">
                <div class="row g-3 my-2">
                  <div class="col-12 mb-3 d-flex flex-column flex-md-row align-items-center justify-content-between">
                    <!-- Left: Date Range + Export (no background) -->
                    <form class="row g-2 align-items-end flex-nowrap mb-3 mb-md-0" method="post" action="export_dasshboard_pdf.php" target="_blank">
                      <div class="col-auto">
                        <label for="start_date" class="form-label mb-0">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                      </div>
                      <div class="col-auto">
                        <label for="end_date" class="form-label mb-0">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" required>
                      </div>
                      <div class="col-auto">
                        <button type="button" class="btn btn-success mt-2 mt-md-0">
                          <i class="fas fa-file-pdf"></i> Export as PDF
                        </button>
                      </div>
                    </form>
                    <!-- Right: Clock and Date (with background) -->
                    <div class="d-flex flex-column align-items-end ms-md-auto"
                         style="background: linear-gradient(90deg, #e0f7fa 0%, #fff 100%); border-radius: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); padding: 1.2rem 2.2rem;">
                      <div class="d-flex align-items-center" style="gap: 0.5rem;">
                        <i class="fas fa-clock fa-2x text-primary"></i>
                        <span id="dashboard-clock" style="font-size:2.2rem; font-weight:bold; letter-spacing:2px;"></span>
                      </div>
                      <div id="dashboard-date" style="font-size:1.1rem; color:#009d63; font-weight:500;"></div>
                    </div>
                  </div>
                </div>
                </div>
                <div class="row g-3 my-2">
                    <div class="col-md-3">
                        <div class="p-3 bg-white shadow d-flex justify-content-around align-items-center rounded card-link">
                            <div>
                                <h3 class="fs-2">
                                    <?php echo $approved_materials_count; ?>
                                </h3>
                                <p class="fs-5">Approved Materials</p>
                            </div>
                            <i class="fas fa-cubes fs-1 primary-text"></i>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-white shadow d-flex justify-content-around align-items-center rounded card-link">
                            <div>
                                <h3 class="fs-2">
                                <?php echo $reorder_count; ?>
                                </h3>
                                <p class="fs-5">Total Reorder</p>
                            </div>
                            <i class="fas fa-wrench fs-1 primary-text"></i>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-white shadow d-flex justify-content-around align-items-center rounded card-link">
                            <div>
                                <h3 class="fs-2">
                                <?php echo $backorder_count; ?>
                                </h3>
                                <p class="fs-5">Total Backorder</p>
                            </div>
                            <i class="fas fa-hand-holding-usd fs-1 primary-text"></i>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-white shadow d-flex justify-content-around align-items-center rounded card-link" onclick="location.href='supplier_order_history.php'" style="cursor: pointer;">
                            <div>
                                <h3 class="fs-2">
                                <?php echo $total_approved_orders; ?>
                                </h3>
                                <p class="fs-5">Approved Orders</p>
                            </div>
                            <i class="fas fa-clipboard-check fs-1 primary-text"></i>
                        </div>
                    </div>
                </div>

                <!-- Charts and Table Section -->
                <div class="row mt-4">
                    <!-- Bar Graph Card -->
                    <div class="col-md-6 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">Monthly Orders (<?php echo date('Y'); ?>)</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="monthlyOrdersChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Line Graph Card -->
                    <div class="col-md-6 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">Order Types (Last 6 Months)</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="orderTypesChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders Table -->
                <div class="row mt-2">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">Recent Approved Orders</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>No.</th>
                                                <th>Order ID</th>
                                                <th>Type</th>
                                                <th>Approval Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $counter = 1; ?>
                                            <?php foreach ($recent_orders as $order): ?>
                                            <tr>
                                                <td><?php echo $counter++; ?></td>
                                                <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                                <td>
                                                    <span class="badge 
                                                        <?php 
                                                            $badgeClass = 'bg-secondary';
                                                            if ($order['type'] === 'material') $badgeClass = 'bg-primary';
                                                            elseif ($order['type'] === 'reorder') $badgeClass = 'bg-warning text-dark';
                                                            elseif ($order['type'] === 'backorder') $badgeClass = 'bg-info text-dark';
                                                            echo $badgeClass;
                                                        ?>">
                                                        <?php echo ucfirst(htmlspecialchars($order['type'])); ?>
                                                    </span>
                                                </td>
                                           
                                                <td><?php echo date('M d, Y', strtotime($order['approve_date'])); ?></td>
                                                
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($recent_orders)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center">No recent orders found</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
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

    <!-- Export PDF Confirmation Modal -->
    <div class="modal fade" id="exportDashboardPdfModal" tabindex="-1" aria-labelledby="exportDashboardPdfModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exportDashboardPdfModalLabel">Export as PDF</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to export the dashboard as PDF for the selected date range?</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" id="confirmExportDashboardPdf" class="btn btn-danger">Export</button>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Monthly Orders Bar Chart
        const monthlyCtx = document.getElementById('monthlyOrdersChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Orders',
                    data: <?php echo json_encode($monthly_orders); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Order Types Line Chart
        const orderTypesCtx = document.getElementById('orderTypesChart').getContext('2d');
        new Chart(orderTypesCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [
                    {
                        label: 'Materials',
                        data: <?php echo json_encode(array_values($line_datasets['material'])); ?>,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Reorders',
                        data: <?php echo json_encode(array_values($line_datasets['reorder'])); ?>,
                        borderColor: 'rgba(255, 206, 86, 1)',
                        backgroundColor: 'rgba(255, 206, 86, 0.1)',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Backorders',
                        data: <?php echo json_encode(array_values($line_datasets['backorder'])); ?>,
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        tension: 0.3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
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
    <!-- Confirmation Modal -->
    <div class="modal fade" id="cardConfirmModal" tabindex="-1" aria-labelledby="cardConfirmModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center">
          <div class="modal-body">
            <span style="font-size: 2.5rem; color: #009d63;"><i class="fas fa-question-circle"></i></span>
            <h5 id="cardConfirmModalLabel" class="mt-3 mb-2">Are you sure?</h5>
            <p id="cardConfirmModalMsg"></p>
            <div class="d-flex justify-content-center gap-2 mt-3">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-success" id="cardConfirmModalGo">Go</button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <script>
function confirmAndRedirect(message, url) {
  // replaced by showCardConfirmModal
}
let cardConfirmUrl = '';
function showCardConfirmModal(message, url) {
  document.getElementById('cardConfirmModalMsg').textContent = message;
  cardConfirmUrl = url;
  var modal = new bootstrap.Modal(document.getElementById('cardConfirmModal'));
  modal.show();
  // Remove previous event listeners to avoid stacking
  const goBtn = document.getElementById('cardConfirmModalGo');
  goBtn.onclick = function() {
    window.location.href = cardConfirmUrl;
  };
}
</script>
<script>
function updateClockAndDate() {
  const now = new Date();
  const h = String(now.getHours()).padStart(2, '0');
  const m = String(now.getMinutes()).padStart(2, '0');
  const s = String(now.getSeconds()).padStart(2, '0');
  document.getElementById('dashboard-clock').textContent = `${h}:${m}:${s}`;
  const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
  document.getElementById('dashboard-date').textContent = now.toLocaleDateString('en-US', options);
}
setInterval(updateClockAndDate, 1000);
updateClockAndDate();
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
  var exportForm = document.querySelector('form[action="export_dasshboard_pdf.php"]');
  var exportBtn = exportForm.querySelector('.btn-success');
  var confirmExportBtn = document.getElementById('confirmExportDashboardPdf');
  var exportModal = new bootstrap.Modal(document.getElementById('exportDashboardPdfModal'));
  if (exportBtn) {
    exportBtn.addEventListener('click', function(e) {
      e.preventDefault();
      exportModal.show();
    });
  }
  if (confirmExportBtn) {
    confirmExportBtn.addEventListener('click', function(e) {
      e.preventDefault();
      exportModal.hide();
      setTimeout(function() {
        exportForm.submit();
        setTimeout(function() { location.reload(); }, 1000);
      }, 300);
    });
  }
});
</script>
</body>

</html>