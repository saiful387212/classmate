<?php
/**
 * Student - Change PIN
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure student access
Auth::requireStudent();

$pageTitle = 'Change PIN';
$pageCss = ['student.css', 'profile.css'];
$pageJs = ['profile.js'];
$isStudent = true;

$db = Database::getInstance();
$studentId = Session::get('user_id');

// Get student data
$stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    Session::setFlash('error', 'Student not found');
    redirect(url('student/dashboard.php'));
}

// Handle PIN change
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_pin') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $currentPin = $_POST['current_pin'] ?? '';
        $newPin = $_POST['new_pin'] ?? '';
        $confirmPin = $_POST['confirm_pin'] ?? '';
        
        if (empty($currentPin) || empty($newPin) || empty($confirmPin)) {
            $error = 'All PIN fields are required.';
        } elseif (!password_verify($currentPin, $student['pin_hash'])) {
            $error = 'Current PIN is incorrect.';
        } elseif (strlen($newPin) < 6) {
            $error = 'New PIN must be at least 6 digits.';
        } elseif (!preg_match('/^[0-9]+$/', $newPin)) {
            $error = 'PIN must contain only numbers.';
        } elseif ($newPin !== $confirmPin) {
            $error = 'New PINs do not match.';
        } else {
            $newHash = Auth::hashPassword($newPin);
            $stmt = $db->prepare("
                UPDATE students 
                SET pin_hash = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $result = $stmt->execute([$newHash, $studentId]);
            
            if ($result) {
                $success = 'PIN changed successfully!';
                logActivity('student_pin_changed', "Student changed PIN: {$student['class_roll']}");
            } else {
                $error = 'Failed to change PIN.';
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
                <h1>Change PIN</h1>
                <p class="text-muted">Update your PIN for secure access</p>
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
            <h3 class="card-title"><i class="fas fa-key text-primary"></i> Change PIN</h3>
            <span class="text-muted">Roll: <?php echo htmlspecialchars($student['class_roll']); ?></span>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="pinForm">
                <?php echo Security::csrfField(); ?>
                <input type="hidden" name="action" value="change_pin">
                
                <div class="form-group">
                    <label for="current_pin">Current PIN <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" id="current_pin" name="current_pin" 
                               class="form-control" placeholder="Enter current PIN" required>
                        <button type="button" class="toggle-pin" onclick="togglePinVisibility('current_pin')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="new_pin">New PIN <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-key"></i></span>
                            <input type="password" id="new_pin" name="new_pin" 
                                   class="form-control" placeholder="Enter new PIN" required minlength="6">
                            <button type="button" class="toggle-pin" onclick="togglePinVisibility('new_pin')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <span class="form-help">Minimum 6 digits, numbers only</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_pin">Confirm New PIN <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-key"></i></span>
                            <input type="password" id="confirm_pin" name="confirm_pin" 
                                   class="form-control" placeholder="Confirm new PIN" required>
                            <button type="button" class="toggle-pin" onclick="togglePinVisibility('confirm_pin')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Change PIN
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function togglePinVisibility(inputId) {
    const input = document.getElementById(inputId);
    const toggleBtn = input.closest('.input-group').querySelector('.toggle-pin i');
    
    if (input.type === 'password') {
        input.type = 'text';
        toggleBtn.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        toggleBtn.className = 'fas fa-eye';
    }
}
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>