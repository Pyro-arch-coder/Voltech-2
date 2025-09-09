<?php
session_start();
header('Content-Type: application/json');
require_once '../config.php';

$response = ['success' => false, 'message' => 'An error occurred'];

try {
    // Make sure user is logged in
    if (!isset($_SESSION['logged_in'])) {
        throw new Exception('Unauthorized access');
    }

    // Get ID from POST
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        throw new Exception('Invalid contract ID');
    }

    // Get file path first
    $stmt = $con->prepare("SELECT file_path FROM project_contracts WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $contract = $result->fetch_assoc();
    $stmt->close();

    if (!$contract) {
        throw new Exception('Contract not found');
    }

    // Delete DB entry
    $stmt = $con->prepare("DELETE FROM project_contracts WHERE id = ?");
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete contract');
    }
    $stmt->close();

    // Delete file from server
    if (!empty($contract['file_path'])) {
        $filePath = '../' . ltrim($contract['file_path'], '/');
        if (file_exists($filePath) && is_file($filePath)) {
            unlink($filePath);
        }
    }

    $response = ['success' => true, 'message' => 'Contract deleted successfully'];

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
