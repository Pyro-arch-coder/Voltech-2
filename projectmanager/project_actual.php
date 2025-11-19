<?php
ob_start();
session_start();

// Initialize current_status with a default value
$current_status = '';
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

// Fetch project details with foreman information and financial data
$project_query = mysqli_query($con, 
    "SELECT p.*, 
            CONCAT(e.first_name, ' ', e.last_name) AS foreman_name,
            COALESCE(p.forecasted_cost, 0) as forecasted_cost,
            COALESCE(p.total_estimation_cost, 0) as total_estimation_cost,
            COALESCE(p.initial_budget, 0) as initial_budget
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

// Calculate project working days (excluding Sundays) - Global calculation for use in tables
$start_date = $project['start_date'];
$end_date = $project['deadline'];
$start = new DateTime($start_date);
$end = new DateTime($end_date);
$interval = $start->diff($end);
$days = $interval->days + 1; // Total days including start and end

// Calculate number of Sundays in the date range
$sundays = 0;
$period = new DatePeriod(
    $start,
    new DateInterval('P1D'),
    $end->modify('+1 day') // Include end date in calculation
);

foreach ($period as $date) {
    if ($date->format('N') == 7) { // 7 = Sunday
        $sundays++;
    }
}

// Calculate working days (excluding Sundays)
$project_days = $days - $sundays;

// Handle project status update (Finish/Cancel)
if (isset($_POST['update_project_status'])) {
    $status = $_POST['update_project_status'];
    $project_id = $_GET['id'];
    
    // Validate status
    if (in_array($status, ['Finished', 'Cancelled'])) {
        // Start transaction
        $con->begin_transaction();
        
        try {
            // Get current project status first
            $check_query = "SELECT status, deadline FROM projects WHERE project_id = ? AND user_id = ?";
            $check_stmt = $con->prepare($check_query);
            $check_stmt->bind_param('ii', $project_id, $userid);
            
            if (!$check_stmt->execute()) {
                throw new Exception('Failed to check project status');
            }
            
            $project_data = $check_stmt->get_result()->fetch_assoc();
            $current_status = $project_data['status'];
            $deadline = $project_data['deadline'];
            $check_stmt->close();
            
            // If marking as Finished, validate requirements
            if ($status === 'Finished') {
                // Get project start date for duration calculation
                $start_query = "SELECT start_date FROM projects WHERE project_id = ?";
                $start_stmt = $con->prepare($start_query);
                $start_stmt->bind_param('i', $project_id);
                $start_stmt->execute();
                $start_result = $start_stmt->get_result();
                $start_data = $start_result->fetch_assoc();
                $start_stmt->close();
                
                $start_date = new DateTime($start_data['start_date']);
                $deadline_date = new DateTime($deadline);
                $today = new DateTime();
                
                // Calculate project duration
                $duration_months = $start_date->diff($deadline_date)->m + ($start_date->diff($deadline_date)->y * 12);
                $duration_days = $start_date->diff($deadline_date)->days;
                
                // Check if all tasks are completed
                $task_check_query = "SELECT COUNT(*) as total, SUM(CASE WHEN progress = 100 THEN 1 ELSE 0 END) as completed 
                                    FROM project_timeline WHERE project_id = ?";
                $task_check_stmt = $con->prepare($task_check_query);
                $task_check_stmt->bind_param('i', $project_id);
                $task_check_stmt->execute();
                $task_check_result = $task_check_stmt->get_result();
                $task_data = $task_check_result->fetch_assoc();
                $task_check_stmt->close();
                
                $total_tasks = intval($task_data['total'] ?? 0);
                $completed_tasks = intval($task_data['completed'] ?? 0);
                
                // Validate tasks completion
                if ($total_tasks == 0) {
                    throw new Exception('Cannot finish project: No tasks found. Please add tasks to the project.');
                }
                
                if ($completed_tasks < $total_tasks) {
                    // Fetch the list of incomplete tasks for clearer feedback
                    $incomplete_list = [];
                    $incomplete_stmt = $con->prepare("SELECT task_name, progress FROM project_timeline WHERE project_id = ? AND (progress IS NULL OR progress < 100)");
                    $incomplete_stmt->bind_param('i', $project_id);
                    $incomplete_stmt->execute();
                    $incomplete_result = $incomplete_stmt->get_result();
                    while ($row = $incomplete_result->fetch_assoc()) {
                        $percent = is_null($row['progress']) ? 0 : intval($row['progress']);
                        $incomplete_list[] = $row['task_name'] . " ({$percent}%)";
                    }
                    $incomplete_stmt->close();
                    $task_message = '';
                    if (!empty($incomplete_list)) {
                        $task_message = ' Incomplete tasks: ' . implode(', ', $incomplete_list) . '.';
                    }
                    throw new Exception('Cannot finish project: Not all tasks are completed (100%). Please complete all tasks first.' . $task_message);
                }
                
                
                
                // Validate date restrictions
                $min_finish_date = clone $deadline_date;
                if ($duration_months >= 3 || $duration_days >= 90) {
                    // For projects 3 months or more, allow 5 days before deadline
                    $min_finish_date->modify('-5 days');
                } elseif ($duration_days <= 7) {
                    // For projects 7 days or less, allow 1 day before deadline
                    $min_finish_date->modify('-1 day');
                } else {
                    // For other projects, must reach deadline
                    $min_finish_date = clone $deadline_date;
                }
                
                if ($today < $min_finish_date) {
                    $days_remaining = $today->diff($min_finish_date)->days;
                    if ($duration_months >= 3 || $duration_days >= 90) {
                        throw new Exception("Cannot finish project: Can only finish 5 days before deadline. {$days_remaining} day(s) remaining.");
                    } elseif ($duration_days <= 7) {
                        throw new Exception("Cannot finish project: Can only finish 1 day before deadline. {$days_remaining} day(s) remaining.");
                    } else {
                        throw new Exception("Cannot finish project: Must reach deadline date. {$days_remaining} day(s) remaining.");
                    }
                }
            }
            
            // If marking as Finished and current status is Overdue, update to Overdue Finished
            if ($status === 'Finished' && $current_status === 'Overdue') {
                $status = 'Overdue Finished';
            }
            
            // Update project status
            $update_query = "UPDATE projects SET status = ? WHERE project_id = ? AND user_id = ?";
            $stmt = $con->prepare($update_query);
            $stmt->bind_param('sii', $status, $project_id, $userid);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to update project status');
            }
            
            // If project is being marked as Finished, update employee and equipment statuses and add expense record
            if ($status === 'Finished' || $status === 'Overdue Finished') {
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
                

                
                // Get equipment total (exclude damaged equipment)
                $equip_query = "SELECT SUM(total) as total FROM project_add_equipment 
                                WHERE project_id=? 
                                AND LOWER(COALESCE(status, '')) NOT IN ('damage', 'damaged')";
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
                
                // Calculate project days for expense calculation
                $project_query_for_days = mysqli_query($con, "SELECT start_date, deadline FROM projects WHERE project_id='$project_id'");
                $project_days_data = mysqli_fetch_assoc($project_query_for_days);
                
                if ($project_days_data) {
                    $start_date = $project_days_data['start_date'];
                    $end_date = $project_days_data['deadline'];
                    $start = new DateTime($start_date);
                    $end = new DateTime($end_date);
                    $interval = $start->diff($end);
                    $days = $interval->days + 1; // Total days including start and end
                    
                    // Calculate number of Sundays in the date range
                    $sundays = 0;
                    $period = new DatePeriod(
                        $start,
                        new DateInterval('P1D'),
                        $end->modify('+1 day') // Include end date in calculation
                    );
                    
                    foreach ($period as $date) {
                        if ($date->format('N') == 7) { // 7 = Sunday
                            $sundays++;
                        }
                    }
                    // Calculate working days (excluding Sundays)
                    $project_days_for_expense = $days - $sundays;
                } else {
                    $project_days_for_expense = 0;
                }
                
                error_log("Project Days Calculated for Expense: " . $project_days_for_expense);
                
                // Calculate employee costs for expense
                $emp_total_for_expense = 0;
                
                $emp_cost_query = mysqli_query($con, "SELECT p.daily_rate
                                                      FROM project_add_employee pae
                                                      JOIN employees e ON pae.employee_id = e.employee_id
                                                      LEFT JOIN positions p ON e.position_id = p.position_id
                                                      WHERE pae.project_id = '$project_id'");
                if (!$emp_cost_query) {
                    error_log("Employee Cost Query Error: " . mysqli_error($con));
                }
                
                while ($emp_row = mysqli_fetch_assoc($emp_cost_query)) {
                    $daily_rate = floatval($emp_row['daily_rate'] ?? 0);
                    error_log("Employee Daily Rate: " . $daily_rate);
                    if ($daily_rate > 0) {
                        $emp_total_for_expense += $daily_rate * $project_days_for_expense;
                        error_log("Added to total: " . ($daily_rate * $project_days_for_expense) . " (rate: $daily_rate * days: $project_days_for_expense)");
                    }
                }
                
                error_log("Final Employee Total for Expense: " . $emp_total_for_expense);
                error_log("Materials Total: " . $mat_total);
                error_log("Equipment Total: " . $equip_total);
                
                // Calculate overhead total for expense calculation
                $overhead_total = 0;
                $overhead_query = "SELECT SUM(price) as total FROM overhead_cost_actual WHERE project_id = ? AND name <> 'VAT'";
                $overhead_stmt = $con->prepare($overhead_query);
                $overhead_stmt->bind_param("i", $project_id);
                $overhead_stmt->execute();
                $overhead_result = $overhead_stmt->get_result();
                $overhead_row = $overhead_result->fetch_assoc();
                $overhead_total = floatval($overhead_row['total'] ?? 0);
                $overhead_stmt->close();
                
                error_log("Overhead Total: " . $overhead_total);
                
                // Compute VAT-inclusive total for finishing: base (materials + equipment + employees + overhead) + 12% VAT
                $base_total_finish = $mat_total + $equip_total + $emp_total_for_expense + $overhead_total;
                $vat_finish = $base_total_finish * 0.12;
                $total_expenses = $base_total_finish + $vat_finish;
                
                // Calculate the remaining budget after expenses
                $remaining_budget = $total_expenses;
                
                // Debug: Log final total
                error_log("Final Expenses Base (no VAT): " . $base_total_finish);
                error_log("VAT (12%): " . $vat_finish);
                error_log("Final Expenses Total (with VAT): " . $total_expenses);
                error_log("Project Budget: " . $project_data['budget']);
                error_log("Remaining Budget: " . $remaining_budget);
                
                // Insert expense record with the total project cost as the expense amount
                $expense_date = date('Y-m-d');
                $expense_category = 'Project';
                $description = 'Total Project Cost for Project: ' . $project_data['project'];
                $project_name = $project_data['project'];
                
                $expense_query = "INSERT INTO expenses (user_id, expense, expensedate, project_name, expensecategory, description, project_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)";
                $expense_stmt = $con->prepare($expense_query);
                $expense_stmt->bind_param('idssssi', $userid, $total_expenses, $expense_date, $project_name, $expense_category, $description, $project_id);
                
                if (!$expense_stmt->execute()) {
                    throw new Exception('Failed to create expense record');
                }
                
                $expense_stmt->close();
                

               

                // Get the budget directly from the database
                $budget_query = "SELECT budget FROM projects WHERE project_id = ?";
                $budget_stmt = $con->prepare($budget_query);
                $budget_stmt->bind_param('i', $project_id);
                $budget_stmt->execute();
                $budget_result = $budget_stmt->get_result();
                $budget_row = $budget_result->fetch_assoc();
                $budget_stmt->close();
                
                $budget = (float)$budget_row['budget'];
                $total_expenses = (float)$total_expenses;
                $profit_loss = $budget - $total_expenses;
                
                // Debug information
                error_log("=== Profit/Loss Calculation ===");
                error_log("Budget from DB: " . $budget);
                error_log("Total Expenses: " . $total_expenses);
                error_log("Calculated Profit/Loss: " . $profit_loss);
                
                // Debug log calculated values
                error_log("Formatted Budget: " . $budget);
                error_log("Formatted Total Expenses: " . $total_expenses);
                error_log("Calculated Profit/Loss: " . $profit_loss);
                error_log("Expected Calculation: {$budget} - {$total_expenses} = " . ($budget - $total_expenses));
                
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
// Fetch project materials with pagination
$proj_mats = [];
$mat_total = 0;
$material_labor_total = 0;

// Pagination settings for materials
$mats_per_page = 10; // Number of materials per page
$mat_page = isset($_GET['mat_page']) ? (int)$_GET['mat_page'] : 1;
$mat_offset = ($mat_page - 1) * $mats_per_page;

// Get total count of materials for this project
$mat_count_query = mysqli_query($con, "SELECT COUNT(*) as total FROM project_add_materials WHERE project_id = '$project_id'");
$mat_count_result = mysqli_fetch_assoc($mat_count_query);
$total_mats = $mat_count_result['total'];
$total_mat_pages = ceil($total_mats / $mats_per_page);

// Fetch paginated materials
$mat_query = mysqli_query($con, "
    SELECT pam.*, m.supplier_name, m.material_price, m.labor_other, m.unit, m.material_name 
    FROM project_add_materials pam 
    LEFT JOIN materials m ON pam.material_id = m.id 
    WHERE pam.project_id = '$project_id'
    ORDER BY pam.id DESC
    LIMIT $mat_offset, $mats_per_page
");

// Calculate totals from all materials (not just current page)
$all_mats_query = mysqli_query($con, "
    SELECT (m.labor_other + m.material_price) * pam.quantity + pam.additional_cost as row_total,
           m.labor_other * pam.quantity as labor_cost
    FROM project_add_materials pam
    LEFT JOIN materials m ON pam.material_id = m.id 
    WHERE pam.project_id = '$project_id'
");

while ($row = mysqli_fetch_assoc($mat_query)) {
    $proj_mats[] = $row;
}

// Calculate total from all materials
$mat_total = 0;
$material_labor_total = 0;
$all_mats_result = mysqli_query($con, "
    SELECT (m.labor_other + m.material_price) * pam.quantity + pam.additional_cost as row_total,
           m.labor_other * pam.quantity as labor_cost
    FROM project_add_materials pam
    LEFT JOIN materials m ON pam.material_id = m.id 
    WHERE pam.project_id = '$project_id'
");

while ($row = mysqli_fetch_assoc($all_mats_result)) {
    $mat_total += $row['row_total'];
    $material_labor_total += $row['labor_cost'];
}
// Fetch project equipments with pagination
$proj_equipments = [];
$equip_total = 0;

// Pagination settings for equipment
$equip_per_page = 10; // Number of equipment per page
$equip_page = isset($_GET['eq_page']) ? (int)$_GET['eq_page'] : 1;
$equip_offset = ($equip_page - 1) * $equip_per_page;

// Get total count of equipment for this project
$equip_count_query = mysqli_query($con, "SELECT COUNT(*) as total FROM project_add_equipment WHERE project_id = '$project_id'");
$equip_count_result = mysqli_fetch_assoc($equip_count_query);
$total_equip = $equip_count_result['total'];
$total_equip_pages = ceil($total_equip / $equip_per_page);

// Fetch paginated equipment
$equip_query = mysqli_query($con, "
    SELECT pae.*, e.equipment_name, e.location, e.equipment_price AS price, e.depreciation, e.status as equipment_status 
    FROM project_add_equipment pae 
    LEFT JOIN equipment e ON pae.equipment_id = e.id 
    WHERE pae.project_id = '$project_id'
    ORDER BY pae.id DESC
    LIMIT $equip_offset, $equip_per_page
");

// Calculate total from all equipment (not just current page)
$all_equip_query = mysqli_query($con, "
    SELECT pae.*, e.equipment_name, e.location, e.equipment_price AS price, e.depreciation, e.status as equipment_status 
    FROM project_add_equipment pae 
    LEFT JOIN equipment e ON pae.equipment_id = e.id 
    WHERE pae.project_id = '$project_id' AND 
          LOWER(COALESCE(pae.status, e.status, '')) NOT IN ('damaged', 'damage')
");

// Process current page equipment
while ($row = mysqli_fetch_assoc($equip_query)) {
    $proj_equipments[] = $row;
}

// Calculate total from all equipment
$equip_total = 0;
while ($row = mysqli_fetch_assoc($all_equip_query)) {
    $status = strtolower(($row['status'] ?? $row['equipment_status'] ?? ''));
    if ($status !== 'damaged' && $status !== 'damage') {
        $equip_total += floatval($row['total']);
    }
}
$final_labor_cost = $material_labor_total- $emp_total;
$grand_total =  $mat_total + $equip_total;

// Initialize division progress for chart
$div_chart_labels = [];
$div_chart_data = [];

// Initialize employee totals
$emp_totals = 0;

// Initialize and calculate overhead total
$overhead_total = 0;
$overhead_query = mysqli_query($con, "SELECT SUM(price) as total FROM overhead_cost_actual WHERE project_id = '$project_id' AND name <> 'VAT'");
if ($overhead_query) {
    $overhead_row = mysqli_fetch_assoc($overhead_query);
    $overhead_total = $overhead_row['total'] ?? 0;
}

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
                    <h2 class="fs-2 m-0">Project Actual</h2>
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
                          <div class="mb-3">
                            <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                          </div>
                          <h5 class="mb-3">Success!</h5>
                          <p class="mb-0">Project status has been updated to <strong><?php echo htmlspecialchars($_GET['new_status']); ?></strong>.</p>
                        </div>
                        <div class="modal-footer justify-content-center border-0">
                          <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                        </div>
                      </div>
                    </div>
                  </div>
                  <script>
                    // Show the success modal when the page loads
                    document.addEventListener('DOMContentLoaded', function() {
                      var statusModal = new bootstrap.Modal(document.getElementById('statusUpdateSuccessModal'));
                      statusModal.show();
                      
                      // Remove the status parameters from the URL
                      const url = new URL(window.location.href);
                      url.searchParams.delete('status_updated');
                      url.searchParams.delete('new_status');
                      window.history.replaceState({}, '', url);
                          window.history.replaceState({}, '', url);
                    });
                  </script>
                <?php endif; ?>
                
                <?php if (isset($_GET['empdeleted']) && $_GET['empdeleted'] == '1'): ?>
                  <!-- Employee Removed Success Modal -->
                  <div class="modal fade" id="employeeRemovedSuccessModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                      <div class="modal-content">
                        <div class="modal-body text-center p-4">
                          <div class="mb-3">
                            <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                          </div>
                          <h5 class="mb-3">Success!</h5>
                          <p class="mb-0">Employee has been removed from the project.</p>
                        </div>
                        <div class="modal-footer justify-content-center border-0">
                          <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                        </div>
                      </div>
                    </div>
                  </div>
                  <script>
                    // Show the success modal when the page loads
                    document.addEventListener('DOMContentLoaded', function() {
                      var empRemovedModal = new bootstrap.Modal(document.getElementById('employeeRemovedSuccessModal'));
                      empRemovedModal.show();
                      
                      // Remove the empdeleted parameter from the URL
                      const url = new URL(window.location.href);
                      url.searchParams.delete('empdeleted');
                      window.history.replaceState({}, '', url);
                    });
                  </script>
                <?php endif; ?>
                
                <?php if (isset($_GET['addemp']) && $_GET['addemp'] == '1'): ?>
                  <!-- Employee Added Success Modal -->
                  <div class="modal fade" id="employeeAddedSuccessModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                      <div class="modal-content">
                        <div class="modal-body text-center p-4">
                          <div class="mb-3">
                            <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                          </div>
                          <h5 class="mb-3">Success!</h5>
                          <p class="mb-0">Employee(s) have been added to the project.</p>
                        </div>
                        <div class="modal-footer justify-content-center border-0">
                          <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                        </div>
                      </div>
                    </div>
                  </div>
                  <script>
                    // Show the success modal when the page loads
                    document.addEventListener('DOMContentLoaded', function() {
                      var empAddedModal = new bootstrap.Modal(document.getElementById('employeeAddedSuccessModal'));
                      empAddedModal.show();
                      
                      // Remove the addemp parameter from the URL
                      const url = new URL(window.location.href);
                      url.searchParams.delete('addemp');
                      window.history.replaceState({}, '', url);
                    });
                  </script>
                <?php endif; ?>
                
                <?php if (isset($_GET['error']) && $_GET['error'] === 'foreman_limit'): ?>
                  <!-- Foreman Restriction Error Modal -->
                  <div class="modal fade" id="foremanRestrictionModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                      <div class="modal-content">
                        <div class="modal-body text-center p-4">
                          <div class="mb-3">
                            <i class="fas fa-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
                          </div>
                          <h5 class="mb-3">Cannot Add Foreman</h5>
                          <p class="mb-0">Only one foreman is allowed per project. Please select a different employee.</p>
                        </div>
                        <div class="modal-footer justify-content-center border-0">
                          <button type="button" class="btn btn-warning" data-bs-dismiss="modal">OK</button>
                        </div>
                      </div>
                    </div>
                  </div>
                  <script>
                    // Show the error modal when the page loads
                    document.addEventListener('DOMContentLoaded', function() {
                      var foremanModal = new bootstrap.Modal(document.getElementById('foremanRestrictionModal'));
                      foremanModal.show();
                      
                      // Remove the error parameter from the URL
                      const url = new URL(window.location.href);
                      url.searchParams.delete('error');
                      window.history.replaceState({}, '', url);
                    });
                  </script>
                <?php elseif (isset($_GET['error'])): ?>
                  <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> Failed to update project status. Please try again.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>
                <?php endif; ?>
                
                <!-- Full Width Project Information Card -->
                <div class="row mb-4">
                  <div class="col-12">
                    <div class="card shadow-sm">
                      <div class="card-header bg-success text-white d-flex align-items-center">
                        <h5 class="mb-0 flex-grow-1">Project Information</h5>
                        <div class="d-flex gap-2 ms-auto">
                          <button type="button" class="btn btn-light btn-sm <?php echo ($project['status'] === 'Finished') ? 'disabled' : ''; ?>" 
                            data-bs-toggle="modal" data-bs-target="#editProjectInfoModal" 
                            <?php echo ($project['status'] === 'Finished') ? 'disabled' : ''; ?>>
                            <i class="fas fa-edit me-1"></i> Edit Project
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
                                <div class="d-flex justify-content-between mb-2">
                                  <span class="text-muted">Foreman:</span>
                                  <span class="fw-bold"><?php echo !empty($project['foreman_name']) ? htmlspecialchars($project['foreman_name']) : 'Not Assigned'; ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                  <span class="text-muted">Initial Budget:</span>
                                  <span class="fw-bold text-primary">₱<?php echo isset($project['initial_budget']) ? number_format($project['initial_budget'], 2) : '0.00'; ?></span>
                                </div>
                              </div>
                            </div>
                                                  </div>
                        
                        <!-- Financial Summary -->
                        <div class="col-md-6">
                            <div class="bg-light p-3 rounded h-100">
                              <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-chart-line text-success me-2"></i>Financial Summary</h6>
                              <!-- Two Column Summary Section -->
                              <div class="row mb-3">
                                <!-- Left Column: Cost Breakdown + Total -->
                                <div class="col-6">
                                  <div class="d-flex justify-content-between mb-2">
                                    <span class="text-dark">Materials:</span>
                                    <span class="fw-bold text-info">₱<?php echo number_format($mat_total, 2); ?></span>
                                  </div>
                                  <div class="d-flex justify-content-between mb-2">
                                    <span class="text-dark">Equipment:</span>
                                    <span class="fw-bold text-warning">₱<?php echo number_format($equip_total, 2); ?></span>
                                  </div>
                                  
                                  
                                  <?php
                                    $proj_emps = [];
                                    $emp_query = mysqli_query($con, "
                                        SELECT pae.*, e.first_name, e.last_name, e.contact_number, e.company_type, e.position_id, 
                                              p.title as position_title, p.daily_rate 
                                        FROM project_add_employee pae 
                                        JOIN employees e ON pae.employee_id = e.employee_id 
                                        LEFT JOIN positions p ON e.position_id = p.position_id 
                                        WHERE pae.project_id = '$project_id'
                                        ORDER BY e.last_name, e.first_name
                                    ");

                                    while ($row = mysqli_fetch_assoc($emp_query)) {
                                        $proj_emps[] = $row;
                                    }

                                    // Ensure variable exists for later totals even if no employees
                                    $emp_totals = 0;
                                    if (count($proj_emps) > 0): 
                                        $i = 1; 
                                        foreach ($proj_emps as $emp): 
                                            $emp_totals += $emp['daily_rate'] * $project_days;
                                        endforeach; 
                                ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-dark">Employee Cost (<?php echo $project_days; ?> days):</span>
                                        <span class="fw-bold text-primary">₱<?php echo number_format($emp_totals, 2); ?></span>
                                    </div>
                                <?php endif; ?>
                                  <div class="d-flex justify-content-between mb-2">
                                    <span class="text-dark">Overhead Costs:</span>
                                    <span class="fw-bold text-info">₱<?php echo number_format($overhead_total, 2); ?></span>
                                  </div>
                                  <?php 
                                    // Base total excluding VAT
                                    $base_total_fs = $mat_total + $equip_total + $emp_totals + $overhead_total; 
                                    $vat_amount_fs = $base_total_fs * 0.12; 
                                  ?>
                                  <div class="d-flex justify-content-between mb-2">
                                    <span class="text-dark">VAT (12%):</span>
                                    <span class="fw-bold text-secondary">₱<?php echo number_format($vat_amount_fs, 2); ?></span>
                                  </div>
                                  <hr class="my-2">
                                  <div class="d-flex justify-content-between mb-2">
                                    <span class="text-dark fw-bold">Total Project Cost:</span>
                                    <span class="fw-bold text-danger fs-5">₱<?php echo number_format($base_total_fs + $vat_amount_fs, 2); ?></span>
                                  </div>
                                </div>
                                
                                <!-- Right Column: Budget - Total Cost = Result -->
                                <div class="col-6">
                                  <?php 
                                  $total_project_cost_before_vat = $mat_total + $equip_total + $emp_totals + $overhead_total;
                                  $vat_amount_right = $total_project_cost_before_vat * 0.12;
                                  $total_project_cost = $total_project_cost_before_vat + $vat_amount_right;
                                  $profit_loss = $project['budget'] - $total_project_cost;
                                  $profit_class = $profit_loss >= 0 ? 'success' : 'danger';
                                  
                                  // Check if total project cost exceeds estimated budget
                                  $exceeds_estimate = $total_project_cost > $project['initial_budget'];
                                  // Store in session for JavaScript to access
                                  $_SESSION['exceeds_estimate'] = $exceeds_estimate;
                                  $profit_icon = $profit_loss >= 0 ? '▲' : '▼';
                                  ?>
                                  <div class="d-flex justify-content-between mb-2">
                                    <span class="text-dark">Budget:</span>
                                    <span class="fw-bold text-success">₱<?php echo number_format($project['budget'], 2); ?></span>
                                  </div>
                                  <div class="d-flex justify-content-between mb-2">
                                    <span class="text-dark">Total Project Cost:</span>
                                    <span class="fw-bold text-danger">₱<?php echo number_format($total_project_cost, 2); ?></span>
                                  </div>
                                  <hr class="my-2">
                                  <div class="d-flex justify-content-between mb-2">
                                    <span class="text-dark fw-bold">Total:</span>
                                    <span class="fw-bold text-<?php echo $profit_class; ?> fs-5">
                                      <?php echo $profit_icon; ?> ₱<?php echo number_format(abs($profit_loss), 2); ?>
                                    </span>
                                  </div>
                                  <div class="text-center mt-2">
                                    <span class="text-<?php echo $profit_class; ?> fw-bold">
                                      <?php echo $profit_loss >= 0 ? 'PROFIT' : 'LOSS'; ?>
                                    </span>
                                  </div>
                                </div>
                              </div>
                              
                              <!-- Additional Info -->
                              <div class="mt-3 pt-2 border-top">
                                <div class="d-flex justify-content-between mb-2">
                                  <span class="text-muted">Labor Budget:</span>
                                  <span class="fw-bold text-primary">₱<?php echo number_format($final_labor_cost, 2); ?></span>
                                </div>
                              </div>            
                              <div class="d-flex justify-content-between mt-5">
                                <span class="text-muted">Status:</span>
                                <?php 
                                    $status = $project['status'] ?? 'In Progress';
                                    $statusClass = 'secondary'; // Default class

                                    // Determine the final status
                                    $final_status = $status;
                                    
                                    // If project is currently overdue and being marked as finished, change to Overdue Finished
                                    if ($final_status === 'Finished' && $current_status === 'Overdue') {
                                        $final_status = 'Overdue Finished';
                                    }
                                    // Set different classes based on status
                                    switch(strtolower($final_status)) {
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
                            <h6 class="mb-0"><i class="fas fa-calendar-day text-warning me-2"></i>Project Timeline</h6>
                            <?php 
                            $is_finished = in_array($project['status'] ?? '', ['Finished', 'Overdue Finished']);
                            if ($is_finished): 
                                // For finished projects, get the actual completion date from the database
                                $completion_date = '';
                                $completion_query = "SELECT updated_at FROM projects WHERE project_id = ?";
                                if ($stmt = $con->prepare($completion_query)) {
                                    $stmt->bind_param('i', $project_id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($row = $result->fetch_assoc()) {
                                        $completion_date = date("M d, Y", strtotime($row['updated_at']));
                                    }
                                    $stmt->close();
                                }
                            ?>
                                <span class="badge bg-success">
                                    <i class="fas fa-check-circle me-1"></i> Completed
                                </span>
                            <?php else: ?>
                            <span class="badge bg-<?php echo $remaining_days >= 0 ? 'success' : 'danger'; ?> me-2">
                              <i class="fas fa-<?php echo $remaining_days >= 0 ? 'play-circle' : 'exclamation-circle'; ?> me-1"></i>
                              <?php echo $remaining_days >= 0 ? 'Active' : 'Overdue'; ?>
                            </span>
                            <?php endif; ?>
                          </div>
                          <?php 
                          if ($is_finished) {
                              // For finished projects, show 100% progress
                              $days_progress = 100;
                              $progress_class = 'success';
                              $progress_text = '100% Completed';
                          } else {
                              // For ongoing projects, calculate progress
                              $start_date = new DateTime($project['start_date']);
                              $end_date = new DateTime($project['deadline']);
                              $total_days = $start_date->diff($end_date)->days + 1;
                              $elapsed_days = $start_date->diff(new DateTime())->days + 1;
                              $days_progress = min(100, max(0, ($elapsed_days / $total_days) * 100));
                              $progress_class = $remaining_days >= 0 ? 'warning' : 'danger';
                              $progress_text = number_format($days_progress, 1) . '%';
                          }
                          ?>
                          <div class="progress mb-2" style="height: 25px;">
                            <div class="progress-bar bg-<?php echo $progress_class; ?> progress-bar-striped <?php echo !$is_finished ? 'progress-bar-animated' : ''; ?>" 
                                 role="progressbar" 
                                 style="width: <?php echo $days_progress; ?>%;" 
                                 aria-valuenow="<?php echo $days_progress; ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                              <?php echo $progress_text; ?>
                            </div>
                          </div>
                          <div class="d-flex justify-content-between">
                            <small class="text-muted">Start: <?php echo date("M d, Y", strtotime($project['start_date'])); ?></small>
                            <small class="text-muted">
                                <?php 
                                if ($is_finished && !empty($completion_date)) {
                                    echo 'Completed: ' . $completion_date;
                                } else {
                                    echo 'Deadline: ' . date("M d, Y", strtotime($project['deadline']));
                                }
                                ?>
                            </small>
                          </div>
                        </div>

                        <!-- Project Status -->
                        <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded">
                          <div>
                            <?php if ($is_finished): ?>
                            <span class="badge bg-success me-2">
                              <i class="fas fa-check-circle me-1"></i> Completed
                            </span>
                            <?php else: ?>
                            <span class="badge bg-<?php echo $remaining_days >= 0 ? 'success' : 'danger'; ?> me-2">
                              <i class="fas fa-<?php echo $remaining_days >= 0 ? 'play-circle' : 'exclamation-circle'; ?> me-1"></i>
                              <?php echo $remaining_days >= 0 ? 'Active' : 'Overdue'; ?>
                            </span>
                            <?php endif; ?>
                          </div>
                          <div class="text-muted small">
                            Project ID: <?php echo $project_id; ?>
                          </div>
                        </div>
                          </div>
                    </div>
                  </div>
                </div>
                
                <!-- Second Row: Progress and Cost Cards -->
                <div class="row">
                  <!-- Task Progress Card -->
                  <div class="col-md-8">
                    <div class="card shadow-sm h-100">
                      <div class="card-header bg-success text-white d-flex align-items-center">
                        <h5 class="mb-0 flex-grow-1">Task Progress</h5>
                        <a href="project_progress.php?id=<?php echo $project_id; ?>" class="btn btn-light btn-sm ml-auto"><i class="fas fa-angle-double-right me-1"></i> Show more</a>
                      </div>
                      <div class="card-body">
                        <?php
                        // Check if project is finished
                        $is_finished = in_array($project['status'], ['Finished', 'Overdue Finished', 'Completed']);
                        
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
                                // If project is finished, use the stored progress without updating
                                if ($is_finished) {
                                    $tasks[] = $task;
                                    $task_names[] = $task['task_name'];
                                    $task_progress[] = $task['progress'];
                                    $total_progress += $task['progress'];
                                    $task_count++;
                                    continue;
                                }
                                
                                // For ongoing projects, calculate progress as before
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
                  
                  <!-- Cost Cards Column -->
                  <div class="col-md-4">
                    <!-- Forecasted Cost Card -->
                    <div class="card mb-3 shadow-sm" style="min-height: 300px; display: flex; flex-direction: column;">
                      <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0">Forecasted Cost</h6>
                      </div>
                      <div class="card-body text-center d-flex flex-column justify-content-center py-4">
                        <h2 class="text-warning mb-2">₱<?php echo isset($project['forecasted_cost']) ? number_format($project['forecasted_cost'], 2) : '0.00'; ?></h2>
                        <p class="text-muted mb-0">Projected Total Cost</p>
                      </div>
                    </div>
                    
                    <!-- Estimated Cost Card -->
                    <div class="card shadow-sm" style="min-height: 300px; display: flex; flex-direction: column;">
                      <div class="card-header bg-primary text-white">
                        <h6 class="mb-0">Estimated Cost</h6>
                      </div>
                      <div class="card-body text-center d-flex flex-column justify-content-center py-4">
                        <h2 class="text-primary mb-2">₱<?php echo isset($project['total_estimation_cost']) ? number_format($project['total_estimation_cost'], 2) : '0.00'; ?></h2>
                        <p class="text-muted mb-0">Planned Budget</p>
                      </div>
                    </div>
                  </div>
                </div>
                
                <!-- Remove estimated and forecasted costs from financial summary -->
                <?php
                // This section intentionally left blank as we've moved these fields to the new card
                ?>
                
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
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="overhead-tab" data-bs-toggle="tab" data-bs-target="#overhead" type="button" role="tab" aria-controls="overhead" aria-selected="false">
                            <i class="fas fa-money-bill-wave me-1"></i> Overhead Costs
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
                                <button class="btn btn-light btn-sm ms-auto <?php echo ($project['status'] === 'Finished' || $project['status'] === 'Overdue Finished') ? 'disabled' : ''; ?>" 
                                    data-bs-toggle="modal" data-bs-target="#addEmployeeModal"
                                    <?php echo ($project['status'] === 'Finished' || $project['status'] === 'Overdue Finished') ? 'disabled' : ''; ?>>
                                    <!-- <i class="fas fa-user-plus me-1"></i> Add Employee -->
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
                                                <!-- <th>Action</th> -->
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Project days already calculated globally above (excluding Sundays)
                                            
                                            // Pagination settings
                                            $records_per_page = 10;
                                            $page = isset($_GET['emp_page']) ? (int)$_GET['emp_page'] : 1;
                                            $offset = ($page - 1) * $records_per_page;
                                            
                                            // Get total number of employees for this project
                                            $total_emps_query = mysqli_query($con, "SELECT COUNT(*) as total FROM project_add_employee WHERE project_id = '$project_id'");
                                            $total_emps = mysqli_fetch_assoc($total_emps_query)['total'];
                                            $total_pages = ceil($total_emps / $records_per_page);
                                            
                                            // Fetch project employees with pagination
                                            $proj_emps = [];
                                            $emp_query = mysqli_query($con, "SELECT pae.*, e.first_name, e.last_name, e.contact_number, e.company_type, e.position_id, p.title as position_title, p.daily_rate 
                                                                        FROM project_add_employee pae 
                                                                        JOIN employees e ON pae.employee_id = e.employee_id 
                                                                        LEFT JOIN positions p ON e.position_id = p.position_id 
                                                                        WHERE pae.project_id = '$project_id'
                                                                        ORDER BY e.last_name, e.first_name
                                                                        LIMIT $records_per_page OFFSET $offset");
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
                                               <!-- <td>
                                                    <form method="post" style="display:inline;">
                                                        <input type="hidden" name="row_id" value="<?php echo $emp['id']; ?>">
                                                        <button type="submit" name="remove_project_employee" class="btn btn-sm btn-danger <?php echo ($project['status'] === 'Finished' || $project['status'] === 'Overdue Finished') ? 'disabled' : ''; ?>" 
                                                            <?php echo ($project['status'] === 'Finished' || $project['status'] === 'Overdue Finished') ? 'disabled' : ''; ?> 
                                                            onclick="return confirm('Remove this employee?')">
                                                            <i class="fas fa-trash"></i> Remove
                                                        </button>
                                                    </form>
                                                </td> -->
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
                                    
                                    <!-- Pagination -->
                                    <?php if ($total_pages > 1): ?>
                                    <nav aria-label="Employee pagination" class="mt-3">
                                        <ul class="pagination justify-content-center">
                                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link" href="?id=<?= $project_id ?>&emp_page=<?= $page - 1 ?>#employees" aria-label="Previous" <?= $page <= 1 ? 'tabindex="-1"' : '' ?>>
                                                    <span aria-hidden="true">&laquo; Previous</span>
                                                </a>
                                            </li>
                                            
                                            <?php 
                                            $start_page = max(1, $page - 2);
                                            $end_page = min($total_pages, $page + 2);
                                            
                                            // Show first page if not in initial range
                                            if ($start_page > 1) {
                                                echo '<li class="page-item"><a class="page-link" href="?id=' . $project_id . '&emp_page=1#employees">1</a></li>';
                                                if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                            }
                                            
                                            // Show page numbers
                                            for ($i = $start_page; $i <= $end_page; $i++): 
                                                $active = $i == $page ? 'active' : '';
                                            ?>
                                                <li class="page-item <?= $active ?>">
                                                    <a class="page-link" href="?id=<?= $project_id ?>&emp_page=<?= $i ?>#employees">
                                                        <?= $i ?>
                                                    </a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <!-- Show last page if not in range -->
                                            <?php if ($end_page < $total_pages): ?>
                                                <?php if ($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?id=<?= $project_id ?>&emp_page=<?= $total_pages ?>#employees">
                                                        <?= $total_pages ?>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                                <a class="page-link" href="?id=<?= $project_id ?>&emp_page=<?= $page + 1 ?>#employees" aria-label="Next" <?= $page >= $total_pages ? 'tabindex="-1"' : '' ?>>
                                                    <span aria-hidden="true">Next &raquo;</span>
                                                </a>
                                            </li>
                                        </ul>
                                        <div class="text-center text-muted small mt-1">
                                            Page <?= $page ?> of <?= $total_pages ?> | <?= $total_emps ?> total employees
                                        </div>
                                    </nav>
                                    <?php endif; ?>
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
                                  <!--  <button class="btn btn-light btn-sm <?php echo ($project['status'] === 'Finished' || $project['status'] === 'Overdue Finished') ? 'disabled' : ''; ?>" 
                                        data-bs-toggle="modal" data-bs-target="#addMaterialsModal"
                                        <?php echo ($project['status'] === 'Finished' || $project['status'] === 'Overdue Finished') ? 'disabled' : ''; ?>>
                                        <i class="fas fa-plus-square me-1"></i> Add Materials
                                    </button> -->
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
                                                <!-- <th>Action</th> -->
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
                                                    <button type="button" class="btn btn-link btn-sm p-0 ms-1 <?php echo ($project['status'] === 'Finished' || $project['status'] === 'Overdue Finished') ? 'disabled' : ''; ?>" 
                                                        data-bs-toggle="modal" data-bs-target="#addCostModal<?php echo $mat['id']; ?>" 
                                                        title="Add/Edit Additional Cost"
                                                        <?php echo ($project['status'] === 'Finished' || $project['status'] === 'Overdue Finished') ? 'disabled' : ''; ?>>
                                                        <i class="fas fa-plus-circle"></i>
                                                    </button>
                                                </td>
                                                <td style="font-weight:bold;color:#222;">₱<?php 
                                                    $row_total = (($mat['labor_other'] + $mat['material_price']) * $mat['quantity']) + $mat['additional_cost'];
                                                    echo number_format($row_total, 2); 
                                                ?></td>
                                                <!-- <td>
                                                    <button type="button" class="btn btn-sm btn-warning <?php echo ($project['status'] === 'Finished' || $project['status'] === 'Overdue Finished') ? 'disabled' : ''; ?>" 
                                                        data-bs-toggle="modal" data-bs-target="#returnMaterialModal" 
                                                        data-row-id="<?php echo $mat['id']; ?>" 
                                                        data-max-qty="<?php echo $mat['quantity']; ?>"
                                                        <?php echo ($project['status'] === 'Finished' || $project['status'] === 'Overdue Finished') ? 'disabled' : ''; ?>>
                                                        <i class="fas fa-undo"></i> Return
                                                    </button>
                                                </td> -->
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
                                    
                                    <!-- Materials Pagination -->
                                    <?php if ($total_mat_pages > 1): ?>
                                    <nav aria-label="Materials pagination" class="mt-3">
                                        <ul class="pagination justify-content-center">
                                            <li class="page-item <?= $mat_page <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link" href="?id=<?php echo $project_id; ?>&mat_page=<?php echo max(1, $mat_page - 1); ?>&tab=materials#materials" tabindex="-1">Previous</a>
                                            </li>
                                            
                                            <?php 
                                            $start_page = max(1, $mat_page - 2);
                                            $end_page = min($total_mat_pages, $mat_page + 2);
                                            
                                            if ($start_page > 1) {
                                                echo '<li class="page-item"><a class="page-link" href="?id='.$project_id.'&mat_page=1&tab=materials#materials">1</a></li>';
                                                if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                            }
                                            
                                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                <li class="page-item <?= $i == $mat_page ? 'active' : '' ?>">
                                                    <a class="page-link" href="?id=<?php echo $project_id; ?>&mat_page=<?php echo $i; ?>&tab=materials#materials"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; 
                                            
                                            if ($end_page < $total_mat_pages) {
                                                if ($end_page < $total_mat_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                echo '<li class="page-item"><a class="page-link" href="?id='.$project_id.'&mat_page='.$total_mat_pages.'&tab=materials#materials">'.$total_mat_pages.'</a></li>';
                                            }
                                            ?>
                                            
                                            <li class="page-item <?= $mat_page >= $total_mat_pages ? 'disabled' : '' ?>">
                                                <a class="page-link" href="?id=<?php echo $project_id; ?>&mat_page=<?php echo min($total_mat_pages, $mat_page + 1); ?>&tab=materials#materials">Next</a>
                                            </li>
                                        </ul>
                                        <div class="text-center text-muted">
                                            Page <?php echo $mat_page; ?> of <?php echo $total_mat_pages; ?> | <?php echo $total_mats; ?> total materials
                                        </div>
                                    </nav>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Project Equipment Tab (Placeholder) -->
                    <div class="tab-pane fade" id="equipment" role="tabpanel" aria-labelledby="equipment-tab">
                        <div class="card shadow-sm">
                            <div class="card-header bg-success text-white d-flex align-items-center">
                                <span class="flex-grow-1">Project Equipment</span>
                                <button class="btn btn-light btn-sm ml-auto <?php echo ($project['status'] === 'Finished' || $project['status'] === 'Overdue Finished') ? 'disabled' : ''; ?>" 
                                    data-bs-toggle="modal" data-bs-target="#addEquipmentModal"
                                    <?php echo ($project['status'] === 'Finished' || $project['status'] === 'Overdue Finished') ? 'disabled' : ''; ?>>
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
                                
                                // Initialize equipment total
                                $eq_total = 0;
                              ?>
                              <?php if (count($proj_equipments) > 0): $i = 1; foreach ($proj_equipments as $eq): 
                                $eq_total += floatval($eq['total']);
                              ?>
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
                                    <?php if ($project['status'] !== 'Finished' && $project['status'] !== 'Overdue Finished'): ?>
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
                                <th colspan="6" class="text-right">Page Total</th>
                                <th colspan="2" style="font-weight:bold;color:#222;">₱<?php 
                                    $page_equip_total = 0;
                                    foreach ($proj_equipments as $eq) {
                                        $status = strtolower(($eq['status'] ?? $eq['equipment_status'] ?? ''));
                                        if ($status !== 'damaged' && $status !== 'damage') {
                                            $page_equip_total += floatval($eq['total']);
                                        }
                                    }
                                    echo number_format($page_equip_total, 2); 
                                ?></th>
                              </tr>
                              <tr>
                                <th colspan="6" class="text-right">Grand Total</th>
                                <th colspan="2" style="font-weight:bold;color:#222;">₱<?php echo number_format($equip_total, 2); ?></th>
                              </tr>
                            </tfoot>
                          </table>
                        </div>
                        
                        <!-- Equipment Pagination -->
                        <?php if ($total_equip_pages > 1): ?>
                        <nav aria-label="Equipment pagination" class="mt-3">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?= $equip_page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?id=<?php echo $project_id; ?>&eq_page=<?php echo max(1, $equip_page - 1); ?>&tab=equipment#equipment" tabindex="-1">Previous</a>
                                </li>
                                
                                <?php 
                                // Show page numbers (limit to 5 pages around current page)
                                $start_page = max(1, $equip_page - 2);
                                $end_page = min($total_equip_pages, $equip_page + 2);
                                
                                if ($start_page > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?id='.$project_id.'&eq_page=1&tab=equipment#equipment">1</a></li>';
                                    if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?= $i == $equip_page ? 'active' : '' ?>">
                                        <a class="page-link" href="?id=<?php echo $project_id; ?>&eq_page=<?php echo $i; ?>&tab=equipment#equipment"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; 
                                
                                if ($end_page < $total_equip_pages) {
                                    if ($end_page < $total_equip_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    echo '<li class="page-item"><a class="page-link" href="?id='.$project_id.'&eq_page='.$total_equip_pages.'&tab=equipment#equipment">'.$total_equip_pages.'</a></li>';
                                }
                                ?>
                                
                                <li class="page-item <?= $equip_page >= $total_equip_pages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?id=<?php echo $project_id; ?>&eq_page=<?php echo min($total_equip_pages, $equip_page + 1); ?>&tab=equipment#equipment">Next</a>
                                </li>
                            </ul>
                            <div class="text-center text-muted">
                                Page <?php echo $equip_page; ?> of <?php echo $total_equip_pages; ?> | <?php echo $total_equip; ?> total equipment
                            </div>
                        </nav>
                        <?php endif; ?>
                      </div>
                    </div>
                </div>
                
                <!-- Overhead Costs Tab -->
                <div class="tab-pane fade" id="overhead" role="tabpanel" aria-labelledby="overhead-tab">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white d-flex align-items-center">
                            <span class="flex-grow-1">Overhead Costs</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered mb-0">
                                    <thead class="table-success">
                                        <tr>
                                            <th>No.</th>
                                            <th>Name</th>
                                            <th>Price (₱)</th>
                                        </tr>
                                    </thead>
                                    <tbody id="overheadCostsBody">
                                        <?php
                                        $project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
                                        $overhead_total = 0;
                                        $counter = 1;
                                        
                                        // Hardcoded overhead cost names
                                        $overhead_names = [
                                            'Mobilization/Demobilization',
                                            'Others',
                                            'Misc. Items',
                                            'Profit',
                                            'Overhead & Supervision',
                                            'Accommodation (Food, Housing)'
                                        ];
                                        
                                        // Get project-specific prices if project_id is valid
                                        $project_prices = [];
                                        if ($project_id > 0) {
                                            $price_query = "SELECT id, name, price FROM overhead_cost_actual WHERE project_id = ? AND name <> 'VAT'";
                                            $stmt = $con->prepare($price_query);
                                            $stmt->bind_param("i", $project_id);
                                            $stmt->execute();
                                            $price_result = $stmt->get_result();
                                            
                                            while ($price_row = $price_result->fetch_assoc()) {
                                                $project_prices[$price_row['name']] = $price_row['price'];
                                            }
                                            $stmt->close();
                                        }
                                        
                                        // Display all overhead costs with project-specific prices or 0
                                        foreach ($overhead_names as $index => $name) {
                                            $price = isset($project_prices[$name]) ? $project_prices[$name] : 0;
                                            $overhead_total += $price;
                                            
                                            echo '<tr data-name="' . htmlspecialchars($name) . '">';
                                            echo '<td>' . $counter++ . '</td>';
                                            echo '<td>' . htmlspecialchars($name) . '</td>';
                                            // REMOVE MO LANG COMMENT HAA
                                            // echo '<td class="editable-price">';
                                            // echo '<div class="input-group input-group-sm">';
                                            // echo '<span class="input-group-text">₱</span>';
                                            // echo '<input type="number" class="form-control form-control-sm price-input" ';
                                            // echo '       value="' . number_format($price, 2, '.', '') . '" ';
                                            // echo '       step="0.01" min="0" ';
                                            // echo '       data-name="' . htmlspecialchars($name, ENT_QUOTES) . '"';
                                            // echo '       onchange="updateOverheadPrice(this)" onkeydown="if(event.key === \'Enter\') { event.preventDefault(); this.blur(); }">';
                                            // echo '</div>';
                                            // echo '</td>';
                                            echo '<td>₱' . number_format($price, 2) . '</td>';
                                            echo '</tr>';
                                        }
                                        
                                        if (empty($overhead_names)) {
                                            echo '<tr><td colspan="3" class="text-center">No overhead costs found</td></tr>';
                                        }
                                        ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="2" class="text-end">Total Overhead Costs:</th>
                                            <th id="overheadTotal">₱<?php echo number_format($overhead_total, 2); ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <script>
                    function updateOverheadPrice(input) {
                        const price = parseFloat(input.value);
                        const name = input.getAttribute('data-name');
                        const projectId = <?php echo $project_id; ?>;
                        const row = input.closest('tr');
                        const totalCell = document.getElementById('overheadTotal');
                        
                        // Validate price
                        if (isNaN(price) || price < 0) {
                            // Revert to previous value if invalid
                            input.value = input.defaultValue;
                            return;
                        }
                        
                        // Update the input's default value
                        input.defaultValue = price.toFixed(2);
                        
                        // Show loading state
                        const originalHTML = input.outerHTML;
                        input.outerHTML = '<div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Loading...</span></div>';
                        
                        // Send AJAX request to save the price
                        const formData = new FormData();
                        formData.append('project_id', projectId);
                        formData.append('name', name);
                        formData.append('price', price);
                        
                        fetch('save_overhead_actual_costs.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Update the total if provided
                                if (data.total !== undefined) {
                                    totalCell.textContent = '₱' + parseFloat(data.total).toLocaleString('en-US', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                                
                                // Show success message
                                const successMsg = document.createElement('span');
                                successMsg.className = 'text-success ms-2';
                                successMsg.innerHTML = '<i class="fas fa-check"></i>';
                                row.querySelector('td:last-child').appendChild(successMsg);
                                
                                // Remove success message after 1.5 seconds
                                setTimeout(() => {
                                    successMsg.remove();
                                }, 1500);
                            } else {
                                // Show error message
                                alert('Error: ' + (data.message || 'Failed to save overhead cost'));
                                // Revert to original value on error
                                input.value = input.defaultValue;
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error saving overhead cost. Please try again.');
                            // Revert to original value on error
                            input.value = input.defaultValue;
                        })
                        .finally(() => {
                            // Restore the input field
                            row.querySelector('td:last-child').innerHTML = originalHTML;
                            // Reattach the event listener
                            const newInput = row.querySelector('.price-input');
                            if (newInput) {
                                newInput.onchange = function() { updateOverheadPrice(this); };
                                newInput.onkeydown = function(e) { 
                                    if (e.key === 'Enter') { 
                                        e.preventDefault(); 
                                        this.blur(); 
                                    } 
                                };
                            }
                        });
                    }
                    </script>
                </div>
                
                <!-- Initialize tooltip for finish button if disabled -->
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const finishBtn = document.getElementById('finishProjectBtn');
                    
                    if (finishBtn && finishBtn.disabled) {
                        // Initialize Bootstrap tooltip for disabled button
                        const tooltip = new bootstrap.Tooltip(finishBtn, {
                            placement: 'top',
                            trigger: 'hover',
                            html: true
                        });
                    }
                });
                </script>
                
                </div>
                <div class="row mt-4">
                        <div class="col-12 text-end">
                            <?php if ($project['status'] !== 'Finished' && $project['status'] !== 'Cancelled' && $project['status'] !== 'Overdue Finished'): 
                                // Fetch tasks to check completion status
                                $task_check_query = "SELECT id, task_name, progress FROM project_timeline WHERE project_id = ?";
                                $task_check_stmt = $con->prepare($task_check_query);
                                $task_check_stmt->bind_param('i', $project_id);
                                $task_check_stmt->execute();
                                $task_check_result = $task_check_stmt->get_result();
                                $tasks_for_validation = [];
                                while ($task_row = $task_check_result->fetch_assoc()) {
                                    $tasks_for_validation[] = $task_row;
                                }
                                $task_check_stmt->close();
                                
                                // Check if all tasks are completed
                                $all_tasks_completed = true;
                                $incomplete_tasks = [];
                                if (count($tasks_for_validation) > 0) {
                                    foreach ($tasks_for_validation as $task) {
                                        if ((int)$task['progress'] < 100) {
                                            $all_tasks_completed = false;
                                            $incomplete_tasks[] = $task['task_name'];
                                        }
                                    }
                                } else {
                                    $all_tasks_completed = false; // No tasks means can't finish
                                }
                                $incomplete_task_text = '';
                                if (!$all_tasks_completed && count($incomplete_tasks) > 0) {
                                    $incomplete_task_text = ' Incomplete tasks: ' . implode(', ', $incomplete_tasks) . '.';
                                }
                                
                                // Check date restrictions
                                $today = new DateTime();
                                $deadline = new DateTime($project['deadline']);
                                $start_date = new DateTime($project['start_date']);
                                
                                // Calculate project duration in months
                                $duration_months = $start_date->diff($deadline)->m + ($start_date->diff($deadline)->y * 12);
                                $duration_days = $start_date->diff($deadline)->days;
                                
                                // Determine minimum finish date
                                $min_finish_date = clone $deadline;
                                if ($duration_months >= 3 || $duration_days >= 90) {
                                    // For projects 3 months or more, allow 5 days before deadline
                                    $min_finish_date->modify('-5 days');
                                } elseif ($duration_days <= 7) {
                                    // For projects 7 days or less, allow 1 day before deadline
                                    $min_finish_date->modify('-1 day');
                                } else {
                                    // For other projects, must reach deadline
                                    $min_finish_date = clone $deadline;
                                }
                                
                                $can_finish_by_date = $today >= $min_finish_date;
                                
                                // Check profit/loss
                                $total_project_cost_before_vat = $mat_total + $equip_total + $emp_totals + $overhead_total;
                                $vat_amount_finish_btn = $total_project_cost_before_vat * 0.12;
                                $total_project_cost = $total_project_cost_before_vat + $vat_amount_finish_btn;
                                $profit_loss = $project['budget'] - $total_project_cost;
                                
                                // Determine if button should be disabled and reason
                                $finish_disabled = false;
                                $finish_reason = '';
                                $days_remaining = $today < $min_finish_date ? $today->diff($min_finish_date)->days : 0;
                                
                                if ($profit_loss < 0) {
                                    $finish_disabled = true;
                                    $finish_reason = 'Cannot finish project: Project is at a loss';
                                } elseif (!$all_tasks_completed) {
                                    $finish_disabled = true;
                                    $finish_reason = 'Cannot finish project: Not all tasks are completed (100%).' . $incomplete_task_text;
                                } elseif (!$can_finish_by_date) {
                                    $finish_disabled = true;
                                    if ($duration_months >= 3 || $duration_days >= 90) {
                                        $finish_reason = "Cannot finish project: Can only finish 5 days before deadline. {$days_remaining} day(s) remaining.";
                                    } elseif ($duration_days <= 7) {
                                        $finish_reason = "Cannot finish project: Can only finish 1 day before deadline. {$days_remaining} day(s) remaining.";
                                    } else {
                                        $finish_reason = "Cannot finish project: Must reach deadline date. {$days_remaining} day(s) remaining.";
                                    }
                                }
                            ?>
                              <button type="button" class="btn btn-danger me-2" id="cancelProjectBtn" data-bs-toggle="modal" data-bs-target="#cancelProjectModal">
                                <i class="fas fa-times-circle"></i> Cancel Project
                              </button>
                              <?php if ($finish_disabled): ?>
                                <span class="d-inline-block" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-html="true" title="<?php echo htmlspecialchars($finish_reason, ENT_QUOTES); ?>">
                                  <button type="button" class="btn btn-success me-2" id="finishProjectBtn" disabled style="pointer-events: none;">
                                    <i class="fas fa-check-circle"></i> Cannot Finish
                                  </button>
                                </span>
                              <?php else: ?>
                                <button
                                  type="button"
                                  class="btn btn-success me-2"
                                  id="finishProjectBtn"
                                  data-bs-toggle="modal"
                                  data-bs-target="#finishProjectModal"
                                  data-profit-loss="<?php echo $profit_loss < 0 ? '1' : '0'; ?>"
                                  data-all-tasks-completed="<?php echo $all_tasks_completed ? '1' : '0'; ?>"
                                  data-can-finish-date="<?php echo $can_finish_by_date ? '1' : '0'; ?>"
                                  data-duration-months="<?php echo $duration_months; ?>"
                                  data-duration-days="<?php echo $duration_days; ?>"
                                  data-days-remaining="<?php echo $days_remaining; ?>"
                                >
                                  <i class="fas fa-check-circle"></i> Finish Project
                                </button>
                              <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($project['status'] === 'Finished'): ?>
                              <a href="generate_completion_pdf.php?id=<?php echo $project_id; ?>" class="btn btn-primary" target="_blank">
                                <i class="fas fa-file-pdf"></i> Generate Completion Certificate
                              </a>
                            <?php endif; ?>
                            <?php if ($project['status'] === 'Overdue Finished'): ?>
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
  // Handle tab persistence from URL
  const urlParams = new URLSearchParams(window.location.search);
  const activeTab = urlParams.get('tab');
  
  // Activate the tab from URL if specified
  if (activeTab) {
    const tabTrigger = document.querySelector(`[data-bs-target="#${activeTab}"]`);
    if (tabTrigger) {
      const tab = new bootstrap.Tab(tabTrigger);
      tab.show();
    }
  }
  
  // Update URL when a tab is clicked
  document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('click', function(e) {
      const tabId = e.target.getAttribute('data-bs-target').substring(1);
      const url = new URL(window.location);
      url.searchParams.set('tab', tabId);
      // Remove page parameters when switching tabs
      url.searchParams.delete('page');
      url.searchParams.delete('mat_page');
      window.history.pushState({}, '', url);
    });
  });
  
  // Handle browser back/forward buttons
  window.addEventListener('popstate', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab');
    if (activeTab) {
      const tabTrigger = document.querySelector(`[data-bs-target="#${activeTab}"]`);
      if (tabTrigger) {
        const tab = new bootstrap.Tab(tabTrigger);
        tab.show();
      }
    }
  });
  
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

// Initialize tooltip for finish button if disabled
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all tooltips on the page (Bootstrap 5 way)
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            html: true
        });
    });
});

</body>

</html>
</html>