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

// Check if we have tasks_data (for multiple tasks) or single task data
$tasksData = [];
if (!empty($data['tasks_data'])) {
    // Multiple tasks in JSON format
    $tasksData = json_decode($data['tasks_data'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(false, 'Invalid tasks data format');
    }
    
    // Validate each task
    foreach ($tasksData as $index => $task) {
        $required = ['task_id', 'task_name', 'start_date', 'end_date'];
        $missing = [];
        foreach ($required as $field) {
            if (empty($task[$field])) {
                $missing[] = $field;
            }
        }
        
        if ($missing) {
            sendResponse(false, sprintf('Task #%d is missing required fields: %s', $index + 1, implode(', ', $missing)));
        }
    }
} else {
    // Single task (legacy support)
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
    
    // Convert single task to array format for unified processing
    $tasksData[] = [
        'task_id' => $data['task_id'] ?? null,
        'task_name' => $data['task_name'],
        'start_date' => $data['start_date'],
        'end_date' => $data['end_date']
    ];
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

        // Begin transaction for multiple inserts
    $con->begin_transaction();
    $savedCount = 0;
    $errors = [];
    
    try {
        $project_id = $data['project_id'];
        
        foreach ($tasksData as $task) {
            $task_id = $task['task_id'];
            $task_name = $task['task_name'];
            $description = $task['description'] ?? '';
            $start_date = $task['start_date'];
            $end_date = $task['end_date']; // Already calculated in frontend

            // Check if task_id already exists for this project
            $checkStmt = $con->prepare("SELECT id FROM project_timeline WHERE project_id = ? AND task_id = ?");
            $checkStmt->bind_param("ii", $project_id, $task_id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $checkStmt->close();
                sendResponse(false, sprintf('A task with the name "%s" already exists in this project.', $task_name));
            }
            $checkStmt->close();

            $stmt = $con->prepare("INSERT INTO project_timeline 
                (project_id, task_id, task_name, start_date, end_date, status, progress, created_at) 
                VALUES (?, ?, ?, ?, ?, 'Not Started', 0, NOW())");

            if (!$stmt) {
                throw new Exception('Failed to prepare statement: ' . $con->error);
            }

            $stmt->bind_param(
                'iisss',
                $project_id,
                $task_id,
                $task_name,
                $start_date,
                $end_date
            );

            if (!$stmt->execute()) {
                throw new Exception('Failed to save task "' . $task_name . '": ' . $stmt->error);
            }
            
            $savedCount++;
            $lastInsertId = $con->insert_id;
            
            // Copy subtasks from subtasks table to project_subtask
            $copySubtasksStmt = $con->prepare("
                INSERT INTO project_subtask (project_timeline_id, name, created_at, updated_at, is_completed)
                SELECT ?, s.name, NOW(), NOW(), 0 
                FROM subtasks s 
                WHERE s.task_id = ?
                AND NOT EXISTS (
                    SELECT 1 FROM project_subtask ps 
                    WHERE ps.project_timeline_id = ? 
                    AND ps.name = s.name
                )
            ");
            
            if ($copySubtasksStmt) {
                $copySubtasksStmt->bind_param("iii", $lastInsertId, $task_id, $lastInsertId);
                if (!$copySubtasksStmt->execute()) {
                    error_log('Failed to copy subtasks: ' . $copySubtasksStmt->error);
                    // Continue even if subtask copy fails
                }
                $copySubtasksStmt->close();
            } else {
                error_log('Failed to prepare subtask copy statement: ' . $con->error);
            }
            
            $stmt->close();
            
            // Notification logic for each task
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
        }
        
        // Commit the transaction if all inserts were successful
        $con->commit();
        
        sendResponse(true, "Successfully saved {$savedCount} schedule items", ['count' => $savedCount]);
        
    } catch (Exception $e) {
        // Rollback the transaction on error
        $con->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log('Schedule Save Error: ' . $e->getMessage());
    sendResponse(false, 'Error saving schedule: ' . $e->getMessage(), [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>