<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 3) {
    header("Location: ../login.php");
    exit();
}

require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

include_once "../config.php";
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Start transaction
    mysqli_begin_transaction($con);
    
    try {
        // Process client information
        $client_type = $_POST['client_type'];
        $client_email = '';
        $user_id = null;
        $first_name = '';
        $last_name = '';
        $password = '';

        if ($client_type === 'new') {
            // Process new client
            $first_name = mysqli_real_escape_string($con, $_POST['first_name']);
            $last_name = mysqli_real_escape_string($con, $_POST['last_name']);
            $client_email = mysqli_real_escape_string($con, $_POST['email']);
            
            // Check if email already exists
            $check_email = mysqli_query($con, "SELECT id FROM users WHERE email = '$client_email'");
            if (mysqli_num_rows($check_email) > 0) {
                throw new Exception('A user with this email already exists.');
            }
            
            // Generate a secure random password
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
            $password = substr(str_shuffle($chars), 0, 12);
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Generate verification code
            $verification_code = md5(uniqid(rand(), true));
            
            // Insert user into users table
            $user_query = "INSERT INTO users (firstname, lastname, email, password, verification_code, is_verified, user_level) 
                          VALUES ('$first_name', '$last_name', '$client_email', '$hashed_password', '$verification_code', 1, 6)";
            if (!mysqli_query($con, $user_query)) {
                throw new Exception('Error creating user account: ' . mysqli_error($con));
            }
            $user_id = mysqli_insert_id($con);
            
            // Send email with credentials only for new clients
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
                $mail->addAddress($client_email, $first_name . ' ' . $last_name);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Your Account Credentials';
                $login_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . "/login.php";
                $mail->Body = "
                    <h2>Welcome to Voltech Electrical Construction</h2>
                    <p>Hello $first_name,</p>
                    <p>Your account has been created successfully. Here are your login details:</p>
                    <p>
                        <strong>Email:</strong> $client_email<br>
                        <strong>Password:</strong> $password
                    </p>
                    <p><a href='$login_link' style='background: #27ae60; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Login to Your Account</a></p>
                    <p>For security reasons, we recommend that you change your password after your first login.</p>
                    <p>If you didn't request this account, please contact us immediately.</p>
                    <p>Thanks,<br>Voltech Electrical Construction</p>
                ";

                $mail->send();
                $_SESSION['success_message'] = 'User account created successfully. Login details have been sent to ' . $client_email;
            } catch (Exception $e) {
                // Log the error but don't show it to the user
                error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
                $_SESSION['error_message'] = 'Account was created, but there was an error sending the email with login details.';
            }
        } else {
            // Process existing client
            $client_email = mysqli_real_escape_string($con, $_POST['client_email']);
            $user_query = mysqli_query($con, "SELECT id, firstname, lastname FROM users WHERE email = '$client_email' AND is_verified = 1 LIMIT 1");
            if (mysqli_num_rows($user_query) === 0) {
                throw new Exception('No verified user found with this email address.');
            }
            $user_data = mysqli_fetch_assoc($user_query);
            $user_id = $user_data['id'];
            $first_name = $user_data['firstname'];
            $last_name = $user_data['lastname'];
            
            $_SESSION['success_message'] = 'Project has been assigned to the existing client.';
        }
        
        // Process project information
        $project_name = mysqli_real_escape_string($con, $_POST['project_name']);
        $size = floatval($_POST['size']);
        $user_id = $_SESSION['user_id'];
        
        // Get location from dropdowns
        $barangay = isset($_POST['barangay']) ? mysqli_real_escape_string($con, $_POST['barangay']) : '';
        $municipality = isset($_POST['municipality']) ? mysqli_real_escape_string($con, $_POST['municipality']) : '';
        $province = isset($_POST['province']) ? mysqli_real_escape_string($con, $_POST['province']) : '';
        $region = isset($_POST['region']) ? mysqli_real_escape_string($con, $_POST['region']) : '';
        $location = trim($region . ' ' . $province . ' ' . $municipality . ' ' . $barangay);
        
        // Insert project with client information
        $project_query = "INSERT INTO projects (project, location, size, user_id, client_email, created_at, updated_at) 
                         VALUES ('$project_name', '$location', '$size', '$user_id', '$client_email', NOW(), NOW())";
        if (!mysqli_query($con, $project_query)) {
            throw new Exception('Error creating project: ' . mysqli_error($con));
        }
        
        // Commit transaction
        mysqli_commit($con);
        
        // Redirect back to projects page with success message
        header('Location: projects.php?success=add');
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($con);
        $_SESSION['error_message'] = $e->getMessage();
        
        // Store form data in session to repopulate form
        $_SESSION['form_data'] = $_POST;
        
        // Redirect back to form with error
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }
} else {
    // If not a POST request, redirect to projects page
    header('Location: projects.php');
    exit();
}
?>
