<?php
ob_start();
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 3) {
    header("Location: ../login.php");
    exit();
}
include_once "../config.php";

// Include function files
require_once 'project_add_functions.php';
require_once 'projects_remove.php';
require_once 'projects_update.php';

$userid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$user_email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
$user_firstname = isset($_SESSION['firstname']) ? $_SESSION['firstname'] : '';
$user_lastname = isset($_SESSION['lastname']) ? $_SESSION['lastname'] : '';
$user_name = trim($user_firstname . ' ' . $user_lastname);
$current_page = basename($_SERVER['PHP_SELF']);


// Handle AJAX password change (like pm_profile.php) - MUST BE BEFORE ANY OUTPUT
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



if (!isset($_GET['id'])) {
    header("Location: project_list.php");
    exit();
}

$project_id = $_GET['id'];

// Fetch project details with foreman information
$project_query = mysqli_query($con, 
    "SELECT p.*, 
            CONCAT(e.first_name, ' ', e.last_name) AS foreman_name
     FROM projects p
     LEFT JOIN project_add_employee pae ON p.project_id = pae.project_id AND pae.position = 'Foreman'
     LEFT JOIN employees e ON pae.employee_id = e.employee_id
     WHERE p.project_id='$project_id' AND p.user_id='$userid'");

// If project not found or doesn't belong to user, redirect
if (mysqli_num_rows($project_query) == 0) {
    header("Location: project_list.php");
    exit();
}

// Fetch project data
$project = mysqli_fetch_assoc($project_query);

// Handle project status update (Finish/Cancel)
if (isset($_POST['update_project_status'])) {
    $status = $_POST['update_project_status'];
    $project_id = $_GET['id'];
    
    // Validate status
    if (in_array($status, ['Finished', 'Cancelled'])) {
        // Start transaction
        $con->begin_transaction();
        
        try {
            // Update project status
            $update_query = "UPDATE projects SET status = ? WHERE project_id = ? AND user_id = ?";
            $stmt = $con->prepare($update_query);
            $stmt->bind_param('sii', $status, $project_id, $userid);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to update project status');
            }
            
            // If project is being marked as Finished, update employee and equipment statuses and add expense record
            if ($status === 'Finished') {
                // 1. Update all employees assigned to this project to 'Available' status
                $update_employees = "UPDATE project_add_employee SET status = 'Available' 
                                  WHERE project_id = ? AND status = 'Working'";
                $stmt_emp = $con->prepare($update_employees);
                if (!$stmt_emp) {
                    throw new Exception('Failed to prepare employee status update: ' . $con->error);
                }
                $stmt_emp->bind_param('i', $project_id);
                if (!$stmt_emp->execute()) {
                    $stmt_emp->close();
                    throw new Exception('Failed to update employee statuses: ' . $stmt_emp->error);
                }
                $stmt_emp->close();

                // 2. Get all equipment assigned to this project that are 'In use'
                $equipment_query = "SELECT equipment_id FROM project_add_equipment 
                                  WHERE project_id = ? AND status = 'In use'";
                $stmt_eq = $con->prepare($equipment_query);
                if (!$stmt_eq) {
                    throw new Exception('Failed to prepare equipment query: ' . $con->error);
                }
                $stmt_eq->bind_param('i', $project_id);
                if (!$stmt_eq->execute()) {
                    $stmt_eq->close();
                    throw new Exception('Failed to execute equipment query: ' . $stmt_eq->error);
                }
                $equipment_result = $stmt_eq->get_result();
                $equipment_ids = [];
                while ($row = $equipment_result->fetch_assoc()) {
                    $equipment_ids[] = $row['equipment_id'];
                }
                $stmt_eq->close();

                // 3. Update project equipment status to 'Returned'
                $update_project_equipment = "UPDATE project_add_equipment SET status = 'returned' 
                                         WHERE project_id = ? AND status = 'In use'";
                $stmt_pe = $con->prepare($update_project_equipment);
                if (!$stmt_pe) {
                    throw new Exception('Failed to prepare project equipment update: ' . $con->error);
                }
                $stmt_pe->bind_param('i', $project_id);
                if (!$stmt_pe->execute()) {
                    $stmt_pe->close();
                    throw new Exception('Failed to update project equipment statuses: ' . $stmt_pe->error);
                }
                $stmt_pe->close();

                // 4. Update main equipment table status to 'Available' for all returned equipment
                if (!empty($equipment_ids)) {
                    $placeholders = implode(',', array_fill(0, count($equipment_ids), '?'));
                    $update_equipment = "UPDATE equipment SET status = 'Available' 
                                       WHERE id IN ($placeholders)";
                    $stmt_e = $con->prepare($update_equipment);
                    if (!$stmt_e) {
                        throw new Exception('Failed to prepare equipment status update: ' . $con->error);
                    }
                    // Bind parameters dynamically
                    $types = str_repeat('i', count($equipment_ids));
                    $stmt_e->bind_param($types, ...$equipment_ids);
                    if (!$stmt_e->execute()) {
                        $stmt_e->close();
                        throw new Exception('Failed to update equipment statuses: ' . $stmt_e->error);
                    }
                    $stmt_e->close();
                }

                // Get project details and calculate total expenses
                $project_query = mysqli_query($con, "SELECT project FROM projects WHERE project_id='$project_id'");
                $project_data = mysqli_fetch_assoc($project_query);
                $project_name = mysqli_real_escape_string($con, $project_data['project']);
                
                // Calculate total expenses (materials + equipment)
                $mat_total = 0;
                $equip_total = 0;
                
                // Get materials total (joining project_add_materials with materials table to get labor_other)
                $mat_query = "
                   SELECT SUM(
                    (pam.material_price * pam.quantity) + pam.additional_cost + (COALESCE(m.labor_other, 0) * pam.quantity)
                    ) as total 
                    FROM project_add_materials pam
                    JOIN materials m ON pam.material_id = m.id 
                    WHERE pam.project_id=?
                ";
                $mat_stmt = $con->prepare($mat_query);
                if ($mat_stmt === false) {
                    throw new Exception('Failed to prepare materials query: ' . $con->error);
                }
                $mat_stmt->bind_param('i', $project_id);
                if (!$mat_stmt->execute()) {
                    throw new Exception('Failed to execute materials query: ' . $mat_stmt->error);
                }
                $mat_result = $mat_stmt->get_result();
                $mat_row = $mat_result->fetch_assoc();
                $mat_total = floatval($mat_row['total'] ?? 0);
                $mat_stmt->close();
                

                
                // Get equipment total
                $equip_query = "SELECT SUM(total) as total FROM project_add_equipment WHERE project_id=?";
                $equip_stmt = $con->prepare($equip_query);
                if ($equip_stmt === false) {
                    throw new Exception('Failed to prepare equipment query: ' . $con->error);
                }
                $equip_stmt->bind_param('i', $project_id);
                if (!$equip_stmt->execute()) {
                    throw new Exception('Failed to execute equipment query: ' . $equip_stmt->error);
                }
                $equip_result = $equip_stmt->get_result();
                $equip_row = $equip_result->fetch_assoc();
                $equip_total = floatval($equip_row['total'] ?? 0);
                $equip_stmt->close();
                
                // Debug: Log equipment total
                error_log("Equipment Total: " . $equip_total);
                error_log("Equipment Query: " . $equip_query);
                
                $total_expenses = $mat_total + $equip_total;
                
                // Debug: Log final total
                error_log("Final Expenses Total: " . $total_expenses);
                
                // Insert expense record
                $expense_date = date('Y-m-d');
                $expense_category = 'Project';
                $description = 'Finished Project';
                $project_name = $project_data['project'];
                
                $expense_query = "INSERT INTO expenses (user_id, expense, expensedate, project_name, expensecategory, description) 
                                VALUES (?, ?, ?, ?, ?, ?)";
                $expense_stmt = $con->prepare($expense_query);
                $expense_stmt->bind_param('idssss', $userid, $total_expenses, $expense_date, $project_name, $expense_category, $description);
                
                if (!$expense_stmt->execute()) {
                    throw new Exception('Failed to create expense record');
                }
                
                $expense_stmt->close();
                
                // Calculate profit/loss (total_contract_amount - total expenses, can be negative for loss)
                $profit_loss = $project_data['total_contract_amount'] - ($mat_total + $equip_total);
                
                // Insert the calculated profit/loss
                $profit_query = "
                    INSERT INTO project_profits (project_id, project_name, profit)
                    SELECT 
                        project_id,
                        project as project_name,
                        ? as profit
                    FROM projects 
                    WHERE project_id = ?
                ";
                
                $profit_stmt = $con->prepare($profit_query);
                if (!$profit_stmt) {
                    throw new Exception('Failed to prepare profit calculation: ' . $con->error);
                }
                
                // Bind parameters - profit_loss (double) and project_id (integer)
                $profit_stmt->bind_param('di', $profit_loss, $project_id);
                
                if (!$profit_stmt->execute()) {
                    $error = $profit_stmt->error;
                    $profit_stmt->close();
                    throw new Exception('Failed to save project profit: ' . $error);
                }
                $profit_stmt->close();
                
                // Send notification to client
                $projectStmt = $con->prepare("SELECT user_id, project, client_email FROM projects WHERE project_id = ?");
                $projectStmt->bind_param("i", $project_id);
                $projectStmt->execute();
                $projectResult = $projectStmt->get_result();
                
                if ($projectRow = $projectResult->fetch_assoc()) {
                    $user_id = $projectRow['user_id'];
                    $project_name = $projectRow['project'];
                    $client_email = $projectRow['client_email'];
                    
                    if (!empty($client_email)) {
                        $notifType = 'project_finished';
                        $message = "The project '$project_name' has been marked as finished. Thank you for your business!";
                        
                        $notifStmt = $con->prepare("INSERT INTO notifications_client (user_id, client_email, notif_type, message) VALUES (?, ?, ?, ?)");
                        $notifStmt->bind_param("isss", $user_id, $client_email, $notifType, $message);
                        if (!$notifStmt->execute()) {
                            error_log("Failed to send client notification: " . $notifStmt->error);
                        }
                        $notifStmt->close();
                    }
                }
                $projectStmt->close();
            }
            
            // Commit transaction
            $con->commit();
            
            // Redirect with success message
            header("Location: project_actual.php?id=$project_id&status_updated=1&new_status=" . strtolower($status));
        } catch (Exception $e) {
            // Rollback transaction on error
            $con->rollback();
            // Redirect with error message
            header("Location: project_actual.php?id=$project_id&error=update_failed&message=" . urlencode($e->getMessage()));
        }
        
        if (isset($stmt)) $stmt->close();
        exit();
    }
}

