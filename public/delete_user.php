<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/bootstrap.php';

/* ── Auth: admin only ── */
if (empty($_SESSION['userID'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$sessionUser = db_one(
    'SELECT userID, userRole FROM tbl_user WHERE userID = ? AND status = ?',
    [(int) $_SESSION['userID'], 'active']
);
if (!$sessionUser || (string) $sessionUser['userRole'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Forbidden — admin access required.']);
    exit;
}

/* ── CSRF ── */
$token = (string) ($_POST['_csrf_token'] ?? '');
if (!hash_equals(csrf_token(), $token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid form token.']);
    exit;
}

/* ── Input ── */
$targetID = (int) ($_POST['userID'] ?? 0);

$target = db_one('SELECT userID, userRole FROM tbl_user WHERE userID = ?', [$targetID]);
if (!$target) {
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit;
}

$meID = (int) $sessionUser['userID'];

if ($targetID === $meID) {
    echo json_encode(['success' => false, 'message' => 'You cannot delete your own account.']);
    exit;
}
if ((string) $target['userRole'] === 'admin') {
    echo json_encode(['success' => false, 'message' => 'Admin accounts cannot be deleted.']);
    exit;
}

/* ── Check linked records ── */
$hasAppointments = db_one(
    'SELECT appointmentID FROM tbl_appointment WHERE userID = ? LIMIT 1',
    [$targetID]
);
if ($hasAppointments) {
    echo json_encode(['success' => false, 'message' => 'Cannot delete — user has existing appointment records.']);
    exit;
}

$hasDentalRecords = db_one(
    'SELECT recordID FROM tbl_dental_record WHERE userID = ? LIMIT 1',
    [$targetID]
);
if ($hasDentalRecords) {
    echo json_encode(['success' => false, 'message' => 'Cannot delete — user has existing dental records.']);
    exit;
}

/* ── Delete ── */
db_execute('DELETE FROM tbl_user WHERE userID = ?', [$targetID]);

log_activity('delete_user', "Deleted user #{$targetID}", $meID);

echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
