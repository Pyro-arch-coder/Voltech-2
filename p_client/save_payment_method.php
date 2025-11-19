<?php
// Enable error reporting for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../php_errors.log');

// Start session and include config
session_start();
require_once '../config.php';

// Function to send JSON response and exit
function sendJsonResponse($success, $message = '', $data = null) {
    // Make sure no output has been sent yet
    if (headers_sent()) {
        error_log('Headers already sent when trying to send JSON response');
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// Check if user is logged in and has the right permissions
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 6) {
    sendJsonResponse(false, 'Unauthorized access');
}

// Function to handle file upload
function handleFileUpload($file, $projectId, $userId) {
    $allowedTypes = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
    ];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    // Check if file was uploaded
    if (!isset($file) || !is_array($file) || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new Exception('No file was uploaded or upload failed');
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        $errorMessage = $errorMessages[$file['error']] ?? 'Unknown upload error';
        throw new Exception('File upload failed: ' . $errorMessage);
    }
    
    // Get file info
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $fileType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    // Check file type
    if (!array_key_exists($fileType, $allowedTypes)) {
        throw new Exception('Invalid file type. Please upload a PDF, JPG, or PNG file.');
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        throw new Exception('File size exceeds 5MB limit.');
    }
    
    // Create uploads directory if it doesn't exist
    $uploadDir = dirname(__DIR__) . '/uploads/proof_of_payments/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory. Please check permissions.');
        }
    }
    
    // Generate unique filename
    $extension = $allowedTypes[$fileType];
    $filename = 'proof_payment_' . $projectId . '_' . $userId . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    $relativePath = 'uploads/proof_of_payments/' . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save uploaded file. Please try again.');
    }
    
    // Verify file was saved
    if (!file_exists($filepath)) {
        throw new Exception('Failed to verify uploaded file. Please try again.');
    }
    
    return [
        'file_name' => $filename,
        'file_path' => $relativePath
    ];
}



