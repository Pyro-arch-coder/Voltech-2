<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        throw new Exception('Unauthorized');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
    $date = isset($_POST['date']) ? trim($_POST['date']) : '';
    $time = isset($_POST['time']) ? trim($_POST['time']) : '';

    if ($project_id <= 0) {
        throw new Exception('Invalid project ID');
    }

    if (!$date || !$time) {
        throw new Exception('Both date and time are required');
    }

    // Basic validation of date and time formats
    $date_obj = DateTime::createFromFormat('Y-m-d', $date);
    $time_obj = DateTime::createFromFormat('H:i', $time);

    if (!$date_obj || !$time_obj) {
        throw new Exception('Invalid date or time format');
    }

    $datetime_str = $date . ' ' . $time . ':00';

    $stmt = $con->prepare("UPDATE projects SET contract_signing_datetime = ? WHERE project_id = ?");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $con->error);
    }
    $stmt->bind_param('si', $datetime_str, $project_id);

    if (!$stmt->execute()) {
        throw new Exception('Failed to save schedule: ' . $stmt->error);
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Schedule saved successfully',
        'datetime' => $datetime_str,
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}


