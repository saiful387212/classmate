<?php
/**
 * Admin - Complete Class
 * Mark a class as completed with additional information
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

$pageTitle = 'Complete Class';
$pageCss = ['admin.css', 'forms.css'];
$isAdmin = true;

$id = $_GET['id'] ?? 0;
if (!$id) {
    Session::setFlash('error', 'Invalid class ID');
    redirect(url('admin/classes.php'));
}

$db = Database::getInstance();
$classManager = new ClassManager();

$class = $classManager->getById($id);
if (!$class) {
    Session::setFlash('error', 'Class not found');
    redirect(url('admin/classes.php'));
}

if ($class['status'] !== 'scheduled') {
    Session::setFlash('warning', 'This class is already completed or cancelled');
    redirect(url('admin/class-details.php?id=' . $id));
}

$error = null;
$formData = [
    'topic_conducted' => $class['topic'] ?? '',
    'class_task' => $class['class_task'] ?? '',
    'additional_info' => $class['additional_info'] ?? ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $data = [
            'topic_conducted' => trim($_POST['topic_conducted'] ?? ''),
            'class_task' => trim($_POST['class_task'] ?? ''),
            'additional_info' => trim($_POST['additional_info'] ?? '')
        ];
        
        $result = $classManager->completeClass($id, $data);
        
        if ($result['success']) {
            Session::setFlash('success', 'Class marked as completed successfully!');
            redirect(url('admin/class-details.php?id=' . $id));
        } else {
            $error = $result['message'];
            $formData = $data;
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
                <h1>Complete Class</h1>
                <p class="text-muted">Mark this class as completed and add information</p>
            </div>
            <a href="<?php echo url('admin/class-details.php?id=' . $id); ?>" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo htmlspecialchars($class['class_title']); ?></h3>
            <span class="badge badge-warning">Marking as Completed</span>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?php echo Security::csrfField(); ?>
                
                <div class="form-group">
                    <label for="topic_conducted">Topic Conducted</label>
                    <input type="text" id="topic_conducted" name="topic_conducted" class="form-control" 
                           placeholder="What topics were covered in this class?"
                           value="<?php echo htmlspecialchars($formData['topic_conducted']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="class_task">Class Task</label>
                    <textarea id="class_task" name="class_task" class="form-control" rows="2" 
                              placeholder="Tasks assigned during this class"><?php echo htmlspecialchars($formData['class_task']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="additional_info">Additional Information</label>
                    <textarea id="additional_info" name="additional_info" class="form-control" rows="2" 
                              placeholder="Any additional information about the class"><?php echo htmlspecialchars($formData['additional_info']); ?></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Mark as Completed
                    </button>
                    <a href="<?php echo url('admin/class-details.php?id=' . $id); ?>" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>