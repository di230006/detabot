<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/bootstrap.php';

/* ── Auth: staff or admin ── */
if (empty($_SESSION['userID'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$sessionUser = db_one(
    'SELECT userID, userRole, userPassword FROM tbl_user WHERE userID = ? AND status = ?',
    [(int) $_SESSION['userID'], 'active']
);
if (!$sessionUser || !in_array((string) $sessionUser['userRole'], ['admin', 'staff'], true)) {
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

/* ── CSRF ── */
$token = (string) ($_POST['_csrf_token'] ?? '');
if (!hash_equals(csrf_token(), $token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid form token.']);
    exit;
}

/* ── Input ── */
$targetID       = (int) ($_POST['userID'] ?? 0);
$currentPassword = (string) ($_POST['currentPassword'] ?? '');
$newPassword     = (string) ($_POST['newPassword']     ?? '');

/* Only allow changing own password */
if ($targetID !== (int) $sessionUser['userID']) {
    echo json_encode(['success' => false, 'message' => 'You can only change your own password.']);
    exit;
}

/* ── Validate ── */
if ($currentPassword === '') {
    echo json_encode(['success' => false, 'message' => 'Current password is required.']);
    exit;
}
if (strlen($newPassword) < 8) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters.']);
    exit;
}
if (!preg_match('/[0-9]/', $newPassword)) {
    echo json_encode(['success' => false, 'message' => 'New password must include at least one number.']);
    exit;
}

/* ── Verify current password ── */
if (!password_verify($currentPassword, (string) $sessionUser['userPassword'])) {
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
    exit;
}

/* ── Hash and update ── */
$hashed = password_hash($newPassword, PASSWORD_DEFAULT);
db_execute(
    'UPDATE tbl_user SET userPassword = ? WHERE userID = ?',
    [$hashed, $targetID]
);

log_activity('change_password', 'Changed own password', $targetID);

echo json_encode([
    'success' => true,
    'message' => 'Password updated successfully.',
]);
