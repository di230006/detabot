<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/bootstrap.php';

/* ── Auth: admin only ─────────────────────────────────────────── */
if (empty($_SESSION['userID'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$sessionUser = db_one('SELECT userRole FROM tbl_user WHERE userID = ?', [(int) $_SESSION['userID']]);
if (!$sessionUser || (string) $sessionUser['userRole'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

/* ── CSRF ─────────────────────────────────────────────────────── */
$token = (string) ($_POST['_csrf_token'] ?? '');
if (!hash_equals(csrf_token(), $token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid form token.']);
    exit;
}

/* ── Input ────────────────────────────────────────────────────── */
$clinicName    = trim((string) ($_POST['clinicName']    ?? ''));
$location      = trim((string) ($_POST['location']      ?? ''));
$contactNumber = trim((string) ($_POST['contactNumber'] ?? ''));
$email         = trim((string) ($_POST['email']         ?? ''));
$promotions    = trim((string) ($_POST['promotions']    ?? ''));
$hoursJSONRaw  = trim((string) ($_POST['hoursJSON']     ?? ''));
$servicesArr   = (array) ($_POST['services'] ?? []);

/* ── Validate ─────────────────────────────────────────────────── */
if ($clinicName === '') {
    echo json_encode(['success' => false, 'message' => 'Clinic name is required.']);
    exit;
}
if ($location === '') {
    echo json_encode(['success' => false, 'message' => 'Location is required.']);
    exit;
}
if ($contactNumber === '') {
    echo json_encode(['success' => false, 'message' => 'Contact number is required.']);
    exit;
}

/* ── Sanitise services → newline-separated string ─────────────── */
$services = implode("\n", array_values(array_filter(
    array_map('trim', $servicesArr),
    fn($s) => $s !== ''
)));

/* ── Validate + sanitise hours JSON ──────────────────────────── */
$hoursJSON = '';
$operatingHoursSummary = '';
if ($hoursJSONRaw !== '') {
    $decoded = json_decode($hoursJSONRaw, true);
    if (is_array($decoded)) {
        $sanitised = [];
        $dayNames  = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'];
        $openDays  = [];
        $timeRange = '';

        foreach ([1, 2, 3, 4, 5, 6, 0] as $dayIdx) {
            $dayData = $decoded[$dayIdx] ?? $decoded[(string) $dayIdx] ?? [];
            $closed  = !empty($dayData['closed']);
            $open    = preg_replace('/[^0-9:]/', '', (string) ($dayData['open']  ?? '09:00'));
            $close   = preg_replace('/[^0-9:]/', '', (string) ($dayData['close'] ?? '17:00'));

            /* Validate HH:MM format */
            if (!preg_match('/^\d{2}:\d{2}$/', $open))  { $open  = '09:00'; }
            if (!preg_match('/^\d{2}:\d{2}$/', $close)) { $close = '17:00'; }

            $sanitised[$dayIdx] = [
                'closed' => $closed,
                'open'   => $open,
                'close'  => $close,
            ];

            if (!$closed) {
                $openDays[] = $dayNames[$dayIdx];
                $timeRange  = date('g:i A', strtotime($open)) . ' – ' . date('g:i A', strtotime($close));
            }
        }

        $hoursJSON = json_encode($sanitised, JSON_UNESCAPED_UNICODE);

        /* Human-readable summary for backward compat (chatbot, legacy display) */
        if (!empty($openDays)) {
            $operatingHoursSummary = implode(', ', $openDays) . ': ' . $timeRange;
        } else {
            $operatingHoursSummary = 'Closed';
        }
        /* Truncate to 100 chars for VARCHAR(100) compat */
        if (strlen($operatingHoursSummary) > 100) {
            $operatingHoursSummary = substr($operatingHoursSummary, 0, 97) . '…';
        }
    }
}

/* ── Ensure clinic row exists ─────────────────────────────────── */
$clinic = db_one('SELECT clinicID FROM tbl_clinic WHERE clinicID = 1');
if (!$clinic) {
    echo json_encode(['success' => false, 'message' => 'Clinic record not found.']);
    exit;
}

/* ── Build UPDATE query (include optional columns only if they exist) ── */
$params = [$clinicName, $location, $contactNumber, $services, $promotions];
$setClauses = [
    'clinicName = ?',
    'location = ?',
    'contactNumber = ?',
    'services = ?',
    'promotions = ?',
];

if ($operatingHoursSummary !== '') {
    $setClauses[] = 'operatingHours = ?';
    $params[]     = $operatingHoursSummary;
}

/* clinicEmail and clinicHoursJSON are new columns added via ensure_database_columns */
$setClauses[] = 'clinicEmail = ?';
$params[]     = $email;

if ($hoursJSON !== '') {
    $setClauses[] = 'clinicHoursJSON = ?';
    $params[]     = $hoursJSON;
}

$setClauses[] = 'updatedDate = CURRENT_TIMESTAMP';
$params[]     = 1; /* clinicID */

db_execute(
    'UPDATE tbl_clinic SET ' . implode(', ', $setClauses) . ' WHERE clinicID = ?',
    $params
);

log_activity('update_clinic_info', 'Updated clinic information', (int) $_SESSION['userID']);

echo json_encode([
    'success' => true,
    'message' => 'Clinic information updated successfully!',
]);
