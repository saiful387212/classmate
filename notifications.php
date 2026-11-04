<?php
/**
 * ClassMate - All Notifications Page
 */

require_once __DIR__ . '/config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

Auth::requireLogin();

$pageTitle = 'All Notifications';
$pageCss = ['notifications-page.css'];
$isAdmin = Authorization::isAdmin();
$isStudent = !$isAdmin;

$db = Database::getInstance();
$userId = Session::get('user_id');
$userType = Session::get('user_type');

// Mark all as read when visiting this page (optional)
$markAllRead = $_GET['mark_all'] ?? '';
if ($markAllRead === '1') {
    $notificationManager = new NotificationManager();
    $notificationManager->markAllAsRead($userType, $userId);
}

// Get notifications with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("
    SELECT * FROM notifications 
    WHERE user_type = ? AND user_id = ? 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $userType);
$stmt->bindValue(2, $userId);
$stmt->bindValue(3, $perPage, PDO::PARAM_INT);
$stmt->bindValue(4, $offset, PDO::PARAM_INT);
$stmt->execute();
$notifications = $stmt->fetchAll();

// Get total count
$stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_type = ? AND user_id = ?");
$stmt->execute([$userType, $userId]);
$totalNotifications = $stmt->fetchColumn();
$totalPages = ceil($totalNotifications / $perPage);

// Unread count
$stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_type = ? AND user_id = ? AND is_read = 0");
$stmt->execute([$userType, $userId]);
$unreadCount = $stmt->fetchColumn();

include_once BASE_PATH . '/includes/header.php';
if ($isAdmin) {
    include_once BASE_PATH . '/includes/sidebar-admin.php';
} else {
    include_once BASE_PATH . '/includes/sidebar-student.php';
}
?>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1><i class="fas fa-bell"></i> Notifications</h1>
                <p class="text-muted">
                    <?php if ($unreadCount > 0): ?>
                        You have <strong><?php echo $unreadCount; ?></strong> unread notification<?php echo $unreadCount > 1 ? 's' : ''; ?>
                    <?php else: ?>
                        You're all caught up!
                    <?php endif; ?>
                </p>
            </div>
            <?php if ($unreadCount > 0): ?>
                <button class="btn btn-outline" onclick="markAllRead()">
                    <i class="fas fa-check-double"></i> Mark All as Read
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <h3>No Notifications Yet</h3>
                    <p>When you receive notifications, they'll appear here.</p>
                </div>
            <?php else: ?>
                <div class="notifications-list-page">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-page-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>" 
                             data-id="<?php echo $notification['id']; ?>">
                            <div class="notification-page-icon type-<?php echo $notification['notification_type']; ?>">
                                <i class="fas fa-<?php 
                                    echo $notification['notification_type'] === 'success' ? 'check-circle' : 
                                        ($notification['notification_type'] === 'error' ? 'exclamation-circle' : 
                                        ($notification['notification_type'] === 'warning' ? 'exclamation-triangle' : 'info-circle')); 
                                ?>"></i>
                            </div>
                            <div class="notification-page-content">
                                <div class="notification-page-header">
                                    <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                                    <?php if (!$notification['is_read']): ?>
                                        <span class="badge badge-primary">New</span>
                                    <?php endif; ?>
                                </div>
                                <p class="notification-page-message"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                                <div class="notification-page-meta">
                                    <span><i class="fas fa-clock"></i> <?php echo timeAgo($notification['created_at']); ?></span>
                                    <span><i class="fas fa-calendar"></i> <?php echo formatDateTime($notification['created_at']); ?></span>
                                </div>
                            </div>
                            <div class="notification-page-actions">
                                <?php if ($notification['link']): ?>
                                    <a href="<?php echo htmlspecialchars($notification['link']); ?>" 
                                       class="btn btn-sm btn-primary" 
                                       onclick="markNotificationRead(<?php echo $notification['id']; ?>)">
                                        <i class="fas fa-external-link-alt"></i> View
                                    </a>
                                <?php endif; ?>
                                <?php if (!$notification['is_read']): ?>
                                    <button class="btn btn-sm btn-outline" onclick="markRead(<?php echo $notification['id']; ?>, this)">
                                        <i class="fas fa-check"></i> Mark Read
                                    </button>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-danger" onclick="deleteNotification(<?php echo $notification['id']; ?>, this)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-container">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a href="?page=<?php echo $page - 1; ?>" class="page-link">Previous</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a href="?page=<?php echo $i; ?>" class="page-link"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a href="?page=<?php echo $page + 1; ?>" class="page-link">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                        <div class="pagination-info">
                            Page <?php echo $page; ?> of <?php echo $totalPages; ?> 
                            (<?php echo $totalNotifications; ?> total)
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const API_URL = '<?php echo url('api/notifications.php'); ?>';

function markRead(id, btn) {
    fetch(API_URL + '?action=mark_read', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const item = btn.closest('.notification-page-item');
            item.classList.remove('unread');
            item.classList.add('read');
            const badge = item.querySelector('.badge-primary');
            if (badge) badge.remove();
            btn.remove();
        }
    });
}

function markNotificationRead(id) {
    fetch(API_URL + '?action=mark_read', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    });
}

function markAllRead() {
    fetch(API_URL + '?action=mark_all_read', {
        method: 'POST'
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function deleteNotification(id, btn) {
    if (!confirm('Delete this notification?')) return;
    
    fetch(API_URL + '?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const item = btn.closest('.notification-page-item');
            item.style.transition = 'all 0.3s ease';
            item.style.opacity = '0';
            item.style.transform = 'translateX(-20px)';
            setTimeout(() => {
                item.remove();
                if (document.querySelectorAll('.notification-page-item').length === 0) {
                    location.reload();
                }
            }, 300);
        }
    });
}
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>