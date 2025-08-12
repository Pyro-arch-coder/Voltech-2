<?php
// Include database connection
require_once '../config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Initialize response array
$response = [
    'success' => false,
    'message' => ''
];

try {
    // Get POST data
    $projectId = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
    $budgetAmount = isset($_POST['budget_amount']) ? $_POST['budget_amount'] : '';

    // Validate input
    if ($projectId <= 0) {
        throw new Exception('Invalid project ID');
    }

    // Optional: If you don't want this strict format, remove the regex check
    if (!is_numeric($budgetAmount) || $budgetAmount <= 0) {
        throw new Exception('Invalid budget amount');
    }

    // Convert to float with 2 decimal places
    $budgetAmount = number_format((float)$budgetAmount, 2, '.', '');

    // Insert new record (default status = Pending)
    $stmt = $con->prepare("
        INSERT INTO project_budget_approval (project_id, budget, status, created_at, updated_at)
        VALUES (?, ?, 'Pending', NOW(), NOW())
    ");
    $stmt->bind_param("id", $projectId, $budgetAmount);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Budget amount inserted successfully';
    } else {
        throw new Exception('Failed to insert budget amount: ' . $stmt->error);
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(400); // Bad Request
}

// Return JSON response
echo json_encode($response);

// Close connection
$con->close();
