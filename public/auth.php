<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../app/bootstrap.php';

db();

// ── Input ─────────────────────────────────────────────────────────────────────
$raw      = json_decode((string) file_get_contents('php://input'), true) ?? [];
$email    = strtolower(trim((string) ($raw['userEmail']    ?? '')));
$password = (string) ($raw['userPassword'] ?? '');

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit;
}

// ── Lookup ────────────────────────────────────────────────────────────────────
$user = db_one('SELECT * FROM tbl_user WHERE userEmail = ? AND status = ?', [$email, 'active']);

if (!$user || !password_verify($password, (string) $user['userPassword'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid email or password. Please try again.']);
    exit;
}

// ── Session ───────────────────────────────────────────────────────────────────
$_SESSION['userID']    = (int)    $user['userID'];
$_SESSION['username']  = (string) $user['username'];
$_SESSION['userRole']  = (string) $user['userRole'];
$_SESSION['userEmail'] = (string) $user['userEmail'];

// ── Activity log ───────────────────────────────────────────────────────────────
log_activity('user_login', 'User signed in via Detabot login page', (int) $user['userID']);

// ── Role-based redirect ───────────────────────────────────────────────────────
$role     = (string) $user['userRole'];
$redirect = match ($role) {
    'admin' => 'admin_dashboard.php',
    'staff' => 'dashboard.php',
    default => 'health_record.php',
};

echo json_encode([
    'success'  => true,
    'username' => (string) $user['username'],
    'userRole' => $role,
    'redirect' => $redirect,
]);
