<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
    header("Location: ../login.php");
    exit();
}

require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['material_id'])) {
    $material_id = intval($_POST['material_id']);
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    
    // Start transaction
    $con->begin_transaction();
    
    try {
        // Lock the material row for update
        $material_query = "SELECT * FROM materials WHERE id = $material_id FOR UPDATE";
        $material_result = $con->query($material_query);
        
        if ($material_result && $material_result->num_rows > 0) {
            $material = $material_result->fetch_assoc();
            $current_quantity = $material['quantity'];
            $new_quantity = $current_quantity;
            $update_message = '';
            $mat_price = floatval($material['material_price']);
            $labor_cost = floatval($material['labor_other']);
            $material_name = $material['material_name'];

            // If already delivered, just skip to the end (no double-add)
            if ($material['delivery_status'] === 'Delivered') {
                // Commit and redirect, no update
                $con->commit();
                $_SESSION['success_message'] = 'Material is already marked as delivered.';
                header('Location: po_materials.php');
                exit();
            }

            // Handle delivery status
            switch ($material['delivery_status']) {
                case 'Backorder Delivery':
                    // Get the latest approved backorder for this material
                    // Match any backorder that's not a reorder (based on delivery_status)
                    $backorder_query = "SELECT id, quantity, reason FROM back_orders 
                                      WHERE material_id = ? AND status = 'Approved'
                                      AND id IN (
                                          SELECT bo.id 
                                          FROM back_orders bo
                                          JOIN materials m ON bo.material_id = m.id
                                          WHERE m.delivery_status = 'Backorder Delivery'
                                      )
                                      ORDER BY id DESC LIMIT 1";
                    $stmt = $con->prepare($backorder_query);
                    $stmt->bind_param("i", $material_id);
                    $stmt->execute();
                    $backorder_result = $stmt->get_result();
                    $qty = 0;
                    if ($backorder_result && $backorder_result->num_rows > 0) {
                        $backorder = $backorder_result->fetch_assoc();
                        $backorder_id = $backorder['id'];
                        $qty = intval($backorder['quantity']);
                        $new_quantity = $current_quantity + $qty;
                        $update_message = " and {$qty} units have been added to stock";
                        
                        // Update back_orders status to 'Delivered'
                        $update_backorder = $con->prepare("UPDATE back_orders SET status = 'Delivered' WHERE id = ?");
                        $update_backorder->bind_param("i", $backorder_id);
                        if (!$update_backorder->execute()) {
                            throw new Exception('Failed to update backorder status');
                        }
                        $update_backorder->close();
                    }
                    $stmt->close();
                    // Insert expense for backorder
                    if ($qty > 0) {
                        $total_expense = ($mat_price + $labor_cost) * $qty;
                        $desc = "Backorder expense for material: $material_name, qty: $qty";
                        $exp_stmt = $con->prepare("INSERT INTO order_expenses (user_id, expense, expensedate, expensecategory, description) VALUES (?, ?, NOW(), 'Backorder', ?)");
                        $exp_stmt->bind_param("ids", $user_id, $total_expense, $desc);
                        $exp_stmt->execute();
                        $exp_stmt->close();
                    }
                    break;
                case 'Reorder Delivery':
                    // Get the latest approved reorder for this material
                    // Match based on delivery_status
                    $reorder_query = "SELECT id, quantity, reason FROM back_orders 
                                      WHERE material_id = ? AND status = 'Approved'
                                      AND id IN (
                                          SELECT bo.id 
                                          FROM back_orders bo
                                          JOIN materials m ON bo.material_id = m.id
                                          WHERE m.delivery_status = 'Reorder Delivery'
                                      )
                                      ORDER BY id DESC LIMIT 1";
                    $stmt = $con->prepare($reorder_query);
                    $stmt->bind_param("i", $material_id);
                    $stmt->execute();
                    $reorder_result = $stmt->get_result();
                    $qty = 0;
                    if ($reorder_result && $reorder_result->num_rows > 0) {
                        $reorder = $reorder_result->fetch_assoc();
                        $reorder_id = $reorder['id'];
                        $qty = intval($reorder['quantity']);
                        $new_quantity = $current_quantity + $qty;
                        $update_message = " and {$qty} units have been added to stock";
                        
                        // Update back_orders status to 'Delivered'
                        $update_reorder = $con->prepare("UPDATE back_orders SET status = 'Delivered' WHERE id = ?");
                        $update_reorder->bind_param("i", $reorder_id);
                        if (!$update_reorder->execute()) {
                            throw new Exception('Failed to update reorder status');
                        }
                        $update_reorder->close();
                    }
                    $stmt->close();
                    // Insert expense for reorder
                    if ($qty > 0) {
                        $total_expense = ($mat_price + $labor_cost) * $qty;
                        $desc = "Reorder expense for material: $material_name, qty: $qty";
                        $exp_stmt = $con->prepare("INSERT INTO order_expenses (user_id, expense, expensedate, expensecategory, description) VALUES (?, ?, NOW(), 'Reorder', ?)");
                        $exp_stmt->bind_param("ids", $user_id, $total_expense, $desc);
                        $exp_stmt->execute();
                        $exp_stmt->close();
                    }
                    break;
                case 'Material On Delivery':
                    // No quantity change, just update status
                    $qty = intval($material['quantity']);
                    $update_message = '';
                    // Insert expense for new material
                    $total_expense = ($mat_price + $labor_cost) * $qty;
                    $desc = "Material expense for $material_name, qty: $qty";
                    $exp_stmt = $con->prepare("INSERT INTO order_expenses (user_id, expense, expensedate, expensecategory, description) VALUES (?, ?, NOW(), 'Material', ?)");
                    $exp_stmt->bind_param("ids", $user_id, $total_expense, $desc);
                    $exp_stmt->execute();
                    $exp_stmt->close();
                    break;
                default:
                    throw new Exception('Invalid delivery status for receiving material');
            }
            
            // Update material status, delivery status, and quantity if changed
            $update_material = $con->prepare("
                UPDATE materials 
                SET status = 'Available', 
                    delivery_status = 'Delivered',
                    quantity = ?
                WHERE id = ?");
            $update_material->bind_param("ii", $new_quantity, $material_id);
            
            if (!$update_material->execute()) {
                throw new Exception('Failed to update material status');
            }
            $update_material->close();
            
            // Insert notification for project manager if "Material On Delivery"
            if ($material['delivery_status'] === 'Material On Delivery') {
                // Get all project manager user_ids (assuming user_level 3)
                $pm_q = $con->query("SELECT id FROM users WHERE user_level = 3");
                $notif_type = 'Material Received';
                $message = "Material '{$material['material_name']}' has been received and is now available in stock.";
                $is_read = 0;
                while($pm_row = $pm_q->fetch_assoc()) {
                    $pm_id = $pm_row['id'];
                    $insert_notif_pm = $con->prepare("
                        INSERT INTO notifications_projectmanager (user_id, notif_type, message, is_read, created_at) 
                        VALUES (?, ?, ?, ?, NOW())");
                    $insert_notif_pm->bind_param("issi", $pm_id, $notif_type, $message, $is_read);
                    $insert_notif_pm->execute();
                    $insert_notif_pm->close();
                }
            }

            // Commit the transaction
            $con->commit();
            $_SESSION['success_message'] = 'Material has been marked as received' . $update_message . '.';
            
        } else {
            throw new Exception('Material not found');
        }
        
    } catch (Exception $e) {
        // An error occurred, rollback the transaction
        $con->rollback();
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    }
}

header('Location: po_materials.php');
exit();
?>