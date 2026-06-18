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

$apptID    = (int) ($_POST['appointmentID'] ?? 0);
$newStatus = trim((string) ($_POST['newStatus'] ?? ''));
$allowed   = ['pending', 'confirmed', 'completed', 'cancelled'];

if ($apptID <= 0 || !in_array($newStatus, $allowed, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
    exit;
}

$appt = db_one('SELECT * FROM tbl_appointment WHERE appointmentID = ?', [$apptID]);
if (!$appt) {
    echo json_encode(['success' => false, 'error' => 'Appointment not found.']);
    exit;
}

db_execute(
    "UPDATE tbl_appointment SET status = ?, updatedDate = CURRENT_TIMESTAMP WHERE appointmentID = ?",
    [$newStatus, $apptID]
);

if ($newStatus === 'completed' && (string) $appt['status'] !== 'completed') {
    award_completion_points($appt);
}

log_activity(
    'update_appointment_status',
    'Appointment #' . $apptID . ' → ' . $newStatus,
    (int) $user['userID']
);

// ── Send confirmation receipt when appointment is confirmed ──────────────────
if ($newStatus === 'confirmed' && (string) $appt['status'] !== 'confirmed') {
    $patient = db_one('SELECT username, userEmail FROM tbl_user WHERE userID = ?', [(int) $appt['userID']]);

    if ($patient && !empty($patient['userEmail'])) {
        $priceMin    = service_price_min((string) ($appt['serviceType'] ?? ''));
        $receiptData = [
            'patientName'     => (string) $patient['username'],
            'appointmentID'   => $apptID,
            'receiptNo'       => 'APT-' . str_pad((string) $apptID, 5, '0', STR_PAD_LEFT),
            'serviceCategory' => service_category((string) ($appt['serviceType'] ?? '')),
            'serviceType'     => (string) ($appt['serviceType'] ?? ''),
            'dentistName'     => extract_dentist_name((string) ($appt['notes'] ?? '')),
            'date'            => date('l, d F Y', strtotime((string) ($appt['appointmentDate'] ?? ''))),
            'time'            => date('g:i A', strtotime('1970-01-01 ' . substr((string) ($appt['appointmentTime'] ?? ''), 0, 8))),
            'duration'        => ((int) ($appt['duration'] ?? 30)) . ' min',
            'price'           => $priceMin > 0
                                     ? 'RM ' . number_format($priceMin, 0) . ' (starting from)'
                                     : 'Price on consultation',
        ];

        require_once __DIR__ . '/../email_templates/receipt.php';
        $html = buildReceiptEmail($receiptData);
        sendEmail(
            (string) $patient['userEmail'],
            (string) $patient['username'],
            '✅ Appointment Confirmed — ' . CLINIC_NAME . ' (' . $receiptData['receiptNo'] . ')',
            $html
        );

        log_activity('email_receipt_sent', "Confirmation email sent for appointment #{$apptID}", (int) $user['userID']);
    }
}

$response = [
    'success'   => true,
    'message'   => 'Status updated.',
    'newStatus' => $newStatus,
    'apptID'    => $apptID,
];

if ($newStatus === 'completed') {
    $response['invoiceReminder'] = true;
    $response['invoiceUrl']      = 'generate_invoice.php?appointmentID=' . $apptID;
}

echo json_encode($response);
