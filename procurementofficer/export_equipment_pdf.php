<?php
require_once('../fpdf.php');
require_once '../config.php';

// Fetch all equipment
$sql = "SELECT equipment_name, category, depreciation, equipment_price FROM equipment ORDER BY equipment_name";
$result = $con->query($sql);

$pdf = new FPDF();
$pdf->AddPage();
$pdf->Image('../uploads/logo.jpg', 10, 10, 190, 40); // 40mm tall header image
$pdf->SetY(55); // 10 (top) + 40 (image) + 5 (space)
$pdf->SetDrawColor(120,120,120);
$pdf->Ln(2);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,7,'Procurement Officer',0,1,'L');
$pdf->Ln(2);
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'Equipment List',0,1,'C');
$pdf->Ln(2);

// Table header
$pdf->SetFont('Arial','B',10);
$header = ['No', 'Equipment Name', 'Rent/Company', 'Depreciation', 'Equipment Price'];
$widths = [10, 50, 35, 35, 50]; // Total: 180mm
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
    $pdf->Cell($widths[1], 7, $row['equipment_name'], 1);
    $pdf->Cell($widths[2], 7, $row['category'], 1);
    
    // Depreciation formatting
    if (isset($row['depreciation']) && $row['depreciation'] !== '') {
        $depr = floatval($row['depreciation']);
        $deprStr = (intval($depr) == $depr) ? intval($depr) . ' yrs' : $depr . ' yrs';
    } else {
        $deprStr = '—';
    }
    $pdf->Cell($widths[3], 7, $deprStr, 1);
    
    // Equipment Price
    $equipPrice = (isset($row['equipment_price']) && $row['equipment_price'] !== '') ? 'Php ' . number_format($row['equipment_price'], 2) : '—';
    $pdf->Cell($widths[4], 7, $equipPrice, 1);
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

$pdf->Output('D', 'equipment.pdf');
exit; 