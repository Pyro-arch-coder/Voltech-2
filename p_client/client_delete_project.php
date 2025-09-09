<?php
session_start();
require_once '../config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 6) {
    header("Location: ../login.php");
    exit();
}

// Check if delete parameter is set
if (isset($_GET['delete'])) {
    $project_id = intval($_GET['delete']);
    $client_email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
    
    // Debug info
    error_log("Delete request for project ID: " . $project_id);
    error_log("Client email: " . $client_email);
    
    // First, check if the project exists and belongs to this client
    $check_query = "SELECT project_id FROM projects WHERE project_id = '$project_id' AND client_email = '$client_email'";
    $result = mysqli_query($con, $check_query);
    
    if (mysqli_num_rows($result) === 0) {
        error_log("Project not found or doesn't belong to client");
        header("Location: client_archieved.php?error=project_not_found");
        exit();
    }
    
    // Check if client_delete column exists, if not, add it
    $check_column = mysqli_query($con, "SHOW COLUMNS FROM projects LIKE 'client_delete'");
    if (mysqli_num_rows($check_column) == 0) {
        // Add the column if it doesn't exist
        mysqli_query($con, "ALTER TABLE projects ADD COLUMN client_delete TINYINT(1) DEFAULT 0");
        error_log("Added client_delete column to projects table");
    }
    
    // Update the project to mark it as deleted by client
    $update_query = "UPDATE projects SET client_delete = 1 WHERE project_id = '$project_id' AND client_email = '$client_email'";
    error_log("Executing query: " . $update_query);
    
    if (mysqli_query($con, $update_query)) {
        $affected_rows = mysqli_affected_rows($con);
        error_log("Query successful. Affected rows: " . $affected_rows);
        // Success - redirect back with success parameter
        header("Location: client_archieved.php?deleted=1");
        exit();
    } else {
        $error = mysqli_error($con);
        error_log("Query failed: " . $error);
        // Error - redirect back with error parameters
        header("Location: client_archieved.php?error=delete_failed&message=" . urlencode($error));
        exit();
    }
    exit();
}

// If no valid action, redirect to archived projects page
header("Location: client_archieved.php");
?>
