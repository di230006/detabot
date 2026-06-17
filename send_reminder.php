<?php
/**
 * send_reminder.php — Appointment 1-hour reminder cron script
 *
 * Run this via Windows Task Scheduler every minute:
 *   Program : C:\xampp\php\php.exe
 *   Arguments: C:\xampp\htdocs\Detabot_VSCode\send_reminder.php
 *
 * Or via Linux/Mac cron:
 *   * * * * * /usr/bin/php /var/www/html/Detabot_VSCode/send_reminder.php
 */

declare(strict_types=1);

// Only run from CLI
if (PHP_SAPI !== 'cli' && !isset($_GET['debug'])) {
    http_response_code(403);
    exit('Forbidden');
}

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/send_email.php';
require __DIR__ . '/email_templates/reminder.php';

$now          = new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur'));
$targetTime   = $now->modify('+1 hour');
$targetDate   = $targetTime->format('Y-m-d');
$targetHHMM   = $targetTime->format('H:i');   // e.g. "14:30"

echo "[" . $now->format('Y-m-d H:i:s') . "] Checking reminders for {$targetDate} {$targetHHMM}…\n";

// Find appointments starting in exactly 1 hour that haven't been reminded yet
$appointments = db_all(
    "SELECT a.*, u.username AS patientName, u.userEmail AS patientEmail
     FROM tbl_appointment a
     JOIN tbl_user u ON u.userID = a.userID
     WHERE a.appointmentDate = ?
       AND TIME(a.appointmentTime) = ?
       AND a.status IN ('confirmed', 'pending')
       AND (a.reminderSent IS NULL OR a.reminderSent = 0)",
    [$targetDate, $targetHHMM . ':00']
);

if (empty($appointments)) {
    echo "No reminders to send.\n";
    exit(0);
}

foreach ($appointments as $appt) {
    $apptID     = (int)  $appt['appointmentID'];
    $email    = (string) ($appt['patientEmail']    ?? '');
    $name     = (string) ($appt['patientName']     ?? 'Patient');
    $service  = (string) ($appt['serviceType']     ?? '');
    $date     = (string) ($appt['appointmentDate'] ?? '');
    $time     = substr((string) ($appt['appointmentTime'] ?? ''), 0, 5);
    $duration = (int)   ($appt['duration']         ?? 30);

    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        echo "  ↳ APT-{$apptID}: skipped (no valid email)\n";
        continue;
    }

    $reminderData = [
        'patientName'   => $name,
        'serviceType'   => $service,
        'dentistName'   => extract_dentist_name((string) ($appt['notes'] ?? '')),
        'date'          => date('l, d F Y', strtotime($date)),
        'time'          => date('g:i A', strtotime('1970-01-01 ' . $time . ':00')),
        'duration'      => $duration . ' min',
        'paymentStatus' => (string) ($appt['paymentStatus'] ?? 'unpaid'),
    ];
    $html = buildReminderEmail($reminderData);
    $sent = sendEmail($email, $name, 'Reminder: Your appointment is in 1 hour — ' . CLINIC_NAME, $html);

    if ($sent) {
        db_execute(
            'UPDATE tbl_appointment SET reminderSent = 1 WHERE appointmentID = ?',
            [$apptID]
        );
        log_activity('reminder_sent', "1-hour reminder sent for appointment #{$apptID}", null);
        echo "  ✓ APT-{$apptID}: reminder sent to {$email}\n";
    } else {
        echo "  ✗ APT-{$apptID}: failed to send to {$email}\n";
    }
}

echo "Done.\n";
