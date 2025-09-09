<?php
// Enable error reporting but don't display errors (we'll log them instead)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start output buffering to catch any unwanted output
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log function for debugging
function logError($message) {
    $logFile = __DIR__ . '/../logs/initial_budget_errors.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Set content type to JSON
header('Content-Type: application/json');

// Log incoming request
logError('=== New Request ===');
logError('POST data: ' . print_r($_POST, true));
logError('FILES data: ' . print_r($_FILES, true));
logError('SESSION data: ' . print_r($_SESSION, true));

// Check if user is logged in and has client role
if (!isset($_SESSION['logged_in'])) {
    logError('User not logged in');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Please log in to continue']);
    exit;
}

if ($_SESSION['user_level'] != 6) {
    logError('User does not have client role. User level: ' . $_SESSION['user_level']);
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Client role required.']);
    exit;
}

// Include database connection
require_once "../config.php";
if (!isset($con) || !($con instanceof mysqli)) {
    $error = $con ? 'Database connection is not a valid MySQLi instance' : 'Database connection variable $con is not set';
    logError('Database connection error: ' . $error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $error]);
    exit;
}

// Check database connection
if ($con->connect_error) {
    logError('Database connection failed: ' . $con->connect_error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Log incoming request
logError('Incoming request: ' . print_r(['POST' => $_POST, 'FILES' => isset($_FILES) ? array_keys($_FILES) : 'No files'], true));

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        logError("Error [$errno] $errstr in $errfile on line $errline");
        throw new ErrorException($errstr, $errno, 1, $errfile, $errline);
    });
    
    try {
        // Log POST data for debugging
        logError('POST data: ' . print_r($_POST, true));
        if (isset($_FILES['payment_proof'])) {
            logError('File upload details: ' . print_r([
                'name' => $_FILES['payment_proof']['name'],
                'size' => $_FILES['payment_proof']['size'],
                'type' => $_FILES['payment_proof']['type'],
                'error' => $_FILES['payment_proof']['error'],
                'tmp_name' => $_FILES['payment_proof']['tmp_name']
            ], true));
        }
        
        $response = ['success' => false, 'message' => ''];
        $projectId = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $userId = $_SESSION['user_id'];
        $percentage = isset($_POST['percentage']) ? floatval($_POST['percentage']) : 0;
        
        // Check for existing pending payment proof for this project
        $checkStmt = $con->prepare("SELECT id FROM initial_b_proof_of_payment WHERE project_id = ? AND status = 'pending'");
        $checkStmt->bind_param('i', $projectId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $checkStmt->close();
        
        if ($checkResult->num_rows > 0) {
            throw new Exception('A pending payment proof already exists for this project. Please wait for it to be processed before submitting another one.');
        }
        
        // Validate inputs
        if ($projectId <= 0 || $percentage <= 0) {
            throw new Exception('Invalid project ID or percentage');
        }
        
        // Start transaction
        $con->begin_transaction();
        
        try {
            // 1. Handle file upload if exists
            $filePath = '';
            $fileName = '';
            
            if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/proof_of_payments/';
                
                // Create directory if it doesn't exist
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Validate file type
                $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
                $fileType = mime_content_type($_FILES['payment_proof']['tmp_name']);
                
                if (!in_array($fileType, $allowedTypes)) {
                    throw new Exception('Invalid file type. Only PDF, JPG, and PNG files are allowed.');
                }
                
                // Validate file size (5MB max)
                $maxSize = 5 * 1024 * 1024; // 5MB
                if ($_FILES['payment_proof']['size'] > $maxSize) {
                    throw new Exception('File is too large. Maximum size is 5MB.');
                }
                
                // Generate unique filename
                $fileExt = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
                $fileName = 'proof_payment_' . $projectId . '_' . $userId . '_' . time() . '.' . $fileExt;
                $filePath = $uploadDir . $fileName;
                
                // Move uploaded file
                if (!move_uploaded_file($_FILES['payment_proof']['tmp_name'], $filePath)) {
                    throw new Exception('Failed to upload file');
                }
                
                // Get payment method from POST data
                $paymentType = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'unknown';
                
                // Calculate the initial budget payment amount
                $stmt = $con->prepare("SELECT (budget * ? / 100) as payment_amount FROM projects WHERE project_id = ?");
                $stmt->bind_param('di', $percentage, $projectId);
                $stmt->execute();
                $result = $stmt->get_result();
                $paymentAmount = $result->fetch_assoc()['payment_amount'];
                $stmt->close();
                
                // Save to proof_of_payments table with payment_type and initial_budget_payment
                $stmt = $con->prepare("INSERT INTO initial_b_proof_of_payment 
                    (project_id, user_id, file_name, file_path, status, payment_type, initial_budget_payment) 
                    VALUES (?, ?, ?, ?, 'pending', ?, ?)");
                $stmt->bind_param('iisssd', $projectId, $userId, $fileName, $filePath, $paymentType, $paymentAmount);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to save payment proof: ' . $stmt->error);
                }
                $stmt->close();
            }
            
            // 2. Update project's initial budget
            $stmt = $con->prepare("
                UPDATE projects 
                SET initial_budget = (budget * ? / 100)
                WHERE project_id = ?
            ");
            $stmt->bind_param('di', $percentage, $projectId);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to update project budget: ' . $stmt->error);
            }
            
            if ($stmt->affected_rows === 0) {
                // First, check if project exists
                $checkStmt = $con->prepare("SELECT project_id FROM projects WHERE project_id = ?");
                $checkStmt->bind_param('i', $projectId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult->num_rows === 0) {
                    throw new Exception('Project with ID ' . $projectId . ' not found in database');
                } else {
                    // Project exists but update didn't affect any rows - likely a permissions issue
                    $checkStmt = $con->prepare("SELECT user_id FROM projects WHERE project_id = ? AND user_id = ?");
                    $checkStmt->bind_param('ii', $projectId, $userId);
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();
                    
                    if ($checkResult->num_rows === 0) {
                        throw new Exception('You do not have permission to update this project');
                    } else {
                        throw new Exception('Failed to update project. Please check if the budget value is different from current value.');
                    }
                }
            }
            
            $stmt->close();
            
            // Get project manager ID and project name for notification
            $pmQuery = "SELECT user_id, project FROM projects WHERE project_id = ?";
            $pmStmt = $con->prepare($pmQuery);
            $pmStmt->bind_param('i', $projectId);
            $pmStmt->execute();
            $pmResult = $pmStmt->get_result();
            
            if ($pmRow = $pmResult->fetch_assoc()) {
                $pm_user_id = $pmRow['user_id'];
                $project_name = $pmRow['project'];
                
                // Insert notification for project manager
                $notif_type = "Initial Budget Submitted";
                $message = "Client has submitted initial budget for project " . htmlspecialchars($project_name) . " (" . $percentage . "% of total budget)";
                $insertNotif = $con->prepare("INSERT INTO notifications_projectmanager (user_id, notif_type, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
                if ($insertNotif) {
                    $insertNotif->bind_param('iss', $pm_user_id, $notif_type, $message);
                    $insertNotif->execute();
                    $insertNotif->close();
                }
            }
            
            // Commit transaction
            $con->commit(); 
            
            $response = [
                'success' => true,
                'message' => 'Initial budget and payment proof submitted successfully',
                'budget_percentage' => $percentage,
                'file_uploaded' => !empty($fileName)
            ];
            
        } catch (Exception $e) {
            // Rollback transaction on error if connection exists
            if (isset($con) && $con instanceof mysqli) {
                $con->rollback();
            }
            
            // Clean up uploaded file if it exists
            if (!empty($filePath) && file_exists($filePath)) {
                @unlink($filePath);
            }
            
            $errorMsg = 'Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
            logError($errorMsg);
            logError('Stack trace: ' . $e->getTraceAsString());
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'An error occurred. Please try again later.',
                'debug' => $errorMsg // Only include in development
            ]);
        } finally {
            // Restore error handler
            restore_error_handler();
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        $response = [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
        
        // Ensure we have a valid JSON response
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Ensure we have a valid JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// If not a POST request
http_response_code(405);
header('Content-Type: application/json');
// Clear any output that might have been generated
ob_end_clean();
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
exit;
