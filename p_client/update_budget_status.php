<?php
session_start();
header('Content-Type: application/json');
require_once '../config.php';

// Check if user is logged in and is a client, and has an email
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 6 || !isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Validate input
if (!isset($_POST['project_id']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$project_id = (int)$_POST['project_id'];
$status = $_POST['status'];

// Validate status
$allowed_statuses = ['Approved', 'Rejected'];
if (!in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

// Check if the project exists and belongs to this client
$check_sql = "SELECT p.project_id, p.user_id, p.project, p.client_email
              FROM projects p
              WHERE p.project_id = ? AND p.client_email = ?";
$stmt1 = $con->prepare($check_sql);
if (!$stmt1) {
    echo json_encode(['success' => false, 'message' => 'Database error: prepare failed']);
    exit();
}
$stmt1->bind_param('is', $project_id, $_SESSION['email']);
if (!$stmt1->execute()) {
    echo json_encode(['success' => false, 'message' => 'Database error: execute failed']);
    exit();
}
$result1 = $stmt1->get_result();
if ($result1->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Project not found or access denied']);
    exit();
}
$project_row = $result1->fetch_assoc();
$stmt1->close();

// Check if budget approval record exists
$check_budget_sql = "SELECT budget FROM project_budget_approval WHERE project_id = ?";
$stmt2 = $con->prepare($check_budget_sql);
$stmt2->bind_param('i', $project_id);
$stmt2->execute();
$result2 = $stmt2->get_result();

if ($result2->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'No budget record found for this project.']);
    exit();
}
$budget_row = $result2->fetch_assoc();
$stmt2->close();

// Defensive: handle null/empty budget
$budget_val = (isset($budget_row['budget']) && is_numeric($budget_row['budget'])) ? $budget_row['budget'] : 0;
$budget_amount = '₱' . number_format($budget_val, 2);

if ($status === 'Approved') {
    // Start transaction
    $con->begin_transaction();
    
    try {
        // Update budget approval status
        $update_sql = "UPDATE project_budget_approval SET status = ?, updated_at = NOW() WHERE project_id = ?";
        $stmt3 = $con->prepare($update_sql);
        if (!$stmt3) {
            throw new Exception('Database error: prepare failed (update budget approval)');
        }
        $stmt3->bind_param('si', $status, $project_id);
        if (!$stmt3->execute()) {
            throw new Exception('Database error: execute failed (update budget approval)');
        }
        $stmt3->close();
        
        // Update projects table with the approved budget
        $update_project_sql = "UPDATE projects SET budget = ? WHERE project_id = ?";
        $stmt4 = $con->prepare($update_project_sql);
        if (!$stmt4) {
            throw new Exception('Database error: prepare failed (update projects)');
        }
        $stmt4->bind_param('di', $budget_val, $project_id);
        if (!$stmt4->execute()) {
            throw new Exception('Database error: execute failed (update projects)');
        }
        $stmt4->close();
        
        // Commit the transaction
        $con->commit();
    } catch (Exception $e) {
        // Rollback the transaction in case of any error
        $con->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
} elseif ($status === 'Rejected') {
    // Start transaction for rejection
    $con->begin_transaction();
    
    try {
        // Delete the budget approval entry
        $delete_sql = "DELETE FROM project_budget_approval WHERE project_id = ?";
        $stmt3 = $con->prepare($delete_sql);
        if (!$stmt3) {
            throw new Exception('Database error: prepare failed (delete budget approval)');
        }
        $stmt3->bind_param('i', $project_id);
        if (!$stmt3->execute()) {
            throw new Exception('Database error: execute failed (delete budget approval)');
        }
        $stmt3->close();
        
        // Set budget to NULL in projects table when rejected
        $update_project_sql = "UPDATE projects SET budget = NULL WHERE project_id = ?";
        $stmt4 = $con->prepare($update_project_sql);
        if (!$stmt4) {
            throw new Exception('Database error: prepare failed (update projects on reject)');
        }
        $stmt4->bind_param('i', $project_id);
        if (!$stmt4->execute()) {
            throw new Exception('Database error: execute failed (update projects on reject)');
        }
        $stmt4->close();
        
        // Commit the transaction
        $con->commit();
    } catch (Exception $e) {
        // Rollback the transaction in case of any error
        $con->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// Insert notification for the project manager if status is Approved or Rejected
$pm_user_id = $project_row['user_id'];
$project_name = $project_row['project'];
$notif_type = "Budget $status";
if ($status === 'Rejected') {
    $message = "Budget of {$budget_amount} for project " . htmlspecialchars($project_name) . "has been Rejected by the client and the entry has been removed.";
} else {
    $message = "Budget for project " . htmlspecialchars($project_name) . " has been Approved by the client.";
}
$insert_notif = $con->prepare("INSERT INTO notifications_projectmanager (user_id, notif_type, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
if ($insert_notif) {
    $insert_notif->bind_param('iss', $pm_user_id, $notif_type, $message);
    $insert_notif->execute();
    $insert_notif->close();
}

echo json_encode([
    'success' => true,
    'message' => $status === 'Rejected'
        ? 'Budget entry rejected and removed successfully'
        : 'Budget status updated successfully'
]);

$con->close();
?>