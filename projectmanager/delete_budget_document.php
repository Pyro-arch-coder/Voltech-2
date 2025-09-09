<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ob_clean();
session_start();
header('Content-Type: application/json');
require_once '../config.php'; // or 'db_connect.php' if that's your connection

// Use your actual connection variable name everywhere (let's assume $con)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}
if (
    !isset($_POST['id']) || !is_numeric($_POST['id']) ||
    !isset($_POST['project_id']) || !is_numeric($_POST['project_id'])
) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$documentId = (int)$_POST['id'];
$projectId = (int)$_POST['project_id'];

$con->begin_transaction();
try {
    // Fetch file path
    $stmt = $con->prepare("SELECT estimation_pdf FROM project_pdf_approval WHERE id = ? AND project_id = ?");
    if (!$stmt) throw new Exception('Failed to prepare SELECT statement: ' . $con->error);
    $stmt->bind_param("ii", $documentId, $projectId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('Document not found or you do not have permission to delete it');
    }
    $document = $result->fetch_assoc();
    $filePath = $document['estimation_pdf'];
    $stmt->close();

    // Delete the record from the database
    $stmt = $con->prepare("DELETE FROM project_pdf_approval WHERE id = ? AND project_id = ?");
    if (!$stmt) throw new Exception('Failed to prepare DELETE statement: ' . $con->error);
    $stmt->bind_param("ii", $documentId, $projectId);
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete document record from database: ' . $stmt->error);
    }
    $stmt->close();

    // Delete the physical file
    if ($filePath && file_exists($filePath)) {
        if (!unlink($filePath)) {
            throw new Exception('Failed to delete the document file from server');
        }
    }

    $con->commit();
    echo json_encode(['success' => true, 'message' => 'Document deleted successfully']);
} catch (Exception $e) {
    $con->rollback();
    error_log("Error deleting budget document: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while deleting the document: ' . $e->getMessage()
    ]);
}
$con->close();
?>