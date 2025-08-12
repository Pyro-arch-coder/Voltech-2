<?php

// Check if user is logged in and has the right permissions
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    header("Location: ../login.php");
    exit();
}

// Get project ID from URL
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

// Verify project exists and user has access
$project_query = "SELECT * FROM projects WHERE project_id = ? AND user_id = ?";
$stmt = $con->prepare($project_query);
$stmt->bind_param("ii", $project_id, $userid);
$stmt->execute();
$project_result = $stmt->get_result();

if ($project_result->num_rows === 0) {
    echo "<script>alert('Project not found or access denied.'); window.location.href='projects.php';</script>";
    exit();
}

$project = $project_result->fetch_assoc();
?>

<div class="step-content d-none" id="step6">
<h4 class="mb-4">Step 6: Actual</h4>
<div class="container-fluid px-4 py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">Project Actual</h4>
                </div>
                <div class="card-body text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-tasks fa-4x text-primary mb-3"></i>
                        <h3>Ready to start actual project work?</h3>
                        <p class="text-muted">Click the button below to go to the actual project page where you can track and manage your project's progress.</p>
                    </div>
                    
                    <div class="d-flex justify-content-center gap-3 mt-4">
                        <a href="project_actual.php?id=<?php echo $project_id; ?>" class="btn btn-primary">
                            <i class="fas fa-play me-2"></i> Go to Actual Project
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-secondary prev-step" data-prev="5">Previous</button>
                                    <button type="button" class="btn btn-primary next-step" data-next="7">Next</button>
                                </div>

                                </div>

<style>
.card {
    border: none;
    border-radius: 10px;
    overflow: hidden;
}

.card-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(0,0,0,.125);
}

.btn {
    padding: 0.5rem 1.5rem;
    border-radius: 5px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-primary {
    padding: 0.6rem 2rem;
    font-size: 1.1rem;
}

.btn i {
    transition: transform 0.2s ease;
}

.btn:hover i {
    transform: translateX(3px);
}

.btn-outline-secondary:hover {
    background-color: #f8f9fa;
}
</style>

