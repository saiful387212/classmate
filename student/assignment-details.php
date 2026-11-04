<?php
/**
 * Student - Assignment Details
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure student access
Auth::requireStudent();

$pageTitle = 'Assignment Details';
$pageCss = ['student.css', 'assignment-details.css'];
$pageJs = ['assignment-details.js'];
$isStudent = true;

$id = $_GET['id'] ?? 0;
if (!$id) {
    Session::setFlash('error', 'Invalid assignment ID');
    redirect(url('student/assignments.php'));
}

$db = Database::getInstance();
$assignmentManager = new AssignmentManager();
$studentId = Session::get('user_id');

// Get assignment details
$assignment = $assignmentManager->getById($id);
if (!$assignment) {
    Session::setFlash('error', 'Assignment not found');
    redirect(url('student/assignments.php'));
}

// Check if student is enrolled in the class
$stmt = $db->prepare("
    SELECT id FROM class_enrollments 
    WHERE student_id = ? AND class_id = ? AND status = 'active'
");
$stmt->execute([$studentId, $assignment['class_id']]);
if (!$stmt->fetch()) {
    Session::setFlash('error', 'You are not enrolled in this class');
    redirect(url('student/assignments.php'));
}

// Get student's submission
$stmt = $db->prepare("
    SELECT * FROM assignment_submissions 
    WHERE assignment_id = ? AND student_id = ?
");
$stmt->execute([$id, $studentId]);
$submission = $stmt->fetch();

// Calculate status
$now = time();
$deadline = strtotime($assignment['deadline']);
$hasSubmitted = $submission ? true : false;

if ($hasSubmitted) {
    $status = 'submitted';
    $statusLabel = 'Submitted';
    $statusBadge = 'success';
} elseif ($deadline < $now) {
    $status = 'overdue';
    $statusLabel = 'Overdue';
    $statusBadge = 'danger';
} elseif ($deadline - $now < 86400) { // Less than 24 hours
    $status = 'due_soon';
    $statusLabel = 'Due Soon';
    $statusBadge = 'warning';
} else {
    $status = 'open';
    $statusLabel = 'Open';
    $statusBadge = 'primary';
}

// Handle submission
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } elseif ($_POST['action'] === 'submit') {
        if ($hasSubmitted) {
            $error = 'You have already submitted this assignment.';
        } elseif ($deadline < $now) {
            $error = 'The deadline for this assignment has passed.';
        } else {
            $submissionText = trim($_POST['submission_text'] ?? '');
            $file = $_FILES['file'] ?? null;
            
            if (empty($submissionText) && !$file['name']) {
                $error = 'Please provide either submission text or upload a file.';
            } else {
                $filePath = null;
                $fileName = null;
                
                if ($file && $file['name']) {
                    $upload = new SecureFileUpload($file, UPLOAD_ASSIGNMENTS);
                    $upload->setAllowedExtensions(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'txt', 'jpg', 'jpeg', 'png', 'gif']);
                    $upload->setMaxSize(10485760);
                    
                    if ($upload->upload()) {
                        $fileInfo = $upload->getFileInfo();
                        $filePath = 'assignments/' . $upload->getNewFileName();
                        $fileName = $fileInfo['original_name'];
                    } else {
                        $error = $upload->getErrorMessage();
                    }
                }
                
                if (!$error) {
                    $stmt = $db->prepare("
                        INSERT INTO assignment_submissions (
                            assignment_id, student_id, submission_text, 
                            submission_file, submitted_at, status
                        ) VALUES (?, ?, ?, ?, NOW(), 'submitted')
                    ");
                    $result = $stmt->execute([
                        $id, 
                        $studentId, 
                        $submissionText, 
                        $filePath
                    ]);
                    
                    if ($result) {
                        $success = 'Assignment submitted successfully!';
                        logActivity('assignment_submitted', "Submitted assignment ID: {$id}");
                        // Refresh page to show updated status
                        header('Refresh: 2; URL=' . url('student/assignment-details.php?id=' . $id));
                    } else {
                        $error = 'Failed to submit assignment.';
                    }
                }
            }
        }
    }
}

include_once BASE_PATH . '/includes/header.php';
?>

<div class="dashboard-container">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1><?php echo htmlspecialchars($assignment['title']); ?></h1>
                <div class="assignment-meta">
                    <span class="class-info">
                        <i class="fas fa-book"></i>
                        <?php echo htmlspecialchars($assignment['class_code'] . ' - ' . $assignment['class_title']); ?>
                    </span>
                    <span class="separator">|</span>
                    <span class="course-info">
                        <i class="fas fa-graduation-cap"></i>
                        <?php echo htmlspecialchars($assignment['course_code']); ?>
                    </span>
                    <span class="separator">|</span>
                    <span class="badge badge-<?php echo $statusBadge; ?>">
                        <?php echo $statusLabel; ?>
                    </span>
                </div>
            </div>
            <a href="<?php echo url('student/assignments.php'); ?>" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Assignments
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
    
    <!-- ===== Assignment Info Card ===== -->
    <div class="assignment-info-card">
        <div class="info-grid">
            <div class="info-item">
                <label><i class="fas fa-tag"></i> Type</label>
                <span><?php echo ucfirst($assignment['assignment_type']); ?></span>
            </div>
            <div class="info-item">
                <label><i class="fas fa-star"></i> Max Score</label>
                <span><?php echo $assignment['max_score']; ?></span>
            </div>
            <div class="info-item">
                <label><i class="fas fa-calendar-plus"></i> Given Date</label>
                <span><?php echo formatDate($assignment['given_date']); ?></span>
            </div>
            <div class="info-item">
                <label><i class="fas fa-clock"></i> Deadline</label>
                <span class="<?php echo $deadline < $now ? 'text-danger' : ''; ?>">
                    <?php echo formatDateTime($assignment['deadline']); ?>
                </span>
            </div>
            <div class="info-item">
                <label><i class="fas fa-check-circle"></i> Status</label>
                <span class="badge badge-<?php echo $statusBadge; ?>">
                    <?php echo $statusLabel; ?>
                </span>
            </div>
            <div class="info-item">
                <label><i class="fas fa-hourglass-half"></i> Time Remaining</label>
                <span id="countdownTimer" data-deadline="<?php echo $deadline; ?>">
                    <?php echo $assignmentManager->getTimeRemaining($assignment['deadline']); ?>
                </span>
            </div>
        </div>
        
        <?php if ($assignment['description']): ?>
            <div class="info-item full">
                <label><i class="fas fa-align-left"></i> Description</label>
                <span><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($assignment['instructions']): ?>
            <div class="info-item full">
                <label><i class="fas fa-info-circle"></i> Instructions</label>
                <span><?php echo nl2br(htmlspecialchars($assignment['instructions'])); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($assignment['file_name']): ?>
            <div class="info-item full">
                <label><i class="fas fa-paperclip"></i> Attachment</label>
                <span>
                    <a href="<?php echo uploadUrl($assignment['file_path']); ?>" class="btn btn-sm btn-info" target="_blank">
                        <i class="fas fa-download"></i> <?php echo htmlspecialchars($assignment['file_name']); ?>
                    </a>
                </span>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- ===== Submission Section ===== -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <?php if ($hasSubmitted): ?>
                    <i class="fas fa-check-circle text-success"></i> Your Submission
                <?php else: ?>
                    <i class="fas fa-upload text-primary"></i> Submit Your Assignment
                <?php endif; ?>
            </h3>
        </div>
        <div class="card-body">
            <?php if ($hasSubmitted): ?>
                <!-- ===== View Submission ===== -->
                <div class="submission-details">
                    <div class="detail-row">
                        <label>Submitted:</label>
                        <span><?php echo formatDateTime($submission['submitted_at']); ?></span>
                    </div>
                    <div class="detail-row">
                        <label>Status:</label>
                        <span class="badge badge-<?php echo $submission['status']; ?>">
                            <?php echo ucfirst($submission['status']); ?>
                        </span>
                    </div>
                    <?php if ($submission['score'] !== null): ?>
                        <div class="detail-row">
                            <label>Score:</label>
                            <span><strong><?php echo $submission['score']; ?>%</strong></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($submission['feedback']): ?>
                        <div class="detail-row">
                            <label>Feedback:</label>
                            <span><?php echo nl2br(htmlspecialchars($submission['feedback'])); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($submission['submission_text']): ?>
                        <div class="detail-row">
                            <label>Your Submission:</label>
                            <span><?php echo nl2br(htmlspecialchars($submission['submission_text'])); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($submission['submission_file']): ?>
                        <div class="detail-row">
                            <label>File:</label>
                            <span>
                                <a href="<?php echo uploadUrl($submission['submission_file']); ?>" class="btn btn-sm btn-primary" target="_blank">
                                    <i class="fas fa-download"></i> Download File
                                </a>
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($submission['status'] === 'graded' || $submission['status'] === 'returned'): ?>
                        <div class="alert alert-success" style="margin-top: 15px;">
                            <i class="fas fa-check-circle"></i>
                            Your assignment has been graded. Check your score and feedback above.
                        </div>
                    <?php endif; ?>
                </div>
                
            <?php elseif ($deadline < $now): ?>
                <!-- ===== Overdue ===== -->
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <strong>Deadline Passed!</strong>
                    The deadline for this assignment has passed. You can no longer submit.
                </div>
                
            <?php else: ?>
                <!-- ===== Submit Form ===== -->
                <form method="POST" action="" enctype="multipart/form-data" class="submission-form">
                    <?php echo Security::csrfField(); ?>
                    <input type="hidden" name="action" value="submit">
                    
                    <div class="form-group">
                        <label for="submission_text">Submission Text</label>
                        <textarea id="submission_text" name="submission_text" class="form-control" rows="6" 
                                  placeholder="Type your submission here..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Or Upload File</label>
                        <div class="file-drop-zone" id="fileDropZone">
                            <div class="drop-zone-content">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Drag & drop your file here or click to browse</p>
                                <span class="file-types">Allowed: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, ZIP, TXT (Max 10MB)</span>
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
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Assignment
                        </button>
                    </div>
                </form>
                
                <div class="alert alert-info" style="margin-top: 15px;">
                    <i class="fas fa-info-circle"></i>
                    You can submit either text or a file, or both. The deadline is <strong><?php echo formatDateTime($assignment['deadline']); ?></strong>.
                </div>
            <?php endif; ?>
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
        'zip': 'fa-file-archive',
        'txt': 'fa-file-alt'
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

// Live countdown timer
document.addEventListener('DOMContentLoaded', function() {
    const timerElement = document.getElementById('countdownTimer');
    if (!timerElement) return;
    
    const deadline = parseInt(timerElement.dataset.deadline) * 1000;
    
    function updateCountdown() {
        const now = Date.now();
        const diff = deadline - now;
        
        if (diff <= 0) {
            timerElement.textContent = 'Deadline Passed';
            timerElement.style.color = '#EF4444';
            return;
        }
        
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);
        
        let timeString = '';
        if (days > 0) timeString += days + 'd ';
        if (hours > 0 || days > 0) timeString += hours + 'h ';
        if (minutes > 0 || hours > 0 || days > 0) timeString += minutes + 'm ';
        timeString += seconds + 's';
        
        timerElement.textContent = timeString;
        
        // Change color when less than 24 hours
        if (diff < 86400000) {
            timerElement.style.color = '#EF4444';
        } else if (diff < 172800000) {
            timerElement.style.color = '#F59E0B';
        } else {
            timerElement.style.color = '#10B981';
        }
    }
    
    updateCountdown();
    setInterval(updateCountdown, 1000);
});
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>