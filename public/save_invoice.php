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
if (!$user || !has_role($user, ['admin', 'staff'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit;
}

$sent = (string) ($_POST['_csrf_token'] ?? '');
if (!hash_equals(csrf_token(), $sent)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'error' => 'Invalid token.']);
    exit;
}

$apptID         = (int) ($_POST['appointmentID'] ?? 0);
$baseService    = trim((string) ($_POST['baseService'] ?? ''));
$baseAmount     = (float) ($_POST['baseAmount'] ?? 0);
$additionalJSON = (string) ($_POST['additionalItems'] ?? '[]');
$discount       = max(0, (float) ($_POST['discount'] ?? 0));
$discountReason = trim((string) ($_POST['discountReason'] ?? ''));
$totalAmount    = (float) ($_POST['totalAmount'] ?? 0);
$notes          = trim((string) ($_POST['notes'] ?? ''));
$action         = (string) ($_POST['action'] ?? 'draft');

if ($apptID <= 0 || $baseService === '' || $baseAmount <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    exit;
}

$appt = db_one(
    "SELECT a.*, u.username, u.userEmail FROM tbl_appointment a
     JOIN tbl_user u ON u.userID = a.userID
     WHERE a.appointmentID = ?",
    [$apptID]
);
if (!$appt) {
    echo json_encode(['success' => false, 'error' => 'Appointment not found.']);
    exit;
}
if ((string) $appt['status'] !== 'completed') {
    echo json_encode(['success' => false, 'error' => 'Invoice can only be generated for completed appointments.']);
    exit;
}

$existing = db_one('SELECT invoiceID FROM tbl_invoice WHERE appointmentID = ?', [$apptID]);
if ($existing) {
    echo json_encode(['success' => false, 'error' => 'An invoice already exists for this appointment.']);
    exit;
}

$items = json_decode($additionalJSON, true);
if (!is_array($items)) {
    $items = [];
}
$additionalJSON = json_encode($items);

$subtotal = $baseAmount;
foreach ($items as $item) {
    $subtotal += (float) ($item['total'] ?? 0);
}
$totalAmount = $subtotal - $discount;
if ($totalAmount < 0) {
    $totalAmount = 0;
}

$patientUID = (int) $appt['userID'];
$staffUID   = (int) $user['userID'];

db_execute(
    'INSERT INTO tbl_invoice
     (appointmentID, userID, invoiceNo, baseService, baseAmount, additionalItems,
      subtotal, discount, discountReason, totalAmount, invoiceStatus, notes, generatedBy)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [
        $apptID,
        $patientUID,
        '__TEMP__',
        $baseService,
        $baseAmount,
        $additionalJSON,
        $subtotal,
        $discount,
        $discountReason ?: null,
        $totalAmount,
        $action === 'send' ? 'unpaid' : 'draft',
        $notes ?: null,
        $staffUID,
    ]
);

$invoiceID = (int) db()->lastInsertId();
$invoiceNo = 'INV-' . date('Ym') . '-' . str_pad((string) $invoiceID, 4, '0', STR_PAD_LEFT);

db_execute('UPDATE tbl_invoice SET invoiceNo = ? WHERE invoiceID = ?', [$invoiceNo, $invoiceID]);

if ($action === 'send') {
    db_execute(
        "UPDATE tbl_appointment SET paymentStatus = 'invoice_sent', updatedDate = CURRENT_TIMESTAMP WHERE appointmentID = ?",
        [$apptID]
    );
}

log_activity('generate_invoice', "Generated invoice $invoiceNo for appointment #$apptID", $staffUID);

if ($action === 'send' && !empty($appt['userEmail'])) {
    $invoiceData = [
        'invoiceNo'      => $invoiceNo,
        'appointmentRef' => 'APT-' . str_pad((string) $apptID, 5, '0', STR_PAD_LEFT),
        'invoiceDate'    => date('l, d F Y'),
        'baseService'    => $baseService,
        'baseAmount'     => number_format($baseAmount, 2),
        'additionalItems'=> $items,
        'subtotal'       => number_format($subtotal, 2),
        'discount'       => number_format($discount, 2),
        'discountReason' => $discountReason,
        'totalAmount'    => number_format($totalAmount, 2),
        'notes'          => $notes,
    ];
    $apptData = [
        'patientName' => (string) $appt['username'],
        'dentistName' => extract_dentist_name((string) ($appt['notes'] ?? '')),
        'date'        => date('l, d F Y', strtotime((string) ($appt['appointmentDate'] ?? ''))),
        'time'        => date('g:i A', strtotime('1970-01-01 ' . substr((string) ($appt['appointmentTime'] ?? ''), 0, 8))),
    ];

    require_once __DIR__ . '/../email_templates/invoice_template.php';
    $html = buildInvoiceEmail($invoiceData, $apptData);
    sendEmail(
        (string) $appt['userEmail'],
        (string) $appt['username'],
        "\xF0\x9F\xA7\xBE Invoice $invoiceNo — " . CLINIC_NAME,
        $html
    );
    log_activity('invoice_email_sent', "Invoice email sent for $invoiceNo", $staffUID);
}

echo json_encode([
    'success'   => true,
    'invoiceID' => $invoiceID,
    'invoiceNo' => $invoiceNo,
    'message'   => $action === 'send' ? 'Invoice sent to patient.' : 'Invoice draft saved.',
]);
