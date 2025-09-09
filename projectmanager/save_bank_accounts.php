<?php
session_start();
require_once dirname(__DIR__) . '/config.php';

// Use the existing connection from config.php
$conn = $con;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['bank_error'] = 'User not logged in';
    header('Location: paymethod.php');
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || (!isset($_POST['save_bank']) && !isset($_POST['update_bank']))) {
    $_SESSION['bank_error'] = 'Invalid request';
    header('Location: paymethod.php');
    exit;
}

// Get form data
$bank_id = isset($_POST['bank_id']) ? intval($_POST['bank_id']) : 0;
$bank_name = isset($_POST['bank_name']) ? trim($_POST['bank_name']) : '';
$account_name = isset($_POST['account_name']) ? trim($_POST['account_name']) : '';
$account_number = isset($_POST['account_number']) ? trim($_POST['account_number']) : '';
$contact_number = isset($_POST['contact_number']) ? trim($_POST['contact_number']) : '';

// Validate required fields
if (empty($bank_name) || empty($account_name) || empty($account_number)) {
    $_SESSION['bank_error'] = 'Please fill in all required fields';
    header('Location: paymethod.php');
    exit;
}

// Additional validation
if (strlen($bank_name) > 100) {
    $_SESSION['bank_error'] = 'Bank name is too long (max 100 characters)';
    header('Location: paymethod.php');
    exit;
}

if (strlen($account_name) > 100) {
    $_SESSION['bank_error'] = 'Account name is too long (max 100 characters)';
    header('Location: paymethod.php');
    exit;
}

if (strlen($account_number) > 50) {
    $_SESSION['bank_error'] = 'Account number is too long (max 50 characters)';
    header('Location: paymethod.php');
    exit;
}

if (!empty($contact_number) && strlen($contact_number) > 20) {
    $_SESSION['bank_error'] = 'Contact number is too long (max 20 characters)';
    header('Location: paymethod.php');
    exit;
}

try {
    if (isset($_POST['update_bank'])) {
        // Update existing bank account
        if ($bank_id <= 0) {
            throw new Exception("Invalid bank account ID");
        }
        
        // Check if another account with the same details already exists for this user
        $user_id = $_SESSION['user_id'];
        $check_stmt = $conn->prepare("SELECT id FROM bank_accounts WHERE bank_name = ? AND account_name = ? AND account_number = ? AND id != ? AND user_id = ? AND is_active = 1 LIMIT 1");
        $check_stmt->bind_param('sssii', $bank_name, $account_name, $account_number, $bank_id, $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            throw new Exception("A bank account with these details already exists");
        }
        
        // Update the account
        $update_stmt = $conn->prepare("UPDATE bank_accounts SET bank_name = ?, account_name = ?, account_number = ?, contact_number = ? WHERE id = ?");
        $update_stmt->bind_param('ssssi', $bank_name, $account_name, $account_number, $contact_number, $bank_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception("Error updating bank account: " . $update_stmt->error);
        }
        
        $_SESSION['bank_success'] = 'Bank account updated successfully';
    } else {
        // Insert new bank account
        // Check if account with same name and number already exists for this user
        $user_id = $_SESSION['user_id'];
        $check_stmt = $conn->prepare("SELECT id FROM bank_accounts WHERE bank_name = ? AND account_name = ? AND account_number = ? AND user_id = ? AND is_active = 1 LIMIT 1");
        $check_stmt->bind_param('sssi', $bank_name, $account_name, $account_number, $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            throw new Exception("A bank account with these details already exists");
        }
        
        // Insert the new account with user_id
        $user_id = $_SESSION['user_id'];
        $insert_stmt = $conn->prepare("INSERT INTO bank_accounts (bank_name, account_name, account_number, contact_number, is_active, user_id) VALUES (?, ?, ?, ?, 1, ?)");
        $insert_stmt->bind_param('ssssi', $bank_name, $account_name, $account_number, $contact_number, $user_id);
        
        if (!$insert_stmt->execute()) {
            throw new Exception("Error saving bank account: " . $insert_stmt->error);
        }
        
        $_SESSION['bank_success'] = 'Bank account added successfully';
    }
    
} catch (Exception $e) {
    $_SESSION['bank_error'] = $e->getMessage();
}

header('Location: paymethod.php');
$conn->close();
?>
