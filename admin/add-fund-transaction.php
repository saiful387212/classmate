<?php
/**
 * Admin - Add Fund Transaction
 * Add income, expenditure, or refund to a fund event
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

Auth::requireAdmin();

$pageTitle = 'Add Fund Transaction';
$pageCss = ['admin.css', 'fund-events.css'];
$pageJs = ['add-fund-transaction.js'];
$isAdmin = true;

$db = Database::getInstance();
$adminBatchId = Authorization::getAdminBatchId();

$eventId = $_GET['event_id'] ?? 0;
if (!$eventId) {
    Session::setFlash('error', 'Invalid event ID');
    redirect(url('admin/fund-events.php'));
}

// Get event details
$stmt = $db->prepare("
    SELECT e.*, c.class_title, c.class_code 
    FROM fund_events e
    JOIN classes c ON e.class_id = c.id
    WHERE e.id = ? AND e.status = 'active'" . ($adminBatchId !== null ? " AND e.batch_id = ?" : "") . "
");
$stmt->execute($adminBatchId !== null ? [$eventId, $adminBatchId] : [$eventId]);
$event = $stmt->fetch();

if (!$event) {
    Session::setFlash('error', 'Event not found or not active');
    redirect(url('admin/fund-events.php'));
}

// Get students for income and refund transactions
$stmt = $db->prepare("
    SELECT id, full_name, class_roll 
    FROM students 
    WHERE status = 'active' AND batch_id = ?
    ORDER BY full_name
");
$stmt->execute([$event['batch_id']]);
$students = $stmt->fetchAll();

$error = null;
$transactionType = $_GET['type'] ?? 'income';

$formData = [
    'event_id' => $eventId,
    'class_id' => $event['class_id'],
    'transaction_type' => $transactionType,
    'amount' => '',
    'description' => '',
    'category' => '',
    'transaction_date' => date('Y-m-d'),
    'reference_number' => '',
    'payer_receiver' => '',
    'student_id' => '',
    'payment_method' => 'cash'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $data = [
            'event_id' => $eventId,
            'class_id' => $event['class_id'],
            'transaction_type' => $_POST['transaction_type'] ?? 'income',
            'amount' => $_POST['amount'] ?? 0,
            'description' => trim($_POST['description'] ?? ''),
            'category' => trim($_POST['category'] ?? ''),
            'transaction_date' => $_POST['transaction_date'] ?? '',
            'reference_number' => trim($_POST['reference_number'] ?? ''),
            'payer_receiver' => trim($_POST['payer_receiver'] ?? ''),
            'student_id' => $_POST['student_id'] ?? null,
            'payment_method' => $_POST['payment_method'] ?? 'cash',
            'status' => 'approved',
            'created_by' => Session::get('user_id')
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
                INSERT INTO fund_transactions (
                    class_id, transaction_type, amount, description,
                    category, transaction_date, reference_number, payer_receiver,
                    payment_method, status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['class_id'],
                $data['transaction_type'],
                $data['amount'],
                $data['description'],
                $data['category'],
                $data['transaction_date'],
                $data['reference_number'],
                $data['payer_receiver'],
                $data['payment_method'],
                $data['status'],
                $data['created_by']
            ]);
            
            if ($result) {
                $transactionId = $db->lastInsertId();
                Session::setFlash('success', 'Transaction added successfully!');
                logActivity('fund_transaction_added', "Added {$data['transaction_type']} to event ID: {$eventId}");
                redirect(url('admin/fund-event-details.php?id=' . $eventId));
            } else {
                $error = 'Failed to add transaction.';
            }
        }
    }
}

include_once BASE_PATH . '/includes/header.php';
include_once BASE_PATH . '/includes/sidebar-admin.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>Add Transaction</h1>
                <p class="text-muted">
                    <?php echo htmlspecialchars($event['class_code'] . ' - ' . $event['event_name']); ?>
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
    
    <div class="card">
        <div class="card-body">
            <form method="POST" action="" class="transaction-form">
                <?php echo Security::csrfField(); ?>
                
                <div class="form-group">
                    <label for="transaction_type">Transaction Type <span class="required">*</span></label>
                    <div class="type-selector">
                        <label class="type-option income <?php echo $transactionType === 'income' ? 'selected' : ''; ?>">
                            <input type="radio" name="transaction_type" value="income" 
                                   <?php echo $transactionType === 'income' ? 'checked' : ''; ?>
                                   onchange="updateType('income')">
                            <i class="fas fa-arrow-down"></i>
                            <span>Income</span>
                        </label>
                        <label class="type-option expenditure <?php echo $transactionType === 'expenditure' ? 'selected' : ''; ?>">
                            <input type="radio" name="transaction_type" value="expenditure" 
                                   <?php echo $transactionType === 'expenditure' ? 'checked' : ''; ?>
                                   onchange="updateType('expenditure')">
                            <i class="fas fa-arrow-up"></i>
                            <span>Expenditure</span>
                        </label>
                        <label class="type-option refund <?php echo $transactionType === 'refund' ? 'selected' : ''; ?>">
                            <input type="radio" name="transaction_type" value="refund" 
                                   <?php echo $transactionType === 'refund' ? 'checked' : ''; ?>
                                   onchange="updateType('refund')">
                            <i class="fas fa-undo"></i>
                            <span>Refund</span>
                        </label>
                    </div>
                </div>
                
                <div class="form-group" id="studentSelect" style="<?php echo in_array($transactionType, ['income', 'refund'], true) ? 'display:block;' : 'display:none;'; ?>">
                    <label for="student_id">Student</label>
                    <select id="student_id" name="student_id" class="form-control">
                        <option value="">Select Student</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['id']; ?>" <?php echo $formData['student_id'] == $student['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($student['full_name'] . ' (' . $student['class_roll'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="amount">Amount (BDT) <span class="required">*</span></label>
                        <input type="number" id="amount" name="amount" class="form-control" 
                               placeholder="0.00" step="0.01" min="0.01"
                               value="<?php echo htmlspecialchars($formData['amount']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="transaction_date">Transaction Date <span class="required">*</span></label>
                        <input type="date" id="transaction_date" name="transaction_date" class="form-control" 
                               value="<?php echo htmlspecialchars($formData['transaction_date']); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description <span class="required">*</span></label>
                    <textarea id="description" name="description" class="form-control" rows="2" 
                              placeholder="Describe the transaction" required><?php echo htmlspecialchars($formData['description']); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="category">Category</label>
                        <input type="text" id="category" name="category" class="form-control" 
                               placeholder="e.g., Donation, Materials, Fee"
                               value="<?php echo htmlspecialchars($formData['category']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="payer_receiver">Payer / Receiver</label>
                        <input type="text" id="payer_receiver" name="payer_receiver" class="form-control" 
                               placeholder="Who is involved?"
                               value="<?php echo htmlspecialchars($formData['payer_receiver']); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="reference_number">Reference Number</label>
                        <input type="text" id="reference_number" name="reference_number" class="form-control" 
                               placeholder="Invoice or reference number"
                               value="<?php echo htmlspecialchars($formData['reference_number']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select id="payment_method" name="payment_method" class="form-control">
                            <option value="cash" <?php echo $formData['payment_method'] === 'cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="bank_transfer" <?php echo $formData['payment_method'] === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                            <option value="bKash" <?php echo $formData['payment_method'] === 'bKash' ? 'selected' : ''; ?>>bKash</option>
                            <option value="Nagad" <?php echo $formData['payment_method'] === 'Nagad' ? 'selected' : ''; ?>>Nagad</option>
                            <option value="Rocket" <?php echo $formData['payment_method'] === 'Rocket' ? 'selected' : ''; ?>>Rocket</option>
                            <option value="other" <?php echo $formData['payment_method'] === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Transaction
                    </button>
                    <a href="<?php echo url('admin/fund-event-details.php?id=' . $eventId); ?>" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateType(type) {
    document.querySelectorAll('.type-option').forEach(el => {
        el.classList.remove('selected');
    });
    document.querySelector(`.type-option.${type}`).classList.add('selected');
    
    // Show/hide student select for income and refunds
    const studentSelect = document.getElementById('studentSelect');
    if (type === 'income' || type === 'refund') {
        studentSelect.style.display = 'block';
    } else {
        studentSelect.style.display = 'none';
        document.getElementById('student_id').value = '';
    }
}
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>