<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';
$unread_count = 0;
$notifications = [];

// Get the logged-in user's email from session
$user_email = isset($_SESSION['email']) ? trim($_SESSION['email']) : '';

if (!empty($user_email)) {
    // First, get the supplier ID using the logged-in user's email
    $supplier_id = 0;
    $supplier_query = "SELECT id FROM suppliers WHERE email = ? LIMIT 1";
    $stmt = $con->prepare($supplier_query);
    $stmt->bind_param('s', $user_email);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $supplier_id = intval($row['id']);
        }
    }
    $stmt->close();
    
    // If we have a valid supplier ID, get their notifications
    if ($supplier_id > 0) {
        $query = "SELECT id, notif_type, message, is_read, created_at 
                 FROM notifications_supplier 
                 WHERE user_id = ? 
                 ORDER BY created_at DESC 
                 LIMIT 10";
        $stmt = $con->prepare($query);
        $stmt->bind_param('i', $supplier_id);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $notifications[] = $row;
                if ($row['is_read'] == 0) $unread_count++;
            }
            $stmt->close();
        }
    }
}
?>
<li class="nav-item dropdown">
    <a class="nav-link position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-bell fa-lg"></i>
        <?php if ($unread_count > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.7em;">
                <?php echo $unread_count; ?>
            </span>
        <?php endif; ?>
    </a>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown" style="min-width:220px; background:#fff;">
        <li class="d-flex justify-content-end" style="padding: 0 10px 5px 10px;">
            <form method="post" action="clear_supplier_notifications.php">
                <button type="submit" class="btn btn-link text-danger p-0" style="font-size:1.1em;" title="Clear all notifications">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </form>
        </li>
        <li>
        <?php if (count($notifications) > 0): ?>
            <?php foreach ($notifications as $notif): ?>
                <?php
                $text = 'text-success'; // default green
                $dateColor = '#198754'; // always green for date
                if (stripos($notif['notif_type'], 'reject') !== false || stripos($notif['notif_type'], 'Rejection') !== false) {
                    $text = 'text-danger';
                    $icon = '<i class="fas fa-times-circle fs-5 text-danger me-3"></i>';
                } elseif (stripos($notif['notif_type'], 'approve') !== false || stripos($notif['notif_type'], 'Approval') !== false) {
                    $text = 'text-success';
                    $icon = '<i class="fas fa-check-circle fs-5 text-success me-3"></i>';
                } else {
                    $icon = '<i class="fas fa-bell fs-5 text-success me-3"></i>';
                }
                ?>
                <li>
                    <a class="dropdown-item p-0" style="background:transparent;" href="mark_supplier_notification_read.php?id=<?php echo $notif['id']; ?>">
                        <div class="rounded-3 p-2 d-flex align-items-center" style="min-width:150px; background:#fff; border-bottom:1px solid #e0e0e0;">
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
            <li><span class="dropdown-item-text text-success">No new notifications</span></li>
        <?php endif; ?>
    </ul>
</li> 