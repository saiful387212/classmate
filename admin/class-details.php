<?php
/**
 * Admin - Class Details with Secure File Upload
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

$pageTitle = 'Class Details';
$pageCss = ['admin.css', 'class-details.css'];
$pageJs = ['class-details.js'];
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

// ============================================
// FILE UPLOAD HANDLER
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        Session::setFlash('error', 'Invalid security token');
        redirect(url('admin/class-details.php?id=' . $id));
    }
    
    if ($action === 'upload_note') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $resourceType = $_POST['resource_type'] ?? 'file';
        $materialType = $_POST['material_type'] ?? 'note';
        $linkUrl = trim($_POST['link_url'] ?? '');
        
        if (empty($title)) {
            Session::setFlash('error', 'Title is required');
        } elseif ($resourceType === 'link' && (!filter_var($linkUrl, FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($linkUrl, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true))) {
            Session::setFlash('error', 'Please enter a valid link URL');
        } elseif ($resourceType !== 'link' && empty($_FILES['file']['name'])) {
            Session::setFlash('error', 'Please select a file to upload');
        } elseif ($resourceType === 'link') {
            $result = $classManager->addMaterial([
                'class_id' => $id,
                'title' => $title,
                'description' => $description,
                'material_type' => 'link',
                'link_url' => $linkUrl,
                'uploaded_by' => Session::get('user_id'),
                'is_public' => true
            ]);

            Session::setFlash(
                $result['success'] ? 'success' : 'error',
                $result['success'] ? 'Link added successfully!' : $result['message']
            );
        } else {
            // Create secure upload instance
            $upload = new SecureFileUpload($_FILES['file'], UPLOAD_PATH . '/notes');
            $upload->setAllowedExtensions(['pdf', 'doc', 'docx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'txt']);
            $upload->setMaxSize(10485760); // 10MB
            
            if ($upload->upload()) {
                $fileInfo = $upload->getFileInfo();
                $newFileName = $upload->getNewFileName();
                
                // Save to database
                $data = [
                    'class_id' => $id,
                    'title' => $title,
                    'description' => $description,
                    'material_type' => $materialType,
                    'file_path' => 'notes/' . $newFileName,
                    'file_name' => $fileInfo['original_name'],
                    'file_size' => $fileInfo['size'],
                    'mime_type' => $fileInfo['mime_type'] ?? $fileInfo['type'],
                    'uploaded_by' => Session::get('user_id'),
                    'is_public' => true
                ];
                
                $result = $classManager->addMaterial($data);
                if ($result['success']) {
                    Session::setFlash('success', 'File uploaded successfully!');
                } else {
                    // Delete uploaded file if database insert fails
                    @unlink(UPLOAD_PATH . '/notes/' . $newFileName);
                    Session::setFlash('error', $result['message']);
                }
            } else {
                Session::setFlash('error', $upload->getErrorMessage());
            }
        }
        redirect(url('admin/class-details.php?id=' . $id));
    }
}

// ============================================
// MATERIAL DELETE HANDLER
// ============================================
if (isset($_GET['delete_material']) && is_numeric($_GET['delete_material'])) {
    $materialId = (int)$_GET['delete_material'];
    
    // Verify CSRF
    if (!Security::verifyCSRFToken($_GET['csrf_token'] ?? '')) {
        Session::setFlash('error', 'Invalid security token');
        redirect(url('admin/class-details.php?id=' . $id));
    }
    
    // Get material info
    $stmt = $db->prepare("SELECT file_path FROM class_materials WHERE id = ? AND class_id = ?");
    $stmt->execute([$materialId, $id]);
    $material = $stmt->fetch();
    
    if ($material) {
        // Delete file from server
        if ($material['file_path']) {
            $filePath = UPLOAD_PATH . '/' . $material['file_path'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
        
        // Delete from database
        $stmt = $db->prepare("DELETE FROM class_materials WHERE id = ?");
        $result = $stmt->execute([$materialId]);
        
        if ($result) {
            logActivity('material_deleted', "Deleted material ID: {$materialId}");
            Session::setFlash('success', 'Material deleted successfully');
        } else {
            Session::setFlash('error', 'Failed to delete material');
        }
    } else {
        Session::setFlash('error', 'Material not found');
    }
    redirect(url('admin/class-details.php?id=' . $id));
}

// ============================================
// GET DATA
// ============================================

// Get enrolled students
$stmt = $db->prepare("
    SELECT s.*, ce.enrollment_date, ce.status as enrollment_status
    FROM students s
    JOIN class_enrollments ce ON s.id = ce.student_id
    WHERE ce.class_id = ?
    ORDER BY s.full_name
");
$stmt->execute([$id]);
$students = $stmt->fetchAll();

// Get materials with uploader info
$stmt = $db->prepare("
    SELECT m.*, a.full_name as uploaded_by_name
    FROM class_materials m
    LEFT JOIN admins a ON m.uploaded_by = a.id
    WHERE m.class_id = ?
    ORDER BY m.created_at DESC
");
$stmt->execute([$id]);
$materials = $stmt->fetchAll();

// Get assignments
$stmt = $db->prepare("
    SELECT * FROM assignments 
    WHERE class_id = ? 
    ORDER BY deadline ASC
");
$stmt->execute([$id]);
$assignments = $stmt->fetchAll();

include_once BASE_PATH . '/includes/header.php';
include_once BASE_PATH . '/includes/sidebar-admin.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1><?php echo htmlspecialchars($class['class_title']); ?></h1>
                <div class="class-meta">
                    <span class="class-code"><?php echo htmlspecialchars($class['class_code']); ?></span>
                    <span class="separator">|</span>
                    <span class="course-info"><?php echo htmlspecialchars($class['course_code'] . ' - ' . $class['course_title']); ?></span>
                    <span class="separator">|</span>
                    <span class="badge badge-<?php echo $class['status']; ?>"><?php echo ucfirst($class['status']); ?></span>
                </div>
            </div>
            <div class="page-actions">
                <a href="<?php echo url('admin/edit-class.php?id=' . $class['id']); ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Class
                </a>
                <?php if ($class['status'] === 'scheduled'): ?>
                    <a href="<?php echo url('admin/complete-class.php?id=' . $class['id']); ?>" class="btn btn-success">
                        <i class="fas fa-check"></i> Mark Complete
                    </a>
                <?php endif; ?>
                <a href="<?php echo url('admin/classes.php'); ?>" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
    
    <!-- ============================================
    CLASS INFORMATION CARD
    ============================================ -->
    <div class="class-info-card">
        <div class="info-grid">
            <div class="info-item">
                <label><i class="fas fa-book"></i> Course</label>
                <span><?php echo htmlspecialchars($class['course_code']); ?></span>
            </div>
            <div class="info-item">
                <label><i class="fas fa-user-tie"></i> Instructor</label>
                <span><?php echo htmlspecialchars($class['instructor']); ?></span>
            </div>
            <div class="info-item">
                <label><i class="fas fa-calendar-alt"></i> Date</label>
                <span><?php echo formatDate($class['class_date']); ?></span>
            </div>
            <div class="info-item">
                <label><i class="fas fa-clock"></i> Time</label>
                <span><?php echo date('h:i A', strtotime($class['start_time'])); ?> - <?php echo date('h:i A', strtotime($class['end_time'])); ?></span>
            </div>
            <div class="info-item">
                <label><i class="fas fa-map-marker-alt"></i> Room</label>
                <span><?php echo htmlspecialchars($class['room'] ?? 'TBA'); ?></span>
            </div>
            <div class="info-item">
                <label><i class="fas fa-users"></i> Enrolled</label>
                <span><?php echo count($students); ?> students</span>
            </div>
            <div class="info-item">
                <label><i class="fas fa-file-alt"></i> Materials</label>
                <span><?php echo count($materials); ?> files</span>
            </div>
            <div class="info-item">
                <label><i class="fas fa-tasks"></i> Assignments</label>
                <span><?php echo count($assignments); ?></span>
            </div>
        </div>
        
        <?php if ($class['topic']): ?>
            <div class="info-item full">
                <label><i class="fas fa-lightbulb"></i> Planned Topic</label>
                <span><?php echo htmlspecialchars($class['topic']); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($class['topic_conducted'] ?? false): ?>
            <div class="info-item full">
                <label><i class="fas fa-check-circle text-success"></i> Topic Conducted</label>
                <span><?php echo htmlspecialchars($class['topic_conducted']); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($class['class_task']): ?>
            <div class="info-item full">
                <label><i class="fas fa-tasks"></i> Class Task</label>
                <span><?php echo nl2br(htmlspecialchars($class['class_task'])); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($class['additional_info']): ?>
            <div class="info-item full">
                <label><i class="fas fa-info-circle"></i> Additional Information</label>
                <span><?php echo nl2br(htmlspecialchars($class['additional_info'])); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($class['description']): ?>
            <div class="info-item full">
                <label><i class="fas fa-align-left"></i> Description</label>
                <span><?php echo nl2br(htmlspecialchars($class['description'])); ?></span>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- ============================================
    MATERIALS / NOTES SECTION
    ============================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-file-alt text-primary"></i>
                Class Materials & Notes
            </h3>
            <button class="btn btn-primary" id="uploadMaterialButton" onclick="toggleUploadForm()">
                <i class="fas fa-upload"></i> Upload File
            </button>
        </div>
        
        <div class="card-body">
            <!-- Upload Form -->
            <div class="upload-form-wrapper" id="uploadForm" style="display:none;">
                <div class="upload-form">
                    <form method="POST" enctype="multipart/form-data" id="uploadMaterialForm">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="upload_note">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="title">Title <span class="required">*</span></label>
                                <input type="text" id="title" name="title" class="form-control" 
                                       placeholder="Enter file title" required>
                            </div>
                            <div class="form-group">
                                <label for="resource_type">Resource</label>
                                <select id="resource_type" name="resource_type" class="form-control">
                                    <option value="file">Upload File</option>
                                    <option value="link">Upload URL</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="material_type">Type</label>
                                <select id="material_type" name="material_type" class="form-control">
                                    <option value="note">Note</option>
                                    <option value="presentation">Presentation</option>
                                    <option value="document">Document</option>
                                    <option value="reference">Reference</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <input type="text" id="description" name="description" class="form-control" 
                                   placeholder="Brief description of the file">
                        </div>
                        
                        <div class="form-group" id="fileUploadFields">
                            <label for="file">Select File <span class="required">*</span></label>
                            <div class="file-drop-zone" id="fileDropZone">
                                <div class="drop-zone-content">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Drag & drop your file here or click to browse</p>
                                    <span class="file-types">Allowed: PDF, DOC, DOCX, PPT, PPTX, JPG, PNG, GIF, ZIP, TXT (Max 10MB)</span>
                                </div>
                                <input type="file" id="file" name="file" required>
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

                        <div class="form-group" id="linkFields" style="display:none;">
                            <label for="link_url">Upload URL <span class="required">*</span></label>
                            <input type="url" id="link_url" name="link_url" class="form-control"
                                   placeholder="https://example.com/resource">
                            <small class="form-help">Use a complete URL beginning with https:// or http://.</small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-success" id="materialSubmitButton">
                                <i class="fas fa-upload"></i> Upload File
                            </button>
                            <button type="button" class="btn btn-outline" onclick="toggleUploadForm()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Materials List -->
            <?php if (empty($materials)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <h4>No Materials</h4>
                    <p>No notes or materials have been uploaded for this class.</p>
                    <button class="btn btn-primary" onclick="toggleUploadForm()">
                        <i class="fas fa-upload"></i> Upload First File
                    </button>
                </div>
            <?php else: ?>
                <div class="materials-grid">
                    <?php foreach ($materials as $material): ?>
                        <div class="material-card">
                            <div class="material-card-header">
                                <div class="material-icon" style="background: <?php echo $material['link_url'] ? 'var(--info)' : SecureFileUpload::getFileColor($material['file_name']); ?>;">
                                    <i class="fas <?php echo $material['link_url'] ? 'fa-link' : SecureFileUpload::getFileIcon($material['file_name']); ?>"></i>
                                </div>
                                <div class="material-info">
                                    <h4><?php echo htmlspecialchars($material['title']); ?></h4>
                                    <span class="material-type badge badge-info">
                                        <?php echo ucfirst($material['material_type']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="material-card-body">
                                <?php if ($material['description']): ?>
                                    <p class="material-description"><?php echo htmlspecialchars($material['description']); ?></p>
                                <?php endif; ?>
                                
                                <div class="material-meta">
                                    <?php if (!$material['link_url']): ?>
                                    <span>
                                        <i class="fas fa-file"></i>
                                        <?php echo htmlspecialchars(basename($material['file_name'])); ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-weight-hanging"></i>
                                        <?php echo getFileSize($material['file_size']); ?>
                                    </span>
                                    <?php else: ?>
                                    <span>
                                        <i class="fas fa-link"></i> External resource
                                    </span>
                                    <?php endif; ?>
                                    <span>
                                        <i class="fas fa-clock"></i>
                                        <?php echo timeAgo($material['created_at']); ?>
                                    </span>
                                </div>
                                
                                <?php if ($material['uploaded_by_name']): ?>
                                    <div class="material-uploader">
                                        <i class="fas fa-user"></i>
                                        Uploaded by: <?php echo htmlspecialchars($material['uploaded_by_name']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="material-card-footer">
                                <?php if ($material['link_url']): ?>
                                    <a href="<?php echo htmlspecialchars($material['link_url']); ?>"
                                       class="btn btn-sm btn-primary" target="_blank" rel="noopener noreferrer">
                                        <i class="fas fa-external-link-alt"></i> Open Link
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo uploadUrl($material['file_path']); ?>"
                                       class="btn btn-sm btn-primary" target="_blank">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                <?php endif; ?>
                                <?php if (Authorization::isAdmin()): ?>
                                    <a href="<?php echo url('admin/class-details.php?id=' . $id . '&delete_material=' . $material['id'] . '&csrf_token=' . Security::generateCSRFToken()); ?>" 
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Delete this material? This action cannot be undone.')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ============================================
    STUDENTS ENROLLED
    ============================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-users text-primary"></i>
                Enrolled Students (<?php echo count($students); ?>)
            </h3>
        </div>
        <div class="card-body">
            <?php if (empty($students)): ?>
                <div class="empty-state small">
                    <i class="fas fa-users"></i>
                    <p>No students enrolled in this class.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Class Roll</th>
                                <th>Email</th>
                                <th>Enrolled</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $index => $student): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <a href="<?php echo url('admin/student-details.php?id=' . $student['id']); ?>">
                                            <?php echo htmlspecialchars($student['full_name']); ?>
                                        </a>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($student['class_roll']); ?></code></td>
                                    <td><?php echo htmlspecialchars($student['email'] ?: 'N/A'); ?></td>
                                    <td><?php echo formatDate($student['enrollment_date']); ?></td>
                                    <td><span class="badge badge-<?php echo $student['enrollment_status']; ?>"><?php echo ucfirst($student['enrollment_status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ============================================
    ASSIGNMENTS
    ============================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-tasks text-primary"></i>
                Assignments (<?php echo count($assignments); ?>)
            </h3>
            <a href="<?php echo url('admin/add-assignment.php?class_id=' . $id); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Assignment
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($assignments)): ?>
                <div class="empty-state small">
                    <i class="fas fa-tasks"></i>
                    <p>No assignments for this class.</p>
                    <a href="<?php echo url('admin/add-assignment.php?class_id=' . $id); ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Assignment
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Max Score</th>
                                <th>Deadline</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignments as $assignment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                    <td><?php echo ucfirst($assignment['assignment_type']); ?></td>
                                    <td><?php echo $assignment['max_score']; ?></td>
                                    <td><?php echo formatDateTime($assignment['deadline']); ?></td>
                                    <td><span class="badge badge-<?php echo $assignment['status']; ?>"><?php echo ucfirst($assignment['status']); ?></span></td>
                                    <td>
                                        <a href="<?php echo url('admin/assignment-details.php?id=' . $assignment['id']); ?>" class="btn btn-sm btn-info">
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
</div>

<script>
function toggleUploadForm() {
    const form = document.getElementById('uploadForm');
    const btn = document.getElementById('uploadMaterialButton');
    
    if (form.style.display === 'none') {
        form.style.display = 'block';
        btn.innerHTML = '<i class="fas fa-times"></i> Cancel Upload';
        btn.className = 'btn btn-danger';
    } else {
        form.style.display = 'none';
        btn.innerHTML = '<i class="fas fa-upload"></i> Upload File';
        btn.className = 'btn btn-primary';
        clearFile();
    }
}

// File Drag & Drop
const dropZone = document.getElementById('fileDropZone');
const fileInput = document.getElementById('file');
const filePreview = document.getElementById('filePreview');
const resourceType = document.getElementById('resource_type');
const fileUploadFields = document.getElementById('fileUploadFields');
const linkFields = document.getElementById('linkFields');
const linkUrl = document.getElementById('link_url');
const materialSubmitButton = document.getElementById('materialSubmitButton');

function toggleResourceType() {
    const isLink = resourceType && resourceType.value === 'link';
    if (fileUploadFields) fileUploadFields.style.display = isLink ? 'none' : 'block';
    if (linkFields) linkFields.style.display = isLink ? 'block' : 'none';
    if (fileInput) fileInput.required = !isLink;
    if (linkUrl) linkUrl.required = isLink;
    if (materialSubmitButton) {
        materialSubmitButton.innerHTML = isLink
            ? '<i class="fas fa-link"></i> Upload URL'
            : '<i class="fas fa-upload"></i> Upload File';
    }
}

resourceType?.addEventListener('change', toggleResourceType);
toggleResourceType();

if (dropZone) {
    dropZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });
    
    dropZone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
    });
    
    dropZone.addEventListener('drop',