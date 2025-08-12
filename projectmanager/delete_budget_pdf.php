<?php
session_start();
require_once '../config.php';

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 3) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;

    if (!$project_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid project ID']);
        exit();
    }

    try {
        // Step 1: Get the row details (including id and file path)
        $stmt = $con->prepare("SELECT id, estimation_pdf FROM project_pdf_approval WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $id = $row['id'];
            $pdf_path = $row['estimation_pdf'];

            // Step 2: Delete the PDF file from server if it exists
            if ($pdf_path && file_exists('../uploads/' . $pdf_path)) {
                unlink('../uploads/' . $pdf_path);
            }

            // Step 3: Delete the actual row from database using id
            $delete_stmt = $con->prepare("DELETE FROM project_pdf_approval WHERE id = ?");
            $delete_stmt->bind_param("i", $id);

            if ($delete_stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'PDF and database record deleted successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to delete record: ' . $con->error
                ]);
            }

            $delete_stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'No PDF found for this project']);
        }

        $stmt->close();

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
