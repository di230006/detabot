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

/* ── Fetch item ───────────────────────────────────────────────── */
$item = db_one('SELECT rewardCatalogID, rewardName FROM tbl_reward_catalog WHERE rewardCatalogID = ?', [$catalogID]);
if (!$item) {
    echo json_encode(['success' => false, 'message' => 'Reward item not found.']);
    exit;
}

/* ── Guard: check for existing redemptions ────────────────────── */
$redemptionRow = db_one(
    "SELECT COUNT(*) AS n FROM tbl_reward
     WHERE transactionType = 'redeemed'
       AND rewardDescription = ?",
    ['Redeemed: ' . (string) $item['rewardName']]
);
$redemptionCount = (int) ($redemptionRow['n'] ?? 0);

if ($redemptionCount > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Cannot delete — patients have already redeemed this reward. Deactivate it instead.',
    ]);
    exit;
}

/* ── Delete ───────────────────────────────────────────────────── */
db_execute('DELETE FROM tbl_reward_catalog WHERE rewardCatalogID = ?', [$catalogID]);

log_activity('delete_reward', 'Deleted reward: ' . (string) $item['rewardName'], (int) $_SESSION['userID']);

echo json_encode(['success' => true]);
