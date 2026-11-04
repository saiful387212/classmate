<?php
/**
 * Admin - Registration Requests Management
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

$pageTitle = 'Registration Requests';
$pageCss = ['admin.css', 'students.css'];
$isAdmin = true;

$db = Database::getInstance();
$currentUser = Auth::getCurrentUser();

// Handle actions
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$id = $_GET['id'] ?? $_POST['registration_id'] ?? 0;

if ($action && $id) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || $action === 'delete') {
        if (!Security::verifyCSRFToken($_GET['csrf_token'] ?? $_POST['csrf_token'] ?? '')) {
            Session::setFlash('error', 'Invalid security token');
            redirect(url('admin/registrations.php'));
        }
    }
    
    if ($action === 'approve') {
        // Get request details
        [$batchSql, $batchParams] = Authorization::adminBatchFilter();
        $stmt = $db->prepare("SELECT * FROM registration_requests WHERE id = ? AND status = 'pending'" . $batchSql);
        $stmt->execute(array_merge([$id], $batchParams));
        $request = $stmt->fetch();
        
        if ($request) {
            // Check if student already exists
            $classRoll = trim($request['class_roll']);
            $stmt = $db->prepare("SELECT id, status FROM students WHERE TRIM(class_roll) = ? LIMIT 1");
            $stmt->execute([$classRoll]);
            $existingStudent = $stmt->fetch();
            if ($existingStudent) {
                if ($existingStudent['status'] === 'pending') {
                    $stmt = $db->prepare("UPDATE students SET batch = ?, batch_id = ?, status = 'active', approved_at = NOW() WHERE id = ?");
                    $stmt->execute([$request['batch'], $request['batch_id'], $existingStudent['id']]);
                }
                $stmt = $db->prepare("UPDATE registration_requests
                    SET status = 'approved', reviewed_by = ?, review_notes = ?, reviewed_at = NOW()
                    WHERE id = ? AND status = 'pending'");
                $stmt->execute([
                    $currentUser['id'],
                    'Existing student account found (ID: ' . $existingStudent['id'] . ').',
                    $id
                ]);
                Session::setFlash('success', 'This class roll already has a student account. The duplicate request was marked approved.');
                redirect(url('admin/registrations.php'));
            }
            
            // Generate PIN
            $pin = Auth::generatePIN(6);
            $pinHash = Auth::hashPassword($pin);
            
            // Begin transaction
            $db->beginTransaction();
            
            try {
                // Create student account
                $stmt = $db->prepare("
                    INSERT INTO students 
                    (full_name, class_roll, email, phone, department, session, batch, batch_id, pin_hash, status, approved_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ");
                $stmt->execute([
                    $request['full_name'],
                    $classRoll,
                    $request['email'],
                    $request['phone'],
                    $request['department'],
                    $request['session'],
                    $request['batch'],
                    $request['batch_id'],
                    $pinHash
                ]);
                
                $studentId = $db->lastInsertId();
                
                // Update registration request
                $stmt = $db->prepare("
                    UPDATE registration_requests 
                    SET status = 'approved', reviewed_by = ?, review_notes = ?, reviewed_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$currentUser['id'], $_POST['notes'] ?? null, $id]);
                
                $db->commit();
                
                // Notification delivery should not make an approved account appear failed.
                try {
                    sendNotification($studentId, 'student', 'Registration Approved', 
                        'Your registration has been approved. Your PIN is: ' . $pin, 
                        'success', url('auth/student-login.php'));
                } catch (Throwable $e) {
                    error_log('Registration approval notification failed: ' . $e->getMessage());
                }
                
                Session::setFlash('success', 
                    "Student approved successfully!<br><strong>PIN: {$pin}</strong><br><small>Please share this PIN with the student securely.</small>"
                );
            } catch (PDOException $e) {
                $db->rollBack();
                if ((int)$e->errorInfo[1] === 1062) {
                    // A duplicate can be created between the pre-check and INSERT.
                    $stmt = $db->prepare("SELECT id FROM students WHERE TRIM(class_roll) = ? LIMIT 1");
                    $stmt->execute([$classRoll]);
                    $existingStudentId = $stmt->fetchColumn();

                    if ($existingStudentId) {
                        $stmt = $db->prepare("UPDATE registration_requests
                            SET status = 'approved', reviewed_by = ?, review_notes = ?, reviewed_at = NOW()
                            WHERE id = ? AND status = 'pending'");
                        $stmt->execute([
                            $currentUser['id'],
                            'Existing student account found (ID: ' . $existingStudentId . ').',
                            $id
                        ]);
                        Session::setFlash('success', 'This class roll already has a student account. The duplicate request was marked approved.');
                    } else {
                        Session::setFlash('error', 'The registration could not be approved because the class roll is already in use.');
                    }
                } else {
                    Session::setFlash('error', 'Failed to approve student: ' . $e->getMessage());
                }
            } catch (Exception $e) {
                $db->rollBack();
                Session::setFlash('error', 'Failed to approve student: ' . $e->getMessage());
            }
        } else {
            Session::setFlash('error', 'Registration request not found or already processed.');
        }
        redirect(url('admin/registrations.php'));
        
    } elseif ($action === 'reject') {
        $notes = trim($_POST['notes'] ?? '');
        [$batchSql, $batchParams] = Authorization::adminBatchFilter();
        $stmt = $db->prepare("
            UPDATE registration_requests 
            SET status = 'rejected', reviewed_by = ?, review_notes = ?, reviewed_at = NOW() 
            WHERE id = ? AND status = 'pending'" . $batchSql
        );
        $stmt->execute(array_merge([$currentUser['id'], $notes, $id], $batchParams));
        
        if ($stmt->rowCount() > 0) {
            Session::setFlash('success', 'Registration request rejected.');
        } else {
            Session::setFlash('error', 'Failed to reject registration request.');
        }
        redirect(url('admin/registrations.php'));
        
    } elseif ($action === 'delete') {
        [$batchSql, $batchParams] = Authorization::adminBatchFilter();
        $stmt = $db->prepare("DELETE FROM registration_requests WHERE id = ? AND status IN ('rejected', 'cancelled')" . $batchSql);
        $stmt->execute(array_merge([$id], $batchParams));
        if ($stmt->rowCount() > 0) {
            Session::setFlash('success', 'Registration request deleted.');
        } else {
            Session::setFlash('error', 'Cannot delete this request.');
        }
        redirect(url('admin/registrations.php'));
    }
}

// Get filters
$statusFilter = $_GET['status'] ?? 'pending';
$search = $_GET['search'] ?? '';

// Build query
$query = "
    SELECT r.*, a.full_name as reviewed_by_name 
    FROM registration_requests r 
    LEFT JOIN admins a ON r.reviewed_by = a.id 
    WHERE 1=1
";
$params = [];

// Batch scope
[$batchSql, $batchParams] = Authorization::adminBatchFilter('r');
$query .= $batchSql;
$params = array_merge($params, $batchParams);

if ($statusFilter !== 'all') {
    $query .= " AND r.status = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $query .= " AND (r.full_name LIKE ? OR r.class_roll LIKE ? OR r.email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$query .= " ORDER BY r.requested_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Get counts (batch-scoped)
$counts = [];
$statuses = ['pending', 'approved', 'rejected', 'cancelled'];
foreach ($statuses as $status) {
    $cSql = "SELECT COUNT(*) FROM registration_requests r WHERE r.status = ?" . $batchSql;
    $cStmt = $db->prepare($cSql);
    $cStmt->execute(array_merge([$status], $batchParams));
    $counts[$status] = $cStmt->fetchColumn();
}
$counts['all'] = array_sum($counts);

include_once BASE_PATH . '/includes/header.php';
include_once BASE_PATH . '/includes/sidebar-admin.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>Registration Requests</h1>
                <p class="text-muted">Manage student registration requests</p>
            </div>
            <div class="page-actions">
                <a href="<?php echo url('admin/students.php'); ?>" class="btn btn-outline">
                    <i class="fas fa-users"></i> All Students
                </a>
            </div>
        </div>
    </div>
    
    <!-- Status Stats -->
    <div class="status-stats">
        <div class="stat-item <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">
            <a href="<?php echo url('admin/registrations.php?status=all'); ?>" class="stat-link">
                <span class="stat-label">Total</span>
                <span class="stat-value"><?php echo $counts['all']; ?></span>
            </a>
        </div>
        <?php foreach ($statuses as $status): ?>
            <div class="stat-item stat-<?php echo $status; ?> <?php echo $statusFilter === $status ? 'active' : ''; ?>">
                <a href="<?php echo url('admin/registrations.php?status=' . $status); ?>" class="stat-link">
                    <span class="stat-label"><?php echo ucfirst($status); ?></span>
                    <span class="stat-value"><?php echo $counts[$status]; ?></span>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Filters -->
    <div class="filter-bar">
        <form method="GET" action="" class="filter-form">
            <input type="hidden" name="status" value="<?php echo $statusFilter; ?>">
            <div class="filter-row">
                <div class="filter-group">
                    <input type="text" name="search" placeholder="Search by name, roll, or email..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="<?php echo url('admin/registrations.php?status=' . $statusFilter); ?>" class="btn btn-outline btn-sm">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <?php if (empty($requests)): ?>
        <div class="empty-state">
            <i class="fas fa-user-plus"></i>
            <h3>No Registration Requests</h3>
            <p>There are no <?php echo $statusFilter !== 'all' ? $statusFilter : ''; ?> registration requests to display.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Class Roll</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Requested</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $index => $request): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($request['full_name']); ?></strong>
                                <?php if ($request['review_notes']): ?>
                                    <br><small class="text-muted">Notes: <?php echo htmlspecialchars($request['review_notes']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo htmlspecialchars($request['class_roll']); ?></code></td>
                            <td><?php echo htmlspecialchars($request['email'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($request['department']); ?></td>
                            <td><?php echo formatDateTime($request['requested_at']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $request['status']; ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                                <?php if ($request['reviewed_by_name']): ?>
                                    <br><small class="text-muted">By: <?php echo htmlspecialchars($request['reviewed_by_name']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($request['status'] === 'pending'): ?>
                                    <div class="action-buttons">
                                        <button type="button" class="btn btn-sm btn-success" onclick="openApproveModal(<?php echo (int)$request['id']; ?>, <?php echo htmlspecialchars(json_encode($request['full_name'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8'); ?>)">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="openRejectModal(<?php echo (int)$request['id']; ?>, <?php echo htmlspecialchars(json_encode($request['full_name'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8'); ?>)">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </div>
                                <?php elseif ($request['status'] === 'rejected'): ?>
                                    <a href="<?php echo url('admin/registrations.php?action=delete&id=' . $request['id'] . '&csrf_token=' . Security::generateCSRFToken()); ?>" 
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Delete this request?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Approve Modal -->
<div class="modal-overlay" id="approveModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Approve Registration</h3>
            <button class="modal-close" onclick="closeModal('approveModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="" id="approveForm">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="registration_id" id="approveRegistrationId" value="">
            
            <div class="modal-body">
                <p>Approve registration for <strong id="approveName"></strong>?</p>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <span>A 6-digit PIN will be generated automatically. You must share it with the student.</span>
                </div>
                <div class="form-group">
                    <label for="approveNotes">Notes (optional)</label>
                    <textarea id="approveNotes" name="notes" class="form-control" rows="2" 
                              placeholder="Any additional notes about this approval"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('approveModal')">Cancel</button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check"></i> Approve & Generate PIN
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Reject Registration</h3>
            <button class="modal-close" onclick="closeModal('rejectModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="" id="rejectForm">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="registration_id" id="rejectRegistrationId" value="">
            
            <div class="modal-body">
                <p>Reject registration for <strong id="rejectName"></strong>?</p>
                <div class="form-group">
                    <label for="rejectNotes">Reason (optional)</label>
                    <textarea id="rejectNotes" name="notes" class="form-control" rows="3" 
                              placeholder="Enter reason for rejection..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('rejectModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-times"></i> Reject
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openApproveModal(id, name) {
    document.getElementById('approveName').textContent = name;
    document.getElementById('approveRegistrationId').value = id;
    document.getElementById('approveNotes').value = '';
    openModal('approveModal');
}

function openRejectModal(id, name) {
    document.getElementById('rejectName').textContent = name;
    document.getElementById('rejectRegistrationId').value = id;
    document.getElementById('rejectNotes').value = '';
    openModal('rejectModal');
}
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>