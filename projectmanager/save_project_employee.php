<?php
// save_project_employee.php

include_once "../config.php";

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_id'], $_POST['selected_employees']) && is_array($_POST['selected_employees'])) {
    $project_id = (int)$_POST['project_id'];
    $employee_ids = $_POST['selected_employees'];
    $success = true;
    $error = '';

    foreach ($employee_ids as $employee_id) {
        $employee_id = (int)$employee_id;

        // Get employee position and daily rate from DB
        $emp_query = "SELECT position_id FROM employees WHERE employee_id = ?";
        $emp_stmt = $con->prepare($emp_query);
        $emp_stmt->bind_param("i", $employee_id);
        $emp_stmt->execute();
        $emp_result = $emp_stmt->get_result();

        if ($emp_row = $emp_result->fetch_assoc()) {
            $position_id = $emp_row['position_id'];

            $pos_query = "SELECT title, daily_rate FROM positions WHERE position_id = ?";
            $pos_stmt = $con->prepare($pos_query);
            $pos_stmt->bind_param("i", $position_id);
            $pos_stmt->execute();
            $pos_result = $pos_stmt->get_result();

            if ($pos_row = $pos_result->fetch_assoc()) {
                $position_title = $pos_row['title'];
                $daily_rate = $pos_row['daily_rate'];
                $total = $daily_rate;

                // Save to project_add_employee
                $insert_query = "INSERT INTO project_add_employee (project_id, employee_id, position, daily_rate, total, added_at)
                                 VALUES (?, ?, ?, ?, ?, NOW())";
                $insert_stmt = $con->prepare($insert_query);
                $insert_stmt->bind_param("iisdd", $project_id, $employee_id, $position_title, $daily_rate, $total);

                if (!$insert_stmt->execute()) {
                    $success = false;
                    $error = 'Failed to add employee: ' . $con->error;
                    break;
                }
                // Employee added successfully
            }
            $pos_stmt->close();
        }
        $emp_stmt->close();
    }
    
    // Redirect back to project_actual.php with success/error parameter
    $redirect_url = "project_actual.php?id=" . $project_id . "&addemp=" . ($success ? '1' : '0');
    if (!$success) {
        $redirect_url .= "&error=" . urlencode($error);
    }
    header("Location: " . $redirect_url);
    exit();
} else {
    // Invalid request, redirect to project_actual.php with error
    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
    header("Location: project_actual.php?id=" . $project_id . "&addemp=0&error=" . urlencode('Invalid request'));
    exit();
}
?>