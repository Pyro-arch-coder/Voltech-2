<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 3) {
    header("Location: ../login.php");
    exit();
}
require_once '../config.php';
$userid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Get previous projects data grouped by category using budget
$query = "SELECT 
            p.project, 
            p.size, 
            p.category,
            p.budget as total_cost
          FROM projects p 
          WHERE p.user_id = '$userid' 
          AND p.size IS NOT NULL 
          AND p.size > 0 
          AND p.budget > 0
          ORDER BY p.category, p.project_id DESC";
$result = mysqli_query($con, $query);
$projects = [];
while ($row = mysqli_fetch_assoc($result)) {
    $projects[] = $row;
}

// Calculate forecast if form is submitted
$forecast_result = null;
$error_message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_size']) && isset($_POST['category'])) {
    $new_size = floatval($_POST['new_size']);
    $selected_category = $_POST['category'];
    if ($new_size <= 0) {
        $error_message = "Please enter a valid size greater than 0";
    } else {
        // Calculate average cost per square meter from previous projects of the same category
        $total_cost_per_sqm = 0;
        $count = 0;
        foreach ($projects as $project) {
            if ($project['size'] > 0 && $project['total_cost'] > 0 && $project['category'] === $selected_category) {
                $total_cost_per_sqm += ($project['total_cost'] / $project['size']);
                $count++;
            }
        }
        if ($count > 0) {
            $avg_cost_per_sqm = $total_cost_per_sqm / $count;
            $forecast_result = $avg_cost_per_sqm * $new_size;
        } else {
            $error_message = "No historical data available for the selected category";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analogous Forecasting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-chart-line me-2"></i>Analogous Cost Forecasting</h4>
                    </div>
                    <div class="card-body">
                        <!-- Previous Projects Table -->
                        <h5 class="mb-3">Previous Projects Reference Data</h5>
                        <div class="table-responsive mb-4">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project Name</th>
                                        <th>Category</th>
                                        <th>Size (m²)</th>
                                        <th>Budget (₱)</th>
                                        <th>Cost per m²</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $project): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($project['project']); ?></td>
                                        <td><?php echo htmlspecialchars($project['category']); ?></td>
                                        <td><?php echo number_format($project['size'], 2); ?></td>
                                        <td><?php echo number_format($project['total_cost'], 2); ?></td>
                                        <td><?php echo $project['size'] > 0 ? number_format($project['total_cost'] / $project['size'], 2) : 'N/A'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Forecasting Form -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Calculate Budget Forecast</h5>
                                        <form method="POST" class="mt-3">
                                            <div class="mb-3">
                                                <label for="category" class="form-label">Project Category</label>
                                                <select class="form-control" id="category" name="category" required>
                                                    <option value="" disabled selected>Select Category</option>
                                                    <option value="House">House</option>
                                                    <option value="Building">Building</option>
                                                    <option value="Renovation">Renovation</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="new_size" class="form-label">New Project Size (m²)</label>
                                                <input type="number" step="0.01" class="form-control" id="new_size" name="new_size" required>
                                            </div>
                                            <button type="submit" class="btn btn-primary">Calculate Forecast</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <?php if ($error_message): ?>
                                <div class="alert alert-danger"><?php echo $error_message; ?></div>
                                <?php endif; ?>
                                
                                <?php if ($forecast_result): ?>
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h5 class="card-title text-success">Forecasted Budget</h5>
                                        <div class="mt-3">
                                            <p class="mb-2">Based on previous projects' average cost per m²:</p>
                                            <h3 class="text-primary">₱ <?php echo number_format($forecast_result, 2); ?></h3>
                                            <small class="text-muted">This forecast is calculated using the average cost per square meter from similar projects in the same category.</small>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="../projectmanager/projects.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Projects
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
