<?php
/**
 * Admin - Add Announcement
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

$pageTitle = 'Create Announcement';
$pageCss = ['admin.css', 'admin-announcements.css'];
$pageJs = ['add-announcement.js'];
$isAdmin = true;

$db = Database::getInstance();
$adminBatchId = Authorization::getAdminBatchId();
$stmt = $db->query("SELECT id, name, label FROM batches WHERE is_active = 1 ORDER BY FIELD(name, 'First year', 'Second year', 'Third Year', 'Fourth year'), name");
$batches = $stmt->fetchAll();

$error = null;
$formData = [
    'title' => '',
    'content' => '',
    'announcement_type' => 'general',
    'priority' => 'medium',
    'target_audience' => 'all',
    'class_id' => '',
    'start_date' => date('Y-m-d H:i'),
    'end_date' => date('Y-m-d H:i', strtotime('+7 days')),
    'status' => 'draft'
];

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

// Get admins for created_by dropdown
$stmt = $db->query("SELECT id, full_name FROM admins WHERE status = 'active' ORDER BY full_name");
$admins = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_announcement') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $announcementType = $_POST['announcement_type'] ?? 'general';
        $priority = $_POST['priority'] ?? 'medium';
        $targetAudience = $_POST['target_audience'] ?? 'all';
        $classId = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;
        $batchId = $adminBatchId ?? (int)($_POST['batch_id'] ?? 0);
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $status = $_POST['status'] ?? 'draft';

        $batchCheck = $db->prepare("SELECT id FROM batches WHERE id = ? AND is_active = 1");
        $batchCheck->execute([$batchId]);
        if (!$batchId || !$batchCheck->fetchColumn()) {
            $error = 'Please select a valid batch.';
        } elseif ($classId) {
            $classCheck = $db->prepare("SELECT id FROM classes WHERE id = ? AND batch_id = ?");
            $classCheck->execute([$classId, $batchId]);
            if (!$classCheck->fetchColumn()) {
                $error = 'Select a class from the selected batch.';
            }
        }
        
        // Validate
        if (empty($title)) {
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
                    INSERT INTO announcements (
                        batch_id, title, content, announcement_type, priority,
                        target_audience, class_id, start_date, end_date,
                        status, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $result = $stmt->execute([
                    $batchId,
                    $title,
                    $content,
                    $announcementType,
                    $priority,
                    $targetAudience,
                    $classId,
                    $startDate,
                    $endDate,
                    $status,
                    Session::get('user_id')
                ]);
                
                if ($result) {
                    $announcementId = $db->lastInsertId();
                    Session::setFlash('success', 'Announcement created successfully!');
                    logActivity('announcement_created', "Created announcement: {$title}");
                    // অ্যানাউন্সমেন্ট সেভ হওয়ার পর নিচের কোডটুকু দিন:
if ($status === 'published') {
    require_once BASE_PATH . '/includes/NotificationManager.php';
    $notifier = new NotificationManager();

    $notifier->notifyStudents(
        '📢 New Announcement: ' . $title,
        substr(strip_tags($content), 0, 100) . '...',
        'info',
        url('student/announcements.php')
    );
}
                    
                    // If published, notify students (batch-scoped)
                    if ($status === 'published') {
                        $batchWhere = " AND s.batch_id = " . (int)$batchId;
                        $stmt2 = $db->prepare("
                            INSERT INTO notifications (user_type, user_id, title, message, notification_type, link)
                            SELECT 
                                'student',
                                s.id,
                                ?,
                                ?,
                                ?,
                                ?
                            FROM students s
                            WHERE s.status = 'active'{$batchWhere}
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
                    $error = 'Failed to create announcement.';
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
                <h1>Create Announcement</h1>
                <p class="text-muted">Create a new announcement for students</p>
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
                <input type="hidden" name="action" value="create_announcement">

                <?php if ($adminBatchId === null): ?>
                    <div class="form-group">
                        <label for="batch_id">Batch <span class="required">*</span></label>
                        <select id="batch_id" name="batch_id" class="form-control" required>
                            <option value="">Select Batch</option>
                            <?php foreach ($batches as $batch): ?>
                                <option value="<?php echo (int)$batch['id']; ?>"><?php echo htmlspecialchars($batch['label'] ?: 'Batch ' . $batch['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
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
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Announcement
                    </button>
                    <a href="<?php echo url('admin/announcements.php'); ?>" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>