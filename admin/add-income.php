<?php
/**
 * Admin - Add Income
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

$pageTitle = 'Add Income';
$pageCss = ['admin.css', 'forms.css'];
$pageJs = ['fund-form.js'];
$isAdmin = true;

$db = Database::getInstance();
$fundManager = new FundManager();

$classId = $_GET['class_id'] ?? 0;
$error = null;

// Get classes for dropdown
$stmt = $db->query("SELECT id, class_title, class_code FROM classes ORDER BY class_title");
$classes = $stmt->fetchAll();

// Form data
$formData = [
    'class_id' => $classId,
    'amount' => '',
    'description' => '',
    'category' => '',
    'transaction_date' => date('Y-m-d'),
    'reference_number' => '',
    'payer_receiver' => '',
    'payment_method' => 'cash'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $data = [
            'class_id' => $_POST['class_id'] ?? '',
            'transaction_type' => 'income',
            'amount' => $_POST['amount'] ?? 0,
            'description' => trim($_POST['description'] ?? ''),
            'category' => trim($_POST['category'] ?? ''),
            'transaction_date' => $_POST['transaction_date'] ?? '',
            'reference_number' => trim($_POST['reference_number'] ?? ''),
            'payer_receiver' => trim($_POST['payer_receiver'] ?? ''),
            'payment_method' => $_POST['payment_method'] ?? 'cash',
            'created_by' => Session::get('user_id')
        ];
        
        // Validate
        if (empty($data['class_id'])) {
            $error = 'Please select a class';
        } elseif ($data['amount'] <= 0) {
            $error = 'Amount must be greater than 0';
        } elseif (empty($data['description'])) {
            $error = 'Description is required';
        } elseif (empty($data['transaction_date'])) {
            $error = 'Transaction date is required';
        } else {
            $result = $fundManager->addTransaction($data);
            if ($result['success']) {
                Session::setFlash('success', 'Income added successfully!');
                redirect(url('admin/funds.php?class_id=' . $data['class_id']));
            } else {
                $error = $result['message'];
            }
        }
        
        if ($error) {
            $formData = $data;
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
                <h1><i class="fas fa-arrow-down text-success"></i> Add Income</h1>
                <p class="text-muted">Record a new income transaction</p>
            </div>
            <a href="<?php echo url('admin/funds.php' . ($classId ? '?class_id=' . $classId : '')); ?>" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Funds
            </a>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="transaction-form" data-validate>
                <?php echo Security::csrfField(); ?>
                
                <div class="form-group">
                    <label for="class_id">Class <span class="required">*</span></label>
                    <select id="class_id" name="class_id" class="form-control" required>
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" 
                                    <?php echo $formData['class_id'] == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_code'] . ' - ' . $class['class_title']); ?>
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
                              placeholder="Describe the income" required><?php echo htmlspecialchars($formData['description']); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="category">Category</label>
                        <input type="text" id="category" name="category" class="form-control" 
                               placeholder="e.g., Donation, Fee, Fundraising"
                               value="<?php echo htmlspecialchars($formData['category']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="payer_receiver">Payer / Source</label>
                        <input type="text" id="payer_receiver" name="payer_receiver" class="form-control" 
                               placeholder="Who provided the income?"
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
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Record Income
                    </button>
                    <a href="<?php echo url('admin/funds.php' . ($classId ? '?class_id=' . $classId : '')); ?>" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>