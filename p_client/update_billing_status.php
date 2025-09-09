<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] != true || $_SESSION['user_level'] != 6) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../config.php';

// Suppress errors to prevent JSON corruption
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
ob_clean();

header('Content-Type: application/json');

try {
    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $billing_id = isset($_POST['billing_id']) ? intval($_POST['billing_id']) : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : ''; // 'approve' or 'reject'
    $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;

    if (!$billing_id || !$action || !$project_id) {
        throw new Exception('Missing required parameters');
    }

    if (!in_array($action, ['approve', 'reject'])) {
        throw new Exception('Invalid action');
    }

    // Check if user has access to this project
    $user_id = $_SESSION['user_id'];
    $check_query = "SELECT client_email FROM projects WHERE project_id = ? AND client_email = (SELECT email FROM users WHERE id = ?)";
    $check_stmt = $con->prepare($check_query);
    $check_stmt->bind_param("ii", $project_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        throw new Exception('Access denied to this project');
    }
    $check_stmt->close();

    // Check if billing request exists and is pending, and get the project manager's user_id
    $check_billing = "SELECT br.id, br.status, br.user_id, br.amount, p.project
                      FROM billing_requests br 
                      JOIN projects p ON br.project_id = p.project_id 
                      WHERE br.id = ? AND br.project_id = ? AND br.status = 'pending'";
    $check_billing_stmt = $con->prepare($check_billing);
    $check_billing_stmt->bind_param("ii", $billing_id, $project_id);
    $check_billing_stmt->execute();
    $billing_result = $check_billing_stmt->get_result();
    
    if ($billing_result->num_rows === 0) {
        throw new Exception('Billing request not found or already processed');
    }
    
    $billing_data = $billing_result->fetch_assoc();
    $project_manager_id = $billing_data['user_id']; // This is the user_id of the project manager
    $project_name = $billing_data['project_name'];
    $amount = $billing_data['amount'];
    $check_billing_stmt->close();

    // Start transaction
    $con->begin_transaction();
    
    try {
        // Update the billing request status with approved_date and approved_by_id
        $new_status = ($action === 'approve') ? 'approved' : 'rejected';
        $update_query = "UPDATE billing_requests 
                        SET status = ?, 
                            approved_date = NOW(),
                            approved_by_id = ?
                        WHERE id = ? AND project_id = ?";
        
        $update_stmt = $con->prepare($update_query);
        $update_stmt->bind_param("siii", $new_status, $user_id, $billing_id, $project_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception('Failed to update billing request status: ' . $update_stmt->error);
        }

        $update_stmt->close();

    // Insert notification for the project manager
    $notification_message = "Billing request for project '{$project_name}' (₱" . number_format($amount, 2) . ") has been " . $new_status . " by the client.";
    $notification_type = "Billing " . ucfirst($new_status);
    
    $insert_notification = "INSERT INTO notifications_projectmanager (user_id, notif_type, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())";
    $notification_stmt = $con->prepare($insert_notification);
    $notification_stmt->bind_param("iss", $project_manager_id, $notification_type, $notification_message);
    
    if (!$notification_stmt->execute()) {
        error_log("Warning: Failed to insert notification for project manager {$project_manager_id}");
    } else {
        error_log("Notification sent to project manager {$project_manager_id}: {$notification_message}");
    }
    
        $notification_stmt->close();

        // Commit the transaction if all operations succeed
        $con->commit();

        // Log the successful action with more details
        error_log(sprintf(
            "Billing request %d %s by client user %d for project %d. Amount: %.2f, New Status: %s",
            $billing_id,
            $action,
            $user_id,
            $project_id,
            $amount,
            $new_status
        ));

        $response = [
            'success' => true,
            'message' => 'Billing request ' . $action . 'd successfully',
            'data' => [
                'status' => ucfirst($new_status),
                'approved_date' => date('Y-m-d H:i:s'),
                'approved_by' => $user_id
            ]
        ];
    } catch (Exception $e) {
        // Rollback the transaction in case of error
        $con->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error updating billing status: " . $e->getMessage());
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

if (isset($con)) {
    $con->close();
}

echo json_encode($response);
?>
