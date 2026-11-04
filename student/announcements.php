<?php
/**
 * Student - Announcements
 * View all class announcements
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure student access
Auth::requireStudent();

$pageTitle = 'Announcements';
$pageCss = ['student.css', 'announcements.css'];
$pageJs = ['announcements.js'];
$isStudent = true;

$db = Database::getInstance();
$studentId = Session::get('user_id');

// Get student's enrolled classes
$stmt = $db->prepare("
    SELECT class_id FROM class_enrollments 
    WHERE student_id = ? AND status = 'active'
");
$stmt->execute([$studentId]);
$classIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

// If no classes, use 0 to prevent SQL error
$classIds = $classIds ?: [0];

// ============================================
// GET FILTERS
// ============================================
$filters = [
    'search' => $_GET['search'] ?? '',
    'type' => $_GET['type'] ?? '',
    'priority' => $_GET['priority'] ?? ''
];

// ============================================
// GET ANNOUNCEMENTS
// ============================================
// Get student's batch_id for scoping
$batchStmt = $db->prepare("SELECT batch_id FROM students WHERE id = ? LIMIT 1");
$batchStmt->execute([$studentId]);
$studentBatchId = (int)($batchStmt->fetchColumn() ?: 0);

$sql = "
    SELECT 
        a.*,
        adm.full_name as created_by_name,
        c.class_title,
        c.class_code,
        (SELECT COUNT(*) FROM announcements WHERE id = a.id) as view_count
    FROM announcements a
    LEFT JOIN admins adm ON a.created_by = adm.id
    LEFT JOIN classes c ON a.class_id = c.id
    WHERE a.status = 'published'
    AND (
        a.target_audience = 'all'
        OR a.target_audience = 'students'
        OR (a.target_audience = 'specific_class' AND a.class_id IN (" . implode(',', $classIds) . "))
    )
    AND (a.start_date <= NOW() OR a.start_date IS NULL)
    AND (a.end_date >= NOW() OR a.end_date IS NULL)
    AND a.batch_id = ?
";

$params = [$studentBatchId];

if (!empty($filters['search'])) {
    $sql .= " AND (a.title LIKE ? OR a.content LIKE ?)";
    $search = '%' . $filters['search'] . '%';
    $params[] = $search;
    $params[] = $search;
}

if (!empty($filters['type'])) {
    $sql .= " AND a.announcement_type = ?";
    $params[] = $filters['type'];
}

if (!empty($filters['priority'])) {
    $sql .= " AND a.priority = ?";
    $params[] = $filters['priority'];
}

$sql .= " ORDER BY a.priority = 'urgent' DESC, a.priority = 'high' DESC, a.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$announcements = $stmt->fetchAll();

// ============================================
// GET ANNOUNCEMENT TYPES FOR FILTER
// ============================================
$stmt = $db->prepare("
    SELECT DISTINCT announcement_type 
    FROM announcements 
    WHERE status = 'published' AND batch_id = ?
    ORDER BY announcement_type
");
$stmt->execute([$studentBatchId]);
$types = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ============================================
// GET UNREAD COUNT
// ============================================
$unreadCount = 0;
foreach ($announcements as $announcement) {
    // Check if student has read this announcement
    $stmt = $db->prepare("
        SELECT id FROM announcement_reads 
        WHERE announcement_id = ? AND user_id = ? AND user_type = 'student'
    ");
    $stmt->execute([$announcement['id'], $studentId]);
    if (!$stmt->fetch()) {
        $unreadCount++;
    }
}

include_once BASE_PATH . '/includes/header.php';
?>

<div class="dashboard-container">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>Announcements</h1>
                <p class="text-muted">Stay updated with the latest announcements</p>
            </div>
            <div class="header-actions">
                <span class="badge badge-info">
                    <i class="fas fa-envelope"></i> <?php echo count($announcements); ?> Total
                </span>
                <?php if ($unreadCount > 0): ?>
                    <span class="badge badge-danger">
                        <i class="fas fa-circle"></i> <?php echo $unreadCount; ?> Unread
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== Quick Stats ===== -->
    <div class="announcement-stats">
        <div class="stat-item">
            <span class="stat-value"><?php echo count($announcements); ?></span>
            <span class="stat-label">Total Announcements</span>
        </div>
        <div class="stat-item">
            <span class="stat-value">
                <?php 
                $urgentCount = array_filter($announcements, function($a) { 
                    return $a['priority'] === 'urgent'; 
                });
                echo count($urgentCount);
                ?>
            </span>
            <span class="stat-label">Urgent</span>
        </div>
        <div class="stat-item">
            <span class="stat-value">
                <?php 
                $highCount = array_filter($announcements, function($a) { 
                    return $a['priority'] === 'high'; 
                });
                echo count($highCount);
                ?>
            </span>
            <span class="stat-label">High Priority</span>
        </div>
        <div class="stat-item">
            <span class="stat-value"><?php echo $unreadCount; ?></span>
            <span class="stat-label">Unread</span>
        </div>
    </div>

    <!-- ===== Filter Bar ===== -->
    <div class="filter-bar">
        <form method="GET" action="" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <input type="text" name="search" placeholder="Search announcements..." 
                           value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
                
                <div class="filter-group">
                    <select name="type" class="filter-auto-submit">
                        <option value="">All Types</option>
                        <?php foreach ($types as $type): ?>
                            <option value="<?php echo $type; ?>" 
                                    <?php echo $filters['type'] === $type ? 'selected' : ''; ?>>
                                <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <select name="priority" class="filter-auto-submit">
                        <option value="">All Priority</option>
                        <option value="low" <?php echo $filters['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                        <option value="medium" <?php echo $filters['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="high" <?php echo $filters['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="urgent" <?php echo $filters['priority'] === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="<?php echo url('student/announcements.php'); ?>" class="btn btn-outline btn-sm">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- ===== Announcements Grid ===== -->
    <?php if (empty($announcements)): ?>
        <div class="empty-state">
            <i class="fas fa-bullhorn"></i>
            <h3>No Announcements Found</h3>
            <p>There are no announcements matching your filters.</p>
        </div>
    <?php else: ?>
        <div class="announcements-list">
            <?php foreach ($announcements as $announcement): 
                // Check if read
                $stmt = $db->prepare("
                    SELECT id FROM announcement_reads 
                    WHERE announcement_id = ? AND user_id = ? AND user_type = 'student'
                ");
                $stmt->execute([$announcement['id'], $studentId]);
                $isRead = $stmt->fetch() ? true : false;
                
                $priorityClass = $announcement['priority'] === 'urgent' ? 'urgent' : 
                                ($announcement['priority'] === 'high' ? 'high' : 'normal');
            ?>
                <div class="announcement-item <?php echo $priorityClass; ?> <?php echo $isRead ? 'read' : 'unread'; ?>" 
                     data-id="<?php echo $announcement['id']; ?>">
                    <div class="announcement-header">
                        <div class="announcement-title">
                            <?php if (!$isRead): ?>
                                <span class="unread-dot"></span>
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($announcement['title']); ?></h3>
                        </div>
                        <div class="announcement-badges">
                            <span class="badge badge-<?php echo $announcement['priority']; ?>">
                                <?php echo ucfirst($announcement['priority']); ?>
                            </span>
                            <span class="badge badge-<?php echo $announcement['announcement_type']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $announcement['announcement_type'])); ?>
                            </span>
                            <?php if ($announcement['class_id']): ?>
                                <span class="badge badge-secondary">
                                    <i class="fas fa-book"></i> <?php echo htmlspecialchars($announcement['class_code']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="announcement-body">
                        <div class="announcement-content">
                            <?php 
                            // Truncate content if too long
                            $content = $announcement['content'];
                            $maxLength = 300;
                            if (strlen($content) > $maxLength) {
                                $content = substr($content, 0, $maxLength) . '...';
                            }
                            echo nl2br(htmlspecialchars($content));
                            ?>
                        </div>
                        
                        <?php if (strlen($announcement['content']) > $maxLength): ?>
                            <button class="btn btn-sm btn-link read-more" onclick="toggleContent(this)">
                                Read More
                            </button>
                            <div class="announcement-full-content" style="display: none;">
                                <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="announcement-footer">
                        <div class="announcement-meta">
                            <span class="meta-item">
                                <i class="fas fa-user"></i>
                                <?php echo htmlspecialchars($announcement['created_by_name'] ?? 'System'); ?>
                            </span>
                            <span class="meta-item">
                                <i class="fas fa-clock"></i>
                                <?php echo timeAgo($announcement['created_at']); ?>
                            </span>
                            <?php if ($announcement['class_title']): ?>
                                <span class="meta-item">
                                    <i class="fas fa-book"></i>
                                    <?php echo htmlspecialchars($announcement['class_title']); ?>
                                </span>
                            <?php endif; ?>
                            <span class="meta-item">
                                <i class="fas fa-eye"></i>
                                <?php echo $announcement['views_count'] ?? 0; ?> views
                            </span>
                        </div>
                        
                        <div class="announcement-actions">
                            <button class="btn btn-sm btn-outline-primary" onclick="markAsRead(<?php echo $announcement['id']; ?>, this)">
                                <i class="fas fa-check"></i> Mark as Read
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="shareAnnouncement(<?php echo $announcement['id']; ?>)">
                                <i class="fas fa-share"></i> Share
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ===== Share Modal ===== -->
<div class="modal-overlay" id="shareModal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3>Share Announcement</h3>
            <button class="modal-close" onclick="closeModal('shareModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p>Share this announcement with your classmates:</p>
            <div class="share-links">
                <button class="btn btn-primary" onclick="shareVia('facebook')">
                    <i class="fab fa-facebook-f"></i> Facebook
                </button>
                <button class="btn btn-info" onclick="shareVia('twitter')">
                    <i class="fab fa-twitter"></i> Twitter
                </button>
                <button class="btn btn-success" onclick="shareVia('whatsapp')">
                    <i class="fab fa-whatsapp"></i> WhatsApp
                </button>
                <button class="btn btn-secondary" onclick="shareVia('copy')">
                    <i class="fas fa-copy"></i> Copy Link
                </button>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('shareModal')">Close</button>
        </div>
    </div>
</div>

<script>
// ===== Mark as Read =====
function markAsRead(id, btn) {
    const csrfToken = <?php echo json_encode(Security::generateCSRFToken()); ?>;
    
    fetch('<?php echo url('api/announcement-api.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'mark_read',
            announcement_id: id,
            csrf_token: csrfToken
        })
    })
    .then(async response => {
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.message || 'The request failed.');
        }
        return data;
    })
    .then(data => {
        if (data.success) {
            const item = btn.closest('.announcement-item');
            item.classList.remove('unread');
            item.classList.add('read');
            const dot = item.querySelector('.unread-dot');
            if (dot) dot.remove();
            
            // Update unread count
            updateUnreadCount();
            
            // Show notification
            showToast('Marked as read', 'success');
        } else {
            showToast(data.message || 'Failed to mark as read', 'error');
        }
    })
    .catch(error => {
        showToast(error.message || 'An error occurred', 'error');
    });
}

// ===== Toggle Content =====
function toggleContent(btn) {
    const parent = btn.closest('.announcement-body');
    const fullContent = parent.querySelector('.announcement-full-content');
    const shortContent = parent.querySelector('.announcement-content');
    
    if (fullContent.style.display === 'none') {
        fullContent.style.display = 'block';
        shortContent.style.display = 'none';
        btn.textContent = 'Read Less';
    } else {
        fullContent.style.display = 'none';
        shortContent.style.display = 'block';
        btn.textContent = 'Read More';
    }
}

// ===== Share Announcement =====
let currentShareId = null;

function shareAnnouncement(id) {
    currentShareId = id;
    openModal('shareModal');
}

function shareVia(platform) {
    const url = window.location.href;
    const title = document.querySelector('.announcement-item .announcement-title h3')?.textContent || 'Check this announcement';
    
    let shareUrl = '';
    switch(platform) {
        case 'facebook':
            shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
            break;
        case 'twitter':
            shareUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(title)}&url=${encodeURIComponent(url)}`;
            break;
        case 'whatsapp':
            shareUrl = `https://api.whatsapp.com/send?text=${encodeURIComponent(title + ' ' + url)}`;
            break;
        case 'copy':
            navigator.clipboard.writeText(url).then(() => {
                showToast('Link copied to clipboard!', 'success');
                closeModal('shareModal');
            }).catch(() => {
                // Fallback
                const textarea = document.createElement('textarea');
                textarea.value = url;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                textarea.remove();
                showToast('Link copied to clipboard!', 'success');
                closeModal('shareModal');
            });
            return;
    }
    
    if (shareUrl) {
        window.open(shareUrl, '_blank', 'width=600,height=400');
        closeModal('shareModal');
    }
}

// ===== Update Unread Count =====
function updateUnreadCount() {
    fetch('<?php echo url('api/announcement-api.php'); ?>?action=unread_count')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const badge = document.querySelector('.badge-danger');
                if (badge) {
                    if (data.count > 0) {
                        badge.textContent = '🔴 ' + data.count + ' Unread';
                        badge.style.display = 'inline';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            }
        })
        .catch(() => {});
}

// ===== Toast Notification =====
function showToast(message, type = 'info') {
    const container = document.querySelector('.toast-container');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="this.closest('.toast').remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('removing');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ===== Mark all as read =====
document.querySelector('.mark-all-read')?.addEventListener('click', function() {
    const csrfToken = <?php echo json_encode(Security::generateCSRFToken()); ?>;
    
    fetch('<?php echo url('api/announcement-api.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'mark_all_read',
            csrf_token: csrfToken
        })
    })
    .then(async response => {
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.message || 'The request failed.');
        }
        return data;
    })
    .then(data => {
        if (data.success) {
            document.querySelectorAll('.announcement-item.unread').forEach(item => {
                item.classList.remove('unread');
                item.classList.add('read');
                const dot = item.querySelector('.unread-dot');
                if (dot) dot.remove();
            });
            updateUnreadCount();
            showToast('All announcements marked as read', 'success');
        } else {
            showToast(data.message || 'Failed to mark announcements as read', 'error');
        }
    })
    .catch(error => {
        showToast(error.message || 'An error occurred', 'error');
    });
});
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>