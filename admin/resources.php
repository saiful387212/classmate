<?php
/**
 * Admin - Resource Management
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

Auth::requireAdmin();

$pageTitle = 'Resource Management';
$pageCss = ['resources.css', 'filters.css'];
$pageJs = ['admin-resources.js'];
$isAdmin = true;

$db = Database::getInstance();
$adminBatchId = Authorization::getAdminBatchId();

$error = null;
$success = null;

// ============================================
// HANDLE ACTIONS
// ============================================
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if ($action === 'delete' && $id) {
    if (!Security::verifyCSRFToken($_GET['csrf_token'] ?? '')) {
        Session::setFlash('error', 'Invalid security token');
        redirect(url('admin/resources.php'));
    }
    
    [$batchSql, $batchParams] = Authorization::adminBatchFilter();
    $stmt = $db->prepare("DELETE FROM resources WHERE id = ?" . $batchSql);
    if ($stmt->execute(array_merge([$id], $batchParams)) && $stmt->rowCount() > 0) {
        Session::setFlash('success', 'Resource deleted successfully!');
        logActivity('resource_deleted', "Deleted resource ID: {$id}");
    }
    redirect(url('admin/resources.php'));
}

if ($action === 'toggle_status' && $id) {
    if (!Security::verifyCSRFToken($_GET['csrf_token'] ?? '')) {
        Session::setFlash('error', 'Invalid security token');
        redirect(url('admin/resources.php'));
    }
    
    [$batchSql, $batchParams] = Authorization::adminBatchFilter();
    $stmt = $db->prepare("
        UPDATE resources 
        SET status = CASE WHEN status = 'published' THEN 'hidden' ELSE 'published' END 
        WHERE id = ?" . $batchSql
    );
    $stmt->execute(array_merge([$id], $batchParams));
    Session::setFlash('success', 'Resource status updated!');
    redirect(url('admin/resources.php'));
}

// ============================================
// ADD NEW RESOURCE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_resource') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $courseId = $_POST['course_id'] ?? '';
        $batchId = $adminBatchId;
        if ($batchId === null) {
            $batchId = (int)($_POST['batch_id'] ?? 0);
            $batchCheck = $db->prepare("SELECT id FROM batches WHERE id = ? AND is_active = 1");
            $batchCheck->execute([$batchId]);
            if (!$batchId || !$batchCheck->fetchColumn()) {
                $error = 'Please select a valid batch.';
            }
        }
        $newCourseCode = strtoupper(trim($_POST['new_course_code'] ?? ''));
        $newCourseTitle = trim($_POST['new_course_title'] ?? '');
        
        // Handle new course creation
        if (!$error && $courseId === 'new' && !empty($newCourseCode) && !empty($newCourseTitle)) {
            try {
                // Check if course exists
                $stmt = $db->prepare("SELECT id FROM courses WHERE course_code = ?");
                $stmt->execute([$newCourseCode]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    $courseId = $existing['id'];
                    $success = 'Existing course used. ';
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO courses (course_code, course_title, credits, department, status, created_at, updated_at)
                        VALUES (?, ?, ?, ?, 'active', NOW(), NOW())
                    ");
                    $stmt->execute([
                        $newCourseCode,
                        $newCourseTitle,
                        $_POST['new_course_credits'] ?? 3.0,
                        $_POST['new_course_department'] ?? 'Geography and Environment'
                    ]);
                    $courseId = $db->lastInsertId();
                    $success = 'New course created. ';
                }
            } catch (Exception $e) {
                $error = 'Failed to create course: ' . $e->getMessage();
            }
        }
        
        if (!$error) {
            $data = [
                'course_id' => $courseId,
                'title' => trim($_POST['title'] ?? ''),
                'resource_type' => $_POST['resource_type'] ?? 'note',
                'subject' => trim($_POST['subject'] ?? ''),
                'year' => trim($_POST['year'] ?? ''),
                'exam_type' => !empty($_POST['exam_type']) ? $_POST['exam_type'] : null,
                'drive_link' => trim($_POST['drive_link'] ?? ''),
                'added_by' => trim($_POST['added_by'] ?? 'Admin'),
                'status' => $_POST['status'] ?? 'published'
            ];
            
            // Validate
            if (empty($data['course_id'])) {
                $error = 'Please select a course.';
            } elseif (empty($data['title'])) {
                $error = 'Title is required.';
            } elseif (empty($data['year'])) {
                $error = 'Year is required.';
            } elseif (empty($data['drive_link'])) {
                $error = 'Google Drive link is required.';
            } elseif (!filter_var($data['drive_link'], FILTER_VALIDATE_URL)) {
                $error = 'Please enter a valid URL.';
            } elseif ($data['resource_type'] === 'question' && empty($data['exam_type'])) {
                $error = 'Exam type is required for questions.';
            } else {
                // If subject is empty, get it from course
                if (empty($data['subject'])) {
                    $stmt = $db->prepare("SELECT course_title FROM courses WHERE id = ?");
                    $stmt->execute([$data['course_id']]);
                    $data['subject'] = $stmt->fetchColumn() ?: 'Other';
                }
                
                $stmt = $db->prepare("
                    INSERT INTO resources (batch_id, course_id, title, resource_type, subject, year, exam_type, drive_link, added_by, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $result = $stmt->execute([
                    $batchId,
                    $data['course_id'],
                    $data['title'],
                    $data['resource_type'],
                    $data['subject'],
                    $data['year'],
                    $data['exam_type'],
                    $data['drive_link'],
                    $data['added_by'],
                    $data['status']
                ]);
                
                if ($result) {
                    $newId = $db->lastInsertId();
                    Session::setFlash('success', ($success ?: '') . 'Resource added successfully!');
                    logActivity('resource_added', "Added resource: {$data['title']}");
                    redirect(url('admin/resources.php'));
                } else {
                    $error = 'Failed to add resource.';
                }
            }
        }
    }
}

// ============================================
// GET DATA
// ============================================
$filters = [
    'search' => $_GET['search'] ?? '',
    'type' => $_GET['type'] ?? '',
    'status' => $_GET['status'] ?? '',
    'course_id' => $_GET['course_id'] ?? ''
];

$sql = "
    SELECT r.*, c.course_code, c.course_title 
    FROM resources r
    LEFT JOIN courses c ON r.course_id = c.id
    WHERE 1=1
";
[$batchSql, $batchParams] = Authorization::adminBatchFilter('r');
$sql .= $batchSql;
$params = $batchParams;

if (!empty($filters['search'])) {
    $sql .= " AND (r.title LIKE ? OR r.subject LIKE ? OR c.course_title LIKE ?)";
    $search = '%' . $filters['search'] . '%';
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

if (!empty($filters['type'])) {
    $sql .= " AND r.resource_type = ?";
    $params[] = $filters['type'];
}

if (!empty($filters['status'])) {
    $sql .= " AND r.status = ?";
    $params[] = $filters['status'];
}

if (!empty($filters['course_id'])) {
    $sql .= " AND r.course_id = ?";
    $params[] = $filters['course_id'];
}

$sql .= " ORDER BY r.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$resources = $stmt->fetchAll();

// Get courses for dropdown
$stmt = $db->query("SELECT id, course_code, course_title FROM courses WHERE status = 'active' ORDER BY course_code");
$courses = $stmt->fetchAll();

// Counts
$stmt = $db->prepare("SELECT r.resource_type, COUNT(*) as count FROM resources r WHERE 1=1" . $batchSql . " GROUP BY r.resource_type");
$stmt->execute($batchParams);
$counts = ['note' => 0, 'question' => 0];
while ($row = $stmt->fetch()) {
    $counts[$row['resource_type']] = $row['count'];
}
$totalResources = array_sum($counts);

$stmt = $db->query("SELECT id, name, label FROM batches WHERE is_active = 1 ORDER BY FIELD(name, 'First year', 'Second year', 'Third Year', 'Fourth year'), name");
$batches = $stmt->fetchAll();

$years = ['2026', '2025', '2024', '2023', '2022'];

include_once BASE_PATH . '/includes/header.php';
include_once BASE_PATH . '/includes/sidebar-admin.php';
?>

<div class="main-content admin-resources-page">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>Resource Management</h1>
                <p class="text-muted">Manage notes and previous questions by course</p>
            </div>
            <button class="btn btn-primary" onclick="toggleAddForm()">
                <i class="fas fa-plus"></i> Add Resource
            </button>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- ============================================
    ADD RESOURCE FORM
    ============================================ -->
    <div class="card" id="addResourceForm" style="display:none;">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-plus-circle text-primary"></i> Add New Resource</h3>
            <button class="btn btn-sm btn-outline" onclick="toggleAddForm()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="resourceForm">
                <?php echo Security::csrfField(); ?>
                <input type="hidden" name="action" value="add_resource">

                <?php if ($adminBatchId === null): ?>
                    <div class="form-group">
                        <label for="batch_id">Batch <span class="required">*</span></label>
                        <select id="batch_id" name="batch_id" class="form-control" required>
                            <option value="">-- Select Batch --</option>
                            <?php foreach ($batches as $batch): ?>
                                <option value="<?php echo (int)$batch['id']; ?>">
                                    <?php echo htmlspecialchars($batch['label'] ?: 'Batch ' . $batch['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="batch_id" value="<?php echo $adminBatchId; ?>">
                <?php endif; ?>
                
                <!-- Course Selection -->
                <div class="form-section">
                    <h4 class="form-section-title">
                        <i class="fas fa-graduation-cap"></i> Course Information
                    </h4>
                    
                    <div class="form-group">
                        <label for="course_id">Select Course <span class="required">*</span></label>
                        <select id="course_id" name="course_id" class="form-control" required onchange="handleCourseChange()">
                            <option value="">-- Select Course --</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>" 
                                        data-code="<?php echo htmlspecialchars($course['course_code']); ?>"
                                        data-title="<?php echo htmlspecialchars($course['course_title']); ?>">
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_title']); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="new" style="color: var(--primary); font-weight: 600;">
                                ➕ Add New Course
                            </option>
                        </select>
                    </div>
                    
                    <!-- New Course Fields (shown when "new" is selected) -->
                    <div id="newCourseFields" class="new-course-fields" style="display:none;">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Enter details for a new course. It will be saved to the database.
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_course_code">Course Code <span class="required">*</span></label>
                                <input type="text" id="new_course_code" name="new_course_code" 
                                       class="form-control" placeholder="e.g., GEO-601" maxlength="20">
                            </div>
                            
                            <div class="form-group">
                                <label for="new_course_title">Course Title <span class="required">*</span></label>
                                <input type="text" id="new_course_title" name="new_course_title" 
                                       class="form-control" placeholder="e.g., Advanced GIS" maxlength="200">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_course_credits">Credits</label>
                                <input type="number" id="new_course_credits" name="new_course_credits" 
                                       class="form-control" value="3.0" step="0.5" min="0.5" max="6">
                            </div>
                            
                            <div class="form-group">
                                <label for="new_course_department">Department</label>
                                <input type="text" id="new_course_department" name="new_course_department" 
                                       class="form-control" value="Geography and Environment">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Resource Details -->
                <div class="form-section">
                    <h4 class="form-section-title">
                        <i class="fas fa-file-alt"></i> Resource Details
                    </h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="resource_type">Resource Type <span class="required">*</span></label>
                            <select id="resource_type" name="resource_type" class="form-control" required onchange="toggleExamField()">
                                <option value="note">📚 Note</option>
                                <option value="question">📝 Previous Question</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="year">Year <span class="required">*</span></label>
                            <select id="year" name="year" class="form-control" required>
                                <option value="">Select Year</option>
                                <?php foreach ($years as $year): ?>
                                    <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="title">Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" class="form-control" 
                               placeholder="e.g., Soil Profile & Soil Horizons" required>
                    </div>
                    
                    <div class="form-group" id="examTypeField" style="display:none;">
                        <label for="exam_type">Exam Type <span class="required">*</span></label>
                        <select id="exam_type" name="exam_type" class="form-control">
                            <option value="">Select Exam Type</option>
                            <option value="midterm">Midterm</option>
                            <option value="final">Final</option>
                            <option value="class_test">Class Test</option>
                            <option value="viva">Viva</option>
                            <option value="practical">Practical</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="drive_link">Google Drive Link <span class="required">*</span></label>
                        <input type="url" id="drive_link" name="drive_link" class="form-control" 
                               placeholder="https://drive.google.com/file/d/..." required>
                        <span class="form-help">Paste the Google Drive shareable link</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="subject">Subject (Optional)</label>
                            <input type="text" id="subject" name="subject" class="form-control" 
                                   placeholder="Auto-filled from course if empty">
                            <span class="form-help">Leave empty to use course title</span>
                        </div>
                        
                        <div class="form-group">
                            <label for="added_by">Added By</label>
                            <input type="text" id="added_by" name="added_by" class="form-control" 
                                   value="<?php echo htmlspecialchars($currentUser['full_name'] ?? 'Admin'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="published">Published</option>
                            <option value="hidden">Hidden</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Resource
                    </button>
                    <button type="button" class="btn btn-outline" onclick="toggleAddForm()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Stats -->
    <div class="status-stats">
        <div class="stat-item">
            <span class="stat-label">Total</span>
            <span class="stat-value"><?php echo $totalResources; ?></span>
        </div>
        <div class="stat-item">
            <span class="stat-label">📚 Notes</span>
            <span class="stat-value"><?php echo $counts['note']; ?></span>
        </div>
        <div class="stat-item">
            <span class="stat-label">📝 Questions</span>
            <span class="stat-value"><?php echo $counts['question']; ?></span>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
        <form method="GET" action="" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <input type="text" name="search" placeholder="Search..." 
                           value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
                <div class="filter-group">
                    <select name="course_id">
                        <option value="">All Courses</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>" 
                                    <?php echo $filters['course_id'] == $course['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['course_code']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <select name="type">
                        <option value="">All Types</option>
                        <option value="note" <?php echo $filters['type'] === 'note' ? 'selected' : ''; ?>>Notes</option>
                        <option value="question" <?php echo $filters['type'] === 'question' ? 'selected' : ''; ?>>Questions</option>
                    </select>
                </div>
                <div class="filter-group">
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="published" <?php echo $filters['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="hidden" <?php echo $filters['status'] === 'hidden' ? 'selected' : ''; ?>>Hidden</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="<?php echo url('admin/resources.php'); ?>" class="btn btn-outline btn-sm">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Resources Table -->
    <?php if (empty($resources)): ?>
        <div class="empty-state">
            <i class="fas fa-folder-open"></i>
            <h3>No Resources Yet</h3>
            <p>Click "Add Resource" to create your first resource.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Course</th>
                        <th>Type</th>
                        <th>Year</th>
                        <th>Exam</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resources as $index => $resource): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($resource['title']); ?></strong></td>
                            <td>
                                <?php if ($resource['course_code']): ?>
                                    <span class="badge badge-info">
                                        <?php echo htmlspecialchars($resource['course_code']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $resource['resource_type'] === 'note' ? 'success' : 'warning'; ?>">
                                    <?php echo $resource['resource_type'] === 'note' ? '📚 Note' : '📝 Question'; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($resource['year']); ?></td>
                            <td><?php echo $resource['exam_type'] ? htmlspecialchars(ucfirst($resource['exam_type'])) : '-'; ?></td>
                            <td>
                                <span class="badge badge-<?php echo $resource['status'] === 'published' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($resource['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="<?php echo htmlspecialchars($resource['drive_link']); ?>" 
                                       target="_blank" class="btn btn-sm btn-info" title="View">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                    <a href="<?php echo url('admin/resources.php?action=toggle_status&id=' . $resource['id'] . '&csrf_token=' . Security::generateCSRFToken()); ?>" 
                                       class="btn btn-sm btn-warning" title="Toggle Status">
                                        <i class="fas fa-eye<?php echo $resource['status'] === 'published' ? '-slash' : ''; ?>"></i>
                                    </a>
                                    <a href="<?php echo url('admin/resources.php?action=delete&id=' . $resource['id'] . '&csrf_token=' . Security::generateCSRFToken()); ?>" 
                                       class="btn btn-sm btn-danger" title="Delete"
                                       onclick="return confirm('Delete this resource?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleAddForm() {
    const form = document.getElementById('addResourceForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block') {
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function toggleExamField() {
    const type = document.getElementById('resource_type').value;
    const examField = document.getElementById('examTypeField');
    const examSelect = document.getElementById('exam_type');
    
    if (type === 'question') {
        examField.style.display = 'block';
        examSelect.required = true;
    } else {
        examField.style.display = 'none';
        examSelect.required = false;
        examSelect.value = '';
    }
}

function handleCourseChange() {
    const courseSelect = document.getElementById('course_id');
    const newCourseFields = document.getElementById('newCourseFields');
    const courseTitle = document.getElementById('course_title');
    const subject = document.getElementById('subject');
    
    if (courseSelect.value === 'new') {
        newCourseFields.style.display = 'block';
        document.getElementById('new_course_code').required = true;
        document.getElementById('new_course_title').required = true;
    } else {
        newCourseFields.style.display = 'none';
        document.getElementById('new_course_code').required = false;
        document.getElementById('new_course_title').required = false;
        
        // Auto-fill subject from course title
        if (courseSelect.value) {
            const selectedOption = courseSelect.options[courseSelect.selectedIndex];
            const title = selectedOption.dataset.title || '';
            if (!subject.value) {
                subject.value = title;
            }
        }
    }
}
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>