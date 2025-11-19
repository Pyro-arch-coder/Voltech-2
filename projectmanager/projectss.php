<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 3) {
    header("Location: ../login.php");
    exit();
}
include_once "../config.php";
$userid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$user_email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
$user_firstname = isset($_SESSION['firstname']) ? $_SESSION['firstname'] : '';
$user_lastname = isset($_SESSION['lastname']) ? $_SESSION['lastname'] : '';
$user_name = trim($user_firstname . ' ' . $user_lastname);
$current_page = basename($_SERVER['PHP_SELF']);

// Default divisions per category
$default_divisions = [
    'Building' => ['Floor', 'Layout', 'Roof', 'Windows', 'Sample'],
    'Renovation' => ['Demolition', 'Structural Repairs', 'Painting', 'Finishing']
];
// Handle AJAX password change (like pm_profile.php) - MUST BE BEFORE ANY OUTPUT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $response = ['success' => false, 'message' => ''];
    $current = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    if (!$current || !$new || !$confirm) {
        $response['message'] = 'All fields are required.';
    } elseif ($new !== $confirm) {
        $response['message'] = 'New passwords do not match.';
    } elseif (strlen($new) < 6) {
        $response['message'] = 'New password must be at least 6 characters.';
    } else {
        $user_row = $con->query("SELECT password FROM users WHERE id = '$userid'");
        if ($user_row && $user_row->num_rows > 0) {
            $user_data = $user_row->fetch_assoc();
            if (password_verify($current, $user_data['password'])) {
                $hashed = password_hash($new, PASSWORD_DEFAULT);
                $update = $con->query("UPDATE users SET password = '$hashed' WHERE id = '$userid'");
                if ($update) {
                    $response['success'] = true;
                    $response['message'] = 'Password changed successfully!';
                } else {
                    $response['message'] = 'Failed to update password.';
                }
            } else {
                $response['message'] = 'Current password is incorrect.';
            }
        } else {
            $response['message'] = 'User not found.';
        }
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}


if (isset($_GET['archive'])) {
    $archive_id = intval($_GET['archive']);
    mysqli_query($con, "UPDATE projects SET archived=1 WHERE project_id='$archive_id' AND user_id='$userid'");
    header("Location: projects.php?archived=1");
    exit();
}