// Handle project deletion
if (isset($_GET['delete'])) {
    // Delete the project
    mysqli_query($con, "DELETE FROM projects WHERE project_id='$project_id' AND user_id='$userid'") 
        or die(mysqli_error($con));
    
    // Redirect to project list
    header("Location: project_list.php?deleted=1");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_project_material'])) {
  $row_id = intval($_POST['row_id']);
  $project_id = intval($_GET['id']);
  $return_quantity = isset($_POST['return_quantity']) ? intval($_POST['return_quantity']) : 0;
  
  if ($return_quantity <= 0) {
      header("Location: project_actual.php?id=$project_id&return_error=Invalid+return+quantity");
      exit();
  }
  
  // Start transaction
  mysqli_begin_transaction($con);
  
  try {
      // Get the current material details with FOR UPDATE to lock the row
      $mat_query = mysqli_query($con, "SELECT material_id, quantity, material_name FROM project_add_materials WHERE id='$row_id' AND project_id='$project_id' FOR UPDATE");
      if ($mat_row = mysqli_fetch_assoc($mat_query)) {
          $material_id = intval($mat_row['material_id']);
          $material_name = mysqli_real_escape_string($con, $mat_row['material_name']);
          $current_quantity = intval($mat_row['quantity']);
          
          if ($return_quantity > $current_quantity) {
              throw new Exception("Return quantity cannot be greater than current quantity");
          }
          
          // Add back the returned quantity to the main materials table
          mysqli_query($con, "UPDATE materials SET quantity = quantity + $return_quantity WHERE id = '$material_id'");
          
          // Calculate remaining quantity
          $remaining_quantity = $current_quantity - $return_quantity;
          
          if ($remaining_quantity <= 0) {
              // If no quantity remains, delete the record
              mysqli_query($con, "DELETE FROM project_add_materials WHERE id='$row_id' AND project_id='$project_id'");
          } else {
              // Otherwise, update the quantity
              mysqli_query($con, "UPDATE project_add_materials SET quantity = $remaining_quantity WHERE id='$row_id' AND project_id='$project_id'");
          }
          
          // Commit the transaction
          mysqli_commit($con);
          
          // Redirect with success message
          header("Location: project_actual.php?id=$project_id&return_success=1&material_name=" . urlencode($material_name) . "&quantity=$return_quantity");
          exit();
      } else {
          throw new Exception("Material not found");
      }
  } catch (Exception $e) {
      // Rollback the transaction on error
      mysqli_rollback($con);
      header("Location: project_actual.php?id=$project_id&return_error=" . urlencode($e->getMessage()));
      exit();
  }
}

