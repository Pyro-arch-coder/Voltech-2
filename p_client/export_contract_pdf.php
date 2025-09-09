<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 6) {
    die('Unauthorized');
}
require_once('../fpdf.php');
include_once "../config.php";
if ($con->connect_error) die("Connection failed: " . $con->connect_error);

// Get project and client details
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$query = $con->prepare("SELECT p.*, u.firstname, u.lastname, p.start_date, p.deadline
                      FROM projects p 
                      LEFT JOIN users u ON p.user_id = u.id 
                      WHERE p.project_id = ?");
$query->bind_param("i", $project_id);
$query->execute();
$project = $query->get_result()->fetch_assoc();

if (!$project) die('Project not found');

// Create new PDF document
$pdf = new FPDF();
$pdf->AddPage();
$pdf->Image('../uploads/logo.jpg', 10, 10, 190, 40); // 40mm tall header image
$pdf->SetY(55); // 10 (top) + 40 (image) + 5 (space)
// Header Section

// Document title
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'CONSTRUCTION AGREEMENT', 0, 1, 'C');
$pdf->Ln(10);

// Get today's date in the desired format
$today = new DateTime();
$formatted_date = $today->format('F j, Y');

// Agreement header
$pdf->SetFont('Arial', '', 12);
$pdf->MultiCell(0, 8, 'This Construction Agreement ("Agreement") is made and entered into this ' . $formatted_date . ', by and between:', 0, 'L');
$pdf->Ln(8);

// Owner/Client Section
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'OWNER: ROMEO MATIAS', 0, 1);
$pdf->SetFont('Arial', '', 12);

$pdf->Ln(5);

// Contractor Section
$pdf->MultiCell(0, 8, 'and', 0, 'C');
$pdf->Ln(5);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'CONTRACTOR: VOLTECH ELECTRICAL CONSTRUCTION', 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, 'Address: 2nd Floor, 3rd Building, 11th Avenue, East Tapinac, Olongapo City, Zambales', 0, 1);
$pdf->Cell(0, 8, 'Contact: 0917-123-4567', 0, 1);
$pdf->Ln(10);

// 1. PROJECT DESCRIPTION
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, '1. PROJECT DESCRIPTION', 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->MultiCell(0, 8, 'The Contractor agrees to furnish all labor, materials, equipment, and supervision necessary to complete the construction project described as:', 0, 'L');
$pdf->SetFont('Arial', 'B', 12);
$pdf->MultiCell(0, 10, strtoupper($project['project']), 0, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Ln(5);

// 2. CONTRACT PRICE / BUDGET APPROVAL
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, '2. CONTRACT PRICE / BUDGET APPROVAL', 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->MultiCell(0, 8, 'The Owner agrees to pay the Contractor the total sum of ', 0, 'L');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, number_format($project['budget'], 2) . ' PESOS', 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->MultiCell(0, 8, 'for the completion of the project. This amount has been reviewed and approved by both parties prior to the commencement of construction.', 0, 'L');
$pdf->Ln(5);

// 3. PROJECT SCHEDULE
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, '3. PROJECT SCHEDULE', 0, 1);
$pdf->SetFont('Arial', '', 12);
$start_date = new DateTime($project['start_date']);
$deadline = new DateTime($project['deadline']);
$formatted_start = $start_date->format('F j, Y');
$formatted_deadline = $deadline->format('F j, Y');

$pdf->MultiCell(0, 8, 'The project shall commence on ' . $formatted_start . ' ("Start Date") and shall be completed on ' . $formatted_deadline . ' ("Completion Date"), unless extended by mutual written agreement of the parties. Any delays caused by force majeure, inclement weather, government restrictions, or other circumstances beyond the control of the Contractor shall be grounds for reasonable extension of time.', 0, 'L');
$pdf->Ln(5);

// 4. TERMS OF PAYMENT
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, '4. TERMS OF PAYMENT', 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->MultiCell(0, 8, 'The Owner shall pay the Contractor in accordance with the following terms:', 0, 'L');

// Downpayment amount
$downpayment = $project['initial_budget'];
$pdf->Cell(0, 8, 'a. Downpayment: ' . number_format($downpayment, 2) . ' PESOS upon execution of this Agreement.', 0, 1);
$pdf->Cell(0, 8, 'b. Progress Payments: Based on agreed milestones or percentage completion of the project.', 0, 1);
$pdf->Ln(5);

// 5. GENERAL PROVISIONS
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, '5. GENERAL PROVISIONS', 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, 'a. Any modifications, variations, or additional work shall not be undertaken without a written change order signed by both parties.', 0, 1);
$pdf->Cell(0, 8, 'b. The Contractor shall comply with all applicable laws, ordinances, and safety regulations.', 0, 1);
$pdf->Cell(0, 8, 'c. The Owner shall have the right to inspect the work at any reasonable time during construction.', 0, 1);
$pdf->MultiCell(0, 8, 'd. In case of dispute, both parties shall endeavor to resolve the matter amicably. Failing such resolution, the dispute shall be settled through arbitration in accordance with the laws of the Republic of the Philippines.', 0, 'L');
$pdf->Ln(10);

// 6. SIGNATURES
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, '6. SIGNATURES', 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->MultiCell(0, 8, 'IN WITNESS WHEREOF, the parties hereto have hereunto set their hands on the date and year first above written.', 0, 'C');
$pdf->Ln(15);

// Signatures table
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(95, 8, '_____________________________', 0, 0, 'C');
$pdf->Cell(95, 8, '_____________________________', 0, 1, 'C');
$pdf->Cell(95, 5, 'PROJECT MANAGER\'S Signature', 0, 0, 'C');
$pdf->Cell(95, 5, 'CLIENT\'S Signature', 0, 1, 'C');
$pdf->Ln(10);

$pdf->Cell(95, 8, '_____________________________', 0, 0, 'C');
$pdf->Cell(95, 8, '_____________________________', 0, 1, 'C');
$pdf->Cell(95, 5, 'Date', 0, 0, 'C');
$pdf->Cell(95, 5, 'Date', 0, 1, 'C');

// Output the PDF with project name in filename
$filename = 'Construction_Agreement_' . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $project['project']) . '.pdf';
$pdf->Output('D', $filename);