<?php
session_start();
require_once('../fpdf.php');

// Database connection
include_once "../config.php";

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die('Unauthorized access.');
}

$project_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_GET['project_id']) ? intval($_GET['project_id']) : 0);
if (!$project_id) {
    die('Invalid project ID.');
}

$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
if (!$user_id) {
    die('User ID not found in session.');
}

// Fetch project details
$project = $con->query("SELECT * FROM projects WHERE project_id='$project_id'")->fetch_assoc();
if (!$project) die('Project not found.');

// Get project_days from database (manual entry, no auto-calculation)
$project_days = isset($project['project_days']) && is_numeric($project['project_days']) ? (int)$project['project_days'] : 0;

// Start transaction
$con->begin_transaction();

try {
    // SNAPSHOT: Persist current estimated materials into project_add_materials
    // Delete existing snapshot for this project to avoid duplicates
    $del_sql = "DELETE FROM project_add_materials WHERE project_id = '$project_id'";
    if (!$con->query($del_sql)) {
        throw new Exception("Failed to clear existing project_add_materials: " . $con->error);
    }

    // Insert fresh rows from current estimation
    $ins_sql = "INSERT INTO project_add_materials (project_id, material_id, material_name, unit, material_price, quantity, added_at)
                SELECT pem.project_id,
                       pem.material_id,
                       COALESCE(m.material_name, pem.material_name),
                       COALESCE(m.unit, pem.unit),
                       COALESCE(m.material_price, 0),
                       COALESCE(pem.quantity, 1),
                       NOW()
                FROM project_estimating_materials pem
                LEFT JOIN materials m ON pem.material_id = m.id
                WHERE pem.project_id = '$project_id'";
    if (!$con->query($ins_sql)) {
        throw new Exception("Failed to insert snapshot into project_add_materials: " . $con->error);
    }

    // Calculate total materials cost
    $materials = [];
    $mat_total = 0;
    $mat_query = $con->query("SELECT pem.*, m.material_name, m.unit, m.material_price, m.labor_other 
                            FROM project_estimating_materials pem 
                            LEFT JOIN materials m ON pem.material_id = m.id 
                            WHERE pem.project_id = '$project_id'");
    
    while($row = $mat_query->fetch_assoc()) {
        $materials[] = $row;
        $material_price = floatval($row['material_price'] ?? 0);
        $labor_other = floatval($row['labor_other'] ?? 0);
        $quantity = floatval($row['quantity'] ?? 0);
        $item_total = ($material_price + $labor_other) * $quantity;
        $mat_total += $item_total;
    }
    
    // Calculate total labor cost from project_estimation_employee
    $labor_total = 0;
    $labor_query = $con->query("SELECT COALESCE(SUM(total), 0) as total 
                               FROM project_estimation_employee 
                               WHERE project_id = '$project_id'");
    if ($labor_row = $labor_query->fetch_assoc()) {
        $labor_total = floatval($labor_row['total']);
    }
    
    // Overhead totals (exclude VAT) and fetch VAT directly from DB
    $overhead_ex_vat = 0;
    $overhead_ex_vat_query = $con->query("SELECT COALESCE(SUM(price), 0) as total FROM overhead_costs WHERE project_id = '$project_id' AND name <> 'VAT'");
    if ($overhead_ex_vat_row = $overhead_ex_vat_query->fetch_assoc()) {
        $overhead_ex_vat = floatval($overhead_ex_vat_row['total']);
    }

    $vat_amount = 0;
    $vat_fetch = $con->prepare("SELECT price FROM overhead_costs WHERE project_id = ? AND name = 'VAT' LIMIT 1");
    if (!$vat_fetch) { throw new Exception("Prepare failed: " . $con->error); }
    $vat_fetch->bind_param("i", $project_id);
    if (!$vat_fetch->execute()) { throw new Exception("Failed to fetch VAT: " . $con->error); }
    $vat_fetch->bind_result($vat_amount_db);
    if ($vat_fetch->fetch()) {
        $vat_amount = (float)$vat_amount_db;
    }
    $vat_fetch->close();

    $base_total = $mat_total + $overhead_ex_vat;

    // After normalizing VAT, snapshot overhead costs to overhead_cost_actual
    $overhead_query = $con->query("SELECT * FROM overhead_costs WHERE project_id = '$project_id'");
    if ($overhead_query->num_rows > 0) {
        $delete_old = $con->query("DELETE FROM overhead_cost_actual WHERE project_id = '$project_id'");
        if (!$delete_old) {
            throw new Exception("Failed to clear existing overhead_cost_actual: " . $con->error);
        }

        $stmt = $con->prepare("INSERT INTO overhead_cost_actual (project_id, name, price, created_at) VALUES (?, ?, ?, NOW())");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $con->error);
        }

        while ($row = $overhead_query->fetch_assoc()) {
            $stmt->bind_param("isd", $row['project_id'], $row['name'], $row['price']);
            if (!$stmt->execute()) {
                throw new Exception("Failed to save overhead cost: " . $stmt->error);
            }
        }
        $stmt->close();
    }

    // Final totals
    $total_estimation = $base_total + $vat_amount;

    // Update total_estimation_cost in projects table
    $update_query = $con->prepare("UPDATE projects SET total_estimation_cost = ? WHERE project_id = ?");
    if (!$update_query) {
        throw new Exception("Prepare failed: " . $con->error);
    }
    $update_query->bind_param("di", $total_estimation, $project_id);
    if (!$update_query->execute()) {
        throw new Exception("Failed to update project total estimation cost: " . $con->error);
    }
    
    // Move employees from project_estimation_employee to project_add_employee
    // Include project_days and total from database (manual values, not calculated)
    $move_employees = $con->prepare("INSERT INTO project_add_employee 
                                    (project_id, employee_id, position, daily_rate, project_days, total, status, added_at)
                                    SELECT project_id, employee_id, position, daily_rate, 
                                           COALESCE(project_days, 0) as project_days, 
                                           total, 'Working', NOW()
                                    FROM project_estimation_employee
                                    WHERE project_id = ?");
    if (!$move_employees) {
        throw new Exception("Prepare failed: " . $con->error);
    }
    $move_employees->bind_param("i", $project_id);
    if (!$move_employees->execute()) {
        throw new Exception("Failed to move employees to project team: " . $con->error);
    }
    
    // Commit the transaction
    $con->commit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    $con->rollback();
    die('Error: ' . $e->getMessage());
}

// Fetch employees for PDF (project estimation employees)
$employees = [];
$emp_total = 0;
$emp_sql = "SELECT pee.*, e.first_name, e.last_name, e.company_type
            FROM project_estimation_employee pee
            JOIN employees e ON pee.employee_id = e.employee_id
            WHERE pee.project_id = '$project_id'
            ORDER BY e.last_name, e.first_name";
$emp_res = $con->query($emp_sql);
if ($emp_res) {
    while ($row = $emp_res->fetch_assoc()) {
        $employees[] = $row;
        $emp_total += isset($row['total']) ? (float)$row['total'] : 0;
    }
}

// Fetch overhead costs for PDF (including VAT row)
$overheads = [];
$overhead_total_display = 0;
$vat_amount_pdf = 0;
$ov_res = $con->query("SELECT name, price FROM overhead_costs WHERE project_id = '$project_id' ORDER BY id ASC");
if ($ov_res) {
    while ($row = $ov_res->fetch_assoc()) {
        $price = isset($row['price']) ? (float)$row['price'] : 0;
        if (strcasecmp($row['name'], 'VAT') === 0) {
            $vat_amount_pdf = $price;
            continue; // Exclude VAT from overhead list/total
        }
        $overheads[] = $row;
        $overhead_total_display += $price;
    }
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

// Employees (Project Team) section
if (!empty($employees)) {
    $pdf->SetFont('Arial','B',13);
    $pdf->Cell(0,8,'Project Team (Labor Costs)',0,1);
    // Column widths sum to 190mm (page width 210 - margins 10 each side)
    $emp_col_widths = [10, 50, 35, 30, 25, 20, 20];
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell($emp_col_widths[0],7,'#',1);
    $pdf->Cell($emp_col_widths[1],7,'Name',1);
    $pdf->Cell($emp_col_widths[2],7,'Position',1);
    $pdf->Cell($emp_col_widths[3],7,'Type',1);
    $pdf->Cell($emp_col_widths[4],7,'Daily Rate',1);
    $pdf->Cell($emp_col_widths[5],7,'Days',1);
    $pdf->Cell($emp_col_widths[6],7,'Total',1);
    $pdf->Ln();
    $pdf->SetFont('Arial','',10);
    $idx = 1;
    foreach ($employees as $emp) {
        $name = trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));
        $position = $emp['position'] ?? '';
        $type = $emp['company_type'] ?? '';
        $daily_rate = isset($emp['daily_rate']) && is_numeric($emp['daily_rate']) ? (float)$emp['daily_rate'] : 0;
        $days = isset($emp['project_days']) && is_numeric($emp['project_days']) ? (float)$emp['project_days'] : 0;
        // Use total from database (manual value, not calculated)
        $row_total = isset($emp['total']) && is_numeric($emp['total']) ? (float)$emp['total'] : 0;

        $pdf->Cell($emp_col_widths[0],7,$idx++,1);
        $pdf->Cell($emp_col_widths[1],7,$name,1);
        $pdf->Cell($emp_col_widths[2],7,$position,1);
        $pdf->Cell($emp_col_widths[3],7,$type,1);
        $pdf->Cell($emp_col_widths[4],7,number_format($daily_rate,2),1);
        $pdf->Cell($emp_col_widths[5],7,$days,1);
        $pdf->Cell($emp_col_widths[6],7,number_format($row_total,2),1);
        $pdf->Ln();
    }
    $pdf->Cell(array_sum(array_slice($emp_col_widths,0,6)),7,'Total',1);
    $pdf->Cell($emp_col_widths[6],7,number_format($emp_total,2),1);
    $pdf->Ln(12);
}