if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_project'])) {
    // Get form data
    $project = mysqli_real_escape_string($con, $_POST['project']);
    $region = isset($_POST['Region']) ? mysqli_real_escape_string($con, $_POST['Region']) : '';
    $province = isset($_POST['Province']) ? mysqli_real_escape_string($con, $_POST['Province']) : '';
    $municipality = isset($_POST['Municipality']) ? mysqli_real_escape_string($con, $_POST['Municipality']) : '';
    $barangay = isset($_POST['Baranggay']) ? mysqli_real_escape_string($con, $_POST['Baranggay']) : '';
    $location = trim($region . ' ' . $province . ' ' . $municipality . ' ' . $barangay);
    $budget = floatval($_POST['budget']);
    $start_date = $_POST['start_date'];
    $deadline = $_POST['deadline'];
    $foreman = mysqli_real_escape_string($con, $_POST['foreman']);
    $category = mysqli_real_escape_string($con, $_POST['category']);
    $size = isset($_POST['size']) ? floatval($_POST['size']) : null;
    $user_id = $userid;
    
    // Convert dates to DateTime objects for comparison
    $start_date_obj = new DateTime($start_date);
    $deadline_obj = new DateTime($deadline);
    
    // Validation flags
    $is_valid = true;
    $error_message = '';
    
    // Check if deadline is before start date
    if ($deadline_obj < $start_date_obj) {
        $is_valid = false;
        $error_message = 'Error: Deadline cannot be before start date.';
    }
    
    // Check for existing projects at the same location with date conflicts
    if ($is_valid) {
        $location_escaped = mysqli_real_escape_string($con, $location);
        $query = "SELECT * FROM projects 
                 WHERE location = '$location_escaped' 
                 AND user_id = '$user_id' 
                 AND archived = 0";
        
        $result = mysqli_query($con, $query);
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $existing_start = new DateTime($row['start_date']);
                $existing_end = new DateTime($row['deadline']);
                
                // Check for same start date
                if ($start_date == $row['start_date']) {
                    $is_valid = false;
                    $error_message = 'Error: There is already a project at this location starting on ' . $start_date . '.';
                    break;
                }
                
                // Check for date range overlap (new project starts during existing project)
                if (($start_date_obj >= $existing_start && $start_date_obj <= $existing_end) ||
                    // New project ends during existing project
                    ($deadline_obj >= $existing_start && $deadline_obj <= $existing_end) ||
                    // New project completely contains existing project
                    ($start_date_obj <= $existing_start && $deadline_obj >= $existing_end)) {
                    $is_valid = false;
                    $error_message = 'Error: This project conflicts with an existing project at this location from ' . 
                                   $existing_start->format('Y-m-d') . ' to ' . $existing_end->format('Y-m-d') . '.';
                    break;
                }
            }
        }
    }
    
        // Check for existing project with same name (case-insensitive)
    if ($is_valid) {
        $project_escaped = mysqli_real_escape_string($con, $project);
        $check_sql = "SELECT project FROM projects WHERE LOWER(project) = LOWER('$project_escaped') AND user_id = '$user_id' LIMIT 1";
        $result = mysqli_query($con, $check_sql);
        
        if (mysqli_num_rows($result) > 0) {
            // Store all form data in session
            $_SESSION['form_data'] = [
                'project' => $project,
                'region' => $region,
                'province' => $province,
                'municipality' => $municipality,
                'barangay' => $barangay,
                'budget' => $budget,
                'start_date' => $start_date,
                'deadline' => $deadline,
                'foreman' => $foreman,
                'category' => $category,
                'billings' => $billings,
                'size' => $size
            ];
            
            // Find the next available name
            $counter = 1;
            $base_project = trim($project);
            $new_project_name = "$base_project $counter";
            
            // Keep incrementing counter until we find an available name
            while (true) {
                $check_sql = "SELECT project FROM projects WHERE LOWER(project) = LOWER('" . mysqli_real_escape_string($con, $new_project_name) . "') AND user_id = '$user_id' LIMIT 1";
                $result = mysqli_query($con, $check_sql);
                
                if (mysqli_num_rows($result) === 0) {
                    // Store the original project name and suggested name in session
            $_SESSION['suggested_project_name'] = $new_project_name;
            $_SESSION['original_project_name'] = $project; // Store the original name too
            
            // Redirect to self to show the modal
            header("Location: projects.php?show_duplicate_modal=1");
                    exit();
                }
                
                $counter++;
                $new_project_name = "$base_project $counter";
                
                // Safety check to prevent infinite loop
                if ($counter > 100) {
                    $is_valid = false;
                    $error_message = 'Error: Too many projects with similar names. Please choose a different name.';
                    break;
                }
            }
        }
    }
    
    // If validation passed, proceed with project creation
    if ($is_valid) {
        // Check if we're coming from a duplicate name confirmation
        if (isset($_SESSION['form_data'])) {
            // Get the project name from POST if available (user modified it in the modal)
            // Otherwise use the suggested name from session
            $project = isset($_POST['project']) ? trim($_POST['project']) : '';
            if (empty($project) && isset($_SESSION['suggested_project_name'])) {
                $project = trim($_SESSION['suggested_project_name']);
            }
            
            // Ensure project name is not empty
            if (empty($project)) {
                $project = 'New Project ' . date('Y-m-d');
            } else {
                // Clean and format the project name
                $project = ucwords(strtolower(trim($project)));
            }
            $region = $_SESSION['form_data']['region'];
            $province = $_SESSION['form_data']['province'];
            $municipality = $_SESSION['form_data']['municipality'];
            $barangay = $_SESSION['form_data']['barangay'];
            $location = trim("$region $province $municipality $barangay");
            $budget = $_SESSION['form_data']['budget'];
            $start_date = $_SESSION['form_data']['start_date'];
            $deadline = $_SESSION['form_data']['deadline'];
            $foreman = $_SESSION['form_data']['foreman'];
            $category = $_SESSION['form_data']['category'];
            $billings = $_SESSION['form_data']['billings'];
            $size = $_SESSION['form_data']['size'];
            
            // Clear the session data
            unset($_SESSION['form_data']);
            unset($_SESSION['suggested_project_name']);
        } else {
            // For regular form submission, ensure project name is properly formatted
            $project = trim($project);
            if (empty($project)) {
                $project = 'New Project ' . date('Y-m-d');
            } else {
                // Capitalize first letter of each word
                $project = ucwords(strtolower($project));
            }
        }
        
        $sql = "INSERT INTO projects (user_id, project, location, budget, start_date, deadline, foreman, category, size)
                VALUES ('$user_id', '$project', '$location', '$budget', '$start_date', '$deadline', '$foreman', '$category', '$size')";

        if (mysqli_query($con, $sql)) {
        // Get the last inserted project_id
        $new_project_id = mysqli_insert_id($con);

        // Insert default divisions for the selected category
        if (isset($default_divisions[$category])) {
            foreach ($default_divisions[$category] as $division) {
                $division_esc = mysqli_real_escape_string($con, $division);
                mysqli_query($con, "INSERT INTO project_divisions (project_id, division_name, progress) VALUES ('$new_project_id', '$division_esc', 0)");
            }
        }

        // Get foreman details
        if (!empty($foreman)) {
            $fres = mysqli_query($con, "SELECT e.employee_id, p.title as position_title, p.daily_rate FROM employees e LEFT JOIN positions p ON e.position_id = p.position_id WHERE CONCAT(e.first_name, ' ', e.last_name) = '" . mysqli_real_escape_string($con, $foreman) . "' LIMIT 1");
            if ($frow = mysqli_fetch_assoc($fres)) {
                $foreman_id = $frow['employee_id'];
                $position = mysqli_real_escape_string($con, $frow['position_title']);
                $daily_rate = floatval($frow['daily_rate']);
                // Calculate project days
                $start = new DateTime($start_date);
                $end = new DateTime($deadline);
                $interval = $start->diff($end);
                $project_days = $interval->days + 1;
                $total = $daily_rate * $project_days;
                // Insert into project_add_employee with correct total
                mysqli_query($con, "INSERT INTO project_add_employee (project_id, employee_id, position, daily_rate, total) VALUES ('$new_project_id', '$foreman_id', '$position', '$daily_rate', '$total')");
            }
        }

            header("Location: projects.php?success=1");
            exit();
        } else {
            $error_message = 'Error: ' . mysqli_error($con);
            $forecastMessage = '<div class="alert alert-danger">' . $error_message . '</div>';
        }
    } else {
        // Show validation error
        $forecastMessage = '<div class="alert alert-danger">' . $error_message . '</div>';
    }
}

// (Removed auto-update for project status based on start_date and deadline)

// Fetch all employees with position 'Foreman' for the dropdown
$foreman_position_id = null;
$pos_result = mysqli_query($con, "SELECT position_id FROM positions WHERE title = 'Foreman' LIMIT 1");
if ($pos_result && $row = mysqli_fetch_assoc($pos_result)) {
    $foreman_position_id = $row['position_id'];
}
$foremen = [];
if ($foreman_position_id) {
    $emp_result = mysqli_query($con, "SELECT employee_id, first_name, last_name FROM employees WHERE position_id = '$foreman_position_id'");
    while ($emp = mysqli_fetch_assoc($emp_result)) {
        $foremen[] = $emp;
    }
}



// --- PAGINATION & SEARCH/FILTER LOGIC FOR PROJECT LIST ---
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';

// Status filter
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($con, $_GET['status']) : '';

