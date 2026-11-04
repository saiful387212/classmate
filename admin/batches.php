<?php
/**
 * Admin - Batch Management
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

Authorization::requireSuperAdmin();

$pageTitle = 'Batch Management';
$pageCss = ['admin.css', 'forms.css'];
$isAdmin = true;
$db = Database::getInstance();
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_batch') {
            $name = trim($_POST['name'] ?? '');
            $label = trim($_POST['label'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $session = trim($_POST['session'] ?? '');

            if ($name === '' || strlen($name) > 50) {
                $error = 'Batch name is required and must be 50 characters or fewer.';
            } else {
                try {
                    $stmt = $db->prepare("INSERT INTO batches (name, label, department, session, is_active) VALUES (?, ?, ?, ?, 1)");
                    $stmt->execute([$name, $label ?: null, $department ?: null, $session ?: null]);
                    $success = 'Batch created.';
                    logActivity('batch_created', "Created batch: {$name}");
                } catch (PDOException $e) {
                    $error = $e->getCode() === '23000' ? 'A batch with this name already exists.' : 'Could not create the batch.';
                }
            }
        } elseif ($action === 'assign_admin') {
            $adminId = (int)($_POST['admin_id'] ?? 0);
            $batchId = ($_POST['batch_id'] ?? '') === '' ? null : (int)$_POST['batch_id'];

            if (!$adminId || $adminId === (int)Session::get('user_id')) {
                $error = 'Select another admin account to change.';
            } elseif ($batchId !== null) {
                $stmt = $db->prepare("SELECT id FROM batches WHERE id = ? AND is_active = 1");
                $stmt->execute([$batchId]);
                if (!$stmt->fetchColumn()) {
                    $error = 'Select a valid active batch.';
                }
            }

            if (!$error) {
                $stmt = $db->prepare("UPDATE admins SET batch_id = ?, updated_at = NOW() WHERE id = ? AND status = 'active'");
                $stmt->execute([$batchId, $adminId]);
                if ($stmt->rowCount() > 0) {
                    $success = 'Admin batch assignment updated.';
                    logActivity('admin_batch_assigned', "Updated batch assignment for admin ID: {$adminId}");
                } else {
                    $error = 'Admin account was not found or its assignment did not change.';
                }
            }
        } elseif ($action === 'set_batch_status') {
            $batchId = (int)($_POST['batch_id'] ?? 0);
            $isActive = (int)($_POST['is_active'] ?? -1);
            if (!$batchId || !in_array($isActive, [0, 1], true)) {
                $error = 'Invalid batch status request.';
            } else {
                $stmt = $db->prepare("UPDATE batches SET is_active = ? WHERE id = ?");
                $stmt->execute([$isActive, $batchId]);
                $success = 'Batch status updated.';
            }
        }
    }
}

$stmt = $db->query("SELECT id, name, label, department, session, is_active FROM batches ORDER BY is_active DESC, FIELD(name, 'First year', 'Second year', 'Third Year', 'Fourth year'), name");
$batches = $stmt->fetchAll();

$stmt = $db->prepare("
    SELECT a.id, a.full_name, a.username, a.email, a.batch_id, b.name AS batch_name, b.label AS batch_label
    FROM admins a
    LEFT JOIN batches b ON b.id = a.batch_id
    WHERE a.status = 'active' AND a.id <> ?
    ORDER BY a.full_name
");
$stmt->execute([Session::get('user_id')]);
$admins = $stmt->fetchAll();

include_once BASE_PATH . '/includes/header.php';
include_once BASE_PATH . '/includes/sidebar-admin.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>Batch Management</h1>
                <p class="text-muted">Manage registration batches and batch administrators</p>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Create Batch</h3></div>
        <div class="card-body">
            <form method="POST" class="form-row">
                <?php echo Security::csrfField(); ?>
                <input type="hidden" name="action" value="create_batch">
                <div class="form-group">
                    <label for="batch-name">Name</label>
                    <input id="batch-name" name="name" class="form-control" maxlength="50" required>
                </div>
                <div class="form-group">
                    <label for="batch-label">Display label</label>
                    <input id="batch-label" name="label" class="form-control" maxlength="100" placeholder="Optional">
                </div>
                <div class="form-group">
                    <label for="batch-session">Session</label>
                    <input id="batch-session" name="session" class="form-control" maxlength="20" placeholder="Optional">
                </div>
                <div class="form-group">
                    <label for="batch-department">Department</label>
                    <input id="batch-department" name="department" class="form-control" maxlength="100">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Create</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Batches</h3></div>
        <div class="card-body table-responsive">
            <table class="table">
                <thead><tr><th>Batch</th><th>Session</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($batches as $batch): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($batch['label'] ?: 'Batch ' . $batch['name']); ?></td>
                            <td><?php echo htmlspecialchars($batch['session'] ?? ''); ?></td>
                            <td><?php echo $batch['is_active'] ? 'Active' : 'Inactive'; ?></td>
                            <td>
                                <form method="POST">
                                    <?php echo Security::csrfField(); ?>
                                    <input type="hidden" name="action" value="set_batch_status">
                                    <input type="hidden" name="batch_id" value="<?php echo (int)$batch['id']; ?>">
                                    <input type="hidden" name="is_active" value="<?php echo $batch['is_active'] ? 0 : 1; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline">
                                        <?php echo $batch['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$batches): ?><tr><td colspan="4">No batches created.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Batch Admins</h3></div>
        <div class="card-body table-responsive">
            <table class="table">
                <thead><tr><th>Admin</th><th>Username</th><th>Batch</th><th>Save</th></tr></thead>
                <tbody>
                    <?php foreach ($admins as $admin): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($admin['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($admin['username']); ?></td>
                            <td colspan="2">
                                <form method="POST" class="form-row">
                                    <?php echo Security::csrfField(); ?>
                                    <input type="hidden" name="action" value="assign_admin">
                                    <input type="hidden" name="admin_id" value="<?php echo (int)$admin['id']; ?>">
                                    <select name="batch_id" class="form-control">
                                        <option value="">Super admin (all batches)</option>
                                        <?php foreach ($batches as $batch): if (!$batch['is_active']) continue; ?>
                                            <option value="<?php echo (int)$batch['id']; ?>" <?php echo (int)$admin['batch_id'] === (int)$batch['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($batch['label'] ?: 'Batch ' . $batch['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$admins): ?><tr><td colspan="4">No other active admin accounts found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>