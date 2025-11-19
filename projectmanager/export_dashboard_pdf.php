<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 3) {
    die('Unauthorized');
}
require_once('../fpdf.php');
include_once "../config.php";
if ($con->connect_error) die("Connection failed: " . $con->connect_error);
$userid = $_SESSION['user_id'];
$start = isset($_POST['start_date']) ? $_POST['start_date'] : null;
$end = isset($_POST['end_date']) ? $_POST['end_date'] : null;
if (!$start || !$end) die('Date range required');

// Project analytics (from projects table)
$project_count = 0;
$category_counts = ['Renovation' => 0, 'Building' => 0];
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

// Get total profit from project_profits table
$profit_query = mysqli_query($con, "SELECT SUM(profit) as total_profit FROM project_profits");
$profit_row = mysqli_fetch_assoc($profit_query);
$total_profit = isset($profit_row['total_profit']) ? floatval($profit_row['total_profit']) : 0;

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

$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 12, 'Dashboard Summary', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Ln(4);
$pdf->Cell(0, 8, "Date Range: $start to $end", 0, 1);
$pdf->Ln(2);

// Table for Project Analytics (full width)
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Project Analytics', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(95, 8, 'Metric', 1, 0, 'C');
$pdf->Cell(95, 8, 'Value', 1, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(95, 8, 'Total Projects', 1);
$pdf->Cell(95, 8, $project_count, 1, 1);
$pdf->Cell(95, 8, 'Average Project Budget', 1);
$pdf->Cell(95, 8, 'Php ' . number_format($average_budget, 2), 1, 1);
$pdf->Cell(95, 8, 'Total Profit', 1);
$pdf->Cell(95, 8, 'Php ' . number_format($total_profit, 2), 1, 1);
// Projects by Category (full width)
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(190, 8, 'Projects by Category', 1, 1, 'C');
$pdf->SetFont('Arial', '', 10);
foreach ($category_counts as $cat => $count) {
    $pdf->Cell(95, 8, $cat, 1);
    $pdf->Cell(95, 8, $count, 1, 1);
}
// Total Expenses (full width)
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(190, 10, 'Total Expenses', 1, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(95, 8, 'Total Expenses (from ' . $start . ' to ' . $end . ')', 1);
$pdf->Cell(95, 8, 'Php ' . number_format($total_expenses, 2), 1, 1);
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