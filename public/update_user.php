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
$targetID = (int) ($_POST['userID']    ?? 0);
$username = trim((string) ($_POST['username']  ?? ''));
$email    = strtolower(trim((string) ($_POST['userEmail'] ?? '')));
$phone    = trim((string) ($_POST['userPhone'] ?? ''));
$role     = (string) ($_POST['userRole']       ?? '');
$status   = (string) ($_POST['status']         ?? '');

/* ── Target exists ── */
$target = db_one('SELECT userID, userRole FROM tbl_user WHERE userID = ?', [$targetID]);
if (!$target) {
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit;
}

$meID = (int) $sessionUser['userID'];

/* ── Guards ── */
if ($targetID === $meID && $status !== 'active') {
    echo json_encode(['success' => false, 'message' => 'You cannot deactivate your own account.']);
    exit;
}
if ((string) $target['userRole'] === 'admin' && $status !== 'active') {
    echo json_encode(['success' => false, 'message' => 'Admin accounts cannot be deactivated.']);
    exit;
}
if ($targetID === $meID && $role !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'You cannot change your own role.']);
    exit;
}

/* ── Validate ── */
if ($username === '' || $username !== strip_tags($username) || strlen($username) > 50) {
    echo json_encode(['success' => false, 'message' => 'Full name is required (no HTML, max 50 characters).']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}
if ($phone === '' || strlen($phone) > 20) {
    echo json_encode(['success' => false, 'message' => 'Phone number is required (max 20 characters).']);
    exit;
}
if (!in_array($role, ['admin', 'staff', 'patient'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid role.']);
    exit;
}
if (!in_array($status, ['active', 'inactive'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status.']);
    exit;
}

/* ── Email uniqueness ── */
$existingEmail = db_one(
    'SELECT userID FROM tbl_user WHERE userEmail = ? AND userID != ?',
    [$email, $targetID]
);
if ($existingEmail) {
    echo json_encode(['success' => false, 'message' => 'That email address is already in use.']);
    exit;
}

/* ── Update ── */
db_execute(
    'UPDATE tbl_user SET username = ?, userEmail = ?, userPhone = ?, userRole = ?, status = ? WHERE userID = ?',
    [$username, $email, $phone, $role, $status, $targetID]
);

log_activity('update_user', "Updated user #{$targetID} ({$username})", $meID);

echo json_encode(['success' => true, 'message' => 'User updated successfully.']);