// Date filter
$date_filter = isset($_GET['date_filter']) ? mysqli_real_escape_string($con, $_GET['date_filter']) : '';
$start_date = isset($_GET['start_date']) ? mysqli_real_escape_string($con, $_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? mysqli_real_escape_string($con, $_GET['end_date']) : '';

// Base filter - only show non-archived projects for the current user
$filter_sql = "user_id='$userid' AND archived=0";

// Apply search filter
if ($search !== '') {
    $filter_sql .= " AND (project LIKE '%$search%' OR location LIKE '%$search%')";
}

// Apply status filter
if ($status_filter === 'finished') {
    $filter_sql .= " AND status = 'Finished'";
} elseif ($status_filter === 'cancelled') {
    $filter_sql .= " AND status = 'Cancelled'";
}

// Apply date filter
if (!empty($date_filter)) {
    $today = date('Y-m-d');
    if ($date_filter === 'today') {
        $filter_sql .= " AND (DATE(start_date) = '$today' OR DATE(deadline) = '$today')";
    } elseif ($date_filter === 'week') {
        $monday = date('Y-m-d', strtotime('monday this week'));
        $sunday = date('Y-m-d', strtotime('sunday this week'));
        $filter_sql .= " AND (
            (DATE(start_date) BETWEEN '$monday' AND '$sunday') 
            OR (DATE(deadline) BETWEEN '$monday' AND '$sunday')
            OR (start_date <= '$monday' AND deadline >= '$sunday')
        )";
    } elseif ($date_filter === 'month') {
        $first_day = date('Y-m-01');
        $last_day = date('Y-m-t');
        $filter_sql .= " AND (
            (DATE(start_date) BETWEEN '$first_day' AND '$last_day') 
            OR (DATE(deadline) BETWEEN '$first_day' AND '$last_day')
            OR (start_date <= '$first_day' AND deadline >= '$last_day')
        )";
    } elseif ($date_filter === 'year') {
        $year = date('Y');
        $first_day = "$year-01-01";
        $last_day = "$year-12-31";
        $filter_sql .= " AND (
            (YEAR(start_date) = '$year' OR YEAR(deadline) = '$year')
            OR (start_date <= '$first_day' AND deadline >= '$last_day')
        )";
    }
}

// Apply start date filter
if (!empty($start_date)) {
    $filter_sql .= " AND DATE(start_date) >= '$start_date'";
}

// Apply end date filter
if (!empty($end_date)) {
    $filter_sql .= " AND DATE(deadline) <= '$end_date'";
}

$count_query = "SELECT COUNT(*) as total FROM projects WHERE $filter_sql";
$count_result = mysqli_query($con, $count_query);
$total_projects = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_projects / $limit);


