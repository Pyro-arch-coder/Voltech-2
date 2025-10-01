<?php
// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Create logs directory if it doesn't exist
if (!is_dir(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0777, true);
}

// Simple response function with logging
function sendJsonResponse($success, $message = '', $data = null) {
    $response = ['success' => $success];
    if ($message) $response['message'] = $message;
    if ($data !== null) $response['data'] = $data;
    
    // Log the response for debugging
    error_log('Sending JSON response: ' . json_encode($response));
    
    echo json_encode($response);
    exit;
}

try {
    // Check if session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if user is logged in
    if (!isset($_SESSION['logged_in'])) {
        error_log('User not logged in');
        http_response_code(403);
        sendJsonResponse(false, 'Please log in to continue');
    }

    // Check if project_id is provided
    if (!isset($_GET['project_id']) || empty($_GET['project_id'])) {
        error_log('No project_id provided');
        http_response_code(400);
        sendJsonResponse(false, 'Project ID is required');
    }

    $projectId = filter_var($_GET['project_id'], FILTER_VALIDATE_INT);
    if ($projectId === false) {
        error_log('Invalid project_id: ' . $_GET['project_id']);
        http_response_code(400);
        sendJsonResponse(false, 'Invalid Project ID');
    }

    // Include database configuration
    $configFile = __DIR__ . '/../config.php';
    if (!file_exists($configFile)) {
        $error = 'Configuration file not found at: ' . $configFile;
        error_log($error);
        http_response_code(500);
        sendJsonResponse(false, 'Configuration error', ['error' => $error]);
    }

    require_once $configFile;

    // Verify database connection
    if (!isset($con) || !($con instanceof mysqli)) {
        $error = 'Database connection not established';
        error_log($error);
        http_response_code(500);
        sendJsonResponse(false, 'Database connection error', ['error' => $error]);
    }

    if ($con->connect_error) {
        $error = 'Database connection failed: ' . $con->connect_error;
        error_log($error);
        http_response_code(500);
        sendJsonResponse(false, 'Database connection error', ['error' => $error]);
    }

    // First, get project and user details
    $query = "SELECT p.user_id, u.email, u.firstname, u.lastname 
              FROM projects p
              JOIN users u ON p.user_id = u.id 
              WHERE p.project_id = ?
              LIMIT 1";
    
    $stmt = $con->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare project query: ' . $con->error);
    }
    
    $stmt->bind_param('i', $projectId);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute project query: ' . $stmt->error);
    }
    
    $projectResult = $stmt->get_result();
    if (!$projectResult) {
        throw new Exception('Failed to get project result: ' . $stmt->error);
    }
    
    if ($projectResult->num_rows === 0) {
        throw new Exception('Project not found');
    }
    
    $projectData = $projectResult->fetch_assoc();
    $userId = $projectData['user_id'];
    
    // Combine firstname and lastname if name is not set
    if (empty($projectData['name']) && !empty($projectData['firstname'])) {
        $projectData['name'] = trim($projectData['firstname'] . ' ' . $projectData['lastname']);
    }
    
    // Get active bank account details
    $query = "SELECT bank_name, account_name, account_number, contact_number 
              FROM bank_accounts 
              WHERE user_id = ? AND is_active = 1";
    
    $stmt = $con->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare bank accounts query: ' . $con->error);
    }
    
    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute bank accounts query: ' . $stmt->error);
    }
    
    $bankResult = $stmt->get_result();
    if (!$bankResult) {
        throw new Exception('Failed to get bank accounts result: ' . $stmt->error);
    }
    
    $bankAccounts = [];
    while ($row = $bankResult->fetch_assoc()) {
        $bankAccounts[] = [
            'bankName' => $row['bank_name'],
            'accountName' => $row['account_name'],
            'accountNumber' => $row['account_number'],
            'contactNumber' => $row['contact_number']
        ];
    }
    
    if (empty($bankAccounts)) {
        // Return a successful response with empty bank accounts array
        sendJsonResponse(true, 'No bank accounts configured yet', [
            'projectManager' => $projectData['name'],
            'bankAccounts' => [],
            'message' => 'No bank accounts have been set up for this project manager yet.'
        ]);
    }
    
    // Return the bank account details
    sendJsonResponse(true, 'Bank details retrieved successfully', [
        'projectManager' => $projectData['name'],
        'bankAccounts' => $bankAccounts
    ]);
    
} catch (Exception $e) {
    error_log('Error in get_bank_details.php: ' . $e->getMessage());
    http_response_code(500);
    sendJsonResponse(false, 'An error occurred while fetching bank details', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
