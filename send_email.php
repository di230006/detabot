<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ── Core mailer ───────────────────────────────────────────────────────────────

function sendEmail(string $toEmail, string $toName, string $subject, string $htmlBody): bool
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('[Detabot Email] Failed to send to ' . $toEmail . ' — ' . $mail->ErrorInfo);
        return false;
    }
}

// ── Shared HTML wrapper (header + outer shell + footer) ───────────────────────

function emailBaseHtml(string $bodyContent): string
{
    $clinicName = CLINIC_NAME;
    $year       = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Klinik Pergigian Putra</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f0fb;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f0fb;padding:32px 0;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 6px 32px rgba(59,7,100,0.14);">

        <!-- ① Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#3b0764 0%,#5b21b6 100%);padding:28px 36px;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td>
                  <div style="font-size:28px;font-weight:800;color:#ffffff;letter-spacing:-0.5px;line-height:1.1;">
                    🦷 Detabot
                  </div>
                  <div style="font-size:12px;color:#c4b2f0;margin-top:4px;letter-spacing:0.04em;">{$clinicName} &nbsp;·&nbsp; Parit Raja, Johor</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td>
            {$bodyContent}
          </td>
        </tr>

        <!-- ⑩ Footer -->
        <tr>
          <td style="background-color:#f9f7fe;border-top:1px solid #ede8f8;padding:20px 36px;text-align:center;">
            <p style="margin:0 0 4px;font-size:12px;color:#9b8ad4;">
              © {$year} Detabot — {$clinicName}. All rights reserved.
            </p>
            <p style="margin:0;font-size:11px;color:#bbb0d0;">
              This is an automated message. Please do not reply to this email.
            </p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;
}
