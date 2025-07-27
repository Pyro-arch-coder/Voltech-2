<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
    header("Location: ../login.php");
    exit();
}
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equipment_name = mysqli_real_escape_string($con, $_POST['equipment_name']);
    $status = 'Available'; // Default status
    $category = mysqli_real_escape_string($con, $_POST['category']);
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    $user_level = isset($_SESSION['user_level']) ? intval($_SESSION['user_level']) : 0;
    $user_name = isset($_SESSION['firstname']) && isset($_SESSION['lastname']) ? trim($_SESSION['firstname'] . ' ' . $_SESSION['lastname']) : '';
    $equipment_price = isset($_POST['equipment_price']) && $_POST['equipment_price'] !== '' ? floatval($_POST['equipment_price']) : null;
    
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
        return_time
    ) VALUES (
        '$equipment_name', 
        " . ($location ? "'$location'" : "NULL") . ", 
        '$status', 
        '$category', 
        " . ($depreciation !== null ? "'$depreciation'" : "NULL") . ", 
        " . ($equipment_price !== null ? "'$equipment_price'" : "NULL") . ", 
        $user_id,
        '0000-00-00 00:00:00',
        '0000-00-00 00:00:00'
    )";
    
    if ($con->query($insert_query)) {
        // Get the ID of the newly inserted equipment
        $equipment_id = $con->insert_id;
        
        // Record the expense if equipment has a price
        if ($equipment_price > 0) {
            $expense_description = "Purchased A $equipment_name";
            $expense_query = "INSERT INTO order_expenses 
                            (user_id, expense, expensedate, expensecategory, description) 
                            VALUES (?, ?, CURDATE(), 'Equipment', ?)";
            $stmt = $con->prepare($expense_query);
            $stmt->bind_param("ids", $user_id, $equipment_price, $expense_description);
            $stmt->execute();
            $stmt->close();
        }
        
        // Insert notification
        $notif_type = "New Equipment Added";
        $notif_message = "A new equipment $equipment_name has been added to inventory.";
        
        $notif_query = "INSERT INTO notifications_projectmanager 
                      (user_id, notif_type, message, is_read, created_at) 
                      VALUES (?, ?, ?, 0, NOW())";
        $stmt = $con->prepare($notif_query);
        $stmt->bind_param("iss", $user_id, $notif_type, $notif_message);
        $stmt->execute();
        $stmt->close();
        
        header('Location: po_equipment.php?success=1');
        exit();
    } else {
        $err = urlencode($con->error);
        header("Location: po_equipment.php?error=$err");
        exit();
    }
} 