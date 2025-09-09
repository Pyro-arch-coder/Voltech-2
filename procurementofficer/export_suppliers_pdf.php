<?php
require_once('../fpdf.php');
require_once '../config.php';

// Fetch all suppliers
$suppliers = $con->query("SELECT * FROM suppliers ORDER BY supplier_name");

$pdf = new FPDF();
$pdf->AddPage();
$pdf->Image('../uploads/logo.jpg', 10, 10, 190, 40); // 40mm tall header image
$pdf->SetY(55); // 10 (top) + 40 (image) + 5 (space)
$pdf->Ln(2);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,7,'Procurement Officer',0,1,'L');
$pdf->Ln(2);
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'Suppliers and Materials List',0,1,'C');
$pdf->Ln(2);

$pdf->SetFont('Arial','',10);

while ($supplier = $suppliers->fetch_assoc()) {
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,8,'Supplier: ' . $supplier['supplier_name'],0,1,'L');
    $pdf->SetFont('Arial','',10);
    $pdf->Cell(0,6,'Contact Person: ' . $supplier['firstname'] . ' ' . $supplier['lastname'],0,1,'L');
    $pdf->Cell(0,6,'Contact Number: ' . $supplier['contact_number'],0,1,'L');
    $pdf->Ln(1);
    // Fetch materials for this supplier
    $materials = $con->query("SELECT material_name, quantity, unit, status, material_price, labor_other, low_stock_threshold, lead_time FROM suppliers_materials WHERE supplier_id = " . intval($supplier['id']) . " ORDER BY material_name");
    if ($materials->num_rows > 0) {
        // Table header
        $pdf->SetFont('Arial','B',9);
        $header = ['#', 'Material Name', 'Qty', 'Unit', 'Status', 'Price', 'Labor/Other', 'Low Stock', 'Lead Time'];
        $widths = [8, 40, 12, 14, 18, 22, 22, 18, 18]; // Total: 172mm
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
            $pdf->Cell($widths[4], 6, $row['status'], 1, 0, 'C');
            $pdf->Cell($widths[5], 6, 'Php ' . number_format($row['material_price'], 2), 1, 0, 'R');
            $pdf->Cell($widths[6], 6, 'Php ' . number_format($row['labor_other'], 2), 1, 0, 'R');
            $pdf->Cell($widths[7], 6, $row['low_stock_threshold'], 1, 0, 'C');
            $pdf->Cell($widths[8], 6, $row['lead_time'] . 'd', 1, 0, 'C');
            $pdf->Ln();
        }
        $pdf->Ln(4);
    } else {
        $pdf->SetFont('Arial','I',9);
        $pdf->Cell(0,6,'No materials found for this supplier.',0,1,'L');
        $pdf->Ln(2);
    }
    $pdf->Ln(2);
}

$pdf->Ln(10);
$pdf->SetFont('Arial','B',9); // Smaller font
$pdf->Cell(0,6,'SUBMITTED BY:',0,1,'L');
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0,6,'VOLTECH ELECTRICAL CONST.',0,1,'L');
$pdf->Ln(4);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(0,6,'BY: _______________',0,1,'L');
$pdf->Cell(0,6,'ENGR. ROMEO MATIAS',0,1,'L');
$pdf->SetFont('Arial','',8);
$pdf->Cell(0,6,'General Manager',0,1,'L');

$pdf->Output('D', 'suppliers_materials.pdf');
exit; 