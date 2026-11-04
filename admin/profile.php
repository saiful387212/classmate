<?php
/**
 * Admin - Profile Page
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

$pageTitle = 'My Profile';
$pageCss = ['admin.css', 'profile.css'];
$pageJs = ['profile.js'];
$isAdmin = true;

$db = Database::getInstance();
$adminId = Session::get('user_id');

// Get admin data
$stmt = $db->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

if (!$admin) {
    Session::setFlash('error', 'Admin not found');
    redirect(url('admin/dashboard.php'));
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
            
            if (empty($fullName)) {
                $error = 'Full name is required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                $stmt = $db->prepare("
                    UPDATE admins 
                    SET full_name = ?, email = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $result = $stmt->execute([$fullName, $email, $adminId]);
                
                if ($result) {
                    $success = 'Profile updated successfully!';
                    logActivity('admin_profile_updated', 'Admin profile updated');
                    
                    // Refresh admin data
                    $stmt = $db->prepare("SELECT * FROM admins WHERE id = ?");
                    $stmt->execute([$adminId]);
                    $admin = $stmt->fetch();
                } else {
                    $error = 'Failed to update profile.';
                }
            }
        } elseif ($action === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                $error = 'All password fields are required.';
            } elseif (!password_verify($currentPassword, $admin['password_hash'])) {
                $error = 'Current password is incorrect.';
            } elseif (strlen($newPassword) < 6) {
                $error = 'New password must be at least 6 characters.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'New passwords do not match.';
            } else {
                $newHash = Auth::hashPassword($newPassword);
                $stmt = $db->prepare("
                    UPDATE admins 
                    SET password_hash = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $result = $stmt->execute([$newHash, $adminId]);
                
                if ($result) {
                    $success = 'Password changed successfully!';
                    logActivity('admin_password_changed', 'Admin password changed');
                } else {
                    $error = 'Failed to change password.';
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
                <h1>My Profile</h1>
                <p class="text-muted">Manage your profile and account settings</p>
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
    <div class="profile-overview admin-profile">
        <div class="profile-avatar">
            <div class="avatar-circle">
                <?php echo strtoupper(substr($admin['full_name'] ?? 'A', 0, 2)); ?>
            </div>
            <div class="profile-status">
                <span class="badge badge-<?php echo $admin['status']; ?>">
                    <?php echo ucfirst($admin['status']); ?>
                </span>
            </div>
        </div>
        <div class="profile-info">
            <h2><?php echo htmlspecialchars($admin['full_name']); ?></h2>
            <p class="text-muted">
                <i class="fas fa-user-shield"></i> <?php echo ucfirst($admin['role'] ?? 'Administrator'); ?>
            </p>
            <p class="text-muted">
                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($admin['email']); ?>
            </p>
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
                    <label for="username">Username</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                        <input type="text" id="username" class="form-control" 
                               value="<?php echo htmlspecialchars($admin['username']); ?>" disabled>
                    </div>
                    <span class="form-help">Username cannot be changed</span>
                </div>
                
                <div class="form-group">
                    <label for="full_name">Full Name <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-user-tie"></i></span>
                        <input type="text" id="full_name" name="full_name" class="form-control" 
                               value="<?php echo htmlspecialchars($admin['full_name']); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="role">Role</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-user-shield"></i></span>
                        <input type="text" id="role" class="form-control" 
                               value="<?php echo ucfirst($admin['role'] ?? 'Administrator'); ?>" disabled>
                    </div>
                    <span class="form-help">Role cannot be changed</span>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- ===== Change Password ===== -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-key text-primary"></i> Change Password</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="passwordForm">
                <?php echo Security::csrfField(); ?>
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label for="current_password">Current Password <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" id="current_password" name="current_password" 
                               class="form-control" placeholder="Enter current password" required>
                        <button type="button" class="toggle-pin" onclick="togglePasswordVisibility('current_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="new_password">New Password <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-key"></i></span>
                            <input type="password" id="new_password" name="new_password" 
                                   class="form-control" placeholder="Enter new password" required minlength="6">
                            <button type="button" class="toggle-pin" onclick="togglePasswordVisibility('new_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <span class="form-help">Minimum 6 characters</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-key"></i></span>
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   class="form-control" placeholder="Confirm new password" required>
                            <button type="button" class="toggle-pin" onclick="togglePasswordVisibility('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>