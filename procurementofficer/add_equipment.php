<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
    header("Location: ../login.php");
    exit();
}
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equipment_name = mysqli_real_escape_string($con, $_POST['equipment_name']);
    $status = 'Available'; // Default status
    $category = mysqli_real_escape_string($con, $_POST['category']);
    $equipment_categories = isset($_POST['equipment_category']) ? mysqli_real_escape_string($con, $_POST['equipment_category']) : null; // <-- new
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    $user_level = isset($_SESSION['user_level']) ? intval($_SESSION['user_level']) : 0;
    $user_name = isset($_SESSION['firstname']) && isset($_SESSION['lastname']) ? trim($_SESSION['firstname'] . ' ' . $_SESSION['lastname']) : '';
    $equipment_price = isset($_POST['equipment_price']) && $_POST['equipment_price'] !== '' ? floatval($_POST['equipment_price']) : null;
    $brand = isset($_POST['brand']) ? mysqli_real_escape_string($con, $_POST['brand']) : null;
    $specification = isset($_POST['specification']) ? mysqli_real_escape_string($con, $_POST['specification']) : null;
    
    if ($category === 'Company') {
        $depreciation = isset($_POST['depreciation']) && $_POST['depreciation'] !== '' ? floatval($_POST['depreciation']) : null;
        $rental_fee = null;
    } else {
        $depreciation = null;
        $rental_fee = isset($_POST['rental_fee']) && $_POST['rental_fee'] !== '' ? floatval($_POST['rental_fee']) : null;
    }

    $location = isset($_POST['location']) ? mysqli_real_escape_string($con, $_POST['location']) : null;

    $insert_query = "INSERT INTO equipment (
        equipment_name, 
        location, 
        status, 
        category, 
        depreciation, 
        equipment_price, 
        user_id,
        borrow_time,
        return_time,
        brand,
        specification,
        equipment_categories
    ) VALUES (
        '$equipment_name', 
        " . ($location ? "'$location'" : "NULL") . ", 
        '$status', 
        '$category', 
        " . ($depreciation !== null ? "'$depreciation'" : "NULL") . ", 
        " . ($equipment_price !== null ? "'$equipment_price'" : "NULL") . ", 
        $user_id,
        '0000-00-00 00:00:00',
        '0000-00-00 00:00:00',
        " . ($brand ? "'$brand'" : "NULL") . ",
        " . ($specification ? "'$specification'" : "NULL") . ",
        " . ($equipment_categories ? "'$equipment_categories'" : "NULL") . "
    )";
    
    if ($con->query($insert_query)) {
        // Get the ID of the newly inserted equipment
        $equipment_id = $con->insert_id;
        
        // Expense recording and notifications moved to update_equipment.php when status is set to 'Delivered'
        
        header('Location: po_equipment.php?success=1');
        exit();
    } else {
        $err = urlencode($con->error);
        header("Location: po_equipment.php?error=$err");
        exit();
    }
}
?>