<?php
// Include the existing config file
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'tasks' => []
];

try {
    // Check if tasks table exists
    $table_check = $con->query("SHOW TABLES LIKE 'tasks'");
    if ($table_check->num_rows === 0) {
        throw new Exception('Tasks table does not exist');
    }
    
    // Fetch tasks
    $result = $con->query("SELECT * FROM tasks ORDER BY name");
    
    if ($result === false) {
        throw new Exception('Error fetching tasks: ' . $con->error);
    }
    
    while ($row = $result->fetch_assoc()) {
        $response['tasks'][] = [
            'id' => $row['id'],
            'name' => $row['name']
        ];
    }
    
    $response['success'] = true;
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
?>
