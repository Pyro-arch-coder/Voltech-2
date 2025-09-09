<?php
// Disable error display to prevent HTML output
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Include config
require_once '../config.php';

// Initialize response
$response = ['success' => false, 'message' => 'An error occurred'];

try {
    // Validate user session and permissions
    if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
        throw new Exception('Unauthorized access');
    }

    // Validate input
    $projectId = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
    $budget = isset($_POST['budget']) ? floatval($_POST['budget']) : 0;
    $status = 'Pending'; // Default status

    if ($projectId <= 0) {
        throw new Exception('Invalid project ID');
    }
    if ($budget <= 0) {
        throw new Exception('Budget amount must be greater than zero');
    }

    // Begin transaction
    $con->begin_transaction();

    try {
        // Check if budget approval already exists for this project
        $checkStmt = $con->prepare("SELECT id FROM project_budget_approval WHERE project_id = ?");
        $checkStmt->bind_param("i", $projectId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $checkStmt->close();

        if ($checkResult && $checkResult->num_rows > 0) {
            // Update existing budget approval
            $stmt = $con->prepare("UPDATE project_budget_approval SET budget = ?, status = ?, updated_at = NOW() WHERE project_id = ?");
            $stmt->bind_param("dsi", $budget, $status, $projectId);
        } else {
            // Insert new budget approval
            $stmt = $con->prepare("INSERT INTO project_budget_approval (project_id, budget, status, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("ids", $projectId, $budget, $status);
        }

        if (!$stmt->execute()) {
            throw new Exception('Failed to save budget approval: ' . $stmt->error);
        }
        $stmt->close();

        // Get project and client details for notification
        $projectStmt = $con->prepare("SELECT user_id, project, client_email FROM projects WHERE project_id = ?");
        $projectStmt->bind_param("i", $projectId);
        $projectStmt->execute();
        $projectResult = $projectStmt->get_result();
        
        if ($projectRow = $projectResult->fetch_assoc()) {
            $user_id = $projectRow['user_id'];
            $project_name = $projectRow['project'];
            $client_email = $projectRow['client_email'];
            
            // Insert notification
            $notifType = 'budget_approval_request';
            $message = "A new budget approval request has been submitted for project: $project_name";
            
            $notifStmt = $con->prepare("INSERT INTO notifications_client (user_id, client_email, notif_type, message) VALUES (?, ?, ?, ?)");
            $notifStmt->bind_param("isss", $user_id, $client_email, $notifType, $message);
            $notifStmt->execute();
            $notifStmt->close();
        }
        $projectStmt->close();

        // Commit transaction
        $con->commit();

        $response['success'] = true;
        $response['message'] = 'Budget approval request submitted successfully';
    } catch (Exception $e) {
        // Rollback transaction on error
        $con->rollback();
        throw $e;
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>