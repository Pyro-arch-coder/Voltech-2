<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 3 || !isset($_GET['id'])) {
    // Return a 403 Forbidden error if the user is not authorized or if the ID is not set
    header('HTTP/1.0 403 Forbidden');
    exit('Access Denied');
}
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}
$userid = $_SESSION['user_id'];
$project_id = intval($_GET['id']);

// Fetch project details
$project_query = mysqli_query($con, "SELECT * FROM projects WHERE project_id='$project_id' AND user_id='$userid'");

if (mysqli_num_rows($project_query) == 0) {
    die('<div class="alert alert-danger">Project not found or you do not have permission to view it.</div>');
}

$project = mysqli_fetch_assoc($project_query);

?>

<div class="row">
    <div class="col-md-12">
        <h4><?php echo htmlspecialchars($project['project']); ?></h4>
        <p class="text-muted"><?php echo htmlspecialchars($project['location']); ?></p>
        <hr>
    </div>
    <div class="col-md-6">
        
        <p><strong>Budget:</strong> <span class="text-success fw-bold">₱<?php echo number_format($project['budget'], 2); ?></span></p>
        <p><strong>Category:</strong> <?php echo htmlspecialchars($project['category']); ?></p>
    </div>
    <div class="col-md-6">
        <p><strong>Start Date:</strong> <?php echo date("F d, Y", strtotime($project['start_date'])); ?></p>
        <p><strong>Deadline:</strong> <span class="text-danger"><?php echo date("F d, Y", strtotime($project['deadline'])); ?></span></p>
        <p><strong>Foreman:</strong> <?php echo htmlspecialchars($project['foreman'] ?? 'N/A'); ?></p>
    </div>
</div>

<hr>

<div class="d-flex justify-content-end gap-2 mt-3">
    <a href="project_estimation.php?id=<?php echo $project_id; ?>" class="btn btn-warning">
        <i class="fas fa-calculator me-1"></i> Plan for Estimation
    </a>
    <a href="project_ongoing.php?id=<?php echo $project_id; ?>" class="btn btn-success">
        <i class="fas fa-hammer me-1"></i> Plan for Actual Use
    </a>
</div> 