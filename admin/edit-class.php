<?php
/**
 * Admin - Edit Class
 * Complete class editing with course management
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

$pageTitle = 'Edit Class';
$pageCss = ['admin.css', 'forms.css', 'add-class.css'];
$pageJs = ['edit-class.js'];
$isAdmin = true;

$id = $_GET['id'] ?? 0;
if (!$id) {
    Session::setFlash('error', 'Invalid class ID');
    redirect(url('admin/classes.php'));
}

$db = Database::getInstance();
$classManager = new ClassManager();

// Get class details
$class = $classManager->getById($id);
if (!$class) {
    Session::setFlash('error', 'Class not found');
    redirect(url('admin/classes.php'));
}

// Get courses for dropdown
$stmt = $db->query("SELECT id, course_code, course_title, credits, department, semester, academic_year FROM courses WHERE status = 'active' ORDER BY course_code");
$courses = $stmt->fetchAll();

// Get default settings
$stmt = $db->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$error = null;
$success = null;
$formData = $class;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $formAction = $_POST['form_action'] ?? 'update_class';
        
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
                        // Insert new course
                        $stmt = $db->prepare("
                            INSERT INTO courses (
                                course_code, course_title, credits, department, 
                                semester, academic_year, status, created_at, updated_at
                            ) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
                        ");
                        $stmt->execute([
                            $newCourseCode, 
                            $newCourseTitle, 
                            $newCredits, 
                            $newDepartment,
                            $newSemester, 
                            $newAcademicYear
                        ]);
                        
                        $newCourseId = $db->lastInsertId();
                        
                        // Refresh courses list
                        $stmt = $db->query("SELECT id, course_code, course_title, credits, department, semester, academic_year FROM courses WHERE status = 'active' ORDER BY course_code");
                        $courses = $stmt->fetchAll();
                        
                        // Auto-select the new course for this class
                        $formData['course_id'] = $newCourseId;
                        
                        // Update the class with the new course
                        $updateData = [
                            'course_id' => $newCourseId,
                            'class_code' => $class['class_code'],
                            'class_title' => $class['class_title'],
                            'description' => $class['description'],
                            'instructor' => $class['instructor'],
                            'class_date' => $class['class_date'],
                            'start_time' => $class['start_time'],
                            'end_time' => $class['end_time'],
                            'room' => $class['room'],
                            'venue' => $class['venue'],
                            'semester' => $class['semester'] ?: $newSemester,
                            'academic_year' => $class['academic_year'] ?: $newAcademicYear,
                            'topic' => $class['topic'],
                            'class_task' => $class['class_task'],
                            'additional_info' => $class['additional_info'],
                            'status' => $class['status']
                        ];
                        
                        $result = $classManager->update($id, $updateData);
                        
                        if ($result['success']) {
                            // Refresh class data
                            $class = $classManager->getById($id);
                            $formData = $class;
                            $success = 'Course added and class updated successfully!';
                            logActivity('class_updated', "Updated class: {$class['class_code']} with new course");
                        } else {
                            $error = 'Course added but failed to update class: ' . $result['message'];
                        }
                    }
                } catch (Throwable $e) {
                    $error = 'Unable to add the course. Please try again.';
                    error_log('Course creation failed: ' . $e->getMessage());
                }
            }
        } 
        // ===== UPDATE CLASS =====
        elseif ($formAction === 'update_class') {
            // Get form data
            $data = [
                'course_id' => $_POST['course_id'] ?? '',
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
                'status' => $_POST['status'] ?? 'scheduled'
            ];
            
            // Validate
            if (empty($data['course_id'])) {
                $error = 'Please select a course.';
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
            } else {
                // Check if class code is unique (except this class)
                $stmt = $db->prepare("SELECT id FROM classes WHERE class_code = ? AND id != ?");
                $stmt->execute([$data['class_code'], $id]);
                if ($stmt->fetch()) {
                    $error = 'Class code already exists. Please use a unique code.';
                } else {
                    $result = $classManager->update($id, $data);
                    
                    if ($result['success']) {
                        Session::setFlash('success', 'Class updated successfully!');
                        logActivity('class_updated', "Updated class: {$data['class_code']}");
                        redirect(url('admin/classes.php'));
                    } else {
                        $error = $result['message'];
                    }
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
                <h1>Edit Class</h1>
                <p class="text-muted">Update class details</p>
            </div>
            <div class="page-actions">
                <a href="<?php echo url('admin/class-details.php?id=' . $id); ?>" class="btn btn-info">
                    <i class="fas fa-eye"></i> View Details
                </a>
                <a href="<?php echo url('admin/classes.php'); ?>" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Classes
                </a>
            </div>
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
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-edit text-primary"></i>
                <?php echo htmlspecialchars($class['class_code'] . ' - ' . $class['class_title']); ?>
            </h3>
            <span class="badge badge-<?php echo $class['status']; ?>">
                <?php echo ucfirst($class['status']); ?>
            </span>
        </div>
        <div class="card-body">
            <form method="POST" action="" class="class-form" id="editClassForm">
                <?php echo Security::csrfField(); ?>
                
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
                        </div>
                        
                        <div class="form-group">
                            <label>Quick Actions</label>
                            <button type="button" class="btn btn-outline-primary btn-block" onclick="toggleCourseForm()">
                                <i class="fas fa-plus-circle"></i> Add New Course
                            </button>
                        </div>
                    </div>
                    
                    <!-- Add New Course Form (Hidden by default) -->
                    <div class="course-form-wrapper" id="courseFormWrapper" style="display: none;">
                        <div class="course-form-inner">
                            <h5><i class="fas fa-plus-circle text-success"></i> Add New Course</h5>
                            <p class="text-muted">Add a new course to the system. It will be automatically selected for this class.</p>
                            
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
                                <button type="submit" name="form_action" value="add_course" class="btn btn-success">
                                    <i class="fas fa-save"></i> Save Course & Update Class
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
                    <button type="submit" name="form_action" value="update_class" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Class
                    </button>
                    <a href="<?php echo url('admin/class-details.php?id=' . $id); ?>" class="btn btn-info">
                        <i class="fas fa-eye"></i> View Details
                    </a>
                    <a href="<?php echo url('admin/classes.php'); ?>" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- ===== Quick Actions ===== -->
    <div class="quick-actions">
        <h4><i class="fas fa-bolt text-primary"></i> Quick Actions</h4>
        <div class="actions-grid">
            <a href="<?php echo url('admin/class-details.php?id=' . $id); ?>" class="action-item">
                <div class="action-icon"><i class="fas fa-eye"></i></div>
                <span>View Details</span>
            </a>
            <?php if ($class['status'] === 'scheduled'): ?>
                <a href="<?php echo url('admin/complete-class.php?id=' . $id); ?>" class="action-item">
                    <div class="action-icon"><i class="fas fa-check-circle"></i></div>
                    <span>Mark Complete</span>
                </a>
            <?php endif; ?>
            <a href="<?php echo url('admin/add-assignment.php?class_id=' . $id); ?>" class="action-item">
                <div class="action-icon"><i class="fas fa-tasks"></i></div>
                <span>Add Assignment</span>
            </a>
            <a href="<?php echo url('admin/add-income.php?class_id=' . $id); ?>" class="action-item">
                <div class="action-icon"><i class="fas fa-coins"></i></div>
                <span>Add Income</span>
            </a>
            <a href="<?php echo url('admin/class-materials.php?class_id=' . $id); ?>" class="action-item">
                <div class="action-icon"><i class="fas fa-file-alt"></i></div>
                <span>Manage Materials</span>
            </a>
            <a href="<?php echo url('admin/class-enrollments.php?class_id=' . $id); ?>" class="action-item">
                <div class="action-icon"><i class="fas fa-users"></i></div>
                <span>Manage Students</span>
            </a>
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
    document.getElementById('editClassForm').addEventListener('submit', function(e) {
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
    
    // ===== Unsaved Changes Warning =====
    let formChanged = false;
    const form = document.getElementById('editClassForm');
    
    form.querySelectorAll('input, textarea, select').forEach(field => {
        field.addEventListener('change', function() {
            formChanged = true;
        });
    });
    
    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            return e.returnValue;
        }
    });
    
    // ===== Confirm Cancel =====
    document.querySelectorAll('.btn-outline, .btn-info').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (formChanged) {
                if (!confirm('You have unsaved changes. Are you sure you want to leave?')) {
                    e.preventDefault();
                }
            }
        });
    });
});
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>