try {
    // Validate required parameters and file upload
    if (!isset($_POST['project_id'], $_POST['payment_type'], $_POST['amount'])) {
        sendJsonResponse(false, 'Missing required parameters');
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['proof_of_payment']) || $_FILES['proof_of_payment']['error'] === UPLOAD_ERR_NO_FILE) {
        sendJsonResponse(false, 'Please upload proof of payment');
    }

    $project_id = intval($_POST['project_id']);
    $payment_type = trim($_POST['payment_type']);
    $amount = floatval($_POST['amount']);
    $user_id = $_SESSION['user_id'];
    
    // Check if there are any pending/processing payments for this project in approved_payments table
    $check_processing = $con->prepare("
        SELECT 1 FROM approved_payments 
        WHERE project_id = ? 
        AND status IN ('pending', 'processing')
        LIMIT 1
    ");
    if (!$check_processing) {
        throw new Exception('Failed to prepare check_processing statement: ' . $con->error);
    }
    
    $check_processing->bind_param('i', $project_id);
    if (!$check_processing->execute()) {
        throw new Exception('Failed to execute check_processing query: ' . $check_processing->error);
    }
    
    $result = $check_processing->get_result();
    if (!$result) {
        throw new Exception('Failed to get check_processing result: ' . $check_processing->error);
    }
    
    $has_processing = $result->num_rows > 0;
    $check_processing->close();
    
    if ($has_processing) {
        sendJsonResponse(false, 'There are payments currently being processed for this project. Please wait for them to be completed before making a new payment.');
    }

    // Start transaction
    $con->begin_transaction();

    try {
        // Verify project exists
        $stmt = $con->prepare("SELECT project_id FROM projects WHERE project_id = ?");
        $stmt->bind_param('i', $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Project not found');
        }
        
        // Check for existing payment with same type and amount within the last 5 seconds
        $checkStmt = $con->prepare("
            SELECT id FROM approved_payments 
            WHERE project_id = ? 
            AND payment_type = ? 
            AND amount = ? 
            AND created_by = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $checkStmt->bind_param('isdi', $project_id, $payment_type, $amount, $user_id);
        $checkStmt->execute();
        $existingPayment = $checkStmt->get_result();
        
        if ($existingPayment->num_rows > 0) {
            // If a duplicate is found, return success but don't insert again
            $payment = $existingPayment->fetch_assoc();
            sendJsonResponse(true, 'Payment method already saved', [
                'payment_id' => $payment['id'],
                'payment_type' => $payment_type,
                'amount' => $amount,
                'is_duplicate' => true
            ]);
        }

        // Insert into approved_payments
        $stmt = $con->prepare("
            INSERT INTO approved_payments (
                project_id, 
                payment_type, 
                amount, 
                status, 
                created_by,
                created_at
            ) 
            SELECT ?, ?, ?, 'pending', ?, NOW()
            FROM DUAL
            WHERE NOT EXISTS (
                SELECT 1 FROM approved_payments 
                WHERE project_id = ? 
                AND payment_type = ? 
                AND amount = ? 
                AND created_by = ?
                AND created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)
            )
            LIMIT 1
        ");

        $stmt->bind_param(
            'isdiisdi',
            $project_id,
            $payment_type,
            $amount,
            $user_id,
            $project_id,
            $payment_type,
            $amount,
            $user_id
        );

        if (!$stmt->execute()) {
            throw new Exception('Failed to save payment: ' . $stmt->error);
        }

        // Check if any rows were actually inserted
        if ($stmt->affected_rows === 0) {
            // No rows inserted, likely a duplicate
            sendJsonResponse(true, 'Payment method already saved', [
                'payment_type' => $payment_type,
                'amount' => $amount,
                'is_duplicate' => true
            ]);
        }

        $payment_id = $con->insert_id;
        
        // Handle file upload
        $fileInfo = handleFileUpload(
            $_FILES['proof_of_payment'],
            $project_id,
            $user_id
        );
        
        // Save proof of payment record
        $stmt = $con->prepare("
            INSERT INTO proof_of_payments (
                project_id,
                user_id,
                file_name,
                file_path,
                status,
                upload_date
            ) VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        
        if (!$stmt) {
            throw new Exception('Failed to prepare proof_of_payment INSERT statement: ' . $con->error);
        }
        
        $stmt->bind_param(
            'iiss',
            $project_id,
            $user_id,
            $fileInfo['file_name'],
            $fileInfo['file_path']
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to save proof of payment: ' . $stmt->error);
        }
        $stmt->close();

        // Note: billing_requests table uses 'status' column, not 'is_paid' or 'payment_status'
        // The payment tracking is handled by the approved_payments table
        // No need to update billing_requests here

        // Get project manager's user_id from projects table
        $pmStmt = $con->prepare("
            SELECT user_id FROM projects WHERE project_id = ?
        ");
        $pmStmt->bind_param('i', $project_id);
        $pmStmt->execute();
        $pmResult = $pmStmt->get_result();
        
        if ($pmResult->num_rows > 0) {
            $project = $pmResult->fetch_assoc();
            $project_manager_id = $project['user_id'];
            
            // Insert notification for project manager
            $notifMessage = "Client has uploaded a proof of payment for project (₱" . number_format($amount, 2) . ")";
            $notifStmt = $con->prepare("
                INSERT INTO notifications_projectmanager 
                (user_id, notif_type, message, is_read, created_at) 
                VALUES (?, 'Proof of Payment Uploaded', ?, 0, NOW())
            ");
            $notifStmt->bind_param('is', $project_manager_id, $notifMessage);
            $notifStmt->execute();
            $notifStmt->close();
        }
        $pmStmt->close();

        // Commit transaction
        $con->commit();

        sendJsonResponse(true, 'Payment method saved successfully', [
            'payment_id' => $payment_id,
            'payment_type' => $payment_type,
            'amount' => $amount
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $con->rollback();
        throw $e;
    }

} catch (Exception $e) {
    // Log the full error with stack trace
    error_log('Error in save_payment_method.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Send JSON error response
    if (!headers_sent()) {
        header('HTTP/1.1 500 Internal Server Error');
        // Enable debug mode to see actual errors
        $debug_mode = true;
        $responseMessage = $debug_mode 
            ? 'Error: ' . $e->getMessage() . ' (File: ' . basename($e->getFile()) . ', Line: ' . $e->getLine() . ')'
            : 'An error occurred while processing your request. Please try again.';
        sendJsonResponse(false, $responseMessage);
    } else {
        // If headers were already sent, output a simple JSON string
        die(json_encode([
            'success' => false,
            'message' => 'An error occurred: Headers already sent'
        ]));
    }
}
