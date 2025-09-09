<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 5) {
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

// Handle AJAX password change (like pm_profile.php)
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

// Fetch contacts from database
$contacts = [];
$current_user_id = $_SESSION['user_id'];

// First, get all users who are either suppliers, admins, or procurement with their latest message timestamp
$query = "SELECT 
    u.id, 
    u.firstname, 
    u.lastname, 
    u.email, 
    u.user_level, 
    u.profile_path,
    CASE WHEN u.user_level = 5 THEN 1 ELSE 0 END as is_supplier,
    COALESCE(
        (SELECT MAX(date_sent) FROM pm_supplier_messages 
         WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id)
         LIMIT 1),
        '1970-01-01 00:00:00'
    ) as last_supplier_message,
    COALESCE(
        (SELECT MAX(date_sent) FROM pm_procurement_messages 
         WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id)
         LIMIT 1),
        '1970-01-01 00:00:00'
    ) as last_procurement_message
FROM users u
WHERE u.is_verified = 1
AND u.id != ? 
AND u.user_level IN (4, 5)";

// Get unread counts from all message tables in separate queries
$unread_query = "
    SELECT 
        sender_id,
        COUNT(*) as unread_count
    FROM pm_supplier_messages 
    WHERE receiver_id = ? AND is_read = 0
    GROUP BY sender_id
    
    UNION ALL
    
    SELECT 
        sender_id,
        COUNT(*) as unread_count
    FROM pm_procurement_messages 
    WHERE receiver_id = ? AND is_read = 0
    GROUP BY sender_id
";

// First, get all contacts with their latest message timestamps
$contacts = [];
if ($stmt = $con->prepare($query)) {
    // We need to bind the current_user_id for each placeholder in the query
    $stmt->bind_param("iiiii", 
        $current_user_id, $current_user_id,  // supplier messages
        $current_user_id, $current_user_id,  // procurement messages
        $current_user_id                     // main query condition
    );
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Get the most recent message timestamp from all message types
        $last_timestamps = [
            strtotime($row['last_supplier_message']),
            strtotime($row['last_procurement_message'])
        ];
        $latest_timestamp = max($last_timestamps);
        
        $contacts[$row['id']] = [
            'id' => $row['id'],
            'name' => $row['firstname'] . ' ' . $row['lastname'],
            'email' => $row['email'],
            'user_level' => $row['user_level'],
            'is_supplier' => $row['is_supplier'],
            'profile_path' => $row['profile_path'],
            'unread_count' => 0,  // Initialize to 0, will be updated below
            'last_message_time' => $latest_timestamp
        ];
    }
    $stmt->close();
}

// Then get unread counts
if ($unreadStmt = $con->prepare($unread_query)) {
    // We have 2 placeholders in the query, all using the same user ID
    $unreadStmt->bind_param("ii", $current_user_id, $current_user_id);
    $unreadStmt->execute();
    $unreadResult = $unreadStmt->get_result();
    
    // Initialize all unread counts to 0 first
    foreach ($contacts as &$contact) {
        $contact['unread_count'] = 0;
    }
    unset($contact); // Break the reference
    
    // Sum up unread counts from all message tables
    while ($unreadRow = $unreadResult->fetch_assoc()) {
        $sender_id = $unreadRow['sender_id'];
        if (isset($contacts[$sender_id])) {
            $contacts[$sender_id]['unread_count'] += (int)$unreadRow['unread_count'];
        }
    }
    $unreadStmt->close();
}

// Sort contacts by last message time (most recent first)
usort($contacts, function($a, $b) {
    return $b['last_message_time'] <=> $a['last_message_time'];
});


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
    <link rel="stylesheet" href="supplier_style.css" />
    <title>Project Manager Messenger</title>