$user = null;
$userprofile = '../uploads/default_profile.png';
if ($userid) {
    $result = $con->query("SELECT * FROM users WHERE id = '$userid'");
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_firstname = $user['firstname'];
        $user_lastname = $user['lastname'];
        $user_email = $user['email'];
        $userprofile = isset($user['profile_path']) && $user['profile_path'] ? '../uploads/' . $user['profile_path'] : '../uploads/default_profile.png';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <link rel="stylesheet" href="style.css" />
    <title>Project Manager Projects</title>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div class="bg-white" id="sidebar-wrapper">
        <div class="user text-center py-4">
                <img class="img img-fluid rounded-circle mb-2 sidebar-profile-img" src="<?php echo isset($userprofile) ? $userprofile : (isset($_SESSION['userprofile']) ? $_SESSION['userprofile'] : '../uploads/default_profile.png'); ?>" width="70" alt="User Profile">
                <h5 class="mb-1 text-white"><?php echo htmlspecialchars($user_name); ?></h5>
                <p class="text-white small mb-0"><?php echo htmlspecialchars($user_email); ?></p>
                <hr style="border-top: 1px solid #fff; opacity: 0.3; margin: 12px 0 0 0;">
            </div>
            <div class="list-group list-group-flush ">
                <a href="pm_dashboard.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'pm_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>Dashboard
                </a>
                <a href="projects.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'projects.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i>Projects
                </a>
                <a href="expenses.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'expenses.php' ? 'active' : ''; ?>">
                    <i class="fas fa-wallet"></i>Expenses
                </a>
                <a href="materials.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'materials.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cubes"></i>Materials
                </a>
                <a href="equipment.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'equipment.php' ? 'active' : ''; ?>">
                    <i class="fas fa-wrench"></i>Equipment
                </a>
                <a href="employees.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'employees.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-friends"></i>Employees
                </a>
                <a href="positions.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'positions.php' ? 'active' : ''; ?>">
                    <i class="fas fa-briefcase"></i>Position
                </a>
            </div>
        </div>
        <!-- /#sidebar-wrapper -->

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-align-left primary-text fs-4 me-3" id="menu-toggle"></i>
                    <h2 class="fs-2 m-0">Project</h2>
                </div>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <?php include 'pm_notification.php'; ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle second-text fw-bold" href="#" id="navbarDropdown"
                                role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php echo htmlspecialchars($user_name); ?>
                                <img src="<?php echo $userprofile; ?>" alt="User" class="rounded-circle" width="30" height="30" style="margin-left: 8px;">
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="pm_profile.php">Profile</a></li>
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal">Change Password</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="container-fluid px-2 px-md-4 py-3">
                  
                                <div class="card mb-5 shadow rounded-3">
                                  <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                      <h4 class="mb-0">List of Projects</h4>
                                      <div class="d-flex align-items-center gap-2">
                                        <a href="gantt.php" class="btn btn-primary"><i class="fas fa-chart-bar me-1"></i> Gantt Chart</a>
                                        <a href="../forecasting/analogous_forecasting.php" class="btn btn-info text-white"><i class="fas fa-chart-line me-1"></i> Analogous Forecasting</a>
                                        <a href="project_archived.php" class="btn btn-danger"><i class="fas fa-archive me-1"></i> Archives</a>
                                        <button class="btn btn-success" style="width:180px;" data-bs-toggle="modal" data-bs-target="#AddProjectModal">
                                          <i class="fas fa-plus"></i> New Project
                                        </button>
                                      </div>
                                    </div>
                                    <hr>
                                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                                        
                                        <form method="get" id="searchForm" class="mb-0 d-flex flex-wrap gap-2" style="flex: 1 1 auto;">
                                            <!-- Search Box -->
                                            <div class="search-box position-relative" style="width:250px;">
                                                <span class="position-absolute" style="left:10px;top:50%;transform:translateY(-50%);color:#aaa;z-index:2;">
                                                    <i class="fas fa-search"></i>
                                                </span>
                                                <input type="hidden" name="page" value="1">
                                                <input type="text" class="form-control pl-4" name="search" placeholder="Search project/location" value="<?php echo htmlspecialchars($search); ?>" id="searchInput" autocomplete="off" style="padding-left:2rem;">
                                            </div>
                                            
                                            <!-- Status Filter -->
                                            <div class="dropdown">
                                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="statusFilter" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <?php 
                                                    $status_text = 'All Status';
                                                    if ($status_filter === 'finished') $status_text = 'Finished';
                                                    elseif ($status_filter === 'cancelled') $status_text = 'Cancelled';
                                                    echo $status_text;
                                                    ?>
                                                </button>
                                                <ul class="dropdown-menu" aria-labelledby="statusFilter">
                                                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['status' => '', 'page' => 1])); ?>">All Status</a></li>
                                                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'finished', 'page' => 1])); ?>">Finished</a></li>
                                                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'cancelled', 'page' => 1])); ?>">Cancelled</a></li>
                                                </ul>
                                            </div>
                                            
                                            <!-- Quick Date Filters -->
                                            <div class="dropdown me-2">
                                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="quickDateFilter" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <?php 
                                                    $date_text = 'All Time';
                                                    if ($date_filter === 'today') $date_text = 'Today';
                                                    elseif ($date_filter === 'week') $date_text = 'This Week';
                                                    elseif ($date_filter === 'month') $date_text = 'This Month';
                                                    elseif ($date_filter === 'year') $date_text = 'This Year';
                                                    echo $date_text;
                                                    ?>
                                                </button>
                                                <ul class="dropdown-menu" aria-labelledby="quickDateFilter">
                                                    <li><a class="dropdown-item" href="?<?php 
                                                    $query = $_GET;
                                                    unset($query['date_filter']);
                                                    $query['page'] = 1;
                                                    echo http_build_query($query);
                                                    ?>"></i>All Time</a></li>
                                                    <li><a class="dropdown-item" href="?<?php 
                                                        $query = array_merge($_GET, ['date_filter' => 'week', 'page' => 1]);
                                                        echo http_build_query($query);
                                                    ?>">
                                                        <div>
                                                            <div>This Week</div>
                                                            
                                                        </div>
                                                    </a></li>
                                                    <li><a class="dropdown-item" href="?<?php 
                                                        $query = array_merge($_GET, ['date_filter' => 'month', 'page' => 1]);
                                                        echo http_build_query($query);
                                                    ?>">
                                                        
                                                        <div>
                                                            <div>This Month</div>
                                                           
                                                        </div>
                                                    </a></li>
                                                    <li><a class="dropdown-item" href="?<?php 
                                                        $query = array_merge($_GET, ['date_filter' => 'year', 'page' => 1]);
                                                        echo http_build_query($query);
                                                    ?>">
                                                       
                                                        <div>
                                                            <div>This Year</div>
                                                            
                                                        </div>
                                                    </a></li>
                                                </ul>
                                            </div>
                                            
                                            <!-- Start Date Filter -->
                                            <div class="input-group input-group-sm me-2" style="width: 180px;">
                                                <span class="input-group-text"><i class="fas fa-calendar-plus"></i></span>
                                                <input type="date" name="start_date" class="form-control form-control-sm" 
                                                       value="<?php echo htmlspecialchars($start_date); ?>" 
                                                       id="startDateInput" 
                                                       onchange="applyDateFilter()"
                                                       placeholder="Start Date">
                                            </div>
                                            
                                            <!-- End Date Filter -->
                                            <div class="input-group input-group-sm" style="width: 180px;">
                                                <span class="input-group-text"><i class="fas fa-calendar-minus"></i></span>
                                                <input type="date" name="end_date" class="form-control form-control-sm" 
                                                       value="<?php echo htmlspecialchars($end_date); ?>" 
                                                       id="endDateInput" 
                                                       onchange="applyDateFilter()"
                                                       placeholder="End Date">
                                                <button class="btn btn-outline-secondary" type="button" onclick="clearDateFilter()">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            
                                           
                                        </form>
                                        <button class="btn btn-primary ms-2" id="filterButton">
                                          <i class="fas fa-filter"></i> Filter
                                        </button>
                                        <div class="ms-auto" style="flex:0 0 auto;text-align:right;">
                                          <!-- Removed Gantt Chart button from here -->
                                      </div>
                                  </div>
                              </div>
                                <script>
                                // Search input auto-submit (vanilla JS)
                                document.addEventListener('DOMContentLoaded', function() {
                                    var searchInput = document.getElementById('searchInput');
                                    var searchForm = document.getElementById('searchForm');
                                    
                                    // Auto-submit for search input
                                    if (searchInput && searchForm) {
                                        var searchTimeout;
                                        searchInput.addEventListener('input', function() {
                                            clearTimeout(searchTimeout);
                                            searchTimeout = setTimeout(function() {
                                                searchForm.submit();
                                            }, 400);
                                        });
                                    }
                                });

                                // Function to apply date filter automatically
                                function applyDateFilter() {
                                    const form = document.getElementById('searchForm');
                                    // Clear quick date filter when using custom dates
                                    const dateFilterInput = document.createElement('input');
                                    dateFilterInput.type = 'hidden';
                                    dateFilterInput.name = 'date_filter';
                                    dateFilterInput.value = '';
                                    form.appendChild(dateFilterInput);
                                    form.submit();
                                }

                                // Function to clear date filter
                                function clearDateFilter() {
                                    document.getElementById('startDateInput').value = '';
                                    document.getElementById('endDateInput').value = '';
                                    // Remove date parameters and keep other filters
                                    const url = new URL(window.location.href);
                                    url.searchParams.delete('start_date');
                                    url.searchParams.delete('end_date');
                                    window.location.href = url.toString();
                                }
                                </script>

                                <!-- Project Table -->
                                <div class="table-responsive mb-0">
                                    <table class="table table-bordered table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>No.</th>
                                                <th>Project</th>
                                                <th>Start Date</th>
                                                <th>Deadline</th>
                                                <th>Location</th>
                                                <th class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Query to fetch projects based on filter, search, and pagination
                                            $query = mysqli_query($con, "SELECT * FROM projects WHERE $filter_sql ORDER BY deadline DESC LIMIT $limit OFFSET $offset");
                                            $no = $offset + 1;
                                            if (mysqli_num_rows($query) > 0) {
                                                while ($row = mysqli_fetch_assoc($query)) {
                                                    $id = $row['project_id'];
                                            ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo $row['project']; ?></td>
                                                <td><?php echo $row['start_date']; ?></td>
                                                <td><?php echo $row['deadline']; ?></td>
                                                <td>
                                                    <?php
                                                    // If location is numeric, show 'Unknown', else show as is
                                                    echo (is_numeric($row['location'])) ? 'Unknown' : htmlspecialchars($row['location']);
                                                    ?>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-info text-white font-weight-bold view-details-btn" data-bs-toggle="modal" data-bs-target="#projectDetailsModal" data-project-id="<?php echo $id; ?>">
                                                        <i class="fas fa-eye"></i> Details
                                                    </button>
                                                    <button class="btn btn-sm btn-danger text-white font-weight-bold archive-project" data-project-id="<?php echo $id; ?>">
                                                        <i class="fas fa-trash"></i> Archive
                                                    </button>
                                                </td>
                                            </tr>
                                <?php
                                                }
                                            } else {
                                            ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No projects found</td>
                                            </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                                <nav aria-label="Page navigation" class="mt-3 mb-3">
                                  <ul class="pagination justify-content-center custom-pagination-green mb-0">
                                    <li class="page-item<?php if($page <= 1) echo ' disabled'; ?>">
                                      <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                                    </li>
                                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                      <li class="page-item<?php if($i == $page) echo ' active'; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                      </li>
                                    <?php endfor; ?>
                                    <li class="page-item<?php if($page >= $total_pages) echo ' disabled'; ?>">
                                      <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                                    </li>
                                  </ul>
                                </nav>
                            </div>
                     
            </div>
    <!-- /#page-content-wrapper -->
    </div>
    
    <!-- Duplicate Project Confirmation Modal -->
    <div class="modal fade" id="duplicateProjectModal" tabindex="-1" aria-labelledby="duplicateProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-danger">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title w-100 text-center" id="duplicateProjectModalLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>Project Name Exists
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-exclamation-circle fa-3x text-danger mb-3"></i>
                        <p class="mb-1">A project with this name already exists.</p>
                        <p class="mb-3">Save as:</p>
                        <div class="d-flex justify-content-center align-items-center mb-3">
                            <input type="text" class="form-control form-control-lg text-center fw-bold text-danger" id="suggestedNameInput" style="max-width: 80%;">
                        </div>
                        <p class="text-muted small">(You can edit the name above)</p>
                    </div>
                </div>
                <div class="modal-footer justify-content-center border-top-0">
                    <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDuplicateBtn">
                        <i class="fas fa-save me-1"></i> Save as <span id="confirmNameDisplay" class="fw-bold"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="AddProjectModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="projects.php" id="addProjectForm">
                <div class="modal-body">
                    <div class="row">
                        <!-- Left Column -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Project Name*</label>
                                <input type="text" class="form-control" name="project" required>
                            </div>
                            <div class="form-group">
                                <label>Region*</label>
                                <select class="form-control" name="Region" id="region-select" required>
                                    <option value="" selected disabled>Select Region</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Province*</label>
                                <select class="form-control" name="Province" id="province-select" required disabled>
                                    <option value="" selected disabled>Select Region First</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Municipality*</label>
                                <select class="form-control" name="Municipality" id="municipality-select" required disabled>
                                    <option value="" selected disabled>Select Province First</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Baranggay*</label>
                                <select class="form-control" name="Baranggay" id="barangay-select" required disabled>
                                    <option value="" selected disabled>Select Municipality First</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Budget (₱)*</label>
                                <input type="number" step="0.01" class="form-control" name="budget" required>
                            </div>
                            <div class="form-group">
                                <label>Start Date*</label>
                                <input type="date" class="form-control" name="start_date" required>
                            </div>
                            <div class="form-group">
                                <label>Deadline*</label>
                                <input type="date" class="form-control" name="deadline" required>
                                <div id="dateValidationError" class="text-danger mt-1" style="display: none;"></div>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="col-md-6">
                            <div class="form-group" style="display:none;">
                                <label>Status*</label>
                                <select class="form-control" name="io" id="status-select" disabled>
                                    <option value="4" selected>Estimating</option>
                                </select>
                            </div>
                            <input type="hidden" name="io" value="4">

                            <div class="form-group">
                                <label>Foreman</label>
                                <select class="form-control" name="foreman">
                                    <option value="" disabled selected>Select Foreman</option>
                                    <?php foreach (isset($foremen) ? $foremen : [] as $foreman): ?>
                                        <option value="<?= htmlspecialchars($foreman['first_name'] . ' ' . $foreman['last_name']) ?>">
                                            <?= htmlspecialchars($foreman['first_name'] . ' ' . $foreman['last_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Category*</label>
                                <select class="form-control" name="category" required>
                                    <option value="" disabled selected>Select Category</option>
                                    <option value="Building">Building</option>
                                    <option value="Renovation">Renovation</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Size (m²)*</label>
                                <input type="number" step="1" class="form-control" name="size" min="20" required oninput="validateSize(this.value)" onkeypress="return validateSizeInput(event)">
                                <div id="sizeFeedback" class="invalid-feedback">
                                    Size must be at least 20 sqm and cannot be negative.
                                </div>
                                <small id="sizeWarning" class="text-warning" style="display: none;">Warning: Size should be at least 20 sqm.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_project" class="btn btn-primary">Save Project</button>
                </div>
            </form>
        </div>
    </div>
</div>
       

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
   
    <?php if (isset($_GET['show_duplicate_modal']) && isset($_SESSION['suggested_project_name'])): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const duplicateModal = document.getElementById('duplicateProjectModal');
        if (duplicateModal) {
            const modal = new bootstrap.Modal(duplicateModal, {
                backdrop: 'static',
                keyboard: false
            });
            
            const suggestedNameInput = document.getElementById('suggestedNameInput');
            const confirmNameDisplay = document.getElementById('confirmNameDisplay');
            const originalForm = document.getElementById('addProjectForm');
            
            // Set the suggested name
            const suggestedName = '<?php echo addslashes($_SESSION['suggested_project_name']); ?>';
            suggestedNameInput.value = suggestedName;
            confirmNameDisplay.textContent = suggestedName;
            
            // Update the suggested name when the input changes
            suggestedNameInput.addEventListener('input', function() {
                const newName = this.value.trim() || 'New Project';
                confirmNameDisplay.textContent = newName;
            });
            
            // Handle confirm button click
            document.getElementById('confirmDuplicateBtn').addEventListener('click', function() {
                // Get and validate the new project name
                const newProjectName = suggestedNameInput.value.trim();
                if (!newProjectName) {
                    alert('Please enter a valid project name');
                    return;
                }
                
                // Create a hidden form to submit the confirmation
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'projects.php';
                
                // Add all the form data from the session
                <?php foreach ($_SESSION['form_data'] as $key => $value): ?>
                    <?php if ($key !== 'project'): ?>
                        const input_<?php echo $key; ?> = document.createElement('input');
                        input_<?php echo $key; ?>.type = 'hidden';
                        input_<?php echo $key; ?>.name = '<?php echo $key; ?>';
                        input_<?php echo $key; ?>.value = '<?php echo addslashes($value); ?>';
                        form.appendChild(input_<?php echo $key; ?>);
                    <?php endif; ?>
                <?php endforeach; ?>
                
                // Add the new project name
                const projectInput = document.createElement('input');
                projectInput.type = 'hidden';
                projectInput.name = 'project';
                projectInput.value = newProjectName;
                form.appendChild(projectInput);
                
                // Add the submit button
                const submitInput = document.createElement('input');
                submitInput.type = 'hidden';
                submitInput.name = 'add_project';
                submitInput.value = '1';
                form.appendChild(submitInput);
                
                // Submit the form
                document.body.appendChild(form);
                form.submit();
            });
            
            // Show the modal
            modal.show();
        }
    });
    </script>
    <?php endif; ?>
   

    <div class="modal fade" id="archiveModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Archive Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to archive this project?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmArchive">Archive</button>
            </div>
        </div>
    </div>
    </div>

    <!-- JS for Archive button -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let projectToArchive = null;
            const archiveModalEl = document.getElementById('archiveModal');
            const archiveModal = new bootstrap.Modal(archiveModalEl);

            document.querySelectorAll('.archive-project').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                e.preventDefault();
                projectToArchive = this.getAttribute('data-project-id');
                archiveModal.show();
                });
            });

            document.getElementById('confirmArchive').addEventListener('click', function() {
                if (projectToArchive) {
                window.location.href = 'projects.php?archive=' + projectToArchive;
                }
                archiveModal.hide();
            });

            archiveModalEl.addEventListener('hidden.bs.modal', function() {
                projectToArchive = null;
            });
        });
    </script>

    <!-- Place this at the end of the body, after jQuery is loaded -->
    <script>
        let phData = null;
        fetch('philippines.json')
            .then(response => response.json())
            .then(data => {
                phData = data;
                // Populate regions
                const regionSelect = document.getElementById('region-select');
                for (const regionKey in phData) {
                    const region = phData[regionKey];
                    const opt = document.createElement('option');
                    opt.value = region.region_name;
                    opt.textContent = region.region_name;
                    regionSelect.appendChild(opt);
                }
            });

        document.getElementById('region-select').addEventListener('change', function() {
            const regionName = this.value;
            const regionKey = Object.keys(phData).find(key => phData[key].region_name === regionName);
            const provinceSelect = document.getElementById('province-select');
            const municipalitySelect = document.getElementById('municipality-select');
            const barangaySelect = document.getElementById('barangay-select');
            provinceSelect.innerHTML = '<option value="" selected disabled>Select Province</option>';
            municipalitySelect.innerHTML = '<option value="" selected disabled>Select Province First</option>';
            barangaySelect.innerHTML = '<option value="" selected disabled>Select Municipality First</option>';
            provinceSelect.disabled = false;
            municipalitySelect.disabled = true;
            barangaySelect.disabled = true;
            for (const provinceName in phData[regionKey].province_list) {
                const opt = document.createElement('option');
                opt.value = provinceName;
                opt.textContent = provinceName;
                provinceSelect.appendChild(opt);
                }
            });

        document.getElementById('province-select').addEventListener('change', function() {
            const regionName = document.getElementById('region-select').value;
            const regionKey = Object.keys(phData).find(key => phData[key].region_name === regionName);
            const provinceName = this.value;
            const municipalitySelect = document.getElementById('municipality-select');
            const barangaySelect = document.getElementById('barangay-select');
            municipalitySelect.innerHTML = '<option value="" selected disabled>Select Municipality</option>';
            barangaySelect.innerHTML = '<option value="" selected disabled>Select Municipality First</option>';
            municipalitySelect.disabled = false;
            barangaySelect.disabled = true;
            for (const municipalityName in phData[regionKey].province_list[provinceName].municipality_list) {
                const opt = document.createElement('option');
                opt.value = municipalityName;
                opt.textContent = municipalityName;
                municipalitySelect.appendChild(opt);
                }
            });

        document.getElementById('municipality-select').addEventListener('change', function() {
            const regionName = document.getElementById('region-select').value;
            const regionKey = Object.keys(phData).find(key => phData[key].region_name === regionName);
            const provinceName = document.getElementById('province-select').value;
            const municipalityName = this.value;
            const barangaySelect = document.getElementById('barangay-select');
            barangaySelect.innerHTML = '<option value="" selected disabled>Select Barangay</option>';
            barangaySelect.disabled = false;
            const barangayList = phData[regionKey].province_list[provinceName].municipality_list[municipalityName].barangay_list;
            for (const barangay of barangayList) {
                const opt = document.createElement('option');
                opt.value = barangay;
                opt.textContent = barangay;
                barangaySelect.appendChild(opt);
        }
    });
    </script>

    <div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
            <div class="modal-body">
                <span id="feedbackIcon" style="font-size: 3rem;"></span>
                <h4 id="feedbackTitle"></h4>
                <p id="feedbackMessage"></p>
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
            </div>
            </div>
        </div>
        </div>

    <script>
        function removeQueryParam(param) {
        const url = new URL(window.location);
        url.searchParams.delete(param);
        window.history.replaceState({}, document.title, url.pathname + url.search);
        }

        function showFeedbackModal(success, message, reason = '', paramToRemove = null) {
        var icon = document.getElementById('feedbackIcon');
        var title = document.getElementById('feedbackTitle');
        var msg = document.getElementById('feedbackMessage');
        if (success) {
            icon.innerHTML = '<i class="fas fa-check-circle" style="color:#28a745"></i>';
            title.textContent = 'Success!';
            msg.textContent = message;
        } else {
            icon.innerHTML = '<i class="fas fa-times-circle" style="color:#dc3545"></i>';
            title.textContent = 'Error!';
            msg.textContent = message + (reason ? ' Reason: ' + reason : '');
        }
        var feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
        feedbackModal.show();

        // Remove the query param after showing the modal
        if (paramToRemove) {
            removeQueryParam(paramToRemove);
        }
        }
        // Show feedback modal if redirected after add or archive
        <?php if (isset($_GET['success'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
        showFeedbackModal(true, 'Project saved successfully.', '', 'success');
        });
        <?php elseif (isset($_GET['archived'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
        showFeedbackModal(true, 'Project archived successfully.', '', 'archived');
        });
        <?php endif; ?>
        </script>

        <?php if (isset($forecastMessage) && strpos($forecastMessage, 'Error:') !== false): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
        showFeedbackModal(false, 'Failed to save project.', <?php echo json_encode(strip_tags($forecastMessage)); ?>, 'forecastMessage');
        });
    </script>
