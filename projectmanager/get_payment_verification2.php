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
    // Query to get payment verification details with payment info
    $stmt = $con->prepare("
        SELECT 
            pop.id,
            pop.project_id,
            pop.user_id,
            pop.file_name,
            pop.file_path,
            pop.upload_date,
            COALESCE(ap.status, pop.status) as status,
            COALESCE(ap.payment_type, 'Payment') as payment_type,
            ap.amount,
            ap.created_by,
            ap.created_at as payment_date,
            CONCAT(u.firstname, ' ', u.lastname) as payer_name
        FROM 
            proof_of_payments pop
        LEFT JOIN 
            approved_payments ap ON pop.project_id = ap.project_id
        LEFT JOIN
            users u ON ap.created_by = u.id
        WHERE 
            pop.project_id = ?
        ORDER BY 
            pop.upload_date DESC
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
            'amount' => $row['amount'],
            'payer_name' => $row['payer_name'],
            'payment_date' => $row['payment_date']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $payments
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

$con->close();
?>
