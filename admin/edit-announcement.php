<?php
/**
 * Admin - Edit Announcement
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

$pageTitle = 'Edit Announcement';
$pageCss = ['admin.css', 'admin-announcements.css'];
$pageJs = ['add-announcement.js'];
$isAdmin = true;

$id = $_GET['id'] ?? 0;
if (!$id) {
    Session::setFlash('error', 'Invalid announcement ID');
    redirect(url('admin/announcements.php'));
}

$db = Database::getInstance();
$adminBatchId = Authorization::getAdminBatchId();
[$batchSql, $batchParams] = Authorization::adminBatchFilter();

// Get announcement
$stmt = $db->prepare("SELECT * FROM announcements WHERE id = ?" . $batchSql);
$stmt->execute(array_merge([$id], $batchParams));
$announcement = $stmt->fetch();

if (!$announcement) {
    Session::setFlash('error', 'Announcement not found');
    redirect(url('admin/announcements.php'));
}

$error = null;
$formData = $announcement;

// Get classes for dropdown
$classSql = "SELECT id, class_title, class_code FROM classes";
$classParams = [];
if ($adminBatchId !== null) {
    $classSql .= " WHERE batch_id = ?";
    $classParams[] = $adminBatchId;
}
$stmt = $db->prepare($classSql . " ORDER BY class_title");
$stmt->execute($classParams);
$classes = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_announcement') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $announcementType = $_POST['announcement_type'] ?? 'general';
        $priority = $_POST['priority'] ?? 'medium';
        $targetAudience = $_POST['target_audience'] ?? 'all';
        $classId = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $status = $_POST['status'] ?? 'draft';

        if ($classId) {
            $classCheck = $db->prepare("SELECT id FROM classes WHERE id = ? AND batch_id = ?");
            $classCheck->execute([$classId, $announcement['batch_id']]);
            if (!$classCheck->fetchColumn()) {
                $error = 'Select a class from the announcement batch.';
            }
        }
        
        // Validate
        if ($error) {
            // Keep batch validation errors intact.
        } elseif (empty($title)) {
            $error = 'Title is required.';
        } elseif (empty($content)) {
            $error = 'Content is required.';
        } else {
            // Validate dates
            if ($startDate && $endDate && strtotime($endDate) < strtotime($startDate)) {
                $error = 'End date must be after start date.';
            }
            
            if (!$error) {
                $stmt = $db->prepare("
                    UPDATE announcements 
                    SET title = ?, content = ?, announcement_type = ?, priority = ?,
                        target_audience = ?, class_id = ?, start_date = ?, end_date = ?,
                        status = ?, updated_at = NOW()
                    WHERE id = ?" . $batchSql
                );
                
                $result = $stmt->execute(array_merge([
                    $title,
                    $content,
                    $announcementType,
                    $priority,
                    $targetAudience,
                    $classId,
                    $startDate,
                    $endDate,
                    $status,
                    $id
                ], $batchParams));
                
                if ($result) {
                    Session::setFlash('success', 'Announcement updated successfully!');
                    logActivity('announcement_updated', "Updated announcement: {$title}");
                    
                    // If published, notify students (only if status changed to published)
                    if ($status === 'published' && $announcement['status'] !== 'published') {
                        $stmt2 = $db->prepare("
                            INSERT INTO notifications (user_type, user_id, title, message, notification_type, link)
                            SELECT 
                                'student',
                                s.id,
                                ?,
                                ?,
                                ?,
                                ?
                            FROM students s WHERE s.status = 'active' AND s.batch_id = " . (int)$announcement['batch_id'] . "
                            " . ($classId ? "AND s.id IN (SELECT student_id FROM class_enrollments WHERE class_id = ? AND status = 'active')" : "")
                        );
                        $params = [
                            'New Announcement: ' . $title,
                            substr(strip_tags($content), 0, 150),
                            'info',
                            '/student/announcements.php'
                        ];
                        if ($classId) {
                            $params[] = $classId;
                        }
                        $stmt2->execute($params);
                    }
                    
                    redirect(url('admin/announcements.php'));
                } else {
                    $error = 'Failed to update announcement.';
                }
            }
        }
    }
}

include_once BASE_PATH . '/includes/header.php';
include_once BASE_PATH . '/includes/sidebar-admin.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>Edit Announcement</h1>
                <p class="text-muted">Update announcement details</p>
            </div>
            <a href="<?php echo url('admin/announcements.php'); ?>" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Announcements
            </a>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <form method="POST" action="" id="announcementForm">
                <?php echo Security::csrfField(); ?>
                <input type="hidden" name="action" value="update_announcement">
                
                <div class="form-group">
                    <label for="title">Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title" class="form-control" 
                           placeholder="Enter announcement title" 
                           value="<?php echo htmlspecialchars($formData['title']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="content">Content <span class="required">*</span></label>
                    <textarea id="content" name="content" class="form-control" rows="8" 
                              placeholder="Write the announcement content here..." required><?php echo htmlspecialchars($formData['content']); ?></textarea>
                    <span class="form-help">You can use HTML tags for formatting</span>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="announcement_type">Type</label>
                        <select id="announcement_type" name="announcement_type" class="form-control">
                            <option value="general" <?php echo $formData['announcement_type'] === 'general' ? 'selected' : ''; ?>>General</option>
                            <option value="class_specific" <?php echo $formData['announcement_type'] === 'class_specific' ? 'selected' : ''; ?>>Class Specific</option>
                            <option value="assignment_related" <?php echo $formData['announcement_type'] === 'assignment_related' ? 'selected' : ''; ?>>Assignment Related</option>
                            <option value="urgent" <?php echo $formData['announcement_type'] === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="priority">Priority</label>
                        <select id="priority" name="priority" class="form-control">
                            <option value="low" <?php echo $formData['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo $formData['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo $formData['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="urgent" <?php echo $formData['priority'] === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="target_audience">Target Audience</label>
                        <select id="target_audience" name="target_audience" class="form-control">
                            <option value="all" <?php echo $formData['target_audience'] === 'all' ? 'selected' : ''; ?>>All Users</option>
                            <option value="students" <?php echo $formData['target_audience'] === 'students' ? 'selected' : ''; ?>>Students Only</option>
                            <option value="admins" <?php echo $formData['target_audience'] === 'admins' ? 'selected' : ''; ?>>Admins Only</option>
                            <option value="specific_class" <?php echo $formData['target_audience'] === 'specific_class' ? 'selected' : ''; ?>>Specific Class</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="class_id">Class (if class-specific)</label>
                        <select id="class_id" name="class_id" class="form-control">
                            <option value="">Select Class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>" 
                                        <?php echo $formData['class_id'] == $class['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['class_code'] . ' - ' . $class['class_title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="datetime-local" id="start_date" name="start_date" class="form-control" 
                               value="<?php echo htmlspecialchars($formData['start_date']); ?>">
                        <span class="form-help">Leave empty for immediate publication</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="datetime-local" id="end_date" name="end_date" class="form-control" 
                               value="<?php echo htmlspecialchars($formData['end_date']); ?>">
                        <span class="form-help">Leave empty for no expiration</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="draft" <?php echo $formData['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo $formData['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="archived" <?php echo $formData['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Announcement
                    </button>
                    <a href="<?php echo url('admin/announcements.php'); ?>" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>