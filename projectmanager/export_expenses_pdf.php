<?php
require_once('../fpdf.php');
include_once "../config.php";

// Fetch all expenses
$sql = "SELECT expensedate, expensecategory, expense, description FROM expenses ORDER BY expensedate DESC";
$result = $con->query($sql);

$pdf = new FPDF();
$pdf->AddPage();
$pdf->Image('../uploads/logo.jpg', 10, 10, 190, 40); // 40mm tall header image
$pdf->SetY(55); // 10 (top) + 40 (image) + 5 (space)
// Header Section

$pdf->Ln(20);

// Table header
$pdf->SetFont('Arial','B',10);
$header = ['#', 'Date', 'Type', 'Amount', 'Description'];
$widths = [12, 32, 40, 30, 90]; // Adjusted for A4 portrait
$leftMargin = 10;
$pdf->SetLeftMargin($leftMargin);
$tableWidth = array_sum($widths);
$pageWidth = $pdf->GetPageWidth() - 2 * $leftMargin;
$x = ($pageWidth - $tableWidth) / 2 + $leftMargin;
$pdf->SetX($x);
foreach ($header as $i => $col) {
    $pdf->Cell($widths[$i], 8, $col, 1, 0, 'C');
}
$pdf->Ln();

$pdf->SetFont('Arial','',9);
$count = 1;
while ($row = $result->fetch_assoc()) {
    $pdf->SetX($x);
    $pdf->Cell($widths[0], 7, $count++, 1, 0, 'C');
    $pdf->Cell($widths[1], 7, date('M d, Y', strtotime($row['expensedate'])), 1);
    $pdf->Cell($widths[2], 7, $row['expensecategory'], 1);
    $pdf->Cell($widths[3], 7, 'Php ' . number_format($row['expense'], 2), 1, 0, 'C');
    $pdf->Cell($widths[4], 7, $row['description'], 1);
    $pdf->Ln();
}

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

$pdf->Output('D', 'expenses.pdf');
exit; 