// Handle additional cost saving
if (isset($_POST['save_additional_cost']) && isset($_POST['add_cost_row_id'])) {
  $row_id = intval($_POST['add_cost_row_id']);
  $additional_cost = floatval($_POST['additional_cost']);
  $project_id = intval($_GET['id']);
  
  try {
      // Get material name for success message
      $mat_query = mysqli_query($con, "SELECT material_name FROM project_add_materials WHERE id='$row_id'");
      $mat_row = mysqli_fetch_assoc($mat_query);
      $material_name = mysqli_real_escape_string($con, $mat_row['material_name']);
      
      // Update only the additional_cost column
      $result = mysqli_query($con, "UPDATE project_add_materials SET 
        additional_cost = '$additional_cost'
        WHERE id='$row_id'");
        
      if ($result) {
          // Redirect with success message
          header("Location: project_actual.php?id=$project_id&cost_success=1&material_name=" . urlencode($material_name) . "#materials");
      } else {
          throw new Exception("Failed to update additional cost: " . mysqli_error($con));
      }
  } catch (Exception $e) {
      header("Location: project_actual.php?id=$project_id&cost_error=" . urlencode($e->getMessage()) . "#materials");
  }
  exit();
}

// Fetch unique units for dropdown
$units_result = mysqli_query($con, "SELECT DISTINCT unit FROM materials WHERE unit IS NOT NULL AND unit != '' ORDER BY unit ASC");
$units = [];
while ($row = mysqli_fetch_assoc($units_result)) {
    $units[] = $row['unit'];
}

// Fetch materials for dropdown
$materials_result = mysqli_query($con, "SELECT * FROM materials WHERE status = 'Available' ORDER BY material_name ASC");
$materials = [];
while ($row = mysqli_fetch_assoc($materials_result)) {
    $materials[] = $row;
}

// Fetch all available employees for dropdown
$employees_result = mysqli_query($con, "SELECT e.employee_id, e.first_name, e.last_name, e.contact_number, p.title as position_title, p.daily_rate FROM employees e LEFT JOIN positions p ON e.position_id = p.position_id WHERE e.user_id='$userid' AND LOWER(p.title) != 'foreman' ORDER BY e.last_name, e.first_name");
$employees = [];
while ($row = mysqli_fetch_assoc($employees_result)) {
    $employees[] = $row;
}

// Fetch project employees and calculate total
$proj_emps = [];
$emp_total = 0;
$emp_query = mysqli_query($con, "SELECT pae.*, e.first_name, e.last_name, p.title, p.daily_rate 
    FROM project_add_employee pae 
    LEFT JOIN employees e ON pae.employee_id = e.employee_id 
    LEFT JOIN positions p ON e.position_id = p.position_id 
    WHERE pae.project_id = '$project_id'");
while ($row = mysqli_fetch_assoc($emp_query)) {
    $proj_emps[] = $row;
    $emp_total += floatval($row['total']);
}
// Fetch project materials
$proj_mats = [];
$mat_total = 0;
$material_labor_total = 0;
$mat_query = mysqli_query($con, "SELECT pam.*, m.supplier_name, m.material_price, m.labor_other, m.unit, m.material_name FROM project_add_materials pam LEFT JOIN materials m ON pam.material_id = m.id WHERE pam.project_id = '$project_id'");
while ($row = mysqli_fetch_assoc($mat_query)) {
    // The total already includes the additional_cost in the database
    $proj_mats[] = $row;
    $mat_total += ($row['labor_other'] + $row['material_price']) * $row['quantity'] + $row['additional_cost'];
    $material_labor_total += $row['labor_other'] * $row['quantity'];
}
// Fetch project equipments
$proj_equipments = [];
$equip_total = 0;
$equip_query = mysqli_query($con, "SELECT pae.*, e.equipment_name, e.location, e.equipment_price AS price, e.depreciation, e.status as equipment_status FROM project_add_equipment pae LEFT JOIN equipment e ON pae.equipment_id = e.id WHERE pae.project_id = '$project_id'");
while ($row = mysqli_fetch_assoc($equip_query)) {
    // Only add to total if equipment is not damaged
    $status = strtolower(($row['status'] ?? $row['equipment_status'] ?? ''));
    if ($status !== 'damaged' && $status !== 'damage') {
        $equip_total += floatval($row['total']);
    }
    $proj_equipments[] = $row;
}
$final_labor_cost = $material_labor_total- $emp_total;
$grand_total =  $mat_total + $equip_total;

// Initialize division progress for chart
$div_chart_labels = [];
$div_chart_data = [];

// Try to fetch division progress if the table exists
try {
    $div_chart_query = mysqli_query($con, "SHOW TABLES LIKE 'project_divisions'");
    if (mysqli_num_rows($div_chart_query) > 0) {
        $div_chart_query = mysqli_query($con, "SELECT division_name, progress FROM project_divisions WHERE project_id='$project_id'");
        if ($div_chart_query) {
            while ($row = mysqli_fetch_assoc($div_chart_query)) {
                $div_chart_labels[] = $row['division_name'];
                $div_chart_data[] = (int)$row['progress'];
            }
        }
    }
} catch (Exception $e) {
    // Table doesn't exist or other error occurred
    error_log("Error fetching division progress: " . $e->getMessage());
}

// Calculate remaining days
$today = new DateTime();
$deadline = new DateTime($project['deadline']);
$interval = $today->diff($deadline);
$remaining_days = $interval->format('%r%a'); // %r shows negative sign if deadline has passed

// Calculate overall project progress (average of all divisions)
$overall_progress = 0;
if (count($div_chart_data) > 0) {
    $avg = array_sum($div_chart_data) / count($div_chart_data);
    $all_full = (min($div_chart_data) == 100);
    $overall_progress = $all_full ? 100 : floor($avg);
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
    <link rel="stylesheet" href="style.css" />
    <title>Project Actual</title>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div class="bg-white" id="sidebar-wrapper">
        <div class="user text-center py-4">
                <img class="img img-fluid rounded-circle mb-2 sidebar-profile-img" src="<?php echo isset($userprofile) ? $userprofile : (isset($_SESSION['userprofile']) ? $_SESSION['userprofile'] : '../uploads/default_profile.png'); ?>" width="70" alt="User Profile">
                <h5 class="mb-1 text-white"><?php echo htmlspecialchars($user_name); ?></h5>
                <p class="text-white small mb-0 text wh"><?php echo htmlspecialchars($user_email); ?></p>
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
            </div>
        </div>
        <!-- /#sidebar-wrapper -->

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-align-left primary-text fs-4 me-3" id="menu-toggle"></i>
                    <h2 class="fs-2 m-0">Project Actual</h2>
                </div>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <?php include 'pm_notification.php'; ?>
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
            <!-- START CARD WRAPPER -->
            <div class="card shadow-sm mb-4">
              <div class="card-header bg-success text-white d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Project Actual Details</h4>
                <div class="d-flex gap-2">
                  <?php
                  // Update project progress to 7 (Schedule) in the database
                  if (isset($project_id) && $project_id) {
                      $update_query = "UPDATE projects SET step_progress = 6, progress_indicator = 6 WHERE project_id = ? AND user_id = ?";
                      $stmt = $con->prepare($update_query);
                      $stmt->bind_param("ii", $project_id, $userid);
                      $stmt->execute();
                  }
                  ?>
                  <a href="project_process_v2.php?project_id=<?php echo $project_id; ?>" class="btn btn-light btn-sm">
                    <i class="fa fa-arrow-left"></i> View Sechedule
                  </a>
                  <a href="#" class="btn btn-danger btn-sm" id="exportProjectPdfBtn">
                    <i class="fas fa-file-export"></i> Generate
                  </a>
                </div>
              </div>
              <div class="card-body">
                <?php if (isset($_GET['status_updated']) && isset($_GET['new_status'])): ?>
                  <!-- Status Update Success Modal -->
                  <div class="modal fade" id="statusUpdateSuccessModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                      <div class="modal-content">
                        <div class="modal-body text-center p-4">
                          <div class="text-success mb-3">
                            <i class="fas fa-check-circle" style="font-size: 4rem;"></i>
                          </div>
                          <h4 class="mb-3">Project Status Updated</h4>
                          <p class="mb-4">The project has been marked as <span id="updatedStatus" class="fw-bold"><?php echo htmlspecialchars(ucfirst($_GET['new_status']), ENT_QUOTES); ?></span> successfully!</p>
                          <button type="button" class="btn btn-success" data-bs-dismiss="modal">
                            <i class="fas fa-check me-2"></i>OK
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                  <script>
                    document.addEventListener('DOMContentLoaded', function() {
                      const successModal = new bootstrap.Modal(document.getElementById('statusUpdateSuccessModal'));
                      successModal.show();
                      
                      // Remove status update parameters from URL without refreshing the page
                      if (window.history.replaceState) {
                        const url = new URL(window.location);
                        url.searchParams.delete('status_updated');
                        url.searchParams.delete('new_status');
                        window.history.replaceState({}, '', url);
                      }
                      
                      // Also handle modal close event to prevent showing again if user navigates back
                      document.getElementById('statusUpdateSuccessModal').addEventListener('hidden.bs.modal', function () {
                        if (window.history.replaceState) {
                          const url = new URL(window.location);
                          url.searchParams.delete('status_updated');
                          url.searchParams.delete('new_status');
                          window.history.replaceState({}, '', url);
                        }
                      });
                    });
                  </script>
                <?php endif; ?>
                <?php if (isset($_GET['error'])): ?>
                  <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> Failed to update project status. Please try again.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>
                <?php endif; ?>
                <div class="row">
                  <div class="col-md-6">
                    <!-- Project Information Card -->
                    <div class="card mb-4 shadow-sm">
                      <div class="card-header bg-success text-white d-flex align-items-center">
                        <h5 class="mb-0 flex-grow-1">Project Information</h5>
                        <div class="d-flex gap-2 ms-auto">
                          <button type="button" class="btn btn-light btn-sm <?php echo ($project['status'] === 'Finished') ? 'disabled' : ''; ?>" 
                            data-bs-toggle="modal" data-bs-target="#editProjectInfoModal" 
                            <?php echo ($project['status'] === 'Finished') ? 'disabled' : ''; ?>>
                            <i class="fas fa-edit me-1"></i> Edit Project
                          </button>
                          <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#viewPermitsModal">
                            <i class="fas fa-file-alt me-1"></i> View Permits
                          </button>
                        </div>
                      </div>
                      <div class="card-body">
                        <!-- First Row -->
                        <div class="row mb-4">
                          <!-- Project Details -->
                          <div class="col-md-6">
                            <div class="bg-light p-3 rounded h-100">
                              <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-info-circle text-success me-2"></i>Project Details</h6>
                              <div class="mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                  <span class="text-muted">Project Name:</span>
                                  <span class="fw-bold"><?php echo htmlspecialchars($project['project']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                  <span class="text-muted">Location:</span>
                                  <span><?php echo htmlspecialchars($project['location']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                  <span class="text-muted">Category:</span>
                                  <span class="text-uppercase"><?php echo htmlspecialchars($project['category']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                  <span class="text-muted">Foreman:</span>
                                  <span class="fw-bold"><?php echo !empty($project['foreman_name']) ? htmlspecialchars($project['foreman_name']) : 'Not Assigned'; ?></span>
                                </div>
                              </div>
                            </div>
                          </div>
                          
                          <!-- Financial Summary -->
                          <div class="col-md-6">
                            <div class="bg-light p-3 rounded h-100">
                              <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-chart-line text-success me-2"></i>Financial Summary</h6>
                              <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Budget:</span>
                                <span class="fw-bold text-success">₱<?php echo number_format($project['budget'], 2); ?></span>
                              </div>
                              <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Grand Total:</span>
                                <span class="fw-bold">₱<?php echo number_format($mat_total + $equip_total, 2); ?></span>
                              </div>
                              <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Profit/Loss:</span>
                                <?php 
                                $profit_loss = $project['budget'] - ($mat_total + $equip_total);
                                $profit_class = $profit_loss >= 0 ? 'text-success' : 'text-danger';
                                $profit_icon = $profit_loss >= 0 ? '▲' : '▼';
                                ?>
                                <span class="fw-bold <?php echo $profit_class; ?>">
                                  <?php echo $profit_icon; ?> ₱<?php echo number_format(abs($profit_loss), 2); ?>
                                  <small class="d-block text-muted">(<?php echo $profit_loss >= 0 ? 'Profit' : 'Loss'; ?>)</small>
                                </span>
                              </div>
                              <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Labor Cost:</span>
                                <span class="fw-bold text-primary">₱<?php echo number_format($final_labor_cost, 2); ?></span>
                </div>            
                              <div class="d-flex justify-content-between mt-5">
                                <span class="text-muted">Status:</span>
                                <?php 
                                    $status = $project['status'] ?? 'In Progress';
                                    $statusClass = 'secondary'; // Default class

                                    // Set different classes based on status
                                    switch(strtolower($status)) {
                                        case 'finished':
                                            $statusClass = 'success';
                                            $icon = 'check-circle';
                                            break;
                                        case 'cancelled':
                                            $statusClass = 'danger';
                                            $icon = 'times-circle';
                                            break;
                                        case 'in progress':
                                            $statusClass = 'primary';
                                            $icon = 'sync-alt';
                                            break;
                                        case 'on hold':
                                            $statusClass = 'warning';
                                            $icon = 'pause-circle';
                                            break;
                                        default:
                                            $icon = 'info-circle';
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?> px-3 py-2 d-inline-flex align-items-center" style="font-size: 1.1rem;">
                                        <i class="fas fa-<?php echo $icon; ?> me-2"></i>
                                        <?php echo ucwords($status); ?>
                                    </span>
                              </div>
                            </div>
                          </div>
                        </div>
                        
                       
                        <!-- Remaining Days Progress -->
                        <div class="bg-light p-3 rounded mb-3">
                          <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0"><i class="fas fa-calendar-day text-warning me-2"></i>Time Remaining</h6>
                            <span class="badge bg-<?php echo $remaining_days >= 0 ? 'success' : 'danger'; ?>">
                              <?php echo $remaining_days; ?> days <?php echo $remaining_days < 0 ? 'overdue' : 'left'; ?>
                            </span>
                          </div>
                          <?php 
                          // Calculate progress percentage
                          $total_days = (new DateTime($project['start_date']))->diff(new DateTime($project['deadline']))->days + 1;
                          $elapsed_days = $total_days - $remaining_days;
                          $days_progress = min(100, max(0, ($elapsed_days / $total_days) * 100));
                          ?>
                          <div class="progress mb-2" style="height: 25px;">
                            <div class="progress-bar bg-<?php echo $remaining_days >= 0 ? 'warning' : 'danger'; ?> progress-bar-striped progress-bar-animated" 
                                 role="progressbar" 
                                 style="width: <?php echo $days_progress; ?>%;" 
                                 aria-valuenow="<?php echo $days_progress; ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                              <?php echo number_format($days_progress, 1); ?>%
                            </div>
                          </div>
                          <div class="d-flex justify-content-between">
                            <small class="text-muted">Start: <?php echo date("M d, Y", strtotime($project['start_date'])); ?></small>
                            <small class="text-muted">Deadline: <?php echo date("M d, Y", strtotime($project['deadline'])); ?></small>
                          </div>
                        </div>

                        <!-- Project Status -->
                        <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded">
                          <div>
                            <span class="badge bg-<?php echo $remaining_days >= 0 ? 'success' : 'danger'; ?> me-2">
                              <?php echo $remaining_days >= 0 ? 'Active' : 'Overdue'; ?>
                            </span>
                          </div>
                          <div class="text-muted small">
                            Project ID: <?php echo $project_id; ?>
                          </div>
                        </div>
                          </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <!-- Division Progress Chart Card -->
                    <div class="card mb-4 shadow-sm">
                      <div class="card-header bg-success text-white d-flex align-items-center">
                        <h5 class="mb-0 flex-grow-1">Task Progress</h5>
                        <a href="project_progress.php?id=<?php echo $project_id; ?>" class="btn btn-light btn-sm ml-auto"><i class="fas fa-angle-double-right me-1"></i> Show more</a>
                      </div>
                      <div class="card-body">
                        <?php
                        // Fetch tasks from project_timeline
                        $task_query = "SELECT id, task_name, progress, start_date, end_date FROM project_timeline 
                                     WHERE project_id = ? ORDER BY start_date ASC";
                        $stmt = $con->prepare($task_query);
                        $stmt->bind_param("i", $project_id);
                        $stmt->execute();
                        $tasks_result = $stmt->get_result();
                        $tasks = [];
                        $task_names = [];
                        $task_progress = [];
                        $total_progress = 0;
                        $task_count = 0;
                        
                        if ($tasks_result && $tasks_result->num_rows > 0) {
                            while ($task = $tasks_result->fetch_assoc()) {
                                $tasks[] = $task;
                                $task_names[] = $task['task_name'];
                                $task_progress[] = $task['progress'];
                                $total_progress += $task['progress'];
                                $task_count++;
                            }
                            $overall_progress = $task_count > 0 ? round($total_progress / $task_count) : 0;
                            
                            // Prepare data for chart
                            $chart_labels = json_encode($task_names);
                            $chart_data = json_encode($task_progress);
                        } else {
                            $overall_progress = 0;
                            echo '<div class="alert alert-info">No tasks found for this project.</div>';
                        }
                        ?>
                        
                        <!-- Task Progress Chart -->
                        <div class="mb-4" style="height: 300px;">
                            <canvas id="taskProgressChart"></canvas>
                        </div>
                        
                        <?php if (!empty($tasks)): ?>
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const ctx = document.getElementById('taskProgressChart').getContext('2d');
                            const taskProgressChart = new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: <?php echo $chart_labels; ?>,
                                    datasets: [{
                                        label: 'Task Progress (%)',
                                        data: <?php echo $chart_data; ?>,
                                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                                        borderColor: 'rgba(40, 167, 69, 1)',
                                        borderWidth: 1,
                                        borderRadius: 4
                                    }]
                                },
                                options: {
                                    indexAxis: 'y',
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    scales: {
                                        x: {
                                            beginAtZero: true,
                                            max: 100,
                                            title: {
                                                display: true,
                                                text: 'Progress (%)',
                                                font: {
                                                    weight: 'bold'
                                                }
                                            },
                                            grid: {
                                                display: false
                                            }
                                        },
                                        y: {
                                            grid: {
                                                display: false
                                            },
                                            ticks: {
                                                autoSkip: false
                                            }
                                        }
                                    },
                                    plugins: {
                                        legend: {
                                            display: false
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    return context.raw + '%';
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                        });
                        </script>
                        <?php endif; ?>
                        
                        <!-- Overall Progress -->
                        <div class="bg-light p-3 rounded mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0"><i class="fas fa-tasks text-success me-2"></i>Overall Progress</h6>
                                <span class="badge bg-success rounded-pill"><?php echo count($tasks); ?> tasks</span>
                            </div>
                            <div class="progress mb-2" style="height: 25px;">
                                <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" 
                                     role="progressbar" 
                                     style="width: <?php echo $overall_progress; ?>%"
                                     aria-valuenow="<?php echo $overall_progress; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                    <?php echo $overall_progress; ?>%
                                </div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">
                                    <i class="far fa-calendar-alt me-1"></i> 
                                    Start: <?php echo date("M d, Y", strtotime($project['start_date'])); ?>
                                </small>
                                <small class="text-muted">
                                    <i class="far fa-calendar-check me-1"></i>
                                    Deadline: <?php echo date("M d, Y", strtotime($project['deadline'])); ?>
                                </small>
                            </div>
                        </div>
                        
                        <p class="mb-0"><i class="fas fa-info-circle text-primary me-1"></i> Showing progress of all tasks in the project timeline.</p>
                      </div>
                    </div>
                    
                  </div>
                </div>

                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs mt-4" id="projectTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="employees-tab" data-bs-toggle="tab" data-bs-target="#employees" type="button" role="tab" aria-controls="employees" aria-selected="true">
                            <i class="fas fa-users me-1"></i> Employees
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="materials-tab" data-bs-toggle="tab" data-bs-target="#materials" type="button" role="tab" aria-controls="materials" aria-selected="false">
                            <i class="fas fa-boxes me-1"></i> Project Materials
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="equipment-tab" data-bs-toggle="tab" data-bs-target="#equipment" type="button" role="tab" aria-controls="equipment" aria-selected="false">
                            <i class="fas fa-tools me-1"></i> Project Equipment
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content p-3 border border-top-0 rounded-bottom" id="projectTabsContent">
                    <!-- Employees Tab -->
                    <div class="tab-pane fade show active" id="employees" role="tabpanel" aria-labelledby="employees-tab">
                        <div class="card shadow-sm">
                            <div class="card-header bg-success text-white d-flex align-items-center">
                                <span class="flex-grow-1">Project Team</span>
                                <button class="btn btn-light btn-sm ms-auto <?php echo ($project['status'] === 'Finished') ? 'disabled' : ''; ?>" 
                                    data-bs-toggle="modal" data-bs-target="#addEmployeeModal"
                                    <?php echo ($project['status'] === 'Finished') ? 'disabled' : ''; ?>>
                                    <i class="fas fa-user-plus me-1"></i> Add Employee
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0">
                                        <thead class="table-secondary">
                                            <tr>
                                                <th>No.</th>
                                                <th>Name</th>
                                                <th>Position</th>
                                                <th>Employee Type</th>
                                                <th>Daily Rate</th>
                                                <th>Project Days</th>
                                                <th>Total</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Compute project days once
                                            $start_date = $project['start_date'];
                                            $end_date = $project['deadline'];
                                            $start = new DateTime($start_date);
                                            $end = new DateTime($end_date);
                                            $interval = $start->diff($end);
                                            $project_days = $interval->days + 1;
                                            
                                            // Fetch project employees
                                            $proj_emps = [];
                                            $emp_query = mysqli_query($con, "SELECT pae.*, e.first_name, e.last_name, e.contact_number, e.company_type, e.position_id, p.title as position_title, p.daily_rate 
                                                                        FROM project_add_employee pae 
                                                                        JOIN employees e ON pae.employee_id = e.employee_id 
                                                                        LEFT JOIN positions p ON e.position_id = p.position_id 
                                                                        WHERE pae.project_id = '$project_id'");
                                            while ($row = mysqli_fetch_assoc($emp_query)) {
                                                $proj_emps[] = $row;
                                            }
                                            
                                            if (count($proj_emps) > 0): 
                                                $i = 1; 
                                                $emp_total = 0;
                                                foreach ($proj_emps as $emp): 
                                                    $emp_total += $emp['daily_rate'] * $project_days;
                                            ?>
                                            <tr>
                                                <td><?php echo $i++; ?></td>
                                                <td style="font-weight:bold;color:#222;"><?php echo htmlspecialchars(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')); ?></td>
                                                <td><?php echo htmlspecialchars($emp['position_title'] ?? $emp['position']); ?></td>
                                                <td><?php echo htmlspecialchars($emp['company_type']); ?></td>
                                                <td>₱<?php echo number_format($emp['daily_rate'], 2); ?></td>
                                                <td><?php echo $project_days; ?></td>
                                                <td style="font-weight:bold;color:#222;">₱<?php echo number_format($emp['daily_rate'] * $project_days, 2); ?></td>
                                                <td>
                                                    <form method="post" style="display:inline;">
                                                        <input type="hidden" name="row_id" value="<?php echo $emp['id']; ?>">
                                                        <button type="submit" name="remove_project_employee" class="btn btn-sm btn-danger <?php echo ($project['status'] === 'Finished') ? 'disabled' : ''; ?>" 
                                                            <?php echo ($project['status'] === 'Finished') ? 'disabled' : ''; ?> 
                                                            onclick="return confirm('Remove this employee?')">
                                                            <i class="fas fa-trash"></i> Remove
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php 
                                                endforeach; 
                                            else: 
                                            ?>
                                            <tr>
                                                <td colspan="7" class="text-center">No employees added</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="5" class="text-right">Total</th>
                                                <th colspan="2" style="font-weight:bold;color:#222;">₱<?php echo isset($emp_total) ? number_format($emp_total, 2) : '0.00'; ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Project Materials Tab -->
                    <div class="tab-pane fade" id="materials" role="tabpanel" aria-labelledby="materials-tab">
                        <div class="card mb-3 shadow-sm mt-3">
                            <div class="card-header bg-success text-white d-flex align-items-center">
                                <span class="flex-grow-1">Project Materials</span>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-light btn-sm <?php echo ($project['status'] === 'Finished') ? 'disabled' : ''; ?>" 
                                        data-bs-toggle="modal" data-bs-target="#addMaterialsModal"
                                        <?php echo ($project['status'] === 'Finished') ? 'disabled' : ''; ?>>
                                        <i class="fas fa-plus-square me-1"></i> Add Materials
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0">
                                        <thead class="table-secondary">
                                            <tr>
                                                <th>No.</th>
                                                <th>Name</th>
                                                <th>Unit</th>
                                                <th>Material Price</th>
                                                <th>Labor/Other</th>
                                                <th>Quantity</th>
                                                <th>Supplier</th>
                                                <th>Additional Cost</th>
                                                <th>Total</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($proj_mats) > 0): $i = 1; foreach ($proj_mats as $mat): ?>
                                            <tr>
                                                <td><?php echo $i++; ?></td>
                                                <td style="font-weight:bold;color:#222;"><?php echo htmlspecialchars($mat['material_name']); ?></td>
                                                <td><?php echo htmlspecialchars($mat['unit']); ?></td>
                                                <td><?php echo number_format($mat['material_price'], 2); ?></td>
                                                <td><?php echo number_format($mat['labor_other'], 2); ?></td>
                                                <td><?php echo $mat['quantity']; ?></td>
                                                <td><?php echo isset($mat['supplier_name']) && $mat['supplier_name'] ? htmlspecialchars($mat['supplier_name']) : 'N/A'; ?></td>
                                                <td>
                                                    <?php $add_cost = isset($mat['additional_cost']) ? floatval($mat['additional_cost']) : 0; ?>
                                                    <span class="text-primary">₱<?php echo number_format($add_cost, 2); ?></span>
                                                    <button type="button" class="btn btn-link btn-sm p-0 ms-1 <?php echo ($project['status'] === 'Finished') ? 'disabled' : ''; ?>" 
                                                        data-bs-toggle="modal" data-bs-target="#addCostModal<?php echo $mat['id']; ?>" 
                                                        title="Add/Edit Additional Cost"
                                                        <?php echo ($project['status'] === 'Finished') ? 'disabled' : ''; ?>>
                                                        <i class="fas fa-plus-circle"></i>
                                                    </button>
                                                </td>
                                                <td style="font-weight:bold;color:#222;">₱<?php 
                                                    $row_total = (($mat['labor_other'] + $mat['material_price']) * $mat['quantity']) + $mat['additional_cost'];
                                                    echo number_format($row_total, 2); 
                                                ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-warning <?php echo ($project['status'] === 'Finished') ? 'disabled' : ''; ?>" 
                                                        data-bs-toggle="modal" data-bs-target="#returnMaterialModal" 
                                                        data-row-id="<?php echo $mat['id']; ?>" 
                                                        data-max-qty="<?php echo $mat['quantity']; ?>"
                                                        <?php echo ($project['status'] === 'Finished') ? 'disabled' : ''; ?>>
                                                        <i class="fas fa-undo"></i> Return
                                                    </button>
                                                </td>
                                            </tr>
                                            <!-- Add/Edit Additional Cost Modal -->
                                            <div class="modal fade" id="addCostModal<?php echo $mat['id']; ?>" tabindex="-1" aria-labelledby="addCostModalLabel<?php echo $mat['id']; ?>" aria-hidden="true">
                                              <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                  <form method="post">
                                                    <div class="modal-header">
                                                      <h5 class="modal-title" id="addCostModalLabel<?php echo $mat['id']; ?>">Add/Edit Additional Cost</h5>
                                                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                      <input type="hidden" name="add_cost_row_id" value="<?php echo $mat['id']; ?>">
                                                      <div class="form-group mb-3">
                                                        <label for="additionalCostInput<?php echo $mat['id']; ?>">Additional Cost (₱)</label>
                                                        <input type="number" step="0.01" min="0" class="form-control" id="additionalCostInput<?php echo $mat['id']; ?>" name="additional_cost" value="<?php echo $add_cost; ?>" required>
                                                        <div class="form-text">Enter any extra cost incurred for this material (e.g. rush fee, extra delivery, etc).</div>
                                                      </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                      <button type="submit" name="save_additional_cost" class="btn btn-success">Save</button>
                                                    </div>
                                                  </form>
                                                </div>
                                              </div>
                                            </div>
                                            <?php endforeach; else: ?>
                                            <tr><td colspan="10" class="text-center">No materials added</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="8" class="text-right">Total</th>
                                                <th colspan="2" style="font-weight:bold;color:#222;">₱<?php
                                                    $total_cost = 0;
                                                    foreach ($proj_mats as $mat) {
                                                       $row_total = (($mat['labor_other'] + $mat['material_price']) * $mat['quantity']) + $mat['additional_cost'];
                                                       $total_cost += $row_total;
                                                    }
                                                    echo number_format($total_cost, 2);
                                                ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Project Equipment Tab (Placeholder) -->
                    <div class="tab-pane fade" id="equipment" role="tabpanel" aria-labelledby="equipment-tab">
                        <div class="card shadow-sm">
                            <div class="card-header bg-success text-white d-flex align-items-center">
                                <span class="flex-grow-1">Project Equipment</span>
                                <button class="btn btn-light btn-sm ml-auto <?php echo ($project['status'] === 'Finished') ? 'disabled' : ''; ?>" 
                                    data-bs-toggle="modal" data-bs-target="#addEquipmentModal"
                                    <?php echo ($project['status'] === 'Finished') ? 'disabled' : ''; ?>>
                                    <i class="fas fa-plus-square me-1"></i> Add Equipment
                                </button>
                            </div>
                            <div class="card-body p-0">
                              <div class="table-responsive">
                                <table class="table table-bordered mb-0">
                                  <thead class="table-secondary">
                                    <tr>
                                      <th>No.</th>
                                      <th>Name</th>
                                      <th>Location</th>
                                      <th>Price</th>
                                      <th>Depreciation</th>
                                      <th>Project Days</th>
                                      <th>Total</th>
                                      <th>Action</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                              <?php
                                // Compute project days once (already computed above, reuse if possible)
                                $start_date = $project['start_date'];
                                $end_date = $project['deadline'];
                                $start = new DateTime($start_date);
                                $end = new DateTime($end_date);
                                $interval = $start->diff($end);
                                $project_days = $interval->days + 1;
                              ?>
                              <?php if (count($proj_equipments) > 0): $i = 1; foreach ($proj_equipments as $eq): ?>
                              <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($eq['equipment_name']); ?></td>
                                <td><?php echo htmlspecialchars($eq['location'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($eq['price'], 2); ?></td>
                                <td>
                                  <?php
                                    if (is_numeric($eq['depreciation'])) {
                                      echo intval($eq['depreciation']) . ' years';
                                    } elseif (!empty($eq['depreciation'])) {
                                      echo htmlspecialchars($eq['depreciation']);
                                    } else {
                                      echo '-';
                                    }
                                  ?>
                                </td>
                                <td><?php echo $project_days; ?></td>
                                <td>
                                  <?php
                                    // Use the stored total value from the database instead of recalculating
                                    echo number_format(floatval($eq['total']), 2);
                                  ?>
                                </td>
                                <td>
                                  <?php if ($eq['status'] === 'damage'): ?>
                                    <span class="badge bg-danger">Damaged</span>
                                  <?php elseif ($eq['status'] === 'returned'): ?>
                                    <span class="badge bg-success">Returned</span>
                                  <?php else: ?>
                                    <?php if ($project['status'] !== 'Finished'): ?>
                                    <form method="post" style="display:inline;">
                                      <input type="hidden" name="row_id" value="<?php echo $eq['id']; ?>">
                                      <button type="submit" name="return_project_equipment" class="btn btn-sm btn-warning" onclick="return confirm('Mark this equipment as returned?')">
                                        <i class="fas fa-undo"></i> Return
                                      </button>
                                    </form>
                                    <form method="post" style="display:inline; margin-left: 4px;">
                                      <input type="hidden" name="report_equipment" value="1">
                                      <input type="hidden" name="report_row_id" value="<?php echo $eq['id']; ?>">
                                      <input type="hidden" name="report_remarks" value="Damage Equipment">
                                      <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Mark this equipment as damaged?')"><i class="fas fa-exclamation-triangle"></i> Report Damage</button>
                                    </form>
                                    <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-warning disabled" disabled><i class="fas fa-undo"></i> Return</button>
                                    <button type="button" class="btn btn-sm btn-danger disabled" disabled><i class="fas fa-exclamation-triangle"></i> Report Damage</button>
                                    <?php endif; ?>
                                  <?php endif; ?>
                                </td>
                              </tr>
                              <?php endforeach; else: ?>
                  
                                <tr><td colspan="8" class="text-center">No equipment added</td></tr>
                              <?php endif; ?>
                            </tbody>
                            <tfoot>
                              <tr>
                                <th colspan="6" class="text-right">Total</th>
                                <th colspan="2" style="font-weight:bold;color:#222;">₱<?php
                                  $equip_total = 0;
                                  foreach ($proj_equipments as $eq) {
                                    // Skip damaged equipment (check both status and equipment_status for compatibility)
                                    $status = strtolower(($eq['status'] ?? $eq['equipment_status'] ?? ''));
                                    if ($status !== 'damaged' && $status !== 'damage') {
                                        $equip_total += floatval($eq['total']);
                                    }
                                  }
                                  echo number_format($equip_total, 2);
                                ?></th>
                              </tr>
                            </tfoot>
                          </table>
                        </div>
                      </div>
                        </div>
                    </div>
           

                </div>
                        <div class="row mt-4">
                        <div class="col-12 text-end">
                            <?php if ($project['status'] !== 'Finished' && $project['status'] !== 'Cancelled'): ?>
                              <button type="button" class="btn btn-danger me-2" id="cancelProjectBtn" data-bs-toggle="modal" data-bs-target="#cancelProjectModal">
                                <i class="fas fa-times-circle"></i> Cancel Project
                              </button>
                              <button type="button" class="btn btn-success me-2" id="finishProjectBtn" data-bs-toggle="modal" data-bs-target="#finishProjectModal">
                                <i class="fas fa-check-circle"></i> Finish Project
                              </button>
                            <?php endif; ?>
                            <?php if ($project['status'] === 'Finished'): ?>
                              <a href="generate_completion_pdf.php?id=<?php echo $project_id; ?>" class="btn btn-primary" target="_blank">
                                <i class="fas fa-file-pdf"></i> Generate Completion Certificate
                              </a>
                            <?php endif; ?>
                    </div>

                    </div>
                </div>                               
              </div>
            </div>

<div class="modal fade" id="returnMaterialModal" tabindex="-1" aria-labelledby="returnMaterialModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="project_actual.php?id=<?php echo $project_id; ?>">
        <input type="hidden" name="return_project_material" value="1">
        <div class="modal-header">
          <h5 class="modal-title" id="returnMaterialModalLabel">Return Material</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="row_id" id="returnMaterialRowId">
          <div class="mb-3">
            <label for="returnMaterialQty" class="form-label">Quantity to return</label>
            <input type="number" class="form-control" id="returnMaterialQty" name="return_quantity" min="1" value="1" required>
            <div class="form-text" id="returnMaterialMaxInfo"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Return</button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- Modals moved outside the card/container for proper Bootstrap modal functionality -->
<?php include 'project_actual_modals.php'; ?>

    <!-- Return Material Modal JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
      var returnModal = document.getElementById('returnMaterialModal');
      var rowIdInput = document.getElementById('returnMaterialRowId');
      var qtyInput = document.getElementById('returnMaterialQty');
      var maxInfo = document.getElementById('returnMaterialMaxInfo');
      
      if (returnModal) {
        returnModal.addEventListener('show.bs.modal', function(event) {
          var button = event.relatedTarget;
          var rowId = button.getAttribute('data-row-id');
          var maxQty = button.getAttribute('data-max-qty');
          
          rowIdInput.value = rowId;
          qtyInput.value = 1;
          qtyInput.max = maxQty;
          maxInfo.textContent = 'Maximum quantity: ' + maxQty;
        });
      }
    });
    </script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
    // Handle employee selection in the add employee modal
    document.addEventListener('DOMContentLoaded', function() {
        const employeeName = document.getElementById('employeeName');
        const employeePosition = document.getElementById('employeePosition');
        const employeeContact = document.getElementById('employeeContact');
        const employeeRate = document.getElementById('employeeRate');
        const employeeTotal = document.getElementById('employeeTotal');
        
        if (employeeName) {
            employeeName.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                employeePosition.value = selectedOption.getAttribute('data-position') || '';
                employeeContact.value = selectedOption.getAttribute('data-contact') || '';
                employeeRate.value = selectedOption.getAttribute('data-rate') || '0.00';
                updateEmployeeTotal();
            });
            
            // Initialize fields if an employee is already selected
            if (employeeName.value) {
                employeeName.dispatchEvent(new Event('change'));
            }
        }
        
        function updateEmployeeTotal() {
            if (employeeRate && employeeTotal) {
                const rate = parseFloat(employeeRate.value) || 0;
                // You can add logic here to calculate total based on days or hours if needed
                employeeTotal.value = rate.toFixed(2);
            }
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
        feather.replace()
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
    // Material auto-fill and total
    var materialName = document.getElementById('materialName');
    var materialUnit = document.getElementById('materialUnit');
    var materialPrice = document.getElementById('materialPrice');
    var laborOther = document.getElementById('laborOther');
    var materialNameText = document.getElementById('materialNameText');
    var materialQty = document.getElementById('materialQty');
    var materialTotal = document.getElementById('materialTotal');
    var addMaterialForm = document.getElementById('addMaterialForm');

    // Initialize values if empty
    if (materialPrice && materialPrice.value === '') materialPrice.value = '0';
    if (laborOther && laborOther.value === '') laborOther.value = '0';

    function updateMaterialTotal() {
      if (!materialQty || !materialPrice || !laborOther || !materialTotal) return;
      
      var qty = parseFloat(materialQty.value) || 0;
      var price = parseFloat(materialPrice.value) || 0;
      var labor = parseFloat(laborOther.value) || 0;
      var total = (price + labor) * qty;
      materialTotal.value = total > 0 ? total.toFixed(2) : '0.00';
    }

    // Handle form submission
    if (addMaterialForm) {
      addMaterialForm.addEventListener('submit', function(e) {
        // Make sure all required fields have values
        if (!materialName.value || !materialQty.value) {
          e.preventDefault();
          alert('Please fill in all required fields');
          return false;
        }
        
        // Make sure materialNameText is set
        if (materialName && materialName.options[materialName.selectedIndex]) {
          var selected = materialName.options[materialName.selectedIndex];
          materialNameText.value = selected.getAttribute('data-name') || '';
        }
        
        return true;
      });
    }

  if (materialName) {
    materialName.addEventListener('change', function() {
      var selected = materialName.options[materialName.selectedIndex];
      materialUnit.value = selected.getAttribute('data-unit') || '';
      materialPrice.value = selected.getAttribute('data-price') || '';
      laborOther.value = selected.getAttribute('data-labor') || '';
      materialNameText.value = selected.getAttribute('data-name') || '';
      updateMaterialTotal();
    });
  }
  if (materialQty) {
    materialQty.addEventListener('input', updateMaterialTotal);
  }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  if (window.location.search.includes('upload_success=1')) {
    setTimeout(function() {
      // Close the modal if open
      var modal = bootstrap.Modal.getInstance(document.getElementById('feedbackModal'));
      if (modal) modal.hide();
      // Refresh the page and remove the query param
      var url = new URL(window.location.href);
      url.searchParams.delete('upload_success');
      window.location.href = url.pathname + url.search;
    }, 1500);
  }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  if (window.location.search.includes('upload_success=1')) {
    setTimeout(function() {
      // Close the modal if open
      var modal = bootstrap.Modal.getInstance(document.getElementById('feedbackModal'));
      if (modal) modal.hide();
      // Refresh the page and remove the query param
      var url = new URL(window.location.href);
      url.searchParams.delete('upload_success');
      window.location.href = url.pathname + url.search;
    }, 1500);
  }
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
// Handle return material modal
document.addEventListener('DOMContentLoaded', function() {
    // Initialize return material modal
    var returnMaterialModal = document.getElementById('returnMaterialModal');
    if (returnMaterialModal) {
        returnMaterialModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var rowId = button.getAttribute('data-row-id');
            var maxQty = button.getAttribute('data-max-qty');
            
            var modal = this;
            modal.querySelector('#return_row_id').value = rowId;
            modal.querySelector('#return_qty').max = maxQty;
            modal.querySelector('#max_qty').textContent = maxQty;
            modal.querySelector('#return_qty').value = '1';
        });
    }

    // Handle return material form submission
    var returnForm = document.querySelector('form[action*="return_material"]');
    if (returnForm) {
        returnForm.addEventListener('submit', function(e) {
            var qty = parseFloat(document.getElementById('return_qty').value);
            var maxQty = parseFloat(document.getElementById('return_qty').max);
            
            if (qty > maxQty) {
                e.preventDefault();
                alert('Return quantity cannot exceed ' + maxQty);
                return false;
            }
            
            if (!confirm('Are you sure you want to return ' + qty + ' item(s) to inventory?')) {
                e.preventDefault();
                return false;
            }
            return true;
        });
    }

    // Material auto-fill and total
    var materialName = document.getElementById('materialName');
    var materialUnit = document.getElementById('materialUnit');
    var materialPrice = document.getElementById('materialPrice');
  var laborOther = document.getElementById('laborOther');
  var materialNameText = document.getElementById('materialNameText');
  var materialQty = document.getElementById('materialQty');
  var materialTotal = document.getElementById('materialTotal');

  function updateMaterialTotal() {
    var qty = parseFloat(materialQty.value) || 0;
    var price = parseFloat(materialPrice.value) || 0;
    var labor = parseFloat(laborOther.value) || 0;
    var total = (price + labor) * qty;
    materialTotal.value = total > 0 ? total.toFixed(2) : '';
  }

  if (materialName) {
    materialName.addEventListener('change', function() {
      var selected = materialName.options[materialName.selectedIndex];
      materialUnit.value = selected.getAttribute('data-unit') || '';
      materialPrice.value = selected.getAttribute('data-price') || '';
      laborOther.value = selected.getAttribute('data-labor') || '';
      materialNameText.value = selected.getAttribute('data-name') || '';
      updateMaterialTotal();
    });
  }
  if (materialQty) {
    materialQty.addEventListener('input', updateMaterialTotal);
  }
});
</script>

<script>
function showFeedbackModal(success, message, error_code = '', query_param = '') {
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
    msg.textContent = message;
  }
  var feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
  feedbackModal.show();
  // Remove the query param after showing the modal
  window.history.replaceState({}, document.title, window.location.pathname + window.location.search.replace(/([&?](addmat|removemat|returnmat|error)=[^&]*)/, ''));
}
document.addEventListener('DOMContentLoaded', function() {
  var params = new URLSearchParams(window.location.search);
  
  // Show success/error message for material return
  if (params.get('return_success') === '1') {
    const materialName = params.get('material_name') || 'Material';
    const quantity = params.get('quantity') || 0;
    showFeedbackModal(true, `Successfully returned ${quantity} unit(s) of ${materialName}`);
    
    // Clean up URL
    params.delete('return_success');
    params.delete('material_name');
    params.delete('quantity');
    window.history.replaceState({}, '', `${window.location.pathname}?${params.toString()}`);
  } else if (params.has('return_error')) {
    const errorMsg = params.get('return_error') || 'An error occurred while returning the material';
    showFeedbackModal(false, errorMsg);
    
    // Clean up URL
    params.delete('return_error');
    window.history.replaceState({}, '', `${window.location.pathname}?${params.toString()}`);
  }
  
  // Show success/error message for additional cost
  if (params.get('cost_success') === '1') {
    const materialName = params.get('material_name') || 'Material';
    showFeedbackModal(true, `Additional cost for ${materialName} has been updated successfully`);
    
    // Clean up URL
    params.delete('cost_success');
    params.delete('material_name');
    window.history.replaceState({}, '', `${window.location.pathname}?${params.toString()}`);
  } else if (params.has('cost_error')) {
    const errorMsg = params.get('cost_error') || 'An error occurred while updating additional cost';
    showFeedbackModal(false, errorMsg);
    
    // Clean up URL
    params.delete('cost_error');
    window.history.replaceState({}, '', `${window.location.pathname}?${params.toString()}`);
  }
  
  // Show success message for material addition
  if (params.get('addmat') === '1') {
    showFeedbackModal(true, 'Material added successfully!');
    params.delete('addmat');
    window.history.replaceState({}, document.title, window.location.pathname + (params.toString() ? '?' + params.toString() : ''));
  } else if (params.get('removemat') === '1') {
    showFeedbackModal(true, 'Material removed successfully!');
    params.delete('removemat');
    window.history.replaceState({}, document.title, window.location.pathname + (params.toString() ? '?' + params.toString() : ''));
  } else if (params.get('addequip') === '1') {
    showFeedbackModal(true, 'Equipment added successfully!');
    params.delete('addequip');
    window.history.replaceState({}, document.title, window.location.pathname + (params.toString() ? '?' + params.toString() : ''));
  } else if (params.get('addemp') === '1') {
    showFeedbackModal(true, 'Employee added to project successfully!');
    params.delete('addemp');
    window.history.replaceState({}, document.title, window.location.pathname + (params.toString() ? '?' + params.toString() : ''));
  } else if (params.get('removeemp') === '1') {
    showFeedbackModal(true, 'Employee removed from project successfully!');
    params.delete('removeemp');
    window.history.replaceState({}, document.title, window.location.pathname + (params.toString() ? '?' + params.toString() : ''));
  }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var exportBtn = document.getElementById('exportProjectPdfBtn');
  if (exportBtn) {
    exportBtn.addEventListener('click', function(e) {
      e.preventDefault();
      var modal = new bootstrap.Modal(document.getElementById('exportProjectPdfModal'));
      modal.show();
    });
  }
  var confirmExportBtn = document.getElementById('confirmExportProjectPdf');
  if (confirmExportBtn) {
    confirmExportBtn.addEventListener('click', function(e) {
      e.preventDefault();
      var modalEl = document.getElementById('exportProjectPdfModal');
      var modalInstance = bootstrap.Modal.getInstance(modalEl);
      if (modalInstance) modalInstance.hide();
      setTimeout(function() {
        window.open('export_project_pdf.php?id=<?php echo $project_id; ?>', '_blank');
        setTimeout(function() { location.reload(); }, 1000);
      }, 300);
    });
  }
});
</script>
<script>
// Initialize permit image preview
document.addEventListener('DOMContentLoaded', function() {
  // Permit image preview functionality
  document.querySelectorAll('.permit-thumb').forEach(function(img) {
    img.addEventListener('click', function() {
      var modalImg = document.getElementById('permitImageModalImg');
      modalImg.src = this.getAttribute('data-img');
    });
  });
});

// Initialize division progress chart
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('divisionProgressChart').getContext('2d');
    var chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($div_chart_labels); ?>,
            datasets: [{
                label: 'Progress (%)',
                data: <?php echo json_encode($div_chart_data); ?>,
                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Progress (%)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Divisions'
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y + '%';
                        }
                    }
                }
            }
        }
    });
});
</script>

