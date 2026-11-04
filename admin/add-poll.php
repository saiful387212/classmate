<?php
/**
 * Admin - Add Poll
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

$pageTitle = 'Create Poll';
$pageCss = ['admin.css', 'polls.css'];
$pageJs = ['add-poll.js'];
$isAdmin = true;

$db = Database::getInstance();
$adminBatchId = Authorization::getAdminBatchId();
$stmt = $db->query("SELECT id, name, label FROM batches WHERE is_active = 1 ORDER BY FIELD(name, 'First year', 'Second year', 'Third Year', 'Fourth year'), name");
$batches = $stmt->fetchAll();

$error = null;
$success = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_poll') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $options = $_POST['options'] ?? [];
        $status = $_POST['status'] ?? 'draft';
        $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
        $allow_multiple = isset($_POST['allow_multiple']) ? 1 : 0;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $class_id = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;
        $batchId = $adminBatchId ?? (int)($_POST['batch_id'] ?? 0);
        $batchCheck = $db->prepare("SELECT id FROM batches WHERE id = ? AND is_active = 1");
        $batchCheck->execute([$batchId]);
        if (!$batchId || !$batchCheck->fetchColumn()) {
            $error = 'Please select a valid batch.';
        } elseif ($class_id) {
            $classCheck = $db->prepare("SELECT id FROM classes WHERE id = ? AND batch_id = ?");
            $classCheck->execute([$class_id, $batchId]);
            if (!$classCheck->fetchColumn()) {
                $error = 'Select a class from the selected batch.';
            }
        }
        
        // Validate
        if ($error) {
            // Keep validation errors from batch checks intact.
        } elseif (empty($title)) {
            $error = 'Poll title is required.';
        } elseif (count($options) < 2) {
            $error = 'Please add at least 2 options.';
        } elseif (count(array_filter($options)) < 2) {
            $error = 'Please fill in at least 2 options.';
        } else {
            // Filter empty options
            $options = array_filter($options, function($opt) {
                return trim($opt) !== '';
            });
            
            if (count($options) < 2) {
                $error = 'Please fill in at least 2 options.';
            } else {
                // Create poll
                $db->beginTransaction();
                try {
                    // If class_id is empty, set to NULL
                    $classIdValue = $class_id ? $class_id : null;
                    
                    $stmt = $db->prepare("
                        INSERT INTO polls (
                            batch_id, title, description, status, is_anonymous,
                            allow_multiple, end_date, class_id, created_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $batchId,
                        $title, 
                        $description, 
                        $status, 
                        $is_anonymous,
                        $allow_multiple, 
                        $end_date, 
                        $classIdValue, 
                        Session::get('user_id')
                    ]);
                    
                    $pollId = $db->lastInsertId();
                    
                    // Add options
                    foreach ($options as $option) {
                        $stmt = $db->prepare("
                            INSERT INTO poll_options (poll_id, option_text) VALUES (?, ?)
                        ");
                        $stmt->execute([$pollId, trim($option)]);
                    }
                    
                    $db->commit();
                    $success = 'Poll created successfully!';
                    logActivity('poll_created', "Created poll: {$title}");
                    
                    // Redirect after success
                    Session::setFlash('success', 'Poll created successfully!');
                    redirect(url('admin/polls.php'));
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Failed to create poll: ' . $e->getMessage();
                }
            }
        }
    }
}

// Get classes for dropdown
$classSql = "SELECT id, class_title, class_code FROM classes WHERE status = 'scheduled'";
$classParams = [];
if ($adminBatchId !== null) {
    $classSql .= " AND batch_id = ?";
    $classParams[] = $adminBatchId;
}
$stmt = $db->prepare($classSql . " ORDER BY class_title");
$stmt->execute($classParams);
$classes = $stmt->fetchAll();

include_once BASE_PATH . '/includes/header.php';
include_once BASE_PATH . '/includes/sidebar-admin.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>Create Poll</h1>
                <p class="text-muted">Create a new poll for students to vote</p>
            </div>
            <a href="<?php echo url('admin/polls.php'); ?>" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Polls
            </a>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <form method="POST" action="" id="pollForm">
                <?php echo Security::csrfField(); ?>
                <input type="hidden" name="action" value="create_poll">

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
                    <label for="title">Poll Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title" class="form-control" 
                           placeholder="Enter poll title" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3" 
                              placeholder="Describe what this poll is about"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="class_id">Target Class (Optional)</label>
                        <select id="class_id" name="class_id" class="form-control">
                            <option value="">All Students</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>">
                                    <?php echo htmlspecialchars($class['class_code'] . ' - ' . $class['class_title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="form-help">Leave empty for all students</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date (Optional)</label>
                        <input type="datetime-local" id="end_date" name="end_date" class="form-control">
                        <span class="form-help">Leave empty for no end date</span>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Poll Settings</label>
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_anonymous" value="1" checked>
                                Anonymous Voting
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="allow_multiple" value="1">
                                Allow Multiple Selections
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="draft">Draft</option>
                            <option value="active" selected>Active</option>
                        </select>
                    </div>
                </div>
                
                <!-- ===== Poll Options ===== -->
                <div class="options-section">
                    <label>Poll Options <span class="required">*</span></label>
                    <p class="text-muted">Add at least 2 options for students to choose from</p>
                    
                    <div id="optionsContainer">
                        <div class="option-row">
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-circle"></i></span>
                                <input type="text" name="options[]" class="form-control" 
                                       placeholder="Option 1" required>
                                <button type="button" class="btn btn-danger remove-option" onclick="removeOption(this)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="option-row">
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-circle"></i></span>
                                <input type="text" name="options[]" class="form-control" 
                                       placeholder="Option 2" required>
                                <button type="button" class="btn btn-danger remove-option" onclick="removeOption(this)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-outline btn-sm" onclick="addOption()">
                        <i class="fas fa-plus"></i> Add Option
                    </button>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Poll
                    </button>
                    <a href="<?php echo url('admin/polls.php'); ?>" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let optionCount = 2;

function addOption() {
    optionCount++;
    const container = document.getElementById('optionsContainer');
    const row = document.createElement('div');
    row.className = 'option-row';
    row.innerHTML = `
        <div class="input-group">
            <span class="input-icon"><i class="fas fa-circle"></i></span>
            <input type="text" name="options[]" class="form-control" 
                   placeholder="Option ${optionCount}" required>
            <button type="button" class="btn btn-danger remove-option" onclick="removeOption(this)">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    container.appendChild(row);
}

function removeOption(btn) {
    const row = btn.closest('.option-row');
    const container = document.getElementById('optionsContainer');
    if (container.children.length > 2) {
        row.remove();
        optionCount--;
    } else {
        alert('You need at least 2 options.');
    }
}
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>