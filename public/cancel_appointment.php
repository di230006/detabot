<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

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

$sent = $_POST['_csrf_token'] ?? '';
if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'error' => 'Invalid token.']);
    exit;
}

$apptID = (int) ($_POST['appointmentID'] ?? 0);
if ($apptID <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid appointment.']);
    exit;
}

$appt = db_one(
    'SELECT * FROM tbl_appointment WHERE appointmentID = ? AND userID = ?',
    [$apptID, (int) $user['userID']]
);

if (!$appt) {
    echo json_encode(['success' => false, 'error' => 'Appointment not found.']);
    exit;
}

if (!in_array($appt['status'], ['pending', 'confirmed'], true)) {
    echo json_encode(['success' => false, 'error' => 'Only pending or confirmed appointments can be cancelled.']);
    exit;
}

db_execute(
    "UPDATE tbl_appointment SET status = 'cancelled', updatedDate = CURRENT_TIMESTAMP WHERE appointmentID = ?",
    [$apptID]
);

log_activity('cancel_appointment', 'Cancelled appointment #' . $apptID, (int) $user['userID']);

echo json_encode(['success' => true]);
