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

// Initialize error logging
function logError($message, $data = []) {
    $logMessage = date('[Y-m-d H:i:s] ') . $message;
    if (!empty($data)) {
        $logMessage .= ' Data: ' . json_encode($data);
    }
    error_log($logMessage, 3, __DIR__ . '/../logs/gcash_errors.log');
    return $logMessage;
}

// Check if session is started
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if user is logged in
    if (!isset($_SESSION['logged_in'])) {
        logError('User not logged in', ['session' => $_SESSION]);
        http_response_code(403);
        sendJsonResponse(false, 'Please log in to continue');
    }
} catch (Exception $e) {
    $errorMessage = logError('Session error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
    http_response_code(500);
    sendJsonResponse(false, 'Session initialization error', ['error' => $errorMessage]);
}

// Check if project_id is provided
if (!isset($_GET['project_id']) || empty($_GET['project_id'])) {
    logError('No project_id provided', ['_GET' => $_GET]);
    http_response_code(400);
    sendJsonResponse(false, 'Project ID is required');
}

$projectId = filter_var($_GET['project_id'], FILTER_VALIDATE_INT);
if ($projectId === false) {
    logError('Invalid project_id', ['project_id' => $_GET['project_id']]);
    http_response_code(400);
    sendJsonResponse(false, 'Invalid Project ID');
}

logError('Processing request', ['project_id' => $projectId]);

// Include database configuration
$configFile = __DIR__ . '/../config.php';
if (!file_exists($configFile)) {
    $error = 'Configuration file not found at: ' . $configFile;
    logError($error);
    http_response_code(500);
    sendJsonResponse(false, 'Configuration error', ['error' => $error]);
}

// Include the config file and verify database connection
require_once $configFile;

// Verify database connection
if (!isset($con) || !($con instanceof mysqli)) {
    $error = 'Database connection not established';
    logError($error);
    http_response_code(500);
    sendJsonResponse(false, 'Database connection error', ['error' => $error]);
}

// Test the connection
if ($con->connect_error) {
    $error = 'Database connection failed: ' . $con->connect_error;
    logError($error);
    http_response_code(500);
    sendJsonResponse(false, 'Database connection error', ['error' => $error]);
}

// Initialize response array
$response = ['success' => false, 'data' => null];

try {
    // Verify database connection
    if (!isset($con) || !($con instanceof mysqli)) {
        throw new Exception('Database connection not established');
    }

    // Test the connection
    if ($con->connect_error) {
        throw new Exception('Database connection failed: ' . $con->connect_error);
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
    
    // Now get GCash settings
    $query = "SELECT gcash_number, account_name 
              FROM gcash_settings 
              WHERE user_id = ? AND is_active = 1";
    
    $stmt = $con->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare GCash query: ' . $con->error);
    }
    
    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute GCash query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception('Failed to get GCash result: ' . $stmt->error);
    }
    
    if ($result->num_rows > 0) {
        $gcashData = $result->fetch_assoc();
        $response = [
            'success' => true,
            'data' => [
                'user_id' => $userId,
                'name' => $projectData['name'],
                'email' => $projectData['email'],
                'gcash_number' => $gcashData['gcash_number'],
                'account_name' => $gcashData['account_name']
            ]
        ];
    } else {
        $response = [
            'success' => true,
            'data' => [
                'user_id' => $userId,
                'name' => $projectData['name'],
                'email' => $projectData['email'],
                'gcash_number' => null,
                'account_name' => null
            ],
            'message' => 'No active GCash details found for the project manager'
        ];
    }
    
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    $response = [
        'success' => false,
        'message' => 'An error occurred while fetching GCash details',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ];
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);

// Close database connection if it exists
if (isset($con) && $con instanceof mysqli) {
    $con->close();
}
?>