<?php endif; ?>

    <script>
        // Size validation function
        function validateSize(value) {
            const sizeInput = document.querySelector('input[name="size"]');
            const sizeFeedback = document.getElementById('sizeFeedback');
            const sizeWarning = document.getElementById('sizeWarning');
            
            // Convert to number
            const size = parseInt(value);
            
            // Clear previous validation states
            sizeInput.classList.remove('is-invalid', 'is-valid');
            sizeFeedback.style.display = 'none';
            sizeWarning.style.display = 'none';
            
            // Check if value is negative or invalid
            if (isNaN(size) || size < 0) {
                sizeInput.value = ''; // Clear negative values
                sizeInput.classList.add('is-invalid');
                sizeFeedback.style.display = 'block';
                sizeFeedback.textContent = 'Negative values are not allowed. Size must be at least 20 sqm.';
                return;
            }
            
            // Check if value is less than 20
            if (size < 20 && size > 0) {
                sizeInput.classList.add('is-invalid');
                sizeFeedback.style.display = 'block';
                sizeFeedback.textContent = 'Size must be at least 20 sqm.';
                sizeWarning.style.display = 'block';
                sizeWarning.textContent = 'Warning: Minimum allowed size is 20 sqm.';
            } else if (size >= 20) {
                sizeInput.classList.add('is-valid');
                sizeWarning.style.display = 'none';
            }
        }
        
        function validateSizeInput(event) {
            const char = String.fromCharCode(event.which || event.keyCode);
            const value = event.target.value;
            
            // Allow: backspace, delete, tab, escape, enter
            if ([8, 9, 13, 27, 46].indexOf(event.which) !== -1 ||
                // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                (event.which === 65 && (event.ctrlKey === true || event.metaKey === true)) ||
                (event.which === 67 && (event.ctrlKey === true || event.metaKey === true)) ||
                (event.which === 86 && (event.ctrlKey === true || event.metaKey === true)) ||
                (event.which === 88 && (event.ctrlKey === true || event.metaKey === true))) {
                return;
            }
            
            // Ensure that it is a number and stop the keypress
            if ((event.which < 48 || event.which > 57) && event.which !== 0) {
                event.preventDefault();
                return false;
            }
            
            // Prevent starting with 0 (unless it's just 0, which will be caught by validation)
            if (value.length === 0 && char === '0') {
                event.preventDefault();
                return false;
            }
            
            return true;
        }
        
        // Function to validate project dates
        function validateProjectDates() {
            const startDate = new Date(document.querySelector('input[name="start_date"]').value);
            const deadline = new Date(document.querySelector('input[name="deadline"]').value);
            const location = document.querySelector('select[name="Baranggay"]').value;
            const errorElement = document.getElementById('dateValidationError');
            
            // Clear previous error
            errorElement.textContent = '';
            errorElement.style.display = 'none';
            
            // Check if deadline is before start date
            if (deadline < startDate) {
                errorElement.textContent = 'Error: Deadline cannot be before start date.';
                errorElement.style.display = 'block';
                return false;
            }
            
            // Check if location is selected
            if (!location) {
                errorElement.textContent = 'Please select a location first.';
                errorElement.style.display = 'block';
                return false;
            }
            
            return true;
        }
        
        // Add event listeners when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Add form submission handler
            const form = document.getElementById('addProjectForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (!validateProjectDates()) {
                        e.preventDefault();
                    }
                });
            }
            
            // Add date change handlers
            const startDateInput = document.querySelector('input[name="start_date"]');
            const deadlineInput = document.querySelector('input[name="deadline"]');
            
            if (startDateInput && deadlineInput) {
                startDateInput.addEventListener('change', validateProjectDates);
                deadlineInput.addEventListener('change', validateProjectDates);
            }
        });
        
        var el = document.getElementById("wrapper");
        var toggleButton = document.getElementById("menu-toggle");

        toggleButton.onclick = function () {
            el.classList.toggle("toggled");
        };
    </script>

    <script>
