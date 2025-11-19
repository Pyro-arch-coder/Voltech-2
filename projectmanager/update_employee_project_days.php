<?php
session_start();
require_once '../config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and has the right permissions
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if required parameters are provided
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get and validate input
$record_id = isset($_POST['record_id']) ? (int)$_POST['record_id'] : 0;
$project_days = isset($_POST['project_days']) ? max(0, (int)$_POST['project_days']) : 0;

if ($record_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid record ID']);
    exit();
}

try {
    // Get the employee's daily rate
    $query = "SELECT id, daily_rate, project_days, total 
              FROM project_estimation_employee 
              WHERE id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("i", $record_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Record not found');
    }

    $record = $result->fetch_assoc();
    $daily_rate = (float)$record['daily_rate'];
    
    // Calculate new total
    $total = $daily_rate * $project_days;
    
    // Start transaction
    $con->begin_transaction();
    
    // Update the project days and total in the database
    $update_query = "UPDATE project_estimation_employee
                    SET project_days = ?, 
                        total = ?
                    WHERE id = ?";
    $update_stmt = $con->prepare($update_query);
    $update_stmt->bind_param("idi", $project_days, $total, $record_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to update record');
    }
    
    // Commit transaction
    $con->commit();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'total' => number_format($total, 2, '.', ''),
        'formatted_total' => '₱' . number_format($total, 2),
        'project_days' => $project_days
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($con) && $con) {
        $con->rollback();
    }
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

// Close connections
if (isset($update_stmt)) $update_stmt->close();
if (isset($stmt)) $stmt->close();
if (isset($con)) $con->close();
?>
