<?php
    session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 3) {
    header("Location: ../login.php");
    exit();
}
require_once '../config.php';
require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Add CSS styles for the forecast display
function addForecastStyles() {
    echo '<style>
        /* Forecast Styles */
        #forecastedValue {
            min-height: 100px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            transition: all 0.3s ease;
            background: white;
            border: 2px solid #0d6efd;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .forecast-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0d6efd;
            margin: 0;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .forecast-details {
            font-size: 0.7rem;
            color: #6c757d;
            text-align: center;
            margin-top: 4px;
            line-height: 1.3;
        }

        .forecast-loading {
            background: #f8f9fa;
        }

        .forecast-loading .forecast-amount {
            color: #6c757d;
        }

        @keyframes loadingShimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        @keyframes barMove {
            0% { background-position: 0% 0; }
            100% { background-position: 100% 0; }
        }
        
        /* Tooltip styles */
        [data-bs-toggle="tooltip"] {
            cursor: help;
            border-bottom: 1px dotted #6c757d;
        }
        
        .tooltip-inner {
            max-width: 300px;
            padding: 0.5rem 1rem;
            text-align: left;
            white-space: pre-line;
        }
    </style>';
}

addForecastStyles();

    $userid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $user_email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
    $user_firstname = isset($_SESSION['firstname']) ? $_SESSION['firstname'] : '';
    $user_lastname = isset($_SESSION['lastname']) ? $_SESSION['lastname'] : '';
    $user_name = trim($user_firstname . ' ' . $user_lastname);
    $current_page = basename($_SERVER['PHP_SELF']);

    // --- Project Form Submission Handler ---
    // Handle AJAX email check
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_email') {
        header('Content-Type: application/json');
        $email = mysqli_real_escape_string($con, $_POST['email']);
        $query = mysqli_query($con, "SELECT id, CONCAT(firstname, ' ', lastname) as name FROM users WHERE email = '$email' AND is_verified = 1 LIMIT 1");
        
        if (mysqli_num_rows($query) > 0) {
            $user = mysqli_fetch_assoc($query);
            echo json_encode([
                'success' => false,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name']
                ]
            ]);
        } else {
            echo json_encode(['success' => true]);
        }
        exit();
    }
    
    // Handle AJAX project name check
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_project_name') {
        header('Content-Type: application/json');
        $project_name = mysqli_real_escape_string($con, $_POST['project_name']);
        $user_id = $_SESSION['user_id'];
        
        $query = mysqli_query($con, "SELECT project_id FROM projects WHERE user_id = '$user_id' AND project = '$project_name' AND (archived IS NULL OR archived = 0) LIMIT 1");
        
        if (mysqli_num_rows($query) > 0) {
            echo json_encode(['exists' => true]);
        } else {
            echo json_encode(['exists' => false]);
        }
        exit();
    }
    
   
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['project_name'])) {
        mysqli_begin_transaction($con);
    
        try {
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
                      && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
            $client_type = $_POST['client_type'] ?? 'new';
            $client_email = '';
            $user_id = null;
            $first_name = '';
            $last_name = '';
    
            if ($client_type === 'new') {
                $first_name = mysqli_real_escape_string($con, $_POST['first_name']);
                $last_name  = mysqli_real_escape_string($con, $_POST['last_name']);
                $client_email = mysqli_real_escape_string($con, $_POST['email']);
    
                $check_email = mysqli_query($con, "SELECT id FROM users WHERE email = '$client_email'");
                if (mysqli_num_rows($check_email) > 0) {
                    throw new Exception('A user with this email already exists.');
                }
    
                // Generate password
                $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
                $password = substr(str_shuffle($chars), 0, 12);
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
                // Verification code
                $verification_code = md5(uniqid(rand(), true));
    
                $user_query = "INSERT INTO users (firstname, lastname, email, password, verification_code, is_verified, user_level) 
                               VALUES ('$first_name', '$last_name', '$client_email', '$hashed_password', '$verification_code', 1, 6)";
                if (!mysqli_query($con, $user_query)) {
                    throw new Exception('Error creating user account: ' . mysqli_error($con));
                }
                $user_id = mysqli_insert_id($con);
    
                // Send email
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'VoltechElectricalConstruction0@gmail.com';
                    $mail->Password = 'sban pumy bmia wwal';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
    
                    $mail->setFrom('VoltechElectricalConstruction0@gmail.com', 'Voltech System');
                    $mail->addAddress($client_email, "$first_name $last_name");
    
                    $mail->isHTML(true);
                    $mail->Subject = 'Your Account Credentials';
                    $login_link = "http://voltechelectricalconstruction.com/login.php";
                    $mail->Body = "
                        <h2>Welcome to Voltech Electrical Construction</h2>
                        <p>Hello $first_name,</p>
                        <p>Your account has been created successfully. Here are your login details:</p>
                        <p>
                            <strong>Email:</strong> $client_email<br>
                            <strong>Password:</strong> $password
                        </p>
                        <p><a href='$login_link' style='background:#27ae60;color:white;padding:10px 20px;
                        text-decoration:none;border-radius:5px;'>Login to Your Account</a></p>
                        <p>We recommend changing your password after your first login.</p>
                        <p>Thanks,<br>Voltech Electrical Construction</p>
                    ";
    
                    $mail->send();
                    $_SESSION['success_message'] = 'User account created successfully. Login details sent to ' . $client_email;
                } catch (Exception $e) {
                    error_log("Mailer Error: {$mail->ErrorInfo}");
                    $_SESSION['error_message'] = 'Account was created, but email sending failed.';
                }
    
            } else {
                $client_email = mysqli_real_escape_string($con, $_POST['client_email']);
                $user_query = mysqli_query($con, "SELECT id, firstname, lastname FROM users WHERE email = '$client_email' AND is_verified = 1 LIMIT 1");
                if (mysqli_num_rows($user_query) === 0) {
                    throw new Exception('No verified user found with this email address.');
                }
                $user_data = mysqli_fetch_assoc($user_query);
                $user_id = $user_data['id'];
                $first_name = $user_data['firstname'];
                $last_name = $user_data['lastname'];
    
                $_SESSION['success_message'] = 'Project has been assigned to the existing client.';
            }
    
            // Project info
            $project_name = mysqli_real_escape_string($con, $_POST['project_name']);
            $size = floatval($_POST['size']);
            $forecasted_cost = floatval($_POST['forecasted_cost'] ?? 0);
            
            // Check for duplicate project name for the same user
            $logged_in_user_id = $_SESSION['user_id'];
            $duplicate_check = "SELECT project_id, project FROM projects WHERE user_id = ? AND project = ? AND (archived IS NULL OR archived = 0) LIMIT 1";
            $stmt = mysqli_prepare($con, $duplicate_check);
            mysqli_stmt_bind_param($stmt, 'is', $logged_in_user_id, $project_name);
            mysqli_stmt_execute($stmt);
            $duplicate_result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($duplicate_result) > 0) {
                $duplicate_row = mysqli_fetch_assoc($duplicate_result);
                throw new Exception("A project with the name '$project_name' already exists. Please use a different project name.");
            }
    
            $barangay = mysqli_real_escape_string($con, $_POST['barangay'] ?? '');
            $municipality = mysqli_real_escape_string($con, $_POST['municipality'] ?? '');
            $province = mysqli_real_escape_string($con, $_POST['province'] ?? '');
            $region = mysqli_real_escape_string($con, $_POST['region'] ?? '');
            $location = trim("$region $province $municipality $barangay");
    
            $start_date = mysqli_real_escape_string($con, $_POST['start_date'] ?? date('Y-m-d'));
            $end_date = mysqli_real_escape_string($con, $_POST['end_date'] ?? date('Y-m-d', strtotime('+1 month')));
            $category = mysqli_real_escape_string($con, $_POST['category'] ?? 'Other');
    
            // Check for project conflicts at the same location with overlapping dates
            $conflict_check = "SELECT project_id, project, start_date, deadline 
                             FROM projects 
                             WHERE location = ?
                             AND (
                                 (start_date <= ? AND deadline >= ?)  -- New project overlaps with existing project
                                 OR (start_date BETWEEN ? AND ?)      -- New project starts during existing project
                                 OR (deadline BETWEEN ? AND ?)        -- New project ends during existing project
                             )
                             LIMIT 1";
            
            $stmt = mysqli_prepare($con, $conflict_check);
            mysqli_stmt_bind_param($stmt, 'sssssss', 
                $location, 
                $end_date, $start_date,  // For first condition (reversed order for overlap check)
                $start_date, $end_date,  // For second condition
                $start_date, $end_date   // For third condition
            );
            mysqli_stmt_execute($stmt);
            $conflict_result = mysqli_stmt_get_result($stmt);
            
            if ($conflict_row = mysqli_fetch_assoc($conflict_result)) {
                $existing_start = date('M d, Y', strtotime($conflict_row['start_date']));
                $existing_end = date('M d, Y', strtotime($conflict_row['deadline']));
                throw new Exception("Cannot add project. There is already a project at this location ($location) with overlapping dates: \n" .
                                "Project: {$conflict_row['project']} (from $existing_start to $existing_end)");
            }
            
            // If no conflicts, insert the new project
            // Use the logged-in user's ID (from session) as the project owner
            $logged_in_user_id = $_SESSION['user_id'];
            $project_query = "INSERT INTO projects (project, location, size, user_id, client_email, start_date, deadline, category, forecasted_cost, created_at, updated_at) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = mysqli_prepare($con, $project_query);
            mysqli_stmt_bind_param($stmt, 'ssdissssd', $project_name, $location, $size, $logged_in_user_id, $client_email, $start_date, $end_date, $category, $forecasted_cost);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Error creating project: ' . mysqli_error($con));
            }

            // Capture the newly inserted project ID before committing
            $project_id = mysqli_insert_id($con);
            if (!$project_id && function_exists('mysqli_stmt_insert_id')) {
                $project_id = mysqli_stmt_insert_id($stmt);
            }

            mysqli_commit($con);

            // Ensure we have a valid project ID
            if (empty($project_id)) {
                throw new Exception('Failed to retrieve the new project ID.');
            }
            
            // Get the project category for the redirect
            $category = isset($_POST['category']) ? urlencode($_POST['category']) : '';
            
            if ($isAjax) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Project created successfully!',
                    'redirect' => 'projects.php?success=add&project_id=' . $project_id . '&category=' . $category
                ]);
            } else {
                header("Location: projects.php?success=add&project_id=" . $project_id . '&category=' . $category);
            }
            exit();
    
                 } catch (Exception $e) {
             mysqli_rollback($con);
             if ($isAjax) {
                 http_response_code(400);
                 echo json_encode(['success' => false, 'message' => $e->getMessage()]);
             } else {
                 $_SESSION['error_message'] = $e->getMessage();
                 header("Location: projects.php?error=" . urlencode($e->getMessage()));
             }
             exit();
         }
    }
    

    // --- Change Password Backend Handler (AJAX) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
        header('Content-Type: application/json');
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
        echo json_encode($response);
        exit();
    }

    // User profile image fetch block (restored)
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

    // Pagination variables (restored)
    $results_per_page = 10;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $start_from = ($page - 1) * $results_per_page;

    $form_data = [];
    if (isset($_SESSION['form_data'])) {
        $form_data = $_SESSION['form_data'];
        unset($_SESSION['form_data']);
    }
    
    // Get user ID from session
    $user_id = $_SESSION['user_id'];
    
    // Get search and filter parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $size_filter = isset($_GET['size_filter']) ? trim($_GET['size_filter']) : '';
    
    // Build the base query
    $where_conditions = ["user_id = $user_id", "(archived IS NULL OR archived = 0)"];
    $params = [];
    
    // Add search condition
    if (!empty($search)) {
        $search_term = mysqli_real_escape_string($con, $search);
        $where_conditions[] = "(project LIKE '%$search_term%' OR location LIKE '%$search_term%')";
    }
    
    // Add status filter
    if (!empty($status_filter)) {
        $status = mysqli_real_escape_string($con, $status_filter);
        $where_conditions[] = "status = '$status'";
    }
    
    // Add size filter
    if (!empty($size_filter)) {
        switch ($size_filter) {
            case '0-50':
                $where_conditions[] = "size BETWEEN 0 AND 50";
                break;
            case '51-100':
                $where_conditions[] = "size BETWEEN 51 AND 100";
                break;
            case '101-200':
                $where_conditions[] = "size BETWEEN 101 AND 200";
                break;
            case '201':
                $where_conditions[] = "size > 200";
                break;
        }
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get total count with filters
    $total_projects = 0;
    $count_query = "SELECT COUNT(*) as total FROM projects WHERE $where_clause";
    $count_result = mysqli_query($con, $count_query);
    if ($count_result) {
        $count_row = mysqli_fetch_assoc($count_result);
        $total_projects = $count_row['total'];
    }
    
    // Calculate total pages
    $total_pages = ceil($total_projects / $results_per_page);
    
    // Debug output
    error_log("Total projects: " . $total_projects);
    error_log("Results per page: " . $results_per_page);
    error_log("Total pages: " . $total_pages);
    
    // Fetch projects for the current page with filters
    $projects = [];
    $query = "SELECT * FROM projects WHERE $where_clause ORDER BY created_at DESC LIMIT $start_from, $results_per_page";
    $result = mysqli_query($con, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $projects[] = $row;
        }
    }

    if (isset($_GET['archive'])) {
        $archive_id = intval($_GET['archive']);
        if (mysqli_query($con, "UPDATE projects SET archived=1 WHERE project_id='$archive_id' AND user_id='$userid'")) {
            header("Location: projects.php?success=archive");
        } else {
            $error = urlencode(mysqli_error($con));
            header("Location: projects.php?error=$error");
        }
        exit();
    }
    

    // Overdue status check is now handled via AJAX when viewing project details

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
    <title>Projects Management</title>
