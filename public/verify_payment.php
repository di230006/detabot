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
    echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
    exit;
}

$sent = (string) ($_POST['_csrf_token'] ?? '');
if (!hash_equals(csrf_token(), $sent)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'error' => 'Invalid token.']);
    exit;
}

$paymentID = (int) ($_POST['paymentID'] ?? 0);
$action    = trim((string) ($_POST['action'] ?? ''));

if ($paymentID <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid payment ID.']);
    exit;
}
if (!in_array($action, ['verify', 'reject'], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid action.']);
    exit;
}

$payment = db_one('SELECT * FROM tbl_payment WHERE paymentID = ?', [$paymentID]);
if (!$payment) {
    echo json_encode(['success' => false, 'error' => 'Payment record not found.']);
    exit;
}

$staffID = (int) $user['userID'];
$apptID  = (int) $payment['appointmentID'];

if ($action === 'verify') {
    db_execute(
        'UPDATE tbl_payment SET paymentStatus = ?, verifiedBy = ?, verifiedDate = CURRENT_TIMESTAMP WHERE paymentID = ?',
        ['paid', $staffID, $paymentID]
    );
    db_execute(
        "UPDATE tbl_appointment SET paymentStatus = 'paid', updatedDate = CURRENT_TIMESTAMP WHERE appointmentID = ?",
        [$apptID]
    );
    log_activity('verify_payment', "Verified payment #$paymentID for appointment #$apptID", $staffID);

    // ── Send payment verified email ───────────────────────────────────────────
    $patientRow = db_one(
        'SELECT u.username, u.userEmail, a.serviceType, a.appointmentDate, a.appointmentTime, a.notes
         FROM tbl_user u
         JOIN tbl_appointment a ON a.userID = u.userID
         WHERE a.appointmentID = ?',
        [$apptID]
    );
    if ($patientRow && !empty($patientRow['userEmail'])) {
        $verifiedData = [
            'patientName'    => (string) $patientRow['username'],
            'paymentRef'     => 'PAY-' . str_pad((string) $paymentID, 5, '0', STR_PAD_LEFT),
            'appointmentRef' => 'APT-' . str_pad((string) $apptID, 5, '0', STR_PAD_LEFT),
            'serviceType'    => (string) ($patientRow['serviceType'] ?? ''),
            'dentistName'    => extract_dentist_name((string) ($patientRow['notes'] ?? '')),
            'date'           => date('l, d F Y', strtotime((string) ($patientRow['appointmentDate'] ?? ''))),
            'time'           => date('g:i A', strtotime('1970-01-01 ' . substr((string) ($patientRow['appointmentTime'] ?? ''), 0, 8))),
            'amount'         => number_format((float) ($payment['amount'] ?? 0), 2),
            'paymentMethod'  => (string) ($payment['paymentMethod'] ?? ''),
            'bankName'       => (string) ($payment['bankName'] ?? ''),
        ];
        require_once __DIR__ . '/../email_templates/payment_template.php';
        $html = buildPaymentVerifiedEmail($verifiedData);
        sendEmail(
            (string) $patientRow['userEmail'],
            (string) $patientRow['username'],
            '✅ Payment Verified — ' . CLINIC_NAME,
            $html
        );
        log_activity('payment_verified_email_sent', "Verified email sent for PAY-$paymentID", $staffID);
    }

    echo json_encode(['success' => true, 'message' => 'Payment verified successfully.']);
} else {
    db_execute(
        'UPDATE tbl_payment SET paymentStatus = ?, verifiedBy = ?, verifiedDate = CURRENT_TIMESTAMP WHERE paymentID = ?',
        ['rejected', $staffID, $paymentID]
    );
    db_execute(
        "UPDATE tbl_appointment SET paymentStatus = 'unpaid', updatedDate = CURRENT_TIMESTAMP WHERE appointmentID = ?",
        [$apptID]
    );
    log_activity('verify_payment', "Rejected payment #$paymentID for appointment #$apptID", $staffID);
    echo json_encode(['success' => true, 'message' => 'Payment rejected.']);
}
