<?php
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 3) {
    header("Location: ../login.php");
    exit();
}
require_once '../config.php';

// Add Equipment to Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project_equipment'])) {
  $equipment_id = intval($_POST['equipment_id']);
  $category = 'purchase'; // Default to purchase since rental is not supported
  // Fetch equipment details
  $eq_res = mysqli_query($con, "SELECT equipment_price, depreciation FROM equipment WHERE id='$equipment_id' LIMIT 1");
  $eq = mysqli_fetch_assoc($eq_res);
  $price = $eq['equipment_price'];
  $depreciation = is_numeric($eq['depreciation']) ? intval($eq['depreciation']) : $eq['depreciation'];
  // Calculate project days
  $project_id = intval($_GET['id']);
  $proj_status_res = mysqli_query($con, "SELECT start_date, deadline FROM projects WHERE project_id='$project_id' LIMIT 1");
  $proj_status_row = mysqli_fetch_assoc($proj_status_res);
  $status = 'In Use'; // Always planning in estimation context
  $now = date('Y-m-d H:i:s');
  $start_date = isset($proj_status_row['start_date']) ? $proj_status_row['start_date'] : null;
  $end_date = isset($proj_status_row['deadline']) ? $proj_status_row['deadline'] : null;
  $project_days = 1;
  if ($start_date && $end_date) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    $project_days = $interval->days + 1;
  }
  // Calculate total based on depreciation
  $total = 0;
  if (is_numeric($depreciation) && $depreciation > 0) {
    $depr_per_day = floatval($price) / ($depreciation * 365);
    $total = $depr_per_day * $project_days;
  } else {
    // fallback: just use price (or zero)
    $total = floatval($price);
  }
  // Insert project equipment WITHOUT borrow_time
  mysqli_query($con, "INSERT INTO project_add_equipment (project_id, equipment_id, category, total, depreciation, status, price) VALUES ('$project_id', '$equipment_id', '$category', '$total', '$depreciation', '$status', '$price')");
  // Update borrow_time in equipment table
  mysqli_query($con, "UPDATE equipment SET borrow_time = '$now' WHERE id = '$equipment_id'");
  // Update status after reservation
  $new_status = 'Not Available';
  mysqli_query($con, "UPDATE equipment SET status = '$new_status' WHERE id = '$equipment_id'");
  
  // Set timestamp for success message (valid for 5 seconds)
  $_SESSION['equipment_success_time'] = time();
  
  header("Location: project_actual.php?id=$project_id");
  exit();
}
// Add Employee to Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project_employee'])) {
    $employee_id = intval($_POST['employeeName']);
    $position = mysqli_real_escape_string($con, $_POST['employeePosition']);
    $daily_rate = floatval($_POST['employeeRate']);
    $project_id = intval($_GET['id']);
    // Get project start_date and deadline
    $proj_res = mysqli_query($con, "SELECT start_date, deadline FROM projects WHERE project_id='$project_id' LIMIT 1");
    $proj_row = mysqli_fetch_assoc($proj_res);
    $start_date = $proj_row['start_date'];
    $end_date = $proj_row['deadline'];
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    $project_days = $interval->days + 1;
    $total = $daily_rate * $project_days;
    $sql = "INSERT INTO project_add_employee (project_id, employee_id, position, daily_rate, total) VALUES ('$project_id', '$employee_id', '$position', '$daily_rate', '$total')";
    mysqli_query($con, $sql);
    header("Location: project_actual.php?id=$project_id&addemp=1");
    exit();
}
// Remove employee from project (add this block if not present)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_project_employee'])) {
    $row_id = intval($_POST['row_id']);
    $project_id = intval($_GET['id']);
    mysqli_query($con, "DELETE FROM project_add_employee WHERE id='$row_id'");
    header("Location:project_actual.php?id=$project_id&removeemp=1");
    exit();
}

