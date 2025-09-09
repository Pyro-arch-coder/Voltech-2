<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

require_once '../config.php';

$supplier_id = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;

if (!$supplier_id) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid supplier ID']);
    exit();
}

// Fetch materials for the specific supplier
$sql = "SELECT id, material_name, quantity, unit, status, material_price, labor_other, category, low_stock_threshold, lead_time, brand, specification FROM suppliers_materials WHERE supplier_id = ? ORDER BY material_name";
$stmt = $con->prepare($sql);
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();

$materials = [];
while ($row = $result->fetch_assoc()) {
    $materials[] = $row;
}

$stmt->close();
$con->close();

header('Content-Type: application/json');
echo json_encode($materials);
?> 