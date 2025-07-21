<?php
// Add Equipment to Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project_equipment'])) {
  $equipment_id = intval($_POST['equipment_id']);
  $category = mysqli_real_escape_string($con, $_POST['category']);
  // Fetch price, rental_fee, depreciation, quantity, reserved_quantity from equipment table
  $eq_res = mysqli_query($con, "SELECT equipment_price, rental_fee, depreciation, quantity, reserved_quantity FROM equipment WHERE id='$equipment_id' LIMIT 1");
  $eq = mysqli_fetch_assoc($eq_res);
  $quantity = isset($eq['quantity']) ? intval($eq['quantity']) : 0;
  $reserved_quantity = isset($eq['reserved_quantity']) ? intval($eq['reserved_quantity']) : 0;
  if ($quantity - $reserved_quantity <= 0) {
    $project_id = intval($_GET['id']);
    header("Location: project_details.php?id=$project_id&error=equipment_not_available");
    exit();
  }
  if (strtolower($category) === 'rental' || strtolower($category) === 'rent') {
    $price = $eq['rental_fee'];
    $depreciation = 'None';
  } else {
    $price = $eq['equipment_price'];
    $depreciation = is_numeric($eq['depreciation']) ? intval($eq['depreciation']) : $eq['depreciation'];
  }
  // Calculate project days
  $project_id = intval($_GET['id']);
  $proj_status_res = mysqli_query($con, "SELECT io, start_date, deadline FROM projects WHERE project_id='$project_id' LIMIT 1");
  $proj_status_row = mysqli_fetch_assoc($proj_status_res);
  $project_io = isset($proj_status_row['io']) ? $proj_status_row['io'] : null;
  $status = ($project_io == '1') ? 'In Use' : 'Planning';
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
  // Calculate total
  $total = 0;
  if (strtolower($category) === 'rental' || strtolower($category) === 'rent') {
    $total = floatval($price) * $project_days;
  } else if (is_numeric($depreciation) && $depreciation > 0) {
    $depr_per_day = floatval($price) / ($depreciation * 365);
    $total = $depr_per_day * $project_days;
  }
  mysqli_query($con, "INSERT INTO project_add_equipment (project_id, equipment_id, category, total, depreciation, status, price) VALUES ('$project_id', '$equipment_id', '$category', '$total', '$depreciation', '$status', '$price')");
  if ($project_io == '4') {
    mysqli_query($con, "UPDATE equipment SET reserved_quantity = reserved_quantity + 1 WHERE id = '$equipment_id'");
    // Update status after reservation
    $status_check = mysqli_query($con, "SELECT quantity, reserved_quantity FROM equipment WHERE id = '$equipment_id'");
    $row = mysqli_fetch_assoc($status_check);
    $available = intval($row['quantity']) - intval($row['reserved_quantity']);
    $new_status = ($available <= 0) ? 'Not Available' : 'Available';
    mysqli_query($con, "UPDATE equipment SET status = '$new_status' WHERE id = '$equipment_id'");
  } else if ($project_io == '1') {
    $new_qty = $quantity - 1;
    $new_reserved = $reserved_quantity > 0 ? $reserved_quantity - 1 : 0;
    $now = date('Y-m-d H:i:s');
    mysqli_query($con, "UPDATE equipment SET quantity = $new_qty, reserved_quantity = $new_reserved, borrow_time = '$now' WHERE id = '$equipment_id'");
    // Update status after actual use
    $status_check = mysqli_query($con, "SELECT quantity, reserved_quantity FROM equipment WHERE id = '$equipment_id'");
    $row = mysqli_fetch_assoc($status_check);
    $available = intval($row['quantity']) - intval($row['reserved_quantity']);
    $new_status = ($available <= 0) ? 'Not Available' : 'Available';
    mysqli_query($con, "UPDATE equipment SET status = '$new_status' WHERE id = '$equipment_id'");
  }
  header("Location: project_details.php?id=$project_id&addequip=1");
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
    header("Location: project_details.php?id=$project_id&addemp=1");
    exit();
}
// Remove employee from project (add this block if not present)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_project_employee'])) {
    $row_id = intval($_POST['row_id']);
    $project_id = intval($_GET['id']);
    mysqli_query($con, "DELETE FROM project_add_employee WHERE id='$row_id'");
    header("Location: project_details.php?id=$project_id&removeemp=1");
    exit();
}
// Add Material to Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project_material'])) {
    $material_id = intval($_POST['materialName']);
    $project_id = intval($_GET['id']);
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
    // Fetch project status (io)
    $proj_status_res = mysqli_query($con, "SELECT io FROM projects WHERE project_id='$project_id' LIMIT 1");
    $proj_status_row = mysqli_fetch_assoc($proj_status_res);
    $project_io = isset($proj_status_row['io']) ? $proj_status_row['io'] : null;
    // Always check if enough stock
    if ($quantity > $current_qty) {
        header("Location: project_details.php?id=$project_id&error=insufficient_stock&left=$current_qty");
        exit();
    }
    $sql = "INSERT INTO project_add_materials (project_id, material_id, material_name, unit, material_price, quantity, total, additional_cost) VALUES ('$project_id', '$material_id', '$material_name', '$unit', '$material_price', '$quantity', '$total', '$additional_cost')";
    mysqli_query($con, $sql);
    // Subtract the quantity from the main materials table only if On going
    if ($project_io == '1') {
        mysqli_query($con, "UPDATE materials SET quantity = quantity - $quantity WHERE id = '$material_id'");
        // Check for low stock and notify procurement
        $mat_info = mysqli_query($con, "SELECT material_name, quantity, low_stock_threshold FROM materials WHERE id = '$material_id' LIMIT 1");
        if ($mat_row = mysqli_fetch_assoc($mat_info)) {
            $current_qty = intval($mat_row['quantity']);
            $threshold = isset($mat_row['low_stock_threshold']) ? intval($mat_row['low_stock_threshold']) : 10;
            $material_name_esc = mysqli_real_escape_string($con, $mat_row['material_name']);
            if ($current_qty <= $threshold) {
                $notif_type = 'Low Stock';
                $message = "Material '$material_name_esc' is low on stock. Remaining: $current_qty";
                $message_esc = mysqli_real_escape_string($con, $message);
                // Find procurement officer user_id (assuming user_level 4)
                $user_res = mysqli_query($con, "SELECT id FROM users WHERE user_level = 4 LIMIT 1");
                $user_id = ($user_res && $user_row = mysqli_fetch_assoc($user_res)) ? intval($user_row['id']) : 1;
                mysqli_query($con, "INSERT INTO notifications_procurement (user_id, notif_type, message, is_read, created_at) VALUES ('$user_id', '$notif_type', '$message_esc', 0, NOW())");
            }
        }
    }
    header("Location: project_details.php?id=$project_id&addmat=1");
    exit();
}
// Remove Material from Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_project_material'])) {
    $row_id = intval($_POST['row_id']);
    $project_id = intval($_GET['id']);
    // Get material_id and quantity before deleting
    $res = mysqli_query($con, "SELECT material_id, quantity FROM project_add_materials WHERE id='$row_id' LIMIT 1");
    $row = mysqli_fetch_assoc($res);
    if ($row) {
        $material_id = $row['material_id'];
        $quantity = $row['quantity'];
        // Return quantity to materials table
        // (Do not add back to stock for Estimating, only for On going)
        // But for remove, we do not add back to stock (as per previous logic)
    }
    mysqli_query($con, "DELETE FROM project_add_materials WHERE id='$row_id'");
    header("Location: project_details.php?id=$project_id&removemat=1");
    exit();
}
// Return Material to Inventory
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_project_material'])) {
    $row_id = intval($_POST['row_id']);
    $project_id = intval($_GET['id']);
    $return_quantity = isset($_POST['return_quantity']) ? intval($_POST['return_quantity']) : 0;
    // Get material_id, quantity, material_price, and project io
    $res = mysqli_query($con, "SELECT pam.material_id, pam.quantity, pam.material_price, p.io FROM project_add_materials pam LEFT JOIN projects p ON pam.project_id = p.project_id WHERE pam.id='$row_id' LIMIT 1");
    $row = mysqli_fetch_assoc($res);
    if ($row) {
        $material_id = $row['material_id'];
        $quantity = $row['quantity'];
        $material_price = floatval($row['material_price']);
        $project_io = isset($row['io']) ? intval($row['io']) : 0;
        $qty_to_return = ($return_quantity > 0 && $return_quantity <= $quantity) ? $return_quantity : $quantity;
        
        // Get labor_other from materials table
        $mat_res = mysqli_query($con, "SELECT labor_other FROM materials WHERE id = '$material_id' LIMIT 1");
        $mat_row = mysqli_fetch_assoc($mat_res);
        $labor_other = floatval($mat_row['labor_other']);
        
        // Only add back to materials table if not Estimating
        if ($project_io != 4) {
            mysqli_query($con, "UPDATE materials SET quantity = quantity + $qty_to_return WHERE id = '$material_id'");
        }
        
        if ($qty_to_return >= $quantity) {
            // All returned, delete row
            mysqli_query($con, "DELETE FROM project_add_materials WHERE id='$row_id'");
        } else {
            // Partial return, update quantity and recalculate total
            $new_qty = $quantity - $qty_to_return;
            $new_total = ($material_price + $labor_other) * $new_qty;
            mysqli_query($con, "UPDATE project_add_materials SET quantity = $new_qty, total = $new_total WHERE id='$row_id'");
        }
    }
    header("Location: project_details.php?id=$project_id&returnmat=1");
    exit();
} 