<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in and has the right permissions
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if required parameters are provided
if (!isset($_POST['name']) || !isset($_POST['price']) || !isset($_POST['project_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$name = trim($_POST['name']);
$project_id = intval($_POST['project_id']);
$price = floatval($_POST['price']);

// Validate price
if ($price < 0) {
    echo json_encode(['success' => false, 'message' => 'Price cannot be negative']);
    exit();
}

try {
    // Check if record exists for this project and overhead name
    $check = $con->prepare("SELECT id FROM overhead_cost_actual WHERE name = ? AND project_id = ?");
    $check->bind_param("si", $name, $project_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing record
        $row = $result->fetch_assoc();
        $stmt = $con->prepare("UPDATE overhead_cost_actual SET price = ? WHERE id = ? AND project_id = ?");
        $stmt->bind_param("dii", $price, $row['id'], $project_id);
    } else {
        // Insert new record
        $stmt = $con->prepare("INSERT INTO overhead_cost_actual (project_id, name, price) VALUES (?, ?, ?)");
        $stmt->bind_param("isd", $project_id, $name, $price);
    }
    
    if ($stmt->execute()) {
        // Calculate new total overhead
        $total_query = $con->prepare("SELECT COALESCE(SUM(price), 0) as total FROM overhead_cost_actual WHERE project_id = ?");
        $total_query->bind_param("i", $project_id);
        $total_query->execute();
        $total_result = $total_query->get_result();
        $total_row = $total_result->fetch_assoc();
        $new_total = $total_row['total'];
        
        echo json_encode([
            'success' => true, 
            'message' => 'Price saved successfully',
            'total' => number_format($new_total, 2, '.', '')
        ]);
    } else {
        throw new Exception('Failed to save overhead cost');
    }
} catch (Exception $e) {
    error_log('Error saving overhead actual cost: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error saving overhead cost: ' . $e->getMessage()
    ]);
}
?>
