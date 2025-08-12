<?php
// Get project details
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$project = [];

if ($project_id) {
    // Get project basic info
    $project_query = "SELECT * FROM projects WHERE project_id = ? AND user_id = ?";
    $stmt = $con->prepare($project_query);
    $stmt->bind_param("ii", $project_id, $userid);
    $stmt->execute();
    $project_result = $stmt->get_result();

    if ($project_result && $project_result->num_rows > 0) {
        $project = $project_result->fetch_assoc();
    }
}
?>

<div class="step-content d-none" id="step7">
    <div class="alert alert-success d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-info-circle me-2"></i>
            This is the latest schedule of the project
        </div>
        <a href="project_actual.php?id=<?php echo $project_id; ?>" class="btn btn-success">
            <i class="fas fa-arrow-left me-1"></i> Back to Actual
        </a>
    </div>

    <div class="row">
        <!-- Project Timeline Row (Moved to top) -->
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0"><i class="fas fa-tasks me-2"></i>Project Timeline</h5>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="scheduleTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Task</th>
                                    <th>Description</th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th>Progress</th>
                                </tr>
                            </thead>
                            <tbody id="timelineTableBody">
                                <?php
                                $current_project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

                                // Pagination settings
                                $items_per_page = 7;
                                $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                                $offset = ($current_page - 1) * $items_per_page;

                                if ($current_project_id) {
                                    // Get total number of items for pagination
                                    $count_query = "SELECT COUNT(*) as total FROM project_timeline WHERE project_id = ?";
                                    $stmt = $con->prepare($count_query);
                                    $stmt->bind_param("i", $current_project_id);
                                    $stmt->execute();
                                    $count_result = $stmt->get_result();
                                    $total_items = $count_result->fetch_assoc()['total'];
                                    $total_pages = ceil($total_items / $items_per_page);

                                    // Fetch paginated timeline data
                                    $timeline_query = "SELECT * FROM project_timeline WHERE project_id = ? ORDER BY start_date ASC LIMIT ? OFFSET ?";
                                    $stmt = $con->prepare($timeline_query);
                                    $stmt->bind_param("iii", $current_project_id, $items_per_page, $offset);
                                    $stmt->execute();
                                    $timeline_result = $stmt->get_result();

                                    if ($timeline_result && $timeline_result->num_rows > 0) {
                                        while ($item = $timeline_result->fetch_assoc()) {
                                            $start_date = date('M d, Y', strtotime($item['start_date']));
                                            $end_date = date('M d, Y', strtotime($item['end_date']));
                                            $progress = (int)$item['progress'];
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['task_name']); ?></td>
                                                <td><?php echo !empty($item['description']) ? htmlspecialchars($item['description']) : '<span class="text-muted">No description</span>'; ?></td>
                                                <td><?php echo $start_date; ?></td>
                                                <td><?php echo $end_date; ?></td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $progress; ?>%" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
                                                            <?php echo $progress; ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php
                                        }
                                    } else {
                                        echo '<tr><td colspan="5" class="text-center py-3">No timeline items found.</td></tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="5" class="text-center py-3">Project not specified.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                        
                        <?php if (isset($total_pages) && $total_pages > 1): ?>
                        <div class="d-flex justify-content-center mt-3">
                            <nav aria-label="Timeline pagination">
                                <ul class="pagination pagination-sm mb-0">
                                    <?php if ($current_page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?project_id=<?php echo $current_project_id; ?>&page=1" aria-label="First">
                                                <i class="fas fa-angle-double-left"></i>
                                            </a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="?project_id=<?php echo $current_project_id; ?>&page=<?php echo $current_page - 1; ?>" aria-label="Previous">
                                                <i class="fas fa-angle-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php
                                    $start_page = max(1, $current_page - 2);
                                    $end_page = min($start_page + 4, $total_pages);

                                    // Adjust start page if we're near the end
                                    $start_page = max(1, min($start_page, $total_pages - 4));

                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                        <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?project_id=<?php echo $current_project_id; ?>&page=<?php echo $i; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($current_page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?project_id=<?php echo $current_project_id; ?>&page=<?php echo $current_page + 1; ?>" aria-label="Next">
                                                <i class="fas fa-angle-right"></i>
                                            </a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="?project_id=<?php echo $current_project_id; ?>&page=<?php echo $total_pages; ?>" aria-label="Last">
                                                <i class="fas fa-angle-double-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Gantt Chart Row (Moved below Project Timeline) -->
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0"><i class="fas fa-project-diagram me-2"></i>Project Gantt Chart</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle text-center" id="ganttTable" style="font-size: 0.85rem;">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 14%;">Task</th>
                                    <th style="width: 7.2%;">Jan</th>
                                    <th style="width: 7.2%;">Feb</th>
                                    <th style="width: 7.2%;">Mar</th>
                                    <th style="width: 7.2%;">Apr</th>
                                    <th style="width: 7.2%;">May</th>
                                    <th style="width: 7.2%;">Jun</th>
                                    <th style="width: 7.2%;">Jul</th>
                                    <th style="width: 7.2%;">Aug</th>
                                    <th style="width: 7.2%;">Sep</th>
                                    <th style="width: 7.2%;">Oct</th>
                                    <th style="width: 7.2%;">Nov</th>
                                    <th style="width: 7.2%;">Dec</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="13" class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2 mb-0">Loading Gantt chart...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mt-4">
        <button type="button" class="btn btn-secondary prev-step" data-prev="6"><i class="fas fa-arrow-left me-1"></i>Previous</button>
        <button type="button" class="btn btn-primary next-step" data-next="8">Next <i class="fas fa-arrow-right ms-1"></i></button>
    </div>
</div>

<?php
// Function to generate Gantt chart data
function generateGanttChartData($con, $userid) {
    $current_project_id = isset($_GET['project_id']) ? $_GET['project_id'] : (isset($_SESSION['current_project_id']) ? $_SESSION['current_project_id'] : null);

    if ($current_project_id) {
        $project_query = "SELECT project_id, project FROM projects WHERE project_id = ? AND user_id = ?";
        $stmt = $con->prepare($project_query);
        $stmt->bind_param("ii", $current_project_id, $userid);
        $stmt->execute();
        $project_result = $stmt->get_result();

        if ($project_result && $project_result->num_rows > 0) {
            $project = $project_result->fetch_assoc();

            $schedules_query = "SELECT * FROM project_timeline WHERE project_id = ? ORDER BY start_date ASC";
            $stmt = $con->prepare($schedules_query);
            $stmt->bind_param("i", $current_project_id);
            $stmt->execute();
            $schedules_result = $stmt->get_result();
            $schedules = [];

            if ($schedules_result) {
                while ($row = $schedules_result->fetch_assoc()) {
                    $schedules[] = $row;
                }
            }

            $currentYear = date('Y');

            if (!empty($schedules)) {
                echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    const ganttTable = document.getElementById("ganttTable");
                    if (ganttTable) {
                        const tbody = ganttTable.querySelector("tbody");
                        tbody.innerHTML = "";
                        const schedules = ' . json_encode($schedules) . ';
                        const currentYear = ' . $currentYear . ';

                        schedules.forEach(function(schedule) {
                            const row = document.createElement("tr");
                            const taskCell = document.createElement("td");
                            taskCell.className = "text-start fw-bold";
                            taskCell.textContent = schedule.task_name || "Task";
                            row.appendChild(taskCell);

                            const startDate = new Date(schedule.start_date);
                            const endDate = new Date(schedule.end_date);
                            const startIdx = startDate.getMonth();
                            const endIdx = endDate.getMonth();
                            const startYear = startDate.getFullYear();
                            const endYear = endDate.getFullYear();

                            const barStart = (startYear < currentYear) ? 0 : startIdx;
                            const barEnd = (endYear > currentYear) ? 11 : endIdx;

                            for (let i = 0; i < barStart; i++) {
                                const emptyCell = document.createElement("td");
                                row.appendChild(emptyCell);
                            }

                            const barCell = document.createElement("td");
                            barCell.colSpan = barEnd - barStart + 1;
                            barCell.style.cssText = "padding:0;vertical-align:middle;";

                            // Show start and end dates inside the green bar
                            const barDiv = document.createElement("div");
                            barDiv.style.cssText = "height:24px;background:#009d63;border-radius:4px;width:100%;display:flex;align-items:center;justify-content:center;position:relative;";
                            // Format: MMM dd, yyyy (e.g. Jan 01, 2025)
                            const formatDate = function(date) {
                                const d = new Date(date);
                                const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
                                return monthNames[d.getMonth()] + " " + ("0" + d.getDate()).slice(-2) + ", " + d.getFullYear();
                            };
                            const dateLabel = document.createElement("span");
                            dateLabel.style.cssText = "color:white;font-size:0.7em;font-weight:bold;text-shadow:0 1px 2px #0007;";
                            dateLabel.textContent = formatDate(schedule.start_date) + " - " + formatDate(schedule.end_date);
                            barDiv.appendChild(dateLabel);

                            barCell.appendChild(barDiv);
                            row.appendChild(barCell);

                            for (let i = barEnd + 1; i < 12; i++) {
                                const emptyCell = document.createElement("td");
                                row.appendChild(emptyCell);
                            }

                            tbody.appendChild(row);
                        });
                    }
                });
                </script>';
            } else {
                echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    const ganttTable = document.getElementById("ganttTable");
                    if (ganttTable) {
                        const tbody = ganttTable.querySelector("tbody");
                        tbody.innerHTML = "<tr><td colspan=\"13\" class=\"text-center py-4\"><p class=\"text-muted mb-0\">No schedules found for this project.</p></td></tr>";
                    }
                });
                </script>';
            }
        } else {
            echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                const ganttTable = document.getElementById("ganttTable");
                if (ganttTable) {
                    const tbody = ganttTable.querySelector("tbody");
                    tbody.innerHTML = "<tr><td colspan=\"13\" class=\"text-center py-4\"><p class=\"text-danger mb-0\">Project not found or access denied.</p></td></tr>";
                }
            });
            </script>';
        }
    } else {
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            const ganttTable = document.getElementById("ganttTable");
            if (ganttTable) {
                const tbody = ganttTable.querySelector("tbody");
                tbody.innerHTML = "<tr><td colspan=\"13\" class=\"text-center py-4\"><p class=\"text-info mb-0\">Please select a project to view the Gantt chart.</p></td></tr>";
            }
        });
        </script>';
    }
}

if (isset($con) && isset($userid)) {
    generateGanttChartData($con, $userid);
}
?>