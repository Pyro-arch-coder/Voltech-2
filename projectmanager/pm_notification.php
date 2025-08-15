<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
include_once "../config.php";
$unread_count = 0;
$notifications = [];
if ($user_id) {
    $res = $con->query("SELECT id, notif_type, message, is_read, created_at FROM notifications_projectmanager WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 7");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $notifications[] = $row;
            if ($row['is_read'] == 0) $unread_count++;
        }
    }
}

// Store the HTML output in a variable instead of direct output
$html_output = '<li class="nav-item dropdown">
    <a class="nav-link position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-bell fa-lg"></i>';
if ($unread_count > 0) {
    $html_output .= '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.7em;">' . $unread_count . '</span>';
}
$html_output .= '</a>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown" style="min-width:220px; max-height: 400px; overflow-y: auto; background:#fff;">
        <li class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom" style="position: sticky; top: 0; z-index: 1; background: #fff;">
            <div class="fw-bold">Notifications</div>
            <form method="post" action="clear_pm_notifications.php">
                <button type="submit" class="btn btn-link text-danger p-0" style="font-size:1.1em;" title="Clear all notifications">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </form>
        </li>';

// Limit notifications to 7
$notifications = array_slice($notifications, 0, 7);
if (count($notifications) > 0) {
    foreach ($notifications as $notif) {
        $text = 'text-success';
        $dateColor = '#198754';
        if (stripos($notif['notif_type'], 'reject') !== false || stripos($notif['notif_type'], 'Rejection') !== false) {
            $text = 'text-danger';
            $icon = '<i class="fas fa-times-circle fs-5 text-danger me-3"></i>';
        } elseif (stripos($notif['notif_type'], 'approve') !== false || stripos($notif['notif_type'], 'Approval') !== false) {
            $text = 'text-success';
            $icon = '<i class="fas fa-check-circle fs-5 text-success me-3"></i>';
        } else {
            $icon = '<i class="fas fa-bell fs-5 text-success me-3"></i>';
        }
        $html_output .= '<li>
            <a class="dropdown-item p-0" style="background:transparent;" href="mark_pm_notification_read.php?id=' . $notif['id'] . '">
                <div class="rounded-3 p-2 d-flex align-items-center" style="min-width:150px; background:#fff; border-bottom:1px solid #e0e0e0;">
                    ' . $icon . '
                    <div>
                        <div class="fw-bold ' . $text . '" style="font-size:0.95em;' . ($notif['is_read']==0 ? 'font-weight:700;' : '') . '">' . htmlspecialchars($notif['notif_type']) . '</div>
                        <div class="' . $text . '" style="font-size:0.85em;">' . htmlspecialchars($notif['message']) . '</div>
                        <div class="small mt-1" style="color:' . $dateColor . '; opacity:0.7; font-size:0.75em;">' . date('M d, Y H:i', strtotime($notif['created_at'])) . '</div>
                    </div>
                </div>
            </a>
        </li>';
    }
} else {
    $html_output .= '<li><span class="dropdown-item-text text-success">No new notifications</span></li>';
}
$html_output .= '</ul>
</li>';

// Output the HTML
echo $html_output;
ob_end_flush();
?>