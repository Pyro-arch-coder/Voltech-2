<?php

// Handle Rent Request 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['equipment_id']) && isset($_POST['project_id'])) {
    header('Content-Type: application/json');

    try {
        $equipmentId = intval($_POST['equipment_id']);
        $projectId = intval($_POST['project_id']);
        $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $renterName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Unknown';
        $renterContact = isset($_SESSION['user_contact']) ? $_SESSION['user_contact'] : 'N/A';
        $rentalDate = date('Y-m-d');
        $createdAt = date('Y-m-d H:i:s');
        $status = 'Rented'; // o 'Pending' kung yun ang default logic mo

        if ($userId === 0) {
            throw new Exception('User not authenticated');
        }

        // Start transaction
        mysqli_begin_transaction($con);

        // 1. Update equipment request status
        $updateEquipment = mysqli_prepare($con, "UPDATE equipment SET `request` = 1 WHERE id = ?");
        mysqli_stmt_bind_param($updateEquipment, 'i', $equipmentId);

        if (!mysqli_stmt_execute($updateEquipment)) {
            throw new Exception('Failed to update equipment status');
        }

        // 2. Insert into equipment_rentals table
        $insertRental = mysqli_prepare($con, "
            INSERT INTO equipment_rentals 
            (equipment_id, equipment_name, rent_fee, rental_date, status, renter_name, renter_contact, project_id, created_at)
            SELECT id, name, rent_fee, ?, ?, ?, ?, ?, ?
            FROM equipment
            WHERE id = ?
        ");
        mysqli_stmt_bind_param($insertRental, 'sssssssi', 
            $rentalDate, $status, $renterName, $renterContact, $projectId, $createdAt, $equipmentId
        );

        if (!mysqli_stmt_execute($insertRental)) {
            throw new Exception('Failed to create equipment rental record');
        }

        // Commit transaction
        mysqli_commit($con);

        echo json_encode([
            'success' => true,
            'message' => 'Equipment rental recorded successfully'
        ]);

    } catch (Exception $e) {
        if (isset($con)) {
            mysqli_rollback($con);
        }

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }

    if (isset($updateEquipment)) mysqli_stmt_close($updateEquipment);
    if (isset($insertRental)) mysqli_stmt_close($insertRental);

    exit();
}

