<?php
// update_project_step.php
session_start();
include_once "../config.php";
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
    $new_step = isset($_POST['new_step']) ? intval($_POST['new_step']) : null;
    $user_id = intval($_SESSION['user_id']);
    if (!$project_id || $new_step === null) {
        echo json_encode(["success" => false, "message" => "Missing parameters"]);
        exit();
    }
    // Only update if user owns the project
    $stmt = $con->prepare("UPDATE projects SET step_progress=?, progress_indicator=? WHERE project_id=? AND user_id=?");
    $stmt->bind_param('iiii', $new_step, $new_step, $project_id, $user_id);
    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to update project step"]);
    }
    $stmt->close();
    exit();
}
echo json_encode(["success" => false, "message" => "Invalid request"]);
exit();