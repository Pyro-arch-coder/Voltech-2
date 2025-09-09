<?php
require_once 'config.php';
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$response = null;
if ($project_id && $user_id) {
    $sql = "SELECT * FROM billing_requests WHERE project_id = ? AND user_id = ? ORDER BY request_date DESC LIMIT 1";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("ii", $project_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $response = $result->fetch_assoc();
}
header('Content-Type: application/json');
echo json_encode($response);
