<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/bootstrap.php';

/* ── Auth: admin only ─────────────────────────────────────────── */
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
$catalogID = (int) ($_POST['rewardCatalogID'] ?? 0);
if ($catalogID <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid reward ID.']);
    exit;
}

/* ── Fetch current state ──────────────────────────────────────── */
$item = db_one('SELECT rewardCatalogID, rewardName, isActive FROM tbl_reward_catalog WHERE rewardCatalogID = ?', [$catalogID]);
if (!$item) {
    echo json_encode(['success' => false, 'message' => 'Reward item not found.']);
    exit;
}

/* ── Flip ─────────────────────────────────────────────────────── */
$newActive = ((int) $item['isActive'] === 1) ? 0 : 1;

db_execute(
    'UPDATE tbl_reward_catalog SET isActive = ? WHERE rewardCatalogID = ?',
    [$newActive, $catalogID]
);

log_activity(
    'toggle_reward',
    ($newActive ? 'Activated' : 'Deactivated') . ' reward: ' . (string) $item['rewardName'],
    (int) $_SESSION['userID']
);

echo json_encode(['success' => true, 'isActive' => $newActive]);
