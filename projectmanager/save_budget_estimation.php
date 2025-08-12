<?php
// Disable error display to prevent HTML output
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Include config
require_once '../config.php';

// Initialize response
$response = ['success' => false, 'message' => 'An error occurred', 'filePath' => ''];

try {
    // Validate user session and permissions
    if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
        throw new Exception('Unauthorized access');
    }

    // Validate project_id
    $projectId = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
    if ($projectId <= 0) throw new Exception('Missing or invalid project ID.');

    // Validate file upload
    if (!isset($_FILES['estimation_pdf']) || $_FILES['estimation_pdf']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error.');
    }

    $file = $_FILES['estimation_pdf'];
    $allowedTypes = ['application/pdf'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB max

    if (!in_array($file['type'], $allowedTypes)) throw new Exception('Only PDF files are allowed.');
    if ($file['size'] > $maxFileSize) throw new Exception('File is too large. Max 5MB.');

    // Save file
    $uploadDir = 'uploads/budget_approvals/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $fileName = 'budget_' . $projectId . '_' . time() . '.pdf';
    $filePath = $uploadDir . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $filePath)) throw new Exception('Failed to save file.');

    // Save to DB
    $stmt = $con->prepare("SELECT id FROM project_pdf_approval WHERE project_id=? LIMIT 1");
    $stmt->bind_param("i", $projectId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        // Update existing record
        $stmt2 = $con->prepare("UPDATE project_pdf_approval SET estimation_pdf=?, status='Pending', updated_at=NOW() WHERE project_id=?");
        $stmt2->bind_param("si", $filePath, $projectId);
        $stmt2->execute();
    } else {
        // Insert new record
        $stmt2 = $con->prepare("INSERT INTO project_pdf_approval (project_id, estimation_pdf, status, created_at, updated_at) VALUES (?, ?, 'Pending', NOW(), NOW())");
        $stmt2->bind_param("is", $projectId, $filePath);
        $stmt2->execute();
    }

    // Get project and client details for notification
    $projectStmt = $con->prepare("SELECT user_id, project, client_email FROM projects WHERE project_id = ?");
    $projectStmt->bind_param("i", $projectId);
    $projectStmt->execute();
    $projectResult = $projectStmt->get_result();
    
    if ($projectRow = $projectResult->fetch_assoc()) {
        $user_id = $projectRow['user_id'];
        $project_name = $projectRow['project'];
        $client_email = $projectRow['client_email'];
        
        // Insert notification
        $notifType = 'budget_estimation_upload';
        $message = "A new budget estimation has been uploaded for project: $project_name";
        
        $notifStmt = $con->prepare("INSERT INTO notifications_client (user_id, client_email, notif_type, message) VALUES (?, ?, ?, ?)");
        $notifStmt->bind_param("isss", $user_id, $client_email, $notifType, $message);
        $notifStmt->execute();
        $notifStmt->close();
    }
    $projectStmt->close();
    
    $response['success'] = true;
    $response['message'] = 'Budget estimation uploaded successfully!';
    $response['filePath'] = $filePath;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>