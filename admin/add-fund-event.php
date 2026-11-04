<?php
/**
 * Admin - Add Fund Event
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

Auth::requireAdmin();

$pageTitle = 'Create Fund Event';
$pageCss = ['admin.css', 'fund-events.css'];
$pageJs = ['add-fund-event.js'];
$isAdmin = true;

$db = Database::getInstance();
$adminBatchId = Authorization::getAdminBatchId();

$error = null;
$formData = [
    'class_id' => $_GET['class_id'] ?? '',
    'event_name' => '',
    'event_description' => '',
    'event_date' => date('Y-m-d'),
    'start_date' => date('Y-m-d'),
    'end_date' => date('Y-m-d', strtotime('+30 days')),
    'status' => 'active'
];

// Get classes for dropdown
$classSql = "SELECT id, class_title, class_code FROM classes WHERE status IN ('scheduled', 'completed')";
$classParams = [];
if ($adminBatchId !== null) {
    $classSql .= " AND batch_id = ?";
    $classParams[] = $adminBatchId;
}
$stmt = $db->prepare($classSql . " ORDER BY class_title");
$stmt->execute($classParams);
$classes = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $data = [
            'class_id' => $_POST['class_id'] ?? '',
            'event_name' => trim($_POST['event_name'] ?? ''),
            'event_description' => trim($_POST['event_description'] ?? ''),
            'event_date' => $_POST['event_date'] ?? '',
            'start_date' => $_POST['start_date'] ?? null,
            'end_date' => $_POST['end_date'] ?? null,
            'status' => $_POST['status'] ?? 'active',
            'created_by' => Session::get('user_id')
        ];
        
        // Validate
        if (empty($data['class_id'])) {
            $error = 'Please select a class.';
        } elseif (empty($data['event_name'])) {
            $error = 'Event name is required.';
        } elseif (empty($data['event_date'])) {
            $error = 'Event date is required.';
        } else {
            $classStmt = $db->prepare("SELECT batch_id FROM classes WHERE id = ?");
            $classStmt->execute([$data['class_id']]);
            $batchId = (int)($classStmt->fetchColumn() ?: 0);
            if (!$batchId || ($adminBatchId !== null && $batchId !== $adminBatchId)) {
                $error = 'Select a class from your assigned batch.';
            }
        }

        if (!$error) {
            $stmt = $db->prepare("
                INSERT INTO fund_events (
                    batch_id, class_id, event_name, event_description, event_date,
                    start_date, end_date, status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $batchId,
                $data['class_id'],
                $data['event_name'],
                $data['event_description'],
                $data['event_date'],
                $data['start_date'],
                $data['end_date'],
                $data['status'],
                $data['created_by']
            ]);
            
            if ($result) {
                $eventId = $db->lastInsertId();
                Session::setFlash('success', 'Fund event created successfully!');
                logActivity('fund_event_created', "Created fund event: {$data['event_name']}");
                redirect(url('admin/fund-events.php'));
            } else {
                $error = 'Failed to create fund event.';
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
                <h1>Create Fund Event</h1>
                <p class="text-muted">Create a new fund event to track class finances</p>
            </div>
            <a href="<?php echo url('admin/fund-events.php'); ?>" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Events
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
            <form method="POST" action="" class="event-form">
                <?php echo Security::csrfField(); ?>
                
                <div class="form-group">
                    <label for="class_id">Class <span class="required">*</span></label>
                    <select id="class_id" name="class_id" class="form-control" required>
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" 
                                    <?php echo $formData['class_id'] == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_code'] . ' - ' . $class['class_title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="event_name">Event Name <span class="required">*</span></label>
                    <input type="text" id="event_name" name="event_name" class="form-control" 
                           placeholder="Enter event name" 
                           value="<?php echo htmlspecialchars($formData['event_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="event_description">Description</label>
                    <textarea id="event_description" name="event_description" class="form-control" rows="3" 
                              placeholder="Describe the fund event"><?php echo htmlspecialchars($formData['event_description']); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="event_date">Event Date <span class="required">*</span></label>
                        <input type="date" id="event_date" name="event_date" class="form-control" 
                               value="<?php echo htmlspecialchars($formData['event_date']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_date">Start Date (Optional)</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" 
                               value="<?php echo htmlspecialchars($formData['start_date']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date (Optional)</label>
                        <input type="date" id="end_date" name="end_date" class="form-control" 
                               value="<?php echo htmlspecialchars($formData['end_date']); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="active" <?php echo $formData['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="closed" <?php echo $formData['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Event
                    </button>
                    <a href="<?php echo url('admin/fund-events.php'); ?>" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>