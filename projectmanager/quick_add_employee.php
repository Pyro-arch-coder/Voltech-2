<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => 'An error occurred'];

try {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || ($_SESSION['user_level'] ?? null) != 3) {
        throw new Exception('Unauthorized access.');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        throw new Exception('User session not found.');
    }

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $position_id = $_POST['position_id'] ?? '';
    $company_type = trim($_POST['company_type'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $fb_link = trim($_POST['fb_link'] ?? '');

    if ($first_name === '' || $last_name === '' || $position_id === '' || $company_type === '') {
        throw new Exception('Please fill in all required fields.');
    }

    $stmt = $con->prepare("
        INSERT INTO employees (user_id, first_name, last_name, position_id, company_type, contact_number, fb_link)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $con->error);
    }

    // Null for empty fb_link
    $fb_link_db = $fb_link !== '' ? $fb_link : null;

    $stmt->bind_param(
        'ississs',
        $userId,
        $first_name,
        $last_name,
        $position_id,
        $company_type,
        $contact_number,
        $fb_link_db
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to save employee: ' . $stmt->error);
    }

    $stmt->close();

    $response['success'] = true;
    $response['message'] = 'Employee added successfully.';
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>


