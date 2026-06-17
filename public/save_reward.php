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
$catalogID   = (int)    ($_POST['rewardCatalogID'] ?? 0);
$rewardName  = trim((string) ($_POST['rewardName']      ?? ''));
$pointsReq   = (int)    ($_POST['pointsRequired']  ?? 0);
$description = trim((string) ($_POST['description']     ?? ''));

/* ── Validate ─────────────────────────────────────────────────── */
if ($rewardName === '') {
    echo json_encode(['success' => false, 'message' => 'Reward name is required.']);
    exit;
}
if ($pointsReq <= 0) {
    echo json_encode(['success' => false, 'message' => 'Points required must be greater than 0.']);
    exit;
}
if ($description === '') {
    echo json_encode(['success' => false, 'message' => 'Description is required.']);
    exit;
}

/* ── Insert or Update ─────────────────────────────────────────── */
if ($catalogID > 0) {
    $existing = db_one('SELECT rewardCatalogID FROM tbl_reward_catalog WHERE rewardCatalogID = ?', [$catalogID]);
    if (!$existing) {
        echo json_encode(['success' => false, 'message' => 'Reward item not found.']);
        exit;
    }
    db_execute(
        'UPDATE tbl_reward_catalog SET rewardName = ?, pointsRequired = ?, description = ? WHERE rewardCatalogID = ?',
        [$rewardName, $pointsReq, $description, $catalogID]
    );
    $action = 'Updated reward catalog item: ' . $rewardName;
    $msg = 'Reward updated successfully.';
} else {
    db_execute(
        'INSERT INTO tbl_reward_catalog (rewardName, pointsRequired, description, isActive) VALUES (?, ?, ?, 1)',
        [$rewardName, $pointsReq, $description]
    );
    $action = 'Added reward catalog item: ' . $rewardName;
    $msg = 'Reward added successfully.';
}

log_activity('save_reward', $action, (int) $_SESSION['userID']);

echo json_encode(['success' => true, 'message' => $msg]);
