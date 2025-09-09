<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

include_once "../config.php";
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
$equipment_data = isset($_POST['equipment_data']) ? json_decode($_POST['equipment_data'], true) : [];

if ($project_id <= 0 || empty($equipment_data) || !is_array($equipment_data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing data. Please try again.']);
    exit();
}

$added_by = intval($_SESSION['user_id']);
$success_count = 0;
$error_messages = [];
$con->begin_transaction();

try {
    $proj_status_res = $con->query("SELECT start_date, deadline FROM projects WHERE project_id = $project_id LIMIT 1");
    $proj_status_row = $proj_status_res->fetch_assoc();
    $start_date = $proj_status_row['start_date'] ?? null;
    $end_date = $proj_status_row['deadline'] ?? null;
    $project_days = 1;
    if ($start_date && $end_date) {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $interval = $start->diff($end);
        $project_days = $interval->days + 1;
    }

    $category = 'purchase';
    $status = 'In Use';

    foreach ($equipment_data as $item) {
        if (empty($item['id']) || empty($item['quantity']) || $item['quantity'] <= 0) {
            $error_messages[] = "Invalid quantity for equipment ID: " . ($item['id'] ?? 'unknown');
            continue;
        }

        $equipment_id = intval($item['id']);
        $quantity = intval($item['quantity']);
        $can_use = false;

        // 1. Check if equipment is Available in equipment table
        $equipment_check = $con->query("SELECT * FROM equipment WHERE id = $equipment_id AND status = 'Available'");
        if ($equipment_check && $equipment_check->num_rows > 0) {
            $equipment = $equipment_check->fetch_assoc();
            $can_use = true;
        } else {
            // 2. If not available, check if it is "returned" in project_add_equipment for the SAME project
            $returned_check = $con->query("SELECT id FROM project_add_equipment WHERE equipment_id = $equipment_id AND project_id = $project_id AND status = 'returned'");
            if ($returned_check && $returned_check->num_rows > 0) {
                $equipment_get = $con->query("SELECT * FROM equipment WHERE id = $equipment_id");
                if ($equipment_get && $equipment_get->num_rows > 0) {
                    $equipment = $equipment_get->fetch_assoc();
                    $can_use = true;
                } else {
                    $error_messages[] = "Equipment ID $equipment_id details not found.";
                    continue;
                }
            }
        }

        if (!$can_use) {
            $error_messages[] = "Equipment ID $equipment_id is not available or has not been returned for this project.";
            continue;
        }

        $price = floatval($equipment['equipment_price']);
        $depreciation = is_numeric($equipment['depreciation']) ? intval($equipment['depreciation']) : 0;
        $total = $price;
        if ($depreciation > 0) {
            $depr_per_day = $price / ($depreciation * 365);
            $total = $depr_per_day * $project_days;
        }
        $calculated_total = $total * $quantity;

        // Only insert if:
        // - There is no 'In Use' entry for this equipment/project
        // - OR there is a 'returned' record for this equipment/project
        $check_query = "SELECT id, status FROM project_add_equipment WHERE project_id = $project_id AND equipment_id = $equipment_id ORDER BY id DESC LIMIT 1";
        $result = $con->query($check_query);
        $may_insert = false;

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (strtolower($row['status']) === 'returned') {
                $may_insert = true;
            } else {
                // If status is not returned (e.g. In Use), do not allow insert
                $error_messages[] = "Equipment ID $equipment_id is already in use for this project.";
                continue;
            }
        } else {
            // No record yet, allow insert
            $may_insert = true;
        }

        if ($may_insert) {
            $query = "INSERT INTO project_add_equipment (
                        project_id, 
                        equipment_id, 
                        category, 
                        price, 
                        total,
                        depreciation,
                        status
                      ) VALUES (
                        $project_id, 
                        $equipment_id, 
                        '$category', 
                        $price, 
                        $calculated_total,
                        '$depreciation',
                        '$status'
                      )";
            if ($con->query($query)) {
                // Update equipment status to Not Available
                $updateQuery = "UPDATE equipment SET status = 'Not Available', borrow_time = NOW() WHERE id = $equipment_id";
                $con->query($updateQuery);
                $success_count++;
            } else {
                $error = $con->error;
                $error_messages[] = "Failed to add equipment ID $equipment_id: $error";
            }
        }
    }

    if ($success_count > 0) {
        $con->commit();
        echo json_encode([
            'success' => true,
            'message' => "Successfully added $success_count equipment item(s) to the project"
        ]);
    } else {
        $con->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'Failed to add equipment',
            'errors' => $error_messages
        ]);
    }
} catch (Exception $e) {
    if (isset($con)) {
        $con->rollback();
    }
    $errorMessage = $e->getMessage();
    if (isset($con) && $con->error) {
        $errorMessage .= '\nDatabase Error: ' . $con->error;
    }
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while saving equipment data',
        'error' => $errorMessage,
        'debug' => [
            'post_data' => $_POST,
            'equipment_data' => $equipment_data ?? 'Not set',
            'project_id' => $project_id ?? 'Not set'
        ]
    ]);
}
if (isset($con)) {
    $con->close();
}
?>