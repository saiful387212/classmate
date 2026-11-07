<?php
/**
 * Admin - Export student or fund data as CSV.
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

Auth::requireAdmin();

$exportType = $_GET['type'] ?? '';
if (!in_array($exportType, ['students', 'fund'], true)) {
    http_response_code(400);
    exit('Invalid export type.');
}

$db = Database::getInstance();

if ($exportType === 'students') {
    [$batchSql, $batchParams] = Authorization::adminBatchFilter('s');
    $stmt = $db->prepare("
        SELECT
            s.id,
            s.full_name,
            s.class_roll,
            s.email,
            s.phone,
            s.department,
            s.session,
            s.batch,
            s.batch_id,
            s.status,
            s.created_at
        FROM students s
        WHERE 1=1" . $batchSql . "
        ORDER BY s.created_at DESC
    ");
    $stmt->execute($batchParams);
    $headers = [
        'Student ID', 'Full Name', 'Class Roll', 'Email', 'Phone',
        'Department', 'Session', 'Batch', 'Batch ID', 'Status', 'Created At'
    ];
    $filename = 'students';
} else {
    [$batchSql, $batchParams] = Authorization::adminBatchFilter('c');
    $stmt = $db->prepare("
        SELECT
            t.id,
            t.class_id,
            c.class_code,
            c.class_title,
            c.batch_id,
            t.transaction_type,
            t.amount,
            t.description,
            t.category,
            t.transaction_date,
            t.reference_number,
            t.payer_receiver,
            t.payment_method,
            t.status,
            t.created_by
        FROM fund_transactions t
        JOIN classes c ON c.id = t.class_id
        WHERE 1=1" . $batchSql . "
        ORDER BY t.transaction_date DESC, t.id DESC
    ");
    $stmt->execute($batchParams);
    $headers = [
        'Transaction ID', 'Class ID', 'Class Code', 'Class Title', 'Batch ID',
        'Transaction Type', 'Amount', 'Description', 'Category',
        'Transaction Date', 'Reference Number', 'Payer/Receiver',
        'Payment Method', 'Status', 'Created By Admin ID'
    ];
    $filename = 'fund-transactions';
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '-' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-store, no-cache, must-revalidate');

$output = fopen('php://output', 'w');
if ($output === false) {
    throw new RuntimeException('Unable to open CSV output stream.');
}

fwrite($output, "\xEF\xBB\xBF");
if (fputcsv($output, $headers) === false) {
    throw new RuntimeException('Unable to write CSV headers.');
}

while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $safeRow = array_map(static function ($value) {
        if ($value === null) {
            return '';
        }

        $value = (string)$value;
        if (preg_match('/^[\s]*[=+\-@]/u', $value)) {
            return "'" . $value;
        }

        return $value;
    }, $row);

    if (fputcsv($output, $safeRow) === false) {
        throw new RuntimeException('Unable to write CSV row.');
    }
}

fclose($output);