<script>
// Function to filter employees by company type
function filterEmployees(companyType) {
    const employeeSelect = document.getElementById('employeeName');
    
    // Clear existing options except the first one
    while (employeeSelect.options.length > 1) {
        employeeSelect.remove(1);
    }
    
    // Get employees for the selected type
    const employees = employeesByType[companyType] || [];
    
    if (employees.length === 0) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'No employees found for this type';
        option.disabled = true;
        option.selected = true;
        employeeSelect.appendChild(option);
        
        // Clear other fields
        document.getElementById('employeePosition').value = '';
        document.getElementById('employeeContact').value = '';
        document.getElementById('employeeRate').value = '';
        document.getElementById('employeeTotal').value = '';
        return;
    }
    
    // Add the default option
    const defaultOption = document.createElement('option');
    defaultOption.value = '';
    defaultOption.textContent = 'Select Employee';
    defaultOption.disabled = true;
    defaultOption.selected = true;
    employeeSelect.appendChild(defaultOption);
    
    // Add employees to the select
    employees.forEach(emp => {
        const option = document.createElement('option');
        option.value = emp.employee_id;
        option.textContent = `${emp.first_name} ${emp.last_name}`;
        option.dataset.position = emp.position_title || '';
        option.dataset.contact = emp.contact_number || '';
        option.dataset.rate = emp.daily_rate || '0.00';
        employeeSelect.appendChild(option);
    });
    
    // Add event listener to update fields when employee is selected
    employeeSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption && selectedOption.dataset) {
            document.getElementById('employeePosition').value = selectedOption.dataset.position || '';
            document.getElementById('employeeContact').value = selectedOption.dataset.contact || '';
            document.getElementById('employeeRate').value = selectedOption.dataset.rate || '0.00';
            document.getElementById('employeeTotal').value = selectedOption.dataset.rate || '0.00';
        } else {
            document.getElementById('employeePosition').value = '';
            document.getElementById('employeeContact').value = '';
            document.getElementById('employeeRate').value = '0.00';
            document.getElementById('employeeTotal').value = '0.00';
        }
    });
}

