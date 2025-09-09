<?php
require_once('../fpdf.php');

// Database connection
include_once "../config.php";

$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
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

// Fetch employees
$employees = $con->query("SELECT pae.*, e.first_name, e.last_name FROM project_add_employee pae LEFT JOIN employees e ON pae.employee_id = e.employee_id WHERE pae.project_id = '$project_id'");
$emp_total = 0;
$emp_total_query = $con->query("SELECT total FROM project_add_employee WHERE project_id='$project_id'");
while($row = $emp_total_query->fetch_assoc()) {
    $emp_total += floatval($row['total']);
}
// Fetch materials for the project with labor_other and material_price from materials table
$materials = [];
$mat_total = 0;
$mat_query = $con->query("SELECT pam.*, m.material_name, m.unit, m.material_price, m.labor_other FROM project_add_materials pam LEFT JOIN materials m ON pam.material_id = m.id WHERE pam.project_id = '$project_id'");
while($row = $mat_query->fetch_assoc()) {
    $materials[] = $row;
    $mat_total += floatval($row['total'] ?? 0);
}
// Fetch equipment
$equip_total = 0;
$equipments = $con->query("SELECT pae.*, e.equipment_name, e.equipment_price AS price, e.depreciation, pae.status, e.category, pae.total FROM project_add_equipment pae LEFT JOIN equipment e ON pae.equipment_id = e.id WHERE pae.project_id = '$project_id'");
while($row = $equipments->fetch_assoc()) {
    if (strtolower($row['status']) !== 'pending') {
        $equip_total += floatval($row['total'] ?? 0);
    }
}

// Create a single instance of FPDF
$pdf = new FPDF();
$pdf->AddPage();

// Add logo and header
$pdf->Image('../uploads/logo.jpg', 10, 10, 190, 40); // 40mm tall header image
$pdf->SetY(55); // 10 (top) + 40 (image) + 5 (space)
// Header Section

$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'Project Details',0,1,'C');
$pdf->SetFont('Arial','',12);
$pdf->Cell(0,8,'Project Name: ' . $project['project'],0,1);
$pdf->Cell(0,8,'Location: ' . $project['location'],0,1);
$pdf->Cell(0,8,'Category: ' . $project['category'],0,1);
$pdf->Cell(0,8,'Deadline: ' . date('F d, Y', strtotime($project['deadline'])),0,1);
// Get foreman name from project_add_employee where position is 'Foreman'
$foreman_query = $con->query("SELECT CONCAT(e.first_name, ' ', e.last_name) as foreman_name 
    FROM project_add_employee pae 
    JOIN employees e ON pae.employee_id = e.employee_id 
    WHERE pae.project_id = '$project_id' AND pae.position = 'Foreman' 
    LIMIT 1");
$foreman = $foreman_query->num_rows > 0 ? $foreman_query->fetch_assoc()['foreman_name'] : 'Not assigned';
$pdf->Cell(0,8,'Foreman: ' . $foreman,0,1);
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
// Employees summary
$pdf->SetFont('Arial','B',13);
$pdf->Cell(0,8,'Project Employees',0,1);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(10,7,'#',1);
$pdf->Cell(45,7,'Name',1);
$pdf->Cell(30,7,'Position',1);
$pdf->Cell(25,7,'Daily Rate',1);
$pdf->Cell(20,7,'Days',1);
$pdf->Cell(30,7,'Total',1);
$pdf->Ln();
$pdf->SetFont('Arial','',10);
$i=1;
$employees = $con->query("SELECT pae.*, e.first_name, e.last_name FROM project_add_employee pae LEFT JOIN employees e ON pae.employee_id = e.employee_id WHERE pae.project_id = '$project_id'");
while($emp = $employees->fetch_assoc()) {
    $name = ($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '');
    $daily_rate = is_numeric($emp['daily_rate']) ? floatval($emp['daily_rate']) : 0;
    $total = isset($emp['total']) ? floatval($emp['total']) : ($daily_rate * $project_days);
    $pdf->Cell(10,7,$i++,1);
    $pdf->Cell(45,7,$name,1);
    $pdf->Cell(30,7,$emp['position'],1);
    $pdf->Cell(25,7,number_format($daily_rate,2),1);
    $pdf->Cell(20,7,$project_days,1);
    $pdf->Cell(30,7,number_format($total,2),1);
    $pdf->Ln();
}
$pdf->Cell(130,7,'Total',1);
$pdf->Cell(30,7,number_format($emp_total,2),1);
$pdf->Ln(10);
// Project Equipment Table
$pdf->SetFont('Arial','B',13);
$pdf->Cell(0,8,'Project Equipment',0,1);
$eq_col_widths = [10, 50, 20, 25, 25, 20, 40]; // sum = 190
$pdf->SetFont('Arial','B',10);
$pdf->Cell($eq_col_widths[0],7,'#',1);
$pdf->Cell($eq_col_widths[1],7,'Name',1);
$pdf->Cell($eq_col_widths[2],7,'Price',1);
$pdf->Cell($eq_col_widths[3],7,'Depreciation',1);
$pdf->Cell($eq_col_widths[4],7,'Category',1);
$pdf->Cell($eq_col_widths[5],7,'Days',1);
$pdf->Cell($eq_col_widths[6],7,'Total',1);
$pdf->Ln();
$pdf->SetFont('Arial','',10);
$i=1;
$equipments = $con->query("SELECT pae.*, e.equipment_name, e.equipment_price AS price, e.depreciation, pae.status, e.category, pae.total FROM project_add_equipment pae LEFT JOIN equipment e ON pae.equipment_id = e.id WHERE pae.project_id = '$project_id'");
while($eq = $equipments->fetch_assoc()) {
    if (strtolower($eq['status']) !== 'in use') continue;
    $price = ($eq['rental_fee'] ?? 0) > 0 ? $eq['rental_fee'] : ($eq['price'] ?? 0);
    $depr = $eq['depreciation'] ?? '';
    $cat = $eq['category'] ?? '';
    $days = $project_days;
    $total = isset($eq['total']) ? floatval($eq['total']) : ($price * $days);
    $pdf->Cell($eq_col_widths[0],7,$i++,1);
    $pdf->Cell($eq_col_widths[1],7,$eq['equipment_name'] ?? '',1);
    $pdf->Cell($eq_col_widths[2],7,number_format($price,2),1);
    $pdf->Cell($eq_col_widths[3],7,$depr,1);
    $pdf->Cell($eq_col_widths[4],7,$cat,1);
    $pdf->Cell($eq_col_widths[5],7,$days,1);
    $pdf->Cell($eq_col_widths[6],7,number_format($total,2),1);
    $pdf->Ln();
}
$pdf->Cell(array_sum(array_slice($eq_col_widths,0,6)),7,'Total',1);
$pdf->Cell($eq_col_widths[6],7,number_format($equip_total,2),1);
$pdf->Ln(10);
// Update Grand Total
$pdf->SetFont('Arial','B',14);
$pdf->Cell(0,10,'Grand Total: PHP '.number_format($emp_total + $mat_total + $equip_total,2),0,1,'R');

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

$pdf->Output('D', 'project_details_'.$project_id.'.pdf'); 