<?php
/**
 * Mobile Bottom Navigation
 */
?>
<nav class="mobile-nav">
    <div class="mobile-nav-container">
        <?php if (Auth::isLoggedIn()): ?>
            <?php if (Authorization::isAdmin()): ?>
                <a href="<?php echo url('admin/dashboard.php'); ?>" class="mobile-nav-item <?php echo isActivePage('admin/dashboard.php') ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo url('admin/students.php'); ?>" class="mobile-nav-item <?php echo isActivePage('admin/students.php') ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Students</span>
                </a>
                <a href="<?php echo url('admin/classes.php'); ?>" class="mobile-nav-item <?php echo isActivePage('admin/classes.php') ? 'active' : ''; ?>">
                    <i class="fas fa-book"></i>
                    <span>Classes</span>
                </a>
                <a href="<?php echo url('admin/assignments.php'); ?>" class="mobile-nav-item <?php echo isActivePage('admin/assignments.php') ? 'active' : ''; ?>">
                    <i class="fas fa-tasks"></i>
                    <span>Tasks</span>
                </a>
            <?php else: ?>
                <a href="<?php echo url('student/dashboard.php'); ?>" class="mobile-nav-item <?php echo isActivePage('student/dashboard.php') ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie"></i>
                    <span>Home</span>
                </a>
                <a href="<?php echo url('student/classes.php'); ?>" class="mobile-nav-item <?php echo isActivePage('student/classes.php') ? 'active' : ''; ?>">
                    <i class="fas fa-book"></i>
                    <span>Classes</span>
                </a>
                <a href="<?php echo url('student/assignments.php'); ?>" class="mobile-nav-item <?php echo isActivePage('student/assignments.php') ? 'active' : ''; ?>">
                    <i class="fas fa-tasks"></i>
                    <span>Tasks</span>
                </a>
                <a href="<?php echo url('student/class-fund.php'); ?>" class="mobile-nav-item <?php echo isActivePage('student/class-fund.php') ? 'active' : ''; ?>">
                    <i class="fas fa-coins"></i>
                    <span>Fund</span>
                </a>
            <?php endif; ?>
            
            <a href="<?php echo url('auth/logout.php'); ?>" class="mobile-nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        <?php else: ?>
            <a href="<?php echo url('auth/login.php'); ?>" class="mobile-nav-item <?php echo isActivePage('auth/login.php') ? 'active' : ''; ?>">
                <i class="fas fa-sign-in-alt"></i>
                <span>Login</span>
            </a>
            <a href="<?php echo url('auth/register.php'); ?>" class="mobile-nav-item <?php echo isActivePage('auth/register.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-plus"></i>
                <span>Register</span>
            </a>
        <?php endif; ?>
    </div>
</nav>