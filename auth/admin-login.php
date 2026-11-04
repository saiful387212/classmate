<?php
/**
 * Admin Login Page
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

$pageTitle = 'Admin Login - ClassMate';
$pageCss = 'auth.css';

$error = null;
$username = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username/email and password.';
        } else {
            $result = Auth::adminLogin($username, $password);
            
            if ($result['success']) {
                Session::setFlash('success', 'Welcome back, ' . $result['user']['full_name'] . '!');
                redirect(url('admin/dashboard.php'));
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
                <h1>Admin Login</h1>
                <p class="auth-subtitle">Access the administrative panel</p>
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
                    <label for="username">Username or Email</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-user-shield"></i></span>
                        <input type="text" id="username" name="username" autocomplete="username"
                               value="<?php echo htmlspecialchars($username); ?>" 
                               placeholder="Enter username or email" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" id="password" name="password" autocomplete="current-password"
                               placeholder="Enter your password" required>
                        <button type="button" class="toggle-pin" onclick="togglePasswordVisibility()">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-options">
                    <div class="remember-me">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me</label>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i> Login to Admin Panel
                    </button>
                </div>
            </form>
            
            <div class="auth-footer">
                <p>Not an admin? <a href="<?php echo url('auth/student-login.php'); ?>">Student Login</a></p>
                <p><a href="<?php echo url(''); ?>">Back to Home</a></p>
            </div>
        </div>
        
        <div class="auth-info">
            <div class="info-card">
                <i class="fas fa-shield-alt"></i>
                <h4>Secure Admin Access</h4>
                <p>This area is restricted to authorized administrators only.</p>
                <p class="text-muted small">Default admin: admin / Admin@2026</p>
            </div>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility() {
    const passwordInput = document.getElementById('password');
    const toggleBtn = document.querySelector('.toggle-pin i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleBtn.className = 'fas fa-eye-slash';
    } else {
        passwordInput.type = 'password';
        toggleBtn.className = 'fas fa-eye';
    }
}
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>