// Update Equipment Days Used
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project_equipment_days'])) {
    $row_id = intval($_POST['row_id']);
    $days_used = intval($_POST['days_used']);
    $project_id = intval($_GET['id']);
    // Get price and depreciation from equipment
    $query = mysqli_query($con, "SELECT e.equipment_price, e.depreciation FROM project_add_equipment pae LEFT JOIN equipment e ON pae.equipment_id = e.id WHERE pae.id='$row_id' AND pae.project_id='$project_id'");
    $data = mysqli_fetch_assoc($query);
    $price = floatval($data['equipment_price']);
    $depreciation = floatval($data['depreciation']);
    $depreciation_per_day = ($depreciation > 0) ? $price / ($depreciation * 365) : 0;
    $total = $depreciation_per_day * $days_used;
    mysqli_query($con, "UPDATE project_add_equipment SET days_used='$days_used', total='$total' WHERE id='$row_id' AND project_id='$project_id'");
    header("Location: project_details.php?id=$project_id");
    exit();
}
// Update Employee Quantity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project_employee_qty'])) {
    $row_id = intval($_POST['row_id']);
    $quantity = intval($_POST['quantity']);
    $rate = floatval($_POST['rate']);
    $total = $quantity * $rate;
    $project_id = intval($_GET['id']);
    mysqli_query($con, "UPDATE project_add_employee SET quantity='$quantity', total='$total' WHERE id='$row_id' AND project_id='$project_id'");
    header("Location: project_details.php?id=$project_id");
    exit();
}
// Update Employee Schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project_employee_schedule'])) {
    $row_id = intval($_POST['row_id']);
    $schedule = intval($_POST['schedule']);
    $rate = floatval($_POST['rate']);
    $total = $schedule * $rate;
    $project_id = intval($_GET['id']);
    mysqli_query($con, "UPDATE project_add_employee SET schedule='$schedule', total='$total' WHERE id='$row_id' AND project_id='$project_id'");
    header("Location: project_details.php?id=$project_id");
    exit();
}
// Update Employee Days
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project_employee_days'])) {
    $row_id = intval($_POST['row_id']);
    $new_days = intval($_POST['days']);
    $project_id = intval($_GET['id']);
    // Get current values
    $current_query = mysqli_query($con, "SELECT days, schedule, daily_rate FROM project_add_employee WHERE id='$row_id' AND project_id='$project_id'");
    $current_data = mysqli_fetch_assoc($current_query);
    $current_days = intval($current_data['days']);
    $current_schedule = intval($current_data['schedule']);
    $daily_rate = floatval($current_data['daily_rate']);
    // Calculate the difference
    $days_difference = $new_days - $current_days;
    $new_schedule = $current_schedule + $days_difference;
    // Update both days and schedule
    $new_total = $daily_rate * $new_schedule;
    mysqli_query($con, "UPDATE project_add_employee SET days='$new_days', schedule='$new_schedule', total='$new_total' WHERE id='$row_id' AND project_id='$project_id'");
    header("Location: project_details.php?id=$project_id");
    exit();
}
// Update Material Quantity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project_material_qty'])) {
    $row_id = intval($_POST['row_id']);
    $new_quantity = intval($_POST['quantity']);
    $price = floatval($_POST['price']);
    $project_id = intval($_GET['id']);

    // 1. Get old quantity and material_id
    $res = mysqli_query($con, "SELECT quantity, material_id FROM project_add_materials WHERE id='$row_id' AND project_id='$project_id' LIMIT 1");
    $row = mysqli_fetch_assoc($res);
    $old_quantity = intval($row['quantity']);
    $material_id = intval($row['material_id']);

    // 2. Compute difference
    $diff = $new_quantity - $old_quantity;
    if ($diff != 0) {
        // 3. Update main materials table
        // If diff > 0, subtract from inventory; if diff < 0, add to inventory
        mysqli_query($con, "UPDATE materials SET quantity = quantity - ($diff) WHERE id = '$material_id'");
        // 4. Update used_slots in the warehouse
        $loc_res = mysqli_query($con, "SELECT location FROM materials WHERE id = '$material_id' LIMIT 1");
        $loc_row = mysqli_fetch_assoc($loc_res);
        $warehouse = mysqli_real_escape_string($con, $loc_row['location']);
        if ($diff > 0) {
            // Tinaasan: bawas sa used_slots
            mysqli_query($con, "UPDATE warehouses SET used_slots = used_slots - $diff WHERE warehouse = '$warehouse'");
            error_log("[WAREHOUSE LOG] Increased project qty for material_id=$material_id, diff=$diff, warehouse='$warehouse' (used_slots -$diff)");
        } else {
            // Binawasan: dagdag sa used_slots
            $add_back = abs($diff);
            mysqli_query($con, "UPDATE warehouses SET used_slots = used_slots + $add_back WHERE warehouse = '$warehouse'");
            error_log("[WAREHOUSE LOG] Decreased project qty for material_id=$material_id, diff=$diff, warehouse='$warehouse' (used_slots +$add_back)");
        }
    }

    // 5. Get labor_other from materials table and calculate total correctly
    $mat_res = mysqli_query($con, "SELECT labor_other FROM materials WHERE id = '$material_id' LIMIT 1");
    $mat_row = mysqli_fetch_assoc($mat_res);
    $labor_other = floatval($mat_row['labor_other']);
    
    // Calculate total including both material_price and labor_other
    $total = ($price + $labor_other) * $new_quantity;
    mysqli_query($con, "UPDATE project_add_materials SET quantity='$new_quantity', total='$total' WHERE id='$row_id' AND project_id='$project_id'");

    header("Location: project_details.php?id=$project_id");
    exit();
} 