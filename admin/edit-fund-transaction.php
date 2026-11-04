<?php
/**
 * Admin - Edit Fund Transaction
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

$pageTitle = 'Edit Fund Transaction';
$pageCss = ['admin.css', 'fund-events.css'];
$pageJs = ['edit-fund-transaction.js'];
$isAdmin = true;

$db = Database::getInstance();
$adminBatchId = Authorization::getAdminBatchId();

$rawTransactionId = $_GET['id'] ?? $_GET['transaction_id'] ?? $_GET['transaction'] ?? $_GET['tid'] ?? 0;
$id = filter_var($rawTransactionId, FILTER_VALIDATE_INT);
$eventId = filter_var($_GET['event_id'] ?? 0, FILTER_VALIDATE_INT);

if ($id === false || $id < 1) {
    Session::setFlash('error', 'Invalid transaction ID');
    redirect(url('admin/fund-events.php'));
}

// Get transaction details
$stmt = $db->prepare("
    SELECT 
        t.*,
        c.batch_id,
        c.class_title,
        c.class_code,
        a.full_name as created_by_name
    FROM fund_transactions t
    JOIN classes c ON t.class_id = c.id
    LEFT JOIN admins a ON t.created_by = a.id
    WHERE t.id = ?" . ($adminBatchId !== null ? " AND c.batch_id = ?" : "") . "
");
$stmt->execute($adminBatchId !== null ? [$id, $adminBatchId] : [$id]);
$transaction = $stmt->fetch();

if (!$transaction) {
    Session::setFlash('error', 'Transaction not found');
    redirect(url('admin/fund-events.php'));
}

// Resolve the event from the link, or fall back to an event for this class.
$eventId = filter_var($eventId, FILTER_VALIDATE_INT);
if ($eventId === false || $eventId < 1) {
    $eventStmt = $db->prepare("SELECT id FROM fund_events WHERE class_id = ?" . ($adminBatchId !== null ? " AND batch_id = ?" : "") . " ORDER BY id DESC LIMIT 1");
    $eventStmt->execute($adminBatchId !== null ? [$transaction['class_id'], $adminBatchId] : [$transaction['class_id']]);
    $eventId = (int)$eventStmt->fetchColumn();
}

$eventStmt = $db->prepare("SELECT event_name FROM fund_events WHERE id = ?" . ($adminBatchId !== null ? " AND batch_id = ?" : ""));
$eventStmt->execute($adminBatchId !== null ? [$eventId, $adminBatchId] : [$eventId]);
$eventName = $eventStmt->fetchColumn() ?: 'Fund Event';
$transaction['event_name'] = $eventName;

// Get students for refund dropdown
$stmt = $db->prepare("
    SELECT id, full_name, class_roll 
    FROM students 
    WHERE status = 'active' AND batch_id = ?
    ORDER BY full_name
");
$stmt->execute([$transaction['batch_id'] ?? 0]);
$students = $stmt->fetchAll();

$error = null;
$success = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $data = [
            'transaction_type' => $_POST['transaction_type'] ?? 'income',
            'amount' => $_POST['amount'] ?? 0,
            'description' => trim($_POST['description'] ?? ''),
            'category' => trim($_POST['category'] ?? ''),
            'transaction_date' => $_POST['transaction_date'] ?? '',
            'reference_number' => trim($_POST['reference_number'] ?? ''),
            'payer_receiver' => trim($_POST['payer_receiver'] ?? ''),
            'student_id' => $_POST['student_id'] ?? null,
            'payment_method' => $_POST['payment_method'] ?? 'cash',
            'status' => $_POST['status'] ?? 'approved'
        ];

        if (in_array($data['transaction_type'], ['income', 'refund'], true) && !empty($data['student_id'])) {
            $studentStmt = $db->prepare("SELECT full_name, class_roll FROM students WHERE id = ? AND status = 'active'");
            $studentStmt->execute([$data['student_id']]);
            $selectedStudent = $studentStmt->fetch();
            if ($selectedStudent) {
                $data['payer_receiver'] = $selectedStudent['full_name'] . ' (' . $selectedStudent['class_roll'] . ')';
            }
        }
        
        // Validate
        if (empty($data['amount']) || $data['amount'] <= 0) {
            $error = 'Amount must be greater than 0.';
        } elseif (empty($data['description'])) {
            $error = 'Description is required.';
        } elseif (empty($data['transaction_date'])) {
            $error = 'Transaction date is required.';
        } else {
            $stmt = $db->prepare("
                UPDATE fund_transactions 
                SET transaction_type = ?,
                    amount = ?,
                    description = ?,
                    category = ?,
                    transaction_date = ?,
                    reference_number = ?,
                    payer_receiver = ?,
                    payment_method = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?" . ($adminBatchId !== null ? " AND class_id IN (SELECT id FROM classes WHERE batch_id = ?)" : "") . "
            ");
            
            $params = [
                $data['transaction_type'],
                $data['amount'],
                $data['description'],
                $data['category'],
                $data['transaction_date'],
                $data['reference_number'],
                $data['payer_receiver'],
                $data['payment_method'],
                $data['status'],
                $id
            ];
            if ($adminBatchId !== null) {
                $params[] = $adminBatchId;
            }
            $result = $stmt->execute($params);
            
            if ($result) {
                // Update fund balance
                $stmt = $db->prepare("
                    UPDATE fund_balances 
                    SET balance = (
                        SELECT COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) - 
                               COALESCE(SUM(CASE WHEN transaction_type = 'expenditure' THEN amount ELSE 0 END), 0) - 
                               COALESCE(SUM(CASE WHEN transaction_type = 'refund' THEN amount ELSE 0 END), 0)
                        FROM fund_transactions 
                        WHERE class_id = ? AND status = 'approved'
                    ),
                    total_income = (
                        SELECT COALESCE(SUM(amount), 0) 
                        FROM fund_transactions 
                        WHERE class_id = ? AND transaction_type = 'income' AND status = 'approved'
                    ),
                    total_expenditure = (
                        SELECT COALESCE(SUM(amount), 0) 
                        FROM fund_transactions 
                        WHERE class_id = ? AND transaction_type = 'expenditure' AND status = 'approved'
                    ),
                    total_refunds = (
                        SELECT COALESCE(SUM(amount), 0) 
                        FROM fund_transactions 
                        WHERE class_id = ? AND transaction_type = 'refund' AND status = 'approved'
                    ),
                    last_updated = NOW()
                    WHERE class_id = ?
                ");
                $stmt->execute([
                    $transaction['class_id'],
                    $transaction['class_id'],
                    $transaction['class_id'],
                    $transaction['class_id'],
                    $transaction['class_id']
                ]);
                
                Session::setFlash('success', 'Transaction updated successfully!');
                logActivity('fund_transaction_updated', "Updated transaction ID: {$id}");
                redirect(url('admin/fund-event-details.php?id=' . $eventId));
            } else {
                $error = 'Failed to update transaction.';
            }
        }
    }
}

// Get transaction types for display
$transactionTypes = [
    'income' => ['label' => 'Income', 'icon' => 'arrow-down', 'color' => 'success'],
    'expenditure' => ['label' => 'Expenditure', 'icon' => 'arrow-up', 'color' => 'danger'],
    'refund' => ['label' => 'Refund', 'icon' => 'undo', 'color' => 'warning']
];

include_once BASE_PATH . '/includes/header.php';
include_once BASE_PATH . '/includes/sidebar-admin.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>Edit Fund Transaction</h1>
                <p class="text-muted">
                    <?php echo htmlspecialchars($transaction['class_code'] . ' - ' . $transaction['event_name']); ?>
                    <span class="separator">|</span>
                    <span class="badge badge-<?php echo $transaction['transaction_type']; ?>">
                        <i class="fas fa-<?php echo $transactionTypes[$transaction['transaction_type']]['icon'] ?? 'circle'; ?>"></i>
                        <?php echo ucfirst($transaction['transaction_type']); ?>
                    </span>
                    <span class="badge badge-<?php echo $transaction['status']; ?>">
                        <?php echo ucfirst($transaction['status']); ?>
                    </span>
                </p>
            </div>
            <a href="<?php echo url('admin/fund-event-details.php?id=' . $eventId); ?>" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
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
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <form method="POST" action="" class="transaction-form">
                <?php echo Security::csrfField(); ?>
                
                <!-- Transaction Type -->
                <div class="form-group">
                    <label for="transaction_type">Transaction Type <span class="required">*</span></label>
                    <div class="type-selector">
                        <?php foreach ($transactionTypes as $key => $type): ?>
                            <label class="type-option <?php echo $key; ?> <?php echo $transaction['transaction_type'] === $key ? 'selected' : ''; ?>">
                                <input type="radio" name="transaction_type" value="<?php echo $key; ?>" 
                                       <?php echo $transaction['transaction_type'] === $key ? 'checked' : ''; ?>
                                       onchange="updateType('<?php echo $key; ?>')">
                                <i class="fas fa-<?php echo $type['icon']; ?>"></i>
                                <span><?php echo $type['label']; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Student Select -->
                <div class="form-group" id="studentSelect" style="<?php echo in_array($transaction['transaction_type'], ['income', 'refund'], true) ? 'display:block;' : 'display:none;'; ?>">
                    <label for="student_id">Student</label>
                    <select id="student_id" name="student_id" class="form-control">
                        <option value="">Select Student</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['id']; ?>" 
                                    <?php echo $transaction['payer_receiver'] === $student['full_name'] . ' (' . $student['class_roll'] . ')' ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($student['full_name'] . ' (' . $student['class_roll'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Amount & Date -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="amount">Amount (BDT) <span class="required">*</span></label>
                        <input type="number" id="amount" name="amount" class="form-control" 
                               placeholder="0.00" step="0.01" min="0.01"
                               value="<?php echo htmlspecialchars($transaction['amount']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="transaction_date">Transaction Date <span class="required">*</span></label>
                        <input type="date" id="transaction_date" name="transaction_date" class="form-control" 
                               value="<?php echo htmlspecialchars($transaction['transaction_date']); ?>" required>
                    </div>
                </div>
                
                <!-- Description -->
                <div class="form-group">
                    <label for="description">Description <span class="required">*</span></label>
                    <textarea id="description" name="description" class="form-control" rows="2" 
                              placeholder="Describe the transaction" required><?php echo htmlspecialchars($transaction['description']); ?></textarea>
                </div>
                
                <!-- Category & Payer/Receiver -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="category">Category</label>
                        <input type="text" id="category" name="category" class="form-control" 
                               placeholder="e.g., Donation, Materials, Fee"
                               value="<?php echo htmlspecialchars($transaction['category'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="payer_receiver">Payer / Receiver</label>
                        <input type="text" id="payer_receiver" name="payer_receiver" class="form-control" 
                               placeholder="Who is involved?"
                               value="<?php echo htmlspecialchars($transaction['payer_receiver'] ?? ''); ?>">
                    </div>
                </div>
                
                <!-- Reference & Payment Method -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="reference_number">Reference Number</label>
                        <input type="text" id="reference_number" name="reference_number" class="form-control" 
                               placeholder="Invoice or reference number"
                               value="<?php echo htmlspecialchars($transaction['reference_number'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select id="payment_method" name="payment_method" class="form-control">
                            <option value="cash" <?php echo ($transaction['payment_method'] ?? 'cash') === 'cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="bank_transfer" <?php echo ($transaction['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                            <option value="bKash" <?php echo ($transaction['payment_method'] ?? '') === 'bKash' ? 'selected' : ''; ?>>bKash</option>
                            <option value="Nagad" <?php echo ($transaction['payment_method'] ?? '') === 'Nagad' ? 'selected' : ''; ?>>Nagad</option>
                            <option value="Rocket" <?php echo ($transaction['payment_method'] ?? '') === 'Rocket' ? 'selected' : ''; ?>>Rocket</option>
                            <option value="other" <?php echo ($transaction['payment_method'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
                
                <!-- Status -->
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="approved" <?php echo $transaction['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="pending" <?php echo $transaction['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="rejected" <?php echo $transaction['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="cancelled" <?php echo $transaction['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <!-- Transaction Info -->
                <div class="transaction-info">
                    <div class="info-item">
                        <label>Created By</label>
                        <span><?php echo htmlspecialchars($transaction['created_by_name'] ?? 'System'); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Created At</label>
                        <span><?php echo formatDateTime($transaction['created_at']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Last Updated</label>
                        <span><?php echo formatDateTime($transaction['updated_at']); ?></span>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Transaction
                    </button>
                    <a href="<?php echo url('admin/fund-event-details.php?id=' . $eventId); ?>" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateType(type) {
    // Update selected state
    document.querySelectorAll('.type-option').forEach(el => {
        el.classList.remove('selected');
    });
    const selectedEl = document.querySelector(`.type-option.${type}`);
    if (selectedEl) {
        selectedEl.classList.add('selected');
    }
    
    // Show/hide student select for income and refunds
    const studentSelect = document.getElementById('studentSelect');
    if (type === 'income' || type === 'refund') {
        studentSelect.style.display = 'block';
    } else {
        studentSelect.style.display = 'none';
        document.getElementById('student_id').value = '';
    }
}

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const amount = document.getElementById('amount').value;
    const description = document.getElementById('description').value.trim();
    const transactionDate = document.getElementById('transaction_date').value;
    
    if (parseFloat(amount) <= 0) {
        e.preventDefault();
        alert('Amount must be greater than 0.');
        return false;
    }
    
    if (description === '') {
        e.preventDefault();
        alert('Description is required.');
        return false;
    }
    
    if (transactionDate === '') {
        e.preventDefault();
        alert('Transaction date is required.');
        return false;
    }
    
    return true;
});
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>