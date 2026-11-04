<?php
/**
 * Student Login Page
 */

require_once __DIR__ . '/../config.php';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    if (Authorization::isAdmin()) {
        redirect(url('admin/dashboard.php'));
    } else {
        redirect(url('student/dashboard.php'));
    }
}

$pageTitle = 'Student Login - ClassMate';
$pageCss = 'auth.css';

$error = null;
$classRoll = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $classRoll = trim($_POST['class_roll'] ?? '');
        $pin = $_POST['pin'] ?? '';
        
        if (empty($classRoll) || empty($pin)) {
            $error = 'Please enter both class roll and PIN.';
        } else {
            $result = Auth::studentLogin($classRoll, $pin);
            
            if ($result['success']) {
                Session::setFlash('success', 'Welcome back, ' . $result['user']['full_name'] . '!');
                redirect(url('student/dashboard.php'));
            } else {
                $error = $result['message'];
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
                <h1>Student Login</h1>
                <p class="auth-subtitle">Login using your class roll and PIN</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form" autocomplete="on">
                <?php echo Security::csrfField(); ?>
                
                <div class="form-group">
                    <label for="class_roll">Class Roll</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-id-badge"></i></span>
                        <input type="text" id="class_roll" name="class_roll" autocomplete="username"
                               value="<?php echo htmlspecialchars($classRoll); ?>" 
                               placeholder="Enter your class roll" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="pin">PIN</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" id="pin" name="pin" autocomplete="current-password"
                               placeholder="Enter your PIN" required>
                        <button type="button" class="toggle-pin" onclick="togglePinVisibility()">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <small class="form-help">Enter the 6-digit PIN you received after registration.</small>
                </div>
                
                <div class="form-options">
                    <div class="remember-me">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="<?php echo url('auth/registration-status.php'); ?>" class="forgot-link">
                        Forgot PIN?
                    </a>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </div>
            </form>
            
            <div class="auth-footer">
                <p>Don't have an account? <a href="<?php echo url('auth/register.php'); ?>">Register here</a></p>
                <p><a href="<?php echo url('auth/registration-status.php'); ?>">Check registration status</a></p>
            </div>
        </div>
        
        <div class="auth-info">
            <div class="info-card">
                <i class="fas fa-phone-alt"></i>
                <h4>Need Help?</h4>
                <p>Contact your class representative or the department office for assistance with your PIN.</p>
            </div>
        </div>
    </div>
</div>

<script>
function togglePinVisibility() {
    const pinInput = document.getElementById('pin');
    const toggleBtn = document.querySelector('.toggle-pin i');
    
    if (pinInput.type === 'password') {
        pinInput.type = 'text';
        toggleBtn.className = 'fas fa-eye-slash';
    } else {
        pinInput.type = 'password';
        toggleBtn.className = 'fas fa-eye';
    }
}
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>