<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 3) {
    die('Unauthorized');
}
require_once('../fpdf.php');
include_once "../config.php";
if ($con->connect_error) die("Connection failed: " . $con->connect_error);
$userid = $_SESSION['user_id'];
$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get project details
$project = [];
$query = "SELECT * FROM projects WHERE project_id = $project_id AND user_id = $userid";
$result = mysqli_query($con, $query);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $project = $row;
} else {
    die('Project not found or unauthorized');
}

// PDF output
$pdf = new FPDF();
$pdf->AddPage();

// Set default font to Arial (standard FPDF font)
$pdf->SetFont('Arial', '', 12);

// Logo and header
$pdf->Image('../uploads/logo.jpg', 10, 10, 190, 40);
$pdf->SetY(55);

// Title
$pdf->SetFont('Arial', 'B', 14);
$title = 'CERTIFICATE OF COMPLETION AND ACCEPTANCE';
$pdf->Cell(0, 10, $title, 0, 1, 'C', false, '');
// Add underline
$pdf->SetLineWidth(0.5);
$pdf->Line(35, $pdf->GetY() - 2, 175, $pdf->GetY() - 2);
$pdf->Ln(8);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(30, 8, 'PROJECT:', 0, 0, 'L');
$pdf->Cell(0, 8, htmlspecialchars($project['project']), 0, 1, 'L');

// Project details with smaller font
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(30, 8, 'LOCATION:', 0, 0, 'L');
$pdf->Cell(0, 8, htmlspecialchars($project['location']), 0, 1, 'L');

$pdf->Cell(30, 8, 'DATE:', 0, 0, 'L');
$pdf->Cell(0, 8, date('F j, Y'), 0, 1, 'L');
$pdf->Ln(20);

// First line with indent
$pdf->SetFont('Arial', '', 11);
$pdf->SetX(40); // Position for first line
$pdf->Cell(0, 6, 'This is to certify that VOLTECH ELECTRICAL CONSTRUCTION has completed', 0, 1, 'L');
$pdf->SetX(30); // Reset position for next line
$pdf->MultiCell(150, 6, 'the above project. Restoration Works as of June 30, 2024 in accordance with Specification, terms and conditions of the contract. The Contractor has a warranty of One (1) year from the date of completion.', 0, 'J');
$pdf->Ln(15);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(55, 10, 'Turn Over by:', 0, 1, 'L');
$pdf->Ln(15);

// Signature section aligned to left
$pdf->SetX(28); // Set left margin for signature section

// Signature line
$pdf->Cell(0, 5, '________________________', 0, 1, 'L');

// Name under signature
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetX(30); // Align name to same left margin
$pdf->Cell(0, 5, 'ENGR. ROMEO MATIAS', 0, 1, 'L');

// Title under name
$pdf->SetFont('Arial', 'I', 10);
$pdf->SetX(35); // Align title to same left margin
$pdf->Cell(0, 5, 'General Manager', 0, 1, 'L');


$pdf->SetFont('Arial', '', 10);
$pdf->SetX(20);
$pdf->Cell(0, 5, 'Approved and Cleared By Clients Representatives', 0, 1, 'L');
$pdf->Ln(15);

// Get current Y position for signature sections
$signatureY = $pdf->GetY();

// APEC Representative Section (Left)
$pdf->SetXY(30, $signatureY);
$pdf->Cell(70, 5, '________________________', 0, 0, 'L');
$pdf->SetXY(30, $signatureY + 8);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(70, 5, 'ENGR. REPRESENTATIVE', 0, 0, 'L');

// Client Representative Section (Right)
$pdf->SetXY(110, $signatureY);
$pdf->Cell(70, 5, '________________________', 0, 0, 'L');
$pdf->SetXY(110, $signatureY + 8);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(70, 5, 'CLIENT REPRESENTATIVE', 0, 0, 'L');


// Add space after signature sections
$pdf->SetY($signatureY + 20);

$pdf->Output('D', 'project_completion_' . $project_id . '.pdf');