// Initialize the employee dropdown when the modal is shown
document.addEventListener('DOMContentLoaded', function() {
    const addEmployeeModal = document.getElementById('addEmployeeModal');
    if (addEmployeeModal) {
        addEmployeeModal.addEventListener('show.bs.modal', function() {
            // Reset the form when modal is shown
            const form = this.querySelector('form');
            if (form) form.reset();
            
            // Reset the employee type dropdown
            const employeeTypeSelect = document.getElementById('employeeType');
            if (employeeTypeSelect) {
                employeeTypeSelect.value = '';
                employeeTypeSelect.querySelector('option[selected]').selected = true;
            }
            
            // Reset the employee name dropdown
            const employeeSelect = document.getElementById('employeeName');
            if (employeeSelect) {
                while (employeeSelect.options.length > 0) {
                    employeeSelect.remove(0);
                }
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = 'Select Employee Type First';
                defaultOption.disabled = true;
                defaultOption.selected = true;
                employeeSelect.appendChild(defaultOption);
            }
            
            // Clear other fields
            document.getElementById('employeePosition').value = '';
            document.getElementById('employeeContact').value = '';
            document.getElementById('employeeRate').value = '0.00';
            document.getElementById('employeeTotal').value = '0.00';
        });
    }
});
</script>
<script>
$(document).ready(function() {
    const materialQtyInput = $('#materialQty');
    const maxQtyDisplay = $('#maxQtyDisplay');
    const qtyHelp = $('#qtyHelp');
    let maxAvailableQty = 0;
    let isLowStock = false;
    let selectedMaterialId = 0;

    // When material is selected
    $('#materialName').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        maxAvailableQty = parseInt(selectedOption.data('available-qty')) || 0;
        selectedMaterialId = $(this).val();
        
        // Update max quantity display
        maxQtyDisplay.text('Max: ' + maxAvailableQty);
        
        // Reset quantity input
        materialQtyInput.val('1').trigger('input');
        
        // Enable/disable input based on availability
        materialQtyInput.prop('disabled', maxAvailableQty <= 0);
        
        // Check if material is out of stock
        if (maxAvailableQty <= 0) {
            qtyHelp.removeClass('d-none').text('This material is out of stock');
        } else {
            qtyHelp.addClass('d-none');
        }
    });

    // Validate quantity on input and auto-adjust to max if exceeded
    materialQtyInput.on('input', function() {
        let enteredQty = parseInt($(this).val()) || 0;
        
        // Auto-adjust to max available if exceeded
        if (enteredQty > maxAvailableQty) {
            enteredQty = maxAvailableQty;
            $(this).val(enteredQty);
        }
        
        // Remove any error states
        $(this).removeClass('is-invalid');
        qtyHelp.addClass('d-none');
        
        // Check for low stock (25 or less remaining)
        const remainingQty = maxAvailableQty - enteredQty;
        isLowStock = remainingQty <= 25 && remainingQty >= 0;
    });



    // Handle form submission
    $('#addMaterialForm').on('submit', function(e) {
        let enteredQty = parseInt(materialQtyInput.val()) || 0;
        
        // Auto-adjust to max available if exceeded
        if (enteredQty > maxAvailableQty) {
            enteredQty = maxAvailableQty;
            materialQtyInput.val(enteredQty);
        }
        
        // Calculate remaining quantity
        const remainingQty = maxAvailableQty - enteredQty;
        
        // The backend will handle low stock notification
        return true;
    });
    
    // Initialize material selection if there's a selected value
    if ($('#materialName').val()) {
        $('#materialName').trigger('change');
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var equipmentSelect = document.getElementById('equipmentSelect');
  var equipmentPriceInput = document.getElementById('equipmentPriceInput');
  var depreciationInput = document.getElementById('depreciationInput');
  var totalInput = document.getElementById('equipmentTotalInput');
  var projectDays = parseInt(document.getElementById('projectDaysInput').value) || 0;

  if (equipmentSelect) {
    equipmentSelect.addEventListener('change', function() {
      var selected = equipmentSelect.options[equipmentSelect.selectedIndex];
      var price = parseFloat(selected.getAttribute('data-price')) || 0;
      var depreciation = selected.getAttribute('data-depreciation');
      equipmentPriceInput.value = price.toFixed(2);
      // Remove decimal for depreciation if numeric
      if (depreciation && !isNaN(depreciation)) {
        depreciationInput.value = parseInt(depreciation);
      } else {
        depreciationInput.value = depreciation;
      }
      var deprYears = parseFloat(depreciation);
      if (deprYears > 0) {
        var deprPerDay = price / (deprYears * 365);
        totalInput.value = (deprPerDay * projectDays).toFixed(2);
      } else {
        totalInput.value = 'N/A';
      }
    });
  }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var equipmentSelect = document.getElementById('equipmentSelect');
  var addBtn = document.getElementById('addEquipmentBtn');
  var rentBtn = document.getElementById('requestForRentBtn');
  
  if (rentBtn) {
    rentBtn.addEventListener('click', function(e) {
      e.preventDefault();
      var selected = equipmentSelect.options[equipmentSelect.selectedIndex];
      var equipmentName = selected ? selected.text : 'this equipment';
      
      if (confirm('Do you want to rent ' + equipmentName + '?')) {
        var equipmentId = selected.value;
        var projectId = '<?php echo $project_id; ?>';
        
        // Show loading state
        rentBtn.disabled = true;
        rentBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
        
        // Send AJAX request
        fetch('project_update.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: 'equipment_id=' + encodeURIComponent(equipmentId) + 
                '&project_id=' + encodeURIComponent(projectId)
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert('Rent request has been submitted successfully!');
            // Optionally refresh the page or update UI
            location.reload();
          } else {
            alert('Error: ' + (data.message || 'Failed to process rent request'));
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('An error occurred while processing your request.');
        })
        .finally(() => {
          rentBtn.disabled = false;
          rentBtn.innerHTML = 'Request for Rent';
        });
      }
    });
  }
  
  if (equipmentSelect) {
    equipmentSelect.addEventListener('change', function() {
      var selected = equipmentSelect.options[equipmentSelect.selectedIndex];
      // Always hide the rent button since we're not allowing rental requests
      rentBtn.style.display = 'none';
      // Show add button only if an available equipment is selected
      addBtn.style.display = selected && !selected.disabled ? '' : 'none';
    });
  }
});
</script>
<!-- Equipment Not Available Modal -->

