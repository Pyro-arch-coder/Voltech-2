<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
    header("Location: ../login.php");
    exit();
}

// Include database configuration and PHPMailer
require_once '../config.php';
require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_supplier'])) {
    $supplier_name = mysqli_real_escape_string($con, $_POST['supplier_name']);
    $contact_firstname = mysqli_real_escape_string($con, $_POST['contact_firstname']);
    $contact_lastname = mysqli_real_escape_string($con, $_POST['contact_lastname']);
    $contact_number = mysqli_real_escape_string($con, $_POST['contact_number']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $address = mysqli_real_escape_string($con, $_POST['address']);
    $status = mysqli_real_escape_string($con, $_POST['status']);
    $now = date('Y-m-d H:i:s');
    
    // Check for existing supplier with same email, name, and address
    $check_sql = "SELECT id FROM suppliers WHERE email = ? OR (supplier_name = ? AND address = ?)";
    $check_stmt = $con->prepare($check_sql);
    $check_stmt->bind_param("sss", $email, $supplier_name, $address);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Supplier with same email or (name + address) already exists
        $existing = $check_result->fetch_assoc();
        $error = urlencode('A supplier with the same email or name and address combination already exists.');
        header('Location: po_suppliers.php?error=' . $error);
        exit();
    }
    
    // Generate verification code and set initial password
    $verification_code = bin2hex(random_bytes(16));
    $generated_password = "supplier123";
    $hashed_password = password_hash($generated_password, PASSWORD_DEFAULT);
    
    // Start transaction
    $con->begin_transaction();
    
    try {
        // Insert supplier with verified status
        $status = 'Active';
        $insert_sql = "INSERT INTO suppliers (supplier_name, firstname, lastname, contact_number, email, address, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $con->prepare($insert_sql);
        $insert_stmt->bind_param("sssssssss", $supplier_name, $contact_firstname, $contact_lastname, $contact_number, $email, $address, $status, $now, $now);
        
        if ($insert_stmt->execute()) {
            $supplier_id = $con->insert_id;
            
            // Insert user account (already verified)
            $user_sql = "INSERT INTO users (firstname, lastname, email, password, verification_code, is_verified, user_level) VALUES (?, ?, ?, ?, ?, 1, 5)";
            $user_stmt = $con->prepare($user_sql);
            $user_stmt->bind_param("sssss", $contact_firstname, $contact_lastname, $email, $hashed_password, $verification_code);
            
            if ($user_stmt->execute()) {
                // Send verification email
                $mail = new PHPMailer(true);
                try {
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
                    $login_link = "http://voltechelectricalconstruction.com/login.php";
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
                    
                    $con->commit();
                    $_SESSION['success_message'] = 'Supplier added successfully. Login details have been sent to ' . $email;
                    header('Location: po_suppliers.php?success=1');
                    exit();
                } catch (Exception $e) {
                    throw new Exception('Email could not be sent. Please try again later. ' . $e->getMessage());
                }
            } else {
                throw new Exception('Error creating user account: ' . $con->error);
            }
        } else {
            throw new Exception('Error adding supplier: ' . $con->error);
        }
    } catch (Exception $e) {
        $con->rollback();
        $err = urlencode($e->getMessage());
        header('Location: po_suppliers.php?error=' . $err);
        exit();
    }
    
    exit();
} else {
    header('Location: po_suppliers.php');
    exit();
}