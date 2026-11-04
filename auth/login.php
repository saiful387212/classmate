<?php
/**
 * Unified Login Page - Choose login type
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

$pageTitle = 'Login - ClassMate';
$pageCss = 'auth.css';

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
                <h1>Welcome Back</h1>
                <p class="auth-subtitle">Choose your login type to continue</p>
            </div>
            
            <div class="login-options">
                <a href="<?php echo url('auth/student-login.php'); ?>" class="login-option">
                    <div class="option-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="option-content">
                        <h3>Student Login</h3>
                        <p>Login using your class roll and PIN</p>
                    </div>
                    <i class="fas fa-chevron-right option-arrow"></i>
                </a>
                
                <a href="<?php echo url('auth/admin-login.php'); ?>" class="login-option">
                    <div class="option-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="option-content">
                        <h3>Admin Login</h3>
                        <p>Access the administrative panel</p>
                    </div>
                    <i class="fas fa-chevron-right option-arrow"></i>
                </a>
            </div>
            
            <div class="auth-footer">
                <p>New student? <a href="<?php echo url('auth/register.php'); ?>">Register here</a></p>
                <p><a href="<?php echo url('auth/registration-status.php'); ?>">Check registration status</a></p>
            </div>
        </div>
    </div>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>