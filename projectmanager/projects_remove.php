<?php
// Remove Employee from Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_project_employee'])) {
    $row_id = intval($_POST['row_id']);
    $project_id = intval($_GET['id']);

    // Kunin muna yung employee_id para ma-update status mamaya
    $result = mysqli_query($con, "SELECT employee_id FROM project_add_employee WHERE id='$row_id' AND project_id='$project_id'");
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $employee_id = intval($row['employee_id']);

        // Burahin sa project_add_employee
        mysqli_query($con, "DELETE FROM project_add_employee WHERE id='$row_id' AND project_id='$project_id'");
    }

    header("Location: project_actual.php?id=$project_id&empdeleted=1");
    exit();
}

// Remove Material from Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_project_material'])) {
    $row_id = intval($_POST['row_id']);
    $project_id = intval($_GET['id']);
    mysqli_query($con, "DELETE FROM project_add_materials WHERE id='$row_id' AND project_id='$project_id'");
    header("Location: project_actual.php?id=$project_id&matdeleted=1");
    exit();
}


// Return Equipment from Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_project_equipment'])) {
    $row_id = intval($_POST['row_id']);
    $project_id = intval($_GET['id']);
    $return_quantity = 1; // Always 1 since no quantity column
    // Get equipment_id 
    $result = mysqli_query($con, "SELECT pae.equipment_id FROM project_add_equipment pae WHERE pae.id='$row_id' AND pae.project_id='$project_id'");
    $row = mysqli_fetch_assoc($result);
    $equipment_id = $row ? intval($row['equipment_id']) : 0;
    if ($equipment_id) {
    // Set project_add_equipment status to 'returned'
    mysqli_query($con, "UPDATE project_add_equipment SET status='returned' WHERE id='$row_id' AND project_id='$project_id'");
    // Set equipment status to 'Available' when returned
    $now = date('Y-m-d H:i:s');
    mysqli_query($con, "UPDATE equipment SET status='Available', return_time='$now' WHERE id='$equipment_id'");
}
    header("Location: project_actual.php?id=$project_id&equipreturned=1");
    exit();
}
// Mark Equipment as Damaged from Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_equipment'])) {
    $row_id = intval($_POST['report_row_id']);
    $project_id = intval($_GET['id']);
    // Get equipment_id from project_add_equipment
    $result = mysqli_query($con, "SELECT equipment_id FROM project_add_equipment WHERE id='$row_id' AND project_id='$project_id'");
    $row = mysqli_fetch_assoc($result);
    $equipment_id = $row ? intval($row['equipment_id']) : 0;
    // Set status in project_add_equipment to 'damage'
    mysqli_query($con, "UPDATE project_add_equipment SET status='damage' WHERE id='$row_id' AND project_id='$project_id'");
    // Set status in equipment to 'Damage' and set return_time
    $now = date('Y-m-d H:i:s');
    if ($equipment_id) {
        mysqli_query($con, "UPDATE equipment SET status='Damage', return_time='$now' WHERE id='$equipment_id'");
        // Insert into equipment_reports
        $remarks = 'Damage Equipment';
        $report_time = $now;
        mysqli_query(
            $con,
            "INSERT INTO equipment_reports (equipment_id, project_id, remarks, report_time)
             VALUES ('$equipment_id', '$project_id', '$remarks', '$report_time')"
        );
    }
    header("Location: project_actual.php?id=$project_id&equipdamaged=1");
    exit();
}
// Handle LGU Permit upload
