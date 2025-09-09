<?php
session_start();
require_once dirname(__DIR__) . '/config.php';

// Use the existing connection from config.php
$conn = $con;

// Check if user is logged in and has proper permissions
if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 3) {
    $_SESSION['gcash_error'] = 'Unauthorized access';
    header('Location: paymethod.php');
    exit;
}

// Check if request is POST and has gcash_id
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['gcash_id'])) {
    $_SESSION['gcash_error'] = 'Invalid request';
    header('Location: paymethod.php');
    exit;
}

// Get GCash ID
$gcash_id = intval($_POST['gcash_id']);

if ($gcash_id <= 0) {
    $_SESSION['gcash_error'] = 'Invalid GCash account ID';
    header('Location: paymethod.php');
    exit;
}

try {
    // Soft delete the GCash account (set is_active to 0)
    $stmt = $conn->prepare("UPDATE gcash_settings SET is_active = 0 WHERE id = ?");
    $stmt->bind_param('i', $gcash_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Error deleting GCash account: " . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("GCash account not found or already deleted");
    }
    
    $_SESSION['gcash_success'] = 'GCash account deleted successfully';
    
} catch (Exception $e) {
    $_SESSION['gcash_error'] = $e->getMessage();
}

header('Location: paymethod.php');
$conn->close();
?>
