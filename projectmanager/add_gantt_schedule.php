<?php
session_start();
require_once '../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get POST data
$project_id = $_POST['project_id'] ?? null;
$category   = $_POST['category'] ?? null;
$start_date = $_POST['start_date'] ?? null;
$end_date   = $_POST['end_date'] ?? null;

// Validate required fields
if (!$project_id || !$category || !$start_date || !$end_date) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Validate dates
if (strtotime($end_date) <= strtotime($start_date)) {
    echo json_encode(['success' => false, 'message' => 'End date must be after start date']);
    exit;
}

try {
    // Check if project exists and belongs to this user
    $check_project = "SELECT project_id FROM projects WHERE project_id = ? AND user_id = ?";
    $stmt = $con->prepare($check_project);
    $stmt->bind_param("ii", $project_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Project not found or access denied']);
        exit;
    }

    $stmt->close();

    // Insert schedule into project_schedule
    $insert_query = "INSERT INTO project_schedule (project_id, category, start_date, end_date) VALUES (?, ?, ?, ?)";
    $stmt = $con->prepare($insert_query);
    $stmt->bind_param("isss", $project_id, $category, $start_date, $end_date);

    if ($stmt->execute()) {
        $schedule_id = $con->insert_id;
        echo json_encode([
            'success' => true,
            'message' => 'Schedule added successfully',
            'data' => [
                'id'         => $schedule_id,
                'project_id' => $project_id,
                'category'   => $category,
                'start_date' => $start_date,
                'end_date'   => $end_date
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add schedule']);
    }

} catch (Exception $e) {
    error_log("Error adding schedule: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while adding schedule']);
}

if (isset($stmt)) {
    $stmt->close();
}
$con->close();
