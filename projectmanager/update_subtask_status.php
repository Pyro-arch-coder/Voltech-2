<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
include_once "../config.php";

header('Content-Type: application/json');

// Log the incoming request for debugging
file_put_contents('subtask_update.log', "[" . date('Y-m-d H:i:s') . "] " . json_encode($_POST) . "\n", FILE_APPEND);

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    $error = 'Unauthorized access - User not logged in or insufficient permissions';
    file_put_contents('subtask_update.log', $error . "\n", FILE_APPEND);
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => $error]);
    exit();
}

// Validate input
if (!isset($_POST['subtask_id']) || !isset($_POST['is_completed'])) {
    $error = 'Missing required parameters: ' . json_encode($_POST);
    file_put_contents('subtask_update.log', $error . "\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $error]);
    exit();
}

$subtask_id = intval($_POST['subtask_id']);
$is_completed = intval($_POST['is_completed']) ? 1 : 0;

// Log the values being used
file_put_contents('subtask_update.log', "Updating subtask $subtask_id to status: $is_completed\n", FILE_APPEND);

try {
    // First, check if the subtask exists
    $checkStmt = $con->prepare("SELECT id FROM project_subtask WHERE id = ?");
    $checkStmt->bind_param('i', $subtask_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Subtask with ID $subtask_id not found");
    }
    
    // Update the subtask status
    $updateStmt = $con->prepare("UPDATE project_subtask SET is_completed = ? WHERE id = ?");
    if ($updateStmt === false) {
        throw new Exception('Prepare failed: ' . $con->error);
    }
    
    $updateStmt->bind_param('ii', $is_completed, $subtask_id);
    
    if ($updateStmt->execute()) {
        $rows_affected = $updateStmt->affected_rows;
        file_put_contents('subtask_update.log', "Update successful. Rows affected: $rows_affected\n", FILE_APPEND);
        
        if ($rows_affected > 0) {
            // Get the division_id for this subtask
            $divQuery = $con->prepare("SELECT division_id FROM project_subtask WHERE id = ?");
            $divQuery->bind_param('i', $subtask_id);
            $divQuery->execute();
            $divResult = $divQuery->get_result();
            
            if ($divRow = $divResult->fetch_assoc()) {
                $division_id = $divRow['division_id'];
                
                // Get all subtasks and their completion status
                $progressQuery = $con->prepare("
                    SELECT id, is_completed 
                    FROM project_subtask 
                    WHERE division_id = ?
                    ORDER BY id ASC  -- Ensure consistent ordering
                ");
                $progressQuery->bind_param('i', $division_id);
                $progressQuery->execute();
                $subtasks = $progressQuery->get_result()->fetch_all(MYSQLI_ASSOC);
                
                if (!empty($subtasks)) {
                    $total_tasks = count($subtasks);
                    $completed_tasks = array_sum(array_column($subtasks, 'is_completed'));
                    
                    // Calculate fixed percentage per subtask
                    $percentage_per_task = 100 / $total_tasks;
                    $new_progress = round($completed_tasks * $percentage_per_task);
                    
                    // Ensure progress is between 0 and 100
                    $new_progress = max(0, min(100, $new_progress));
                    
                    file_put_contents('subtask_update.log', "Fixed percentage calculation: $completed_tasks/$total_tasks tasks = $new_progress% ($percentage_per_task% per task)\n", FILE_APPEND);
                    
                    // Update the division's progress
                    $updateProgress = $con->prepare("UPDATE project_divisions SET progress = ? WHERE id = ?");
                    $updateProgress->bind_param('ii', $new_progress, $division_id);
                    $updateProgress->execute();
                    
                    file_put_contents('subtask_update.log', "Updated division $division_id progress to $new_progress% ($completed_tasks/$total_tasks tasks completed)\n", FILE_APPEND);
                }
            }
        } else {
            // No rows were updated, possibly because the value was the same
            file_put_contents('subtask_update.log', "No rows updated. Value might be the same.\n", FILE_APPEND);
        }
        
        echo json_encode([
            'success' => true,
            'rows_affected' => $rows_affected,
            'subtask_id' => $subtask_id,
            'is_completed' => $is_completed,
            'new_progress' => $new_progress ?? null
        ]);
    } else {
        throw new Exception('Execute failed: ' . $updateStmt->error);
    }
} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
    file_put_contents('subtask_update.log', $error . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $error,
        'error_details' => $con->error ?? 'No database error'
    ]);
}

if (isset($con)) {
    $con->close();
}
?>
