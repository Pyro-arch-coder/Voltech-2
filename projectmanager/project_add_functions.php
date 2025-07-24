<?php
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
  $status = 'Planning'; // Always planning in estimation context
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
  
  header("Location: project_ongoing.php?id=$project_id&addequip=1");
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
    header("Location: project_ongoing.php?id=$project_id&addemp=1");
    exit();
}
// Remove employee from project (add this block if not present)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_project_employee'])) {
    $row_id = intval($_POST['row_id']);
    $project_id = intval($_GET['id']);
    mysqli_query($con, "DELETE FROM project_add_employee WHERE id='$row_id'");
    header("Location:project_ongoing.php?id=$project_id&removeemp=1");
    exit();
}
// Add Material to Project

// 1. Add material to project and decrease stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project_material'])) {
    $material_id = intval($_POST['materialName']);
    $project_id = $_GET['id'];
    $material_name = mysqli_real_escape_string($con, $_POST['materialNameText']);
    $unit = mysqli_real_escape_string($con, $_POST['materialUnit']);
    $material_price = floatval($_POST['materialPrice']);
    $quantity = intval($_POST['materialQty']);
    $additional_cost = isset($_POST['additional_cost']) ? floatval($_POST['additional_cost']) : 0;
    // Get labor_other from materials table
    $mat_res = mysqli_query($con, "SELECT quantity, location, labor_other FROM materials WHERE id = '$material_id' LIMIT 1");
    $mat_row = mysqli_fetch_assoc($mat_res);
    $current_qty = intval($mat_row['quantity']);
    $warehouse = mysqli_real_escape_string($con, $mat_row['location']);
    $labor_other = floatval($mat_row['labor_other']);
    // Calculate total including both material_price and labor_other
    $total = ($material_price + $labor_other) * $quantity;
    // Always check if enough stock
    if ($quantity > $current_qty) {
        header("Location: project_ongoing.php?id=$project_id&error=insufficient_stock&left=$current_qty");
        exit();
    }
    $sql = "INSERT INTO project_add_materials (project_id, material_id, material_name, unit, material_price, quantity, total, additional_cost) VALUES ('$project_id', '$material_id', '$material_name', '$unit', '$material_price', '$quantity', '$total', '$additional_cost')";
    if (!mysqli_query($con, $sql)) {
        echo '<div style="color:red;background:#fee;padding:10px;">SQL ERROR: ' . mysqli_error($con) . '</div>';
        echo '<div style="font-family:monospace;background:#eef;padding:10px;">' . htmlspecialchars($sql) . '</div>';
        exit();
    }
    // Decrease stock immediately
    mysqli_query($con, "UPDATE materials SET quantity = GREATEST(quantity - $quantity, 0) WHERE id = '$material_id'");
    header("Location: project_ongoing.php?id=$project_id&addmat=1");
    exit();
}
?> 