<?php
/**
 * Admin - Student Management
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

$pageTitle = 'Student Management';
$pageCss = ['admin.css', 'students.css'];
$pageJs = ['students.js'];
$isAdmin = true;

$db = Database::getInstance();

// Handle actions
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if ($action && $id) {
    if (!Security::verifyCSRFToken($_GET['csrf_token'] ?? '')) {
        Session::setFlash('error', 'Invalid security token');
        redirect(url('admin/students.php'));
    }

    [$studentBatchSql, $studentBatchParams] = Authorization::adminBatchFilter();
    $scopeStmt = $db->prepare("SELECT id FROM students WHERE id = ?" . $studentBatchSql);
    $scopeStmt->execute(array_merge([$id], $studentBatchParams));
    if (!$scopeStmt->fetchColumn()) {
        Session::setFlash('error', 'Student not found.');
        redirect(url('admin/students.php'));
    }
    
    switch ($action) {
        case 'activate':
            $stmt = $db->prepare("UPDATE students SET status = 'active' WHERE id = ?" . $studentBatchSql);
            $result = $stmt->execute(array_merge([$id], $studentBatchParams));
            if ($result) {
                logActivity('student_activated', "Activated student ID: {$id}");
                Session::setFlash('success', 'Student activated successfully');
            } else {
                Session::setFlash('error', 'Failed to activate student');
            }
            break;
            // স্টুডেন্ট অ্যাপ্রুভ করার পর নিচের কোডটুকু দিন:
require_once BASE_PATH . '/includes/NotificationManager.php';
$notifier = new NotificationManager();

$notifier->send(
    'student',
    $studentId,
    '✅ Registration Approved',
    'Your registration has been approved! You can now login with your PIN.',
    'success',
    url('auth/student-login.php')
);
            
        case 'deactivate':
            $stmt = $db->prepare("UPDATE students SET status = 'inactive' WHERE id = ?" . $studentBatchSql);
            $result = $stmt->execute(array_merge([$id], $studentBatchParams));
            if ($result) {
                logActivity('student_deactivated', "Deactivated student ID: {$id}");
                Session::setFlash('success', 'Student deactivated successfully');
            } else {
                Session::setFlash('error', 'Failed to deactivate student');
            }
            break;
            
        case 'suspend':
            $stmt = $db->prepare("UPDATE students SET status = 'suspended' WHERE id = ?" . $studentBatchSql);
            $result = $stmt->execute(array_merge([$id], $studentBatchParams));
            if ($result) {
                logActivity('student_suspended', "Suspended student ID: {$id}");
                Session::setFlash('success', 'Student suspended successfully');
            } else {
                Session::setFlash('error', 'Failed to suspend student');
            }
            break;
            
        case 'delete':
            // Check if student has any enrollments
            $stmt = $db->prepare("SELECT COUNT(*) FROM class_enrollments WHERE student_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                Session::setFlash('error', 'Cannot delete student with class enrollments');
            } else {
                // Delete student
                $stmt = $db->prepare("DELETE FROM students WHERE id = ?" . $studentBatchSql);
                $result = $stmt->execute(array_merge([$id], $studentBatchParams));
                if ($result) {
                    logActivity('student_deleted', "Deleted student ID: {$id}");
                    Session::setFlash('success', 'Student deleted successfully');
                } else {
                    Session::setFlash('error', 'Failed to delete student');
                }
            }
            break;
            
        default:
            Session::setFlash('error', 'Invalid action');
            break;
    }
    
    redirect(url('admin/students.php'));
}

// Handle PIN reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_pin'])) {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        Session::setFlash('error', 'Invalid security token');
        redirect(url('admin/students.php'));
    }
    
    $studentId = $_POST['student_id'] ?? 0;
    $newPin = $_POST['new_pin'] ?? '';
    
    if (!$studentId) {
        Session::setFlash('error', 'Invalid student');
        redirect(url('admin/students.php'));
    }
    
    if (empty($newPin) || strlen($newPin) !== 6 || !is_numeric($newPin)) {
        Session::setFlash('error', 'PIN must be a 6-digit number');
        redirect(url('admin/students.php'));
    }
    
    $pinHash = Auth::hashPassword($newPin);
    [$studentBatchSql, $studentBatchParams] = Authorization::adminBatchFilter();
    $stmt = $db->prepare("UPDATE students SET pin_hash = ? WHERE id = ?" . $studentBatchSql);
    $result = $stmt->execute(array_merge([$pinHash, $studentId], $studentBatchParams));
    
    if ($result) {
        logActivity('student_pin_reset', "Reset PIN for student ID: {$studentId}");
        Session::setFlash('success', "PIN reset successfully! New PIN: <strong>{$newPin}</strong>");
    } else {
        Session::setFlash('error', 'Failed to reset PIN');
    }
    
    redirect(url('admin/students.php'));
}

// Get filters
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'department' => $_GET['department'] ?? '',
    'batch' => $_GET['batch'] ?? ''
];
// Build filters for students
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'department' => $_GET['department'] ?? '',
    'batch' => $_GET['batch'] ?? ''
];

// Build filter query
$filterBuilder = new FilterBuilder();

if (!empty($filters['search'])) {
    $filterBuilder->addSearch($filters['search'], ['s.full_name', 's.class_roll', 's.email']);
}

if (!empty($filters['status'])) {
    $filterBuilder->addEqual('status', $filters['status'], 's');
}

if (!empty($filters['department'])) {
    $filterBuilder->addLike('department', $filters['department'], 's');
}

if (!empty($filters['batch'])) {
    $filterBuilder->addEqual('batch', $filters['batch'], 's');
}

// Get total count and paginate...

// Build query
$sql = "
    SELECT 
        s.*,
        (SELECT COUNT(*) FROM class_enrollments ce JOIN classes c ON c.id = ce.class_id WHERE ce.student_id = s.id AND ce.status = 'active' AND c.batch_id = s.batch_id) as enrolled_classes
    FROM students s
    WHERE 1=1
";
$params = [];

// Batch scope for admin
[$batchSql, $batchParams] = Authorization::adminBatchFilter('s');
$sql .= $batchSql;
$params = array_merge($params, $batchParams);

if (!empty($filters['search'])) {
    $sql .= " AND (s.full_name LIKE ? OR s.class_roll LIKE ? OR s.email LIKE ?)";
    $search = '%' . $filters['search'] . '%';
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

if (!empty($filters['status'])) {
    $sql .= " AND s.status = ?";
    $params[] = $filters['status'];
}

if (!empty($filters['department'])) {
    $sql .= " AND s.department LIKE ?";
    $params[] = '%' . $filters['department'] . '%';
}

if (!empty($filters['batch']) && Authorization::isSuperAdmin()) {
    // Batch admins are already filtered to their batch; super admin can filter by batch name
    $sql .= " AND s.batch = ?";
    $params[] = $filters['batch'];
}

$sql .= " ORDER BY s.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get status counts (batch-scoped)
$statuses = ['active', 'inactive', 'pending', 'suspended', 'rejected'];
$statusCounts = [];
foreach ($statuses as $status) {
    $scSql = "SELECT COUNT(*) FROM students s WHERE s.status = ?" . $batchSql;
    $scStmt = $db->prepare($scSql);
    $scStmt->execute(array_merge([$status], $batchParams));
    $statusCounts[$status] = $scStmt->fetchColumn();
}
$totalStudents = array_sum($statusCounts);

// Get pending registration count for badge (batch-scoped)
$prSql = "SELECT COUNT(*) FROM registration_requests r WHERE r.status = 'pending'" . (Authorization::isBatchAdmin() ? " AND r.batch_id = ?" : "");
$prStmt = $db->prepare($prSql);
$prStmt->execute(Authorization::isBatchAdmin() ? [Authorization::getAdminBatchId()] : []);
$pendingCount = $prStmt->fetchColumn();

include_once BASE_PATH . '/includes/header.php';
include_once BASE_PATH . '/includes/sidebar-admin.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>Student Management</h1>
                <p class="text-muted">Manage all students in the system</p>
            </div>
            <div class="page-actions">
                <a href="<?php echo url('admin/registrations.php'); ?>" class="btn btn-warning">
                    <i class="fas fa-user-plus"></i> Pending Registrations
                    <?php if ($pendingCount > 0): ?>
                        <span class="badge badge-danger"><?php echo $pendingCount; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Status Stats -->
    <div class="status-stats">
        <div class="stat-item <?php echo empty($filters['status']) ? 'active' : ''; ?>">
            <a href="<?php echo url('admin/students.php'); ?>" class="stat-link">
                <span class="stat-label">Total</span>
                <span class="stat-value"><?php echo $totalStudents; ?></span>
            </a>
        </div>
        <?php foreach ($statuses as $status): ?>
            <div class="stat-item stat-<?php echo $status; ?> <?php echo $filters['status'] === $status ? 'active' : ''; ?>">
                <a href="<?php echo url('admin/students.php?status=' . $status); ?>" class="stat-link">
                    <span class="stat-label"><?php echo ucfirst($status); ?></span>
                    <span class="stat-value"><?php echo $statusCounts[$status]; ?></span>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Filters -->
    <div class="filter-bar">
        <form method="GET" action="" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <input type="text" name="search" placeholder="Search by name, roll, or email..." 
                           value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
                
                <div class="filter-group">
                    <select name="status">
                        <option value="">All Status</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo $status; ?>" 
                                    <?php echo $filters['status'] === $status ? 'selected' : ''; ?>>
                                <?php echo ucfirst($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <input type="text" name="department" placeholder="Department" 
                           value="<?php echo htmlspecialchars($filters['department']); ?>">
                </div>
                
                <div class="filter-group">
                    <input type="text" name="batch" placeholder="Batch" 
                           value="<?php echo htmlspecialchars($filters['batch']); ?>">
                </div>
                
                <div class="filter-group filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="<?php echo url('admin/students.php'); ?>" class="btn btn-outline btn-sm">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Students Table -->
    <?php if (empty($students)): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <h3>No Students Found</h3>
            <p>Try adjusting your filters or wait for students to register.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Class Roll</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Last Login</th>
                        <th>Classes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $index => $student): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <a href="<?php echo url('admin/student-details.php?id=' . $student['id']); ?>" class="student-name">
                                    <?php echo htmlspecialchars($student['full_name']); ?>
                                </a>
                            </td>
                            <td><code><?php echo htmlspecialchars($student['class_roll']); ?></code></td>
                            <td><?php echo htmlspecialchars($student['department']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $student['status']; ?>">
                                    <?php echo ucfirst($student['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $student['approved_at'] ? formatDate($student['approved_at']) : formatDate($student['created_at']); ?></td>
                            <td>
                                <?php echo $student['last_login'] ? timeAgo($student['last_login']) : 'Never'; ?>
                            </td>
                            <td>
                                <span class="badge badge-info"><?php echo $student['enrolled_classes']; ?></span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="<?php echo url('admin/student-details.php?id=' . $student['id']); ?>" 
                                       class="btn btn-sm btn-info" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if ($student['status'] === 'active'): ?>
                                        <a href="<?php echo url('admin/students.php?action=deactivate&id=' . $student['id'] . '&csrf_token=' . Security::generateCSRFToken()); ?>" 
                                           class="btn btn-sm btn-warning" 
                                           onclick="return confirm('Deactivate this student?')" title="Deactivate">
                                            <i class="fas fa-pause"></i>
                                        </a>
                                    <?php elseif ($student['status'] === 'inactive' || $student['status'] === 'suspended'): ?>
                                        <a href="<?php echo url('admin/students.php?action=activate&id=' . $student['id'] . '&csrf_token=' . Security::generateCSRFToken()); ?>" 
                                           class="btn btn-sm btn-success" 
                                           onclick="return confirm('Activate this student?')" title="Activate">
                                            <i class="fas fa-play"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($student['status'] === 'active'): ?>
                                        <a href="#" class="btn btn-sm btn-primary" 
                                           onclick="openResetPinModal(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['full_name']); ?>')" 
                                           title="Reset PIN">
                                            <i class="fas fa-key"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($student['enrolled_classes'] == 0): ?>
                                        <a href="<?php echo url('admin/students.php?action=delete&id=' . $student['id'] . '&csrf_token=' . Security::generateCSRFToken()); ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Delete this student? This cannot be undone.')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="table-footer">
            <span class="text-muted">Showing <?php echo count($students); ?> students</span>
        </div>
    <?php endif; ?>
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

// Form validation for PIN
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