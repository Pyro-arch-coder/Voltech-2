<?php
// save_project_employee_estimation.php

include_once "../config.php";

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_id'], $_POST['selected_employees']) && is_array($_POST['selected_employees'])) {
    $project_id = (int)$_POST['project_id'];
    $employee_ids = $_POST['selected_employees'];
    $success = true;
    $error = '';
    
    // Check if any of the selected employees is a foreman
    $has_foreman = false;
    foreach ($employee_ids as $employee_id) {
        $check_foreman_query = "SELECT p.title 
                              FROM employees e 
                              JOIN positions p ON e.position_id = p.position_id 
                              WHERE e.employee_id = ? AND LOWER(p.title) = 'foreman'";
        $check_foreman_stmt = $con->prepare($check_foreman_query);
        $check_foreman_stmt->bind_param("i", $employee_id);
        $check_foreman_stmt->execute();
        $check_foreman_result = $check_foreman_stmt->get_result();
        
        if ($check_foreman_result->num_rows > 0) {
            $has_foreman = true;
            break;
        }
        $check_foreman_stmt->close();
    }
    
    // If trying to add a foreman, check if there's already one in the project
    if ($has_foreman) {
        $check_existing_foreman = "SELECT COUNT(*) as foreman_count 
                                 FROM project_estimation_employee 
                                 WHERE project_id = ? AND LOWER(position) = 'foreman'";
        $check_foreman_stmt = $con->prepare($check_existing_foreman);
        $check_foreman_stmt->bind_param("i", $project_id);
        $check_foreman_stmt->execute();
        $foreman_result = $check_foreman_stmt->get_result();
        $foreman_count = $foreman_result->fetch_assoc()['foreman_count'];
        $check_foreman_stmt->close();
        
        if ($foreman_count > 0) {
            // Return to step 2 with error message
            header("Location: project_process_v2.php?project_id=$project_id&step=2&error=Only one foreman is allowed per project.");
            exit();
        }
    }

    // Calculate current labor costs and labor budget validation
    // Get current labor costs for existing employees
    $current_labor_query = "SELECT COALESCE(SUM(total), 0) as current_total 
                           FROM project_estimation_employee 
                           WHERE project_id = ?";
    $current_labor_stmt = $con->prepare($current_labor_query);
    $current_labor_stmt->bind_param("i", $project_id);
    $current_labor_stmt->execute();
    $current_labor_result = $current_labor_stmt->get_result();
    $current_labor_total = $current_labor_result->fetch_assoc()['current_total'];
    $current_labor_stmt->close();

    // Get labor budget from materials labor_other costs
    $labor_budget_query = "SELECT COALESCE(SUM(pem.quantity * COALESCE(m.labor_other, 0)), 0) as labor_budget 
                          FROM project_estimating_materials pem
                          LEFT JOIN materials m ON pem.material_id = m.id
                          WHERE pem.project_id = ?";
    $budget_stmt = $con->prepare($labor_budget_query);
    $budget_stmt->bind_param("i", $project_id);
    $budget_stmt->execute();
    $budget_result = $budget_stmt->get_result();
    $labor_budget = $budget_result->fetch_assoc()['labor_budget'];
    $budget_stmt->close();

    // Calculate projected total for new employees (pre-validation)
    $projected_total = $current_labor_total;
    $new_employees_data = []; // Store employee data for later use
    foreach ($employee_ids as $employee_id) {
        $employee_id = (int)$employee_id;

        // Check if employee is already assigned to this project in estimation
        $check_query = "SELECT id FROM project_estimation_employee 
                       WHERE project_id = ? AND employee_id = ?";
        $check_stmt = $con->prepare($check_query);
        $check_stmt->bind_param("ii", $project_id, $employee_id);
        $check_stmt->execute();
        $exists = $check_stmt->get_result()->num_rows > 0;
        $check_stmt->close();

        if ($exists) {
            continue; // Skip if already assigned
        }

        // Get employee position and daily rate from DB (for validation)
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

                // Calculate project duration in days (excluding Sundays) - same logic as below
                $project_days = 1; // Default to 1 day if not found
                $project_query = $con->prepare("SELECT start_date, deadline FROM projects WHERE project_id = ?");
                $project_query->bind_param("i", $project_id);
                $project_query->execute();
                $project_result = $project_query->get_result();

                if ($project_row = $project_result->fetch_assoc()) {
                    $start_date = new DateTime($project_row['start_date']);
                    $end_date = new DateTime($project_row['deadline']);
                    $interval = $start_date->diff($end_date);
                    $days = $interval->days + 1; // Total days including start and end

                    // Calculate number of Sundays in the date range
                    $sundays = 0;
                    $period = new DatePeriod(
                        $start_date,
                        new DateInterval('P1D'),
                        $end_date->modify('+1 day') // Include end date in calculation
                    );

                    foreach ($period as $date) {
                        if ($date->format('N') == 7) { // 7 = Sunday
                            $sundays++;
                        }
                    }

                    // Calculate working days (excluding Sundays)
                    $project_days = max(1, $days - $sundays); // Ensure at least 1 day
                } // Close the if ($project_row) block

                // Calculate total for this employee
                $employee_total = $daily_rate * $project_days;
                $projected_total += $employee_total;

                // Store employee data for later use to avoid recalculating
                $new_employees_data[] = [
                    'employee_id' => $employee_id,
                    'position_title' => $position_title,
                    'daily_rate' => $daily_rate,
                    'total' => $employee_total,
                    'project_days' => $project_days
                ];
            }
            $pos_stmt->close();
        }
        $emp_stmt->close();
    }

    // Check if projected total exceeds labor budget
    if ($projected_total > $labor_budget) {
        $over_budget_amount = $projected_total - $labor_budget;
        header("Location: project_process_v2.php?project_id=$project_id&step=2&error=" . urlencode("Labor costs (₱" . number_format($projected_total, 2) . ") would exceed labor budget (₱" . number_format($labor_budget, 2) . ") by ₱" . number_format($over_budget_amount, 2) . ". Please remove some employees or adjust your materials."));
        exit();
    }

    // Start transaction
    $con->begin_transaction();

    try {
        foreach ($new_employees_data as $employee_data) {
            $employee_id = $employee_data['employee_id'];
            $position_title = $employee_data['position_title'];
            $daily_rate = $employee_data['daily_rate'];
            $total = $employee_data['total'];

            // Save to project_estimation_employee table
            $insert_query = "INSERT INTO project_estimation_employee 
                            (project_id, employee_id, position, daily_rate, total, added_at)
                            VALUES (?, ?, ?, ?, ?, NOW())";
            $insert_stmt = $con->prepare($insert_query);
            $insert_stmt->bind_param("iisdd", 
                $project_id, 
                $employee_id, 
                $position_title, 
                $daily_rate,
                $total
            );

            if (!$insert_stmt->execute()) {
                throw new Exception('Failed to add employee: ' . $con->error);
            }
        }
        
        // Commit transaction if all queries were successful
        $con->commit();
        
        // Redirect back to step 2 with success message
        header("Location: project_process_v2.php?project_id=$project_id&step=2&success=1");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $con->rollback();
        
        // Redirect back to step 2 with error message
        header("Location: project_process_v2.php?project_id=$project_id&step=2&error=" . urlencode($e->getMessage()));
        exit();
    }
    
} else {
    // Invalid request, redirect back to step 2 with error
    if (isset($_POST['project_id'])) {
        $project_id = (int)$_POST['project_id'];
        header("Location: project_process_v2.php?project_id=$project_id&step=2&error=Invalid request. Please select at least one employee.");
    } else {
        // If no project_id is provided, redirect to projects list
        header("Location: projects.php");
    }
    exit();
}
?>
