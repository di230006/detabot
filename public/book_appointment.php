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

$uid = (int) $user['userID'];
$clinic = clinic();
$clinicID = (int) ($clinic['clinicID'] ?? 1);
$today = date('Y-m-d');

$allowedDentists = ['Dr. Muhammad Firdaus', 'Dr. Siti Zafirah', 'Dr. Alia Suhana'];

$input = $_POST;
$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($ct, 'application/json')) {
    $jsonBody = json_decode((string) file_get_contents('php://input'), true);
    if (is_array($jsonBody)) {
        $input = $jsonBody;
    }
}

$sent = (string) ($input['_csrf_token'] ?? '');
if (!hash_equals(csrf_token(), $sent)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'error' => 'Invalid token.']);
    exit;
}

$service = trim((string) ($input['serviceType'] ?? ''));
$dentistName = trim((string) ($input['dentistName'] ?? ''));
$date = (string) ($input['appointmentDate'] ?? '');
$time = substr((string) ($input['appointmentTime'] ?? ''), 0, 5);
$patientAge = (int) ($input['patientAge'] ?? 0);
$healthCat = (string) ($input['healthProblemCategory'] ?? 'none');
$healthDetail = trim((string) ($input['healthProblemDetail'] ?? ''));
$notes = trim((string) ($input['notes'] ?? ''));

$healthCat = in_array($healthCat, ['none', 'common'], true) ? $healthCat : 'none';

// Fallback: use session user age when patientAge not supplied (AI booking path)
if ($patientAge === 0) {
    $patientAge = (int) ($user['userAge'] ?? 0);
}

// Normalise dentist: "No preference" / empty → default dentist
if ($dentistName === '' || !in_array($dentistName, $allowedDentists, true)) {
    // Try partial match (e.g. "Firdaus" → "Dr. Muhammad Firdaus")
    $matched = '';
    foreach ($allowedDentists as $d) {
        if (stripos($d, $dentistName) !== false) {
            $matched = $d;
            break;
        }
    }
    $dentistName = $matched !== '' ? $matched : $allowedDentists[0];
}

if ($service === '') {
    echo json_encode(['success' => false, 'error' => 'Please select a treatment.']);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < $today) {
    echo json_encode(['success' => false, 'error' => 'Please select a valid future date.']);
    exit;
}
$dayOfWeek = (int) date('w', strtotime($date));
if ($dayOfWeek === 0) {
    echo json_encode(['success' => false, 'error' => 'Clinic is closed on Sundays.']);
    exit;
}
if (!in_array($time, build_time_slots(), true)) {
    echo json_encode(['success' => false, 'error' => 'Please select a valid time slot.']);
    exit;
}
if ($patientAge < 1 || $patientAge > 120) {
    echo json_encode(['success' => false, 'error' => 'Please enter a valid age (1–120).']);
    exit;
}
if (!appointment_meets_minimum_notice($date, $time)) {
    echo json_encode(['success' => false, 'error' => 'Please choose a slot at least 6 hours from now.']);
    exit;
}
if (appointment_conflict($clinicID, $date, $time)) {
    echo json_encode(['success' => false, 'error' => 'That slot is already taken. Please choose another time.']);
    exit;
}

$duration    = service_duration_minutes($service);
$dentistNote = "Dentist: $dentistName";
$fullNotes   = implode("\n", array_filter([$dentistNote, $notes], fn($v) => $v !== ''));

db_execute(
    'INSERT INTO tbl_appointment
     (userID, clinicID, appointmentDate, appointmentTime, serviceType, duration, status,
      patientAge, healthProblemCategory, healthProblemDetail, notes)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [
        $uid,
        $clinicID,
        $date,
        $time . ':00',
        $service,
        $duration,
        'pending',
        $patientAge,
        $healthCat,
        $healthDetail,
        $fullNotes,
    ]
);

$newID = (int) db()->lastInsertId();

log_activity('book_appointment', "Booked appointment #$newID for $service", $uid);

send_appointment_receipt($user, $newID, $date, $time, $service, $dentistName, 'Pay at clinic counter', $duration);

// ── Send acknowledgement email ────────────────────────────────────────────────
if (!empty($user['userEmail'])) {
    $ackData = [
        'patientName'   => (string) $user['username'],
        'appointmentID' => $newID,
        'receiptNo'     => 'APT-' . str_pad((string) $newID, 5, '0', STR_PAD_LEFT),
        'serviceType'   => $service,
        'dentistName'   => $dentistName,
        'date'          => date('l, d F Y', strtotime($date)),
        'time'          => date('g:i A', strtotime('1970-01-01 ' . $time . ':00')),
        'duration'      => $duration . ' min',
    ];
    require_once __DIR__ . '/../email_templates/acknowledgement.php';
    $html = buildAcknowledgementEmail($ackData);
    sendEmail(
        (string) $user['userEmail'],
        (string) $user['username'],
        'Appointment Received — ' . CLINIC_NAME,
        $html
    );
}

echo json_encode(['success' => true, 'appointmentID' => $newID, 'message' => 'Appointment booked successfully']);
