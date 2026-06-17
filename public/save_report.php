<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/bootstrap.php';

/* ── Auth ─────────────────────────────────────────────────────── */
if (empty($_SESSION['userID'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$sessionUser = db_one('SELECT userRole, username FROM tbl_user WHERE userID = ?', [(int) $_SESSION['userID']]);
if (!$sessionUser || !in_array((string) $sessionUser['userRole'], ['admin', 'staff'], true)) {
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

/* ── CSRF ─────────────────────────────────────────────────────── */
$token = (string) ($_POST['_csrf_token'] ?? '');
if (!hash_equals(csrf_token(), $token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid token.']);
    exit;
}

/* ── Input ────────────────────────────────────────────────────── */
$period = preg_replace('/[^a-z]/', '', strtolower((string) ($_POST['period'] ?? 'month')));
if (!in_array($period, ['week', 'month', 'year', 'custom'], true)) {
    $period = 'month';
}

/* ── Insert ───────────────────────────────────────────────────── */
db_execute(
    'INSERT INTO tbl_report (reportType, generatedBy, reportData, parameters, exportFormat)
     VALUES (?, ?, ?, ?, ?)',
    [
        'analytics',
        (string) ($sessionUser['username'] ?? ''),
        json_encode(['period' => $period]),
        json_encode(['period' => $period]),
        'pdf',
    ]
);

log_activity('export_report', 'Exported analytics report (period: ' . $period . ')', (int) $_SESSION['userID']);

echo json_encode(['success' => true]);
