<?php
/**
 * Admin - Fund Event Details
 * Complete dashboard with transaction management
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

Auth::requireAdmin();

$pageTitle = 'Fund Event Dashboard';
$pageCss = ['admin.css', 'fund-events.css'];
$pageJs = ['fund-events.js', 'charts.js'];
$isAdmin = true;

$id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    Session::setFlash('error', 'Invalid event ID');
    redirect(url('admin/fund-events.php'));
}

$db = Database::getInstance();
[$batchSql, $batchParams] = Authorization::adminBatchFilter();

// Handle transaction actions without reusing the event ID parameter.
$action = $_GET['action'] ?? '';
$transactionId = filter_var($_GET['transaction_id'] ?? 0, FILTER_VALIDATE_INT);
if ($action === 'delete' && $transactionId !== false && $transactionId > 0) {
    if (!Security::verifyCSRFToken($_GET['csrf_token'] ?? '')) {
        Session::setFlash('error', 'Invalid security token');
    } else {
        $transactionStmt = $db->prepare("SELECT class_id FROM fund_transactions WHERE id = ?");
        $transactionStmt->execute([$transactionId]);
        $transaction = $transactionStmt->fetch();

        $eventStmt = $db->prepare("SELECT class_id FROM fund_events WHERE id = ?" . $batchSql);
        $eventStmt->execute(array_merge([$id], $batchParams));
        $eventClassId = $eventStmt->fetchColumn();

        if (!$transaction || !$eventClassId || (int)$transaction['class_id'] !== (int)$eventClassId) {
            $result = ['success' => false, 'message' => 'Transaction not found for this event'];
        } else {
            $result = (new FundManager())->deleteTransaction($transactionId);
        }
        Session::setFlash($result['success'] ? 'success' : 'error', $result['message']);
    }
    redirect(url('admin/fund-event-details.php?id=' . (int)$id));
}

// Get event details
$stmt = $db->prepare("
    SELECT 
        e.*,
        c.class_title,
        c.class_code,
        a.full_name as created_by_name,
        (SELECT COUNT(*) FROM fund_transactions WHERE class_id = e.class_id) as transaction_count,
        (SELECT COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) FROM fund_transactions WHERE class_id = e.class_id AND status = 'approved') as total_income,
        (SELECT COALESCE(SUM(CASE WHEN transaction_type = 'expenditure' THEN amount ELSE 0 END), 0) FROM fund_transactions WHERE class_id = e.class_id AND status = 'approved') as total_expenditure,
        (SELECT COALESCE(SUM(CASE WHEN transaction_type = 'refund' THEN amount ELSE 0 END), 0) FROM fund_transactions WHERE class_id = e.class_id AND status = 'approved') as total_refunds
    FROM fund_events e
    LEFT JOIN classes c ON e.class_id = c.id
    LEFT JOIN admins a ON e.created_by = a.id
    WHERE e.id = ?" . $batchSql
);
$stmt->execute(array_merge([$id], $batchParams));
$event = $stmt->fetch();

if (!$event) {
    Session::setFlash('error', 'Event not found');
    redirect(url('admin/fund-events.php'));
}

// Get transactions with filters
$filters = [
    'type' => $_GET['type'] ?? '',
    'category' => $_GET['category'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$sql = "
    SELECT 
        t.*,
        CASE WHEN t.transaction_type IN ('income', 'refund') THEN t.payer_receiver ELSE NULL END AS student_name,
        a.full_name as created_by_name
    FROM fund_transactions t
    LEFT JOIN admins a ON t.created_by = a.id
    WHERE t.class_id = ?
";
$params = [$event['class_id']];

if (!empty($filters['type'])) {
    $sql .= " AND t.transaction_type = ?";
    $params[] = $filters['type'];
}

if (!empty($filters['category'])) {
    $sql .= " AND t.category = ?";
    $params[] = $filters['category'];
}

if (!empty($filters['date_from'])) {
    $sql .= " AND t.transaction_date >= ?";
    $params[] = $filters['date_from'];
}

if (!empty($filters['date_to'])) {
    $sql .= " AND t.transaction_date <= ?";
    $params[] = $filters['date_to'];
}

if (!empty($filters['search'])) {
    $sql .= " AND (t.description LIKE ? OR t.reference_number LIKE ? OR t.payer_receiver LIKE ?)";
    $search = '%' . $filters['search'] . '%';
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

$sql .= " ORDER BY t.transaction_date DESC, t.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Get categories for filter
$stmt = $db->prepare("SELECT DISTINCT category FROM fund_transactions WHERE class_id = ? AND category IS NOT NULL");
$stmt->execute([$event['class_id']]);
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get monthly data for chart
$stmt = $db->prepare("
    SELECT 
        DATE_FORMAT(transaction_date, '%Y-%m') as month,
        COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) as income,
        COALESCE(SUM(CASE WHEN transaction_type = 'expenditure' THEN amount ELSE 0 END), 0) as expenditure,
        COALESCE(SUM(CASE WHEN transaction_type = 'refund' THEN amount ELSE 0 END), 0) as refunds
    FROM fund_transactions
    WHERE class_id = ? AND status = 'approved'
    GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
    ORDER BY month ASC
");
$stmt->execute([$event['class_id']]);
$monthlyData = $stmt->fetchAll();

// Calculate current balance
$currentBalance = $event['total_income'] - $event['total_expenditure'] - $event['total_refunds'];

include_once BASE_PATH . '/includes/header.php';
include_once BASE_PATH . '/includes/sidebar-admin.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1><?php echo htmlspecialchars($event['event_name']); ?></h1>
                <p class="text-muted">
                    <?php echo htmlspecialchars($event['class_code'] . ' - ' . $event['class_title']); ?>
                    | Status: <span class="badge badge-<?php echo $event['status']; ?>"><?php echo ucfirst($event['status']); ?></span>
                </p>
            </div>
            <div class="page-actions">
                <?php if ($event['status'] === 'active'): ?>
                    <a href="<?php echo url('admin/add-fund-transaction.php?event_id=' . $id); ?>" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add Transaction
                    </a>
                <?php endif; ?>
                <a href="<?php echo url('admin/fund-reports.php?event_id=' . $id); ?>" class="btn btn-secondary">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </a>
                <a href="<?php echo url('admin/fund-events.php'); ?>" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
    
    <!-- Dashboard Stats -->
    <div class="fund-dashboard-stats">
        <div class="stat-card income" onclick="filterByType('income')" style="cursor:pointer;">
            <div class="stat-icon"><i class="fas fa-arrow-down"></i></div>
            <div class="stat-info">
                <h3>BDT <?php echo number_format($event['total_income'], 2); ?></h3>
                <p>Total Income</p>
                <small class="text-muted">Click to filter</small>
            </div>
        </div>
        <div class="stat-card expenditure" onclick="filterByType('expenditure')" style="cursor:pointer;">
            <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
            <div class="stat-info">
                <h3>BDT <?php echo number_format($event['total_expenditure'], 2); ?></h3>
                <p>Total Expenditure</p>
                <small class="text-muted">Click to filter</small>
            </div>
        </div>
        <div class="stat-card refund" onclick="filterByType('refund')" style="cursor:pointer;">
            <div class="stat-icon"><i class="fas fa-undo"></i></div>
            <div class="stat-info">
                <h3>BDT <?php echo number_format($event['total_refunds'], 2); ?></h3>
                <p>Total Refunds</p>
                <small class="text-muted">Click to filter</small>
            </div>
        </div>
        <div class="stat-card balance" onclick="filterByType('')" style="cursor:pointer;">
            <div class="stat-icon"><i class="fas fa-wallet"></i></div>
            <div class="stat-info">
                <h3>BDT <?php echo number_format($currentBalance, 2); ?></h3>
                <p>Current Balance</p>
                <small class="text-muted">Click to reset</small>
            </div>
        </div>
    </div>
    
    <!-- Monthly Chart -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-bar text-primary"></i> Monthly Overview</h3>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Transactions Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-list"></i> Transactions</h3>
            <span class="badge badge-info"><?php echo count($transactions); ?> transactions</span>
        </div>
        <div class="card-body">
            <!-- Filters -->
            <div class="transaction-filters">
                <form method="GET" action="" class="filter-form">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <div class="filter-row">
                        <div class="filter-group">
                            <input type="text" name="search" placeholder="Search..." 
                                   value="<?php echo htmlspecialchars($filters['search']); ?>">
                        </div>
                        <div class="filter-group">
                            <select name="type">
                                <option value="">All Types</option>
                                <option value="income" <?php echo $filters['type'] === 'income' ? 'selected' : ''; ?>>Income</option>
                                <option value="expenditure" <?php echo $filters['type'] === 'expenditure' ? 'selected' : ''; ?>>Expenditure</option>
                                <option value="refund" <?php echo $filters['type'] === 'refund' ? 'selected' : ''; ?>>Refund</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <select name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>" 
                                            <?php echo $filters['category'] === $category ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group date-group">
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                            <span>to</span>
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="<?php echo url('admin/fund-event-details.php?id=' . $id); ?>" class="btn btn-outline btn-sm">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            
            <?php if (empty($transactions)): ?>
                <div class="empty-state small">
                    <i class="fas fa-inbox"></i>
                    <p>No transactions found for this event.</p>
                    <?php if ($event['status'] === 'active'): ?>
                        <a href="<?php echo url('admin/add-fund-transaction.php?event_id=' . $id); ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-plus"></i> Add Transaction
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Amount</th>
                                <th>Description</th>
                                <th>Date</th>
                                <th>Student</th>
                                <th>Ref</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $index => $transaction): ?>
                                <tr class="transaction-<?php echo $transaction['transaction_type']; ?>">
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $transaction['transaction_type']; ?>">
                                            <i class="fas fa-<?php echo $transaction['transaction_type'] === 'income' ? 'arrow-down' : ($transaction['transaction_type'] === 'expenditure' ? 'arrow-up' : 'undo'); ?>"></i>
                                            <?php echo ucfirst($transaction['transaction_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($transaction['category'] ?? 'N/A'); ?></td>
                                    <td class="amount-<?php echo $transaction['transaction_type']; ?>">
                                        BDT <?php echo number_format($transaction['amount'], 2); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(substr($transaction['description'], 0, 30)) . (strlen($transaction['description']) > 30 ? '...' : ''); ?></td>
                                    <td><?php echo formatDate($transaction['transaction_date']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['student_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['reference_number'] ?? 'N/A'); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="<?php echo url('admin/edit-fund-transaction.php?id=' . (int)$transaction['id'] . '&event_id=' . (int)$id); ?>" 
                                               class="btn btn-sm btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="<?php echo url('admin/fund-event-details.php?action=delete&id=' . $id . '&transaction_id=' . $transaction['id'] . '&csrf_token=' . Security::generateCSRFToken()); ?>" 
                                               class="btn btn-sm btn-danger" title="Delete"
                                               onclick="return confirm('Delete this transaction?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
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
</div>

<script>
// Chart.js for monthly data
const monthlyData = <?php echo json_encode($monthlyData); ?>;

if (monthlyData.length > 0) {
    const ctx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: monthlyData.map(item => item.month),
            datasets: [
                {
                    label: 'Income',
                    data: monthlyData.map(item => item.income),
                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    borderColor: '#10B981',
                    borderWidth: 2
                },
                {
                    label: 'Expenditure',
                    data: monthlyData.map(item => item.expenditure),
                    backgroundColor: 'rgba(239, 68, 68, 0.7)',
                    borderColor: '#EF4444',
                    borderWidth: 2
                },
                {
                    label: 'Refunds',
                    data: monthlyData.map(item => item.refunds),
                    backgroundColor: 'rgba(245, 158, 11, 0.7)',
                    borderColor: '#F59E0B',
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
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': BDT ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'BDT ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

function filterByType(type) {
    const url = new URL(window.location.href);
    if (type) {
        url.searchParams.set('type', type);
    } else {
        url.searchParams.delete('type');
    }
    window.location.href = url.toString();
}
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>