<?php
session_start();
require_once '../config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and has project manager access
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 3) {
    header("Location: ../login.php");
    exit();
}

// Check if project ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'Invalid project ID';
    header("Location: project_archived.php");
    exit();
}

$project_id = (int)$_GET['id'];

// Debug info
error_log("Delete request for project ID: " . $project_id);

// Verify project exists and is archived
$check_sql = "SELECT * FROM projects WHERE project_id = ? AND archived = 1";
if ($stmt = $con->prepare($check_sql)) {
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = 'Project not found or not archived';
        $stmt->close();
        header("Location: project_archived.php");
        exit();
    }
    $stmt->close();
} else {
    $_SESSION['error_message'] = 'Database error: ' . $con->error;
    header("Location: project_archived.php");
    exit();
}

try {
    // Mark project as deleted by project manager (soft delete)
    $update_sql = "UPDATE projects SET projectmanager_delete = 1 WHERE project_id = ?";
    if ($stmt = $con->prepare($update_sql)) {
        $stmt->bind_param("i", $project_id);
        
        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            if ($affected_rows > 0) {
                error_log("Successfully marked project ID for deletion: " . $project_id);
                header("Location: project_archived.php?deleted=1");
                exit();
            } else {
                error_log("No rows affected when marking project for deletion ID: " . $project_id);
                header("Location: project_archived.php?error=no_changes");
                exit();
            }
        } else {
            $error = $stmt->error;
            error_log("Error marking project for deletion: " . $error);
            header("Location: project_archived.php?error=delete_failed&message=" . urlencode($error));
            exit();
        }
        
        $stmt->close();
    } else {
        $error = $con->error;
        throw new Exception('Failed to prepare project delete statement: ' . $error);
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    error_log("Exception in project_delete.php: " . $e->getMessage());
}

// Close connection
$con->close();

// Redirect back to archived projects page
header("Location: project_archived.php");
exit();
?>
