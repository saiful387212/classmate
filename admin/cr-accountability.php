<?php
/**
 * Admin - CR Accountability
 * Track and manage Class Representative performance
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Authorization::requireSuperAdmin();

$pageTitle = 'CR Accountability';
$pageCss = ['admin.css', 'cr-accountability.css'];
$pageJs = ['cr-accountability.js', 'charts.js'];
$isAdmin = true;

$db = Database::getInstance();

// ============================================
// GET CR PERFORMANCE DATA
// ============================================

// Get all admins with role 'admin' or 'sub_admin' (CRs)
$stmt = $db->prepare("
    SELECT 
        a.*,
        (SELECT COUNT(*) FROM classes WHERE created_by = a.id) as total_classes,
        (SELECT COUNT(*) FROM classes WHERE created_by = a.id AND status = 'completed') as completed_classes,
        (SELECT COUNT(*) FROM classes WHERE created_by = a.id AND status = 'scheduled') as scheduled_classes,
        (SELECT COUNT(*) FROM assignments WHERE created_by = a.id) as total_assignments,
        (SELECT COUNT(*) FROM assignments WHERE created_by = a.id AND status IN ('published', 'open')) as active_assignments,
        (SELECT COUNT(*) FROM announcements WHERE created_by = a.id) as total_announcements,
        (SELECT COUNT(*) FROM fund_transactions WHERE created_by = a.id) as total_transactions,
        (SELECT COUNT(*) FROM students WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND id IN (
            SELECT student_id FROM class_enrollments WHERE class_id IN (
                SELECT id FROM classes WHERE created_by = a.id
            )
        )) as new_students_30d,
        (SELECT COUNT(*) FROM activity_logs WHERE user_id = a.id AND user_type = 'admin' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as activities_30d,
        (SELECT COUNT(*) FROM activity_logs WHERE user_id = a.id AND user_type = 'admin' AND action = 'login' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as logins_30d
    FROM admins a
    WHERE a.role IN ('admin', 'sub_admin')
    AND a.status = 'active'
    ORDER BY a.full_name
");
$stmt->execute();
$crs = $stmt->fetchAll();

// Get overall statistics
$totalCRs = count($crs);
$totalCRClasses = 0;
$totalCRAssignments = 0;
$totalCRAnnouncements = 0;
$totalCRTransactions = 0;

foreach ($crs as $cr) {
    $totalCRClasses += $cr['total_classes'];
    $totalCRAssignments += $cr['total_assignments'];
    $totalCRAnnouncements += $cr['total_announcements'];
    $totalCRTransactions += $cr['total_transactions'];
}

// ============================================
// GET FILTERS
// ============================================
$search = $_GET['search'] ?? '';
$sortBy = $_GET['sort'] ?? 'total_classes';
$sortDir = $_GET['dir'] ?? 'DESC';

// Filter CRs
$filteredCRs = $crs;
if (!empty($search)) {
    $filteredCRs = array_filter($crs, function($cr) use ($search) {
        return stripos($cr['full_name'], $search) !== false || 
               stripos($cr['username'], $search) !== false ||
               stripos($cr['email'], $search) !== false;
    });
}

// Sort CRs
usort($filteredCRs, function($a, $b) use ($sortBy, $sortDir) {
    $valA = $a[$sortBy] ?? 0;
    $valB = $b[$sortBy] ?? 0;
    if ($sortDir === 'ASC') {
        return $valA <=> $valB;
    }
    return $valB <=> $valA;
});

// ============================================
// GET TOP PERFORMING CR
// ============================================
$topCR = !empty($filteredCRs) ? $filteredCRs[0] : null;

include_once BASE_PATH . '/includes/header.php';
include_once BASE_PATH . '/includes/sidebar-admin.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>CR Accountability</h1>
                <p class="text-muted">Track and monitor Class Representative performance</p>
            </div>
            <div class="page-actions">
                <button class="btn btn-outline" onclick="exportData()">
                    <i class="fas fa-download"></i> Export Report
                </button>
                <button class="btn btn-primary" onclick="refreshData()">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
        </div>
    </div>

    <!-- ===== Overview Stats ===== -->
    <div class="overview-stats">
        <div class="stat-item">
            <span class="stat-value"><?php echo $totalCRs; ?></span>
            <span class="stat-label">Active CRs</span>
        </div>
        <div class="stat-item">
            <span class="stat-value"><?php echo $totalCRClasses; ?></span>
            <span class="stat-label">Total Classes</span>
        </div>
        <div class="stat-item">
            <span class="stat-value"><?php echo $totalCRAssignments; ?></span>
            <span class="stat-label">Total Assignments</span>
        </div>
        <div class="stat-item">
            <span class="stat-value"><?php echo $totalCRAnnouncements; ?></span>
            <span class="stat-label">Announcements</span>
        </div>
        <div class="stat-item">
            <span class="stat-value">BDT <?php echo number_format($totalCRTransactions, 0); ?></span>
            <span class="stat-label">Funds Managed</span>
        </div>
    </div>

    <!-- ===== Top CR Spotlight ===== -->
    <?php if ($topCR): ?>
    <div class="top-cr-spotlight">
        <div class="spotlight-content">
            <div class="spotlight-badge">
                <i class="fas fa-crown"></i> Top Performing CR
            </div>
            <div class="spotlight-info">
                <div class="spotlight-avatar">
                    <?php echo strtoupper(substr($topCR['full_name'], 0, 2)); ?>
                </div>
                <div class="spotlight-details">
                    <h3><?php echo htmlspecialchars($topCR['full_name']); ?></h3>
                    <p>
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($topCR['email']); ?>
                        <span class="separator">|</span>
                        <i class="fas fa-user-shield"></i> <?php echo ucfirst($topCR['role']); ?>
                    </p>
                    <div class="spotlight-stats">
                        <span><i class="fas fa-book"></i> <?php echo $topCR['total_classes']; ?> Classes</span>
                        <span><i class="fas fa-tasks"></i> <?php echo $topCR['total_assignments']; ?> Assignments</span>
                        <span><i class="fas fa-bullhorn"></i> <?php echo $topCR['total_announcements']; ?> Announcements</span>
                        <span><i class="fas fa-coins"></i> <?php echo $topCR['total_transactions']; ?> Transactions</span>
                    </div>
                </div>
                <div class="spotlight-score">
                    <div class="score-circle">
                        <?php 
                        // Calculate performance score
                        $score = 0;
                        $score += min($topCR['total_classes'] * 2, 20);
                        $score += min($topCR['total_assignments'] * 3, 20);
                        $score += min($topCR['total_announcements'] * 2, 15);
                        $score += min($topCR['total_transactions'] * 1, 10);
                        $score += min($topCR['logins_30d'] * 1, 15);
                        $score += min($topCR['activities_30d'] * 0.5, 10);
                        $score = min(round($score), 100);
                        ?>
                        <span class="score-value"><?php echo $score; ?></span>
                        <span class="score-label">Performance</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== Filter Bar ===== -->
    <div class="filter-bar">
        <form method="GET" action="" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <input type="text" name="search" placeholder="Search CR by name, email..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <select name="sort" class="filter-auto-submit">
                        <option value="total_classes" <?php echo $sortBy === 'total_classes' ? 'selected' : ''; ?>>Sort by Classes</option>
                        <option value="total_assignments" <?php echo $sortBy === 'total_assignments' ? 'selected' : ''; ?>>Sort by Assignments</option>
                        <option value="total_announcements" <?php echo $sortBy === 'total_announcements' ? 'selected' : ''; ?>>Sort by Announcements</option>
                        <option value="total_transactions" <?php echo $sortBy === 'total_transactions' ? 'selected' : ''; ?>>Sort by Transactions</option>
                        <option value="logins_30d" <?php echo $sortBy === 'logins_30d' ? 'selected' : ''; ?>>Sort by Activity</option>
                    </select>
                </div>
                <div class="filter-group">
                    <select name="dir" class="filter-auto-submit">
                        <option value="DESC" <?php echo $sortDir === 'DESC' ? 'selected' : ''; ?>>Highest First</option>
                        <option value="ASC" <?php echo $sortDir === 'ASC' ? 'selected' : ''; ?>>Lowest First</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i> Apply
                    </button>
                    <a href="<?php echo url('admin/cr-accountability.php'); ?>" class="btn btn-outline btn-sm">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- ===== CR Performance Table ===== -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-users text-primary"></i> CR Performance Dashboard</h3>
            <span class="badge badge-info"><?php echo count($filteredCRs); ?> CRs</span>
        </div>
        <div class="card-body">
            <?php if (empty($filteredCRs)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No CRs Found</h3>
                    <p>No Class Representatives match your search criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="crTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>CR Name</th>
                                <th>Role</th>
                                <th>Classes</th>
                                <th>Assignments</th>
                                <th>Announcements</th>
                                <th>Transactions</th>
                                <th>Activity</th>
                                <th>Score</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filteredCRs as $index => $cr): 
                                // Calculate performance score
                                $score = 0;
                                $score += min($cr['total_classes'] * 2, 20);
                                $score += min($cr['total_assignments'] * 3, 20);
                                $score += min($cr['total_announcements'] * 2, 15);
                                $score += min($cr['total_transactions'] * 1, 10);
                                $score += min($cr['logins_30d'] * 1, 15);
                                $score += min($cr['activities_30d'] * 0.5, 10);
                                $score = min(round($score), 100);
                                
                                $scoreColor = $score >= 80 ? 'success' : ($score >= 60 ? 'warning' : 'danger');
                            ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <div class="cr-info">
                                            <div class="cr-avatar">
                                                <?php echo strtoupper(substr($cr['full_name'], 0, 2)); ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($cr['full_name']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($cr['email']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $cr['role'] === 'super_admin' ? 'danger' : 'primary'; ?>">
                                            <?php echo ucfirst($cr['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-info"><?php echo $cr['total_classes']; ?></span>
                                        <br><small class="text-muted"><?php echo $cr['completed_classes']; ?> completed</small>
                                    </td>
                                    <td>
                                        <span class="badge badge-warning"><?php echo $cr['total_assignments']; ?></span>
                                        <br><small class="text-muted"><?php echo $cr['active_assignments']; ?> active</small>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary"><?php echo $cr['total_announcements']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-success"><?php echo $cr['total_transactions']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary"><?php echo $cr['logins_30d']; ?> logins</span>
                                        <br><small class="text-muted"><?php echo $cr['activities_30d']; ?> actions</small>
                                    </td>
                                    <td>
                                        <div class="score-indicator">
                                            <span class="score-value badge badge-<?php echo $scoreColor; ?>">
                                                <?php echo $score; ?>%
                                            </span>
                                            <div class="score-bar">
                                                <div class="score-bar-fill" style="width: <?php echo $score; ?>%; background: var(--<?php echo $scoreColor; ?>);"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="<?php echo url('admin/cr-details.php?id=' . $cr['id']); ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button class="btn btn-sm btn-primary" onclick="viewReport(<?php echo $cr['id']; ?>)">
                                                <i class="fas fa-file-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== CR Performance Chart ===== -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-bar text-primary"></i> CR Performance Comparison</h3>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="crPerformanceChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ===== Report Modal ===== -->
<div class="modal-overlay" id="reportModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>CR Performance Report</h3>
            <button class="modal-close" onclick="closeModal('reportModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="reportContent">
            <div class="loading-spinner"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('reportModal')">Close</button>
            <button class="btn btn-primary" onclick="printReport()">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>
    </div>
</div>

<script>
// ===== Chart Data =====
const chartData = {
    labels: <?php echo json_encode(array_column($filteredCRs, 'full_name')); ?>,
    classes: <?php echo json_encode(array_column($filteredCRs, 'total_classes')); ?>,
    assignments: <?php echo json_encode(array_column($filteredCRs, 'total_assignments')); ?>,
    announcements: <?php echo json_encode(array_column($filteredCRs, 'total_announcements')); ?>,
    transactions: <?php echo json_encode(array_column($filteredCRs, 'total_transactions')); ?>
};

// ===== Initialize Chart =====
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('crPerformanceChart');
    if (ctx && typeof Chart !== 'undefined') {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: 'Classes',
                        data: chartData.classes,
                        backgroundColor: 'rgba(79, 70, 229, 0.7)',
                        borderColor: '#4F46E5',
                        borderWidth: 2
                    },
                    {
                        label: 'Assignments',
                        data: chartData.assignments,
                        backgroundColor: 'rgba(245, 158, 11, 0.7)',
                        borderColor: '#F59E0B',
                        borderWidth: 2
                    },
                    {
                        label: 'Announcements',
                        data: chartData.announcements,
                        backgroundColor: 'rgba(16, 185, 129, 0.7)',
                        borderColor: '#10B981',
                        borderWidth: 2
                    },
                    {
                        label: 'Transactions',
                        data: chartData.transactions,
                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                        borderColor: '#3B82F6',
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
});

// ===== View Report =====
function viewReport(id) {
    const modal = document.getElementById('reportModal');
    const content = document.getElementById('reportContent');
    
    content.innerHTML = '<div class="loading-spinner"></div>';
    openModal('reportModal');
    
    fetch('<?php echo url('api/cr-api.php'); ?>?action=get_report&id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const cr = data.cr;
                content.innerHTML = `
                    <div class="report-header">
                        <h2>${cr.full_name}</h2>
                        <p>${cr.email} | ${cr.role}</p>
                    </div>
                    <div class="report-stats">
                        <div class="stat-item">
                            <span class="stat-value">${cr.total_classes}</span>
                            <span class="stat-label">Total Classes</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">${cr.completed_classes}</span>
                            <span class="stat-label">Completed</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">${cr.total_assignments}</span>
                            <span class="stat-label">Assignments</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">${cr.total_announcements}</span>
                            <span class="stat-label">Announcements</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">${cr.total_transactions}</span>
                            <span class="stat-label">Transactions</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">${cr.logins_30d}</span>
                            <span class="stat-label">Logins (30d)</span>
                        </div>
                    </div>
                    <div class="report-activity">
                        <h4>Recent Activity</h4>
                        ${cr.recent_activities ? cr.recent_activities.map(act => `
                            <div class="activity-item">
                                <span class="activity-time">${act.created_at}</span>
                                <span class="activity-action">${act.action}</span>
                                <span class="activity-description">${act.description}</span>
                            </div>
                        `).join('') : '<p>No recent activity</p>'}
                    </div>
                `;
            } else {
                content.innerHTML = '<div class="alert alert-error">Failed to load report.</div>';
            }
        })
        .catch(() => {
            content.innerHTML = '<div class="alert alert-error">An error occurred.</div>';
        });
}

// ===== Print Report =====
function printReport() {
    window.print();
}

// ===== Export Data =====
function exportData() {
    window.open('<?php echo url('api/cr-api.php?action=export'); ?>', '_blank');
}

// ===== Refresh Data =====
function refreshData() {
    location.reload();
}
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>