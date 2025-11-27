<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

try {
    $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

    if ($project_id <= 0) {
        throw new Exception('Invalid project ID');
    }

    $stmt = $con->prepare("SELECT contract_signing_datetime FROM projects WHERE project_id = ?");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $con->error);
    }
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $stmt->bind_result($datetime);
    $stmt->fetch();
    $stmt->close();

    if (!$datetime) {
        echo json_encode([
            'success' => true,
            'schedule' => null,
        ]);
        exit;
    }

    $dt = new DateTime($datetime);

    echo json_encode([
        'success' => true,
        'schedule' => [
            'date' => $dt->format('Y-m-d'),
            'time' => $dt->format('H:i'),
            'display' => $dt->format('F j, Y g:i A'),
        ],
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}


