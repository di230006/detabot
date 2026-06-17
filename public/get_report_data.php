<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/bootstrap.php';

/* ── Auth: staff / admin only ─────────────────────────────────── */
if (empty($_SESSION['userID'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$sessionUser = db_one('SELECT userRole FROM tbl_user WHERE userID = ?', [(int) $_SESSION['userID']]);
if (!$sessionUser || !in_array((string) $sessionUser['userRole'], ['admin', 'staff'], true)) {
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

/* ── Inputs ───────────────────────────────────────────────────── */
$period = preg_replace('/[^a-z]/', '', strtolower((string) ($_GET['period'] ?? 'month')));
if (!in_array($period, ['week', 'month', 'year', 'custom'], true)) {
    $period = 'month';
}
$customFrom = preg_replace('/[^0-9\-]/', '', (string) ($_GET['from'] ?? ''));
$customTo   = preg_replace('/[^0-9\-]/', '', (string) ($_GET['to']   ?? ''));

/* ── Date ranges ──────────────────────────────────────────────── */
[$dateFrom, $dateTo] = rp_date_range_json($period, $customFrom, $customTo);
[$prevFrom, $prevTo] = rp_prev_range_json($dateFrom, $dateTo);

/* ── Service price map ───────────────────────────────────────── */
$servicePrices = [
    'Consultation'    => 30,
    'Scaling'         => 70,
    'Filling'         => 60,
    'Extraction'      => 80,
    'Root Canal'      => 350,
    'Whitening'       => 400,
    'Dental Check-up' => 50,
    'Braces'          => 500,
    'X-Ray'           => 40,
];

/* ── Current period ───────────────────────────────────────────── */
$totalAppts = (int) (db_one(
    "SELECT COUNT(*) AS n FROM tbl_appointment WHERE appointmentDate BETWEEN ? AND ?",
    [$dateFrom, $dateTo]
)['n'] ?? 0);

$completedRows = db_all(
    "SELECT serviceType FROM tbl_appointment WHERE status='completed' AND appointmentDate BETWEEN ? AND ?",
    [$dateFrom, $dateTo]
);
$revenue = (int) array_sum(array_map(
    fn($r) => $servicePrices[(string)($r['serviceType'] ?? '')] ?? 0,
    $completedRows
));

$newPatients = (int) (db_one(
    "SELECT COUNT(*) AS n FROM tbl_user WHERE userRole='patient' AND DATE(createdDate) BETWEEN ? AND ?",
    [$dateFrom, $dateTo]
)['n'] ?? 0);

$avgRatingRow = db_one(
    "SELECT ROUND(AVG(f.rating), 1) AS avg FROM tbl_feedback f
     JOIN tbl_appointment a ON a.appointmentID = f.appointmentID
     WHERE DATE(f.feedbackDate) BETWEEN ? AND ?",
    [$dateFrom, $dateTo]
);
$avgRating = round((float) ($avgRatingRow['avg'] ?? 0), 1);

/* ── Previous period ──────────────────────────────────────────── */
$prevAppts = (int) (db_one(
    "SELECT COUNT(*) AS n FROM tbl_appointment WHERE appointmentDate BETWEEN ? AND ?",
    [$prevFrom, $prevTo]
)['n'] ?? 0);

$prevRows = db_all(
    "SELECT serviceType FROM tbl_appointment WHERE status='completed' AND appointmentDate BETWEEN ? AND ?",
    [$prevFrom, $prevTo]
);
$prevRevenue = (int) array_sum(array_map(
    fn($r) => $servicePrices[(string)($r['serviceType'] ?? '')] ?? 0,
    $prevRows
));

$prevPatients = (int) (db_one(
    "SELECT COUNT(*) AS n FROM tbl_user WHERE userRole='patient' AND DATE(createdDate) BETWEEN ? AND ?",
    [$prevFrom, $prevTo]
)['n'] ?? 0);

/* ── Appointment trend ────────────────────────────────────────── */
$trendRows = db_all(
    "SELECT appointmentDate AS d, COUNT(*) AS n
     FROM tbl_appointment
     WHERE appointmentDate BETWEEN ? AND ?
     GROUP BY appointmentDate
     ORDER BY appointmentDate ASC",
    [$dateFrom, $dateTo]
);

/* ── Status breakdown ─────────────────────────────────────────── */
$statusRows = db_all(
    "SELECT status, COUNT(*) AS n FROM tbl_appointment
     WHERE appointmentDate BETWEEN ? AND ?
     GROUP BY status",
    [$dateFrom, $dateTo]
);
$statusMap = [];
foreach ($statusRows as $sr) {
    $statusMap[(string)$sr['status']] = (int) $sr['n'];
}

/* ── Popular treatments ───────────────────────────────────────── */
$treatments = db_all(
    "SELECT serviceType, COUNT(*) AS bookings,
            SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed
     FROM tbl_appointment
     WHERE appointmentDate BETWEEN ? AND ?
     GROUP BY serviceType
     ORDER BY bookings DESC",
    [$dateFrom, $dateTo]
);

$treatmentsOut = [];
foreach ($treatments as $t) {
    $svc = (string) ($t['serviceType'] ?? '');
    $bk  = (int) $t['bookings'];
    $cp  = (int) $t['completed'];
    $rev = ($servicePrices[$svc] ?? 0) * $cp;
    $treatmentsOut[] = [
        'serviceType' => $svc,
        'bookings'    => $bk,
        'revenue'     => $rev,
    ];
}

/* ── Dentist performance ──────────────────────────────────────── */
$dentists = db_all(
    "SELECT u.username,
            COUNT(DISTINCT a.appointmentID) AS apptCount,
            ROUND(AVG(f.rating), 1) AS avgRating
     FROM tbl_user u
     JOIN tbl_appointment a ON a.clinicID > 0
     LEFT JOIN tbl_feedback f ON f.appointmentID = a.appointmentID
     WHERE u.userRole IN ('staff','admin')
       AND a.appointmentDate BETWEEN ? AND ?
     GROUP BY u.userID, u.username
     ORDER BY apptCount DESC
     LIMIT 10",
    [$dateFrom, $dateTo]
);

/* ── Trend helper ─────────────────────────────────────────────── */
$trendPct = function(int $cur, int $prev): string {
    if ($prev === 0) {
        return $cur > 0 ? '+100%' : '0%';
    }
    return sprintf('%+.0f%%', (($cur - $prev) / $prev) * 100);
};

/* ── Response ─────────────────────────────────────────────────── */
echo json_encode([
    'success'  => true,
    'period'   => ['from' => $dateFrom, 'to' => $dateTo],
    'stats'    => [
        'totalAppts'  => $totalAppts,
        'revenue'     => $revenue,
        'newPatients' => $newPatients,
        'avgRating'   => $avgRating,
        'apptTrend'   => $trendPct($totalAppts, $prevAppts),
        'revTrend'    => $trendPct($revenue, $prevRevenue),
        'ptTrend'     => $trendPct($newPatients, $prevPatients),
    ],
    'trend'    => [
        'labels' => array_column($trendRows, 'd'),
        'counts' => array_map(fn($r) => (int) $r['n'], $trendRows),
    ],
    'status'   => [
        'completed' => $statusMap['completed']  ?? 0,
        'pending'   => $statusMap['pending']    ?? 0,
        'confirmed' => $statusMap['confirmed']  ?? 0,
        'cancelled' => $statusMap['cancelled']  ?? 0,
    ],
    'treatments' => $treatmentsOut,
    'dentists'   => array_map(fn($d) => [
        'username'  => (string) ($d['username'] ?? ''),
        'apptCount' => (int) ($d['apptCount'] ?? 0),
        'avgRating' => isset($d['avgRating']) ? (float) $d['avgRating'] : null,
    ], $dentists),
]);

/* ── Local helpers (mirror of view helpers) ───────────────────── */
function rp_date_range_json(string $period, string $from, string $to): array
{
    $today = date('Y-m-d');
    return match($period) {
        'week'   => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week'))],
        'year'   => [date('Y-01-01'), date('Y-12-31')],
        'custom' => [$from ?: date('Y-m-01'), $to ?: $today],
        default  => [date('Y-m-01'), date('Y-m-t')],
    };
}

function rp_prev_range_json(string $from, string $to): array
{
    $days = max(1, (int) round((strtotime($to) - strtotime($from)) / 86400) + 1);
    $prevTo   = date('Y-m-d', strtotime($from) - 86400);
    $prevFrom = date('Y-m-d', strtotime($prevTo) - ($days - 1) * 86400);
    return [$prevFrom, $prevTo];
}
