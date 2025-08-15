<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 6) {
    header("Location: ../login.php");
    exit();
}
include_once "../config.php";
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}
$userid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$user_email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
$user_firstname = isset($_SESSION['firstname']) ? $_SESSION['firstname'] : '';
$user_lastname = isset($_SESSION['lastname']) ? $_SESSION['lastname'] : '';
$user_name = trim($user_firstname . ' ' . $user_lastname);
$current_page = basename($_SERVER['PHP_SELF']);
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

$projects = [];
$res = mysqli_query($con, "SELECT project_id, project, start_date, deadline 
                          FROM projects 
                          WHERE client_email='$user_email' 
                          AND client_archived=0 
                          AND (client_delete IS NULL OR client_delete=0)
                          AND status != 'Finished' 
                          ORDER BY project_id DESC");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $projects[] = $row;
    }
}
function monthIndex($date) {
    return (int)date('n', strtotime($date)) - 1;
}
function yearOf($date) {
    return (int)date('Y', strtotime($date));
}
$currentYear = date('Y');
$months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <link rel="stylesheet" href="client_styles.css" />
    <title>Client Gantt Chart</title>
    <style>
        /* General Styles */
        body {
            background-color: #f8f9fa;
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: 10px;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-bottom: 1.5rem;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
        }

        /* Status Badges */
        .badge {
            padding: 0.5em 0.8em;
            font-weight: 500;
        }

        /* Progress Bar */
        .progress {
            border-radius: 10px;
            background-color: #e9ecef;
        }

        /* Sidebar Styles */
        #sidebar-wrapper {
            background: linear-gradient(180deg, #4e73df 0%, #224abe 100%);
            min-height: 100vh;
            transition: all 0.3s;
        }

        .sidebar-profile-img {
            border: 3px solid rgba(255, 255, 255, 0.2);
        }

        .list-group-item {
            border: none;
            padding: 0.8rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
            transition: all 0.3s;
        }

        .list-group-item:hover,
        .list-group-item.active {
            background-color: rgba(255, 255, 255, 0.1) !important;
            color: #fff !important;
            border-left: 4px solid #fff;
        }

        /* Navbar Styles */
        .navbar {
            background-color: transparent !important;
            box-shadow: none !important;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            #sidebar-wrapper {
                margin-left: -15rem;
            }
            #wrapper.toggled #sidebar-wrapper {
                margin-left: 0;
            }
            #page-content-wrapper {
                min-width: 100%;
                width: 100%;
            }
        }
    </style>
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
                <a href="clients_dashboard.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'client_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>Dashboard
                </a>
                <a href="client_projects.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'client_projects.php' ? 'active' : ''; ?>">
                    <i class="fas fa-project-diagram"></i>Projects
                </a>
                <a href="client_gantt.chart.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'client_gantt.chart.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar"></i>Gantt Chart
                </a>
            </div>
        </div>
        <!-- /#sidebar-wrapper -->

        <!-- Page Content -->
        <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-align-left primary-text fs-4 me-3" id="menu-toggle"></i>
                    <h2 class="fs-2 m-0">Dashboard</h2>
                </div>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                    <?php 
                    include 'clients_notification.php'; 
                    
                    // Function to count unread messages
                    function countUnreadMessages($con, $userId) {
                        $query = "SELECT COUNT(*) as count FROM pm_client_messages WHERE receiver_id = ? AND is_read = 0";
                        $stmt = $con->prepare($query);
                        $stmt->bind_param("i", $userId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $row = $result->fetch_assoc();
                        $stmt->close();
                        return $row['count'];
                    }
                    
                    // Get total unread messages
                    $unreadCount = countUnreadMessages($con, $_SESSION['user_id']);
                    ?>
                    <li class="nav-item ms-2">
                        <a class="nav-link position-relative" href="client_messenger.php" title="Messages">
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
                                <li><a class="dropdown-item" href="client_profile.php">Profile</a></li>
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal">Change Password</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="container-fluid  px-2 px-md-4 py-3">
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex flex-column align-items-center mb-3">
                            <div class="w-100 d-flex justify-content-center align-items-center mb-3">
                                <h4 class="mb-0 text-center">Gantt Chart (by Month, <?php echo $currentYear; ?>)</h4>
                            </div>
                            <hr class="w-100 my-2">
                            <div class="w-100 d-flex justify-content-end mb-3">
                                <div class="btn-group" role="group">
                                    <button class="btn btn-outline-danger" id="exportPdfBtn">
                                        <i class="fas fa-file-pdf me-1"></i> Export as PDF
                                    </button>
                                    <button class="btn btn-outline-primary" id="exportImgBtn">
                                        <i class="fas fa-image me-1"></i> Export as Image
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php if (count($projects) === 0): ?>
                            <div class="alert alert-info mb-0">No projects found for your account.</div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle text-center table-hover" id="ganttTable">
                                <thead class="table-light">
                                    <tr>
                                        <th class="w-25">Project</th>
                                        <?php foreach ($months as $m): ?>
                                            <th class="text-nowrap"><?php echo $m; ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $p): ?>
                                    <tr>
                                        <td class="text-start fw-bold"><?php echo htmlspecialchars($p['project']); ?></td>
                                        <?php
                                            $startIdx = monthIndex($p['start_date']);
                                            $endIdx = monthIndex($p['deadline']);
                                            $startYear = yearOf($p['start_date']);
                                            $endYear = yearOf($p['deadline']);
                                            // Calculate bar start and end for this year
                                            $barStart = ($startYear < $currentYear) ? 0 : $startIdx;
                                            $barEnd = ($endYear > $currentYear) ? 11 : $endIdx;
                                            // Left empty cells
                                            for ($i = 0; $i < $barStart; $i++) echo '<td class="p-0"></td>';
                                            // Bar cell
                                            $colspan = $barEnd - $barStart + 1;
                                            echo '<td colspan="' . $colspan . '" class="p-0 align-middle">';
                                            echo '<div class="h-100 w-100 bg-primary rounded d-flex align-items-center justify-content-center">';
                                            $start_fmt = date('m-d-Y', strtotime($p['start_date']));
                                            $end_fmt = date('m-d-Y', strtotime($p['deadline']));
                                            echo '<span class="text-white small fw-bold">' . $start_fmt . ' to ' . $end_fmt . '</span>';
                                            echo '</div>';
                                            echo '</td>';
                                            // Right empty cells
                                            for ($i = $barEnd + 1; $i < 12; $i++) echo '<td class="p-0"></td>';
                                        ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /#page-content-wrapper -->
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        var el = document.getElementById("wrapper");
        var toggleButton = document.getElementById("menu-toggle");

        toggleButton.onclick = function () {
            el.classList.toggle("toggled");
        };
    </script>
    <?php if (count($projects) > 0): ?>
    <script>
