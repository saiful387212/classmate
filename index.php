<?php
/**
 * ClassMate - Modern Landing Page
 * Class Representative Management System
 */

require_once __DIR__ . '/includes/config.php';

// Check if user is already logged in
if (Auth::isLoggedIn()) {
    if (Authorization::isAdmin()) {
        redirect(url('admin/dashboard.php'));
    } else {
        redirect(url('student/dashboard.php'));
    }
}

$landingStats = [
    'students' => 0,
    'classes' => 0,
    'assignments' => 0,
    'active_users' => 0,
    'today_classes' => 0,
    'due_soon' => 0,
    'fund_balance' => 0
];

try {
    $db = Database::getInstance();
    $landingStats['students'] = (int)$db->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn();
    $landingStats['classes'] = (int)$db->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    $landingStats['assignments'] = (int)$db->query("SELECT COUNT(*) FROM assignments WHERE status IN ('published', 'open')")->fetchColumn();
    $landingStats['active_users'] = $landingStats['students'] + (int)$db->query("SELECT COUNT(*) FROM admins WHERE status = 'active'")->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM classes WHERE class_date = ?");
    $stmt->execute([date('Y-m-d')]);
    $landingStats['today_classes'] = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM assignments WHERE status IN ('published', 'open') AND deadline > NOW() AND deadline <= DATE_ADD(NOW(), INTERVAL 3 DAY)");
    $stmt->execute();
    $landingStats['due_soon'] = (int)$stmt->fetchColumn();

    $fundTotals = $db->query("
        SELECT
            COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) AS total_income,
            COALESCE(SUM(CASE WHEN transaction_type = 'expenditure' THEN amount ELSE 0 END), 0) AS total_expenditure,
            COALESCE(SUM(CASE WHEN transaction_type = 'refund' THEN amount ELSE 0 END), 0) AS total_refunds
        FROM fund_transactions
        WHERE status = 'approved'
    ")->fetch();
    $landingStats['fund_balance'] = (float)$fundTotals['total_income']
        - (float)$fundTotals['total_expenditure']
        - (float)$fundTotals['total_refunds'];
} catch (Throwable $e) {
    error_log('Landing page stats unavailable: ' . $e->getMessage());
}

$pageTitle = 'Welcome to ClassMate';
$pageCss = ['landing.css'];
$pageJs = ['landing.js'];

include_once BASE_PATH . '/includes/header.php';
?>

<!-- ============================================
    MODERN LANDING PAGE
