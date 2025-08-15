<?php
// Start output buffering to prevent any accidental output
ob_start();

// Error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Check if user is logged in and is a supplier (user_level = 5)
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 5) {
    die('Unauthorized');
}

// Include required files
require_once('../fpdf.php');
require_once '../config.php';

// Check database connection
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

// Get user ID and date range
$userid = $_SESSION['user_id'];
$start = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01'); // Default to start of current month
$end = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-t'); // Default to end of current month

// Validate date range
if (!$start || !$end) {
    die('Date range is required');
}

// Get supplier name
$supplier_name = 'Supplier';
$supplier_query = mysqli_query($con, "SELECT firstname, lastname FROM users WHERE id = '$userid'");
if (!$supplier_query) {
    die('DB error: '.mysqli_error($con));
}
if ($supplier_row = mysqli_fetch_assoc($supplier_query)) {
    $supplier_name = trim($supplier_row['firstname'] . ' ' . $supplier_row['lastname']);
    if (empty(trim($supplier_name))) {
        $supplier_name = 'Supplier';
    }
}

// Clear any output that might have been generated before
ob_clean();

// Get order summary by type
$order_summary = [
    'material' => ['count' => 0, 'label' => 'Materials'],
    'reorder' => ['count' => 0, 'label' => 'Reorders'],
    'backorder' => ['count' => 0, 'label' => 'Backorders']
];

$summary_query = mysqli_query($con, 
    "SELECT type, COUNT(*) as count 
    FROM suppliers_orders_approved 
    WHERE user_id = '$userid' 
    AND approve_date BETWEEN '$start' AND '$end'
    GROUP BY type"
);
if (!$summary_query) die('DB error: '.mysqli_error($con));
while ($row = mysqli_fetch_assoc($summary_query)) {
    if (isset($order_summary[$row['type']])) {
        $order_summary[$row['type']]['count'] = (int)$row['count'];
    }
}

// Get recent orders for the table
$recent_orders = [];
$orders_query = mysqli_query($con, 
    "SELECT id, type, approve_date
    FROM suppliers_orders_approved 
    WHERE user_id = '$userid' 
    AND approve_date BETWEEN '$start' AND '$end'
    ORDER BY approve_date DESC"
);
if (!$orders_query) die('DB error: '.mysqli_error($con));
while ($row = mysqli_fetch_assoc($orders_query)) {
    $recent_orders[] = $row;
}

// PDF output
$pdf = new FPDF();
$pdf->AddPage();

// Add company logo and header
$pdf->Image('../uploads/logo.jpg', 10, 10, 190, 40); // 40mm tall header image
$pdf->SetY(55); // 10 (top) + 40 (image) + 5 (space)

// Header Section
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'SUPPLIER ORDER REPORT', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, $supplier_name, 0, 1, 'C');
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 10, 'Date Range: ' . date('M d, Y', strtotime($start)) . ' to ' . date('M d, Y', strtotime($end)), 0, 1, 'C');
$pdf->Ln(5);

// Order Summary Section
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Order Summary', 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);

// Order Summary Table
$pdf->SetX(20);
$pdf->Cell(120, 8, 'Order Type', 1);
$pdf->Cell(50, 8, 'Count', 1, 0, 'C');
$pdf->Ln();

$total_orders = 0;
foreach ($order_summary as $type => $data) {
    $pdf->SetX(20);
    $pdf->Cell(120, 8, $data['label'], 1);
    $pdf->Cell(50, 8, $data['count'], 1, 0, 'C');
    $pdf->Ln();
    $total_orders += $data['count'];
}

// Total row
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetX(20);
$pdf->Cell(120, 8, 'Total Orders', 1);
$pdf->Cell(50, 8, $total_orders, 1, 0, 'C');
$pdf->Ln(15);

// Recent Orders Section
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Recent Orders', 0, 1, 'L');

if (!empty($recent_orders)) {
    // Table Header
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetX(10);
    $pdf->Cell(15, 8, 'No.', 1, 0, 'C');
    $pdf->Cell(30, 8, 'Order ID', 1, 0, 'C');
    $pdf->Cell(40, 8, 'Type', 1, 0, 'C');
    $pdf->Cell(35, 8, 'Date', 1, 0, 'C');
    $pdf->Ln();

    // Table Rows
    $pdf->SetFont('Arial', '', 9);
    $counter = 1;
    foreach ($recent_orders as $order) {
        $pdf->SetX(10);
        $pdf->Cell(15, 8, $counter++, 1, 0, 'C');
        $pdf->Cell(30, 8, '#' . $order['id'], 1, 0, 'C');
        $pdf->Cell(40, 8, ucfirst($order['type']), 1, 0, 'C');
        $pdf->Cell(35, 8, date('M d, Y', strtotime($order['approve_date'])), 1, 0, 'C');
        $pdf->Ln();
    }
} else {
    $pdf->Cell(0, 10, 'No orders found for the selected date range.', 0, 1, 'C');
}

// Footer
$pdf->SetY(-50);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, 'SUBMITTED BY:', 0, 1, 'L');
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, strtoupper($supplier_name), 0, 1, 'L');
$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, 'BY: _______________', 0, 1, 'L');
$pdf->Cell(0, 6, 'SIGNATURE', 0, 1, 'L');

// Generate filename with date range
$filename = 'Supplier_Order_Report_' . date('Y-m-d', strtotime($start)) . '_to_' . date('Y-m-d', strtotime($end)) . '.pdf';

// Output PDF
$pdf->Output('D', $filename);

// Close database connection
if (isset($con)) {
    mysqli_close($con);
}
?>