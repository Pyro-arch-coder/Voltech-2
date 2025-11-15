<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database configuration and PHPMailer
require_once '../config.php';
require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Initialize response array
$response = ['success' => false, 'message' => ''];

// Function to send JSON response and exit
function sendResponse($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_supplier'])) {
    // Validate required fields
    $required_fields = ['supplier_name', 'contact_firstname', 'contact_lastname', 'email'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $missing_fields[] = str_replace('_', ' ', ucfirst($field));
        }
    }
    
    if (!empty($missing_fields)) {
        $response['message'] = 'Please fill in all required fields: ' . implode(', ', $missing_fields);
        echo json_encode($response);
        exit();
    }
    
    $supplier_name = mysqli_real_escape_string($con, $_POST['supplier_name']);
    $contact_firstname = mysqli_real_escape_string($con, $_POST['contact_firstname']);
    $contact_lastname = mysqli_real_escape_string($con, $_POST['contact_lastname']);
    $contact_number = isset($_POST['contact_number']) ? mysqli_real_escape_string($con, $_POST['contact_number']) : '';
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $address = isset($_POST['address']) ? mysqli_real_escape_string($con, $_POST['address']) : '';
    $status = isset($_POST['status']) ? mysqli_real_escape_string($con, $_POST['status']) : 'Active';
    $now = date('Y-m-d H:i:s');
    
    // Check for existing supplier with same email or name
    $check_sql = "SELECT id FROM suppliers WHERE email = ? OR supplier_name = ?";
    $check_stmt = $con->prepare($check_sql);
    if (!$check_stmt) {
        sendResponse(false, 'Database error: ' . $con->error);
    }
    
    $check_stmt->bind_param("ss", $email, $supplier_name);
    if (!$check_stmt->execute()) {
        sendResponse(false, 'Error checking for existing supplier: ' . $check_stmt->error);
    }
    
    $check_result = $check_stmt->get_result();
    if ($check_result->num_rows > 0) {
        $existing = $check_result->fetch_assoc();
        sendResponse(false, 'A supplier with the same email or name already exists.');
    }
    
    // Generate verification code and set initial password
    $verification_code = bin2hex(random_bytes(16));
    $generated_password = "supplier123";
    $hashed_password = password_hash($generated_password, PASSWORD_DEFAULT);
    $now = date('Y-m-d H:i:s');
    
    // Start transaction
    $con->begin_transaction();
    
    try {
        // Insert supplier with verified status
        $insert_sql = "INSERT INTO suppliers (supplier_name, firstname, lastname, contact_number, email, address, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $con->prepare($insert_sql);
        if (!$insert_stmt) {
            throw new Exception('Database error: ' . $con->error);
        }
        
        $insert_stmt->bind_param("sssssssss", $supplier_name, $contact_firstname, $contact_lastname, $contact_number, $email, $address, $status, $now, $now);
        
        if (!$insert_stmt->execute()) {
            throw new Exception('Error adding supplier: ' . $insert_stmt->error);
        }
        
        $supplier_id = $con->insert_id;
        
        // Insert user account (already verified)
        $user_sql = "INSERT INTO users (firstname, lastname, email, password, verification_code, is_verified, user_level) VALUES (?, ?, ?, ?, ?, 1, 5)";
        $user_stmt = $con->prepare($user_sql);
        if (!$user_stmt) {
            throw new Exception('Database error: ' . $con->error);
        }
        
        $user_stmt->bind_param("sssss", $contact_firstname, $contact_lastname, $email, $hashed_password, $verification_code);
        
        if (!$user_stmt->execute()) {
            throw new Exception('Error creating user account: ' . $user_stmt->error);
        }
        
        // Try to send email (but don't fail the whole process if email fails)
        try {
            $mail = new PHPMailer(true);
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'VoltechElectricalConstruction0@gmail.com';
            $mail->Password = 'sban pumy bmia wwal';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Recipients
            $mail->setFrom('VoltechElectricalConstruction0@gmail.com', 'Voltech System');
            $mail->addAddress($email, $contact_firstname . ' ' . $contact_lastname);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Your Supplier Account is Ready';
            $login_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/login.php";
            $mail->Body = "
                <h2>Welcome to Voltech Electrical Construction</h2>
                <p>Hello $contact_firstname,</p>
                <p>Your supplier account has been created and verified. You can now log in using the following credentials:</p>
                <p>
                    <strong>Email:</strong> $email<br>
                    <strong>Password:</strong> $generated_password
                </p>
                <p><a href='$login_link' style='background: #27ae60; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Login to Your Account</a></p>
                <p>For security reasons, we recommend that you change your password after your first login.</p>
                <p>If you didn't request this account, please contact us immediately.</p>
                <p>Thanks,<br>Voltech Electrical Construction</p>
            ";

            $mail->send();
            $email_sent = true;
        } catch (Exception $e) {
            // Log the error but don't fail the process
            error_log('Email sending failed: ' . $e->getMessage());
            $email_sent = false;
        }
        
        // Commit the transaction
        $con->commit();
        
        $message = 'Supplier added successfully';
        if ($email_sent) {
            $message .= '. Login details have been sent to ' . $email;
        } else {
            $message .= '. However, there was an issue sending the email notification.';
        }
        
        $response = [
            'success' => true,
            'message' => $message,
            'supplier_id' => $supplier_id
        ];
        
    } catch (Exception $e) {
        $con->rollback();
        $response = [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
    
    // Send JSON response
    echo json_encode($response);
    exit();
    
    exit();
} else {
    $response = [
        'success' => false,
        'message' => 'Invalid request method or missing parameters.'
    ];
    echo json_encode($response);
    exit();
}