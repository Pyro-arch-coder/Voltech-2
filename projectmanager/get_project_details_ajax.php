<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 3 || !isset($_GET['id'])) {
    // Return a 403 Forbidden error if the user is not authorized or if the ID is not set
    header('HTTP/1.0 403 Forbidden');
    exit('Access Denied');
}
include_once "../config.php";
$userid = $_SESSION['user_id'];
$project_id = intval($_GET['id']);

// Fetch project details
$project_query = mysqli_query($con, "SELECT * FROM projects WHERE project_id='$project_id' AND user_id='$userid'");

if (mysqli_num_rows($project_query) == 0) {
    die('<div class="alert alert-danger">Project not found or you do not have permission to view it.</div>');
}

$project = mysqli_fetch_assoc($project_query);

?>

<div class="project-header mb-4">
    <h3 class="text-primary fw-bold mb-2"><?php echo htmlspecialchars($project['project']); ?></h3>
    <p class="text-muted mb-0">
        <i class="fas fa-map-marker-alt me-1 text-primary"></i> 
        <?php echo htmlspecialchars($project['location']); ?>
    </p>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <h5 class="card-title text-primary mb-3">
                    <i class="fas fa-info-circle me-2"></i>Project Information
                </h5>
                <div class="project-details">
                    <div class="detail-item mb-3">
                        <div class="d-flex align-items-center">
                            <div class="icon-circle bg-primary bg-opacity-10 text-primary p-2 rounded-circle me-3">
                                <i class="fas fa-tag"></i>
                            </div>
                            <div>
                                <span class="text-muted small">Category</span>
                                <p class="mb-0 fw-medium"><?php echo htmlspecialchars($project['category']); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="detail-item mb-3">
                        <div class="d-flex align-items-center">
                            <div class="icon-circle bg-success bg-opacity-10 text-success p-2 rounded-circle me-3">
                                <i class="fas fa-wallet"></i>
                            </div>
                            <div>
                                <span class="text-muted small">Budget</span>
                                <p class="mb-0 fw-bold text-success">₱<?php echo number_format($project['budget'], 2); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <h5 class="card-title text-primary mb-3">
                    <i class="fas fa-calendar-alt me-2"></i>Project Timeline
                </h5>
                <div class="project-details">
                    <div class="detail-item mb-3">
                        <div class="d-flex align-items-center">
                            <div class="icon-circle bg-info bg-opacity-10 text-info p-2 rounded-circle me-3">
                                <i class="fas fa-play"></i>
                            </div>
                            <div>
                                <span class="text-muted small">Start Date</span>
                                <p class="mb-0 fw-medium"><?php echo date("F d, Y", strtotime($project['start_date'])); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="detail-item mb-3">
                        <div class="d-flex align-items-center">
                            <div class="icon-circle bg-danger bg-opacity-10 text-danger p-2 rounded-circle me-3">
                                <i class="fas fa-flag-checkered"></i>
                            </div>
                            <div>
                                <span class="text-muted small">Deadline</span>
                                <p class="mb-0 fw-bold text-danger"><?php echo date("F d, Y", strtotime($project['deadline'])); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="d-flex align-items-center">
                            <div class="icon-circle bg-warning bg-opacity-10 text-warning p-2 rounded-circle me-3">
                                <i class="fas fa-hard-hat"></i>
                            </div>
                            <div>
                                <span class="text-muted small">Foreman</span>
                                <p class="mb-0 fw-medium"><?php echo htmlspecialchars($project['foreman'] ?? 'Not Assigned'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="project_estimation.php?id=<?php echo $project_id; ?>" class="btn btn-warning px-4">
        <i class="fas fa-calculator me-2"></i> Plan for Estimation
    </a>
    <a href="project_actual.php?id=<?php echo $project_id; ?>" class="btn btn-success px-4">
        <i class="fas fa-hammer me-2"></i> Plan for Actual Use
    </a>
</div>

<style>
.project-header {
    border-bottom: 1px solid #eee;
    padding-bottom: 1rem;
    margin-bottom: 1.5rem;
}
.icon-circle {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.detail-item {
    border-left: 3px solid #dee2e6;
    padding-left: 1rem;
    margin-left: 0.5rem;
}
.card {
    transition: transform 0.2s ease-in-out;
}
.card:hover {
    transform: translateY(-3px);
}
</style>