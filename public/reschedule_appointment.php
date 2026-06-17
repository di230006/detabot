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

$apptID  = (int)    ($_POST['appointmentID'] ?? 0);
$newDate = (string) ($_POST['newDate']       ?? '');
$newTime = substr((string) ($_POST['newTime'] ?? ''), 0, 5);
$today   = date('Y-m-d');

if ($apptID <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid appointment.']);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate) || $newDate < $today) {
    echo json_encode(['success' => false, 'error' => 'Please select a valid future date.']);
    exit;
}
$dayOfWeek = (int) date('w', strtotime($newDate));
if ($dayOfWeek === 0) {
    echo json_encode(['success' => false, 'error' => 'Clinic is closed on Sundays.']);
    exit;
}
if (!in_array($newTime, build_time_slots(), true)) {
    echo json_encode(['success' => false, 'error' => 'Please select a valid time slot.']);
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
    echo json_encode(['success' => false, 'error' => 'Only pending or confirmed appointments can be rescheduled.']);
    exit;
}

if (!appointment_meets_minimum_notice($newDate, $newTime)) {
    echo json_encode(['success' => false, 'error' => 'Please choose a slot at least 6 hours from now.']);
    exit;
}

$clinicID = (int) ($appt['clinicID'] ?? 1);
if (appointment_conflict($clinicID, $newDate, $newTime, $apptID)) {
    echo json_encode(['success' => false, 'error' => 'That slot is already taken. Please choose another time.']);
    exit;
}

db_execute(
    'UPDATE tbl_appointment SET appointmentDate = ?, appointmentTime = ?, updatedDate = CURRENT_TIMESTAMP WHERE appointmentID = ?',
    [$newDate, $newTime . ':00', $apptID]
);

log_activity('reschedule_appointment', 'Rescheduled appointment #' . $apptID . ' to ' . $newDate . ' ' . $newTime, (int) $user['userID']);

echo json_encode(['success' => true]);
