<?php
session_start();
header('Content-Type: application/json');

require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if required parameters are provided
if (!isset($_POST['id']) || !is_numeric($_POST['id']) || !isset($_POST['project_id']) || !is_numeric($_POST['project_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$documentId = (int)$_POST['id'];
$projectId = (int)$_POST['project_id'];
$userId = $_SESSION['user_id'];

// Start transaction
$conn->begin_transaction();

try {
    // First, get the file path before deleting the record
    $stmt = $conn->prepare("SELECT estimation_pdf as file_path FROM project_pdf_approval WHERE id = ? AND project_id = ?");
    $stmt->bind_param("ii", $documentId, $projectId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Document not found or you do not have permission to delete it');
    }
    
    $document = $result->fetch_assoc();
    $filePath = $document['file_path'];
    
    // Delete the record from the database
    $stmt = $conn->prepare("DELETE FROM project_pdf_approval WHERE id = ? AND project_id = ?");
    $stmt->bind_param("ii", $documentId, $projectId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete document record from database');
    }
    
    // Delete the physical file
    if (file_exists($filePath)) {
        if (!unlink($filePath)) {
            throw new Exception('Failed to delete the document file');
        }
    }
    
    // If we got here, everything was successful
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Document deleted successfully'
    ]);
    
} catch (Exception $e) {
    // Something went wrong, rollback the transaction
    $conn->rollback();
    
    error_log("Error deleting budget document: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while deleting the document: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