</head>

<style>
@keyframes barMove {
  0% { background-position: 0% 0; }
  100% { background-position: 100% 0; }
}
#forecastedValue {
    transition: box-shadow 0.3s;
}
#forecastedValue:hover {
    box-shadow: 0 0 20px 0 rgba(13,110,253,.15);
}
</style>

<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div class="bg-white" id="sidebar-wrapper">
        <div class="user text-center py-4">
                <img class="img img-fluid rounded-circle mb-2 sidebar-profile-img" src="<?php echo $userprofile; ?>" width="70" alt="User Profile">
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
                <a href="gantt.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'gantt.php' ? 'active' : ''; ?>">
                    <i class="fas fa-briefcase"></i>My Schedule
                </a>
                <a href="paymethod.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'paymethod.php' ? 'active' : ''; ?>">
                    <i class="fas fa-money-bill"></i>Payment Methods
                </a>
            </div>
        </div>
        <!-- /#sidebar-wrapper -->

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-align-left primary-text fs-4 me-3" id="menu-toggle"></i>
                    <h2 class="fs-2 m-0">Projects Management</h2>
                </div>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                    <?php 
                    include 'pm_notification.php'; 
                    
                    // Function to count unread messages
                    function countUnreadMessages($con, $tables, $userId) {
                        $total = 0;
                        foreach ($tables as $table) {
                            $query = "SELECT COUNT(*) as count FROM $table WHERE receiver_id = ? AND is_read = 0";
                            if ($stmt = $con->prepare($query)) {
                                $stmt->bind_param("i", $userId);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $row = $result->fetch_assoc();
                                $total += $row['count'];
                            }
                        }
                        return $total;
                    }
                    
                    // Get total unread messages
                    $tables = ['pm_client_messages', 'pm_procurement_messages', 'admin_pm_messages'];
                    $unreadCount = countUnreadMessages($con, $tables, $_SESSION['user_id']);
                    ?>
                    <li class="nav-item ms-2">
                        <a class="nav-link position-relative" href="pm_messenger.php" title="Messages">
                            <i class="fas fa-comment-dots fs-5"></i>
                            <?php if ($unreadCount > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.6em;">
                                    <?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
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
            <div class="container-fluid px-4 py-4">
                <div class="card mb-5 shadow rounded-3">
                    <div class="card-body">
                        <div class="mb-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
                            <h4 class="mb-0">Projects</h4>
                            <div class="d-flex gap-2">
                                <a href="project_archived.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-archive me-1"></i>Archived
                                </a>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                                    <i class="fas fa-plus me-1"></i> Add Project
                                </button>
                            </div>
                        </div>
                        <hr>
                        <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
                            <form class="d-flex flex-grow-1" method="get" action="" id="searchForm" style="min-width:260px; max-width:400px;">
                                <div class="input-group w-100">
                                    <span class="input-group-text bg-white border-end-0">
                                        <i class="fas fa-search text-muted"></i>
                                    </span>
                                    <input type="text" class="form-control border-start-0" name="search" placeholder="Search projects..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" autocomplete="off">
                                </div>
                            </form>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="statusFilter" data-bs-toggle="dropdown" aria-expanded="false">
                                    <?php 
                                    if (isset($_GET['status']) && !empty($_GET['status'])) {
                                        echo 'Status: ' . htmlspecialchars($_GET['status']);
                                    } else {
                                        echo 'All Status';
                                    }
                                    ?>
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="statusFilter">
                                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['status' => ''])); ?>">All Status</a></li>
                                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'Pending'])); ?>">Pending</a></li>
                                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'Ongoing'])); ?>">Ongoing</a></li>
                                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'Finished'])); ?>">Finished</a></li>
                                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'Cancelled'])); ?>">Cancelled</a></li>
                                </ul>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="sizeFilter" data-bs-toggle="dropdown" aria-expanded="false">
                                    <?php 
                                    if (isset($_GET['size_filter']) && !empty($_GET['size_filter'])) {
                                        echo 'Size: ' . htmlspecialchars($_GET['size_filter']);
                                    } else {
                                        echo 'All Sizes';
                                    }
                                    ?>
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="sizeFilter">
                                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['size_filter' => ''])); ?>">All Sizes</a></li>
                                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['size_filter' => '0-50'])); ?>">0 - 50 sqm</a></li>
                                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['size_filter' => '51-100'])); ?>">51 - 100 sqm</a></li>
                                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['size_filter' => '101-200'])); ?>">101 - 200 sqm</a></li>
                                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['size_filter' => '201'])); ?>">201+ sqm</a></li>
                                </ul>
                            </div>
                            <div class="d-flex gap-2">
                            </div>
                        </div>
                        
                        <?php 
                        // Calculate starting number based on current page and items per page
                        $no = (($page - 1) * $results_per_page) + 1;
                        ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped text-center">
                                <thead class="bg-success text-white">
                                    <tr>
                                        <th class="text-center">No.</th>
                                        <th>Project Name</th>
                                        <th>Location</th>
                                        <th>Size (Floor sqm)</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($projects)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4">
                                                <div class="text-muted">No projects found. Add your first project to get started.</div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($projects as $project): ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo htmlspecialchars($project['project']); ?></td>
                                                <td><?php echo htmlspecialchars($project['location']); ?></td>
                                                <td class="text-end"><?php echo number_format($project['size'], 2); ?></td>
                                                <td class="text-nowrap">
                                                  <button class="btn btn-outline-primary btn-sm view-details" data-project-id="<?php echo $project['project_id']; ?>">
                                                        <i class="fas fa-eye"></i> Details
                                                    </button>
                                                    <button class="btn btn-sm btn-danger text-white font-weight-bold archive-project" data-project-id="<?php echo $project['project_id']; ?>">
                                                        <i class="fas fa-trash"></i> Archive
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Debug Info -->
                        <div class="alert alert-info d-none">
                            Total Projects: <?php echo $total_projects; ?><br>
                            Results Per Page: <?php echo $results_per_page; ?><br>
                            Total Pages: <?php echo $total_pages; ?><br>
                            Current Page: <?php echo $page; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php 
                        error_log("Pagination Debug - Total Projects: " . $total_projects);
                        error_log("Pagination Debug - Total Pages: " . $total_pages);
                        // Always show pagination for testing
                        // if ($total_pages > 1): 
                        ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center custom-pagination-green">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status=' . urlencode($status) : ''; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                        <span class="sr-only">Previous</span>
                                    </a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status=' . urlencode($status) : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status=' . urlencode($status) : ''; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                        <span class="sr-only">Next</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        <?php // endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <!-- Suggestion Modal -->
    <div class="modal fade" id="suggestionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Project Creation Suggestion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Would you like to add materials and equipment to this project now?</p>
                    <p class="small text-muted">You can add them later from the project details page if needed.</p>
                    
                    <!-- Suggested Materials Section -->
                    <div class="mt-4">
                        <h6>Suggested Materials (based on similar projects):</h6>
                        <div id="suggestedMaterialsLoading" class="text-center py-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 mb-0">Loading suggested materials...</p>
                        </div>
                        <div id="suggestedMaterialsContainer" class="d-none">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Material</th>
                                            <th>Quantity</th>
                                            <th>Unit</th>
                                            <th>Unit Price</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="suggestedMaterialsList">
                                        <!-- Suggested materials will be inserted here -->
                                    </tbody>
                                </table>
                            </div>
                            <div class="alert alert-info mt-2">
                                <i class="fas fa-info-circle me-1"></i> These are suggested materials based on similar completed projects. You can add them with one click or add your own materials.
                            </div>
                        </div>
                        <div id="noSuggestions" class="alert alert-warning d-none">
                            <i class="fas fa-exclamation-circle me-1"></i> No suggested materials found for this project type.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Maybe Later</button>
                    <button type="button" id="goToProjectBtn" class="btn btn-primary">
                        <span class="d-flex align-items-center">
                            <span id="addNowText">Yes, Add Now</span>
                            <span id="addNowLoading" class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Feedback Modal -->
     <div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
       <div class="modal-dialog modal-dialog-centered">
         <div class="modal-content text-center">
           <div class="modal-body py-4">
             <span id="feedbackIcon" style="font-size: 3rem;"></span>
             <h4 id="feedbackMessage" class="mt-3"></h4>
           </div>
           <div class="modal-footer justify-content-center border-0">
             <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal">OK</button>
           </div>
         </div>
       </div>
     </div>

     <!-- Success Modal -->
     <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
       <div class="modal-dialog modal-dialog-centered">
         <div class="modal-content text-center">
           <div class="modal-body py-4">
             <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
             <h4 id="successMessage" class="mt-3">Success!</h4>
           </div>
           <div class="modal-footer justify-content-center border-0">
             <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal">OK</button>
           </div>
         </div>
       </div>
     </div>

     <!-- Error Modal -->
     <div class="modal fade" id="errorModal" tabindex="-1" aria-hidden="true">
       <div class="modal-dialog modal-dialog-centered">
         <div class="modal-content text-center">
           <div class="modal-body py-4">
             <i class="fas fa-times-circle text-danger" style="font-size: 3rem;"></i>
             <h4 id="errorMessage" class="mt-3">Error!</h4>
           </div>
           <div class="modal-footer justify-content-center border-0">
             <button type="button" class="btn btn-danger px-4" data-bs-dismiss="modal">OK</button>
           </div>
         </div>
       </div>
     </div>

    
    <div class="modal fade" id="addProjectModal" tabindex="-1" aria-labelledby="addProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="" id="multiStepForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addProjectModalLabel">Add New Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Progress Bar -->
                        <div class="progress mb-4" style="height: 10px;">
                            <div class="progress-bar" id="formProgress" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        
                        <!-- Step Indicators -->
                        <div class="d-flex justify-content-between mb-4">
                            <div class="step-indicator active" data-step="1">
                                <div class="step-number">1</div>
                                <div class="step-label">Client Info</div>
                            </div>
                            <div class="step-connector"></div>
                            <div class="step-indicator" data-step="2">
                                <div class="step-number">2</div>
                                <div class="step-label">Project Details</div>
                            </div>
                        </div>
                        
                        <!-- Step 1: Client Information -->
                        <div class="step" id="step1">
                            <h5 class="mb-4">Client Information</h5>
                            
                            <!-- Client Type Selection -->
                            <div class="mb-4">
                                <label class="form-label d-block mb-2">Client Type <span class="text-danger">*</span></label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="client_type" id="newClient" value="new" checked>
                                    <label class="form-check-label" for="newClient">New Client</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="client_type" id="existingClient" value="existing">
                                    <label class="form-check-label" for="existingClient">Existing Client</label>
                                </div>
                            </div>
                            
                            <!-- New Client Fields -->
                            <div id="newClientFields">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="firstName" class="form-label">First Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="firstName" name="first_name">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="lastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="lastName" name="last_name">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" required oninput="checkEmailExists(this.value)">
                                    <div id="emailFeedback" class="invalid-feedback">
                                        An account with this email already exists. Please select "Existing Client" instead.
                                    </div>
                                    <div id="emailChecking" class="text-muted small mt-1 d-none">
                                        <i class="fas fa-spinner fa-spin"></i> Checking email availability...
                                    </div>
                                </div>
                                <!-- Password will be auto-generated on the server side -->
                                <input type="hidden" name="password" value="auto-generated">
                            </div>
                            
                            <!-- Existing Client Fields -->
                            <div id="existingClientFields" class="d-none">
                                <div class="mb-3">
                                    <label for="clientSelect" class="form-label">Select Client <span class="text-danger">*</span></label>
                                    <select class="form-select" id="clientSelect" name="client_email" required>
                                        <option value="" selected disabled>Loading clients...</option>
                                    </select>
                                    <div class="form-text">Only verified clients are shown in this list.</div>
                                </div>
                            </div>
                        </div>
                        
                       <!-- Step 2: Project Details -->
                       <div class="step d-none" id="step2">
                            <h5 class="mb-4">Project Details</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="projectName" class="form-label">Project Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="projectName" name="project_name" required oninput="checkProjectNameExists(this.value)">
                                    <div id="projectNameFeedback" class="invalid-feedback">
                                        A project with this name already exists. Please use a different name.
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="size" class="form-label">Size (Floor sqm) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="size" name="size" step="0.01" min="0" required>
                                        <span class="input-group-text">sqm</span>
                                    </div>
                                </div>

                                <!-- Region -->
                                <div class="col-md-6 mb-3">
                                    <label for="region" class="form-label">Region <span class="text-danger">*</span></label>
                                    <select class="form-control" name="region" id="region-select" required>
                                        <option value="" selected disabled>Select Region</option>
                                    </select>
                                </div>

                                <!-- Province -->
                                <div class="col-md-6 mb-3">
                                    <label for="province" class="form-label">Province <span class="text-danger">*</span></label>
                                    <select class="form-control" name="province" id="province-select" required disabled>
                                        <option value="" selected disabled>Select Region First</option>
                                    </select>
                                </div>

                                <!-- Municipality -->
                                <div class="col-md-6 mb-3">
                                    <label for="municipality" class="form-label">Municipality/City <span class="text-danger">*</span></label>
                                    <select class="form-control" name="municipality" id="municipality-select" required disabled>
                                        <option value="" selected disabled>Select Province First</option>
                                    </select>
                                </div>

                                <!-- Barangay -->
                                <div class="col-md-6 mb-3">
                                    <label for="barangay" class="form-label">Barangay <span class="text-danger">*</span></label>
                                    <select class="form-control" name="barangay" id="barangay-select" required disabled>
                                        <option value="" selected disabled>Select Municipality First</option>
                                    </select>
                                </div>

                                <!-- Date Pickers Container -->
                                <div class="mb-4">
                                    <!-- 3-Month Date Picker (House, Building) -->
                                    <div id="datePicker3Months" class="border rounded p-3 mb-3">
                                        <h6 class="mb-3">Standard Projects (3 Months)</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="start_date_3m" class="form-label">Start Date <span class="text-danger">*</span></label>
                                                    <input type="date" class="form-control" id="start_date_3m" name="start_date_3m">
                                                    <div class="form-text text-muted">Project start date (cannot be in the past)</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="end_date_3m" class="form-label">End Date <span class="text-danger">*</span></label>
                                                    <input type="date" class="form-control" id="end_date_3m" name="end_date_3m">
                                                    <div class="form-text text-muted">Must be at least 3 months after start date</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 1-Week Date Picker (Electrical, Renovation) -->
                                    <div id="datePicker1Week" class="border rounded p-3" style="display: none;">
                                        <h6 class="mb-3">Quick Projects (1 Week)</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="start_date_1w" class="form-label">Start Date <span class="text-danger">*</span></label>
                                                    <input type="date" class="form-control" id="start_date_1w" name="start_date_1w">
                                                    <div class="form-text text-muted">Project start date (cannot be in the past)</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="end_date_1w" class="form-label">End Date <span class="text-danger">*</span></label>
                                                    <input type="date" class="form-control" id="end_date_1w" name="end_date_1w">
                                                    <div class="form-text text-muted">Must be at least 1 week after start date</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Hidden fields for form submission -->
                                <input type="hidden" id="final_start_date" name="start_date">
                                <input type="hidden" id="final_end_date" name="end_date">

                                <!-- Category -->
                                <div class="col-md-6 mb-3">
                                    <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                                    <select class="form-control" id="category" name="category" required>
                                        <option value="" selected disabled>Select Category</option>
                                        <option value="House">House</option>
                                        <option value="House Electrical">House Electrical</option>
                                        <option value="House Renovation">House Renovation</option>
                                        <option value="Building">Building</option>
                                        <option value="Building Electrical">Building Electrical</option>
                                        <option value="Building Renovation">Building Renovation</option>
                                    </select>
                                </div>

                                <!-- Forecasted Value -->
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0 fw-bold text-primary">Analogous Forecasting</h6>
                                        <span class="badge bg-primary bg-opacity-10 text-primary">Beta</span>
                                    </div>
                                    <div class="w-100">
                                        <div 
                                            id="forecastedValue" 
                                            class="bg-white border-2 border-primary rounded p-3 d-flex flex-column align-items-center w-100"
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                            title="Enter project size and select a category to see forecast"
                                        >
                                            <div class="forecast-amount text-muted">
                                                <i class="fas fa-calculator me-2"></i> FORECAST COST
                                            </div>
                                            <div class="forecast-details small text-muted mt-1 text-center">
                                                Enter size & select category
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Hidden location input -->
                                <input type="hidden" id="location" name="location" required>
                                <!-- Hidden forecasted cost input -->
                                <input type="hidden" id="forecasted_cost" name="forecasted_cost" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-outline-primary" id="prevBtn" style="display: none;">
                            <i class="fas fa-arrow-left me-1"></i> Previous
                        </button>
                        <button type="button" class="btn btn-primary" id="nextBtn">
                            Next <i class="fas fa-arrow-right ms-1"></i>
                        </button>
                        <button type="submit" name="add_project" class="btn btn-success" id="submitBtn" style="display: none;">
                            <i class="fas fa-save me-1"></i> Save Project
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    
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
            </div> <!-- <-- THIS WAS MISSING -->
        </div> <!-- <-- THIS WAS MISSING -->
    </div>
