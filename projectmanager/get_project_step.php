<?php
session_start();
include_once "../config.php";
header('Content-Type: application/json');
$step = 1;
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}
if (isset($_GET['project_id'])) {
    $pid = intval($_GET['project_id']);
    $user_id = intval($_SESSION['user_id']);
    $res = $con->query("SELECT step_progress FROM projects WHERE project_id = $pid AND user_id = $user_id LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $step = intval($row['step_progress']);
        echo json_encode(["success" => true, "step_progress" => $step]);
        exit();
    }
}
echo json_encode(["success" => false, "step_progress" => $step]);
?>