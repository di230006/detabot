<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/bootstrap.php';

/* ── Auth: any logged-in user ── */
if (empty($_SESSION['userID'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$sessionUser = db_one(
    'SELECT userID, userAvatar FROM tbl_user WHERE userID = ? AND status = ?',
    [(int) $_SESSION['userID'], 'active']
);
if (!$sessionUser) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

/* ── CSRF ── */
$token = (string) ($_POST['_csrf_token'] ?? '');
if (!hash_equals(csrf_token(), $token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid form token.']);
    exit;
}

/* ── File ── */
$file = $_FILES['avatar'] ?? null;
if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No valid file received.']);
    exit;
}

$maxBytes  = 2 * 1024 * 1024;
$tmpName   = (string) ($file['tmp_name'] ?? '');
$fileSize  = (int)   ($file['size']     ?? 0);

if ($fileSize > $maxBytes || $tmpName === '') {
    echo json_encode(['success' => false, 'message' => 'Image must be under 2 MB.']);
    exit;
}

$allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$mime = function_exists('mime_content_type')
    ? (string) mime_content_type($tmpName)
    : (string) ($file['type'] ?? '');

if (!isset($allowedMimes[$mime])) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, or WebP images are allowed.']);
    exit;
}

/* ── Save file ── */
$avatarDir = user_avatar_dir();
if (!is_dir($avatarDir)) {
    mkdir($avatarDir, 0775, true);
}

/* Remove old avatar */
$oldAvatar = (string) ($sessionUser['userAvatar'] ?? '');
if ($oldAvatar !== '') {
    @unlink($avatarDir . '/' . $oldAvatar);
}

$stored = 'avatar_' . $sessionUser['userID'] . '_' . bin2hex(random_bytes(6)) . '.' . $allowedMimes[$mime];
if (!move_uploaded_file($tmpName, $avatarDir . '/' . $stored)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save image. Please try again.']);
    exit;
}

/* ── Update DB ── */
db_execute(
    'UPDATE tbl_user SET userAvatar = ? WHERE userID = ?',
    [$stored, (int) $sessionUser['userID']]
);

log_activity('upload_avatar', 'Updated profile picture', (int) $sessionUser['userID']);

echo json_encode([
    'success'   => true,
    'message'   => 'Avatar updated.',
    'avatarUrl' => 'assets/avatars/' . rawurlencode($stored),
]);