document.addEventListener('DOMContentLoaded', function() {
  // Set min for Start Date to today
  var startDateInput = document.querySelector('#AddProjectModal input[name="start_date"]');
  if (startDateInput) {
    var today = new Date();
    var yyyy = today.getFullYear();
    var mm = String(today.getMonth() + 1).padStart(2, '0');
    var dd = String(today.getDate()).padStart(2, '0');
    var minDate = yyyy + '-' + mm + '-' + dd;
    startDateInput.setAttribute('min', minDate);
  }
  // Set min for Deadline to tomorrow
  var deadlineInput = document.querySelector('#AddProjectModal input[name="deadline"]');
  if (deadlineInput) {
    var tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    var yyyy = tomorrow.getFullYear();
    var mm = String(tomorrow.getMonth() + 1).padStart(2, '0');
    var dd = String(tomorrow.getDate()).padStart(2, '0');
    var minDeadline = yyyy + '-' + mm + '-' + dd;
    deadlineInput.setAttribute('min', minDeadline);
  }
  // Prevent submit if start date is in the past or deadline is today/past
  var addProjectForm = document.getElementById('addProjectForm');
  if (addProjectForm && startDateInput && deadlineInput) {
    addProjectForm.addEventListener('submit', function(e) {
      var selectedStart = startDateInput.value;
      var selectedDeadline = deadlineInput.value;
      var now = new Date();
      now.setHours(0,0,0,0);
      // Start Date check
      if (selectedStart) {
        var selectedStartDate = new Date(selectedStart + 'T00:00:00');
        if (selectedStartDate < now) {
          startDateInput.setCustomValidity('Start Date cannot be in the past.');
          startDateInput.reportValidity();
          e.preventDefault();
          return;
        } else {
          startDateInput.setCustomValidity('');
        }
      }
      // Deadline check
      if (selectedDeadline) {
        var selectedDeadlineDate = new Date(selectedDeadline + 'T00:00:00');
        var tomorrow = new Date(now);
        tomorrow.setDate(tomorrow.getDate() + 1);
        if (selectedDeadlineDate < tomorrow) {
          deadlineInput.setCustomValidity('Deadline must be after today.');
          deadlineInput.reportValidity();
          e.preventDefault();
          return;
        } else {
          deadlineInput.setCustomValidity('');
        }
      }
    });
    startDateInput.addEventListener('input', function() {
      startDateInput.setCustomValidity('');
    });
    deadlineInput.addEventListener('input', function() {
      deadlineInput.setCustomValidity('');
    });
  }
});
</script>

