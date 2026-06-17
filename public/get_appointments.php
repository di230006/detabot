<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json');

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}

$uid    = (int) $user['userID'];
$status = trim((string) ($_GET['status'] ?? ''));

$allowed = ['pending', 'confirmed', 'completed', 'cancelled'];
$params  = [$uid];
$sql     = 'SELECT a.appointmentID, a.appointmentDate, a.appointmentTime, a.serviceType,
                   a.duration, a.status, a.notes, a.patientAge,
                   a.healthProblemCategory, a.healthProblemDetail
            FROM tbl_appointment a
            WHERE a.userID = ?';

if ($status !== '' && in_array($status, $allowed, true)) {
    $sql    .= ' AND a.status = ?';
    $params[] = $status;
}

$sql .= ' ORDER BY a.appointmentDate DESC, a.appointmentTime DESC';

$rows = db_all($sql, $params);

$out = array_map(static function (array $row): array {
    return [
        'appointmentID'   => (int)    $row['appointmentID'],
        'appointmentDate' => (string) $row['appointmentDate'],
        'appointmentTime' => substr((string) $row['appointmentTime'], 0, 5),
        'serviceType'     => (string) $row['serviceType'],
        'duration'        => (int)    $row['duration'],
        'durationLabel'   => format_duration((int) $row['duration']),
        'status'          => (string) $row['status'],
        'dentistName'     => extract_dentist_name((string) ($row['notes'] ?? '')),
    ];
}, $rows);

echo json_encode(['success' => true, 'appointments' => $out]);

function extract_dentist_name(string $notes): string
{
    if (preg_match('/^Dentist:\s*(.+)$/m', $notes, $m)) {
        return trim($m[1]);
    }
    return 'Dental Team';
}
