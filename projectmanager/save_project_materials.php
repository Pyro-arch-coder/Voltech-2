<?php
session_start();

// Check if user is logged in and has the right permissions
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database connection
include_once "../config.php";

// Set content type to JSON
header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get the form data
$project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
$materials_data = isset($_POST['materials_data']) ? json_decode($_POST['materials_data'], true) : [];

// Validate required fields
if ($project_id <= 0 || empty($materials_data) || !is_array($materials_data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing data. Please try again.']);
    exit();
}

$added_by = intval($_SESSION['user_id']);
$success_count = 0;
$error_messages = [];

// Start transaction
$con->begin_transaction();

try {
    foreach ($materials_data as $material) {
        // Validate material data
        if (empty($material['id']) || empty($material['quantity']) || $material['quantity'] <= 0) {
            $error_messages[] = "Invalid quantity for material ID: " . ($material['id'] ?? 'unknown');
            continue;
        }

        $material_id = intval($material['id']);
        $quantity = floatval($material['quantity']);
        
        // Check if there's enough stock
        $stock_check = $con->query("SELECT quantity FROM materials WHERE id = $material_id");
        if ($stock_check && $stock_check->num_rows > 0) {
            $stock = $stock_check->fetch_assoc();
            if ($stock['quantity'] < $quantity) {
                $error_messages[] = "Not enough stock for material ID: $material_id. Available: {$stock['quantity']}, Requested: $quantity";
                continue;
            }
        } else {
            $error_messages[] = "Material ID $material_id not found";
            continue;
        }
        $material_name = $con->real_escape_string($material['name'] ?? '');
        $unit = $con->real_escape_string($material['unit'] ?? '');
        $material_price = floatval($material['price'] ?? 0);
        $additional_cost = floatval($material['additional_cost'] ?? 0);

        // Check if material already exists in the project
        $check_query = "SELECT id, quantity FROM project_add_materials 
                       WHERE project_id = $project_id AND material_id = $material_id";
        $result = $con->query($check_query);
        
        if ($result && $result->num_rows > 0) {
            // Material exists, update quantity
            $existing = $result->fetch_assoc();
            $existing_id = $existing['id'];
            $new_quantity = $existing['quantity'] + $quantity;
            
            // Only update quantity and timestamp, leave other fields unchanged
            $query = "UPDATE project_add_materials 
                     SET quantity = $new_quantity,
                         added_at = NOW()
                     WHERE id = $existing_id";
        } else {
            // Material doesn't exist, insert new
            $query = "INSERT INTO project_add_materials (
                        project_id, 
                        material_id, 
                        material_name, 
                        unit, 
                        material_price, 
                        quantity, 
                        additional_cost,
                        added_at
                      ) VALUES (
                        $project_id, 
                        $material_id, 
                        '$material_name', 
                        '$unit', 
                        $material_price, 
                        $quantity, 
                        $additional_cost,
                        NOW()
                      )";
        }

        if ($con->query($query)) {
            $success_count++;
            
            // Update material quantity in stock and get the new quantity
            $update_query = "UPDATE materials SET quantity = quantity - $quantity WHERE id = $material_id";
            if (!$con->query($update_query)) {
                throw new Exception("Failed to update material stock for ID: $material_id");
            }
            
            // Get the new quantity after update
            $new_quantity = $stock['quantity'] - $quantity;
            
            // Check if stock is low (25 or below) and create notification
            if ($new_quantity <= 25) {
                $material_name = $con->real_escape_string($material_name);
                $message = "Low stock alert: $material_name is now at $new_quantity $unit";
                
                // Insert notification for procurement
                $notif_query = "INSERT INTO notifications_procurement 
                               (user_id, notif_type, message, is_read, created_at) 
                               VALUES 
                               (1, 'low_stock', '$message', 0, NOW())";
                
                if (!$con->query($notif_query)) {
                    error_log("Failed to create low stock notification: " . $con->error);
                    // Don't fail the whole operation if notification fails
                }
            }
        } else {
            $error_messages[] = "Failed to add material: " . $con->error;
        }
    }

    // Commit transaction if all queries were successful
    if (empty($error_messages)) {
        $con->commit();
        echo json_encode(['success' => true, 'message' => "Successfully added $success_count material(s) to the project"]);
    } else {
        // Rollback on any error
        $con->rollback();
        echo json_encode(['success' => false, 'message' => 'Some materials could not be added: ' . implode(', ', $error_messages)]);
    }
    exit();

} catch (Exception $e) {
    // Rollback on exception
    if (isset($con)) {
        $con->rollback();
    }
    
    error_log('Error in save_project_materials.php: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while saving materials: ' . $e->getMessage()]);
    exit();
}

$con->close();