// Remove estimation employee from project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_estimation_employee'])) {
    header('Content-Type: application/json');
    
    $employee_id = intval($_POST['employee_id']);
    $project_id = intval($_POST['project_id']);
    
    // Debug logging
    error_log("Removing estimation employee: employee_id=$employee_id, project_id=$project_id");
    
    if (!$employee_id || !$project_id) {
        echo json_encode(['success' => false, 'error' => 'Missing employee_id or project_id']);
        exit();
    }
    
    // Delete from project_estimation_employee table
    $query = "DELETE FROM project_estimation_employee WHERE id='$employee_id' AND project_id='$project_id'";
    $result = mysqli_query($con, $query);
    
    if (!$result) {
        echo json_encode(['success' => false, 'error' => mysqli_error($con)]);
        exit();
    }
    
    $affected_rows = mysqli_affected_rows($con);
    error_log("Affected rows: $affected_rows");
    
    // Return success response
    echo json_encode(['success' => true, 'affected_rows' => $affected_rows]);
    exit();
}
// Add Material to Project

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project_material'])) {
  $material_id = intval($_POST['materialName']);
  $project_id = $_GET['id'];
  $material_name = mysqli_real_escape_string($con, $_POST['materialNameText']);
  $unit = mysqli_real_escape_string($con, $_POST['materialUnit']);
  $material_price = floatval($_POST['materialPrice']);
  $quantity = intval($_POST['materialQty']);
  $additional_cost = isset($_POST['additional_cost']) ? floatval($_POST['additional_cost']) : 0;

  // Get material data from DB
  $mat_res = mysqli_query($con, "SELECT quantity, location, labor_other FROM materials WHERE id = '$material_id' LIMIT 1");
  $mat_row = mysqli_fetch_assoc($mat_res);
  $current_qty = intval($mat_row['quantity']);
  $warehouse = mysqli_real_escape_string($con, $mat_row['location']);
  $labor_other = floatval($mat_row['labor_other']);

  // Check stock availability
  if ($quantity > $current_qty) {
      header("Location: project_actual.php?id=$project_id&error=insufficient_stock&left=$current_qty");
      exit();
  }

  // Insert to project_add_materials
  $sql = "INSERT INTO project_add_materials (
              project_id, material_id, material_name, unit, material_price, quantity, additional_cost
          ) VALUES (
              '$project_id', '$material_id', '$material_name', '$unit', '$material_price', '$quantity', '$additional_cost'
          )";

  if (!mysqli_query($con, $sql)) {
      echo '<div style="color:red;background:#fee;padding:10px;">SQL ERROR: ' . mysqli_error($con) . '</div>';
      echo '<div style="font-family:monospace;background:#eef;padding:10px;">' . htmlspecialchars($sql) . '</div>';
      exit();
  }

  // Decrease stock
  mysqli_query($con, "UPDATE materials SET quantity = GREATEST(quantity - $quantity, 0) WHERE id = '$material_id'");

  // Get updated stock after deduction
  $new_qty_res = mysqli_query($con, "SELECT quantity, material_name FROM materials WHERE id = '$material_id' LIMIT 1");
  $new_qty_row = mysqli_fetch_assoc($new_qty_res);
  $new_qty = intval($new_qty_row['quantity']);
  $material_name_only = mysqli_real_escape_string($con, $new_qty_row['material_name']);

  // Notify procurement if stock is low (≤25)
  if ($new_qty <= 25) {
      $notif_user_id = $_SESSION['user_id'] ?? 0; // Get the logged-in user's ID
      $notif_type = 'Low Stock Alert';
      $notif_msg = "The material '$material_name_only' is now low on stock (Remaining: $new_qty) after being added to a project.";
      $notif_created = date('Y-m-d H:i:s');

      $notif_sql = "INSERT INTO notifications_procurement (
                      user_id, notif_type, message, is_read, created_at
                  ) VALUES (
                      " . (int)$notif_user_id . ", 
                      '" . mysqli_real_escape_string($con, $notif_type) . "', 
                      '" . mysqli_real_escape_string($con, $notif_msg) . "', 
                      0, 
                      '" . $notif_created . "'
                  )";

      mysqli_query($con, $notif_sql) or error_log('Notification error: ' . mysqli_error($con));
  }

  // Redirect after success
  header("Location: project_actual.php?id=$project_id&addmat=1");
  exit();
}

?> 