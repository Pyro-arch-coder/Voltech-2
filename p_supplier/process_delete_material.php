<?php
session_start();
require_once '../config.php';

// Check if user is logged in and has supplier access
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 5) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Set header to JSON response
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Get the material ID
        if (!isset($_POST['material_id']) || empty($_POST['material_id'])) {
            throw new Exception('Material ID is required');
        }
        $material_id = filter_var($_POST['material_id'], FILTER_VALIDATE_INT);
        
        // Prepare and execute delete query
        $sql = "DELETE FROM suppliers_materials WHERE id = ?";
        $stmt = $con->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Database preparation failed: ' . $con->error);
        }

        $stmt->bind_param("i", $material_id);

        if ($stmt->execute()) {
            if (isset($stmt)) {
                $stmt->close();
            }
            $con->close();
            header("Location: supplier_materials.php?success=delete");
            exit();
        } else {
            throw new Exception('Failed to delete material: ' . $stmt->error);
        }

    } catch (Exception $e) {
        if (isset($stmt)) {
            $stmt->close();
        }
        $con->close();
        $error = urlencode($e->getMessage());
        header("Location: supplier_materials.php?error=$error");
        exit();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}
?>
