<?php
/**
 * Admin - Settings
 * System configuration and application settings
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Authorization::requireSuperAdmin();

$pageTitle = 'Settings';
$pageCss = ['admin.css', 'settings.css'];
$pageJs = ['settings.js'];
$isAdmin = true;

$db = Database::getInstance();

// ============================================
// HANDLE SETTINGS UPDATES
// ============================================
$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Validate CSRF
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'];
        
        if ($action === 'update_settings') {
            // Get all settings from POST
            $settings = $_POST['settings'] ?? [];
            
            // Begin transaction
            $db->beginTransaction();
            
            try {
                foreach ($settings as $key => $value) {
                    // Sanitize values
                    $value = trim($value);
                    
                    // Update setting
                    $stmt = $db->prepare("
                        UPDATE settings 
                        SET setting_value = ?, updated_at = NOW() 
                        WHERE setting_key = ?
                    ");
                    $stmt->execute([$value, $key]);
                }
                
                $db->commit();
                $success = 'Settings updated successfully!';
                
                // Log activity
                logActivity('settings_updated', 'System settings updated');
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Failed to update settings: ' . $e->getMessage();
            }
        } elseif ($action === 'update_profile') {
            // Update admin profile
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if (empty($fullName) || empty($email)) {
                $error = 'Name and email are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                // Get current admin
                $stmt = $db->prepare("SELECT * FROM admins WHERE id = ?");
                $stmt->execute([Session::get('user_id')]);
                $admin = $stmt->fetch();
                
                if (!$admin) {
                    $error = 'Admin not found.';
                } else {
                    // Update basic info
                    $stmt = $db->prepare("
                        UPDATE admins 
                        SET full_name = ?, email = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$fullName, $email, $admin['id']]);
                    
                    // Update password if provided
                    if (!empty($currentPassword) && !empty($newPassword)) {
                        if (!password_verify($currentPassword, $admin['password_hash'])) {
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
                            $stmt->execute([$newHash, $admin['id']]);
                            $success = 'Profile updated successfully! Password has been changed.';
                            logActivity('admin_password_changed', 'Admin password changed');
                        }
                    } else {
                        $success = 'Profile updated successfully!';
                        logActivity('admin_profile_updated', 'Admin profile updated');
                    }
                }
            }
        } elseif ($action === 'run_maintenance') {
            // Run maintenance tasks
            try {
                // Clean old activity logs (older than 90 days)
                $stmt = $db->prepare("
                    DELETE FROM activity_logs 
                    WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
                ");
                $stmt->execute();
                $logsDeleted = $stmt->rowCount();
                
                // Clean old notifications (older than 30 days)
                $stmt = $db->prepare("
                    DELETE FROM notifications 
                    WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) AND is_read = 1
                ");
                $stmt->execute();
                $notificationsDeleted = $stmt->rowCount();
                
                $success = "Maintenance completed! Deleted {$logsDeleted} old logs and {$notificationsDeleted} old notifications.";
                logActivity('maintenance_run', 'System maintenance performed');
                
            } catch (Exception $e) {
                $error = 'Maintenance failed: ' . $e->getMessage();
            }
        }
    }
}

// ============================================
// GET CURRENT SETTINGS
// ============================================
$stmt = $db->query("
    SELECT setting_key, setting_value, setting_type, description 
    FROM settings 
    ORDER BY setting_key
");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row;
}

// ============================================
// GET ADMIN PROFILE
// ============================================
$stmt = $db->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([Session::get('user_id')]);
$admin = $stmt->fetch();

include_once BASE_PATH . '/includes/header.php';
include_once BASE_PATH . '/includes/sidebar-admin.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>System Settings</h1>
                <p class="text-muted">Configure and manage system settings</p>
            </div>
        </div>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <!-- ===== Settings Tabs ===== -->
    <div class="settings-tabs">
        <button class="tab-btn active" data-tab="general">
            <i class="fas fa-cog"></i> General
        </button>
        <button class="tab-btn" data-tab="profile">
            <i class="fas fa-user"></i> Profile
        </button>
        <button class="tab-btn" data-tab="security">
            <i class="fas fa-shield-alt"></i> Security
        </button>
        <button class="tab-btn" data-tab="maintenance">
            <i class="fas fa-tools"></i> Maintenance
        </button>
        <button class="tab-btn" data-tab="system">
            <i class="fas fa-server"></i> System Info
        </button>
    </div>
    
    <!-- ============================================
    TAB 1: GENERAL SETTINGS
    ============================================ -->
    <div class="tab-content active" id="tab-general">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-cog text-primary"></i> General Settings</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="settingsForm">
                    <?php echo Security::csrfField(); ?>
                    <input type="hidden" name="action" value="update_settings">
                    
                    <div class="settings-grid">
                        <!-- App Name -->
                        <div class="form-group">
                            <label for="app_name">Application Name</label>
                            <input type="text" id="app_name" name="settings[app_name]" 
                                   class="form-control" 
                                   value="<?php echo htmlspecialchars($settings['app_name']['setting_value'] ?? 'ClassMate'); ?>">
                            <span class="form-help">The name of the application</span>
                        </div>
                        
                        <!-- App Version -->
                        <div class="form-group">
                            <label for="app_version">Application Version</label>
                            <input type="text" id="app_version" name="settings[app_version]" 
                                   class="form-control" 
                                   value="<?php echo htmlspecialchars($settings['app_version']['setting_value'] ?? '2.0.0'); ?>">
                            <span class="form-help">Current version number</span>
                        </div>
                        
                        <!-- Default Currency -->
                        <div class="form-group">
                            <label for="default_currency">Default Currency</label>
                            <select id="default_currency" name="settings[default_currency]" class="form-control">
                                <option value="BDT" <?php echo ($settings['default_currency']['setting_value'] ?? 'BDT') === 'BDT' ? 'selected' : ''; ?>>BDT - Bangladeshi Taka</option>
                                <option value="USD" <?php echo ($settings['default_currency']['setting_value'] ?? 'BDT') === 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                                <option value="EUR" <?php echo ($settings['default_currency']['setting_value'] ?? 'BDT') === 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                                <option value="GBP" <?php echo ($settings['default_currency']['setting_value'] ?? 'BDT') === 'GBP' ? 'selected' : ''; ?>>GBP - British Pound</option>
                            </select>
                            <span class="form-help">Default currency for financial transactions</span>
                        </div>
                        
                        <!-- Timezone -->
                        <div class="form-group">
                            <label for="timezone">Timezone</label>
                            <select id="timezone" name="settings[timezone]" class="form-control">
                                <option value="Asia/Dhaka" <?php echo ($settings['timezone']['setting_value'] ?? 'Asia/Dhaka') === 'Asia/Dhaka' ? 'selected' : ''; ?>>Asia/Dhaka</option>
                                <option value="Asia/Kolkata" <?php echo ($settings['timezone']['setting_value'] ?? 'Asia/Dhaka') === 'Asia/Kolkata' ? 'selected' : ''; ?>>Asia/Kolkata</option>
                                <option value="Asia/Karachi" <?php echo ($settings['timezone']['setting_value'] ?? 'Asia/Dhaka') === 'Asia/Karachi' ? 'selected' : ''; ?>>Asia/Karachi</option>
                                <option value="Asia/Dubai" <?php echo ($settings['timezone']['setting_value'] ?? 'Asia/Dhaka') === 'Asia/Dubai' ? 'selected' : ''; ?>>Asia/Dubai</option>
                                <option value="UTC" <?php echo ($settings['timezone']['setting_value'] ?? 'Asia/Dhaka') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                            </select>
                            <span class="form-help">System timezone</span>
                        </div>
                        
                        <!-- Session Timeout -->
                        <div class="form-group">
                            <label for="session_timeout">Session Timeout (seconds)</label>
                            <input type="number" id="session_timeout" name="settings[session_timeout]" 
                                   class="form-control" 
                                   value="<?php echo htmlspecialchars($settings['session_timeout']['setting_value'] ?? 3600); ?>">
                            <span class="form-help">Time in seconds before session expires (3600 = 1 hour)</span>
                        </div>
                        
                        <!-- Registration Enabled -->
                        <div class="form-group">
                            <label>Registration Status</label>
                            <div class="switch-group">
                                <div class="switch">
                                    <input type="checkbox" id="registration_enabled" 
                                           name="settings[registration_enabled]" value="1"
                                           <?php echo ($settings['registration_enabled']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </div>
                                <label for="registration_enabled">Enable Student Registration</label>
                            </div>
                            <span class="form-help">Allow new students to register</span>
                        </div>
                        
                        <!-- Maintenance Mode -->
                        <div class="form-group">
                            <label>Maintenance Mode</label>
                            <div class="switch-group">
                                <div class="switch">
                                    <input type="checkbox" id="maintenance_mode" 
                                           name="settings[maintenance_mode]" value="1"
                                           <?php echo ($settings['maintenance_mode']['setting_value'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </div>
                                <label for="maintenance_mode">Enable Maintenance Mode</label>
                            </div>
                            <span class="form-help">When enabled, only admins can access the system</span>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- ============================================
    TAB 2: PROFILE SETTINGS
    ============================================ -->
    <div class="tab-content" id="tab-profile">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user text-primary"></i> Profile Settings</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="profileForm">
                    <?php echo Security::csrfField(); ?>
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="profile-avatar">
                        <div class="avatar-circle">
                            <?php echo strtoupper(substr($admin['full_name'] ?? 'A', 0, 2)); ?>
                        </div>
                        <div class="avatar-info">
                            <h4><?php echo htmlspecialchars($admin['full_name'] ?? 'Admin'); ?></h4>
                            <p class="text-muted"><?php echo htmlspecialchars($admin['role'] ?? 'Administrator'); ?></p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name">Full Name <span class="required">*</span></label>
                        <input type="text" id="full_name" name="full_name" class="form-control" 
                               value="<?php echo htmlspecialchars($admin['full_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" class="form-control" 
                               value="<?php echo htmlspecialchars($admin['username'] ?? ''); ?>" disabled>
                        <span class="form-help">Username cannot be changed</span>
                    </div>
                    
                    <hr>
                    <h4>Change Password</h4>
                    <p class="text-muted">Leave empty to keep current password</p>
                    
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-lock"></i></span>
                            <input type="password" id="current_password" name="current_password" 
                                   class="form-control" placeholder="Enter current password">
                            <button type="button" class="toggle-pin" onclick="togglePasswordVisibility('current_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-key"></i></span>
                                <input type="password" id="new_password" name="new_password" 
                                       class="form-control" placeholder="Enter new password" minlength="6">
                                <button type="button" class="toggle-pin" onclick="togglePasswordVisibility('new_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <span class="form-help">Minimum 6 characters</span>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-key"></i></span>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       class="form-control" placeholder="Confirm new password">
                                <button type="button" class="toggle-pin" onclick="togglePasswordVisibility('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
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
    </div>
    
    // ... continue in next message ...
        <!-- ============================================
    TAB 3: SECURITY SETTINGS
    ============================================ -->
    <div class="tab-content" id="tab-security">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-shield-alt text-primary"></i> Security Settings</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="securityForm">
                    <?php echo Security::csrfField(); ?>
                    <input type="hidden" name="action" value="update_settings">
                    
                    <div class="settings-grid">
                        <!-- Max Login Attempts -->
                        <div class="form-group">
                            <label for="max_login_attempts">Max Login Attempts</label>
                            <input type="number" id="max_login_attempts" 
                                   name="settings[max_login_attempts]" 
                                   class="form-control" 
                                   value="<?php echo htmlspecialchars($settings['max_login_attempts']['setting_value'] ?? 5); ?>"
                                   min="1" max="10">
                            <span class="form-help">Number of failed attempts before lockout</span>
                        </div>
                        
                        <!-- Student PIN Length -->
                        <div class="form-group">
                            <label for="student_pin_length">Student PIN Length</label>
                            <input type="number" id="student_pin_length" 
                                   name="settings[student_pin_length]" 
                                   class="form-control" 
                                   value="<?php echo htmlspecialchars($settings['student_pin_length']['setting_value'] ?? 6); ?>"
                                   min="4" max="10">
                            <span class="form-help">Number of digits for student PIN</span>
                        </div>
                        
                        <!-- Max Upload Size -->
                        <div class="form-group">
                            <label for="max_upload_size">Max Upload Size (bytes)</label>
                            <input type="number" id="max_upload_size" 
                                   name="settings[max_upload_size]" 
                                   class="form-control" 
                                   value="<?php echo htmlspecialchars($settings['max_upload_size']['setting_value'] ?? 10485760); ?>">
                            <span class="form-help">Maximum file upload size in bytes (10MB = 10485760)</span>
                        </div>
                        
                        <!-- Allowed File Types -->
                        <div class="form-group">
                            <label for="allowed_file_types">Allowed File Types</label>
                            <input type="text" id="allowed_file_types" 
                                   name="settings[allowed_file_types]" 
                                   class="form-control" 
                                   value="<?php echo htmlspecialchars($settings['allowed_file_types']['setting_value'] ?? 'pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,zip'); ?>">
                            <span class="form-help">Comma-separated list of allowed file extensions</span>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Security Settings
                        </button>
                    </div>
                </form>
                
                <hr>
                
                <!-- Session Management -->
                <h4>Session Management</h4>
                <div class="session-actions">
                    <button class="btn btn-warning" onclick="clearAllSessions()">
                        <i class="fas fa-sign-out-alt"></i> Clear All User Sessions
                    </button>
                    <span class="text-muted">This will log out all users except you</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ============================================
    TAB 4: MAINTENANCE
    ============================================ -->
    <div class="tab-content" id="tab-maintenance">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-tools text-primary"></i> Maintenance</h3>
            </div>
            <div class="card-body">
                <!-- Database Maintenance -->
                <div class="maintenance-section">
                    <h4><i class="fas fa-database"></i> Database Maintenance</h4>
                    <p class="text-muted">Clean up old data to keep the database optimized</p>
                    
                    <form method="POST" action="" id="maintenanceForm">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="run_maintenance">
                        
                        <div class="maintenance-actions">
                            <button type="submit" class="btn btn-primary" 
                                    onclick="return confirm('Run maintenance? This will delete old logs and notifications.')">
                                <i class="fas fa-broom"></i> Run Maintenance
                            </button>
                        </div>
                    </form>
                </div>
                
                <hr>
                
                <!-- System Backup -->
                <div class="maintenance-section">
                    <h4><i class="fas fa-archive"></i> Backup</h4>
                    <p class="text-muted">Download a backup of the database</p>
                    
                    <div class="maintenance-actions">
                        <a href="<?php echo url('api/backup-api.php'); ?>" class="btn btn-success" target="_blank">
                            <i class="fas fa-download"></i> Download Database Backup
                        </a>
                    </div>
                </div>
                
                <hr>
                
                <!-- Cache Management -->
                <div class="maintenance-section">
                    <h4><i class="fas fa-bolt"></i> Cache Management</h4>
                    <p class="text-muted">Clear system cache</p>
                    
                    <div class="maintenance-actions">
                        <button class="btn btn-warning" onclick="clearCache()">
                            <i class="fas fa-trash"></i> Clear Cache
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ============================================
    TAB 5: SYSTEM INFORMATION
    ============================================ -->
    <div class="tab-content" id="tab-system">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-server text-primary"></i> System Information</h3>
            </div>
            <div class="card-body">
                <div class="system-info-grid">
                    <!-- PHP Info -->
                    <div class="info-section">
                        <h4><i class="fas fa-php"></i> PHP Configuration</h4>
                        <div class="info-row">
                            <label>PHP Version</label>
                            <span><?php echo phpversion(); ?></span>
                        </div>
                        <div class="info-row">
                            <label>Server Software</label>
                            <span><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></span>
                        </div>
                        <div class="info-row">
                            <label>Max Upload Size</label>
                            <span><?php echo ini_get('upload_max_filesize'); ?></span>
                        </div>
                        <div class="info-row">
                            <label>Max Post Size</label>
                            <span><?php echo ini_get('post_max_size'); ?></span>
                        </div>
                        <div class="info-row">
                            <label>Memory Limit</label>
                            <span><?php echo ini_get('memory_limit'); ?></span>
                        </div>
                    </div>
                    
                    <!-- Application Info -->
                    <div class="info-section">
                        <h4><i class="fas fa-cube"></i> Application Info</h4>
                        <div class="info-row">
                            <label>Application Name</label>
                            <span><?php echo APP_NAME; ?></span>
                        </div>
                        <div class="info-row">
                            <label>Version</label>
                            <span><?php echo APP_VERSION; ?></span>
                        </div>
                        <div class="info-row">
                            <label>Environment</label>
                            <span class="badge badge-<?php echo APP_ENV === 'production' ? 'success' : 'warning'; ?>">
                                <?php echo APP_ENV; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <label>Database</label>
                            <span><?php echo DB_NAME; ?></span>
                        </div>
                        <div class="info-row">
                            <label>Base URL</label>
                            <span><?php echo BASE_URL; ?></span>
                        </div>
                    </div>
                    
                    <!-- Database Statistics -->
                    <div class="info-section">
                        <h4><i class="fas fa-database"></i> Database Statistics</h4>
                        <?php
                        $tables = $db->query("SHOW TABLES");
                        $totalTables = 0;
                        $totalRecords = 0;
                        while ($row = $tables->fetch()) {
                            $table = reset($row);
                            $totalTables++;
                            $stmt = $db->query("SELECT COUNT(*) FROM $table");
                            $totalRecords += $stmt->fetchColumn();
                        }
                        ?>
                        <div class="info-row">
                            <label>Total Tables</label>
                            <span><?php echo $totalTables; ?></span>
                        </div>
                        <div class="info-row">
                            <label>Total Records</label>
                            <span><?php echo number_format($totalRecords); ?></span>
                        </div>
                        <div class="info-row">
                            <label>Database Size</label>
                            <span>
                                <?php
                                $stmt = $db->query("
                                    SELECT SUM(data_length + index_length) / 1024 / 1024 as size 
                                    FROM information_schema.tables 
                                    WHERE table_schema = '".DB_NAME."'
                                ");
                                $size = $stmt->fetchColumn();
                                echo number_format($size, 2) . ' MB';
                                ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Server Info -->
                    <div class="info-section">
                        <h4><i class="fas fa-server"></i> Server Info</h4>
                        <div class="info-row">
                            <label>Server IP</label>
                            <span><?php echo $_SERVER['SERVER_ADDR'] ?? 'N/A'; ?></span>
                        </div>
                        <div class="info-row">
                            <label>Server Port</label>
                            <span><?php echo $_SERVER['SERVER_PORT'] ?? 'N/A'; ?></span>
                        </div>
                        <div class="info-row">
                            <label>Document Root</label>
                            <span><?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'N/A'; ?></span>
                        </div>
                        <div class="info-row">
                            <label>Operating System</label>
                            <span><?php echo php_uname('s') . ' ' . php_uname('r'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
    JAVASCRIPT
============================================ -->
<script>
// ===== Tab Navigation =====
document.addEventListener('DOMContentLoaded', function() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active from all tabs
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            // Add active to current tab
            this.classList.add('active');
            const tabId = this.dataset.tab;
            document.getElementById('tab-' + tabId).classList.add('active');
        });
    });
});

// ===== Toggle Password Visibility =====
function togglePasswordVisibility(inputId) {
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

// ===== Clear All Sessions =====
function clearAllSessions() {
    if (confirm('Are you sure you want to clear all user sessions? This will log out all users.')) {
        fetch('<?php echo url('api/session-api.php'); ?>?action=clear_all', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '<?php echo Security::generateCSRFToken(); ?>'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('All sessions cleared successfully!');
                location.reload();
            } else {
                alert('Failed to clear sessions: ' + data.message);
            }
        })
        .catch(() => {
            alert('An error occurred.');
        });
    }
}

// ===== Clear Cache =====
function clearCache() {
    if (confirm('Clear system cache?')) {
        fetch('<?php echo url('api/cache-api.php'); ?>?action=clear', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '<?php echo Security::generateCSRFToken(); ?>'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Cache cleared successfully!');
            } else {
                alert('Failed to clear cache: ' + data.message);
            }
        })
        .catch(() => {
            alert('An error occurred.');
        });
    }
}

// ===== Profile Form Validation =====
document.getElementById('profileForm').addEventListener('submit', function(e) {
    const currentPassword = document.getElementById('current_password').value;
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword || confirmPassword) {
        if (!currentPassword) {
            e.preventDefault();
            alert('Please enter your current password to change it.');
            return false;
        }
        
        if (newPassword.length < 6) {
            e.preventDefault();
            alert('New password must be at least 6 characters.');
            return false;
        }
        
        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('New passwords do not match.');
            return false;
        }
    }
    
    return true;
});

// ===== Settings Form Auto-Submit for Switches =====
document.querySelectorAll('.switch input[type="checkbox"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        // Set value to 1 if checked, else 0
        this.value = this.checked ? '1' : '0';
        // Auto-submit the form after 500ms delay
        clearTimeout(this.submitTimeout);
        this.submitTimeout = setTimeout(() => {
            document.getElementById('settingsForm').submit();
        }, 500);
    });
});
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>