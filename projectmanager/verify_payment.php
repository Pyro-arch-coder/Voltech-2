<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if payment_id is provided
if (!isset($_POST['payment_id']) || !is_numeric($_POST['payment_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
    exit;
}

// Check if action is provided
if (!isset($_POST['action']) || !in_array($_POST['action'], ['approve', 'reject'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$paymentId = intval($_POST['payment_id']);
$adminId = $_SESSION['user_id'] ?? 0;
$action = $_POST['action'];

try {
    // First, check if payment exists and get its current status
    $checkStmt = $con->prepare("
        SELECT id, status, project_id 
        FROM initial_b_proof_of_payment 
        WHERE id = ?
    ");
    $checkStmt->bind_param('i', $paymentId);
    $checkStmt->execute();
    $payment = $checkStmt->get_result()->fetch_assoc();
    
    if (!$payment) {
        throw new Exception('Payment not found');
    }
    
    // Check if already processed
    if (in_array($payment['status'], ['approved', 'rejected'])) {
        throw new Exception('Payment already ' . $payment['status']);
    }
    
    // Start transaction
    $con->begin_transaction();
    
    // First, get the project_id before any updates
    $projectStmt = $con->prepare("
        SELECT project_id 
        FROM initial_b_proof_of_payment 
        WHERE id = ?
    ");
    $projectStmt->bind_param('i', $paymentId);
    $projectStmt->execute();
    $projectResult = $projectStmt->get_result();
    
    if ($projectResult->num_rows === 0) {
        throw new Exception('Payment not found');
    }
    
    $project = $projectResult->fetch_assoc();
    $projectId = $project['project_id'];
    
    // Set status based on action
    $status = ($action === 'approve') ? 'approved' : 'rejected';
    
    if ($action === 'reject') {
        // For rejection, reset initial_budget to 0.00 in projects table
        $resetBudgetStmt = $con->prepare("
            UPDATE projects 
            SET initial_budget = 0.00
            WHERE project_id = ?
        ");
        $resetBudgetStmt->bind_param('i', $projectId);
        $resetBudgetStmt->execute();
        
        // Delete the payment proof entry
        $deleteStmt = $con->prepare("
            DELETE FROM initial_b_proof_of_payment 
            WHERE id = ?
        ");
        $deleteStmt->bind_param('i', $paymentId);
        $deleteStmt->execute();
        
    } else {
        // For approval, update the status
        $updateStmt = $con->prepare("
            UPDATE initial_b_proof_of_payment 
            SET status = ?
            WHERE id = ? AND status NOT IN ('approved', 'rejected')
        ");
        $updateStmt->bind_param('si', $status, $paymentId);
        $updateStmt->execute();
        
        if ($updateStmt->affected_rows === 0) {
            throw new Exception('Failed to update payment status');
        }
    }
    
    // Get project title and client email for notification
    $projectInfoStmt = $con->prepare("
        SELECT p.project, u.email 
        FROM projects p
        JOIN users u ON p.user_id = u.id
        WHERE p.project_id = ?
    ");
    $projectInfoStmt->bind_param('i', $projectId);
    $projectInfoStmt->execute();
    $projectInfo = $projectInfoStmt->get_result()->fetch_assoc();
    
    if ($projectInfo) {
        $projectTitle = $projectInfo['project'];
        $clientEmail = $projectInfo['email'];
        $notifType = ($action === 'approve') ? 'payment_approved' : 'payment_rejected';
        $message = 'Your payment for project ' . htmlspecialchars($projectTitle) . ' has been ' . 
                  ($action === 'approve' ? 'approved' : 'rejected') . '.';
        
        // Insert notification
        $notifStmt = $con->prepare("
            INSERT INTO notifications_client 
            (user_id, client_email, notif_type, message, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $notifStmt->bind_param('isss', $adminId, $clientEmail, $notifType, $message);
        $notifStmt->execute();
    }
    
    // Commit transaction
    $con->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment ' . ($action === 'approve' ? 'approved' : 'rejected') . ' successfully',
        'payment_id' => $paymentId
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $con->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to ' . ($action === 'approve' ? 'approve' : 'reject') . ' payment: ' . $e->getMessage()
    ]);
}

$con->close();
?>