<!-- View Project Details Modal -->
<div class="modal fade" id="projectDetailsModal" tabindex="-1" aria-labelledby="projectDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold" id="projectDetailsModalLabel">
          <i class="fas fa-info-circle me-2"></i>Project Details
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4" id="projectDetailsModalBody">
        <div class="text-center py-4">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading project details...</span>
          </div>
          <p class="mt-2 text-muted">Loading project information...</p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var projectDetailsModal = document.getElementById('projectDetailsModal');
    projectDetailsModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var projectId = button.getAttribute('data-project-id');
        var modalBody = document.getElementById('projectDetailsModalBody');
        modalBody.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';

        fetch('get_project_details_ajax.php?id=' + projectId)
            .then(response => response.text())
            .then(html => {
                modalBody.innerHTML = html;
            })
            .catch(error => {
                modalBody.innerHTML = '<div class="alert alert-danger">Failed to load project details.</div>';
                console.error('Error:', error);
            });
    });
});
</script>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="changePasswordModalLabel">Change Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="changePasswordForm">
          <div class="mb-3">
            <label for="current_password" class="form-label">Current Password</label>
            <input type="password" class="form-control" id="current_password" name="current_password" required>
          </div>
          <div class="mb-3">
            <label for="new_password" class="form-label">New Password</label>
            <input type="password" class="form-control" id="new_password" name="new_password" required>
          </div>
          <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm New Password</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
          </div>
          <div id="changePasswordFeedback" class="mb-2"></div>
          <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-success">Change Password</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
