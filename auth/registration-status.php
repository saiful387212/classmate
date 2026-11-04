<?php
/**
 * Registration Status Check Page
 */

require_once __DIR__ . '/../config.php';

$pageTitle = 'Registration Status - ClassMate';
$pageCss = 'auth.css';

$status = null;
$classRoll = '';
$error = null;

// Handle status check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['class_roll'])) {
    $classRoll = trim($_POST['class_roll']);
    
    if (empty($classRoll)) {
        $error = 'Please enter your class roll.';
    } else {
        $db = Database::getInstance();
        
        // Check in registration_requests
        $stmt = $db->prepare("
            SELECT * FROM registration_requests 
            WHERE class_roll = ? 
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$classRoll]);
        $request = $stmt->fetch();
        
        if ($request) {
            $status = [
                'type' => $request['status'],
                'message' => $request['status'] === 'pending' ? 'Your registration is pending approval.' :
                            ($request['status'] === 'approved' ? 'Your registration has been approved!' :
                            ($request['status'] === 'rejected' ? 'Your registration was rejected.' : 'Unknown status')),
                'full_name' => $request['full_name'],
                'status' => $request['status'],
                'created_at' => $request['created_at'],
                'review_notes' => $request['review_notes']
            ];
        } else {
            // Check in students table
            $stmt = $db->prepare("SELECT * FROM students WHERE class_roll = ?");
            $stmt->execute([$classRoll]);
            $student = $stmt->fetch();
            
            if ($student) {
                $status = [
                    'type' => 'active',
                    'message' => 'Your account is active. You can login now.',
                    'full_name' => $student['full_name'],
                    'status' => $student['status'],
                    'created_at' => $student['created_at']
                ];
            } else {
                $error = 'No registration found for this class roll. Please register first.';
            }
        }
    }
}

include_once BASE_PATH . '/includes/header.php';
?>

<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <?php if (file_exists(BASE_PATH . '/assets/images/logo.svg')): ?>
                        <img src="<?php echo asset('images/logo.svg'); ?>" alt="ClassMate">
                    <?php else: ?>
                        <h2>ClassMate</h2>
                    <?php endif; ?>
                </div>
                <h1>Registration Status</h1>
                <p class="auth-subtitle">Check the status of your registration request</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($status): ?>
                <div class="status-card">
                    <div class="status-header">
                        <h3><?php echo htmlspecialchars($status['full_name']); ?></h3>
                        <span class="status-badge status-<?php echo $status['type']; ?>">
                            <?php echo ucfirst($status['type']); ?>
                        </span>
                    </div>
                    
                    <div class="status-body">
                        <p class="status-message"><?php echo htmlspecialchars($status['message']); ?></p>
                        
                        <?php if ($status['status'] === 'pending'): ?>
                            <div class="status-action">
                                <i class="fas fa-clock"></i>
                                <p>Your request is being reviewed. Please check back later.</p>
                            </div>
                        <?php elseif ($status['status'] === 'approved'): ?>
                            <div class="status-action success">
                                <i class="fas fa-check-circle"></i>
                                <div>
                                    <p>Your registration has been approved!</p>
                                    <a href="<?php echo url('auth/student-login.php'); ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-sign-in-alt"></i> Login Now
                                    </a>
                                </div>
                            </div>
                        <?php elseif ($status['status'] === 'rejected'): ?>
                            <div class="status-action error">
                                <i class="fas fa-times-circle"></i>
                                <div>
                                    <p>Your registration was rejected.</p>
                                    <?php if (!empty($status['review_notes'])): ?>
                                        <p class="review-notes">Reason: <?php echo htmlspecialchars($status['review_notes']); ?></p>
                                    <?php endif; ?>
                                    <a href="<?php echo url('auth/register.php'); ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-redo"></i> Re-submit
                                    </a>
                                </div>
                            </div>
                        <?php elseif ($status['status'] === 'active'): ?>
                            <div class="status-action success">
                                <i class="fas fa-check-circle"></i>
                                <div>
                                    <p>Your account is active!</p>
                                    <a href="<?php echo url('auth/student-login.php'); ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-sign-in-alt"></i> Login Now
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($status['created_at']): ?>
                            <p class="status-meta">Requested on: <?php echo formatDateTime($status['created_at']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="<?php echo url('auth/registration-status.php'); ?>" class="btn btn-outline">
                        <i class="fas fa-search"></i> Check Another
                    </a>
                    <a href="<?php echo url('auth/login.php'); ?>" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" action="" class="auth-form">
                    <div class="form-group">
                        <label for="class_roll">Enter Your Class Roll</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-id-badge"></i></span>
                            <input type="text" id="class_roll" name="class_roll" 
                                   value="<?php echo htmlspecialchars($classRoll); ?>" 
                                   placeholder="e.g., 2024-001" required>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-search"></i> Check Status
                        </button>
                    </div>
                </form>
                
                <div class="auth-footer">
                    <p>Don't have an account? <a href="<?php echo url('auth/register.php'); ?>">Register here</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>