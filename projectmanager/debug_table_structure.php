<?php
// Suppress error reporting to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unexpected output
ob_start();

session_start();
require_once '../config.php';

// Clear any output that might have been generated
ob_clean();

header('Content-Type: application/json');

try {
    // Check database connection
    if (!$con) {
        throw new Exception('Database connection failed');
    }
    
    // Get table structure
    $query = "DESCRIBE billing_requests";
    $result = $con->query($query);
    
    if (!$result) {
        throw new Exception('Failed to get table structure: ' . $con->error);
    }
    
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row;
    }
    
    // Get sample data
    $sampleQuery = "SELECT * FROM billing_requests LIMIT 1";
    $sampleResult = $con->query($sampleQuery);
    
    $sampleData = null;
    if ($sampleResult && $sampleResult->num_rows > 0) {
        $sampleData = $sampleResult->fetch_assoc();
    }
    
    $response = [
        'success' => true,
        'table_structure' => $columns,
        'sample_data' => $sampleData,
        'total_records' => $con->query("SELECT COUNT(*) as count FROM billing_requests")->fetch_assoc()['count']
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

if (isset($con)) {
    $con->close();
}
?>
