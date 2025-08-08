<?php
require_once('../fpdf.php');
require_once '../config.php';

// Fetch all materials
$sql = "SELECT category, material_name, quantity, unit, status, supplier_name, total_amount FROM materials ORDER BY category, material_name";
$result = $con->query($sql);

$pdf = new FPDF();
$pdf->AddPage();
$pdf->Image('../uploads/logo.jpg', 10, 10, 190, 40); // 40mm tall header image
$pdf->SetY(55); // 10 (top) + 40 (image) + 5 (space)
$pdf->Ln(2);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,7,'Procurement Officer',0,1,'L');
$pdf->Ln(2);
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'Materials List',0,1,'C');
$pdf->Ln(2);

// Table header
$pdf->SetFont('Arial','B',10);
$header = ['#', 'Category', 'Material Name', 'Qty', 'Unit', 'Status', 'Supplier', 'Total Amount'];
$widths = [10, 28, 40, 15, 15, 20, 40, 32]; // Total: 180mm
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
    $pdf->Cell($widths[1], 7, $row['category'], 1);
    $pdf->Cell($widths[2], 7, $row['material_name'], 1);
    $pdf->Cell($widths[3], 7, $row['quantity'], 1, 0, 'C');
    $pdf->Cell($widths[4], 7, $row['unit'], 1, 0, 'C');
    $pdf->Cell($widths[5], 7, $row['status'], 1, 0, 'C');
    $pdf->Cell($widths[6], 7, $row['supplier_name'], 1);
    $pdf->Cell($widths[7], 7, 'Php ' . number_format($row['total_amount'], 2), 1, 0, 'C');
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

$pdf->Output('D', 'materials.pdf');
exit;
