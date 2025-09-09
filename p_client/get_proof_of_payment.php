<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in and is a client
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 6) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$client_id = $_SESSION['user_id'];
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($project_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid project ID']);
    exit();
}

try {
    // Get proof of payment for this project and client
    $query = "SELECT * FROM proof_of_payments WHERE project_id = ? AND user_id = ? ORDER BY upload_date DESC LIMIT 1";
    $stmt = $con->prepare($query);
    $stmt->bind_param('ii', $project_id, $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $proofOfPayment = $result->fetch_assoc();
        
        // Format the data for frontend
        $response = [
            'success' => true,
            'data' => [
                'id' => $proofOfPayment['id'],
                'file_name' => $proofOfPayment['file_name'],
                'file_path' => $proofOfPayment['file_path'],
                'upload_date' => $proofOfPayment['upload_date'],
                'status' => $proofOfPayment['status'],
                'file_exists' => file_exists($proofOfPayment['file_path'])
            ]
        ];
        
        echo json_encode($response);
    } else {
        echo json_encode([
            'success' => true,
            'data' => null,
            'message' => 'No proof of payment found'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$con->close();
?>
