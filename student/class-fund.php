<?php
/**
 * Student - Class Fund View
 * View fund events and transactions
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure student access
Auth::requireStudent();

$pageTitle = 'Class Fund';
$pageCss = ['student.css', 'fund-events.css'];
$pageJs = ['student-fund.js', 'charts.js'];
$isStudent = true;

$db = Database::getInstance();
$studentId = Session::get('user_id');

// Get student's enrolled classes
$stmt = $db->prepare("
    SELECT class_id FROM class_enrollments 
    WHERE student_id = ? AND status = 'active'
");
$stmt->execute([$studentId]);
$classIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

$batchId = (int)(Authorization::getStudentBatchId() ?? 0);
$stmt = $db->prepare("SELECT id FROM classes WHERE batch_id = ?");
$stmt->execute([$batchId]);
$batchClassIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
$classIds = array_values(array_intersect($classIds, $batchClassIds));

// If no classes, use 0 to prevent SQL error
$classIds = $classIds ?: [0];

// ============================================
// GET FUND EVENTS FOR STUDENT'S CLASSES
// ============================================
$filters = [
    'status' => $_GET['status'] ?? '',
    'class_id' => $_GET['class_id'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$sql = "
    SELECT 
        e.*,
        c.class_title,
        c.class_code,
        c.instructor,
        (SELECT COUNT(*) FROM fund_transactions WHERE class_id = e.class_id) as transaction_count,
        (SELECT COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) 
         FROM fund_transactions WHERE class_id = e.class_id AND status = 'approved') as total_income,
        (SELECT COALESCE(SUM(CASE WHEN transaction_type = 'expenditure' THEN amount ELSE 0 END), 0) 
         FROM fund_transactions WHERE class_id = e.class_id AND status = 'approved') as total_expenditure,
        (SELECT COALESCE(SUM(CASE WHEN transaction_type = 'refund' THEN amount ELSE 0 END), 0) 
         FROM fund_transactions WHERE class_id = e.class_id AND status = 'approved') as total_refunds,
        (SELECT COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) - 
                COALESCE(SUM(CASE WHEN transaction_type = 'expenditure' THEN amount ELSE 0 END), 0) - 
                COALESCE(SUM(CASE WHEN transaction_type = 'refund' THEN amount ELSE 0 END), 0) 
         FROM fund_transactions WHERE class_id = e.class_id AND status = 'approved') as current_balance
    FROM fund_events e
    JOIN classes c ON e.class_id = c.id
    WHERE e.class_id IN (" . implode(',', $classIds) . ")
    AND e.status != 'archived'
";
$params = [];

if (!empty($filters['status'])) {
    $sql .= " AND e.status = ?";
    $params[] = $filters['status'];
}

if (!empty($filters['class_id'])) {
    $sql .= " AND e.class_id = ?";
    $params[] = $filters['class_id'];
}

if (!empty($filters['search'])) {
    $sql .= " AND (e.event_name LIKE ? OR e.event_description LIKE ?)";
    $search = '%' . $filters['search'] . '%';
    $params[] = $search;
    $params[] = $search;
}

$sql .= " ORDER BY e.event_date DESC, e.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

// ============================================
// GET CLASSES FOR FILTER
// ============================================
$stmt = $db->prepare("
    SELECT DISTINCT c.id, c.class_title, c.class_code 
    FROM classes c
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ? AND ce.status = 'active' AND c.batch_id = ?
    ORDER BY c.class_title
");
$stmt->execute([$studentId, $batchId]);
$classes = $stmt->fetchAll();

// ============================================
// CALCULATE TOTALS
// ============================================
$totalIncome = 0;
$totalExpenditure = 0;
$totalRefunds = 0;
$totalBalance = 0;

foreach ($events as $event) {
    $totalIncome += (float)$event['total_income'];
    $totalExpenditure += (float)$event['total_expenditure'];
    $totalRefunds += (float)$event['total_refunds'];
    $totalBalance += (float)$event['current_balance'];
}

include_once BASE_PATH . '/includes/header.php';
?>

<div class="dashboard-container">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>Class Fund</h1>
                <p class="text-muted">View financial information for your classes</p>
            </div>
        </div>
    </div>
    
    <!-- ===== Fund Summary Stats ===== -->
    <div class="fund-stats-grid">
        <div class="stat-card income" onclick="filterByType('income')" style="cursor:pointer;">
            <div class="stat-icon"><i class="fas fa-arrow-down"></i></div>
            <div class="stat-info">
                <h3>BDT <?php echo number_format($totalIncome, 2); ?></h3>
                <p>Total Income</p>
                <small class="text-muted">Click to view income</small>
            </div>
        </div>
        <div class="stat-card expenditure" onclick="filterByType('expenditure')" style="cursor:pointer;">
            <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
            <div class="stat-info">
                <h3>BDT <?php echo number_format($totalExpenditure, 2); ?></h3>
                <p>Total Expenditure</p>
                <small class="text-muted">Click to view expenditure</small>
            </div>
        </div>
        <div class="stat-card refund" onclick="filterByType('refund')" style="cursor:pointer;">
            <div class="stat-icon"><i class="fas fa-undo"></i></div>
            <div class="stat-info">
                <h3>BDT <?php echo number_format($totalRefunds, 2); ?></h3>
                <p>Total Refunds</p>
                <small class="text-muted">Click to view refunds</small>
            </div>
        </div>
        <div class="stat-card balance" onclick="filterByType('')" style="cursor:pointer;">
            <div class="stat-icon"><i class="fas fa-wallet"></i></div>
            <div class="stat-info">
                <h3>BDT <?php echo number_format($totalBalance, 2); ?></h3>
                <p>Current Balance</p>
                <small class="text-muted">Click to reset filter</small>
            </div>
        </div>
    </div>
    
    <!-- ===== Filter Bar ===== -->
    <div class="filter-bar">
        <form method="GET" action="" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <input type="text" name="search" placeholder="Search events..." 
                           value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
                <div class="filter-group">
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="closed" <?php echo $filters['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>
                <div class="filter-group">
                    <select name="class_id">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" 
                                    <?php echo $filters['class_id'] == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_code'] . ' - ' . $class['class_title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="<?php echo url('student/class-fund.php'); ?>" class="btn btn-outline btn-sm">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- ===== Events Grid ===== -->
    <?php if (empty($events)): ?>
        <div class="empty-state">
            <i class="fas fa-coins"></i>
            <h3>No Fund Events</h3>
            <p>There are no fund events available for your enrolled classes.</p>
        </div>
    <?php else: ?>
        <div class="events-grid">
            <?php foreach ($events as $event): ?>
                <div class="event-card <?php echo $event['status']; ?>">
                    <div class="event-card-header">
                        <div class="event-title">
                            <h3><?php echo htmlspecialchars($event['event_name']); ?></h3>
                            <span class="event-class"><?php echo htmlspecialchars($event['class_code']); ?></span>
                        </div>
                        <span class="badge badge-<?php echo $event['status']; ?>">
                            <?php echo ucfirst($event['status']); ?>
                        </span>
                    </div>
                    
                    <div class="event-card-body">
                        <?php if ($event['event_description']): ?>
                            <p class="event-description"><?php echo htmlspecialchars($event['event_description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="event-stats">
                            <div class="stat-item income">
                                <span class="stat-value">BDT <?php echo number_format($event['total_income'], 2); ?></span>
                                <span class="stat-label">Income</span>
                            </div>
                            <div class="stat-item expenditure">
                                <span class="stat-value">BDT <?php echo number_format($event['total_expenditure'], 2); ?></span>
                                <span class="stat-label">Expenditure</span>
                            </div>
                            <div class="stat-item refund">
                                <span class="stat-value">BDT <?php echo number_format($event['total_refunds'], 2); ?></span>
                                <span class="stat-label">Refunds</span>
                            </div>
                            <div class="stat-item balance">
                                <span class="stat-value">BDT <?php echo number_format($event['current_balance'] ?? 0, 2); ?></span>
                                <span class="stat-label">Balance</span>
                            </div>
                        </div>
                        
                        <div class="event-meta">
                            <span><i class="fas fa-calendar-alt"></i> <?php echo formatDate($event['event_date']); ?></span>
                            <span><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($event['instructor']); ?></span>
                            <span><i class="fas fa-file-invoice"></i> <?php echo $event['transaction_count']; ?> transactions</span>
                        </div>
                    </div>
                    
                    <div class="event-card-footer">
                        <button class="btn btn-info btn-sm" onclick="viewEventDetails(<?php echo $event['id']; ?>)">
                            <i class="fas fa-eye"></i> View Details
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ===== Event Details Modal ===== -->
<div class="modal-overlay" id="eventModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 id="modalTitle">Event Details</h3>
            <button class="modal-close" onclick="closeModal('eventModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="modalContent">
            <div class="loading-spinner"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('eventModal')">Close</button>
        </div>
    </div>
</div>

<script>
// ===== Filter by Type =====
function filterByType(type) {
    const url = new URL(window.location.href);
    if (type) {
        url.searchParams.set('type', type);
    } else {
        url.searchParams.delete('type');
    }
    window.location.href = url.toString();
}

// ===== View Event Details =====
function viewEventDetails(eventId) {
    const modal = document.getElementById('eventModal');
    const content = document.getElementById('modalContent');
    const title = document.getElementById('modalTitle');
    
    content.innerHTML = '<div class="loading-spinner"></div>';
    openModal('eventModal');
    
    fetch('<?php echo url('api/fund-api.php'); ?>?action=get_event_details&id=' + eventId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const event = data.event;
                const transactions = data.transactions;
                
                title.textContent = event.event_name + ' - ' + event.class_code;
                
                let html = `
                    <div class="event-details">
                        <div class="event-summary">
                            <div class="summary-item">
                                <span class="label">Total Income</span>
                                <span class="value income">BDT ${Number(event.total_income).toFixed(2)}</span>
                            </div>
                            <div class="summary-item">
                                <span class="label">Total Expenditure</span>
                                <span class="value expenditure">BDT ${Number(event.total_expenditure).toFixed(2)}</span>
                            </div>
                            <div class="summary-item">
                                <span class="label">Total Refunds</span>
                                <span class="value refund">BDT ${Number(event.total_refunds).toFixed(2)}</span>
                            </div>
                            <div class="summary-item">
                                <span class="label">Balance</span>
                                <span class="value balance">BDT ${Number(event.current_balance).toFixed(2)}</span>
                            </div>
                        </div>
                        
                        ${event.event_description ? `<p class="event-description">${event.event_description}</p>` : ''}
                        
                        <h4>Transactions</h4>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Category</th>
                                        <th>Amount</th>
                                        <th>Description</th>
                                        <th>Date</th>
                                        <th>Student</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;
                
                if (transactions.length === 0) {
                    html += `<tr><td colspan="6" class="text-center">No transactions found</td></tr>`;
                } else {
                    transactions.forEach(t => {
                        const typeClass = t.transaction_type;
                        const typeIcon = t.transaction_type === 'income' ? 'arrow-down' : 
                                        (t.transaction_type === 'expenditure' ? 'arrow-up' : 'undo');
                        html += `
                            <tr class="transaction-${typeClass}">
                                <td>
                                    <span class="badge badge-${typeClass}">
                                        <i class="fas fa-${typeIcon}"></i>
                                        ${t.transaction_type.charAt(0).toUpperCase() + t.transaction_type.slice(1)}
                                    </span>
                                </td>
                                <td>${t.category || 'N/A'}</td>
                                <td class="amount-${typeClass}">BDT ${Number(t.amount).toFixed(2)}</td>
                                <td>${t.description}</td>
                                <td>${t.transaction_date}</td>
                                <td>${t.student_name || 'N/A'}</td>
                            </tr>
                        `;
                    });
                }
                
                html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
                
                content.innerHTML = html;
            } else {
                content.innerHTML = '<div class="alert alert-error">Failed to load event details.</div>';
            }
        })
        .catch(() => {
            content.innerHTML = '<div class="alert alert-error">An error occurred.</div>';
        });
}
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>