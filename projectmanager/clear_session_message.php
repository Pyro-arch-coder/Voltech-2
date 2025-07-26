<?php
session_start();
if (isset($_SESSION['success_message'])) {
    unset($_SESSION['success_message']);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'No message to clear']);
}
?>
