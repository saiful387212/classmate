<?php
/**
 * Student - Profile Page
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure student access
Auth::requireStudent();

$pageTitle = 'My Profile';
$pageCss = ['student.css', 'profile.css'];
$pageJs = ['profile.js'];
$isStudent = true;

$db = Database::getInstance();
$studentId = Session::get('user_id');

// Get student data
$stmt = $db->prepare("
    SELECT s.*,
           (SELECT COUNT(*) FROM class_enrollments WHERE student_id = s.id AND status = 'active') as active_classes,
           (SELECT COUNT(*) FROM class_enrollments WHERE student_id = s.id AND status = 'completed') as completed_classes,
           (SELECT COUNT(*) FROM assignments a 
            JOIN class_enrollments ce ON a.class_id = ce.class_id 
            WHERE ce.student_id = s.id AND a.status IN ('published', 'open')) as total_assignments,
           (SELECT COUNT(*) FROM assignment_submissions WHERE student_id = s.id) as submitted_assignments
    FROM students s
    WHERE s.id = ?
");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    Session::setFlash('error', 'Student not found');
    redirect(url('student/dashboard.php'));
}

// Handle profile update
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'];
        
        if ($action === 'update_profile') {
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            
            if (empty($fullName)) {
                $error = 'Full name is required.';
            } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                $stmt = $db->prepare("
                    UPDATE students 
                    SET full_name = ?, email = ?, phone = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $result = $stmt->execute([$fullName, $email, $phone, $studentId]);
                
                if ($result) {
                    $success = 'Profile updated successfully!';
                    logActivity('student_profile_updated', "Student updated profile: {$student['class_roll']}");
                    
                    // Refresh student data, including the profile statistics.
                    $stmt = $db->prepare("
                        SELECT s.*,
                               (SELECT COUNT(*) FROM class_enrollments WHERE student_id = s.id AND status = 'active') as active_classes,
                               (SELECT COUNT(*) FROM class_enrollments WHERE student_id = s.id AND status = 'completed') as completed_classes,
                               (SELECT COUNT(*) FROM assignments a
                                JOIN class_enrollments ce ON a.class_id = ce.class_id
                                WHERE ce.student_id = s.id AND a.status IN ('published', 'open')) as total_assignments,
                               (SELECT COUNT(*) FROM assignment_submissions WHERE student_id = s.id) as submitted_assignments
                        FROM students s
                        WHERE s.id = ?
                    ");
                    $stmt->execute([$studentId]);
                    $student = $stmt->fetch();
                } else {
                    $error = 'Failed to update profile.';
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
                <h1>My Profile</h1>
                <p class="text-muted">Manage your personal information</p>
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
    
    <!-- ===== Profile Overview ===== -->
    <div class="profile-overview">
        <div class="profile-avatar">
            <div class="avatar-circle">
                <?php echo strtoupper(substr($student['full_name'] ?? 'S', 0, 2)); ?>
            </div>
            <div class="profile-status">
                <span class="badge badge-<?php echo $student['status']; ?>">
                    <?php echo ucfirst($student['status']); ?>
                </span>
            </div>
        </div>
        <div class="profile-info">
            <h2><?php echo htmlspecialchars($student['full_name']); ?></h2>
            <p class="text-muted">
                <i class="fas fa-id-badge"></i> Roll: <?php echo htmlspecialchars($student['class_roll']); ?>
            </p>
            <p class="text-muted">
                <i class="fas fa-university"></i> <?php echo htmlspecialchars($student['department']); ?>
            </p>
            <?php if ($student['session']): ?>
                <p class="text-muted">
                    <i class="fas fa-calendar"></i> Session: <?php echo htmlspecialchars($student['session']); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ===== Profile Stats ===== -->
    <div class="profile-stats">
        <div class="stat-item">
            <span class="stat-value"><?php echo $student['active_classes']; ?></span>
            <span class="stat-label">Active Classes</span>
        </div>
        <div class="stat-item">
            <span class="stat-value"><?php echo $student['completed_classes']; ?></span>
            <span class="stat-label">Completed Classes</span>
        </div>
        <div class="stat-item">
            <span class="stat-value"><?php echo $student['total_assignments']; ?></span>
            <span class="stat-label">Total Assignments</span>
        </div>
        <div class="stat-item">
            <span class="stat-value"><?php echo $student['submitted_assignments']; ?></span>
            <span class="stat-label">Submitted</span>
        </div>
        <div class="stat-item">
            <span class="stat-value"><?php echo timeAgo($student['last_login'] ?? $student['created_at']); ?></span>
            <span class="stat-label">Last Activity</span>
        </div>
    </div>
    
    <!-- ===== Profile Edit Form ===== -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-edit text-primary"></i> Edit Profile</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="profileForm">
                <?php echo Security::csrfField(); ?>
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label for="full_name">Full Name <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                        <input type="text" id="full_name" name="full_name" class="form-control" 
                               value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="class_roll">Class Roll</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-id-badge"></i></span>
                        <input type="text" id="class_roll" class="form-control" 
                               value="<?php echo htmlspecialchars($student['class_roll']); ?>" disabled>
                    </div>
                    <span class="form-help">Class roll cannot be changed</span>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" 
                               placeholder="Enter your email address">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-phone"></i></span>
                        <input type="text" id="phone" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>" 
                               placeholder="Enter your phone number">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="department">Department</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-university"></i></span>
                        <input type="text" id="department" class="form-control" 
                               value="<?php echo htmlspecialchars($student['department']); ?>" disabled>
                    </div>
                    <span class="form-help">Department cannot be changed</span>
                </div>
                
                <div class="form-row">
                    <div class="form-group half">
                        <label for="session">Session</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-calendar"></i></span>
                            <input type="text" id="session" class="form-control" 
                                   value="<?php echo htmlspecialchars($student['session'] ?? ''); ?>" disabled>
                        </div>
                    </div>
                    
                    <div class="form-group half">
                        <label for="batch">Batch</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-calendar-alt"></i></span>
                            <input type="text" id="batch" class="form-control" 
                                   value="<?php echo htmlspecialchars($student['batch'] ?? ''); ?>" disabled>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- ===== Account Information ===== -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-info-circle text-primary"></i> Account Information</h3>
        </div>
        <div class="card-body">
            <div class="account-info-grid">
                <div class="info-item">
                    <label>Account Created</label>
                    <span><?php echo formatDateTime($student['created_at']); ?></span>
                </div>
                <div class="info-item">
                    <label>Approved On</label>
                    <span><?php echo $student['approved_at'] ? formatDateTime($student['approved_at']) : 'Pending'; ?></span>
                </div>
                <div class="info-item">
                    <label>Last Login</label>
                    <span><?php echo $student['last_login'] ? formatDateTime($student['last_login']) : 'Never'; ?></span>
                </div>
                <div class="info-item">
                    <label>Account Status</label>
                    <span class="badge badge-<?php echo $student['status']; ?>">
                        <?php echo ucfirst($student['status']); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>