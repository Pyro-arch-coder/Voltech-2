<?php
require_once('../fpdf.php');

// Database connection
include_once "../config.php";

$project_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_GET['project_id']) ? intval($_GET['project_id']) : 0);
if (!$project_id) {
    die('Invalid project ID.');
}

// Fetch project details
$project = $con->query("SELECT * FROM projects WHERE project_id='$project_id'")->fetch_assoc();
if (!$project) die('Project not found.');

// Calculate project days
$start = new DateTime($project['start_date']);
$end = new DateTime($project['deadline']);
$interval = $start->diff($end);
$project_days = $interval->days + 1;

// Start transaction
$con->begin_transaction();

try {
    // Calculate total estimation cost from materials
    $materials = [];
    $mat_total = 0;
    $mat_query = $con->query("SELECT pem.*, m.material_name, m.unit, m.material_price, m.labor_other 
                             FROM project_estimating_materials pem 
                             LEFT JOIN materials m ON pem.material_id = m.id 
                             WHERE pem.project_id = '$project_id'");
    
    while($row = $mat_query->fetch_assoc()) {
        $materials[] = $row;
        // Calculate total using the same formula as grand total: (material_price + labor_other) * quantity
        $material_price = floatval($row['material_price'] ?? 0);
        $labor_other = floatval($row['labor_other'] ?? 0);
        $quantity = floatval($row['quantity'] ?? 0);
        $item_total = ($material_price + $labor_other) * $quantity;
        $mat_total += $item_total;
    }
    
    // Update total_estimation_cost in projects table
    $update_query = "UPDATE projects SET total_estimation_cost = $mat_total WHERE project_id = $project_id";
    if (!$con->query($update_query)) {
        throw new Exception("Failed to update project total estimation cost: " . $con->error);
    }
    
    // Commit the transaction
    $con->commit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    $con->rollback();
    die('Error: ' . $e->getMessage());
}

// Create PDF instance
$pdf = new FPDF();
$pdf->AddPage();

// Logo (left)
$pdf->Image('../uploads/voltech_logo_transparent.png', 10, 10, 28);
// Business name and info (right)
$pdf->Image('../uploads/logo.jpg', 10, 10, 190, 40); // 40mm tall header image
$pdf->SetY(55); // 10 (top) + 40 (image) + 5 (space)
// Header Section

$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'Project Details',0,1,'C');
$pdf->SetFont('Arial','',12);
$pdf->Cell(0,8,'Project Name: ' . $project['project'],0,1);
$pdf->Cell(0,8,'Location: ' . $project['location'],0,1);
$pdf->Ln(4);

// Bill of Materials and Labor
$pdf->SetFont('Arial','B',13);
$pdf->Cell(0,8,'Bill of Materials and Labor',0,1);
// Set column widths to fit exactly 190mm (from x=10 to x=200)
$col_widths = [10, 55, 15, 15, 25, 25, 20, 25]; // sum = 190
$pdf->SetFont('Arial','B',10);
$pdf->Cell($col_widths[0],7,'#',1);
$pdf->Cell($col_widths[1],7,'Description',1);
$pdf->Cell($col_widths[2],7,'Qty',1);
$pdf->Cell($col_widths[3],7,'Unit',1);
$pdf->Cell($col_widths[4],7,'Material Price',1);
$pdf->Cell($col_widths[5],7,'Labor/Other',1);
$pdf->Cell($col_widths[6],7,'Amount',1);
$pdf->Cell($col_widths[7],7,'Total',1);
$pdf->Ln();
$pdf->SetFont('Arial','',10);
$i=1;
foreach($materials as $mat) {
    $desc = $mat['material_name'] ?? '';
    $qty = is_numeric($mat['quantity']) ? floatval($mat['quantity']) : 0;
    $unit = $mat['unit'] ?? '';
    $mat_price = is_numeric($mat['material_price']) ? floatval($mat['material_price']) : 0;
    $labor = is_numeric($mat['labor_other']) ? floatval($mat['labor_other']) : 0;
    $amount = $mat_price + $labor;
    $total = $amount * $qty;
    $pdf->Cell($col_widths[0],7,$i++,1);
    $pdf->Cell($col_widths[1],7,$desc,1);
    $pdf->Cell($col_widths[2],7,$qty,1);
    $pdf->Cell($col_widths[3],7,$unit,1);
    $pdf->Cell($col_widths[4],7,number_format($mat_price,2),1);
    $pdf->Cell($col_widths[5],7,number_format($labor,2),1);
    $pdf->Cell($col_widths[6],7,number_format($amount,2),1);
    $pdf->Cell($col_widths[7],7,number_format($total,2),1);
    $pdf->Ln();
}
$pdf->Cell(array_sum(array_slice($col_widths,0,7)),7,'Total',1);
$pdf->Cell($col_widths[7],7,number_format($mat_total,2),1);
$pdf->Ln(12);

// Responsive signature section (no fixed SetY)
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

$pdf->Output('D', 'project_materials_estimation'.$project_id.'.pdf'); 