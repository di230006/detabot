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
$username = trim((string) ($_POST['username']        ?? ''));
$email    = strtolower(trim((string) ($_POST['userEmail']  ?? '')));
$phone    = trim((string) ($_POST['userPhone']       ?? ''));
$role     = (string) ($_POST['userRole']             ?? '');
$password = (string) ($_POST['userPassword']         ?? '');
$confirm  = (string) ($_POST['confirmPassword']      ?? '');

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
if (!in_array($role, ['admin', 'staff'], true)) {
    echo json_encode(['success' => false, 'message' => 'Role must be Admin or Staff.']);
    exit;
}
if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}
if (!preg_match('/[0-9]/', $password)) {
    echo json_encode(['success' => false, 'message' => 'Password must include at least one number.']);
    exit;
}
if ($password !== $confirm) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
    exit;
}

/* ── Email uniqueness ── */
$existing = db_one('SELECT userID FROM tbl_user WHERE userEmail = ?', [$email]);
if ($existing) {
    echo json_encode(['success' => false, 'message' => 'That email address is already in use.']);
    exit;
}

/* ── Insert ── */
$hashed = password_hash($password, PASSWORD_DEFAULT);
db_execute(
    'INSERT INTO tbl_user (username, userEmail, userPassword, userPhone, userRole, status) VALUES (?, ?, ?, ?, ?, ?)',
    [$username, $email, $hashed, $phone, $role, 'active']
);

log_activity(
    'create_staff_account',
    "Created {$role} account for {$username} ({$email})",
    (int) $sessionUser['userID']
);

echo json_encode(['success' => true, 'message' => ucfirst($role) . ' account created successfully.']);
