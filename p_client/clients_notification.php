<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$user_email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
require_once '../config.php';
$unread_count = 0;
$notifications = [];
if (!empty($user_email)) {
    $stmt = $con->prepare("SELECT id, notif_type, message, is_read, created_at FROM notifications_client WHERE client_email = ? ORDER BY created_at DESC LIMIT 7");
    $stmt->bind_param('s', $user_email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $notifications[] = $row;
            if ($row['is_read'] == 0) $unread_count++;
        }
    }
}
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle notification clicks
    document.querySelectorAll('.mark-notification').forEach(function(element) {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            const notificationId = this.getAttribute('data-id');
            const isRead = this.getAttribute('data-read');
            
            // Only mark as read if it's unread
            if (isRead === '0') {
                fetch('mark_client_notification_read.php?id=' + notificationId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update the UI
                            const badge = document.querySelector('.badge');
                            if (badge) {
                                const count = parseInt(badge.textContent) - 1;
                                if (count > 0) {
                                    badge.textContent = count;
                                } else {
                                    badge.remove();
                                }
                            }
                            // Update the notification style
                            const notification = document.getElementById('notif-' + notificationId);
                            if (notification) {
                                notification.querySelector('.fw-bold').style.fontWeight = 'normal';
                                element.setAttribute('data-read', '1');
                            }
                        }
                    })
                    .catch(error => console.error('Error:', error));
            }
            
            // Still allow the default link behavior (if any)
            // You can add additional navigation logic here if needed
        });
    });
});
</script>
<li class="nav-item dropdown">
    <a class="nav-link position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-bell fa-lg"></i>
        <?php if ($unread_count > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.7em;">
                <?php echo $unread_count; ?>
            </span>
        <?php endif; ?>
    </a>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown" style="min-width:220px; max-height: 400px; overflow-y: auto; background:#fff;">
        <li class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom" style="position: sticky; top: 0; z-index: 1; background: #fff;">
            <div class="fw-bold">Notifications</div>
            <form method="post" action="clients_clear_notifications.php">
                <button type="submit" class="btn btn-link text-danger p-0" style="font-size:1.1em;" title="Clear all notifications">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </form>
        </li>
        <?php if (count($notifications) > 0): ?>
            <?php foreach (array_slice($notifications, 0, 7) as $notif): ?>
                <?php
                $text = 'text-success'; // default green
                $dateColor = '#0d6efd'; // blue color for date
                if (stripos($notif['notif_type'], 'reject') !== false || stripos($notif['notif_type'], 'Rejection') !== false) {
                    $text = 'text-danger';
                    $icon = '<i class="fas fa-times-circle fs-5 text-danger me-3"></i>';
                } elseif (stripos($notif['notif_type'], 'approve') !== false || stripos($notif['notif_type'], 'Approval') !== false) {
                    $text = 'text-primary';
                    $icon = '<i class="fas fa-check-circle fs-5 text-primary me-3"></i>';
                } else {
                    $text = 'text-primary';
                    $icon = '<i class="fas fa-bell fs-5 text-primary me-3"></i>';
                }
                ?>
                <li>
                    <a class="dropdown-item p-0 mark-notification" style="background:transparent; cursor:pointer;" data-id="<?php echo $notif['id']; ?>" data-read="<?php echo $notif['is_read']; ?>">
                        <div class="rounded-3 p-2 d-flex align-items-center notification-item" style="min-width:150px; background:#fff; border-bottom:1px solid #e0e0e0;" id="notif-<?php echo $notif['id']; ?>">
                            <?php echo $icon; ?>
                            <div>
                                <div class="fw-bold <?php echo $text; ?>" style="font-size:0.95em;<?php if($notif['is_read']==0) echo 'font-weight:700;'; ?>"><?php echo htmlspecialchars($notif['notif_type']); ?></div>
                                <div class="<?php echo $text; ?>" style="font-size:0.85em;"> <?php echo htmlspecialchars($notif['message']); ?> </div>
                                <div class="small mt-1" style="color:<?php echo $dateColor; ?>; opacity:0.7; font-size:0.75em;"> <?php echo date('M d, Y H:i', strtotime($notif['created_at'])); ?> </div>
                            </div>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <li><span class="dropdown-item-text text-primary">No new notifications</span></li>
        <?php endif; ?>
    </ul>
</li> 