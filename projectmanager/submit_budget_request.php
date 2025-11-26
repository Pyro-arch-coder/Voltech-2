<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in and is a project manager
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$project_manager_id = $_SESSION['user_id'];
$project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
$budget_amount = isset($_POST['budget_amount']) ? floatval($_POST['budget_amount']) : 0;

if ($project_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid project ID']);
    exit();
}

if ($budget_amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid budget amount']);
    exit();
}

try {
    // Check if project exists and get project details
    $checkProject = "SELECT project_id, client_email, budget, initial_budget, total_estimation_cost FROM projects WHERE project_id = ?";
    $stmt = $con->prepare($checkProject);
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Project not found']);
        exit();
    }
    
    $project = $result->fetch_assoc();
    $client_email = $project['client_email'];
    $project_budget = floatval($project['budget']);
    $initial_budget = floatval($project['initial_budget'] ?? 0);
    $total_estimation_cost = floatval($project['total_estimation_cost']);
    
    // Validation: Budget amount cannot exceed project budget
    if ($budget_amount > $project_budget) {
        echo json_encode([
            'success' => false, 
            'message' => 'Budget amount cannot exceed project budget (₱' . number_format($project_budget, 2) . ').'
        ]);
        exit();
    }
    
    // Validation 3: Check if request will cause negative remaining balance (utang)
    // Get total completed payments
    
    // Get total completed payments
    $stmt = $con->prepare("SELECT COALESCE(SUM(amount),0) FROM approved_payments WHERE project_id = ? AND status = 'completed'");
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $stmt->bind_result($total_completed_payments);
    $stmt->fetch();
    $stmt->close();
    
    $total_paid = $initial_budget + $total_completed_payments;
    $new_total_paid = $total_paid + $budget_amount;
    $new_remaining_balance = $project_budget - $new_total_paid;
    
    if ($new_remaining_balance < 0) {
        $excess_amount = abs($new_remaining_balance);
        echo json_encode([
            'success' => false, 
            'message' => 'Sorry, you need to lower your request. This request will exceed the project budget by ₱' . number_format($excess_amount, 2) . ' and will result in a negative remaining balance.'
        ]);
        exit();
    }
    
    // Check if there's already a pending budget request for this project
    $checkPending = "SELECT id FROM billing_requests WHERE project_id = ? AND status = 'pending'";
    $stmt = $con->prepare($checkPending);
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $pendingResult = $stmt->get_result();
    
    if ($pendingResult->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot submit new budget request. There is already a pending request for this project.']);
        exit();
    }
    
    // Always insert new record to allow multiple budget requests
    $insertQuery = "INSERT INTO billing_requests (project_id, user_id, amount, request_date, status) 
                   VALUES (?, ?, ?, NOW(), 'pending')";
    $stmt = $con->prepare($insertQuery);
    $stmt->bind_param('iid', $project_id, $project_manager_id, $budget_amount);
    
    if ($stmt->execute()) {
        // Insert notification into notifications_client table
        $notificationMessage = "A new budget request has been submitted for your project. Amount: ₱" . number_format($budget_amount, 2);
        $insertNotification = "INSERT INTO notifications_client (user_id, client_email, notif_type, message, is_read, created_at) 
                             VALUES (?, ?, 'budget_request', ?, 0, NOW())";
        $stmt = $con->prepare($insertNotification);
        $stmt->bind_param('iss', $project_manager_id, $client_email, $notificationMessage);
        $stmt->execute();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Budget request submitted successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit budget request']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$con->close();
?>