// Overhead Costs section
if (!empty($overheads)) {
    $pdf->SetFont('Arial','B',13);
    $pdf->Cell(0,8,'Overhead Costs',0,1);
    $ov_col_widths = [10, 130, 50]; // sum = 190
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell($ov_col_widths[0],7,'#',1);
    $pdf->Cell($ov_col_widths[1],7,'Name',1);
    $pdf->Cell($ov_col_widths[2],7,'Price',1);
    $pdf->Ln();
    $pdf->SetFont('Arial','',10);
    $idx = 1;
    foreach ($overheads as $ov) {
        $name = $ov['name'] ?? '';
        $price = isset($ov['price']) && is_numeric($ov['price']) ? (float)$ov['price'] : 0;
        $pdf->Cell($ov_col_widths[0],7,$idx++,1);
        $pdf->Cell($ov_col_widths[1],7,$name,1);
        $pdf->Cell($ov_col_widths[2],7,number_format($price,2),1);
        $pdf->Ln();
    }
    $pdf->Cell($ov_col_widths[0] + $ov_col_widths[1],7,'Total Overhead Costs',1);
    $pdf->Cell($ov_col_widths[2],7,number_format($overhead_total_display,2),1);
    $pdf->Ln(12);
}

// Cost Summary (Grand total excludes labor per export requirements)
$pdf->SetFont('Arial','B',13);
$pdf->Cell(0,8,'Cost Summary',0,1);
$grand_total_no_labor = $mat_total + $overhead_total_display + ($vat_amount_pdf ?: $vat_amount);
$summary_labels = [
    'Materials Total' => $mat_total,
    'Labor Total' => $labor_total,
    'Overhead Total (ex VAT)' => $overhead_total_display,
    'VAT (12%)' => ($vat_amount_pdf ?: $vat_amount),
    'Grand Total' => $grand_total_no_labor
];
$pdf->SetFont('Arial','',11);
foreach ($summary_labels as $label => $amount) {
    $pdf->Cell(100,8,$label,0,0,'L');
    $pdf->Cell(0,8,'P ' . number_format($amount,2),0,1,'R');
}
$pdf->Ln(8);

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

