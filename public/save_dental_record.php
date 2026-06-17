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

/* ── CSRF ─────────────────────────────────────────────────────── */
$token = (string) ($_POST['_csrf_token'] ?? '');
if (!hash_equals(csrf_token(), $token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid form token.']);
    exit;
}

/* ── Input ────────────────────────────────────────────────────── */
$patientID     = (int)    ($_POST['patientID']     ?? 0);
$appointmentID = (int)    ($_POST['appointmentID'] ?? 0);
$toothNumber   = trim((string) ($_POST['toothNumber']   ?? ''));
$diagnosis     = trim((string) ($_POST['diagnosis']     ?? ''));
$treatmentDone = trim((string) ($_POST['treatmentDone'] ?? ''));
$toothCondition = trim((string) ($_POST['toothCondition'] ?? ''));
$nextAction    = trim((string) ($_POST['nextAction']    ?? ''));
$dentistNotes  = trim((string) ($_POST['dentistNotes']  ?? ''));

/* ── Validate ─────────────────────────────────────────────────── */
if ($patientID <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid patient.']);
    exit;
}

if ($diagnosis === '' || $treatmentDone === '') {
    echo json_encode(['success' => false, 'message' => 'Diagnosis and treatment done are required.']);
    exit;
}

$allowedConditions = ['good', 'monitor', 'needs_treatment', 'extracted'];
if (!in_array($toothCondition, $allowedConditions, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid tooth condition value.']);
    exit;
}

/* ── Verify patient exists ────────────────────────────────────── */
$patient = db_one("SELECT userID FROM tbl_user WHERE userID = ? AND userRole = 'patient'", [$patientID]);
if (!$patient) {
    echo json_encode(['success' => false, 'message' => 'Patient not found.']);
    exit;
}

/* ── Verify appointment belongs to this patient (if provided) ── */
if ($appointmentID > 0) {
    $appt = db_one(
        "SELECT appointmentID FROM tbl_appointment WHERE appointmentID = ? AND userID = ? AND status = 'completed'",
        [$appointmentID, $patientID]
    );
    if (!$appt) {
        $appointmentID = 0;
    }
}

/* ── Insert ───────────────────────────────────────────────────── */
db_execute(
    "INSERT INTO tbl_dental_record
        (userID, appointmentID, toothNumber, diagnosis, treatmentDone, toothCondition, nextAction, dentistNotes, recordedBy)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
    [
        $patientID,
        $appointmentID ?: null,
        $toothNumber   ?: null,
        $diagnosis,
        $treatmentDone,
        $toothCondition,
        $nextAction    ?: null,
        $dentistNotes  ?: null,
        (int) $_SESSION['userID'],
    ]
);

/* ── Log activity ─────────────────────────────────────────────── */
log_activity(
    'add_dental_record',
    'Added dental record for patient #' . $patientID,
    (int) $_SESSION['userID']
);

/* ── Return updated records so UI can refresh without reload ─── */
$updatedRecords = db_all(
    "SELECT dr.recordID, dr.toothNumber, dr.diagnosis, dr.treatmentDone,
            dr.toothCondition, dr.nextAction, dr.dentistNotes, dr.recordDate,
            u.username AS dentistName
     FROM tbl_dental_record dr
     LEFT JOIN tbl_user u ON u.userID = dr.recordedBy
     WHERE dr.userID = ?
     ORDER BY dr.recordDate DESC",
    [$patientID]
);

echo json_encode([
    'success'       => true,
    'message'       => 'Health record saved. The patient can now view it on their Health Record page.',
    'dentalRecords' => $updatedRecords,
]);
