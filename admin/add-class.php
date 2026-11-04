<?php
/**
 * Admin - Add Class
 * Complete class creation with course management
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

$pageTitle = 'Add New Class';
$pageCss = ['admin.css', 'forms.css', 'add-class.css'];
$pageJs = ['add-class.js'];
$isAdmin = true;

$db = Database::getInstance();
$classManager = new ClassManager();
$adminBatchId = Authorization::getAdminBatchId();
$stmt = $db->query("SELECT id, name, label FROM batches WHERE is_active = 1 ORDER BY FIELD(name, 'First year', 'Second year', 'Third Year', 'Fourth year'), name");
$batches = $stmt->fetchAll();

$error = null;
$success = null;

// Get courses for dropdown
$stmt = $db->query("SELECT id, course_code, course_title, credits, department, semester, academic_year,
                                                     (SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(existing_classes.class_code, '-', -1) AS UNSIGNED)), 0) + 1
                                                        FROM classes existing_classes
                                                        WHERE existing_classes.course_id = courses.id
                                                            AND existing_classes.class_code LIKE CONCAT(courses.course_code, '-%')) AS next_section
                                        FROM courses
                                        WHERE status = 'active'
                                        ORDER BY course_code");
$courses = $stmt->fetchAll();

// Get default settings
$stmt = $db->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Default values
$formData = [
    'course_id' => '',
    'course_title' => '',
    'class_code' => '',
    'class_title' => '',
    'description' => '',
    'instructor' => '',
    'class_date' => date('Y-m-d'),
    'start_time' => '09:00',
    'end_time' => '10:30',
    'room' => '',
    'venue' => '',
    'semester' => date('Y') . '-' . (date('Y') + 1),
    'academic_year' => date('Y') . '-' . (date('Y') + 1),
    'topic' => '',
    'class_task' => '',
    'additional_info' => '',
    'status' => 'scheduled',
    'credits' => 3.0,
    'department' => 'Geography and Environment'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $formAction = $_POST['form_action'] ?? 'create_class';
        
        // ===== ADD NEW COURSE =====
        if ($formAction === 'add_course') {
            $newCourseCode = strtoupper(trim($_POST['new_course_code'] ?? ''));
            $newCourseTitle = trim($_POST['new_course_title'] ?? '');
            $newCredits = $_POST['new_credits'] ?? 3.0;
            $newDepartment = trim($_POST['new_department'] ?? 'Geography and Environment');
            $newSemester = trim($_POST['new_semester'] ?? date('Y') . '-' . (date('Y') + 1));
            $newAcademicYear = trim($_POST['new_academic_year'] ?? date('Y') . '-' . (date('Y') + 1));
            
            if (empty($newCourseCode) || empty($newCourseTitle)) {
                $error = 'Course code and course name are required.';
            } else {
                try {
                    // Check if course exists
                    $stmt = $db->prepare("SELECT id FROM courses WHERE course_code = ?");
                    $stmt->execute([$newCourseCode]);
                    
                    if ($stmt->fetch()) {
                        $error = 'A course with this code already exists.';
                    } else {
                        // Insert new course. Retry with an explicit ID for older schemas
                        // where courses.id was created without AUTO_INCREMENT.
                        $courseValues = [
                            $newCourseCode,
                            $newCourseTitle,
                            $newCredits,
                            $newDepartment,
                            $newSemester,
                            $newAcademicYear
                        ];
                        try {
                            $stmt = $db->prepare("
                                INSERT INTO courses (
                                    course_code, course_title, credits, department,
                                    semester, academic_year, status, created_at, updated_at
                                ) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
                            ");
                            $stmt->execute($courseValues);
                        } catch (PDOException $insertException) {
                            if ($insertException->getCode() !== '23000') {
                                throw $insertException;
                            }

                            $stmt = $db->query("SELECT COALESCE(MAX(id), 0) + 1 FROM courses");
                            $nextCourseId = (int)$stmt->fetchColumn();
                            $stmt = $db->prepare("
                                INSERT INTO courses (
                                    id, course_code, course_title, credits, department,
                                    semester, academic_year, status, created_at, updated_at
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
                            ");
                            $stmt->execute(array_merge([$nextCourseId], $courseValues));
                        }
                        
                        $newCourseId = (int)$db->lastInsertId();
                        if ($newCourseId <= 0) {
                            $stmt = $db->prepare("SELECT id FROM courses WHERE course_code = ?");
                            $stmt->execute([$newCourseCode]);
                            $newCourseId = (int)$stmt->fetchColumn();
                        }
                        if ($newCourseId <= 0) {
                            throw new RuntimeException('The new course ID could not be determined.');
                        }
                        
                        // Refresh courses list
                                                $stmt = $db->query("SELECT id, course_code, course_title, credits, department, semester, academic_year,
                                                                                                     (SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(existing_classes.class_code, '-', -1) AS UNSIGNED)), 0) + 1
                                                                                                        FROM classes existing_classes
                                                                                                        WHERE existing_classes.course_id = courses.id
                                                                                                            AND existing_classes.class_code LIKE CONCAT(courses.course_code, '-%')) AS next_section
                                                                                        FROM courses
                                                                                        WHERE status = 'active'
                                                                                        ORDER BY course_code");
                        $courses = $stmt->fetchAll();
                        
                        // Auto-select the new course
                        $formData['course_id'] = $newCourseId;
                        $formData['semester'] = $newSemester;
                        $formData['academic_year'] = $newAcademicYear;
                        $formData['department'] = $newDepartment;
                        $formData['credits'] = $newCredits;
                        
                        // Auto-generate class code based on course code
                        $formData['class_code'] = $newCourseCode . '-01';
                        
                        // Auto-fill class title
                        $formData['course_title'] = $newCourseTitle;
                        $formData['class_title'] = $newCourseTitle . ' - Section A';
                        
                        $success = 'Course added successfully! Class details have been auto-filled.';
                        logActivity('course_created', "Created course: {$newCourseCode} - {$newCourseTitle}");
                    }
                } catch (Throwable $e) {
                    $error = 'Unable to add the course. Please try again.';
                    error_log('Course creation failed: ' . $e->getMessage());
                }
            }
        } 
        // ===== CREATE CLASS =====
        elseif ($formAction === 'create_class') {
            // Get form data
            $postedCourseId = $_POST['course_id'] ?? $_POST['selected_course_id'] ?? null;
            $courseId = filter_var($postedCourseId, FILTER_VALIDATE_INT);
            $data = [
                'batch_id' => $adminBatchId ?? (int)($_POST['batch_id'] ?? 0),
                'course_id' => $courseId ?: '',
                'class_code' => trim($_POST['class_code'] ?? ''),
                'class_title' => trim($_POST['class_title'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'instructor' => trim($_POST['instructor'] ?? ''),
                'class_date' => $_POST['class_date'] ?? '',
                'start_time' => $_POST['start_time'] ?? '',
                'end_time' => $_POST['end_time'] ?? '',
                'room' => trim($_POST['room'] ?? ''),
                'venue' => trim($_POST['venue'] ?? ''),
                'semester' => trim($_POST['semester'] ?? ''),
                'academic_year' => trim($_POST['academic_year'] ?? ''),
                'topic' => trim($_POST['topic'] ?? ''),
                'class_task' => trim($_POST['class_task'] ?? ''),
                'additional_info' => trim($_POST['additional_info'] ?? ''),
                'status' => $_POST['status'] ?? 'scheduled',
                'created_by' => Session::get('user_id')
            ];

            $courseData = [
                'course_title' => trim($_POST['course_title'] ?? ''),
                'credits' => $_POST['credits'] ?? 3.0,
                'department' => trim($_POST['department'] ?? ''),
                'semester' => $data['semester'],
                'academic_year' => $data['academic_year']
            ];
            
            // Validate
            if (!$courseId || $courseId < 1) {
                $error = 'Please select a course.';
            } elseif (!$data['batch_id']) {
                $error = 'Please select a batch.';
            } elseif (empty($data['class_code'])) {
                $error = 'Class code is required.';
            } elseif (empty($data['class_title'])) {
                $error = 'Class title is required.';
            } elseif (empty($data['instructor'])) {
                $error = 'Instructor name is required.';
            } elseif (empty($data['class_date'])) {
                $error = 'Class date is required.';
            } elseif (empty($data['start_time'])) {
                $error = 'Start time is required.';
            } elseif (empty($data['end_time'])) {
                $error = 'End time is required.';
            } elseif ($data['end_time'] <= $data['start_time']) {
                $error = 'End time must be after start time.';
            } elseif (empty($courseData['course_title']) || empty($courseData['department'])) {
                $error = 'Course name and department are required.';
            } else {
                try {
                    $stmt = $db->prepare("UPDATE courses SET course_title = ?, credits = ?, department = ?, semester = ?, academic_year = ? WHERE id = ?");
                    $stmt->execute([
                        $courseData['course_title'],
                        $courseData['credits'],
                        $courseData['department'],
                        $courseData['semester'],
                        $courseData['academic_year'],
                        $data['course_id']
                    ]);
                    $result = $classManager->create($data);
                } catch (Throwable $e) {
                    $result = ['success' => false, 'message' => 'Unable to save the course information. Please try again.'];
                    error_log('Course information update failed: ' . $e->getMessage());
                }
                
                if ($result['success']) {
                    // Notify students about the new class
                    try {
                        $notifier = new NotificationManager();
                        $classTitle = $data['class_title'];
                        $courseTitle = $courseData['course_title'] ?? $data['class_code'];
                        $classDate = date('d M Y', strtotime($data['class_date']));
                        $classTime = date('h:i A', strtotime($data['start_time']));
                        $notifier->notifyBatchStudents(
                            $data['batch_id'],
                            "📚 New Class: {$classTitle}",
                            "{$courseTitle} class on {$classDate} at {$classTime} (Room: {$data['room']})",
                            'info',
                            url('student/classes.php')
                        );
                    } catch (Throwable $e) {
                        error_log('Class notification error: ' . $e->getMessage());
                    }

                    Session::setFlash('success', 'Class added successfully!');
                    redirect(url('admin/classes.php'));
                } else {
                    $error = $result['message'];
                    $formData = $data;
                    $formData['course_title'] = $courseData['course_title'];
                    $formData['credits'] = $courseData['credits'];
                    $formData['department'] = $courseData['department'];
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
                <h1>Add New Class</h1>
                <p class="text-muted">Create a new class in the system</p>
            </div>
            <a href="<?php echo url('admin/classes.php'); ?>" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Classes
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
            <form method="POST" action="" class="class-form" id="addClassForm">
                <?php echo Security::csrfField(); ?>

                <?php if ($adminBatchId === null): ?>
                    <div class="form-group">
                        <label for="batch_id">Batch <span class="required">*</span></label>
                        <select id="batch_id" name="batch_id" class="form-control" required>
                            <option value="">-- Select Batch --</option>
                            <?php foreach ($batches as $batch): ?>
                                <option value="<?php echo (int)$batch['id']; ?>" <?php echo (int)($formData['batch_id'] ?? 0) === (int)$batch['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($batch['label'] ?: 'Batch ' . $batch['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="batch_id" value="<?php echo $adminBatchId; ?>">
                <?php endif; ?>
                
                <!-- ===== COURSE SELECTION ===== -->
                <div class="form-section">
                    <h4 class="form-section-title">
                        <i class="fas fa-graduation-cap"></i> Course Information
                    </h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="course_id">Select Course <span class="required">*</span></label>
                            <select id="course_id" name="course_id" class="form-control" required>
                                <option value="">-- Select a Course --</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                            data-code="<?php echo htmlspecialchars($course['course_code']); ?>"
                                            data-next-code="<?php echo htmlspecialchars($course['course_code'] . '-' . str_pad((int)($course['next_section'] ?: 1), 2, '0', STR_PAD_LEFT)); ?>"
                                            data-title="<?php echo htmlspecialchars($course['course_title']); ?>"
                                            data-credits="<?php echo $course['credits']; ?>"
                                            data-department="<?php echo htmlspecialchars($course['department']); ?>"
                                            data-semester="<?php echo htmlspecialchars($course['semester'] ?? ''); ?>"
                                            data-academic_year="<?php echo htmlspecialchars($course['academic_year'] ?? ''); ?>"
                                            <?php echo $formData['course_id'] == $course['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" id="selected_course_id" name="selected_course_id"
                                   value="<?php echo htmlspecialchars((string)$formData['course_id']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Quick Actions</label>
                            <button type="button" class="btn btn-outline-primary btn-block" onclick="toggleCourseForm()">
                                <i class="fas fa-plus-circle"></i> Add New Course
                            </button>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="course_title">Course Name <span class="required">*</span></label>
                            <input type="text" id="course_title" name="course_title" class="form-control"
                                   value="<?php echo htmlspecialchars($formData['course_title'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="credits">Credits <span class="required">*</span></label>
                            <input type="number" id="credits" name="credits" class="form-control"
                                   value="<?php echo htmlspecialchars($formData['credits']); ?>" step="0.5" min="0.5" max="6" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="department">Department <span class="required">*</span></label>
                        <input type="text" id="department" name="department" class="form-control"
                               value="<?php echo htmlspecialchars($formData['department']); ?>" required>
                    </div>
                    
                    <!-- Add New Course Form (Hidden by default) -->
                    <div class="course-form-wrapper" id="courseFormWrapper" style="display: none;">
                        <div class="course-form-inner">
                            <h5><i class="fas fa-plus-circle text-success"></i> Add New Course</h5>
                            <p class="text-muted">Add a new course to the system. It will be automatically selected.</p>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="new_course_code">Course Code <span class="required">*</span></label>
                                    <input type="text" id="new_course_code" name="new_course_code" class="form-control" 
                                           placeholder="e.g., GEO-501" maxlength="20">
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_course_title">Course Name <span class="required">*</span></label>
                                    <input type="text" id="new_course_title" name="new_course_title" class="form-control" 
                                           placeholder="Enter course name" maxlength="200">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="new_credits">Credits</label>
                                    <input type="number" id="new_credits" name="new_credits" class="form-control" 
                                           value="3.0" step="0.5" min="0.5" max="6">
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_department">Department</label>
                                    <input type="text" id="new_department" name="new_department" class="form-control" 
                                           value="Geography and Environment">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="new_semester">Semester</label>
                                    <input type="text" id="new_semester" name="new_semester" class="form-control" 
                                           value="<?php echo date('Y') . '-' . (date('Y') + 1); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_academic_year">Academic Year</label>
                                    <input type="text" id="new_academic_year" name="new_academic_year" class="form-control" 
                                           value="<?php echo date('Y') . '-' . (date('Y') + 1); ?>">
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="form_action" value="add_course" formnovalidate class="btn btn-success">
                                    <i class="fas fa-save"></i> Save Course & Auto-Fill Class
                                </button>
                                <button type="button" class="btn btn-outline" onclick="toggleCourseForm()">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ===== CLASS DETAILS ===== -->
                <div class="form-section">
                    <h4 class="form-section-title">
                        <i class="fas fa-info-circle"></i> Class Details
                    </h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="class_code">Class Code <span class="required">*</span></label>
                            <input type="text" id="class_code" name="class_code" class="form-control" 
                                   placeholder="e.g., GEO-101-01" 
                                   value="<?php echo htmlspecialchars($formData['class_code']); ?>" required>
                            <span class="form-help">Unique identifier for this class</span>
                        </div>
                        
                        <div class="form-group">
                            <label for="class_title">Class Title <span class="required">*</span></label>
                            <input type="text" id="class_title" name="class_title" class="form-control" 
                                   placeholder="Enter class title" 
                                   value="<?php echo htmlspecialchars($formData['class_title']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="2" 
                                  placeholder="Describe the class"><?php echo htmlspecialchars($formData['description']); ?></textarea>
                    </div>
                </div>
                
                <!-- ===== INSTRUCTOR & LOCATION ===== -->
                <div class="form-section">
                    <h4 class="form-section-title">
                        <i class="fas fa-user-tie"></i> Instructor & Location
                    </h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="instructor">Instructor <span class="required">*</span></label>
                            <input type="text" id="instructor" name="instructor" class="form-control" 
                                   placeholder="Instructor name" 
                                   value="<?php echo htmlspecialchars($formData['instructor']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="room">Room</label>
                            <input type="text" id="room" name="room" class="form-control" 
                                   placeholder="Room number" 
                                   value="<?php echo htmlspecialchars($formData['room']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="venue">Venue</label>
                        <input type="text" id="venue" name="venue" class="form-control" 
                               placeholder="Venue name" 
                               value="<?php echo htmlspecialchars($formData['venue']); ?>">
                    </div>
                </div>
                
                <!-- ===== DATE & TIME ===== -->
                <div class="form-section">
                    <h4 class="form-section-title">
                        <i class="fas fa-calendar-alt"></i> Date & Time
                    </h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="class_date">Date <span class="required">*</span></label>
                            <input type="date" id="class_date" name="class_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($formData['class_date']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="start_time">Start Time <span class="required">*</span></label>
                            <input type="time" id="start_time" name="start_time" class="form-control" 
                                   value="<?php echo htmlspecialchars($formData['start_time']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="end_time">End Time <span class="required">*</span></label>
                            <input type="time" id="end_time" name="end_time" class="form-control" 
                                   value="<?php echo htmlspecialchars($formData['end_time']); ?>" required>
                        </div>
                    </div>
                </div>
                
                <!-- ===== ACADEMIC INFO ===== -->
                <div class="form-section">
                    <h4 class="form-section-title">
                        <i class="fas fa-graduation-cap"></i> Academic Info
                    </h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="semester">Semester</label>
                            <input type="text" id="semester" name="semester" class="form-control" 
                                   placeholder="e.g., Fall 2024" 
                                   value="<?php echo htmlspecialchars($formData['semester']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="academic_year">Academic Year</label>
                            <input type="text" id="academic_year" name="academic_year" class="form-control" 
                                   placeholder="e.g., 2024-2025" 
                                   value="<?php echo htmlspecialchars($formData['academic_year']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="scheduled" <?php echo $formData['status'] === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                            <option value="completed" <?php echo $formData['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $formData['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="retaked" <?php echo $formData['status'] === 'retaked' ? 'selected' : ''; ?>>Retaked</option>
                        </select>
                    </div>
                </div>
                
                <!-- ===== CLASS CONTENT ===== -->
                <div class="form-section">
                    <h4 class="form-section-title">
                        <i class="fas fa-book-open"></i> Class Content
                    </h4>
                    
                    <div class="form-group">
                        <label for="topic">Topic / Planned Topic</label>
                        <input type="text" id="topic" name="topic" class="form-control" 
                               placeholder="Class topic" 
                               value="<?php echo htmlspecialchars($formData['topic']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="class_task">Class Task</label>
                        <textarea id="class_task" name="class_task" class="form-control" rows="2" 
                                  placeholder="Tasks for this class"><?php echo htmlspecialchars($formData['class_task']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="additional_info">Additional Information</label>
                        <textarea id="additional_info" name="additional_info" class="form-control" rows="2" 
                                  placeholder="Any additional information"><?php echo htmlspecialchars($formData['additional_info']); ?></textarea>
                    </div>
                </div>
                
                <!-- ===== FORM ACTIONS ===== -->
                <div class="form-actions">
                    <button type="submit" name="form_action" value="create_class" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Class
                    </button>
                    <a href="<?php echo url('admin/classes.php'); ?>" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // ===== Toggle Course Form =====
    window.toggleCourseForm = function() {
        const wrapper = document.getElementById('courseFormWrapper');
        if (wrapper.style.display === 'none') {
            wrapper.style.display = 'block';
            wrapper.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            wrapper.style.display = 'none';
        }
    };
    
    // ===== Auto-fill from Course Selection =====
    const courseSelect = document.getElementById('course_id');
    const selectedCourseIdInput = document.getElementById('selected_course_id');
    const courseTitleInput = document.getElementById('course_title');
    const creditsInput = document.getElementById('credits');
    const departmentInput = document.getElementById('department');
    const classCodeInput = document.getElementById('class_code');
    const classTitleInput = document.getElementById('class_title');
    const semesterInput = document.getElementById('semester');
    const academicYearInput = document.getElementById('academic_year');
    
    courseSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        selectedCourseIdInput.value = this.value;
        if (!selectedOption || !selectedOption.value) return;
        
        const courseCode = selectedOption.dataset.code || '';
        const nextClassCode = selectedOption.dataset.nextCode || (courseCode ? courseCode + '-01' : '');
        const courseTitle = selectedOption.dataset.title || '';
        const credits = selectedOption.dataset.credits || '3.0';
        const department = selectedOption.dataset.department || '';
        const semester = selectedOption.dataset.semester || '';
        const academicYear = selectedOption.dataset.academic_year || '';
        
        // Fill class fields from the selected course. All fields remain editable.
        courseTitleInput.value = courseTitle;
        creditsInput.value = credits;
        departmentInput.value = department;
        classCodeInput.value = nextClassCode;
        classTitleInput.value = courseTitle ? courseTitle + ' - Section A' : '';
        semesterInput.value = semester || '<?php echo date('Y') . '-' . (date('Y') + 1); ?>';
        academicYearInput.value = academicYear || '<?php echo date('Y') . '-' . (date('Y') + 1); ?>';
    });
    
    // ===== End Time Validation =====
    const startTime = document.getElementById('start_time');
    const endTime = document.getElementById('end_time');
    
    function validateTimes() {
        if (startTime.value && endTime.value) {
            if (endTime.value <= startTime.value) {
                endTime.setCustomValidity('End time must be after start time.');
                endTime.classList.add('error');
            } else {
                endTime.setCustomValidity('');
                endTime.classList.remove('error');
            }
        }
    }
    
    startTime.addEventListener('change', validateTimes);
    endTime.addEventListener('change', validateTimes);
    
    // ===== Form Validation =====
    document.getElementById('addClassForm').addEventListener('submit', function(e) {
        if (e.submitter && e.submitter.value === 'add_course') {
            return true;
        }

        const classCode = document.getElementById('class_code');
        const classTitle = document.getElementById('class_title');
        const instructor = document.getElementById('instructor');
        const courseSelect = document.getElementById('course_id');
        
        if (!courseSelect.value) {
            e.preventDefault();
            alert('Please select a course.');
            courseSelect.focus();
            return false;
        }
        
        if (classCode.value.trim().length < 3) {
            e.preventDefault();
            alert('Class code must be at least 3 characters.');
            classCode.focus();
            return false;
        }
        
        if (classTitle.value.trim().length < 3) {
            e.preventDefault();
            alert('Class title must be at least 3 characters.');
            classTitle.focus();
            return false;
        }
        
        if (instructor.value.trim().length < 2) {
            e.preventDefault();
            alert('Please enter the instructor name.');
            instructor.focus();
            return false;
        }
        
        return true;
    });
});

// ===== Auto-generate Class Code =====
function autoGenerateClassCode() {
    const courseSelect = document.getElementById('course_id');
    const classCodeInput = document.getElementById('class_code');
    
    if (courseSelect.value && !classCodeInput.value) {
        const selectedOption = courseSelect.options[courseSelect.selectedIndex];
        const courseCode = selectedOption.dataset.code || '';
        if (courseCode) {
            // Get existing class count for this course
            const prefix = courseCode + '-';
            const currentValue = classCodeInput.value;
            if (!currentValue.startsWith(prefix)) {
                // Count existing classes with this prefix
                // This would require an AJAX call, but we'll just set a default
                classCodeInput.value = courseCode + '-01';
            }
        }
    }
}
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>