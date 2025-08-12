<?php
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Do not display errors to the user

session_start();
require_once '../config.php';

// Helper function to send a JSON response and exit
function sendJsonResponse($success, $message = '') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit();
}

// Check login and permissions
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    sendJsonResponse(false, 'Unauthorized access');
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Invalid request method');
}

// Get and validate input
$project_id = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
$start_date = filter_input(INPUT_POST, 'start_date', FILTER_SANITIZE_STRING);
$end_date = filter_input(INPUT_POST, 'end_date', FILTER_SANITIZE_STRING);

// Validate required fields
if (!$project_id || !$start_date || !$end_date) {
    sendJsonResponse(false, 'All fields are required');
}

// Validate date format and logic
$start_timestamp = strtotime($start_date);
$end_timestamp = strtotime($end_date);

if (!$start_timestamp || !$end_timestamp) {
    sendJsonResponse(false, 'Invalid date format');
}

if ($end_timestamp < $start_timestamp) {
    sendJsonResponse(false, 'End date cannot be before start date');
}

try {
    // Prepare the SQL statement
    $stmt = $con->prepare("UPDATE projects SET start_date = ?, deadline = ? WHERE project_id = ?");
    if (!$stmt) {
        throw new Exception("Database error: " . $con->error);
    }
    $stmt->bind_param("ssi", $start_date, $end_date, $project_id);
    $result = $stmt->execute();

    if ($result) {
        sendJsonResponse(true, 'Project dates updated successfully');
    } else {
        throw new Exception("Failed to update project dates: " . ($con->error ?? 'Unknown error'));
    }

    $stmt->close();
} catch (Exception $e) {
    error_log("Error updating project dates: " . $e->getMessage());
    sendJsonResponse(false, 'An error occurred while updating project dates: ' . $e->getMessage());
} finally {
    if (isset($con)) {
        $con->close();
    }
}
?>