// Change Password AJAX (like pm_profile.php)
document.addEventListener('DOMContentLoaded', function() {
  var changePasswordForm = document.getElementById('changePasswordForm');
  var feedbackDiv = document.getElementById('changePasswordFeedback');
  if (changePasswordForm) {
    changePasswordForm.addEventListener('submit', function(e) {
      e.preventDefault();
      feedbackDiv.innerHTML = '';
      var formData = new FormData(changePasswordForm);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', '', true);
      xhr.onload = function() {
        try {
          var res = JSON.parse(xhr.responseText);
          if (res.success) {
            feedbackDiv.innerHTML = '<div class="alert alert-success">' + res.message + '</div>';
            changePasswordForm.reset();
            setTimeout(function() {
              var modal = bootstrap.Modal.getInstance(document.getElementById('changePasswordModal'));
              if (modal) modal.hide();
            }, 1200);
          } else {
            feedbackDiv.innerHTML = '<div class="alert alert-danger">' + res.message + '</div>';
          }
        } catch (err) {
          feedbackDiv.innerHTML = '<div class="alert alert-danger">Unexpected error. Please try again.</div>';
        }
      };
      formData.append('change_password', '1');
      xhr.send(formData);
    });
  }
});
</script>

<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to log out?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="../logout.php" class="btn btn-danger">Logout</a>
      </div>
    </div>
  </div>
</div>

</html>