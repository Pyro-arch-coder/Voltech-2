<?php
// Suppress error reporting to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in and is a project manager
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($project_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid project ID']);
    exit();
}

try {
    // Get all proof of payments for this project
    $query = "SELECT pop.*, u.firstname, u.lastname, u.email 
              FROM proof_of_payments pop
              LEFT JOIN users u ON pop.user_id = u.id
              WHERE pop.project_id = ? 
              ORDER BY pop.upload_date DESC";
    $stmt = $con->prepare($query);
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $proofOfPayments = [];
    while ($row = $result->fetch_assoc()) {
        $proofOfPayments[] = [
            'id' => $row['id'],
            'file_name' => $row['file_name'],
            'file_path' => $row['file_path'],
            'upload_date' => $row['upload_date'],
            'status' => $row['status'],
            'user_id' => $row['user_id'],
            'client_name' => trim($row['firstname'] . ' ' . $row['lastname']),
            'client_email' => $row['email']
        ];
    }
    
    $response = [
        'success' => true,
        'data' => $proofOfPayments,
        'count' => count($proofOfPayments)
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$con->close();
?>