============================================ -->
<div class="landing-page">
    
    <!-- ===== HERO SECTION ===== -->
    <section class="hero-section">
        <div class="hero-container">
            <div class="hero-content">
                <div class="hero-badge">
                    <span class="badge-pulse">✨ v2.0</span>
                    <span class="badge-text">Class Representative Management System</span>
                </div>
                
                <h1 class="hero-title">
                    <span class="highlight">ClassMate</span>
                    <br>Manage Your Class
                    <span class="highlight">Effortlessly</span>
                </h1>
                
                <p class="hero-description">
                    The complete class management solution for the 
                    <strong>Department of Geography and Environment</strong>. 
                    Streamline classes, assignments, funds, and communication 
                    all in one place.
                </p>
                
                <div class="hero-stats">
                    <div class="hero-stat">
                        <span class="stat-number" data-count="<?php echo $landingStats['students']; ?>">0</span>
                        <span class="stat-label">Students</span>
                    </div>
                    <div class="hero-stat">
                        <span class="stat-number" data-count="<?php echo $landingStats['classes']; ?>">0</span>
                        <span class="stat-label">Classes</span>
                    </div>
                    <div class="hero-stat">
                        <span class="stat-number" data-count="<?php echo $landingStats['assignments']; ?>">0</span>
                        <span class="stat-label">Assignments</span>
                    </div>
                    <div class="hero-stat">
                        <span class="stat-number" data-count="<?php echo $landingStats['active_users']; ?>">0</span>
                        <span class="stat-label">Active Users</span>
                    </div>
                </div>
                
                <div class="hero-actions">
                    <a href="<?php echo url('auth/student-login.php'); ?>" class="btn btn-primary btn-hero">
                        <i class="fas fa-user-graduate"></i>
                        Student Login
                        <span class="btn-arrow"><i class="fas fa-arrow-right"></i></span>
                    </a>
                    <a href="<?php echo url('auth/register.php'); ?>" class="btn btn-success btn-hero">
                        <i class="fas fa-user-plus"></i>
                        Register Now
                    </a>
                    <a href="<?php echo url('auth/admin-login.php'); ?>" class="btn btn-outline-light btn-hero">
                        <i class="fas fa-user-shield"></i>
                        Admin
                    </a>
                </div>
            </div>
            
            <div class="hero-visual">
                <div class="hero-illustration">
                    <img src="<?php echo asset('images/landing-illustration.svg'); ?>" alt="ClassMate Illustration" loading="lazy">
                </div>
                
                <!-- Floating Feature Cards -->
                <div class="floating-card card-1">
                    <div class="floating-icon"><i class="fas fa-book-open"></i></div>
                    <div class="floating-info">
                        <span class="floating-title">Classes</span>
                        <span class="floating-value"><?php echo $landingStats['today_classes']; ?> Today</span>
                    </div>
                </div>
                <div class="floating-card card-2">
                    <div class="floating-icon"><i class="fas fa-tasks"></i></div>
                    <div class="floating-info">
                        <span class="floating-title">Assignments</span>
                        <span class="floating-value"><?php echo $landingStats['due_soon']; ?> Due Soon</span>
                    </div>
                </div>
                <div class="floating-card card-3">
                    <div class="floating-icon"><i class="fas fa-coins"></i></div>
                    <div class="floating-info">
                        <span class="floating-title">Class Fund</span>
                        <span class="floating-value">BDT <?php echo number_format($landingStats['fund_balance'], 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Wave Divider -->
        <div class="hero-wave">
            <svg viewBox="0 0 1440 120" xmlns="http://www.w3.org/2000/svg">
                <path d="M0,40 C360,120 720,0 1080,80 C1260,120 1380,80 1440,60 L1440,120 L0,120 Z" fill="#F8FAFC"/>
            </svg>
        </div>
    </section>
    
    <!-- ===== FEATURES SECTION ===== -->
    <section class="features-section" id="features">
        <div class="section-container">
            <div class="section-header">
                <span class="section-badge">Features</span>
                <h2 class="section-title">Everything You Need to <span class="highlight">Manage Your Class</span></h2>
                <p class="section-description">
                    ClassMate provides all the tools you need to efficiently manage your class 
                    and keep everyone on the same page.
                </p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon" style="background: #EEF2FF; color: #4F46E5;">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="feature-title">Student Management</h3>
                    <p class="feature-description">
                        Easily manage student registrations, approvals, and profiles. 
                        Track attendance and academic progress.
                    </p>
                    <div class="feature-tag">Admin</div>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon" style="background: #ECFDF5; color: #10B981;">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h3 class="feature-title">Class Management</h3>
                    <p class="feature-description">
                        Create, schedule, and manage classes with ease. 
                        Track topics, tasks, and class progress.
                    </p>
                    <div class="feature-tag">Admin</div>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon" style="background: #FEF3C7; color: #F59E0B;">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h3 class="feature-title">Assignment Tracking</h3>
                    <p class="feature-description">
                        Create assignments, set deadlines, and track submissions. 
                        Students can view and submit work.
                    </p>
                    <div class="feature-tag">Both</div>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon" style="background: #DBEAFE; color: #3B82F6;">
                        <i class="fas fa-coins"></i>
                    </div>
                    <h3 class="feature-title">Class Fund Management</h3>
                    <p class="feature-description">
                        Track income, expenditures, and refunds. 
                        Maintain financial transparency for the class.
                    </p>
                    <div class="feature-tag">Admin</div>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon" style="background: #FCE4EC; color: #EF4444;">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <h3 class="feature-title">Announcements</h3>
                    <p class="feature-description">
                        Keep everyone informed with class announcements. 
                        Targeted notifications for students.
                    </p>
                    <div class="feature-tag">Admin</div>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon" style="background: #F3E8FF; color: #8B5CF6;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="feature-title">Analytics Dashboard</h3>
                    <p class="feature-description">
                        Real-time analytics and insights. 
                        Monitor class performance and engagement.
                    </p>
                    <div class="feature-tag">Admin</div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- ===== HOW IT WORKS ===== -->
    <section class="how-it-works-section">
        <div class="section-container">
            <div class="section-header">
                <span class="section-badge">How It Works</span>
                <h2 class="section-title">Simple <span class="highlight">3-Step Process</span></h2>
                <p class="section-description">
                    Get started with ClassMate in just a few minutes
                </p>
            </div>
            
            <div class="steps-container">
                <div class="step-item">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3 class="step-title">Register</h3>
                        <p class="step-description">
                            Students register with their name and class roll. 
                            The admin approves the request.
                        </p>
                    </div>
                    <div class="step-connector"></div>
                </div>
                
                <div class="step-item">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3 class="step-title">Login & Explore</h3>
                        <p class="step-description">
                            Use your class roll and PIN to login. 
                            Explore classes, assignments, and more.
                        </p>
                    </div>
                    <div class="step-connector"></div>
                </div>
                
                <div class="step-item">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3 class="step-title">Manage & Collaborate</h3>
                        <p class="step-description">
                            Admins manage the class while students participate. 
                            Stay connected and organized.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- ===== TESTIMONIALS / STATS ===== -->
    <section class="stats-section">
        <div class="section-container">
            <div class="stats-grid-large">
                <div class="stats-item">
                    <div class="stats-number"><?php echo number_format($landingStats['students']); ?></div>
                    <div class="stats-label">Active Students</div>
                </div>
                <div class="stats-item">
                    <div class="stats-number"><?php echo number_format($landingStats['classes']); ?></div>
                    <div class="stats-label">Classes Recorded</div>
                </div>
                <div class="stats-item">
                    <div class="stats-number"><?php echo number_format($landingStats['assignments']); ?></div>
                    <div class="stats-label">Live Assignments</div>
                </div>
                <div class="stats-item">
                    <div class="stats-number">BDT <?php echo number_format($landingStats['fund_balance'], 0); ?></div>
                    <div class="stats-label">Current Class Fund</div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- ===== CTA SECTION ===== -->
    <section class="cta-section">
        <div class="cta-container">
            <div class="cta-content">
                <h2 class="cta-title">Ready to Get Started?</h2>
                <p class="cta-description">
                    Join hundreds of students and administrators using ClassMate 
                    to manage their classes efficiently.
                </p>
                <div class="cta-buttons">
                    <a href="<?php echo url('auth/register.php'); ?>" class="btn btn-primary btn-lg">
                        <i class="fas fa-user-plus"></i> Register Now
                    </a>
                    <a href="<?php echo url('auth/student-login.php'); ?>" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                </div>
            </div>
        </div>
    </section>
    
    <!-- ===== FOOTER ===== -->
    <footer class="landing-footer">
        <div class="footer-container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <div class="footer-logo">
                        <?php if (file_exists(BASE_PATH . '/assets/images/logo.svg')): ?>
                            <img src="<?php echo asset('images/logo.svg'); ?>" alt="ClassMate" class="footer-logo-img">
                        <?php else: ?>
                            <span class="brand-text">ClassMate</span>
                        <?php endif; ?>
                        <span>ClassMate</span>
                    </div>
                    <p class="footer-description">
                        Class Representative Management System<br>
                        Department of Geography and Environment
                    </p>
                    <div class="footer-social">
                        <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                
                <div class="footer-links">
                    <h4 class="footer-heading">Quick Links</h4>
                    <ul>
                        <li><a href="<?php echo url('auth/student-login.php'); ?>">Student Login</a></li>
                        <li><a href="<?php echo url('auth/admin-login.php'); ?>">Admin Login</a></li>
                        <li><a href="<?php echo url('auth/register.php'); ?>">Register</a></li>
                        <li><a href="<?php echo url('auth/registration-status.php'); ?>">Check Status</a></li>
                    </ul>
                </div>
                
                <div class="footer-links">
                    <h4 class="footer-heading">Resources</h4>
                    <ul>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#">Documentation</a></li>
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">Contact Support</a></li>
                    </ul>
                </div>
                
                <div class="footer-contact">
                    <h4 class="footer-heading">Contact</h4>
                    <ul>
                        <li><i class="fas fa-map-marker-alt"></i> Department of Geography</li>
                        <li><i class="fas fa-envelope"></i> support@classmate.edu</li>
                        <li><i class="fas fa-phone"></i> +880 1234 567890</li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> ClassMate. All rights reserved.</p>
                <div class="footer-bottom-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>