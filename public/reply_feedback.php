<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/bootstrap.php';

/* ── Auth: staff / admin only ─────────────────────────────────── */
if (empty($_SESSION['userID'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$sessionUser = db_one('SELECT userRole FROM tbl_user WHERE userID = ?', [(int) $_SESSION['userID']]);
if (!$sessionUser || !in_array((string) $sessionUser['userRole'], ['admin', 'staff'], true)) {
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
$feedbackID    = (int)    ($_POST['feedbackID']    ?? 0);
$adminResponse = trim((string) ($_POST['adminResponse'] ?? ''));

/* ── Validate ─────────────────────────────────────────────────── */
if ($feedbackID <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid feedback ID.']);
    exit;
}

if ($adminResponse === '') {
    echo json_encode(['success' => false, 'message' => 'Reply cannot be empty.']);
    exit;
}

/* ── Verify feedback exists ───────────────────────────────────── */
$existing = db_one('SELECT feedbackID FROM tbl_feedback WHERE feedbackID = ?', [$feedbackID]);
if (!$existing) {
    echo json_encode(['success' => false, 'message' => 'Feedback not found.']);
    exit;
}

/* ── Update ───────────────────────────────────────────────────── */
db_execute(
    'UPDATE tbl_feedback SET adminResponse = ?, responseDate = CURRENT_TIMESTAMP WHERE feedbackID = ?',
    [$adminResponse, $feedbackID]
);

/* ── Log ──────────────────────────────────────────────────────── */
log_activity(
    'reply_feedback',
    'Replied to feedback #' . $feedbackID,
    (int) $_SESSION['userID']
);

echo json_encode([
    'success' => true,
    'message' => 'Reply sent. The patient will see your response on their Feedback page.',
]);