</div>

   

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/project_forecast.js"></script>

    <script src="js/project_overdue.js"></script>
    <script>
    let lastProjectId = null; // Store the last created project ID
    
    // Show suggestion modal
    function showSuggestion(projectId, category = '') {
        const modal = new bootstrap.Modal(document.getElementById('suggestionModal'));
        const modalEl = document.getElementById('suggestionModal');
        
        // Store the project ID in the modal for later use
        modalEl.dataset.projectId = projectId;
        
        // Reset UI state
        document.getElementById('suggestedMaterialsLoading').classList.remove('d-none');
        document.getElementById('suggestedMaterialsContainer').classList.add('d-none');
        document.getElementById('noSuggestions').classList.add('d-none');
        
        // Get the project category if not provided
        if (!category) {
            // Try to get category from the URL or form data
            const urlParams = new URLSearchParams(window.location.search);
            category = urlParams.get('category') || '';
        }
        
        // If we have a category, fetch suggested materials
        if (category) {
            fetchSuggestedMaterials(category, projectId);
        } else {
            // No category, hide loading and show no suggestions
            document.getElementById('suggestedMaterialsLoading').classList.add('d-none');
            document.getElementById('noSuggestions').classList.remove('d-none');
        }
        
        // Add event listener for the "Add Now" button
        const addNowBtn = document.getElementById('goToProjectBtn');
        const addNowText = document.getElementById('addNowText');
        const addNowLoading = document.getElementById('addNowLoading');
        
        // Remove any existing event listeners to prevent duplicates
        const newAddNowBtn = addNowBtn.cloneNode(true);
        addNowBtn.parentNode.replaceChild(newAddNowBtn, addNowBtn);
        
        newAddNowBtn.addEventListener('click', function() {
            // Call addSuggestedMaterial with the project ID
            const projectId = modalEl.dataset.projectId;
            if (projectId) {
                addSuggestedMaterial(projectId);
            } else {
                console.error('Project ID not found');
                showFeedback('error', 'Error: Project ID not found');
            }
        });
        
        // Add event listener to show feedback when modal is closed
        if (suggestionModal) {
            suggestionModal.addEventListener('hidden.bs.modal', function() {
                showFeedback('success', 'Project has been added successfully!');
            });
        }
        
        modal.show();
    }
    
    // Fetch suggested materials based on project category
    function fetchSuggestedMaterials(category, projectId) {
        const loadingEl = document.getElementById('suggestedMaterialsLoading');
        const containerEl = document.getElementById('suggestedMaterialsContainer');
        const noSuggestionsEl = document.getElementById('noSuggestions');
        const materialsListEl = document.getElementById('suggestedMaterialsList');
        
        // Show loading state
        loadingEl.classList.remove('d-none');
        containerEl.classList.add('d-none');
        noSuggestionsEl.classList.add('d-none');
        
        // Fetch suggested materials from the API
        fetch(`get_suggested_materials.php?category=${encodeURIComponent(category)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                // Hide loading state
                loadingEl.classList.add('d-none');
                
                if (data.error) {
                    console.error('Error fetching suggested materials:', data.error);
                    noSuggestionsEl.classList.remove('d-none');
                    return;
                }
                
                if (!data.suggestions || data.suggestions.length === 0) {
                    noSuggestionsEl.classList.remove('d-none');
                    return;
                }
                
                // Clear existing materials
                materialsListEl.innerHTML = '';
                
                // Add each suggested material to the list
                data.suggestions.forEach(material => {
                    const totalCost = (material.material_price * material.quantity + parseFloat(material.additional_cost || 0)).toFixed(2);
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${material.material_id}</td>
                        <td>${material.material_name}</td>
                        <td>${parseFloat(material.quantity).toFixed(2)}</td>
                        <td>${material.unit || 'pcs'}</td>
                        <td>₱${parseFloat(material.material_price).toFixed(2)}</td>
                        <td>₱${totalCost}</td>
                    `;
                    row.style.cursor = 'pointer';
                    row.title = 'Click to add this material';
                    row.addEventListener('click', () => {
                        addSuggestedMaterial(projectId, material);
                    });
                    materialsListEl.appendChild(row);
                });
                
                // Show the materials container
                containerEl.classList.remove('d-none');
            })
            .catch(error => {
                console.error('Error fetching suggested materials:', error);
                loadingEl.classList.add('d-none');
                noSuggestionsEl.classList.remove('d-none');
            });
    }
    
    // Add all suggested materials to the project
    function addSuggestedMaterial(projectId, material) {
        const addNowBtn = document.getElementById('goToProjectBtn');
        const addNowText = document.getElementById('addNowText');
        const addNowLoading = document.getElementById('addNowLoading');
        
        // Disable the button and show loading
        addNowBtn.disabled = true;
        addNowText.textContent = 'Saving Materials...';
        addNowLoading.classList.remove('d-none');
        
        // If a single material is provided, use it directly
        // Otherwise, get all materials from the table
        let materialsToSave = [];
        
        if (material) {
            // Single material provided (from row click)
            materialsToSave = [{
                material_id: material.material_id, // Include material_id in the saved object
                material_name: material.material_name,
                quantity: parseFloat(material.quantity),
                unit: material.unit || 'pcs',
                material_price: parseFloat(material.material_price)
            }];
        } else {
            // Get all materials from the table (from Add Now button)
            const materialRows = document.querySelectorAll('#suggestedMaterialsList tr');
            materialRows.forEach(row => {
                const cells = row.cells;
                if (cells.length >= 6) { // Changed from 4 to 6 to account for all columns
                    materialsToSave.push({
                        material_id: parseInt(cells[0].textContent.trim()) || 0, // Get material_id from first column
                        material_name: cells[1].textContent.trim(), // Material name is now in the second column
                        quantity: parseFloat(cells[2].textContent) || 1,
                        unit: cells[3].textContent.trim() || 'pcs',
                        material_price: parseFloat(cells[4].textContent.replace('₱', '').replace(/,/g, '')) || 0
                    });
                }
            });
        }
        
        // Validate project ID
        if (!projectId || projectId === '0') {
            console.error('Invalid project ID:', projectId);
            showFeedback('error', 'Error: Invalid project ID. Please try again.');
            addNowBtn.disabled = false;
            addNowText.textContent = 'Add Now';
            addNowLoading.classList.add('d-none');
            return;
        }
        
        // Validate materials
        if (materialsToSave.length === 0) {
            console.error('No materials to save');
            showFeedback('error', 'Error: No materials selected');
            addNowBtn.disabled = false;
            addNowText.textContent = 'Add Now';
            addNowLoading.classList.add('d-none');
            return;
        }
        
        console.log('Saving materials for project ID:', projectId);
        console.log('Materials to save:', materialsToSave);
        
        // Send the materials to the server
        fetch('save_suggested_materials.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                project_id: projectId,
                materials: materialsToSave
            })
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => {
                    throw new Error(err.message || 'Network response was not ok');
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('Server response:', data);
            if (data.success) {
                addNowText.textContent = 'Success!';
                // Show success modal then redirect to projects page
                const successModalEl = document.getElementById('successModal');
                if (successModalEl) {
                    const successMsgEl = document.getElementById('successMessage');
                    if (successMsgEl) {
                        successMsgEl.textContent = 'Materials added successfully!';
                    }
                    const successModal = new bootstrap.Modal(successModalEl);
                    successModal.show();
                }
                setTimeout(() => {
                    window.location.href = `projects.php`;
                }, 1500);
            } else {
                throw new Error(data.message || 'Failed to save materials');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            addNowText.textContent = 'Add Now';
            addNowBtn.disabled = false;
            addNowLoading.classList.add('d-none');
            
            // Show error feedback to user
            showFeedback('error', 'Failed to save materials: ' + (error.message || 'Unknown error'));
        });
    }
    
    // Show feedback modal
    function showFeedback(type, message) {
      console.log('showFeedback called with:', type, message);
      const modalEl = document.getElementById('feedbackModal');
      
      if (!modalEl) {
        console.error('Modal element not found!');
        return;
      }
      
      const modal = new bootstrap.Modal(modalEl);
      const icon = document.getElementById('feedbackIcon');
      const msg = document.getElementById('feedbackMessage');
      
      if (!icon || !msg) {
        console.error('Modal elements not found!', {icon, msg});
        return;
      }
      
      if (type === 'success') {
        icon.className = 'fas fa-check-circle text-success';
      } else if (type === 'error') {
        icon.className = 'fas fa-times-circle text-danger';
      } else {
        icon.className = 'fas fa-info-circle text-primary';
      }
      
      msg.textContent = message;
      
      try {
        modal.show();
        console.log('Modal should be visible now');
      } catch (e) {
        console.error('Error showing modal:', e);
      }
    }
    
    // Check for success/error messages in URL
    document.addEventListener('DOMContentLoaded', function() {
      const urlParams = new URLSearchParams(window.location.search);
      
      if (urlParams.has('success')) {
        const projectId = urlParams.get('project_id');
        const category = urlParams.get('category') || '';
        
        if (projectId) {
          // Show suggestion modal for new project with category
          showSuggestion(projectId, category);
          
          // Clean up URL
          const cleanUrl = window.location.pathname + 
            window.location.search
              .replace(/[?&]success=[^&]*/, '')
              .replace(/[?&]project_id=[^&]*/, '')
              .replace(/[?&]category=[^&]*/, '')
              .replace(/^&/, '?');
          window.history.replaceState({}, document.title, cleanUrl);
          return;
        }
        
        let message = 'Operation completed successfully!';
        
        if (urlParams.get('success') === 'add') {
          const projectId = urlParams.get('project_id');
          const category = urlParams.get('category') || '';
          message = 'Project has been added successfully!';
          if (projectId) {
            // Show suggestion modal instead of feedback for new projects
            showSuggestion(projectId, category);
            // Clean up URL without showing the default success message
            const cleanUrl = window.location.pathname + 
              window.location.search
                .replace(/[?&]success=[^&]*/, '')
                .replace(/[?&]project_id=[^&]*/, '')
                .replace(/[?&]category=[^&]*/, '')
                .replace(/^&/, '?');
            window.history.replaceState({}, document.title, cleanUrl);
            return;
          }
        } else if (urlParams.get('success') === 'archive') {
          message = 'Project has been archived successfully!';
        }
        
        showFeedback('success', message);
        
        // Clean up URL
        const cleanUrl = window.location.pathname + 
          window.location.search.replace(/[?&]success=[^&]*/, '').replace(/[?&]project_id=[^&]*/, '').replace(/^&/, '?');
        window.history.replaceState({}, document.title, cleanUrl);
      }
      
      if (urlParams.has('error')) {
        showFeedback('error', decodeURIComponent(urlParams.get('error')));
        
        // Clean up URL
        const cleanUrl = window.location.pathname + 
          window.location.search.replace(/[?&]error=[^&]*/, '').replace(/^&/, '?');
        window.history.replaceState({}, document.title, cleanUrl);
      }
    });
    </script>

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

        var el = document.getElementById("wrapper");
        var toggleButton = document.getElementById("menu-toggle");

        toggleButton.onclick = function () {
            el.classList.toggle("toggled");
        };
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Fetch clients with user_level 6
    function fetchClients() {
        fetch('get_clients.php')
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('clientSelect');
                select.innerHTML = '<option value="" selected disabled>Select a client</option>';
                data.forEach(client => {
                    const option = document.createElement('option');
                    option.value = client.email;
                    option.textContent = `${client.firstname} ${client.lastname} (${client.email})`;
                    select.appendChild(option);
                });
            })
            .catch(error => console.error('Error fetching clients:', error));
    }

    // Handle client type selection
    const newClientRadio = document.getElementById('newClient');
    const existingClientRadio = document.getElementById('existingClient');
    const newClientFields = document.getElementById('newClientFields');
    const existingClientFields = document.getElementById('existingClientFields');
            
            function toggleClientFields() {
                if (newClientRadio.checked) {
                    newClientFields.classList.remove('d-none');
                    existingClientFields.classList.add('d-none');
                    // Make new client fields required
                    document.getElementById('firstName').required = true;
                    document.getElementById('lastName').required = true;
                    document.getElementById('email').required = true;
                    document.getElementById('clientSelect').required = false;
                } else {
                    newClientFields.classList.add('d-none');
                    existingClientFields.classList.remove('d-none');
                    // Fetch clients when switching to existing client
                    fetchClients();
                    // Make existing client select required
                    document.getElementById('firstName').required = false;
                    document.getElementById('lastName').required = false;
                    document.getElementById('email').required = false;
                    document.getElementById('clientSelect').required = true;
                }
            }
            
            newClientRadio.addEventListener('change', toggleClientFields);
            existingClientRadio.addEventListener('change', toggleClientFields);
            
            // Initialize the form state
            toggleClientFields();
            
            // Add project name validation event listener
            const projectNameInput = document.getElementById('projectName');
            if (projectNameInput) {
                let projectNameTimeout;
                projectNameInput.addEventListener('input', function() {
                    clearTimeout(projectNameTimeout);
                    const projectName = this.value.trim();
                    
                    // Only check if project name is at least 3 characters
                    if (projectName.length >= 3) {
                        projectNameTimeout = setTimeout(() => {
                            checkProjectNameExists(projectName);
                        }, 500); // Debounce for 500ms
                    } else {
                        // Clear validation state if name is too short
                        this.classList.remove('is-invalid');
                        const feedback = document.getElementById('projectNameFeedback');
                        if (feedback) {
                            feedback.classList.remove('d-block');
                        }
                    }
                });
            }
            
            // Form validation
            function validateStep(step) {
                let isValid = true;
                const currentStepElement = document.getElementById(`step${step}`);
                
                if (!currentStepElement) {
                    console.error(`Step ${step} element not found`);
                    return false;
                }
                
                console.log(`Validating step ${step}`);
                
                // Get all required fields in the current step
                const requiredFields = currentStepElement.querySelectorAll('[required]');
                console.log(`Found ${requiredFields.length} required fields in step ${step}`);
                
                // Reset all invalid states first
                currentStepElement.querySelectorAll('.is-invalid').forEach(el => {
                    el.classList.remove('is-invalid');
                });
                
                // Check each required field
                requiredFields.forEach(field => {
                    console.log(`Checking field:`, field);
                    if (!field.value || (field.tagName === 'SELECT' && field.value === '')) {
                        console.log(`Field is invalid:`, field);
                        field.classList.add('is-invalid');
                        isValid = false;
                    }
                });
                
                // Special validation for client selection
                if (step === 1 && !newClientRadio.checked) {
                    const clientSelect = document.getElementById('clientSelect');
                    if (clientSelect && clientSelect.required && (!clientSelect.value || clientSelect.value === '')) {
                        console.log('Client selection is required');
                        clientSelect.classList.add('is-invalid');
                        isValid = false;
                    }
                }
                
                // Special validation for project name (step 2)
                if (step === 2) {
                    const projectNameInput = document.getElementById('projectName');
                    if (projectNameInput && projectNameInput.classList.contains('is-invalid')) {
                        console.log('Project name is duplicate');
                        isValid = false;
                    }
                }
                
                console.log(`Step ${step} validation result:`, isValid);
                return isValid;
            }
            
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Multi-step form functionality
            const form = document.getElementById('multiStepForm');
            const steps = document.querySelectorAll('.step');
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const submitBtn = document.getElementById('submitBtn');
            const progressBar = document.getElementById('formProgress');
            let currentStep = 1;
            const totalSteps = steps.length;

            // Initialize form
            updateForm();
            
            // Fetch clients when modal is shown
            const addProjectModal = document.getElementById('addProjectModal');
            if (addProjectModal) {
                addProjectModal.addEventListener('shown.bs.modal', function() {
                    // If existing client is selected, fetch clients
                    if (existingClientRadio.checked) {
                        fetchClients();
                    }
                });
            }

            // Function to check if email exists
            async function checkEmailExists(email) {
                if (!email) return false;
                
                // Basic email format validation
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    return false;
                }
                
                try {
                    const formData = new FormData();
                    formData.append('email', email);
                    
                    const response = await fetch('add_project.php?action=check_email', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    return data.exists === true;
                } catch (error) {
                    console.error('Error checking email:', error);
                    return false;
                }
            }
            
            // Next button click handler
            nextBtn.addEventListener('click', async function() {
                console.log('Next button clicked');
                console.log('Current step:', currentStep);
                
                // For step 1, check if email exists
                if (currentStep === 1) {
                    const emailInput = document.getElementById('email');
                    const clientType = document.querySelector('input[name="client_type"]:checked');
                    
                    // Only check for new clients
                    if (clientType && clientType.value === 'new' && emailInput) {
                        const email = emailInput.value.trim();
                        if (email) {
                            // Show loading state
                            const originalBtnText = nextBtn.innerHTML;
                            nextBtn.disabled = true;
                            nextBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Checking email...';
                            
                            try {
                                const response = await fetch('projects.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: 'action=check_email&email=' + encodeURIComponent(email)
                                });
                                const data = await response.json();
                                
                                if (data.success === false && data.user) {
                                    // Show error message with the user's name
                                    alert(`This email is already registered to ${data.user.name}. Please select "Existing Client" or use a different email.`);
                                    emailInput.focus();
                                    nextBtn.disabled = false;
                                    nextBtn.innerHTML = originalBtnText;
                                    return;
                                }
                            } catch (error) {
                                console.error('Error during email check:', error);
                                // Continue with normal validation if there's an error checking email
                            }
                            
                            // Restore button state
                            nextBtn.disabled = false;
                            nextBtn.innerHTML = originalBtnText;
                        }
                    }
                }
                
                // Proceed with normal validation
                if (validateStep(currentStep)) {
                    console.log('Validation passed, moving to next step');
                    currentStep++;
                    updateForm();
                } else {
                    console.log('Validation failed');
                    const form = document.getElementById('multiStepForm');
                    const invalidFields = form.querySelectorAll(':invalid');
                    console.log('Invalid fields:', invalidFields);
                    
                    // Focus on first invalid field
                    if (invalidFields.length > 0) {
                        invalidFields[0].focus();
                    }
                }
            });

            // Previous button click handler
            prevBtn.addEventListener('click', function() {
                currentStep--;
                updateForm();
            });

            // Toggle password visibility
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            });

            // Generate random password
            document.getElementById('generatePassword').addEventListener('click', function(e) {
                e.preventDefault();
                const randomString = Math.random().toString(36).slice(-8);
                passwordInput.value = randomString;
                // Ensure password is hidden after generation
                passwordInput.setAttribute('type', 'password');
                togglePassword.innerHTML = '<i class="fas fa-eye"></i>';
            });

            // Update form UI based on current step
            function updateForm() {
                // Hide all steps
                steps.forEach(step => step.classList.add('d-none'));
                
                // Show current step
                document.getElementById(`step${currentStep}`).classList.remove('d-none');
                
                // Update progress bar
                const progress = ((currentStep - 1) / (totalSteps - 1)) * 100;
                progressBar.style.width = `${progress}%`;
                progressBar.setAttribute('aria-valuenow', progress);
                
                // Update step indicators
                document.querySelectorAll('.step-indicator').forEach((indicator, index) => {
                    const stepNumber = parseInt(indicator.getAttribute('data-step'));
                    if (stepNumber < currentStep) {
                        indicator.classList.add('completed');
                        indicator.classList.remove('active');
                    } else if (stepNumber === currentStep) {
                        indicator.classList.add('active');
                        indicator.classList.remove('completed');
                    } else {
                        indicator.classList.remove('active', 'completed');
                    }
                });
                
                // Update button visibility
                if (currentStep === 1) {
                    prevBtn.style.display = 'none';
                    nextBtn.style.display = 'inline-block';
                    submitBtn.style.display = 'none';
                    nextBtn.innerHTML = 'Next <i class="fas fa-arrow-right ms-1"></i>';
                } else if (currentStep === totalSteps) {
                    prevBtn.style.display = 'inline-block';
                    nextBtn.style.display = 'none';
                    submitBtn.style.display = 'inline-block';
                } else {
                    prevBtn.style.display = 'inline-block';
                    nextBtn.style.display = 'inline-block';
                    submitBtn.style.display = 'none';
                    nextBtn.innerHTML = 'Next <i class="fas fa-arrow-right ms-1"></i>';
                }
                
                // Scroll to top of form
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            // Clear form when modal is closed
            document.getElementById('addProjectModal').addEventListener('hidden.bs.modal', function () {
                form.reset();
                currentStep = 1;
                updateForm();
                // Reset any error states
                document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                
                // Clear project name validation feedback
                const projectNameFeedback = document.getElementById('projectNameFeedback');
                if (projectNameFeedback) {
                    projectNameFeedback.classList.remove('d-block');
                }
            });

            document.addEventListener('DOMContentLoaded', function() {
                const form = document.getElementById('multiStepForm');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        // Show loading state
                        const submitBtn = document.getElementById('submitBtn');
                        const originalBtnText = submitBtn.innerHTML;
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating Project...';
                        
                        // Validate all steps before submission
                        let allValid = true;
                        for (let i = 1; i <= totalSteps; i++) {
                            if (!validateStep(i)) {
                                allValid = false;
                                // Go to the first invalid step
                                currentStep = i;
                                updateForm();
                                break;
                            }
                        }
                        
                                                 // Additional validation for project dates
                         if (allValid && !validateProjectDates()) {
                             allValid = false;
                         }
                         
                         // Function to validate project dates
                         function validateProjectDates() {
                             const finalStartDate = document.getElementById('final_start_date').value;
                             const finalEndDate = document.getElementById('final_end_date').value;
                             
                             if (!finalStartDate || !finalEndDate) {
                                 alert('Please select both start and end dates.');
                                 return false;
                             }
                             
                             const startDate = new Date(finalStartDate);
                             const endDate = new Date(finalEndDate);
                             const today = new Date();
                             today.setHours(0, 0, 0, 0);
                             
                             if (startDate < today) {
                                 alert('Start date cannot be in the past.');
                                 return false;
                             }
                             
                             if (endDate <= startDate) {
                                 alert('End date must be after start date.');
                                 return false;
                             }
                             
                             return true;
                         }
                        
                        if (allValid) {
                            // Submit form via AJAX
                            const formData = new FormData(form);
                            
                            fetch('projects.php', {
                                method: 'POST',
                                body: formData,
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    // Show success modal
                                    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                                    document.getElementById('successMessage').textContent = data.message;
                                    successModal.show();
                                    
                                    // Redirect after 2 seconds
                                    setTimeout(() => {
                                        window.location.href = data.redirect;
                                    }, 2000);
                                } else {
                                    // Show error modal
                                    const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                                    document.getElementById('errorMessage').textContent = data.message;
                                    errorModal.show();
                                    
                                    // If email exists, suggest using existing client
                                    if (data.user) {
                                        const existingClientRadio = document.getElementById('existingClient');
                                        if (existingClientRadio) {
                                            existingClientRadio.checked = true;
                                            toggleClientFields();
                                            
                                            // Select the existing client
                                            const clientSelect = document.getElementById('clientSelect');
                                            if (clientSelect) {
                                                clientSelect.value = data.user.id;
                                                clientSelect.dispatchEvent(new Event('change'));
                                            }
                                        }
                                    }
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                                document.getElementById('errorMessage').textContent = 'An error occurred while processing your request. Please try again.';
                                errorModal.show();
                            })
                            .finally(() => {
                                // Reset button state
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalBtnText;
                            });
                        } else {
                            // Reset button state if validation failed
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalBtnText;
                        }
                    });
                }
            });

            // Date fields are now free from validation
        });
    </script>
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
    <script>
            // Project name validation function
    function checkProjectNameExists(projectName) {
        if (!projectName || projectName.trim().length < 3) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'check_project_name');
        formData.append('project_name', projectName.trim());
        
        fetch('projects.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            const projectNameInput = document.getElementById('projectName');
            const projectNameFeedback = document.getElementById('projectNameFeedback');
            
            if (data.exists) {
                projectNameInput.classList.add('is-invalid');
                if (projectNameFeedback) {
                    projectNameFeedback.textContent = 'A project with this name already exists. Please use a different name.';
                    projectNameFeedback.classList.add('d-block');
                }
            } else {
                projectNameInput.classList.remove('is-invalid');
                if (projectNameFeedback) {
                    projectNameFeedback.classList.remove('d-block');
                }
            }
        })
        .catch(error => {
            console.error('Error checking project name:', error);
        });
    }
    
    // Email validation function
    function checkEmailExists(email) {
            const emailInput = document.getElementById('email');
            const emailFeedback = document.getElementById('emailFeedback');
            const emailChecking = document.getElementById('emailChecking');
            
            // Basic email format validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                emailInput.classList.remove('is-invalid');
                emailChecking.classList.add('d-none');
                return;
            }
            
            // Show checking indicator
            emailChecking.classList.remove('d-none');
            
            // Check email existence using the integrated backend
            const formData = new FormData();
            formData.append('email', email);
            formData.append('action', 'check_email');
            
            fetch('projects.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success === false && data.user) {
                    // Email exists - show error
                    emailInput.classList.add('is-invalid');
                    emailFeedback.textContent = `This email is already registered to ${data.user.name}. Please select "Existing Client" instead.`;
                    emailFeedback.classList.add('d-block');
                } else {
                    // Email is available
                    emailInput.classList.remove('is-invalid');
                    emailFeedback.classList.remove('d-block');
                }
            })
            .catch(error => {
                console.error('Error checking email:', error);
                // Don't block the user if there's an error checking the email
                emailInput.classList.remove('is-invalid');
                emailFeedback.classList.remove('d-block');
            })
            .finally(() => {
                emailChecking.classList.add('d-none');
            });
        }
        
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
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Date picker elements
            const datePicker3M = document.getElementById('datePicker3Months');
            const datePicker1W = document.getElementById('datePicker1Week');
            const categorySelect = document.getElementById('category');
            
            // 3-month date pickers
            const startDate3M = document.getElementById('start_date_3m');
            const endDate3M = document.getElementById('end_date_3m');
            
            // 1-week date pickers
            const startDate1W = document.getElementById('start_date_1w');
            const endDate1W = document.getElementById('end_date_1w');
            
            // Hidden fields for form submission
            const finalStartDate = document.getElementById('final_start_date');
            const finalEndDate = document.getElementById('final_end_date');
            
            // Set today's date as minimum for all date inputs
            const today = new Date().toISOString().split('T')[0];
            startDate3M.min = today;
            startDate1W.min = today;
            
            // Initialize 3-month date picker with default values
            const defaultEndDate3M = new Date();
            defaultEndDate3M.setMonth(defaultEndDate3M.getMonth() + 3);
            const defaultEndDate3MStr = defaultEndDate3M.toISOString().split('T')[0];
            endDate3M.min = defaultEndDate3MStr;
            endDate3M.value = defaultEndDate3MStr;
            
            // Initialize 1-week date picker with default values
            const defaultEndDate1W = new Date();
            defaultEndDate1W.setDate(defaultEndDate1W.getDate() + 7);
            const defaultEndDate1WStr = defaultEndDate1W.toISOString().split('T')[0];
            endDate1W.min = defaultEndDate1WStr;
            endDate1W.value = defaultEndDate1WStr;
            
            // Update hidden fields with initial values
            finalStartDate.value = today;
            finalEndDate.value = defaultEndDate3MStr;
            
            // Function to calculate minimum end date based on duration
            function getMinEndDate(startDate, isOneWeek) {
                const minEndDate = new Date(startDate);
                if (isOneWeek) {
                    minEndDate.setDate(minEndDate.getDate() + 7);
                } else {
                    minEndDate.setMonth(minEndDate.getMonth() + 3);
                }
                return minEndDate.toISOString().split('T')[0];
            }
            
            // Category change handler
            categorySelect.addEventListener('change', function() {
                const category = this.value;
                const isOneWeek = ['House Electrical', 'House Renovation', 'Building Electrical', 'Building Renovation'].includes(category);
                
                // Show/hide appropriate date pickers
                if (isOneWeek) {
                    datePicker3M.style.display = 'none';
                    datePicker1W.style.display = 'block';
                    // Update hidden fields with 1-week values
                    finalStartDate.value = startDate1W.value || today;
                    finalEndDate.value = endDate1W.value || defaultEndDate1WStr;
                } else {
                    datePicker3M.style.display = 'block';
                    datePicker1W.style.display = 'none';
                    // Update hidden fields with 3-month values
                    finalStartDate.value = startDate3M.value || today;
                    finalEndDate.value = endDate3M.value || defaultEndDate3MStr;
                }
            });
            
            // 3-month date picker event listeners
            startDate3M.addEventListener('change', function() {
                const selectedDate = new Date(this.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                if (selectedDate < today) {
                    this.value = '';
                    alert('Start date cannot be in the past');
                    return;
                }
                
                const minEndDate = getMinEndDate(selectedDate, false);
                endDate3M.min = minEndDate;
                
                if (!endDate3M.value || new Date(endDate3M.value) < new Date(minEndDate)) {
                    endDate3M.value = minEndDate;
                }
                
                // Update hidden fields
                finalStartDate.value = this.value;
                finalEndDate.value = endDate3M.value;
            });
            
            endDate3M.addEventListener('change', function() {
                if (startDate3M.value) {
                    const minEndDate = getMinEndDate(new Date(startDate3M.value), false);
                    
                    if (new Date(this.value) < new Date(minEndDate)) {
                        alert('End date must be at least 3 months after start date');
                        this.value = minEndDate;
                    }
                    
                    // Update hidden fields
                    finalStartDate.value = startDate3M.value;
                    finalEndDate.value = this.value;
                }
            });
            
            // 1-week date picker event listeners
            startDate1W.addEventListener('change', function() {
                const selectedDate = new Date(this.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                if (selectedDate < today) {
                    this.value = '';
                    alert('Start date cannot be in the past');
                    return;
                }
                
                const minEndDate = getMinEndDate(selectedDate, true);
                endDate1W.min = minEndDate;
                
                if (!endDate1W.value || new Date(endDate1W.value) < new Date(minEndDate)) {
                    endDate1W.value = minEndDate;
                }
                
                // Update hidden fields
                finalStartDate.value = this.value;
                finalEndDate.value = endDate1W.value;
            });
            
            endDate1W.addEventListener('change', function() {
                if (startDate1W.value) {
                    const minEndDate = getMinEndDate(new Date(startDate1W.value), true);
                    
                    if (new Date(this.value) < new Date(minEndDate)) {
                        alert('End date must be at least 1 week after start date');
                        this.value = minEndDate;
                    }
                    
                    // Update hidden fields
                    finalStartDate.value = startDate1W.value;
                    finalEndDate.value = this.value;
                }
            });
        });
    </script>
  </body>
</html>