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

$target = db_one('SELECT userID, userRole, status FROM tbl_user WHERE userID = ?', [$targetID]);
if (!$target) {
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit;
}

$meID = (int) $sessionUser['userID'];

if ($targetID === $meID) {
    echo json_encode(['success' => false, 'message' => 'You cannot deactivate your own account.']);
    exit;
}
if ((string) $target['userRole'] === 'admin') {
    echo json_encode(['success' => false, 'message' => 'Admin accounts cannot be deactivated.']);
    exit;
}

/* ── Flip status ── */
$newStatus = (string) $target['status'] === 'active' ? 'inactive' : 'active';
db_execute('UPDATE tbl_user SET status = ? WHERE userID = ?', [$newStatus, $targetID]);

log_activity('toggle_user_status', "Set user #{$targetID} status to {$newStatus}", $meID);

echo json_encode(['success' => true, 'newStatus' => $newStatus]);
