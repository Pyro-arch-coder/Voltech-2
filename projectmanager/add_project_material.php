<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session and set content type
session_start();
header('Content-Type: application/json');

// Accept JSON payloads for AJAX
if (strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $_POST = json_decode(file_get_contents('php://input'), true);
}

// Function to send JSON response and exit
function sendJsonResponse($success, $message, $data = []) {
    $response = array_merge(['success' => $success, 'message' => $message], $data);
    echo json_encode($response);
    exit();
}

// Check if user is logged in and has the right permissions
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    sendJsonResponse(false, 'Unauthorized access');
}

// Database connection
include_once "../config.php";

// Log the incoming request for debugging
file_put_contents('add_material_debug.log', "[" . date('Y-m-d H:i:s') . "] New request: " . print_r($_POST, true) . "\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_estimation_material'])) {
    file_put_contents('add_material_debug.log', "[" . date('Y-m-d H:i:s') . "] Processing add_estimation_material request\n", FILE_APPEND);
    try {
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : null;
        if (!$project_id) {
            file_put_contents('add_material_debug.log', "[" . date('Y-m-d H:i:s') . "] Error: Project ID is required\n", FILE_APPEND);
            sendJsonResponse(false, 'Project ID is required');
        }

        $con->begin_transaction();

        // Check if we have materials array
        if (!isset($_POST['materials']) || !is_array($_POST['materials']) || count($_POST['materials']) == 0) {
            file_put_contents('add_material_debug.log', "[" . date('Y-m-d H:i:s') . "] Error: No materials data received\n", FILE_APPEND);
            sendJsonResponse(false, 'No materials data received');
        }

        file_put_contents('add_material_debug.log', "[" . date('Y-m-d H:i:s') . "] Received " . count($_POST['materials']) . " materials\n", FILE_APPEND);

        $successCount = 0;
        $errorMessages = [];

        // Prepare the insert statement
        $insert_query = "INSERT INTO project_estimating_materials 
                        (project_id, material_id, material_name, unit, material_price, quantity, added_at) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $con->prepare($insert_query);

        foreach ($_POST['materials'] as $material) {
            $material_id = intval($material['material_id']);
            $quantity = floatval($material['quantity']);

            if ($material_id <= 0 || $quantity <= 0) {
                $errorMessages[] = "Invalid material data for material ID: $material_id";
                continue;
            }

            // Get material details including supplier
            $material_query = "SELECT m.material_name, m.unit, m.material_price, m.supplier_name 
                              FROM materials m 
                              WHERE m.id = ?";
            $mat_stmt = $con->prepare($material_query);
            $mat_stmt->bind_param("i", $material_id);
            $mat_stmt->execute();
            $material_result = $mat_stmt->get_result();

            if ($material_result->num_rows === 0) {
                $errorMessages[] = "Material not found with ID: $material_id";
                $mat_stmt->close();
                continue;
            }

            $material_data = $material_result->fetch_assoc();
            $material_name = $material_data['material_name'];
            $supplier_name = $material_data['supplier_name'];
            $unit = $material_data['unit'];
            $material_price = floatval($material_data['material_price']);
            
            // Check if material with same name and supplier already exists in project
            $check_duplicate = $con->prepare("
                SELECT COUNT(*) as count 
                FROM project_estimating_materials pem
                JOIN materials m ON pem.material_id = m.id
                WHERE pem.project_id = ? 
                AND pem.material_name = ? 
                AND m.supplier_name = ?
            ");
            $check_duplicate->bind_param("iss", $project_id, $material_name, $supplier_name);
            $check_duplicate->execute();
            $duplicate_result = $check_duplicate->get_result()->fetch_assoc();
            $check_duplicate->close();
            
            if ($duplicate_result['count'] > 0) {
                $errorMessages[] = "Material '$material_name' from supplier '$supplier_name' already exists in this project";
                continue;
            }

            // Bind and execute
            $stmt->bind_param(
                "iissdi",
                $project_id,
                $material_id,
                $material_name,
                $unit,
                $material_price,
                $quantity
            );

            if (!$stmt->execute()) {
                $errorMessages[] = "Failed to save material ID $material_id: " . $stmt->error;
            } else {
                $successCount++;
            }

            $mat_stmt->close();
        }

        if ($successCount > 0) {
            $con->commit();
            sendJsonResponse(
                true,
                "Successfully added $successCount material(s)." . 
                (count($errorMessages) > 0 ? ' ' . implode(' ', $errorMessages) : '')
            );
        } else {
            $con->rollback();
            sendJsonResponse(
                false,
                'Failed to add any materials. ' . implode(' ', $errorMessages)
            );
        }

        $stmt->close();

    } catch (Exception $e) {
        if (isset($con)) {
            $con->rollback();
        }
        http_response_code(500);
        sendJsonResponse(false, 'Error: ' . $e->getMessage());
    }
} else {
    http_response_code(400);
    sendJsonResponse(false, 'Invalid request method or missing parameters');
}

if (isset($con)) {
    $con->close();
}
?>