<?php
require_once '../config.php';
require_once '../vendor/autoload.php';

// Check if user is logged in and is a supplier
if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 5) {
    die('Access Denied');
}

// Create new PDF document with UTF-8 support
$pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document metadata
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Voltech');
$pdf->SetTitle('Materials List');
$pdf->SetSubject('Materials List');

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Set some language-dependent strings (optional)
if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
    require_once(dirname(__FILE__).'/lang/eng.php');
    $pdf->setLanguageArray($l);
}

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Voltech');
$pdf->SetTitle('Materials List');
$pdf->SetSubject('Materials List');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Add a page
$pdf->AddPage();

// Set font with UTF-8 support
$pdf->SetFont('dejavusans', '', 10);

// Add a title
$pdf->SetFont('dejavusans', 'B', 16);
$pdf->Cell(0, 10, 'Materials List', 0, 1, 'C');
$pdf->Ln(10);

// Set font for table
$pdf->SetFont('dejavusans', '', 9);

// Get supplier_id based on user's email
$supplier_query = "SELECT s.id as supplier_id FROM suppliers s 
                INNER JOIN users u ON s.email = u.email 
                WHERE u.id = ?";
$supplier_stmt = $con->prepare($supplier_query);
$supplier_stmt->bind_param("i", $_SESSION['user_id']);
$supplier_stmt->execute();
$supplier_result = $supplier_stmt->get_result();
$supplier_data = $supplier_result->fetch_assoc();
$supplier_id = $supplier_data['supplier_id'];

// Get materials data
$query = "SELECT * FROM suppliers_materials 
          WHERE supplier_id = ? 
          ORDER BY material_name";
$stmt = $con->prepare($query);
$stmt->bind_param('i', $supplier_id);
$stmt->execute();
$result = $stmt->get_result();

// Create table header
$header = array('No', 'Material Name', 'Brand', 'Category', 'Specification', 'Unit', 'Price', 'Status');

// Column widths
$w = array(10, 50, 30, 30, 60, 20, 25, 20);

// Header
for($i = 0; $i < count($header); $i++) {
    $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C');
}
$pdf->Ln();

// Data
$counter = 1;
$pdf->SetFont('helvetica', '', 8);
while($row = $result->fetch_assoc()) {
    $pdf->Cell($w[0], 6, $counter++, 'LR', 0, 'C');
    $pdf->Cell($w[1], 6, $row['material_name'], 'LR');
    $pdf->Cell($w[2], 6, $row['brand'], 'LR');
    $pdf->Cell($w[3], 6, $row['category'], 'LR');
    
    // Handle long specifications with MultiCell
    $pdf->Cell($w[4], 6, $row['specification'], 'LR', 0, 'L', 0, '', 1);
    
    $pdf->Cell($w[5], 6, $row['unit'], 'LR', 0, 'C');
    $pdf->Cell($w[6], 6, '₱' . number_format($row['material_price'], 2), 'LR', 0, 'R');
    $pdf->Cell($w[7], 6, $row['status'], 'LR', 0, 'C');
    $pdf->Ln();
}

// Closing line
$pdf->Cell(array_sum($w), 0, '', 'T');

// Close and output PDF document
$pdf->Output('materials_list_' . date('Y-m-d') . '.pdf', 'D');

// Close database connection
$stmt->close();
$con->close();
?>
