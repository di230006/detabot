<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$sent = (string) ($_POST['_csrf_token'] ?? '');
if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Invalid session token. Please refresh the page.']);
    exit;
}

$catalogID = (int) ($_POST['rewardCatalogID'] ?? 0);
if ($catalogID <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid reward item.']);
    exit;
}

$item = db_one('SELECT * FROM tbl_reward_catalog WHERE rewardCatalogID = ? AND isActive = 1', [$catalogID]);
if (!$item) {
    echo json_encode(['success' => false, 'message' => 'Reward item is not available.']);
    exit;
}

$uid      = (int) $user['userID'];
$balance  = reward_balance($uid);
$required = (int) $item['pointsRequired'];

if ($balance < $required) {
    echo json_encode(['success' => false, 'message' => 'Not enough points. You need ' . ($required - $balance) . ' more points.']);
    exit;
}

add_reward_transaction($uid, 0, $required, 'redeemed', 'Redeemed: ' . $item['rewardName']);
log_activity('redeem_reward', 'Redeemed ' . $item['rewardName'], $uid);

$newBalance = reward_balance($uid);

echo json_encode([
    'success'    => true,
    'message'    => 'Reward redeemed successfully! Please show this record at the clinic counter.',
    'newBalance' => $newBalance,
]);
