<?php
/**
 * Admin Sidebar Navigation
 */
?>
<aside class="sidebar sidebar-admin">
    <div class="sidebar-header">
        <a href="<?php echo url('admin/dashboard.php'); ?>" class="sidebar-brand">
            <?php if (file_exists(BASE_PATH . '/assets/images/logo.svg')): ?>
                <img src="<?php echo asset('images/logo.svg'); ?>" alt="ClassMate" class="brand-icon">
            <?php endif; ?>
            <span class="brand-text">ClassMate</span>
        </a>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav-list">
            <li class="nav-item <?php echo isActivePage('admin/dashboard.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('admin/dashboard.php'); ?>" class="nav-link">
                    <i class="fas fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <li class="nav-section">Management</li>
            
            <li class="nav-item <?php echo isActivePage('admin/students.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('admin/students.php'); ?>" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span>Students</span>
                    <?php 
                    // Get pending registration count
                    $db = Database::getInstance();
                    [$pendingBatchSql, $pendingBatchParams] = Authorization::adminBatchFilter();
                    $stmt = $db->prepare("SELECT COUNT(*) FROM registration_requests WHERE status = 'pending'" . $pendingBatchSql);
                    $stmt->execute($pendingBatchParams);
                    $pending = $stmt->fetchColumn();
                    if ($pending > 0): ?>
                        <span class="badge badge-warning"><?php echo $pending; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="nav-item <?php echo isActivePage('admin/registrations.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('admin/registrations.php'); ?>" class="nav-link">
                    <i class="fas fa-user-plus"></i>
                    <span>Registration Requests</span>
                    <?php if ($pending > 0): ?>
                        <span class="badge badge-danger"><?php echo $pending; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="nav-item <?php echo isActivePage('admin/classes.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('admin/classes.php'); ?>" class="nav-link">
                    <i class="fas fa-book"></i>
                    <span>Classes</span>
                </a>
            </li>

            <li class="nav-item <?php echo isActivePage('admin/routine.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('admin/routine.php'); ?>" class="nav-link">
                    <i class="fas fa-calendar-week"></i>
                    <span>Weekly Routine</span>
                </a>
            </li>
            
            <li class="nav-item <?php echo isActivePage('admin/assignments.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('admin/assignments.php'); ?>" class="nav-link">
                    <i class="fas fa-tasks"></i>
                    <span>Assignments</span>
                </a>
            </li>
            <li class="nav-item <?php echo isActivePage('admin/resources.php') ? 'active' : ''; ?>">
    <a href="<?php echo url('admin/resources.php'); ?>" class="nav-link">
        <i class="fas fa-folder-open"></i>
        <span>Resources</span>
    </a>
</li>
            
            <li class="nav-item <?php echo isActivePage('admin/fund-events.php') ? 'active' : ''; ?>">
                <a href="<?php echo htmlspecialchars(url('admin/fund-events.php'), ENT_QUOTES, 'UTF-8'); ?>"
                   class="nav-link" title="Open Fund Events" aria-label="Open Fund Events">
                    <i class="fas fa-coins"></i>
                    <span>Fund Events</span>
                </a>
            </li>
            
            <li class="nav-section">Content</li>
            
            <li class="nav-item <?php echo isActivePage('admin/announcements.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('admin/announcements.php'); ?>" class="nav-link">
                    <i class="fas fa-bullhorn"></i>
                    <span>Announcements</span>
                </a>
            </li>
            
            <li class="nav-item <?php echo isActivePage('admin/polls.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('admin/polls.php'); ?>" class="nav-link">
                    <i class="fas fa-poll"></i>
                    <span>Polls</span>
                </a>
            </li>
            
            <li class="nav-section">Reports</li>
            
            <?php if (Authorization::isSuperAdmin()): ?>
                <li class="nav-item <?php echo isActivePage('admin/cr-accountability.php') ? 'active' : ''; ?>">
                    <a href="<?php echo url('admin/cr-accountability.php'); ?>" class="nav-link">
                        <i class="fas fa-user-check"></i>
                        <span>CR Accountability</span>
                    </a>
                </li>
            <?php endif; ?>
            
            <li class="nav-item <?php echo isActivePage('admin/financial-reports.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('admin/financial-reports.php'); ?>" class="nav-link">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Financial Reports</span>
                </a>
            </li>
            
            <?php if (Authorization::isSuperAdmin()): ?>
                <li class="nav-item <?php echo isActivePage('admin/activity-logs.php') ? 'active' : ''; ?>">
                    <a href="<?php echo url('admin/activity-logs.php'); ?>" class="nav-link">
                        <i class="fas fa-history"></i>
                        <span>Activity Logs</span>
                    </a>
                </li>
            <?php endif; ?>
            
            <li class="nav-section">System</li>

            <?php if (Authorization::isSuperAdmin()): ?>
                <li class="nav-item <?php echo isActivePage('admin/batches.php') ? 'active' : ''; ?>">
                    <a href="<?php echo url('admin/batches.php'); ?>" class="nav-link">
                        <i class="fas fa-layer-group"></i>
                        <span>Batches & Admins</span>
                    </a>
                </li>
            <?php endif; ?>
            
            <?php if (Authorization::isSuperAdmin()): ?>
                <li class="nav-item <?php echo isActivePage('admin/settings.php') ? 'active' : ''; ?>">
                    <a href="<?php echo url('admin/settings.php'); ?>" class="nav-link">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </li>
            <?php endif; ?>

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
                <span><?php echo strtoupper(substr($currentUser['full_name'] ?? 'A', 0, 1)); ?></span>
            </div>
            <div class="user-details">
                <span class="user-name"><?php echo $currentUser['full_name'] ?? 'Admin'; ?></span>
                <span class="user-role">Administrator</span>
            </div>
        </div>
    </div>
</aside>