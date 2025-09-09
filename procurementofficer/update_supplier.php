<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
    header("Location: ../login.php");
    exit();
}
require_once '../config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_supplier'])) {
    $id = (int)$_POST['edit_supplier_id'];
    $supplier_name = mysqli_real_escape_string($con, $_POST['supplier_name']);
    $contact_firstname = mysqli_real_escape_string($con, $_POST['contact_firstname']);
    $contact_lastname = mysqli_real_escape_string($con, $_POST['contact_lastname']);
    $contact_number = mysqli_real_escape_string($con, $_POST['contact_number']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $address = mysqli_real_escape_string($con, $_POST['address']);
    $status = mysqli_real_escape_string($con, $_POST['status']);
    $now = date('Y-m-d H:i:s');
    
    $update_sql = "UPDATE suppliers SET supplier_name=?, firstname=?, lastname=?, contact_number=?, email=?, address=?, status=?, updated_at=? WHERE id=?";
    $update_stmt = $con->prepare($update_sql);
    $update_stmt->bind_param("ssssssssi", $supplier_name, $contact_firstname, $contact_lastname, $contact_number, $email, $address, $status, $now, $id);
    
    if ($update_stmt->execute()) {
        header('Location: po_suppliers.php?updated=1');
    } else {
        $err = urlencode('Error updating supplier: ' . $con->error);
        header('Location: po_suppliers.php?error=' . $err);
    }
    exit();
} else {
    header('Location: po_suppliers.php');
    exit();
} 