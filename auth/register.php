<?php
/**
 * Student Registration Page
 * Complete registration with PIN setup
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

$pageTitle = 'Student Registration - ClassMate';
$pageCss = ['auth.css', 'register.css'];
$pageJs = ['register.js'];
$step = $_GET['step'] ?? 'register';

$db = Database::getInstance();

// ============================================
// STEP 1: REGISTRATION WITH PIN SETUP
// ============================================
if ($step === 'register') {
    // Check if registration is enabled
    $stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'registration_enabled'");
    $registrationEnabled = $stmt->fetchColumn();

    if ($registrationEnabled === '0') {
        Session::setFlash('warning', 'Registration is currently disabled. Please contact the administrator.');
        header('Location: ' . url('auth/login.php'));
        exit;
    }

    // Get available batches for dropdown
    $stmt = $db->query("SELECT id, name, label FROM batches WHERE is_active = 1 ORDER BY FIELD(name, 'First year', 'Second year', 'Third Year', 'Fourth year'), name");
    $availableBatches = $stmt->fetchAll();

    $error = null;
    $success = null;
    $formData = [
        'full_name' => '',
        'class_roll' => '',
        'email' => '',
        'phone' => '',
        'pin' => '',
        'pin_confirm' => '',
        'department' => 'Geography and Environment',
        'session' => date('Y') . '-' . (date('Y') + 1),
        'batch' => date('Y'),
        'batch_id' => ''
    ];

    // Handle registration form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
        // Validate CSRF token
        if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $error = 'Invalid security token. Please try again.';
        } else {
            // Get form data
            $fullName = trim($_POST['full_name'] ?? '');
            $classRoll = trim($_POST['class_roll'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $pin = $_POST['pin'] ?? '';
            $pinConfirm = $_POST['pin_confirm'] ?? '';
            $department = trim($_POST['department'] ?? 'Geography and Environment');
            $session = trim($_POST['session'] ?? '');
            $batchId = (int)($_POST['batch_id'] ?? 0);
            
            // Resolve batch name from batch_id
            $batchName = '';
            if ($batchId) {
                $bStmt = $db->prepare("SELECT name FROM batches WHERE id = ? AND is_active = 1");
                $bStmt->execute([$batchId]);
                $batchRow = $bStmt->fetch();
                $batchName = $batchRow ? $batchRow['name'] : '';
            }
            
            // Store for repopulation
            $formData = [
                'full_name' => $fullName,
                'class_roll' => $classRoll,
                'email' => $email,
                'phone' => $phone,
                'pin' => $pin,
                'pin_confirm' => $pinConfirm,
                'department' => $department,
                'session' => $session,
                'batch' => $batchName,
                'batch_id' => $batchId
            ];
            
            // Validate
            $validator = new Validation($_POST);
            $validator->setRules([
                'full_name' => 'required|min:2|max:100',
                'class_roll' => 'required|min:3|max:20',
                'email' => 'optional|email|max:100',
                'phone' => 'optional|max:20',
                'pin' => 'required|min:6|max:20',
                'pin_confirm' => 'required|match:pin',
                'department' => 'optional|max:100',
                'session' => 'optional|max:20',
                'batch_id' => 'optional'
            ]);
            
            if (!$validator->validate()) {
                $error = $validator->getFirstError();
            } elseif (!$batchId || empty($batchName)) {
                $error = 'Please select a valid batch.';
            } elseif (!preg_match('/^[0-9]+$/', $pin)) {
                $error = 'PIN must contain only numbers.';
            } elseif (strlen($pin) < 6) {
                $error = 'PIN must be at least 6 digits.';
            } else {
                // Check if class roll already exists in students table
                $stmt = $db->prepare("SELECT id FROM students WHERE class_roll = ?");
                $stmt->execute([$classRoll]);
                if ($stmt->fetch()) {
                    $error = 'This class roll is already registered. Please contact the administrator.';
                }
                
                // Check if there's a pending request
                if (!$error) {
                    $stmt = $db->prepare("
                        SELECT id FROM registration_requests 
                        WHERE class_roll = ? AND status = 'pending'
                    ");
                    $stmt->execute([$classRoll]);
                    if ($stmt->fetch()) {
                        $error = 'A registration request for this class roll is already pending. Please wait for approval.';
                    }
                }
                
                // Proceed with registration
                if (!$error) {
                    // Hash the PIN
                    $pinHash = Auth::hashPassword($pin);
                    
                    // Begin transaction
                    $db->beginTransaction();
                    
                    try {
                        // Create registration request
                        $stmt = $db->prepare("
                            INSERT INTO registration_requests 
                            (full_name, class_roll, email, phone, department, session, batch, batch_id, status, requested_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                        ");
                        $stmt->execute([$fullName, $classRoll, $email, $phone, $department, $session, $batchName, $batchId]);
                        
                        $requestId = $db->lastInsertId();
                        // স্টুডেন্ট সেভ হওয়ার পর নিচের কোডটুকু দিন:
require_once BASE_PATH . '/includes/NotificationManager.php';
$notifier = new NotificationManager();

$notifier->notifyAdmins(
    '👤 New Registration Request',
    'Student ' . $fullName . ' (' . $classRoll . ') has registered and is waiting for approval.',
    'warning',
    url('admin/registrations.php')
);
                        
                        // Create student account with pending status and PIN
                        $stmt = $db->prepare("
                            INSERT INTO students 
                            (full_name, class_roll, email, phone, pin_hash, department, session, batch, batch_id, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                        ");
                        $stmt->execute([
                            $fullName, 
                            $classRoll, 
                            $email, 
                            $phone, 
                            $pinHash, 
                            $department, 
                            $session, 
                            $batchName,
                            $batchId
                        ]);
                        
                        $studentId = $db->lastInsertId();
                        
                        // Notify the batch-specific admin (or super admin if no batch admin exists)
                        $adminStmt = $db->prepare("
                            SELECT id, full_name FROM admins 
                            WHERE status = 'active' AND (batch_id = ? OR batch_id IS NULL)
                            ORDER BY batch_id DESC LIMIT 1
                        ");
                        $adminStmt->execute([$batchId]);
                        $admin = $adminStmt->fetch();
                        if ($admin) {
                            $message = "New registration request from $fullName (Roll: $classRoll, Batch: $batchName)";
                            sendNotification($admin['id'], 'admin', 'New Registration Request', $message, 'info', url('admin/registrations.php'));
                        }
                        
                        // Log activity
                        logActivity('student_registration', "Student registered: $fullName (Roll: $classRoll)");
                        
                        $db->commit();
                        
                        $success = 'Your registration has been submitted successfully! Please wait for admin approval.';
                        
                    } catch (Exception $e) {
                        $db->rollBack();
                        $error = 'Registration failed. Please try again.';
                    }
                }
            }
        }
    }
}

// ============================================
// STEP 2: REGISTRATION STATUS
// ============================================
if ($step === 'status') {
    $classRoll = $_GET['roll'] ?? '';
    
    if (!empty($classRoll)) {
        $stmt = $db->prepare("
            SELECT * FROM registration_requests 
            WHERE class_roll = ? 
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$classRoll]);
        $request = $stmt->fetch();
        
        if ($request) {
            $status = $request['status'];
        } else {
            // Check in students table
            $stmt = $db->prepare("SELECT status FROM students WHERE class_roll = ?");
            $stmt->execute([$classRoll]);
            $student = $stmt->fetch();
            if ($student) {
                $status = $student['status'];
            } else {
                $status = 'not_found';
            }
        }
    } else {
        $status = 'no_roll';
    }
}

include_once BASE_PATH . '/includes/header.php';
?>

<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            
            <?php if ($step === 'register'): ?>
            <!-- ============================================
            STEP 1: REGISTRATION FORM WITH PIN
            ============================================ -->
            
            <div class="auth-header">
                <div class="auth-logo">
                    <img src="<?php echo asset('images/logo.svg'); ?>" alt="ClassMate">
                </div>
                <h1>Student Registration</h1>
                <p class="auth-subtitle">Create your account and set your PIN</p>
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
                <div class="registration-success-actions">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3>Registration Submitted!</h3>
                    <p>Your registration has been submitted successfully.</p>
                    <div class="next-steps">
                        <h4>What happens next?</h4>
                        <ol>
                            <li>Admin will review your request</li>
                            <li>You will be notified once approved</li>
                            <li>Login with your class roll and PIN</li>
                        </ol>
                    </div>
                    <div class="action-buttons">
                        <a href="<?php echo url('auth/register.php?step=status&roll=' . urlencode($formData['class_roll'])); ?>" class="btn btn-primary">
                            <i class="fas fa-search"></i> Check Status
                        </a>
                        <a href="<?php echo url('auth/student-login.php'); ?>" class="btn btn-outline">
                            <i class="fas fa-sign-in-alt"></i> Back to Login
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <form method="POST" action="" class="auth-form" id="registrationForm">
                    <?php echo Security::csrfField(); ?>
                    <input type="hidden" name="action" value="register">
                    
                    <div class="form-group">
                        <label for="full_name">Full Name <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-user"></i></span>
                            <input type="text" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($formData['full_name']); ?>" 
                                   placeholder="Enter your full name" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="class_roll">Class Roll <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-id-badge"></i></span>
                            <input type="text" id="class_roll" name="class_roll" 
                                   value="<?php echo htmlspecialchars($formData['class_roll']); ?>" 
                                   placeholder="e.g., 2024-001" required>
                        </div>
                        <span class="form-help">Enter your unique class roll number.</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-envelope"></i></span>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($formData['email']); ?>" 
                                   placeholder="your@email.com">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-phone"></i></span>
                            <input type="text" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($formData['phone']); ?>" 
                                   placeholder="Enter your phone number">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="department">Department</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-university"></i></span>
                            <input type="text" id="department" name="department" 
                                   value="<?php echo htmlspecialchars($formData['department']); ?>" 
                                   placeholder="Department name">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group half">
                            <label for="session">Session</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-calendar"></i></span>
                                <input type="text" id="session" name="session" 
                                       value="<?php echo htmlspecialchars($formData['session']); ?>" 
                                       placeholder="e.g., 2024-2025">
                            </div>
                        </div>
                        
                        <div class="form-group half">
                            <label for="batch_id">Batch <span class="required">*</span></label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-calendar-alt"></i></span>
                                <select id="batch_id" name="batch_id" required>
                                    <option value="">-- Select Batch --</option>
                                    <?php foreach ($availableBatches as $b): ?>
                                        <option value="<?php echo $b['id']; ?>" <?php echo (int)$formData['batch_id'] === (int)$b['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($b['label'] ?: $b['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <span class="form-help">Select your class batch year.</span>
                        </div>
                    </div>
                    
                    <!-- ===== PIN SETUP SECTION ===== -->
                    <div class="pin-section">
                        <div class="pin-section-header">
                            <i class="fas fa-lock"></i>
                            <h3>Set Your PIN</h3>
                            <p>Create a secure PIN for your account</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="pin">Create PIN <span class="required">*</span></label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-key"></i></span>
                                <input type="password" id="pin" name="pin" 
                                       placeholder="Enter a 6-digit PIN" 
                                       minlength="6" maxlength="20" 
                                       value="<?php echo htmlspecialchars($formData['pin']); ?>"
                                       required>
                                <button type="button" class="toggle-pin" onclick="togglePinVisibility('pin')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <span class="form-help">PIN must be at least 6 digits and contain only numbers.</span>
                        </div>
                        
                        <div class="form-group">
                            <label for="pin_confirm">Confirm PIN <span class="required">*</span></label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-key"></i></span>
                                <input type="password" id="pin_confirm" name="pin_confirm" 
                                       placeholder="Confirm your PIN" 
                                       minlength="6" maxlength="20" 
                                       value="<?php echo htmlspecialchars($formData['pin_confirm']); ?>"
                                       required>
                                <button type="button" class="toggle-pin" onclick="togglePinVisibility('pin_confirm')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group password-strength">
                            <label>PIN Strength</label>
                            <div class="strength-bar">
                                <div class="strength-bar-fill" id="strengthBar"></div>
                            </div>
                            <span class="strength-text" id="strengthText">Enter a PIN</span>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-block" id="registerBtn">
                            <i class="fas fa-user-plus"></i> Register & Set PIN
                        </button>
                    </div>
                </form>
                
                <div class="auth-footer">
                    <p>Already have an account? <a href="<?php echo url('auth/student-login.php'); ?>">Login here</a></p>
                    <p><a href="<?php echo url('auth/register.php?step=status'); ?>">Check registration status</a></p>
                </div>
            <?php endif; ?>
            
            <?php elseif ($step === 'status'): ?>
            <!-- ============================================
            STEP 2: REGISTRATION STATUS
            ============================================ -->
            
            <div class="auth-header">
                <div class="auth-logo">
                    <img src="<?php echo asset('images/logo.svg'); ?>" alt="ClassMate">
                </div>
                <h1>Registration Status</h1>
                <p class="auth-subtitle">Check the status of your registration</p>
            </div>
            
            <?php if ($status === 'no_roll'): ?>
                <form method="GET" action="" class="auth-form">
                    <input type="hidden" name="step" value="status">
                    <div class="form-group">
                        <label for="roll">Enter Your Class Roll</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-id-badge"></i></span>
                            <input type="text" id="roll" name="roll" 
                                   placeholder="e.g., 2024-001" required>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-search"></i> Check Status
                        </button>
                    </div>
                </form>
            <?php elseif ($status === 'not_found'): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    No registration found for this class roll.
                </div>
                <div class="auth-footer">
                    <a href="<?php echo url('auth/register.php'); ?>" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Register Now
                    </a>
                </div>
            <?php else: ?>
                <div class="status-card">
                    <div class="status-header">
                        <h3>Registration Status</h3>
                        <span class="status-badge status-<?php echo $status; ?>">
                            <?php echo ucfirst($status); ?>
                        </span>
                    </div>
                    <div class="status-body">
                        <?php if ($status === 'pending'): ?>
                            <div class="status-message">
                                <i class="fas fa-clock text-warning"></i>
                                <p>Your registration is pending approval. Please wait for the admin to review your request.</p>
                            </div>
                        <?php elseif ($status === 'approved'): ?>
                            <div class="status-message">
                                <i class="fas fa-check-circle text-success"></i>
                                <p>Your registration has been approved! You can now login with your class roll and PIN.</p>
                            </div>
                            <div class="action-buttons">
                                <a href="<?php echo url('auth/student-login.php'); ?>" class="btn btn-success">
                                    <i class="fas fa-sign-in-alt"></i> Login Now
                                </a>
                            </div>
                        <?php elseif ($status === 'rejected'): ?>
                            <div class="status-message">
                                <i class="fas fa-times-circle text-danger"></i>
                                <p>Your registration has been rejected. Please contact the administrator for more information.</p>
                            </div>
                        <?php elseif ($status === 'active'): ?>
                            <div class="status-message">
                                <i class="fas fa-check-circle text-success"></i>
                                <p>Your account is active! You can login with your class roll and PIN.</p>
                            </div>
                            <div class="action-buttons">
                                <a href="<?php echo url('auth/student-login.php'); ?>" class="btn btn-success">
                                    <i class="fas fa-sign-in-alt"></i> Login Now
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="auth-footer">
                    <a href="<?php echo url('auth/register.php?step=status'); ?>" class="btn btn-outline">
                        <i class="fas fa-search"></i> Check Another
                    </a>
                    <a href="<?php echo url('auth/register.php'); ?>" class="btn btn-outline">
                        <i class="fas fa-user-plus"></i> New Registration
                    </a>
                </div>
            <?php endif; ?>
            
            <?php endif; ?>
            
        </div>
        
        <!-- Info Sidebar -->
        <?php if ($step === 'register' && !$success): ?>
        <div class="auth-info">
            <div class="info-card">
                <i class="fas fa-info-circle"></i>
                <h4>Registration Process</h4>
                <ol>
                    <li>Fill in your details</li>
                    <li>Set your secure PIN</li>
                    <li>Submit for approval</li>
                    <li>Wait for admin approval</li>
                    <li>Login with roll and PIN</li>
                </ol>
            </div>
            <div class="info-card">
                <i class="fas fa-shield-alt"></i>
                <h4>PIN Security Tips</h4>
                <ul>
                    <li>Use at least 6 digits</li>
                    <li>Don't share your PIN</li>
                    <li>Memorize your PIN</li>
                    <li>Contact admin if you forget</li>
                </ul>
            </div>
        </div>
        <?php elseif ($step === 'status'): ?>
        <div class="auth-info">
            <div class="info-card">
                <i class="fas fa-question-circle"></i>
                <h4>Need Help?</h4>
                <p>Contact the class representative or department office for assistance with your registration.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    <?php if ($step === 'register' && !$success): ?>
    // ===== PIN Strength Meter =====
    const pinInput = document.getElementById('pin');
    const pinConfirm = document.getElementById('pin_confirm');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const registerBtn = document.getElementById('registerBtn');
    
    function checkPINStrength(pin) {
        let strength = 0;
        
        // Length check
        if (pin.length >= 6) strength += 1;
        if (pin.length >= 8) strength += 1;
        if (pin.length >= 10) strength += 1;
        
        // Only numbers
        if (/^[0-9]+$/.test(pin)) {
            strength += 1;
        }
        
        // Uniqueness (check for repeated patterns)
        if (!/(.)\1{2,}/.test(pin)) {
            strength += 1;
        }
        
        // Sequential numbers
        if (!/(012|123|234|345|456|567|678|789|890)/.test(pin)) {
            strength += 1;
        }
        
        return Math.min(strength, 5);
    }
    
    function updateStrength(pin) {
        const strength = checkPINStrength(pin);
        const percentage = (strength / 5) * 100;
        
        strengthBar.style.width = percentage + '%';
        
        let color, text;
        if (strength === 0) {
            color = '#E5E7EB';
            text = 'Enter a PIN';
        } else if (strength <= 1) {
            color = '#EF4444';
            text = 'Weak - Use at least 6 digits';
        } else if (strength <= 2) {
            color = '#F59E0B';
            text = 'Fair - Add more digits';
        } else if (strength <= 3) {
            color = '#3B82F6';
            text = 'Good - Strong PIN';
        } else {
            color = '#10B981';
            text = 'Strong - Excellent PIN!';
        }
        
        strengthBar.style.background = color;
        strengthText.textContent = text;
        strengthText.style.color = color;
    }
    
    pinInput.addEventListener('input', function() {
        updateStrength(this.value);
        
        // Check if PINs match
        if (pinConfirm.value && this.value !== pinConfirm.value) {
            pinConfirm.classList.add('error');
        } else {
            pinConfirm.classList.remove('error');
        }
    });
    
    pinConfirm.addEventListener('input', function() {
        if (this.value && this.value !== pinInput.value) {
            this.classList.add('error');
        } else {
            this.classList.remove('error');
        }
    });
    
    // ===== Form Validation =====
    document.getElementById('registrationForm').addEventListener('submit', function(e) {
        const fullName = document.getElementById('full_name').value.trim();
        const classRoll = document.getElementById('class_roll').value.trim();
        const pin = document.getElementById('pin').value;
        const confirm = document.getElementById('pin_confirm').value;
        
        if (fullName.length < 2) {
            e.preventDefault();
            alert('Please enter your full name (minimum 2 characters).');
            return false;
        }
        
        if (classRoll.length < 3) {
            e.preventDefault();
            alert('Please enter a valid class roll (minimum 3 characters).');
            return false;
        }
        
        if (pin.length < 6) {
            e.preventDefault();
            alert('PIN must be at least 6 digits.');
            return false;
        }
        
        if (!/^[0-9]+$/.test(pin)) {
            e.preventDefault();
            alert('PIN must contain only numbers.');
            return false;
        }
        
        if (pin !== confirm) {
            e.preventDefault();
            alert('PINs do not match.');
            return false;
        }
        
        return true;
    });
    <?php endif; ?>
});

// ===== Toggle PIN Visibility =====
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