<script>
document.addEventListener('DOMContentLoaded', function() {
  var params = new URLSearchParams(window.location.search);
  if (params.get('error') === 'equipment_not_available') {
    var modal = new bootstrap.Modal(document.getElementById('equipmentNotAvailableModal'));
    modal.show();
    // Remove the error param after showing
    params.delete('error');
    window.history.replaceState({}, document.title, window.location.pathname + (params.toString() ? '?' + params.toString() : ''));
  }
});
</script>
<!-- Equipment Return Success Modal -->

<script>
document.addEventListener('DOMContentLoaded', function() {
  var params = new URLSearchParams(window.location.search);
  if (params.get('equipreturned') === '1') {
    var modal = new bootstrap.Modal(document.getElementById('equipmentReturnSuccessModal'));
    modal.show();
    // Remove the param after showing
    params.delete('equipreturned');
    window.history.replaceState({}, document.title, window.location.pathname + (params.toString() ? '?' + params.toString() : ''));
  }
});
</script>
<script>
// Project Action Buttons JavaScript - Will be moved to bottom of file

// Store all equipment data in JS for filtering
var allEquipment = <?php
  $all_equipment = mysqli_query($con, "SELECT * FROM equipment ORDER BY equipment_name ASC");
  $equipment_js = [];
  while ($eq = mysqli_fetch_assoc($all_equipment)) {
    $equipment_js[] = [
      'id' => $eq['id'],
      'name' => $eq['equipment_name'],
      'category' => $eq['category'],
      'price' => $eq['equipment_price'],
      'depreciation' => $eq['depreciation'],
      'quantity' => $eq['quantity']
    ];
  }
  echo json_encode($equipment_js);
?>;

// Handle success message and clear URL parameters
if (window.location.search.includes('addequip=1')) {
  // Show success toast
  const toast = new bootstrap.Toast(document.getElementById('successToast'));
  toast.show();
  
  // Clear the URL parameter without refreshing the page
  const url = new URL(window.location);
  url.searchParams.delete('addequip');
  window.history.replaceState({}, document.title, url.toString());
}


</body>

</html>