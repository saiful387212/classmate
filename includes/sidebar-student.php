<?php
/**
 * Student Sidebar Navigation
 */
?>
<aside class="sidebar sidebar-student">
    <div class="sidebar-header">
        <a href="<?php echo url('student/dashboard.php'); ?>" class="sidebar-brand">
            <?php if (file_exists(BASE_PATH . '/assets/images/logo.svg')): ?>
                <img src="<?php echo asset('images/logo.svg'); ?>" alt="ClassMate" class="brand-icon">
            <?php endif; ?>
            <span class="brand-text">ClassMate</span>
        </a>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav-list">
            <li class="nav-item <?php echo isActivePage('student/dashboard.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('student/dashboard.php'); ?>" class="nav-link">
                    <i class="fas fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <li class="nav-item <?php echo isActivePage('student/classes.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('student/classes.php'); ?>" class="nav-link">
                    <i class="fas fa-book"></i>
                    <span>My Classes</span>
                </a>
            </li>
            
            <li class="nav-item <?php echo isActivePage('student/assignments.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('student/assignments.php'); ?>" class="nav-link">
                    <i class="fas fa-tasks"></i>
                    <span>Assignments</span>
                </a>
            </li>
        <li class="nav-item">
    <a href="<?php echo url('resources_course.php'); ?>" class="nav-link">
        <i class="fas fa-folder-open"></i>
        <span>Resources</span>
    </a>
</li>
            
            <li class="nav-item <?php echo isActivePage('student/polls.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('student/polls.php'); ?>" class="nav-link">
                    <i class="fas fa-poll"></i>
                    <span>Polls</span>
                </a>
            </li>
            
            <li class="nav-item <?php echo isActivePage('student/class-fund.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('student/class-fund.php'); ?>" class="nav-link">
                    <i class="fas fa-coins"></i>
                    <span>Class Fund</span>
                </a>
            </li>
            
            <li class="nav-item <?php echo isActivePage('student/announcements.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('student/announcements.php'); ?>" class="nav-link">
                    <i class="fas fa-bullhorn"></i>
                    <span>Announcements</span>
                </a>
            </li>
            
            <li class="nav-section">Account</li>
            
            <li class="nav-item <?php echo isActivePage('student/profile.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('student/profile.php'); ?>" class="nav-link">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
            </li>
            
            <li class="nav-item <?php echo isActivePage('student/change-pin.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('student/change-pin.php'); ?>" class="nav-link">
                    <i class="fas fa-key"></i>
                    <span>Change PIN</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="#" class="nav-link" onclick="installApp(); return false;">
                    <i class="fas fa-download"></i>
                    <span>Download App</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="<?php echo url('auth/logout.php'); ?>" class="nav-link text-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <span><?php echo strtoupper(substr($currentUser['full_name'] ?? 'S', 0, 1)); ?></span>
            </div>
            <div class="user-details">
                <span class="user-name"><?php echo $currentUser['full_name'] ?? 'Student'; ?></span>
                <span class="user-role">Roll: <?php echo $currentUser['class_roll'] ?? 'N/A'; ?></span>
            </div>
        </div>
    </div>
</aside>