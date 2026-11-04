<?php
/**
 * Admin - Student Details
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

$pageTitle = 'Student Details';
$pageCss = ['admin.css', 'students.css'];
$isAdmin = true;

$id = $_GET['id'] ?? 0;
if (!$id) {
    Session::setFlash('error', 'Invalid student ID');
    redirect(url('admin/students.php'));
}

$db = Database::getInstance();
[$batchSql, $batchParams] = Authorization::adminBatchFilter('s');

// Get student details
$stmt = $db->prepare("
    SELECT s.*,
           (SELECT COUNT(*) FROM class_enrollments ce JOIN classes c ON c.id = ce.class_id WHERE ce.student_id = s.id AND ce.status = 'active' AND c.batch_id = s.batch_id) as enrolled_classes,
           (SELECT COUNT(*) FROM class_enrollments ce JOIN classes c ON c.id = ce.class_id WHERE ce.student_id = s.id AND ce.status = 'completed' AND c.batch_id = s.batch_id) as completed_classes,
           (SELECT COUNT(*) FROM class_enrollments ce JOIN classes c ON c.id = ce.class_id WHERE ce.student_id = s.id AND ce.status = 'dropped' AND c.batch_id = s.batch_id) as dropped_classes
    FROM students s
    WHERE s.id = ?" . $batchSql
);
$stmt->execute(array_merge([$id], $batchParams));
$student = $stmt->fetch();

if (!$student) {
    Session::setFlash('error', 'Student not found');
    redirect(url('admin/students.php'));
}

// Get enrolled classes
$stmt = $db->prepare("
    SELECT c.*, ce.enrollment_date, ce.status as enrollment_status, ce.grade,
           crs.course_code, crs.course_title
    FROM class_enrollments ce
    JOIN classes c ON ce.class_id = c.id
    JOIN courses crs ON c.course_id = crs.id
    WHERE ce.student_id = ? AND c.batch_id = ?
    ORDER BY ce.enrollment_date DESC
");
$stmt->execute([$id, $student['batch_id']]);
$enrollments = $stmt->fetchAll();

// Get recent activity
$stmt = $db->prepare("
    SELECT * FROM activity_logs 
    WHERE user_type = 'student' AND user_id = ? 
    ORDER BY created_at DESC LIMIT 10
");
$stmt->execute([$id]);
$activities = $stmt->fetchAll();

include_once BASE_PATH . '/includes/header.php';
include_once BASE_PATH . '/includes/sidebar-admin.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1><?php echo htmlspecialchars($student['full_name']); ?></h1>
                <p class="text-muted">
                    <span class="class-roll">Roll: <?php echo htmlspecialchars($student['class_roll']); ?></span>
                    <span class="separator">|</span>
                    <span class="status-badge">Status: <span class="badge badge-<?php echo $student['status']; ?>"><?php echo ucfirst($student['status']); ?></span></span>
                    <span class="separator">|</span>
                    <span class="department"><?php echo htmlspecialchars($student['department']); ?></span>
                </p>
            </div>
            <div class="page-actions">
                <a href="<?php echo url('admin/students.php'); ?>" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Students
                </a>
            </div>
        </div>
    </div>
    
    <!-- Student Information Grid -->
    <div class="student-info-grid">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user"></i> Personal Information</h3>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <label>Full Name</label>
                    <span><?php echo htmlspecialchars($student['full_name']); ?></span>
                </div>
                <div class="info-row">
                    <label>Class Roll</label>
                    <span><code><?php echo htmlspecialchars($student['class_roll']); ?></code></span>
                </div>
                <div class="info-row">
                    <label>Email</label>
                    <span><?php echo htmlspecialchars($student['email'] ?: 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <label>Phone</label>
                    <span><?php echo htmlspecialchars($student['phone'] ?: 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <label>Department</label>
                    <span><?php echo htmlspecialchars($student['department']); ?></span>
                </div>
                <div class="info-row">
                    <label>Session</label>
                    <span><?php echo htmlspecialchars($student['session'] ?: 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <label>Batch</label>
                    <span><?php echo htmlspecialchars($student['batch'] ?: 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <label>PIN Status</label>
                    <span>
                        <?php if ($student['pin_hash']): ?>
                            <span class="badge badge-success"><i class="fas fa-check"></i> Set</span>
                        <?php else: ?>
                            <span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> Not Set</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-simple"></i> Account Statistics</h3>
            </div>
            <div class="card-body">
                <div class="stat-row">
                    <div class="stat-item">
                        <span class="stat-value"><?php echo $student['enrolled_classes']; ?></span>
                        <span class="stat-label">Active Classes</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo $student['completed_classes']; ?></span>
                        <span class="stat-label">Completed</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo $student['dropped_classes']; ?></span>
                        <span class="stat-label">Dropped</span>
                    </div>
                </div>
                
                <div class="info-row">
                    <label>Registered On</label>
                    <span><?php echo formatDateTime($student['created_at']); ?></span>
                </div>
                <div class="info-row">
                    <label>Approved On</label>
                    <span><?php echo $student['approved_at'] ? formatDateTime($student['approved_at']) : 'Pending'; ?></span>
                </div>
                <div class="info-row">
                    <label>Last Login</label>
                    <span><?php echo $student['last_login'] ? formatDateTime($student['last_login']) : 'Never'; ?></span>
                </div>
                <div class="info-row">
                    <label>Account Status</label>
                    <span>
                        <form method="POST" action="<?php echo url('admin/students.php?action=' . ($student['status'] === 'active' ? 'deactivate' : 'activate') . '&id=' . $student['id'] . '&csrf_token=' . Security::generateCSRFToken()); ?>" 
                              style="display:inline;">
                            <button type="submit" class="btn btn-sm <?php echo $student['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?>" 
                                    onclick="return confirm('<?php echo $student['status'] === 'active' ? 'Deactivate' : 'Activate'; ?> this student?')">
                                <i class="fas <?php echo $student['status'] === 'active' ? 'fa-pause' : 'fa-play'; ?>"></i>
                                <?php echo $student['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                            </button>
                        </form>
                        <?php if ($student['status'] === 'active'): ?>
                            <button class="btn btn-sm btn-primary" onclick="openResetPinModal(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['full_name']); ?>')">
                                <i class="fas fa-key"></i> Reset PIN
                            </button>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Enrolled Classes -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-book"></i> Enrolled Classes</h3>
            <span class="badge badge-info"><?php echo count($enrollments); ?> classes</span>
        </div>
        <div class="card-body">
            <?php if (empty($enrollments)): ?>
                <div class="empty-state small">
                    <i class="fas fa-book"></i>
                    <p>This student is not enrolled in any classes.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th>Course</th>
                                <th>Enrolled</th>
                                <th>Status</th>
                                <th>Grade</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrollments as $enrollment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($enrollment['class_title']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($enrollment['class_code']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($enrollment['course_code']); ?></td>
                                    <td><?php echo formatDate($enrollment['enrollment_date']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $enrollment['enrollment_status']; ?>">
                                            <?php echo ucfirst($enrollment['enrollment_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $enrollment['grade'] ?: '-'; ?></td>
                                    <td>
                                        <a href="<?php echo url('admin/class-details.php?id=' . $enrollment['id']); ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-history"></i> Recent Activity</h3>
        </div>
        <div class="card-body">
            <?php if (empty($activities)): ?>
                <div class="empty-state small">
                    <i class="fas fa-history"></i>
                    <p>No recent activity for this student.</p>
                </div>
            <?php else: ?>
                <div class="activity-timeline">
                    <?php foreach ($activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-<?php 
                                    echo $activity['action_type'] === 'login' ? 'sign-in-alt' : 
                                        ($activity['action_type'] === 'logout' ? 'sign-out-alt' : 
                                        ($activity['action_type'] === 'create' ? 'plus-circle' : 
                                        ($activity['action_type'] === 'update' ? 'edit' : 
                                        ($activity['action_type'] === 'delete' ? 'trash' : 'info-circle')))); 
                                ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    <?php echo htmlspecialchars($activity['action']); ?>
                                </div>
                                <div class="activity-meta">
                                    <span class="activity-time"><?php echo timeAgo($activity['created_at']); ?></span>
                                    <?php if ($activity['description']): ?>
                                        <span class="activity-description">- <?php echo htmlspecialchars($activity['description']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- PIN Reset Modal -->
<div class="modal-overlay" id="resetPinModal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3>Reset Student PIN</h3>
            <button class="modal-close" onclick="closeModal('resetPinModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="<?php echo url('admin/students.php'); ?>" id="resetPinForm">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="reset_pin" value="1">
            <input type="hidden" name="student_id" id="resetStudentId">
            
            <div class="modal-body">
                <p>Reset PIN for <strong id="resetStudentName"></strong></p>
                
                <div class="form-group">
                    <label for="new_pin">New PIN (6-digit number)</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-key"></i></span>
                        <input type="text" id="new_pin" name="new_pin" class="form-control" 
                               placeholder="Enter 6-digit PIN" maxlength="6" pattern="[0-9]{6}" required>
                        <button type="button" class="btn btn-sm btn-outline" onclick="generatePIN()">
                            <i class="fas fa-random"></i> Generate
                        </button>
                    </div>
                    <span class="form-help">PIN must be exactly 6 digits</span>
                </div>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>The student will need this PIN to login. Share it securely.</span>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('resetPinModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Reset PIN
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openResetPinModal(studentId, studentName) {
    document.getElementById('resetStudentId').value = studentId;
    document.getElementById('resetStudentName').textContent = studentName;
    document.getElementById('new_pin').value = '';
    openModal('resetPinModal');
}

function generatePIN() {
    const pin = String(Math.floor(100000 + Math.random() * 900000));
    document.getElementById('new_pin').value = pin;
}

document.getElementById('resetPinForm').addEventListener('submit', function(e) {
    const pin = document.getElementById('new_pin').value;
    if (!/^\d{6}$/.test(pin)) {
        e.preventDefault();
        alert('Please enter a valid 6-digit PIN.');
        return false;
    }
    return true;
});
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>