<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
    die('Unauthorized');
}
require_once('../fpdf.php');
require_once '../config.php';
if ($con->connect_error) die("Connection failed: " . $con->connect_error);
$userid = $_SESSION['user_id'];
$start = isset($_POST['start_date']) ? $_POST['start_date'] : null;
$end = isset($_POST['end_date']) ? $_POST['end_date'] : null;
if (!$start || !$end) die('Date range required');

// Project analytics (from projects table)
$project_count = 0;
$category_counts = ['House' => 0, 'Renovation' => 0, 'Building' => 0];
$total_budget = 0;
$proj_query = mysqli_query($con, "SELECT category, budget FROM projects WHERE user_id='$userid'");
while ($row = mysqli_fetch_assoc($proj_query)) {
    $project_count++;
    $cat = ucfirst(strtolower(trim($row['category'])));
    if (isset($category_counts[$cat])) $category_counts[$cat]++;
    $total_budget += floatval($row['budget']);
}
$average_budget = $project_count > 0 ? $total_budget / $project_count : 0;
$estimated_total_expenses = $total_budget; // This is the sum of all project budgets

// Total expenses (from expenses table, filtered by expensedate)
$total_expenses = 0;
$exp_query = mysqli_query($con, "SELECT SUM(expense) as total FROM expenses WHERE user_id='$userid' AND expensedate BETWEEN '$start' AND '$end'");
if ($row = mysqli_fetch_assoc($exp_query)) {
    $total_expenses = $row['total'] ?? 0;
}

// PDF output
$pdf = new FPDF();
$pdf->AddPage();
$pdf->Image('../uploads/logo.jpg', 10, 10, 190, 40); // 40mm tall header image
$pdf->SetY(55); // 10 (top) + 40 (image) + 5 (space)
$pdf->Ln(2);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,7,'Procurement Officer',0,1,'L');
$pdf->Ln(2);

