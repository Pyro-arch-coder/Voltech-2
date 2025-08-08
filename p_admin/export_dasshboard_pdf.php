<?php
ini_set('display_errors', 0);
error_reporting(0);
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 2) {
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
// Header Section

$pdf->Ln(20);

// --- Orders Summary Table (Count & Amount, Compact) ---

// Total Orders (from materials table)
$total_orders_q = mysqli_query($con, "SELECT COUNT(*) as cnt, IFNULL(SUM(total_amount),0) as amt FROM materials WHERE purchase_date BETWEEN '$start' AND '$end'");
    $total_orders_row = mysqli_fetch_assoc($total_orders_q);
    $total_orders = intval($total_orders_row['cnt']);
    $total_orders_amt = floatval($total_orders_row['amt']);
// Total Reorders (count)
$total_reorders_q = mysqli_query($con, "SELECT COUNT(*) as cnt FROM back_orders WHERE reason = 'Reorder' AND created_at BETWEEN '$start' AND '$end'");
    $total_reorders_row = mysqli_fetch_assoc($total_reorders_q);
    $total_reorders = intval($total_reorders_row['cnt']);
// Total Backorders (count)
$total_backorders_q = mysqli_query($con, "SELECT COUNT(*) as cnt FROM back_orders WHERE reason != 'Reorder' AND created_at BETWEEN '$start' AND '$end'");
$total_backorders_row = mysqli_fetch_assoc($total_backorders_q);
$total_backorders = intval($total_backorders_row['cnt']);
// Approved Materials (count & total amount)
$approved_materials_q = mysqli_query($con, "SELECT COUNT(*) as cnt, IFNULL(SUM(total_amount),0) as amt FROM materials WHERE purchase_date BETWEEN '$start' AND '$end'");
$approved_materials_row = mysqli_fetch_assoc($approved_materials_q);
$approved_materials = intval($approved_materials_row['cnt']);
$approved_materials_amt = floatval($approved_materials_row['amt']);
// Total Reorder Expenses
$reorder_exp_q = mysqli_query($con, "SELECT IFNULL(SUM(expense),0) as amt FROM order_expenses WHERE description LIKE '%Reorder%' AND expensedate BETWEEN '$start' AND '$end'");
$reorder_exp = ($row = mysqli_fetch_assoc($reorder_exp_q)) ? floatval($row['amt']) : 0;
// Total Backorder Expenses
$backorder_exp_q = mysqli_query($con, "SELECT IFNULL(SUM(expense),0) as amt FROM order_expenses WHERE description LIKE '%Backorder%' AND expensedate BETWEEN '$start' AND '$end'");
$backorder_exp = ($row = mysqli_fetch_assoc($backorder_exp_q)) ? floatval($row['amt']) : 0;
// Total Purchased Expenses
$purchased_exp_q = mysqli_query($con, "SELECT IFNULL(SUM(expense),0) as amt FROM order_expenses WHERE description LIKE '%Purchased A%' AND expensedate BETWEEN '$start' AND '$end'");
$purchased_exp = ($row = mysqli_fetch_assoc($purchased_exp_q)) ? floatval($row['amt']) : 0;
// For Total Purchased, use total_all as the count
$purchased_count = $total_orders + $total_reorders + $total_backorders;
// Calculate total count
$total_count = $total_orders + $total_reorders + $total_backorders;
// Calculate total all
$total_all = $total_orders + $total_reorders + $total_backorders;

$pdf->SetFont('Arial', 'B', 12);
$pdf->SetX(20);
$pdf->Cell(70, 8, 'Metric', 1);
$pdf->Cell(40, 8, 'Count', 1, 0, 'C');
$pdf->Cell(60, 8, 'Amount (Php)', 1, 0, 'C');
$pdf->Ln();
$pdf->SetFont('Arial', '', 9);
// Table rows
$pdf->SetX(20);
$pdf->Cell(70, 8, 'Total Orders', 1);
$pdf->Cell(40, 8, $total_orders, 1, 0, 'C');
$pdf->Cell(60, 8, 'Php ' . number_format($total_orders_amt,2), 1, 0, 'R');
$pdf->Ln();
$pdf->SetX(20);
$pdf->Cell(70, 8, 'Total Reorders', 1);
$pdf->Cell(40, 8, $total_reorders, 1, 0, 'C');
$pdf->Cell(60, 8, 'Php ' . number_format($reorder_exp,2), 1, 0, 'R');
$pdf->Ln();
$pdf->SetX(20);
$pdf->Cell(70, 8, 'Total Backorders', 1);
$pdf->Cell(40, 8, $total_backorders, 1, 0, 'C');
$pdf->Cell(60, 8, 'Php ' . number_format($backorder_exp,2), 1, 0, 'R');
$pdf->Ln(12);


$pdf->SetX(20);
$pdf->Cell(70, 8, 'Total', 1);
$pdf->Cell(40, 8, $total_orders + $total_reorders + $total_backorders, 1, 0, 'C');
$pdf->Cell(60, 8, 'Php ' . number_format($total_orders_amt + $reorder_exp + $backorder_exp,2), 1, 0, 'R');
$pdf->Ln(12);

// --- 3. Total Expenses Table (Equipment, Project, Materials) ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetX(20);
$pdf->Cell(70, 8, 'Metric', 1);
$pdf->Cell(40, 8, 'Count', 1, 0, 'C');
$pdf->Cell(60, 8, 'Amount (Php)', 1, 0, 'C');
$pdf->Ln();
$pdf->SetFont('Arial', '', 9);
// Table rows
$pdf->SetX(20);
$pdf->Cell(70, 8, 'Total Projects Expenses', 1);
$pdf->Cell(40, 8, $project_count, 1, 0, 'C');
$pdf->Cell(60, 8, 'Php ' . number_format($total_budget,2), 1, 0, 'R');
$pdf->Ln();
$pdf->SetX(20);
$pdf->Cell(70, 8, 'Total Materials Expenses', 1);
$pdf->Cell(40, 8, $approved_materials, 1, 0, 'C');
$pdf->Cell(60, 8, 'Php ' . number_format($approved_materials_amt,2), 1, 0, 'R');
$pdf->Ln();
$pdf->SetX(20);
$pdf->Cell(70, 8, 'Total Equipment Expenses', 1);
$equip_exp_q = mysqli_query($con, "SELECT COUNT(*) as cnt, IFNULL(SUM(equipment_price),0) as price FROM equipment WHERE created_at BETWEEN '$start' AND '$end'");
$equip_exp_row = mysqli_fetch_assoc($equip_exp_q);
$equip_count = isset($equip_exp_row['cnt']) ? intval($equip_exp_row['cnt']) : 0;
$equip_amt = isset($equip_exp_row['price']) ? floatval($equip_exp_row['price']) : 0;
$pdf->Cell(40, 8, $equip_count, 1, 0, 'C');
$pdf->Cell(60, 8, 'Php ' . number_format($equip_amt,2), 1, 0, 'R');
$pdf->Ln(12);

// Calculate total count and total amount for the summary
$total_expenses_count = $project_count + $approved_materials + $equip_count;
$total_expenses_amt = $total_budget + $approved_materials_amt + $equip_amt;

$pdf->SetX(20);
$pdf->Cell(70, 8, 'Total', 1);
$pdf->Cell(40, 8, $total_expenses_count, 1, 0, 'C');
$pdf->Cell(60, 8, 'Php ' . number_format($total_expenses_amt,2), 1, 0, 'R');
$pdf->Ln(12);

// Calculate total count and amount for Materials Orders Summary
$materials_total_count = $total_orders + $total_reorders + $total_backorders;
$materials_total_amt = $total_orders_amt + $reorder_exp + $backorder_exp;


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