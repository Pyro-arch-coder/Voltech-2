<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

// Check if required parameters are provided
if (!isset($_POST['payment_id']) || !isset($_POST['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$paymentId = intval($_POST['payment_id']);
$action = $_POST['action'] === 'approve' ? 'approved' : 'rejected';
$currentUserId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$currentTime = date('Y-m-d H:i:s');

// Start transaction
$con->begin_transaction();

try {
        // First, try to get the project ID from approved_payments
    $stmt = $con->prepare("SELECT project_id FROM approved_payments WHERE id = ?");
    $stmt->bind_param('i', $paymentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $paymentData = $result->fetch_assoc();
    
    // If not found in approved_payments, try proof_of_payments
    if (!$paymentData) {
        $stmt = $con->prepare("SELECT project_id FROM proof_of_payments WHERE id = ?");
        $stmt->bind_param('i', $paymentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $paymentData = $result->fetch_assoc();
        
        if (!$paymentData) {
            throw new Exception('Payment not found in either approved_payments or proof_of_payments');
        }
    }
    
    $projectId = $paymentData['project_id'];
    
    // Get project and client details
    $stmt = $con->prepare("
        SELECT 
            p.project_id,
            p.project as project_title,
            u.email as client_email,
            u.firstname as client_firstname,
            u.lastname as client_lastname
        FROM projects p
        JOIN users u ON p.user_id = u.id
        WHERE p.project_id = ?
    ");
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    if (!$data) {
        throw new Exception('Project details not found');
    }
    
    // First determine which table the payment is in
    $stmt = $con->prepare("SELECT id FROM approved_payments WHERE id = ?");
    $stmt->bind_param('i', $paymentId);
    $stmt->execute();
    $isInApprovedPayments = $stmt->get_result()->num_rows > 0;
    
    if ($isInApprovedPayments) {
        // Update the payment status in approved_payments
        $stmt = $con->prepare("
            UPDATE approved_payments 
            SET status = ?, 
                approved_by = ?, 
                approved_at = ? 
            WHERE id = ?
        ");
        $stmt->bind_param('sisi', $action, $currentUserId, $currentTime, $paymentId);
        $stmt->execute();
        
        // Find and update the corresponding record in proof_of_payments
        $stmt = $con->prepare("
            UPDATE proof_of_payments 
            SET status = ?
            WHERE project_id = ? AND status = 'pending'
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->bind_param('si', $action, $projectId);
        $stmt->execute();
    } else {
        // Update the payment status in proof_of_payments
        $stmt = $con->prepare("
            UPDATE proof_of_payments 
            SET status = ?
            WHERE id = ?
        ");
        $stmt->bind_param('si', $action, $paymentId);
        $stmt->execute();
        
        // Find and update the corresponding record in approved_payments
        $stmt = $con->prepare("
            UPDATE approved_payments 
            SET status = ?, 
                approved_by = ?, 
                approved_at = ?
            WHERE project_id = ? AND status = 'pending'
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->bind_param('sisi', $action, $currentUserId, $currentTime, $projectId);
        $stmt->execute();
    }
    
    // Get current user's name for the notification
    $currentUserStmt = $con->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
    $currentUserStmt->bind_param('i', $currentUserId);
    $currentUserStmt->execute();
    $currentUser = $currentUserStmt->get_result()->fetch_assoc();
    $currentUserStmt->close();
    
    $projectId = $data['project_id'];
    $projectTitle = $data['project_title'];
    $clientEmail = $data['client_email'];
    $adminName = $currentUser['firstname'] . ' ' . $currentUser['lastname'];
    $clientName = $data['client_firstname'] . ' ' . $data['client_lastname'];

    if ($action === 'approved') {
        // If approving, update the status in all related tables
        
        // 3. Update billing_requests table for the same project
        $stmt = $con->prepare("
            UPDATE billing_requests 
            SET payment_status = ?
            WHERE project_id = ? 
            AND payment_status = 'processing'
        ");
        $stmt->bind_param('si', $action, $projectId);
        $stmt->execute();

        // 4. Update proof_of_payments table
        $stmt = $con->prepare("
            UPDATE proof_of_payments 
            SET status = ?,
                updated_at = ?
            WHERE project_id = ?
        ");
        $stmt->bind_param('ssi', $action, $currentTime, $projectId);
        $stmt->execute();
    } else {
        // If rejecting, remove the payment records and reset billing requests
        
        // 3. Set billing_requests back to 'pending' and clear approval data
        $pendingStatus = 'pending';
        $stmt = $con->prepare("
            UPDATE billing_requests 
            SET payment_status = ?,
            WHERE project_id = ? 
            AND payment_status = 'processing'
        ");
        $stmt->bind_param('si', $pendingStatus, $projectId);
        $stmt->execute();

        // 4. Delete from approved_payments
        $stmt = $con->prepare("DELETE FROM approved_payments WHERE project_id = ?");
        $stmt->bind_param('i', $projectId);
        $stmt->execute();

        // 5. Delete from proof_of_payments
        $stmt = $con->prepare("DELETE FROM proof_of_payments WHERE project_id = ?");
        $stmt->bind_param('i', $projectId);
        $stmt->execute();
    }

    // Prepare notification message
    $message = "Your payment for project \"{$projectTitle}\" has been {$action} by {$adminName}.";
    $notifType = ($action === 'approved') ? 'payment_approved' : 'payment_rejected';
    
    // Insert notification
    $notifStmt = $con->prepare("
        INSERT INTO notifications_client 
        (user_id, client_email, notif_type, message, created_at) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $notifStmt->bind_param('issss', 
        $currentUserId, 
        $clientEmail, 
        $notifType, 
        $message,
        $currentTime
    );
    $notifStmt->execute();

    // Commit transaction
    $con->commit();

    echo json_encode([
        'success' => true,
        'message' => "Payment {$action} successfully",
        'status' => $action,
        'notification_sent' => true,
        'client_name' => $clientName
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $con->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error updating payment: ' . $e->getMessage()
    ]);
}

$con->close();
?>