// --- Orders, Reorders, Backorders Table ---
$pdf->Ln(8);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Orders, Reorders, Backorders Summary', 0, 1);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(90, 8, 'Type', 1);
$pdf->Cell(40, 8, 'Count', 1, 0, 'C');
$pdf->Cell(60, 8, 'Amount (Php)', 1, 0, 'C');
$pdf->Ln();
$pdf->SetFont('Arial', '', 9);
// Total Orders - Removed approval filter as it doesn't exist in the table
$total_orders_q = mysqli_query($con, "SELECT COUNT(*) as cnt, IFNULL(SUM(total_amount),0) as amt FROM materials WHERE purchase_date BETWEEN '$start' AND '$end'");
$total_orders_row = mysqli_fetch_assoc($total_orders_q);
$total_orders = intval($total_orders_row['cnt']);
$total_orders_amt = floatval($total_orders_row['amt']);
// Total Reorders
$total_reorders_q = mysqli_query($con, "SELECT COUNT(*) as cnt FROM back_orders WHERE reason = 'Reorder' AND created_at BETWEEN '$start' AND '$end'");
$total_reorders_row = mysqli_fetch_assoc($total_reorders_q);
$total_reorders = intval($total_reorders_row['cnt']);
$reorder_exp_q = mysqli_query($con, "SELECT IFNULL(SUM(expense),0) as amt FROM order_expenses WHERE description LIKE '%Reorder%' AND expensedate BETWEEN '$start' AND '$end'");
$reorder_exp = ($row = mysqli_fetch_assoc($reorder_exp_q)) ? floatval($row['amt']) : 0;
// Total Backorders
$total_backorders_q = mysqli_query($con, "SELECT COUNT(*) as cnt FROM back_orders WHERE reason != 'Reorder' AND created_at BETWEEN '$start' AND '$end'");
$total_backorders_row = mysqli_fetch_assoc($total_backorders_q);
$total_backorders = intval($total_backorders_row['cnt']);
$backorder_exp_q = mysqli_query($con, "SELECT IFNULL(SUM(expense),0) as amt FROM order_expenses WHERE description LIKE '%Backorder%' AND expensedate BETWEEN '$start' AND '$end'");
$backorder_exp = ($row = mysqli_fetch_assoc($backorder_exp_q)) ? floatval($row['amt']) : 0;
// Table rows
$pdf->Cell(90, 8, 'Total Orders', 1);
$pdf->Cell(40, 8, $total_orders, 1, 0, 'C');
$pdf->Cell(60, 8, 'Php ' . number_format($total_orders_amt,2), 1, 0, 'R');
$pdf->Ln();
$pdf->Cell(90, 8, 'Total Reorders', 1);
$pdf->Cell(40, 8, $total_reorders, 1, 0, 'C');
$pdf->Cell(60, 8, 'Php ' . number_format($reorder_exp,2), 1, 0, 'R');
$pdf->Ln();
$pdf->Cell(90, 8, 'Total Backorders', 1);
$pdf->Cell(40, 8, $total_backorders, 1, 0, 'C');
$pdf->Cell(60, 8, 'Php ' . number_format($backorder_exp,2), 1, 0, 'R');
$pdf->Ln();
// Total row
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(90, 8, 'TOTAL', 1);
$pdf->Cell(40, 8, $total_orders + $total_reorders + $total_backorders, 1, 0, 'C');
$pdf->Cell(60, 8, 'Php ' . number_format($total_orders_amt + $reorder_exp + $backorder_exp,2), 1, 0, 'R');
$pdf->Ln(10);
// --- Purchased Equipment Table ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Purchased/Added Equipment', 0, 1);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(70, 8, 'Name', 1);
$pdf->Cell(40, 8, 'Category', 1);
$pdf->Cell(40, 8, 'Price', 1);
$pdf->Cell(40, 8, 'Date Added', 1);
$pdf->Ln();
$pdf->SetFont('Arial', '', 8);
$equip_query = mysqli_query($con, "SELECT equipment_name, category, equipment_price, created_at FROM equipment WHERE created_at BETWEEN '$start' AND '$end' ORDER BY created_at DESC LIMIT 10");
$equip_count = 0;
$equip_total_amt = 0;
if (mysqli_num_rows($equip_query) > 0) {
    while ($row = mysqli_fetch_assoc($equip_query)) {
        $pdf->Cell(70, 8, $row['equipment_name'], 1);
        $pdf->Cell(40, 8, $row['category'], 1);
        $price = $row['equipment_price'];
        $pdf->Cell(40, 8, 'Php ' . number_format($price,2), 1, 0, 'R');
        $pdf->Cell(40, 8, date('M d, Y', strtotime($row['created_at'])), 1);
        $pdf->Ln();
        $equip_count++;
        $equip_total_amt += floatval($price);
    }
    // Total row
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(110, 8, 'TOTAL', 1);
    $pdf->Cell(40, 8, $equip_count, 1, 0, 'C');
    $pdf->Cell(40, 8, 'Php ' . number_format($equip_total_amt,2), 1, 0, 'R');
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 8);
} else {
    $pdf->Cell(190, 8, 'No equipment added in this period.', 1, 1, 'C');
}
$pdf->Ln(8);
// --- All Suppliers and Their Materials Table ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Suppliers and Materials List', 0, 1);
$pdf->Ln(2);
$pdf->SetFont('Arial', '', 10);
$suppliers = $con->query("SELECT * FROM suppliers ORDER BY supplier_name");
while ($supplier = $suppliers->fetch_assoc()) {
    $pdf->SetFont('Arial','B',11);
    $pdf->Cell(0,8,'Supplier: ' . $supplier['supplier_name'],0,1,'L');
    $pdf->SetFont('Arial','',9);
    $pdf->Cell(0,6,'Contact Person: ' . $supplier['firstname'] . ' ' . $supplier['lastname'],0,1,'L');
    $pdf->Cell(0,6,'Contact Number: ' . $supplier['contact_number'],0,1,'L');
    $pdf->Ln(1);
    // Fetch materials for this supplier
    $materials = $con->query("SELECT material_name, quantity, unit, status, material_price, labor_other, low_stock_threshold, lead_time FROM suppliers_materials WHERE supplier_id = " . intval($supplier['id']) . " ORDER BY material_name");
    if ($materials->num_rows > 0) {
        // Table header
        $pdf->SetFont('Arial','B',8);
        $header = ['#', 'Material Name', 'Qty', 'Unit', 'Price', 'Labor/Other', 'Low Stock', 'Lead Time'];
        $widths = [8, 48, 14, 16, 28, 28, 18, 18]; // Total: 178mm
        $leftMargin = 10;
        $pdf->SetLeftMargin($leftMargin);
        $tableWidth = array_sum($widths);
        $pageWidth = $pdf->GetPageWidth() - 2 * $leftMargin;
        $x = ($pageWidth - $tableWidth) / 2 + $leftMargin;
        $pdf->SetX($x);
        foreach ($header as $i => $col) {
            $pdf->Cell($widths[$i], 7, $col, 1, 0, 'C');
        }
        $pdf->Ln();
        $pdf->SetFont('Arial','',8);
        $count = 1;
        while ($row = $materials->fetch_assoc()) {
            $pdf->SetX($x);
            $pdf->Cell($widths[0], 6, $count++, 1, 0, 'C');
            $pdf->Cell($widths[1], 6, $row['material_name'], 1);
            $pdf->Cell($widths[2], 6, $row['quantity'], 1, 0, 'C');
            $pdf->Cell($widths[3], 6, $row['unit'], 1, 0, 'C');
            $pdf->Cell($widths[4], 6, 'Php ' . number_format($row['material_price'], 2), 1, 0, 'R');
            $pdf->Cell($widths[5], 6, 'Php ' . number_format($row['labor_other'], 2), 1, 0, 'R');
            $pdf->Cell($widths[6], 6, $row['low_stock_threshold'], 1, 0, 'C');
            $pdf->Cell($widths[7], 6, $row['lead_time'] . 'd', 1, 0, 'C');
            $pdf->Ln();
        }
        $pdf->Ln(4);
    } else {
        $pdf->SetFont('Arial','I',8);
        $pdf->Cell(0,6,'No materials found for this supplier.',0,1,'L');
        $pdf->Ln(2);
    }
    $pdf->Ln(2);
}
$pdf->Ln(10);

$pdf->Ln(20); // Add some space after the tables
$pdf->SetFont('Arial','B',10); // Smaller font
$pdf->Cell(0,6,'SUBMITTED BY:',0,1,'L');
$pdf->SetFont('Arial','B',11);
$pdf->Cell(0,6,'VOLTECH ELECTRICAL CONST.',0,1,'L');
$pdf->Ln(6);
// No image
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0,6,'BY: _______________',0,1,'L');
$pdf->Cell(0,6,'ENGR. ROMEO MATIAS',0,1,'L');
$pdf->SetFont('Arial','',9);
$pdf->Cell(0,6,'General Manager',0,1,'L');

$pdf->Output('D', 'dashboard_summary.pdf'); 