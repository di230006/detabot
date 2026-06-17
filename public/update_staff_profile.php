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
    'SELECT userID, userRole FROM tbl_user WHERE userID = ? AND status = ?',
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
$targetID = (int) ($_POST['userID'] ?? 0);
$username = trim((string) ($_POST['username']  ?? ''));
$email    = strtolower(trim((string) ($_POST['userEmail'] ?? '')));
$phone    = trim((string) ($_POST['userPhone'] ?? ''));

/* Only allow editing own profile */
if ($targetID !== (int) $sessionUser['userID']) {
    echo json_encode(['success' => false, 'message' => 'You can only edit your own profile.']);
    exit;
}

/* ── Validate ── */
if ($username === '') {
    echo json_encode(['success' => false, 'message' => 'Full name is required.']);
    exit;
}
if ($username !== strip_tags($username) || strlen($username) > 50) {
    echo json_encode(['success' => false, 'message' => 'Invalid name.']);
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

/* ── Check email uniqueness (allow own email) ── */
$existing = db_one(
    'SELECT userID FROM tbl_user WHERE userEmail = ? AND userID != ?',
    [$email, $targetID]
);
if ($existing) {
    echo json_encode(['success' => false, 'message' => 'That email address is already in use.']);
    exit;
}

/* ── Update ── */
db_execute(
    'UPDATE tbl_user SET username = ?, userEmail = ?, userPhone = ? WHERE userID = ?',
    [$username, $email, $phone, $targetID]
);

log_activity('update_profile', 'Updated own profile details', $targetID);

echo json_encode([
    'success' => true,
    'message' => 'Profile updated successfully.',
]);
