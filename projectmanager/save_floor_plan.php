<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and has the right permissions
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

include_once "../config.php";


// Validate form data
if (empty($_POST['planName']) || !isset($_FILES['planImage']) || empty($_POST['project_id'])) {
    echo json_encode(['success' => false, 'message' => 'Project name, file, and project ID are required']);
    exit();
}

$name = trim($_POST['planName']);
$project_id = intval($_POST['project_id']); // sanitize
$file = $_FILES['planImage'];

// File upload validation
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'dwg'];
$maxFileSize = 10 * 1024 * 1024; // 10MB

$fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($fileExt, $allowedExtensions)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, GIF, PDF, and DWG files are allowed']);
    exit();
}

if ($file['size'] > $maxFileSize) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit']);
    exit();
}

// Handle upload directory - store in projectmanager/uploads/floor_plans/
$uploadDir = 'uploads/floor_plans/';
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
        exit();
    }
}

$fileName = uniqid('floorplan_') . '.' . $fileExt;
$targetPath = $uploadDir . $fileName;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    $imagePath = 'uploads/floor_plans/' . $fileName;
    $status = 'Pending';

    $stmt = $con->prepare("INSERT INTO blueprints (project_id, name, image_path, status) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        unlink($targetPath);
        echo json_encode(['success' => false, 'message' => 'Database prepare failed: ' . $con->error]);
        exit();
    }

    $stmt->bind_param("isss", $project_id, $name, $imagePath, $status);

    if ($stmt->execute()) {
        // Get project and client details for notification
        $projectStmt = $con->prepare("SELECT user_id, project, client_email FROM projects WHERE project_id = ?");
        $projectStmt->bind_param("i", $project_id);
        $projectStmt->execute();
        $projectResult = $projectStmt->get_result();
        
        if ($projectRow = $projectResult->fetch_assoc()) {
            $user_id = $projectRow['user_id'];
            $project_name = $projectRow['project'];
            $client_email = $projectRow['client_email'];
            
            // Insert notification
            $notifType = 'blueprint_upload';
            $message = "A new blueprint has been uploaded for project: $project_name";
            
            $notifStmt = $con->prepare("INSERT INTO notifications_client (user_id, client_email, notif_type, message) VALUES (?, ?, ?, ?)");
            $notifStmt->bind_param("isss", $user_id, $client_email, $notifType, $message);
            $notifStmt->execute();
            $notifStmt->close();
        }
        $projectStmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Floor plan saved successfully',
            'imagePath' => $imagePath,
            'fileName' => $fileName
        ]);
    } else {
        unlink($targetPath);
        echo json_encode(['success' => false, 'message' => 'Failed to save floor plan to database: ' . $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
}

$con->close();
?>
