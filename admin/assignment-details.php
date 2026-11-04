<?php
/**
 * Admin - Assignment Details
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

$pageTitle = 'Assignment Details';
$pageCss = ['admin.css', 'assignment-details.css'];
$pageJs = ['assignment-details.js'];
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

// Get submissions
$stmt = $db->prepare("
    SELECT 
        sub.*,
        s.full_name,
        s.class_roll,
        s.email,
        s.phone
    FROM assignment_submissions sub
    JOIN students s ON sub.student_id = s.id
    WHERE sub.assignment_id = ?
    ORDER BY sub.submitted_at DESC
");
$stmt->execute([$id]);
$submissions = $stmt->fetchAll();

// Get submission stats
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN status = 'graded' THEN 1 ELSE 0 END) as graded,
        SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
    FROM assignment_submissions
    WHERE assignment_id = ?
");
$stmt->execute([$id]);
$submissionStats = $stmt->fetch();

// Get enrolled students count
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT student_id) as total_enrolled
    FROM class_enrollments
    WHERE class_id = ? AND status = 'active'
");
$stmt->execute([$assignment['class_id']]);
$totalEnrolled = $stmt->fetchColumn();

// Calculate submission percentage
$submissionPercentage = $totalEnrolled > 0 ? round(($submissionStats['submitted'] / $totalEnrolled) * 100, 1) : 0;

include_once BASE_PATH . '/includes/header.php';
include_once BASE_PATH . '/includes/sidebar-admin.php';
?>

<div class="main-content">
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
                    <span class="badge badge-<?php echo $assignment['status']; ?>">
                        <?php echo ucfirst($assignment['status']); ?>
                    </span>
                </div>
            </div>
            <div class="page-actions">
                <a href="<?php echo url('admin/edit-assignment.php?id=' . $assignment['id']); ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="<?php echo url('admin/assignments.php'); ?>" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
    
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
                <span class="<?php echo strtotime($assignment['deadline']) < time() ? 'text-danger' : ''; ?>">
                    <?php echo formatDateTime($assignment['deadline']); ?>
                </span>
            </div>
            <div class="info-item">
                <label><i class="fas fa-check-circle"></i> Status</label>
                <span class="badge badge-<?php echo $assignment['status']; ?>">
                    <?php echo ucfirst($assignment['status']); ?>
                </span>
            </div>
            <div class="info-item">
                <label><i class="fas fa-users"></i> Submissions</label>
                <span><?php echo $submissionStats['submitted']; ?> / <?php echo $totalEnrolled; ?> (<?php echo $submissionPercentage; ?>%)</span>
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
    
    <!-- ===== Submission Stats ===== -->
    <div class="submission-stats">
        <div class="stat-item">
            <span class="stat-value"><?php echo $totalEnrolled; ?></span>
            <span class="stat-label">Total Enrolled</span>
        </div>
        <div class="stat-item stat-submitted">
            <span class="stat-value"><?php echo $submissionStats['submitted']; ?></span>
            <span class="stat-label">Submitted</span>
        </div>
        <div class="stat-item stat-graded">
            <span class="stat-value"><?php echo $submissionStats['graded']; ?></span>
            <span class="stat-label">Graded</span>
        </div>
        <div class="stat-item stat-late">
            <span class="stat-value"><?php echo $submissionStats['late']; ?></span>
            <span class="stat-label">Late</span>
        </div>
        <div class="stat-item stat-returned">
            <span class="stat-value"><?php echo $submissionStats['returned']; ?></span>
            <span class="stat-label">Returned</span>
        </div>
    </div>
    
    <!-- ===== Submissions Table ===== -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-list"></i> Student Submissions</h3>
            <span class="badge badge-info"><?php echo count($submissions); ?> submissions</span>
        </div>
        <div class="card-body">
            <?php if (empty($submissions)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h4>No Submissions Yet</h4>
                    <p>Students haven't submitted this assignment yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <th>Class Roll</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th>Score</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($submissions as $index => $submission): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <a href="<?php echo url('admin/student-details.php?id=' . $submission['student_id']); ?>">
                                            <?php echo htmlspecialchars($submission['full_name']); ?>
                                        </a>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($submission['class_roll']); ?></code></td>
                                    <td><?php echo timeAgo($submission['submitted_at']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $submission['status']; ?>">
                                            <?php echo ucfirst($submission['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($submission['score'] !== null): ?>
                                            <strong><?php echo $submission['score']; ?>%</strong>
                                        <?php else: ?>
                                            <span class="text-muted">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick="viewSubmission(<?php echo $submission['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($submission['status'] === 'submitted' || $submission['status'] === 'late'): ?>
                                            <button class="btn btn-sm btn-success" onclick="gradeSubmission(<?php echo $submission['id']; ?>, '<?php echo htmlspecialchars($submission['full_name']); ?>')">
                                                <i class="fas fa-graduation-cap"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($submission['submission_file']): ?>
                                            <a href="<?php echo uploadUrl($submission['submission_file']); ?>" class="btn btn-sm btn-primary" target="_blank">
                                                <i class="fas fa-download"></i>
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
    </div>
</div>

<!-- ===== View Submission Modal ===== -->
<div class="modal-overlay" id="viewSubmissionModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>Submission Details</h3>
            <button class="modal-close" onclick="closeModal('viewSubmissionModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="submissionContent">
            <div class="loading-spinner"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('viewSubmissionModal')">Close</button>
        </div>
    </div>
</div>

<!-- ===== Grade Submission Modal ===== -->
<div class="modal-overlay" id="gradeModal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3>Grade Submission</h3>
            <button class="modal-close" onclick="closeModal('gradeModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="" id="gradeForm">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="grade">
            <input type="hidden" name="submission_id" id="gradeSubmissionId">
            
            <div class="modal-body">
                <p>Grade submission for <strong id="gradeStudentName"></strong></p>
                
                <div class="form-group">
                    <label for="score">Score (%)</label>
                    <input type="number" id="score" name="score" class="form-control" 
                           placeholder="Enter score" min="0" max="100" step="0.5" required>
                </div>
                
                <div class="form-group">
                    <label for="feedback">Feedback</label>
                    <textarea id="feedback" name="feedback" class="form-control" rows="4" 
                              placeholder="Provide feedback for the student"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('gradeModal')">Cancel</button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Submit Grade
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function viewSubmission(id) {
    const modal = document.getElementById('viewSubmissionModal');
    const content = document.getElementById('submissionContent');
    
    content.innerHTML = '<div class="loading-spinner"></div>';
    openModal('viewSubmissionModal');
    
    fetch('<?php echo url('api/assignment-api.php'); ?>?action=get_submission&id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const sub = data.submission;
                content.innerHTML = `
                    <div class="submission-details">
                        <div class="detail-row">
                            <label>Student:</label>
                            <span>${sub.full_name} (${sub.class_roll})</span>
                        </div>
                        <div class="detail-row">
                            <label>Submitted:</label>
                            <span>${sub.submitted_at}</span>
                        </div>
                        <div class="detail-row">
                            <label>Status:</label>
                            <span class="badge badge-${sub.status}">${sub.status}</span>
                        </div>
                        ${sub.score !== null ? `
                        <div class="detail-row">
                            <label>Score:</label>
                            <span><strong>${sub.score}%</strong></span>
                        </div>
                        ` : ''}
                        ${sub.feedback ? `
                        <div class="detail-row">
                            <label>Feedback:</label>
                            <span>${sub.feedback}</span>
                        </div>
                        ` : ''}
                        ${sub.submission_text ? `
                        <div class="detail-row">
                            <label>Submission Text:</label>
                            <span>${sub.submission_text}</span>
                        </div>
                        ` : ''}
                        ${sub.submission_file ? `
                        <div class="detail-row">
                            <label>File:</label>
                            <span>
                                <a href="${sub.submission_file_url}" class="btn btn-sm btn-primary" target="_blank">
                                    <i class="fas fa-download"></i> Download File
                                </a>
                            </span>
                        </div>
                        ` : ''}
                    </div>
                `;
            } else {
                content.innerHTML = '<div class="alert alert-error">Failed to load submission details.</div>';
            }
        })
        .catch(() => {
            content.innerHTML = '<div class="alert alert-error">An error occurred.</div>';
        });
}

function gradeSubmission(id, name) {
    document.getElementById('gradeSubmissionId').value = id;
    document.getElementById('gradeStudentName').textContent = name;
    document.getElementById('score').value = '';
    document.getElementById('feedback').value = '';
    openModal('gradeModal');
}

document.getElementById('gradeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'grade');
    
    fetch('<?php echo url('api/assignment-api.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeModal('gradeModal');
            alert('Grade submitted successfully!');
            location.reload();
        } else {
            alert(data.message || 'Failed to submit grade.');
        }
    })
    .catch(() => {
        alert('An error occurred.');
    });
});
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>