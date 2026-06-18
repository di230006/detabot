<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../send_email.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}

$sent = (string) ($_POST['_csrf_token'] ?? '');
if (!hash_equals(csrf_token(), $sent)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'error' => 'Invalid token.']);
    exit;
}

$uid    = (int) $user['userID'];
$apptID = (int) ($_POST['appointmentID'] ?? 0);
$amount = (float) ($_POST['amount'] ?? 0);
$method = trim((string) ($_POST['paymentMethod'] ?? ''));
$bank   = trim((string) ($_POST['bankName'] ?? ''));
$refNo  = trim((string) ($_POST['referenceNo'] ?? ''));
$payDate = (string) ($_POST['paymentDate'] ?? date('Y-m-d'));

if ($apptID <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid appointment.']);
    exit;
}
if (!in_array($method, ['tng_qr', 'fpx'], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid payment method.']);
    exit;
}
if ($refNo === '') {
    echo json_encode(['success' => false, 'error' => 'Please enter a reference/transaction number.']);
    exit;
}
if ($amount <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid amount.']);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payDate)) {
    $payDate = date('Y-m-d');
}

$appt = db_one(
    'SELECT * FROM tbl_appointment WHERE appointmentID = ? AND userID = ?',
    [$apptID, $uid]
);
if (!$appt) {
    echo json_encode(['success' => false, 'error' => 'Appointment not found.']);
    exit;
}
if ((string) $appt['status'] !== 'confirmed') {
    echo json_encode(['success' => false, 'error' => 'Only confirmed appointments can be paid.']);
    exit;
}
if ((string) ($appt['paymentStatus'] ?? 'unpaid') === 'paid') {
    echo json_encode(['success' => false, 'error' => 'This appointment has already been paid.']);
    exit;
}

// Handle file upload
$proofPath = null;
$fileErr   = (int) ($_FILES['paymentProof']['error'] ?? UPLOAD_ERR_NO_FILE);

if ($fileErr === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['success' => false, 'error' => 'Please upload a payment screenshot or slip.']);
    exit;
}
if ($fileErr !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'File upload error. Please try again.']);
    exit;
}

$file = $_FILES['paymentProof'];
if ((int) $file['size'] > 2 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'File too large. Maximum 2 MB.']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = (string) finfo_file($finfo, (string) $file['tmp_name']);
finfo_close($finfo);

$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
if (!in_array($mime, $allowedMimes, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Please upload JPG, PNG, or PDF.']);
    exit;
}

$ext      = strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));
$filename = 'proof_' . $uid . '_' . $apptID . '_' . time() . '.' . $ext;
$dir      = payment_proof_upload_dir();

if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $filename)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save file. Please try again.']);
    exit;
}

$proofPath = $filename;

db_execute(
    'INSERT INTO tbl_payment
     (appointmentID, userID, amount, paymentMethod, bankName, referenceNo, paymentDate, paymentStatus, proofPath)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [$apptID, $uid, $amount, $method, $bank ?: null, $refNo, $payDate, 'pending_verification', $proofPath]
);

db_execute(
    "UPDATE tbl_appointment SET paymentStatus = 'pending_verification', updatedDate = CURRENT_TIMESTAMP WHERE appointmentID = ?",
    [$apptID]
);

db_execute(
    "UPDATE tbl_invoice SET invoiceStatus = 'pending_verification', updatedDate = CURRENT_TIMESTAMP WHERE appointmentID = ?",
    [$apptID]
);

$paymentID = (int) db()->lastInsertId();

log_activity('submit_payment', "Submitted payment for appointment #$apptID via $method", $uid);

// ── Send payment receipt email ────────────────────────────────────────────────
if (!empty($user['userEmail'])) {
    $apptData = [
        'patientName' => (string) $user['username'],
        'serviceType' => (string) ($appt['serviceType'] ?? ''),
        'dentistName' => extract_dentist_name((string) ($appt['notes'] ?? '')),
        'date'        => date('l, d F Y', strtotime((string) ($appt['appointmentDate'] ?? ''))),
        'time'        => date('g:i A', strtotime('1970-01-01 ' . substr((string) ($appt['appointmentTime'] ?? ''), 0, 8))),
    ];
    $payData = [
        'paymentRef'     => 'PAY-' . str_pad((string) $paymentID, 5, '0', STR_PAD_LEFT),
        'appointmentRef' => 'APT-' . str_pad((string) $apptID, 5, '0', STR_PAD_LEFT),
        'method'         => $method,
        'bankName'       => $bank,
        'referenceNo'    => $refNo,
        'paymentDate'    => date('l, d F Y', strtotime($payDate)),
        'amount'         => number_format($amount, 2),
    ];
    require_once __DIR__ . '/../email_templates/payment_template.php';
    $html = buildPaymentEmail($apptData, $payData);
    sendEmail(
        (string) $user['userEmail'],
        (string) $user['username'],
        '💳 Payment Received — ' . CLINIC_NAME . ' (' . $payData['appointmentRef'] . ')',
        $html
    );
    log_activity('payment_email_sent', "Payment receipt email sent for PAY-$paymentID", $uid);
}

echo json_encode(['success' => true, 'message' => 'Payment submitted. Pending clinic verification.']);
