<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and has the right permissions
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database connection
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Validate form data
if (empty($_POST['planName']) || empty($_POST['planLocation']) || !isset($_FILES['planImage'])) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

$name = trim($_POST['planName']);
$location = trim($_POST['planLocation']);
$file = $_FILES['planImage'];

// Validate file upload
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
$maxFileSize = 5 * 1024 * 1024; // 5MB

if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, and GIF files are allowed']);
    exit();
}

if ($file['size'] > $maxFileSize) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
    exit();
}

// Create uploads directory if it doesn't exist
$uploadDir = '../uploads/floor_plans/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Generate a unique filename
$fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
$fileName = uniqid('floorplan_') . '.' . $fileExt;
$targetPath = $uploadDir . $fileName;

// Move the uploaded file
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // Save to database
    $imagePath = 'uploads/floor_plans/' . $fileName;
    $stmt = $con->prepare("INSERT INTO floor_plans (name, location, image_path) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $location, $imagePath);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Floor plan saved successfully']);
    } else {
        // Delete the uploaded file if database insert fails
        unlink($targetPath);
        echo json_encode(['success' => false, 'message' => 'Failed to save floor plan to database']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
}

$con->close();
?>
