<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

function sendResponse($success, $message, $data = []) {
    http_response_code($success ? 200 : 500);
    echo json_encode(['success' => $success, 'message' => $message] + $data);
    exit();
}

session_start();

if (!isset($_SESSION['user_id'])) {
    sendResponse(false, 'Please log in to continue');
}

$json = file_get_contents('php://input');
$data = json_decode($json, true) ?: $_POST;

$required = ['project_id', 'task_name', 'start_date', 'end_date'];
$missing = [];
foreach ($required as $field) {
    if (empty($data[$field])) {
        $missing[] = $field;
    }
}

if ($missing) {
    sendResponse(false, 'Missing required fields: ' . implode(', ', $missing));
}

require_once '../config.php';

try {
    if (!isset($con) || !$con || $con->connect_error) {
        throw new Exception('Database connection failed: ' . ($con->connect_error ?? 'No connection'));
    }

    $result = $con->query("SELECT 1 FROM project_timeline LIMIT 1");
    if ($result === false) {
        throw new Exception('Table project_timeline might not exist or has no permissions');
    }

    // Assign to variables
    $project_id = $data['project_id'];
    $task_name = $data['task_name'];
    $description = isset($data['description']) ? $data['description'] : '';
    $start_date = $data['start_date'];
    $end_date = $data['end_date'];

    $stmt = $con->prepare("INSERT INTO project_timeline 
        (project_id, task_name, description, start_date, end_date, status, progress, created_at) 
        VALUES (?, ?, ?, ?, ?, 'Not Started', 0, NOW())");

    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $con->error);
    }

    $stmt->bind_param(
        'issss',
        $project_id,
        $task_name,
        $description,
        $start_date,
        $end_date
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to save: ' . $stmt->error);
    }

    // --- ADD NOTIFICATION LOGIC HERE ---
    // Get client_email for this project
    $client_email = '';
    $user_id = null;
    $stmt_proj = $con->prepare("SELECT client_email, user_id FROM projects WHERE project_id = ?");
    $stmt_proj->bind_param("i", $project_id);
    $stmt_proj->execute();
    $stmt_proj->bind_result($client_email, $user_id);
    $stmt_proj->fetch();
    $stmt_proj->close();

    if ($client_email && $user_id) {
        $notif_type = 'timeline_update';
        $message = "A new schedule item ('{$task_name}') has been added to your project.";
        $is_read = 0;

        $stmt_notif = $con->prepare(
            "INSERT INTO notifications_client (user_id, client_email, notif_type, message, is_read, created_at) VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $stmt_notif->bind_param(
            "isssi",
            $user_id,
            $client_email,
            $notif_type,
            $message,
            $is_read
        );
        $stmt_notif->execute();
        $stmt_notif->close();
    }
    // --- END NOTIFICATION LOGIC ---

    sendResponse(true, 'Schedule item saved', ['id' => $stmt->insert_id]);

} catch (Exception $e) {
    error_log('Schedule Save Error: ' . $e->getMessage());
    sendResponse(false, 'Error saving schedule: ' . $e->getMessage(), [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>