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
$username = trim((string) ($raw['username']     ?? ''));
$email    = strtolower(trim((string) ($raw['userEmail']    ?? '')));
$phone    = trim((string) ($raw['userPhone']    ?? ''));
$age      = (int) ($raw['userAge']       ?? 0);
$gender   = strtolower(trim((string) ($raw['userGender']   ?? '')));
$password = (string) ($raw['userPassword']  ?? '');

// ── Required fields ────────────────────────────────────────────────────────────
if ($username === '' || $email === '' || $phone === '' || $age === 0 || $gender === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// ── Field validation ───────────────────────────────────────────────────────────
if (strlen($username) < 3 || !preg_match('/^[a-zA-Z\s]+$/u', $username)) {
    echo json_encode(['success' => false, 'message' => 'Invalid name — letters only, minimum 3 characters']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

$phoneDigits = preg_replace('/\D/', '', $phone);
if (strlen($phoneDigits) < 10) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone number — minimum 10 digits']);
    exit;
}

if ($age < 1 || $age > 120) {
    echo json_encode(['success' => false, 'message' => 'Invalid age']);
    exit;
}

if (!in_array($gender, ['male', 'female'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid gender']);
    exit;
}

if (strlen($password) < 8 || !preg_match('/\d/', $password)) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters and contain at least 1 number']);
    exit;
}

// ── Email uniqueness ───────────────────────────────────────────────────────────
if (db_one('SELECT userID FROM tbl_user WHERE userEmail = ?', [$email])) {
    echo json_encode(['success' => false, 'message' => 'Email already exists']);
    exit;
}

// ── Role assignment ────────────────────────────────────────────────────────────
$role = 'patient';
if (str_ends_with($email, '@dentalputra.com')) {
    $role = str_starts_with($email, 'admin') ? 'admin' : 'staff';
}

// ── Insert user ────────────────────────────────────────────────────────────────
db_execute(
    'INSERT INTO tbl_user (username, userEmail, userPassword, userPhone, userAge, userGender, userRole, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
    [
        $username,
        $email,
        password_hash($password, PASSWORD_BCRYPT),
        $phone,
        $age,
        $gender,
        $role,
        'active',
    ]
);

$newUserID = (int) db()->lastInsertId();

// ── Activity log ───────────────────────────────────────────────────────────────
log_activity(
    'user_registered',
    "New $role account registered: $username",
    $newUserID
);

echo json_encode(['success' => true, 'message' => 'Account created successfully']);
