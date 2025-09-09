<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

// Check if project_id is provided
if (!isset($_GET['project_id']) || !is_numeric($_GET['project_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid project ID']);
    exit;
}

$projectId = intval($_GET['project_id']);
$response = [];

try {
    // Query to get payment verification details
    $stmt = $con->prepare("
        SELECT 
            p.*,
            p.initial_budget_payment as amount
        FROM 
            initial_b_proof_of_payment p
        WHERE 
            p.project_id = ?
        ORDER BY 
            p.upload_date DESC
    ");
    
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    while ($row = $result->fetch_assoc()) {
        $payments[] = [
            'id' => $row['id'],
            'project_id' => $row['project_id'],
            'file_name' => $row['file_name'],
            'file_path' => $row['file_path'],
            'upload_date' => $row['upload_date'],
            'status' => $row['status'],
            'payment_type' => $row['payment_type'],
            'amount' => $row['amount']
        ];
    }
    
    echo json_encode($payments);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

$con->close();
?>