</head>

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
                <a href="supplier_dashboard.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'supplier_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>Dashboard
                </a>
                <a href="supplier_materials.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'supplier_materials.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cubes"></i>Materials
                </a>
                <a href="supplier_category.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'supplier_category.php' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i>Category
                </a>
                <a href="supplier_approval.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'supplier_approval.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-check"></i>Order Management
                </a>
                <a href="supplier_order_history.php" class="list-group-item list-group-item-action bg-transparent second-text <?php echo $current_page == 'supplier_order_history.php' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i>Order History
                </a>
            </div>
        </div>
        <!-- /#sidebar-wrapper -->

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-align-left primary-text fs-4 me-3" id="menu-toggle"></i>
                    <h2 class="fs-2 m-0">Supplier Messenger</h2>
                </div>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                    <?php 
                    include 'supplier_notification.php'; 
                    
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
                    $tables = ['pm_supplier_messages', 'pm_procurement_messages'];
                    $unreadCount = countUnreadMessages($con, $tables, $_SESSION['user_id']);
                    ?>
                    <li class="nav-item ms-2">
                        <a class="nav-link position-relative" href="supplier_messenger.php" title="Messages">
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
                                <li><a class="dropdown-item" href="supplier_profile.php">Profile</a></li>
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal">Change Password</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="container-fluid py-4">
            <div class="card shadow-sm">
            <div class="card-header text-white" style="background-color: #009d63; border-color: #009d63;">
                <h5 class="mb-0"><i class="fas fa-comments me-2"></i>Messenger</h5>
            </div>
            <div class="card-body p-0">
                <div class="row g-0 h-100">
                    <!-- Left Column: People List -->
                    <div class="col-md-4 col-lg-3 border-end d-flex flex-column" style="height: 80vh;">
                        <div class="border-bottom p-3">
                            <h6 class="mb-2 text-uppercase fw-bold text-center fs-5 mt-2">Contacts</h6>
                        </div>
                        <div class="flex-grow-1 overflow-auto" style="overflow-y: auto; max-height: calc(80vh - 120px);">
                            <style>
                                /* Custom scrollbar styling */
                                .flex-grow-1::-webkit-scrollbar {
                                    width: 6px;
                                }
                                .flex-grow-1::-webkit-scrollbar-track {
                                    background: #f1f1f1;
                                    border-radius: 10px;
                                }
                                .flex-grow-1::-webkit-scrollbar-thumb {
                                    background: #888;
                                    border-radius: 10px;
                                }
                                .flex-grow-1::-webkit-scrollbar-thumb:hover {
                                    background: #555;
                                }
                                /* Contact item hover and active states */
                                .contact-item {
                                    transition: background-color 0.2s;
                                }
                                .contact-item:hover {
                                    background-color: #f8f9fa;
                                }
                                .contact-item.active {
                                    background-color: #e9ecef;
                                    border-left: 3px solid #0d6efd;
                                }
                                .contact-email {
                                    font-size: 0.75rem;
                                    color: #6c757d;
                                    white-space: nowrap;
                                    overflow: hidden;
                                    text-overflow: ellipsis;
                                    max-width: 200px;
                                }
                            </style>
                          
                            <?php if (!empty($contacts)): ?>
                                <?php foreach ($contacts as $contact): ?>
                                    <div class="d-flex align-items-center p-3 border-bottom contact-item" 
                                         style="cursor: pointer;"
                                         data-user-id="<?php echo htmlspecialchars($contact['id']); ?>"
                                         data-last-message-time="<?php echo !empty($contact['last_message_time']) ? $contact['last_message_time'] : '0'; ?>"
                                         data-user-level="<?php echo $contact['user_level']; ?>"
                                         onclick="selectContact('<?php echo htmlspecialchars($contact['id']); ?>', 
                                                             '<?php echo htmlspecialchars($contact['name']); ?>',
                                                             '<?php echo htmlspecialchars($contact['email']); ?>',
                                                             '<?php echo $contact['user_level']; ?>',
                                                             '<?php echo !empty($contact['profile_path']) ? '../uploads/' . htmlspecialchars($contact['profile_path']) : ''; ?>')">
                                        <?php 
                                        if (!empty($contact['profile_path'])): 
                                            $profile_img = '../uploads/' . htmlspecialchars($contact['profile_path']);
                                        ?>
                                            <img src="<?php echo $profile_img; ?>" class="rounded-circle me-3" alt="User" width="40" height="40" style="object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                <i class="fas fa-user text-muted" style="font-size: 1.25rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <span class="fw-bold contact-name"><?php echo htmlspecialchars($contact['name']); ?></span>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($contact['unread_count'] > 0): ?>
                                                        <span class="badge bg-danger rounded-pill ms-2"><?php echo $contact['unread_count']; ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($contact['is_supplier']): ?>
                                                        <span class="badge rounded-circle ms-2" style="width: 10px; height: 10px; margin-top: 1px; background-color: #009d63;" title="Online"></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="contact-email text-truncate" title="<?php echo htmlspecialchars($contact['email']); ?>">
                                                <?php echo htmlspecialchars($contact['email']); ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php 
                                                    if ($contact['is_supplier']) {
                                                        echo 'Supplier';
                                                    } elseif ($contact['user_level'] == 2) {
                                                        echo 'Administrator';
                                                    } else {
                                                        echo 'Procurement Officer';
                                                    }
                                                ?>
                                            </small>
                                        </div>
                                        <?php if ($contact['is_supplier']): ?>
                                            <span class="badge bg-success rounded-circle ms-auto" style="width: 10px; height: 10px;" title="Online"></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center p-4 text-muted">
                                    No contacts found
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="p-3 border-top">
                            <div class="input-group">
                                <input type="text" 
                                       class="form-control" 
                                       id="contactSearch" 
                                       placeholder="Search contact"
                                       onkeyup="filterContacts()">
                                <button class="btn btn-success" type="button" style="background-color: #009d63; border-color: #009d63;">
                                    <i class="fas fa-search text-white"></i>
                                </button>
                            </div>
                        </div>
                        <style>
                            #contactSearch:focus {
                                border-color: #009d63;
                                box-shadow: 0 0 0 0.2rem rgba(0, 157, 99, 0.25);
                            }
                            .contact-item {
                                transition: all 0.3s ease;
                            }
                            .contact-item.hidden {
                                display: none !important;
                            }
                        </style>
                    </div>

                    <!-- Middle Column: Chat Box -->
                    <div class="col-md-8 col-lg-9 d-flex flex-column" style="height: 80vh;">
                        <!-- Chat Header -->
                        <div class="border-bottom p-3 d-flex align-items-center justify-content-between bg-light">
                            <div class="d-flex align-items-center">
                                <div id="chatContactImage" class="me-3">
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; overflow: hidden;">
                                        <i class="fas fa-user text-muted" style="font-size: 1.25rem;" id="defaultAvatarIcon"></i>
                                        <img src="" alt="Profile" id="contactProfileImage" style="display: none; width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold" id="chatContactName">Select Contact</h6>
                                    <small class="text-muted d-block" id="chatContactEmail">Select a contact to start chatting</small>
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-link text-muted p-0" type="button" id="chatOptions" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item text-danger delete-conversation" href="#">Delete Conversation</a></li>
                                </ul>
                            </div>
                        </div>

                        <!-- Chat Messages -->
                        <div id="chatMessages" class="d-flex flex-column p-3 overflow-auto" style="height: calc(100vh - 300px);">
                            <div id="messageContainer" class="d-flex flex-column">
                                <!-- Messages will be inserted here -->
                            </div>
                        </div>

                        <!-- Chat Input -->
                        <div class="p-3 border-top bg-white">
                            <form class="d-flex align-items-center" id="chatForm" autocomplete="off" onsubmit="return false;">
                            
                                <input type="file" 
                                       id="fileInput" 
                                       style="display: none;" 
                                       accept="image/*,.pdf"
                                       onchange="handleFileSelect(event)">
                                <button type="button" 
                                        class="btn btn-link text-muted me-2" 
                                        onclick="document.getElementById('fileInput').click()"
                                        title="Attach file">
                                    <i class="fas fa-paperclip fa-lg"></i>
                                </button>
                                <div class="position-relative flex-grow-1 me-2">
                                    <input type="text" 
                                           class="form-control rounded-pill" 
                                           placeholder="Type a message..." 
                                           id="chatInput" 
                                           autocomplete="off"
                                           style="padding-right: 30px;">
                                    <div id="filePreview" class="position-absolute top-50 end-0 translate-middle-y me-3 d-none">
                                        <i class="fas fa-file text-primary"></i>
                                    </div>
                                </div>
                                <button type="submit" 
                                        class="btn btn-primary rounded-circle" 
                                        style="width: 40px; height: 40px;"
                                        id="sendButton">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>

            </div>

        </div>
    </div>
    <!-- /#page-content-wrapper -->
    </div>

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

    <!-- Export PDF Confirmation Modal -->
    <div class="modal fade" id="exportDashboardPdfModal" tabindex="-1" aria-labelledby="exportDashboardPdfModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exportDashboardPdfModalLabel">Export as PDF</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to export the dashboard as PDF for the selected date range?</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" id="confirmExportDashboardPdf" class="btn btn-danger">Export</button>
          </div>
        </div>
      </div>
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
<script>
    // Function to show alerts to the user
    function showAlert(message, type = 'info') {
        // Remove any existing alerts first
        const existingAlert = document.querySelector('#alert-container');
        if (existingAlert) {
            existingAlert.remove();
        }
        
        // Create alert element
        const alertDiv = document.createElement('div');
        alertDiv.id = 'alert-container';
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.zIndex = '9999';
        alertDiv.style.maxWidth = '400px';
        alertDiv.role = 'alert';
        
        // Add close button
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        // Add to body
        document.body.appendChild(alertDiv);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alertDiv);
            bsAlert.close();
        }, 5000);
    }
    
    // Add event listener for Enter key in chat input
    document.addEventListener('DOMContentLoaded', function() {
        const chatInput = document.getElementById('chatInput');
        const chatForm = document.getElementById('chatForm');
        
        if (chatInput && chatForm) {
            chatInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault(); // Prevent form submission
                    const sendButton = document.getElementById('sendButton');
                    if (sendButton && !sendButton.disabled) {
                        // Trigger click on the send button
                        sendButton.click();
                    }
                }
            });
        }
    });

    // Function to enable/disable send button based on input
    function updateSendButton() {
        const messageInput = document.getElementById('chatInput');
        const sendButton = document.getElementById('sendButton');
        if (messageInput && sendButton) {
            sendButton.disabled = messageInput.value.trim() === '' && !selectedFile;
        }
    }

    // Initialize chat functionality when the page loads
    document.addEventListener('DOMContentLoaded', function() {
        const chatForm = document.getElementById('chatForm');
        const messageInput = document.getElementById('chatInput');
        
        // Handle form submission
        if (chatForm) {
            chatForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const activeContact = document.querySelector('.contact-item.active');
                if (!activeContact) {
                    showAlert('Please select a contact first', 'warning');
                    return;
                }
                
                const receiverId = activeContact.getAttribute('data-user-id');
                const userLevel = activeContact.getAttribute('data-user-level');
                const messageText = messageInput.value.trim();
                
                if (!messageText && !selectedFile) {
                    showAlert('Please enter a message or select a file', 'warning');
                    return;
                }
                
                const sendButton = document.getElementById('sendButton');
                const originalButtonHTML = sendButton ? sendButton.innerHTML : '';
                
                // Disable send button and show loading state
                if (sendButton) {
                    sendButton.disabled = true;
                    sendButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
                }
                
                try {
                    let filePath = '';
                    let message = messageText;
                    
                    // Upload file if selected
                    if (selectedFile) {
                        const uploadResult = await uploadFile();
                        if (uploadResult && uploadResult.file_path) {
                            filePath = uploadResult.file_path;
                            if (message) {
                                message += '\n';
                            }
                            message += `[File: ${filePath}]`;
                        }
                    }
                    
                    // Send message
                    const formData = new FormData();
                    formData.append('receiver_id', receiverId);
                    formData.append('message', message);
                    formData.append('table', getMessageTable(userLevel));
                    
                    const response = await fetch('send_message.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    });
                    
                    if (!response.ok) {
                        throw new Error('Failed to send message');
                    }
                    
                    const result = await response.json();
                    
                    if (!result.success) {
                        throw new Error(result.error || 'Failed to send message');
                    }
                    
                    // Clear input and reset file selection
                    messageInput.value = '';
                    if (selectedFile) {
                        document.getElementById('fileInput').value = '';
                        document.getElementById('filePreview').classList.add('d-none');
                        selectedFile = null;
                    }
                    
                    // Reload messages
                    loadMessages(receiverId, userLevel);
                    
                } catch (error) {
                    console.error('Error sending message:', error);
                    showAlert('Failed to send message: ' + error.message, 'danger');
                } finally {
                    // Re-enable send button
                    if (sendButton) {
                        sendButton.disabled = false;
                        sendButton.innerHTML = originalButtonHTML;
                    }
                }
            });
        }
        
        // Enable/disable send button based on input
        if (messageInput) {
            messageInput.addEventListener('input', updateSendButton);
            
            // Handle Enter key to send message
            messageInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    const sendButton = document.getElementById('sendButton');
                    if (sendButton && !sendButton.disabled) {
                        chatForm.dispatchEvent(new Event('submit'));
                    }
                }
            });
        }
    });

    function selectContact(userId, userName, userEmail, userLevel, userImage = '') {
        try {
            console.log('Selected contact:', { userId, userName, userEmail, userLevel, userImage });
            
            // Clear search input and show all contacts
            const searchInput = document.getElementById('contactSearch');
            if (searchInput) {
                searchInput.value = '';
                filterContacts(); // Show all contacts again
            }
            
            // Clear message input and reset file selection
            const messageInput = document.getElementById('chatInput');
            if (messageInput) {
                messageInput.value = '';
            }
            
            // Reset file input if exists
            const fileInput = document.getElementById('fileInput');
            if (fileInput) {
                fileInput.value = '';
            }
            
            // Hide file preview if visible
            const filePreview = document.getElementById('filePreview');
            if (filePreview) {
                filePreview.classList.add('d-none');
            }
            
            // Reset selected file
            selectedFile = null;

            // Update active state
            document.querySelectorAll('.contact-item').forEach(item => {
                item.classList.remove('active');
            });

            if (event && event.currentTarget) {
                event.currentTarget.classList.add('active');
                // Store the user level as a data attribute
                event.currentTarget.setAttribute('data-user-level', userLevel);
            }

            // Update contact info in the chat header
            const contactName = document.getElementById('chatContactName');
            const contactEmail = document.getElementById('chatContactEmail');
            const defaultAvatar = document.getElementById('defaultAvatarIcon');
            const profileImage = document.getElementById('contactProfileImage');

            if (contactName) contactName.textContent = userName;
            if (contactEmail) contactEmail.textContent = userEmail;

            if (userImage && userImage.trim() !== '') {
                profileImage.src = userImage;
                profileImage.style.display = 'block';
                if (defaultAvatar) defaultAvatar.style.display = 'none';
            } else {
                profileImage.style.display = 'none';
                if (defaultAvatar) defaultAvatar.style.display = 'block';
            }

            // Enable chat input and send button
            const chatInput = document.getElementById('chatInput');
            const submitButton = document.querySelector('#chatForm button[type="submit"], #chatForm button[type="button"]');
            if (chatInput) chatInput.disabled = false;
            if (submitButton) submitButton.disabled = false;

            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }

            // Load messages for the selected user with their level
            loadMessages(userId, userLevel);

        } catch (error) {
            console.error('Error in selectContact:', error);
        }
    }

    function filterContacts() {
        const searchTerm = document.getElementById('contactSearch').value.toLowerCase();
        const contactItems = document.querySelectorAll('.contact-item');

        contactItems.forEach(item => {
            const name = item.querySelector('.contact-name')?.textContent?.toLowerCase() || '';
            const email = item.querySelector('.contact-email')?.textContent?.toLowerCase() || '';

            if (name.includes(searchTerm) || email.includes(searchTerm)) {
                item.classList.remove('hidden');
            } else {
                item.classList.add('hidden');
            }
        });

        // Show message if no contacts found
        const noContactsMessage = document.querySelector('.no-contacts-message');
        const visibleContacts = document.querySelectorAll('.contact-item:not(.hidden)').length;

        if (visibleContacts === 0) {
            if (!noContactsMessage) {
                const message = document.createElement('div');
                message.className = 'text-center p-4 text-muted no-contacts-message';
                message.textContent = 'No matching contacts found';
                document.querySelector('.flex-grow-1').appendChild(message);
            }
        } else if (noContactsMessage) {
            noContactsMessage.remove();
        }
    }

    // Close any open dropdowns when clicking outside
    document.addEventListener('click', function (event) {
        if (!event.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    });

    // File upload handling
    let selectedFile = null;

// 1. File selection and preview function (called on file input change)
    function handleFileSelect(event) {
        const file = event.target.files[0];
        if (!file) return;
        
        // Check file type
        const fileType = file.type;
        const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        
        if (!validTypes.includes(fileType)) {
            alert('Only JPG, PNG, GIF, and PDF files are allowed');
            return;
        }
        
        // Check file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            alert('File size exceeds 5MB limit');
            return;
        }
        
        selectedFile = file;
        const chatInput = document.getElementById('chatInput');
        const filePreview = document.getElementById('filePreview');
        
        // Show file name in input
        chatInput.value = '';
        chatInput.placeholder = file.name;
        filePreview.classList.remove('d-none');
        
        // Update send button state
        updateSendButton();
        
        // If it's an image, show a preview
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                filePreview.innerHTML = `<img src="${e.target.result}" style="max-height: 24px; max-width: 24px; border-radius: 4px;" title="${file.name}">`;
            };
            reader.readAsDataURL(file);
        } else {
            filePreview.innerHTML = '<i class="fas fa-file-pdf text-danger"></i>';
        }
    }

    
  // 2. File upload to server function (called before sending a message)
    async function uploadFile() {
        if (!selectedFile) return null;
        
        const formData = new FormData();
        formData.append('file', selectedFile);
        
        try {
            const response = await fetch('../includes/upload_file.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin' // Include session cookies
            });
            
            // First check if the response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Unexpected response:', text);
                throw new Error('Server returned an invalid response');
            }
            
            const result = await response.json();
            
            if (!response.ok) {
                throw new Error(result.error || `Server returned ${response.status} status`);
            }
            
            if (result.success) {
                return result;
            } else {
                throw new Error(result.error || 'Failed to upload file');
            }
        } catch (error) {
            console.error('Error uploading file:', error);
            showAlert('Failed to upload file: ' + error.message, 'danger');
            throw error; // Re-throw to be caught by the caller
        }
    }



    // Function to get the appropriate message table based on user level
    function getMessageTable(userLevel) {
        console.log('Getting message table for user level:', userLevel);
        const level = parseInt(userLevel);
        
        if (isNaN(level)) {
            console.error('Invalid user level (not a number):', userLevel);
            return null;
        }
        
        // Map user levels to their respective message tables
        const tableMap = {
            4: 'pm_procurement_messages', // Procurement
            5: 'pm_supplier_messages'    // Supplier
        };
        
        const table = tableMap[level];
        
        if (!table) {
            console.error('No message table mapped for user level:', level);
            return null;
        }
        
        console.log('Using message table:', table);
        return table;
    }


    // Function to load messages for a contact
    function loadMessages(userId, userLevel) {
        const chatMessages = document.getElementById('chatMessages');
        if (!chatMessages) return;
        
        // Show loading state
        chatMessages.innerHTML = `
            <div class="d-flex justify-content-center align-items-center h-100">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>`;
        
        // Determine which endpoint to use based on user level
        let endpoint = '';
        if (userLevel == 5) { // Supplier
            endpoint = 'get_supplier_messages.php';
        } else { // Other procurement officers
            endpoint = 'get_procurement_messages.php';
        }
        
        // Fetch messages from the appropriate endpoint
        fetch(`${endpoint}?receiver_id=${userId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to fetch messages');
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    throw new Error(data.error || 'Failed to load messages');
                }
                
                // Clear existing messages
                chatMessages.innerHTML = '';
                
                // Check if current user has sent any messages
                const currentUserId = <?php echo $_SESSION['user_id']; ?>;
                const hasUserSentMessage = data.messages.some(msg => msg.sender_id == currentUserId);
                
                // Show start conversation message if user hasn't sent any messages
                if (data.messages.length === 0 || !hasUserSentMessage) {
                    chatMessages.innerHTML = `
                        <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted">
                            <i class="fas fa-comment-alt fa-3x mb-3"></i>
                            <h5>No messages yet</h5>
                            <p>Send a message to start the conversation</p>
                        </div>
                    `;
                    return;
                }
                
                // Process and display messages
                data.messages.forEach(msg => {
                    const isMe = msg.sender_id == <?php echo $_SESSION['user_id']; ?>;
                    const messageDiv = document.createElement('div');
                    messageDiv.className = `d-flex mb-3 ${isMe ? 'justify-content-end' : 'justify-content-start'}`;
                    
                    let messageContent = msg.message;
                    
                    // Check if message contains a file link
                    if (msg.message.includes('[File: ') && msg.message.includes(']')) {
                        const filePath = msg.message.match(/\[File: (.*?)\]/)[1];
                        const fileName = filePath.split('/').pop();
                        const fileExt = fileName.split('.').pop().toLowerCase();
                        const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExt);
                        const isPdf = fileExt === 'pdf';
                        
                        let fileIcon = '<i class="fas fa-file me-2"></i>';
                        if (isImage) fileIcon = '<i class="fas fa-file-image me-2"></i>';
                        else if (isPdf) fileIcon = '<i class="fas fa-file-pdf me-2"></i>';
                        
                        const fileLink = `<div class="mt-2"><a href="../${filePath}" target="_blank" class="text-decoration-none ${isMe ? 'text-white' : 'text-primary'} fw-medium">
                            ${fileIcon}${fileName}
                        </a></div>`;
                        
                        // If the message is just the file link, show only the file
                        if (msg.message.trim() === `[File: ${filePath}]`) {
                            messageContent = fileLink;
                        } 
                        // If there's other text in the message, show it with the file below
                        else {
                            messageContent = msg.message.replace(`[File: ${filePath}]`, '').trim();
                            messageContent = messageContent.replace(/\n/g, '<br>') + fileLink;
                        }
                    } else {
                        // Only apply line breaks to regular text messages
                        messageContent = messageContent.replace(/\n/g, '<br>');
                    }
                    
                    // Format timestamp
                    const timestamp = new Date(msg.date_sent).toLocaleString();
                    
                    messageDiv.innerHTML = `
                        <div class="d-flex flex-column ${isMe ? 'align-items-end' : 'align-items-start'}" style="max-width: 80%;">
                            ${!isMe ? `
                                <div class="d-flex align-items-center mb-1">
                                    <img src="${msg.sender_avatar || '../uploads/default_profile.png'}" 
                                         class="rounded-circle me-2" 
                                         width="30" 
                                         height="30" 
                                         alt="${msg.sender_name}">
                                    <small class="fw-bold">${msg.sender_name}</small>
                                </div>
                            ` : ''}
                            <div class="d-flex align-items-center">
                                ${isMe ? `
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-link text-${isMe ? 'muted' : 'muted'} p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="#" onclick="return false;">Reply</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="return false;">Forward</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        ${isMe ? `
                                        <li><a class="dropdown-item text-danger delete-message" href="#" data-action="delete-for-everyone" data-message-id="${msg.id}">Delete for everyone</a></li>
                                        ` : ''}
                                        <li><a class="dropdown-item text-danger delete-message" href="#" data-action="delete-for-me" data-message-id="${msg.id}">
                                            ${isMe ? 'Delete for me' : 'Delete message'}
                                        </a></li>
                                    </ul>
                                </div>
                                ` : ''}
                                <div class="p-3 rounded-3 ${isMe ? 'bg-primary text-white' : 'bg-light'} ${isMe ? '' : 'me-2'}">
                                    ${messageContent}
                                </div>
                                ${!isMe ? `
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-link text-muted p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="#" onclick="return false;">Reply</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="return false;">Forward</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger delete-message" href="#" data-action="delete-for-me" data-message-id="${msg.id}">Delete for me</a></li>
                                    </ul>
                                </div>
                                ` : ''}
                            </div>
                            <small class="text-muted mt-1">
                                ${timestamp}
                                ${msg.is_read ? ' <i class="fas fa-check-double ms-1"></i>' : ''}
                            </small>
                        </div>
                    `;
                    
                    chatMessages.appendChild(messageDiv);
                });
                
                // Scroll to bottom with smooth animation to show latest message
                const scrollToBottom = () => {
                    chatMessages.scrollTo({
                        top: chatMessages.scrollHeight,
                        behavior: 'smooth'
                    });
                };
                
                // Initial scroll to bottom
                scrollToBottom();
                
                // Additional scroll after a short delay to ensure all content is rendered
                setTimeout(scrollToBottom, 100);
                
                // Mark messages as read
              
                
            })
            .catch(error => {
                console.error('Error loading messages:', error);
                chatMessages.innerHTML = `
                    <div class="alert alert-danger m-3">
                        Failed to load messages. Please try again.
                    </div>`;
            });
    }
    
    // Handle message deletion
    document.addEventListener('click', async function(e) {
        if (e.target.closest('.delete-message')) {
            e.preventDefault();
            
            const deleteButton = e.target.closest('.delete-message');
            const action = deleteButton.getAttribute('data-action');
            const messageId = deleteButton.getAttribute('data-message-id');
            const messageDiv = deleteButton.closest('.d-flex.mb-3');
            
            if (!messageId) return;
            
            // Show confirmation dialog
            const confirmMessage = action === 'delete-for-everyone' 
                ? 'Are you sure you want to delete this message for everyone?'
                : 'Are you sure you want to delete this message for yourself?';
                
            if (!confirm(confirmMessage)) return;
            
            try {
                // Get the active contact to reload messages after deletion
                const activeContact = document.querySelector('.contact-item.active');
                if (!activeContact) return;
                
                const receiverId = activeContact.getAttribute('data-user-id');
                const userLevel = activeContact.getAttribute('data-user-level');
                
                const response = await fetch('delete_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `message_id=${encodeURIComponent(messageId)}&action=${encodeURIComponent(action)}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Reload messages to reflect the deletion
                    loadMessages(receiverId, userLevel);
                } else {
                    alert('Failed to delete message: ' + (result.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error deleting message:', error);
                alert('An error occurred while deleting the message');
            }
        }
    });

    // Function to automatically select the most recent conversation
    function autoSelectMostRecentConversation() {
        const contactItems = document.querySelectorAll('.contact-item');
        let mostRecentTime = 0;
        let mostRecentContact = null;
        
        // Find the contact with the most recent message
        contactItems.forEach(contact => {
            const lastMessageTime = contact.getAttribute('data-last-message-time');
            if (lastMessageTime) {
                const messageTime = parseInt(lastMessageTime);
                if (messageTime > mostRecentTime) {
                    mostRecentTime = messageTime;
                    mostRecentContact = contact;
                }
            }
        });
        
        // If we found a contact with messages, select it
        if (mostRecentContact) {
            const userId = mostRecentContact.getAttribute('data-user-id');
            const userName = mostRecentContact.querySelector('.contact-name')?.textContent || '';
            const userEmail = mostRecentContact.querySelector('.contact-email')?.textContent || '';
            const userLevel = mostRecentContact.getAttribute('data-user-level') || '';
            const userImage = mostRecentContact.querySelector('img')?.src || '';
            
            // Trigger the selectContact function
            selectContact(userId, userName, userEmail, userLevel, userImage);
        } else if (contactItems.length > 0) {
            // If no messages but we have contacts, select the first one
            contactItems[0].click();
        }
    }
    
    // Run auto-select after a short delay to ensure DOM is fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(autoSelectMostRecentConversation, 500);
    });
</script>

</body>

</html>