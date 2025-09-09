<?php
session_start();
require_once "../config.php";

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 3) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Fetch users with user_level 6 (assuming 6 is the client level)
$query = "SELECT id, firstname, lastname, email FROM users WHERE user_level = 6 AND is_verified = 1 ORDER BY firstname, lastname";
$result = $con->query($query);

$clients = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $clients[] = [
            'id' => $row['id'],
            'firstname' => $row['firstname'],
            'lastname' => $row['lastname'],
            'email' => $row['email']
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($clients);
?>
