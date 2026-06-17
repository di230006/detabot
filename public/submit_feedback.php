<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$sent = $_POST['_csrf_token'] ?? '';
if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Invalid token.']);
    exit;
}

$apptID   = (int)    ($_POST['appointmentID'] ?? 0);
$rating   = (int)    ($_POST['rating']        ?? 0);
$comments = trim((string) ($_POST['comments'] ?? ''));

if ($apptID <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment.']);
    exit;
}
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Please provide a rating between 1 and 5.']);
    exit;
}
if ($comments === '') {
    echo json_encode(['success' => false, 'message' => 'Please leave a comment.']);
    exit;
}

$appt = db_one(
    "SELECT * FROM tbl_appointment WHERE appointmentID = ? AND userID = ? AND status = 'completed'",
    [$apptID, (int) $user['userID']]
);

if (!$appt) {
    echo json_encode(['success' => false, 'message' => 'Appointment not found or not yet completed.']);
    exit;
}

$existing = db_one(
    'SELECT feedbackID FROM tbl_feedback WHERE appointmentID = ? AND userID = ?',
    [$apptID, (int) $user['userID']]
);
if ($existing) {
    echo json_encode(['success' => false, 'message' => 'You have already submitted feedback for this appointment.']);
    exit;
}

db_execute(
    'INSERT INTO tbl_feedback (userID, appointmentID, rating, comments) VALUES (?, ?, ?, ?)',
    [(int) $user['userID'], $apptID, $rating, $comments]
);

log_activity('submit_feedback', "Submitted feedback for appointment #$apptID, rating $rating", (int) $user['userID']);

echo json_encode(['success' => true, 'message' => 'Thank you for your feedback!']);
