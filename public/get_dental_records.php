<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../app/bootstrap.php';

if (empty($_SESSION['userID'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$sessionUserID = (int) $_SESSION['userID'];
$requestedUID  = (int) ($_GET['userID'] ?? 0);
$offset        = max(0, (int) ($_GET['offset'] ?? 5));
$limit         = 5;

// Patients may only fetch their own records; staff/admin can fetch any
$sessionUser = db_one('SELECT userRole FROM tbl_user WHERE userID = ?', [$sessionUserID]);
$isStaff     = $sessionUser && in_array((string) $sessionUser['userRole'], ['admin', 'staff'], true);

if (!$isStaff && $requestedUID !== $sessionUserID) {
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$uid = $isStaff ? $requestedUID : $sessionUserID;
if (!$uid) { echo json_encode(['success' => false, 'message' => 'Invalid userID']); exit; }

$records = db_all(
    'SELECT dr.*, u.username AS dentistName, a.serviceType AS appointmentService
       FROM tbl_dental_record dr
       JOIN tbl_user u ON dr.recordedBy = u.userID
       LEFT JOIN tbl_appointment a ON dr.appointmentID = a.appointmentID
      WHERE dr.userID = ?
      ORDER BY dr.recordDate DESC
      LIMIT ? OFFSET ?',
    [$uid, $limit, $offset]
);

$total   = (int) ((db_one('SELECT COUNT(*) AS c FROM tbl_dental_record WHERE userID=?', [$uid]) ?? [])['c'] ?? 0);
$hasMore = ($offset + count($records)) < $total;

// Add formatted date for JS rendering
foreach ($records as &$r) {
    $r['recordDateFormatted'] = date('d F Y', (int) strtotime((string) $r['recordDate']));
}
unset($r);

echo json_encode(['success' => true, 'records' => $records, 'hasMore' => $hasMore, 'total' => $total]);
