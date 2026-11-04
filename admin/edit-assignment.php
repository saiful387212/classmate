<?php
/**
 * Admin - Edit Assignment
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

$pageTitle = 'Edit Assignment';
$pageCss = ['admin.css', 'forms.css'];
$pageJs = ['add-assignment.js'];
$isAdmin = true;

$id = $_GET['id'] ?? 0;
if (!$id) {
    Session::setFlash('error', 'Invalid assignment ID');
    redirect(url('admin/assignments.php'));
}

$db = Database::getInstance();
$assignmentManager = new AssignmentManager();

$assignment = $assignmentManager->getById($id);
if (!$assignment) {
    Session::setFlash('error', 'Assignment not found');
    redirect(url('admin/assignments.php'));
}

$error = null;
$formData = $assignment;

// Get classes for dropdown
$stmt = $db->query("
    SELECT c.id, c.class_title, c.class_code, crs.course_code 
    FROM classes c
    JOIN courses crs ON c.course_id = crs.id
    WHERE c.status = 'scheduled' OR c.status = 'completed'
    ORDER BY c.class_date DESC
");
$classes = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $data = [
            'class_id' => $_POST['class_id'] ?? '',
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'assignment_type' => $_POST['assignment_type'] ?? 'individual',
            'max_score' => $_POST['max_score'] ?? 100,
            'given_date' => $_POST['given_date'] ?? '',
            'deadline' => $_POST['deadline'] ?? '',
            'instructions' => trim($_POST['instructions'] ?? ''),
            'is_mandatory' => isset($_POST['is_mandatory']) ? 1 : 0,
            'status' => $_POST['status'] ?? 'draft'
        ];
        
        // Validate
        if (empty($data['class_id'])) {
            $error = 'Please select a class';
        } elseif (empty($data['title'])) {
            $error = 'Assignment title is required';
        } elseif (empty($data['given_date'])) {
            $error = 'Given date is required';
        } elseif (empty($data['deadline'])) {
            $error = 'Deadline is required';
        } elseif (strtotime($data['deadline']) <= strtotime($data['given_date'])) {
            $error = 'Deadline must be after the given date';
        } else {
            // Handle file upload
            if (!empty($_FILES['file']['name'])) {
                $upload = new SecureFileUpload($_FILES['file'], UPLOAD_ASSIGNMENTS);
                $upload->setAllowedExtensions(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'jpg', 'jpeg', 'png', 'gif']);
                $upload->setMaxSize(10485760);
                
                if ($upload->upload()) {
                    $fileInfo = $upload->getFileInfo();
                    $data['file_path'] = 'assignments/' . $upload->getNewFileName();
                    $data['file_name'] = $fileInfo['original_name'];
                    
                    // Delete old file
                    if ($assignment['file_path']) {
                        $oldFile = UPLOAD_PATH . '/' . $assignment['file_path'];
                        if (file_exists($oldFile)) {
                            @unlink($oldFile);
                        }
                    }
                } else {
                    $error = $upload->getErrorMessage();
                }
            }
            
            if (!$error) {
                $result = $assignmentManager->update($id, $data);
                if ($result['success']) {
                    Session::setFlash('success', 'Assignment updated successfully!');
                    redirect(url('admin/assignments.php'));
                } else {
                    $error = $result['message'];
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
                <h1>Edit Assignment</h1>
                <p class="text-muted">Update assignment details</p>
            </div>
            <a href="<?php echo url('admin/assignments.php'); ?>" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Assignments
            </a>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data" class="assignment-form" data-validate>
                <?php echo Security::csrfField(); ?>
                
                <!-- Same fields as add-assignment.php with values pre-filled -->
                <div class="form-group">
                    <label for="class_id">Class <span class="required">*</span></label>
                    <select id="class_id" name="class_id" class="form-control" required>
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" 
                                    <?php echo $formData['class_id'] == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_code'] . ' - ' . $class['class_title']); ?>
                                (<?php echo htmlspecialchars($class['course_code']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="title">Assignment Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title" class="form-control" 
                           placeholder="Enter assignment title" 
                           value="<?php echo htmlspecialchars($formData['title']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="4" 
                              placeholder="Describe the assignment"><?php echo htmlspecialchars($formData['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="assignment_type">Assignment Type</label>
                        <select id="assignment_type" name="assignment_type" class="form-control">
                            <option value="individual" <?php echo ($formData['assignment_type'] ?? '') === 'individual' ? 'selected' : ''; ?>>Individual</option>
                            <option value="group" <?php echo ($formData['assignment_type'] ?? '') === 'group' ? 'selected' : ''; ?>>Group</option>
                            <option value="quiz" <?php echo ($formData['assignment_type'] ?? '') === 'quiz' ? 'selected' : ''; ?>>Quiz</option>
                            <option value="exam" <?php echo ($formData['assignment_type'] ?? '') === 'exam' ? 'selected' : ''; ?>>Exam</option>
                            <option value="project" <?php echo ($formData['assignment_type'] ?? '') === 'project' ? 'selected' : ''; ?>>Project</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="max_score">Maximum Score</label>
                        <input type="number" id="max_score" name="max_score" class="form-control" 
                               value="<?php echo htmlspecialchars($formData['max_score'] ?? ''); ?>" min="0" step="0.5">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="given_date">Given Date <span class="required">*</span></label>
                        <input type="date" id="given_date" name="given_date" class="form-control" 
                               value="<?php echo htmlspecialchars(isset($formData['given_date']) ? explode(' ', $formData['given_date'])[0] : ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="deadline">Deadline <span class="required">*</span></label>
                        <input type="datetime-local" id="deadline" name="deadline" class="form-control" 
                               value="<?php echo htmlspecialchars(isset($formData['deadline']) ? date('Y-m-d\TH:i', strtotime($formData['deadline'])) : ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="instructions">Instructions</label>
                    <textarea id="instructions" name="instructions" class="form-control" rows="3" 
                              placeholder="Additional instructions for students"><?php echo htmlspecialchars($formData['instructions'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="file">Assignment File (Optional)</label>
                    <?php if (!empty($formData['file_name'])): ?>
                        <div class="current-file" style="margin-bottom: 10px; padding: 10px; background: var(--bg-light); border-radius: var(--radius-md);">
                            <p style="margin: 0;">Current file: <strong><?php echo htmlspecialchars($formData['file_name']); ?></strong></p>
                            <p class="text-muted" style="margin: 5px 0 0; font-size: 0.85em;">Upload a new file to replace the current one.</p>
                        </div>
                    <?php endif; ?>
                    <div class="file-drop-zone" id="fileDropZone">
                        <div class="drop-zone-content">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Drag & drop your file here or click to browse</p>
                            <span class="file-types">Allowed: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, ZIP, JPG, PNG (Max 10MB)</span>
                        </div>
                        <input type="file" id="file" name="file">
                    </div>
                    <div class="file-preview" id="filePreview" style="display:none;">
                        <i class="fas fa-file"></i>
                        <span id="fileName">file.pdf</span>
                        <span id="fileSize">0 KB</span>
                        <button type="button" onclick="clearFile()" class="btn btn-sm btn-danger">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="draft" <?php echo ($formData['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="published" <?php echo ($formData['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                            <option value="open" <?php echo ($formData['status'] ?? '') === 'open' ? 'selected' : ''; ?>>Open</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="switch-label">
                            <div class="switch">
                                <input type="checkbox" id="is_mandatory" name="is_mandatory" 
                                       <?php echo !empty($formData['is_mandatory']) ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </div>
                            Mandatory Assignment
                        </label>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Assignment
                    </button>
                    <a href="<?php echo url('admin/assignments.php'); ?>" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// File upload handling
const dropZone = document.getElementById('fileDropZone');
const fileInput = document.getElementById('file');
const filePreview = document.getElementById('filePreview');

if (dropZone) {
    dropZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });
    
    dropZone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
    });
    
    dropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            updateFilePreview(e.dataTransfer.files[0]);
        }
    });
    
    dropZone.addEventListener('click', function() {
        fileInput.click();
    });
}

fileInput.addEventListener('change', function() {
    if (this.files.length) {
        updateFilePreview(this.files[0]);
    }
});

function updateFilePreview(file) {
    const preview = document.getElementById('filePreview');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    
    fileName.textContent = file.name;
    fileSize.textContent = formatFileSize(file.size);
    preview.style.display = 'flex';
    dropZone.style.display = 'none';
    
    // Update file icon
    const icon = preview.querySelector('i');
    const ext = file.name.split('.').pop().toLowerCase();
    const iconMap = {
        'pdf': 'fa-file-pdf',
        'doc': 'fa-file-word',
        'docx': 'fa-file-word',
        'xls': 'fa-file-excel',
        'xlsx': 'fa-file-excel',
        'ppt': 'fa-file-powerpoint',
        'pptx': 'fa-file-powerpoint',
        'zip': 'fa-file-archive'
    };
    icon.className = 'fas ' + (iconMap[ext] || 'fa-file');
}

function clearFile() {
    const preview = document.getElementById('filePreview');
    const dropZone = document.getElementById('fileDropZone');
    const fileInput = document.getElementById('file');
    
    preview.style.display = 'none';
    dropZone.style.display = 'block';
    fileInput.value = '';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>