const ctx = document.getElementById('ganttChart').getContext('2d');
const data = {
    labels: <?php echo json_encode(array_column($projects, 'project')); ?>,
    datasets: [{
        label: 'Project',
        data: <?php echo json_encode(array_map(function($p, $i) { return 10 + $i * 5; }, $projects, array_keys($projects))); ?>,
        backgroundColor: 'rgba(54, 162, 235, 1)',
        borderColor: 'rgba(54, 162, 235, 1)',
        borderWidth: 1,
        borderRadius: 8,
        barPercentage: 0.7,
        categoryPercentage: 0.7,
    }]
};
const config = {
    type: 'bar',
    data: data,
    options: {
        indexAxis: 'y',
        plugins: {
            legend: { display: false },
            title: { display: false }
        },
        scales: {
            x: { display: false, min: 0 },
            y: { ticks: { color: '#222', font: { size: 16, weight: 'bold' } } }
        },
        responsive: true,
        maintainAspectRatio: false,
    }
};
new Chart(ctx, config);
</script>
<?php endif; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<!-- Export Confirmation Modal -->
<div class="modal fade" id="exportConfirmModal" tabindex="-1" aria-labelledby="exportConfirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exportConfirmModalLabel">Export</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="exportConfirmMsg">Are you sure you want to export?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="#" id="confirmExportBtn" class="btn btn-danger">Export</a>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var exportType = '';
  var exportImgBtn = document.getElementById('exportImgBtn');
  var exportPdfBtn = document.getElementById('exportPdfBtn');
  var confirmExportBtn = document.getElementById('confirmExportBtn');
  var exportConfirmMsg = document.getElementById('exportConfirmMsg');
  var exportConfirmModal = new bootstrap.Modal(document.getElementById('exportConfirmModal'));

  if (exportImgBtn) {
    exportImgBtn.addEventListener('click', function(e) {
      e.preventDefault();
      exportType = 'image';
      exportConfirmMsg.textContent = 'Are you sure you want to export the Gantt chart as an image?';
      exportConfirmModal.show();
    });
  }
  if (exportPdfBtn) {
    exportPdfBtn.addEventListener('click', function(e) {
      e.preventDefault();
      exportType = 'pdf';
      exportConfirmMsg.textContent = 'Are you sure you want to export the Gantt chart as PDF?';
      exportConfirmModal.show();
    });
  }
  if (confirmExportBtn) {
    confirmExportBtn.addEventListener('click', function(e) {
      e.preventDefault();
      exportConfirmModal.hide();
      setTimeout(function() {
        if (exportType === 'image') {
          html2canvas(document.getElementById('ganttTable')).then(function(canvas) {
            var link = document.createElement('a');
            link.download = 'gantt_chart.png';
            link.href = canvas.toDataURL();
            link.click();
            setTimeout(function() { location.reload(); }, 1000);
          });
        } else if (exportType === 'pdf') {
          html2canvas(document.getElementById('ganttTable')).then(function(canvas) {
            var imgData = canvas.toDataURL('image/png');
            var pdf = new window.jspdf.jsPDF({orientation: 'landscape'});
            var pageWidth = pdf.internal.pageSize.getWidth();
            var pageHeight = pdf.internal.pageSize.getHeight();
            var imgWidth = pageWidth - 20;
            var imgHeight = canvas.height * imgWidth / canvas.width;
            pdf.addImage(imgData, 'PNG', 10, 10, imgWidth, imgHeight);
            pdf.save('gantt_chart.pdf');
            setTimeout(function() { location.reload(); }, 1000);
          });
        }
      }, 300);
    });
  }
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
</body>

</html>