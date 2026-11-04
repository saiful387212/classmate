<?php
/**
 * Admin - Fund Reports
 * Generate and export fund reports
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

Auth::requireAdmin();

$pageTitle = 'Fund Reports';
$pageCss = ['admin.css', 'fund-events.css'];
$pageJs = ['fund-reports.js'];
$isAdmin = true;

$db = Database::getInstance();

$eventId = $_GET['event_id'] ?? 0;
[$batchSql, $batchParams] = Authorization::adminBatchFilter('e');

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
$stmt->execute(array_merge([$eventId], $batchParams));
$event = $stmt->fetch();

if (!$event) {
    Session::setFlash('error', 'Event not found');
    redirect(url('admin/fund-events.php'));
}

$currentBalance = $event['total_income'] - $event['total_expenditure'] - $event['total_refunds'];

// Get all transactions for this event
$stmt = $db->prepare("
    SELECT 
        t.*,
        CASE WHEN t.transaction_type IN ('income', 'refund') THEN t.payer_receiver ELSE NULL END AS student_name,
        a.full_name as created_by_name
    FROM fund_transactions t
    LEFT JOIN admins a ON t.created_by = a.id
    WHERE t.class_id = ?
    ORDER BY t.transaction_date DESC
");
$stmt->execute([$event['class_id']]);
$transactions = $stmt->fetchAll();

include_once BASE_PATH . '/includes/header.php';
include_once BASE_PATH . '/includes/sidebar-admin.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>Fund Report</h1>
                <p class="text-muted">
                    <?php echo htmlspecialchars($event['class_code'] . ' - ' . $event['event_name']); ?>
                </p>
            </div>
            <div class="page-actions">
                <button class="btn btn-primary" onclick="exportPDF()">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </button>
                <button class="btn btn-secondary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
                <a href="<?php echo url('admin/fund-event-details.php?id=' . $eventId); ?>" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
    
    <!-- Report Content -->
    <div id="reportContent">
        <!-- Report Header -->
        <div class="report-header">
            <h2><?php echo htmlspecialchars($event['event_name']); ?></h2>
            <p><strong>Class:</strong> <?php echo htmlspecialchars($event['class_code'] . ' - ' . $event['class_title']); ?></p>
            <p><strong>Date:</strong> <?php echo formatDate($event['event_date']); ?></p>
            <p><strong>Status:</strong> <?php echo ucfirst($event['status']); ?></p>
            <?php if ($event['event_description']): ?>
                <p><strong>Description:</strong> <?php echo htmlspecialchars($event['event_description']); ?></p>
            <?php endif; ?>
            <p><strong>Generated:</strong> <?php echo date('d M Y h:i A'); ?></p>
        </div>
        
        <!-- Summary Cards -->
        <div class="report-summary">
            <div class="summary-card income">
                <span class="label">Total Income</span>
                <span class="value">BDT <?php echo number_format($event['total_income'], 2); ?></span>
            </div>
            <div class="summary-card expenditure">
                <span class="label">Total Expenditure</span>
                <span class="value">BDT <?php echo number_format($event['total_expenditure'], 2); ?></span>
            </div>
            <div class="summary-card refund">
                <span class="label">Total Refunds</span>
                <span class="value">BDT <?php echo number_format($event['total_refunds'], 2); ?></span>
            </div>
            <div class="summary-card balance">
                <span class="label">Current Balance</span>
                <span class="value">BDT <?php echo number_format($currentBalance, 2); ?></span>
            </div>
        </div>
        
        <!-- Transactions Table -->
        <div class="report-table">
            <h3>Transaction Details</h3>
            <?php if (empty($transactions)): ?>
                <p class="text-muted">No transactions found for this event.</p>
            <?php else: ?>
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
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $index => $transaction): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo ucfirst($transaction['transaction_type']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['category'] ?? 'N/A'); ?></td>
                                <td class="amount-<?php echo $transaction['transaction_type']; ?>">
                                    BDT <?php echo number_format($transaction['amount'], 2); ?>
                                </td>
                                <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                <td><?php echo formatDate($transaction['transaction_date']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['student_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($transaction['reference_number'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="3"><strong>Totals</strong></td>
                            <td><strong>BDT <?php echo number_format($event['total_income'], 2); ?></strong></td>
                            <td colspan="4"></td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Signatures -->
        <div class="report-signatures">
            <div class="signature">
                <p>_________________________</p>
                <p><strong>Prepared By</strong></p>
                <p><?php echo htmlspecialchars($currentUser['full_name']); ?></p>
            </div>
            <div class="signature">
                <p>_________________________</p>
                <p><strong>Approved By</strong></p>
                <p>Class Representative</p>
            </div>
            <div class="signature">
                <p>_________________________</p>
                <p><strong>Date</strong></p>
                <p><?php echo date('d M Y'); ?></p>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .sidebar, .app-header, .page-actions, .filter-bar, .no-print {
        display: none !important;
    }
    .main-content {
        margin: 0 !important;
        padding: 20px !important;
    }
    .report-header, .report-summary, .report-table {
        margin-bottom: 20px;
    }
    .report-summary {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
    }
    .summary-card {
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 8px;
        text-align: center;
    }
    .summary-card .value {
        display: block;
        font-size: 18px;
        font-weight: 700;
    }
    .summary-card.income .value { color: #10B981; }
    .summary-card.expenditure .value { color: #EF4444; }
    .summary-card.refund .value { color: #F59E0B; }
    .summary-card.balance .value { color: #4F46E5; }
    .report-signatures {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 30px;
        margin-top: 50px;
        text-align: center;
    }
    .amount-income { color: #10B981; }
    .amount-expenditure { color: #EF4444; }
    .amount-refund { color: #F59E0B; }
    .total-row { background: #f3f4f6; font-weight: 700; }
}

.report-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #e5e7eb;
}
.report-header h2 {
    margin: 0 0 10px 0;
    color: #1f2937;
}
.report-header p {
    margin: 5px 0;
    color: #6b7280;
}

.report-summary {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 30px;
}
.summary-card {
    background: #f9fafb;
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    border: 1px solid #e5e7eb;
}
.summary-card .label {
    display: block;
    font-size: 12px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.summary-card .value {
    display: block;
    font-size: 18px;
    font-weight: 700;
    margin-top: 5px;
}
.summary-card.income .value { color: #10B981; }
.summary-card.expenditure .value { color: #EF4444; }
.summary-card.refund .value { color: #F59E0B; }
.summary-card.balance .value { color: #4F46E5; }

.report-table h3 {
    margin-bottom: 15px;
    color: #1f2937;
}

.report-signatures {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin-top: 50px;
    text-align: center;
}

.amount-income { color: #10B981; font-weight: 600; }
.amount-expenditure { color: #EF4444; font-weight: 600; }
.amount-refund { color: #F59E0B; font-weight: 600; }
</style>

<script>
function exportPDF() {
    // Use html2pdf or DomPDF library
    // For now, open print dialog
    window.print();
}

// Handle print
document.addEventListener('DOMContentLoaded', function() {
    // Add print styles
    const style = document.createElement('style');
    style.textContent = `
        @media print {
            .sidebar, .app-header, .page-actions, .filter-bar, .no-print {
                display: none !important;
            }
            .main-content {
                margin: 0 !important;
                padding: 20px !important;
            }
        }
    `;
    document.head.appendChild(style);
});
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>