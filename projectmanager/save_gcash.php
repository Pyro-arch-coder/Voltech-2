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

// Check if request is POST and has the required action
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || (!isset($_POST['save_gcash']) && !isset($_POST['update_gcash']))) {
    $_SESSION['gcash_error'] = 'Invalid request';
    header('Location: paymethod.php');
    exit;
}

// Get form data
$gcash_id = isset($_POST['gcash_id']) ? intval($_POST['gcash_id']) : 0;
$gcash_number = isset($_POST['gcash_number']) ? trim($_POST['gcash_number']) : '';
$account_name = isset($_POST['account_name']) ? trim($_POST['account_name']) : '';

// Validate required fields
if (empty($gcash_number) || empty($account_name)) {
    $_SESSION['gcash_error'] = 'Please fill in all required fields';
    header('Location: paymethod.php');
    exit;
}

// Additional validation
if (strlen($gcash_number) > 20) {
    $_SESSION['gcash_error'] = 'GCash number is too long (max 20 characters)';
    header('Location: paymethod.php');
    exit;
}

if (strlen($account_name) > 100) {
    $_SESSION['gcash_error'] = 'Account name is too long (max 100 characters)';
    header('Location: paymethod.php');
    exit;
}

// Validate GCash number format (should be 11 digits starting with 09 or +63)
if (!preg_match('/^(09|\+639)\d{9}$/', $gcash_number)) {
    $_SESSION['gcash_error'] = 'Please enter a valid GCash number (must be 11 digits starting with 09 or +63)';
    header('Location: paymethod.php');
    exit;
}

try {
    if (isset($_POST['update_gcash'])) {
        // Update existing GCash account
        if ($gcash_id <= 0) {
            throw new Exception("Invalid GCash account ID");
        }
        
        // Check if another GCash account with the same number already exists for this user
        $user_id = $_SESSION['user_id'];
        $check_duplicate = $conn->prepare("SELECT id FROM gcash_settings WHERE gcash_number = ? AND id != ? AND user_id = ? AND is_active = 1 LIMIT 1");
        $check_duplicate->bind_param('sii', $gcash_number, $gcash_id, $user_id);
        $check_duplicate->execute();
        $duplicate_result = $check_duplicate->get_result();

        if ($duplicate_result->num_rows > 0) {
            throw new Exception('This GCash number is already registered. Please use a different number.');
        }
        
        // Update the GCash account
        $update_stmt = $conn->prepare("UPDATE gcash_settings SET gcash_number = ?, account_name = ? WHERE id = ?");
        $update_stmt->bind_param('ssi', $gcash_number, $account_name, $gcash_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception("Error updating GCash account: " . $update_stmt->error);
        }
        
        if ($update_stmt->affected_rows === 0) {
            throw new Exception("No changes made or GCash account not found");
        }
        
        $_SESSION['gcash_success'] = 'GCash account updated successfully';
    } else {
        // Insert new GCash account
        // Check if GCash number already exists for this user
        $user_id = $_SESSION['user_id'];
        $check_duplicate = $conn->prepare("SELECT id FROM gcash_settings WHERE gcash_number = ? AND user_id = ? AND is_active = 1 LIMIT 1");
        $check_duplicate->bind_param('si', $gcash_number, $user_id);
        $check_duplicate->execute();
        $duplicate_result = $check_duplicate->get_result();

        if ($duplicate_result->num_rows > 0) {
            throw new Exception('This GCash number is already registered. Please use a different number.');
        }
        
        // Insert the new GCash account with user_id
        $user_id = $_SESSION['user_id'];
        $insert_stmt = $conn->prepare("INSERT INTO gcash_settings (gcash_number, account_name, is_active, user_id) VALUES (?, ?, 1, ?)");
        $insert_stmt->bind_param('ssi', $gcash_number, $account_name, $user_id);
        
        if (!$insert_stmt->execute()) {
            throw new Exception("Error saving GCash account: " . $insert_stmt->error);
        }
        
        $_SESSION['gcash_success'] = 'GCash account added successfully';
    }
    
} catch (Exception $e) {
    $_SESSION['gcash_error'] = $e->getMessage();
}

header('Location: paymethod.php');
$conn->close();
?>
