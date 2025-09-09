<?php
session_start();
require_once dirname(__DIR__) . '/config.php';

// Use the existing connection from config.php
$conn = $con;

// Check if user is logged in and has proper permissions
if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 3) {
    $_SESSION['bank_error'] = 'Unauthorized access';
    header('Location: paymethod.php');
    exit;
}

// Check if request is POST and has bank_id
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['bank_id'])) {
    $_SESSION['bank_error'] = 'Invalid request';
    header('Location: paymethod.php');
    exit;
}

// Get bank ID
$bank_id = intval($_POST['bank_id']);

if ($bank_id <= 0) {
    $_SESSION['bank_error'] = 'Invalid bank account ID';
    header('Location: paymethod.php');
    exit;
}

try {
    // Soft delete the bank account (set is_active to 0)
    $stmt = $conn->prepare("UPDATE bank_accounts SET is_active = 0 WHERE id = ?");
    $stmt->bind_param('i', $bank_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Error deleting bank account: " . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("Bank account not found or already deleted");
    }
    
    $_SESSION['bank_success'] = 'Bank account deleted successfully';
    
} catch (Exception $e) {
    $_SESSION['bank_error'] = $e->getMessage();
}

header('Location: paymethod.php');
$conn->close();
?>
