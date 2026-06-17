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

$patientID = (int) ($_GET['patientID'] ?? 0);
if ($patientID <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid patientID']);
    exit;
}

/* ── Patient profile ──────────────────────────────────────────── */
$patient = db_one(
    "SELECT userID, username, userEmail, userPhone, userAge, userGender,
            userChronicHealthProblems, userAvatar, status, createdDate
     FROM tbl_user
     WHERE userID = ? AND userRole = 'patient'",
    [$patientID]
);

if (!$patient) {
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
    exit;
}

/* ── Appointment history ──────────────────────────────────────── */
$appointments = db_all(
    "SELECT appointmentID, serviceType, appointmentDate, appointmentTime,
            duration, status, healthProblemDetail, notes, createdDate
     FROM tbl_appointment
     WHERE userID = ?
     ORDER BY appointmentDate DESC, appointmentTime DESC",
    [$patientID]
);

$summary = ['total' => 0, 'completed' => 0, 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0];
foreach ($appointments as $a) {
    $summary['total']++;
    $st = (string) ($a['status'] ?? '');
    if (array_key_exists($st, $summary)) {
        $summary[$st]++;
    }
}

/* ── Dental records ───────────────────────────────────────────── */
$dentalRecords = db_all(
    "SELECT dr.recordID, dr.toothNumber, dr.diagnosis, dr.treatmentDone,
            dr.toothCondition, dr.nextAction, dr.dentistNotes, dr.recordDate,
            u.username AS dentistName
     FROM tbl_dental_record dr
     LEFT JOIN tbl_user u ON u.userID = dr.recordedBy
     WHERE dr.userID = ?
     ORDER BY dr.recordDate DESC",
    [$patientID]
);

/* ── Reward balance ───────────────────────────────────────────── */
$rewardRow     = db_one(
    "SELECT COALESCE(SUM(currentBalance), 0) AS balance FROM tbl_reward WHERE userID = ?",
    [$patientID]
);
$rewardBalance = (int) ($rewardRow['balance'] ?? 0);

/* ── Response ─────────────────────────────────────────────────── */
echo json_encode([
    'success'            => true,
    'patient'            => $patient,
    'appointments'       => $appointments,
    'appointmentSummary' => $summary,
    'dentalRecords'      => $dentalRecords,
    'rewardBalance'      => $rewardBalance,
]);