// Calculate total estimated cost (grand total with labor)
$total_estimated_cost = $mat_total + $labor_total + $overhead_total_display + ($vat_amount_pdf ?: $vat_amount);

// Create uploads/cost_estimates directory if it doesn't exist
$upload_dir = __DIR__ . '/uploads/cost_estimates/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename
$file_name = 'project_materials_estimation_' . $project_id . '_' . time() . '.pdf';
$file_path = $upload_dir . $file_name;

// Prepare relative path for database
$relative_file_path = 'uploads/cost_estimates/' . $file_name;

// Save PDF to file first
$pdf->Output('F', $file_path);

// Insert record into cost_estimate_files table
try {
    $insert_stmt = $con->prepare("
        INSERT INTO cost_estimate_files 
        (project_id, user_id, file_name, file_path, upload_date, status, cost_type, estimated_cost) 
        VALUES (?, ?, ?, ?, NOW(), 'pending', 'Materials Estimation', ?)
    ");
    
    if (!$insert_stmt) {
        throw new Exception('Failed to prepare insert statement: ' . $con->error);
    }
    
    $insert_stmt->bind_param('iissd', $project_id, $user_id, $file_name, $relative_file_path, $total_estimated_cost);
    
    if (!$insert_stmt->execute()) {
        throw new Exception('Failed to insert cost estimate file record: ' . $insert_stmt->error);
    }
    
    $insert_stmt->close();
} catch (Exception $e) {
    // Log error but don't stop PDF output
    error_log('Error saving cost estimate file record: ' . $e->getMessage());
}

// Read the saved file and output to browser for download
if (file_exists($file_path)) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="project_materials_estimation'.$project_id.'.pdf"');
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path);
    exit;
} else {
    die('Error: PDF file could not be saved.');
} 