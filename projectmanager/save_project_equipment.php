<?php
session_start();

// Check if user is logged in and has the right permissions
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database connection
include_once "../config.php";

// Set content type to JSON
header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get the form data
$project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
$equipment_data = isset($_POST['equipment_data']) ? json_decode($_POST['equipment_data'], true) : [];

// Validate required fields
if ($project_id <= 0 || empty($equipment_data) || !is_array($equipment_data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing data. Please try again.']);
    exit();
}

$added_by = intval($_SESSION['user_id']);
$success_count = 0;
$error_messages = [];

// Start transaction
$con->begin_transaction();

try {
    // Fetch project days for depreciation calculation
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
    
    $now = date('Y-m-d H:i:s');
    $category = 'purchase';
    $status = 'In Use';

    foreach ($equipment_data as $item) {
        // Validate equipment data
        if (empty($item['id']) || empty($item['quantity']) || $item['quantity'] <= 0) {
            $error_messages[] = "Invalid quantity for equipment ID: " . ($item['id'] ?? 'unknown');
            continue;
        }

        $equipment_id = intval($item['id']);
        $quantity = intval($item['quantity']);
        
        // Check if equipment exists and is available
        $equipment_check = $con->query("SELECT * FROM equipment WHERE id = $equipment_id AND status = 'Available'");
        if (!$equipment_check || $equipment_check->num_rows === 0) {
            $error_messages[] = "Equipment ID $equipment_id is not available or not found";
            continue;
        }
        
        $equipment = $equipment_check->fetch_assoc();
        $price = floatval($equipment['equipment_price']);
        $depreciation = is_numeric($equipment['depreciation']) ? intval($equipment['depreciation']) : 0;
        $equipment_name = $con->real_escape_string($equipment['equipment_name']);
        
        // Calculate total based on depreciation
        $total = $price;
        if ($depreciation > 0) {
            $depr_per_day = $price / ($depreciation * 365);
            $total = $depr_per_day * $project_days;
        }
        
        // Check if equipment is already added to project
        $check_query = "SELECT id FROM project_add_equipment 
                       WHERE project_id = $project_id AND equipment_id = $equipment_id";
        $result = $con->query($check_query);
        
        if ($result && $result->num_rows > 0) {
            // Equipment exists, just update the timestamp
            $existing = $result->fetch_assoc();
            $existing_id = $existing['id'];
            
            $query = "UPDATE project_add_equipment 
                     SET total = $total,
                         added_at = NOW()
                     WHERE id = $existing_id";
        } else {
            // Equipment doesn't exist, insert new
            // Calculate total based on quantity
            $calculated_total = $total * $quantity;
            
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
        }
        
        // Execute the query
        error_log("Executing query: $query");
        
        if ($con->query($query)) {
            error_log("Successfully inserted equipment ID $equipment_id");
            
            // Update equipment status to Not Available
            $updateQuery = "UPDATE equipment SET status = 'Not Available', borrow_time = NOW() WHERE id = $equipment_id";
            error_log("Executing update query: $updateQuery");
            
            if ($con->query($updateQuery)) {
                error_log("Successfully updated equipment status for ID $equipment_id");
                $success_count++;
            } else {
                $error = $con->error;
                error_log("Failed to update equipment status for ID $equipment_id: $error");
                $error_messages[] = "Failed to update equipment status for ID $equipment_id: $error";
            }
        } else {
            $error = $con->error;
            error_log("Failed to add equipment ID $equipment_id: $error");
            $error_messages[] = "Failed to add equipment ID $equipment_id: $error";
        }
    } // End of foreach loop

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
    // Log the full error with trace for debugging
    error_log('Error in save_project_equipment.php: ' . $errorMessage . '\n' . $e->getTraceAsString());
    
    // Check for database errors
    if (isset($con) && $con->error) {
        $errorMessage .= '\nDatabase Error: ' . $con->error;
        error_log('Database Error: ' . $con->error);
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

// Close connection
if (isset($con)) {
    $con->close();
}
?>