<?php
declare(strict_types=1);

/**
 * buildReminderEmail — 1-hour appointment reminder.
 *
 * @param array{
 *   patientName: string,
 *   serviceType: string,
 *   dentistName: string,
 *   date: string,
 *   time: string,
 *   duration: string,
 *   paymentStatus?: string
 * } $data
 */
function buildReminderEmail(array $data): string
{
    $name          = htmlspecialchars((string) ($data['patientName']    ?? 'Patient'),     ENT_QUOTES);
    $service       = htmlspecialchars((string) ($data['serviceType']    ?? ''),            ENT_QUOTES);
    $dentist       = htmlspecialchars((string) ($data['dentistName']    ?? 'Dental Team'), ENT_QUOTES);
    $date          = htmlspecialchars((string) ($data['date']           ?? ''),            ENT_QUOTES);
    $time          = htmlspecialchars((string) ($data['time']           ?? ''),            ENT_QUOTES);
    $duration      = htmlspecialchars((string) ($data['duration']       ?? ''),            ENT_QUOTES);
    $paymentStatus = (string) ($data['paymentStatus'] ?? 'unpaid');
    $clinicName    = CLINIC_NAME;
    $clinicAddr    = CLINIC_ADDRESS;
    $clinicPhone   = CLINIC_PHONE;
    $clinicUrl     = CLINIC_URL;
    $mapsUrl       = 'https://maps.google.com/?q=' . urlencode($clinicAddr . ', ' . $clinicName);

    $body = <<<HTML

    <!-- Reminder banner -->
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#fff8e6;border-bottom:3px solid #f5d78f;">
      <tr>
        <td style="padding:28px 36px;text-align:center;">
          <div style="font-size:44px;line-height:1;">⏰</div>
          <p style="margin:10px 0 4px;font-size:24px;font-weight:800;color:#92400e;letter-spacing:-0.3px;">Your Appointment is in 1 Hour!</p>
          <p style="margin:0;font-size:14px;color:#b45309;">Please make sure you are ready, {$name}.</p>
        </td>
      </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0">
      <tr>
        <td style="padding:32px 36px 0;">

          <!-- Greeting -->
          <p style="margin:0 0 24px;font-size:14px;color:#4b3a6e;line-height:1.7;">
            Hi <strong>{$name}</strong>! This is your 1-hour reminder from <strong>{$clinicName}</strong>.
            Your dental appointment is coming up very soon — we look forward to seeing you!
          </p>

          <!-- Today's appointment details -->
          <table width="100%" cellpadding="0" cellspacing="0" style="border:2px solid #7c3aed;border-radius:12px;overflow:hidden;margin-bottom:24px;">
            <tr>
              <td style="background-color:#7c3aed;padding:14px 20px;">
                <span style="font-size:13px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:0.08em;">📅 Today's Appointment</span>
              </td>
            </tr>
            <tr>
              <td style="padding:20px 20px 16px;">
                <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13.5px;border-collapse:collapse;">
                  <tr>
                    <td style="color:#72647a;padding:7px 0;width:110px;border-bottom:1px solid #f3eeff;">Date</td>
                    <td style="font-weight:600;color:#1a0e2e;padding:7px 0;border-bottom:1px solid #f3eeff;">{$date}</td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:7px 0;border-bottom:1px solid #f3eeff;">Time</td>
                    <td style="padding:7px 0;border-bottom:1px solid #f3eeff;">
                      <strong style="color:#7c3aed;font-size:18px;">{$time}</strong>
                      <span style="font-size:12px;color:#c77712;margin-left:8px;font-weight:700;">— in 1 hour!</span>
                    </td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:7px 0;border-bottom:1px solid #f3eeff;">Service</td>
                    <td style="font-weight:600;color:#1a0e2e;padding:7px 0;border-bottom:1px solid #f3eeff;">{$service}</td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:7px 0;border-bottom:1px solid #f3eeff;">Dentist</td>
                    <td style="font-weight:600;color:#1a0e2e;padding:7px 0;border-bottom:1px solid #f3eeff;">{$dentist}</td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:7px 0;">Duration</td>
                    <td style="font-weight:600;color:#1a0e2e;padding:7px 0;">{$duration}</td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>

          <!-- Checklist -->
          <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#e6f1fb;border:1px solid #bfdbfe;border-radius:10px;margin-bottom:20px;">
            <tr>
              <td style="padding:18px 20px;">
                <p style="margin:0 0 12px;font-size:13px;font-weight:700;color:#1e40af;">✓ Last-Minute Checklist</p>
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td style="padding:3px 0;font-size:13px;color:#1d4ed8;">✔&nbsp;&nbsp;Arrive <strong>10 minutes early</strong> to complete check-in</td>
                  </tr>
                  <tr>
                    <td style="padding:3px 0;font-size:13px;color:#1d4ed8;">✔&nbsp;&nbsp;Bring your <strong>IC / MyKad</strong></td>
                  </tr>
                  <tr>
                    <td style="padding:3px 0;font-size:13px;color:#1d4ed8;">✔&nbsp;&nbsp;Inform staff of any <strong>allergies or medications</strong></td>
                  </tr>
                  <tr>
                    <td style="padding:3px 0;font-size:13px;color:#1d4ed8;">✔&nbsp;&nbsp;Payment ready: <strong>Cash / Touch 'n Go QR / FPX</strong></td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>

          <!-- Clinic location -->
          <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#faf8ff;border:1px solid #ede8f8;border-radius:10px;margin-bottom:24px;">
            <tr>
              <td style="padding:18px 20px;">
                <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#3b0764;">📍 {$clinicName}</p>
                <p style="margin:0 0 3px;font-size:13px;color:#72647a;">{$clinicAddr}</p>
                <p style="margin:0;font-size:13px;color:#72647a;">Tel: {$clinicPhone}</p>
              </td>
            </tr>
          </table>

          <!-- CTA buttons -->
          <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
            <tr>
              <td align="center">
                <a href="{$mapsUrl}"
                   style="display:inline-block;background-color:#7c3aed;color:#ffffff;padding:14px 28px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:700;margin-right:8px;">
                  📍 Get Directions
                </a>
                <a href="{$clinicUrl}"
                   style="display:inline-block;background-color:#ffffff;color:#7c3aed;padding:13px 28px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:700;border:2px solid #7c3aed;">
                  View Appointment
                </a>
              </td>
            </tr>
          </table>

HTML;

    // Conditionally append payment reminder if the appointment hasn't been paid yet
    if ($paymentStatus === 'unpaid') {
        $body .= <<<PAYBLOCK

          <!-- Payment reminder (unpaid only) -->
          <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
              <td style="padding:0 36px 8px;">
                <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#faeeda;border:1px solid #f5d78f;border-radius:10px;">
                  <tr>
                    <td style="padding:18px 20px;">
                      <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#92400e;">💳 Payment Reminder</p>
                      <p style="margin:0 0 12px;font-size:13px;color:#a16207;line-height:1.6;">
                        You have not paid for this appointment yet. You can pay at the clinic counter
                        or through the Detabot system before your visit.
                      </p>
                      <table cellpadding="0" cellspacing="0">
                        <tr>
                          <td>
                            <a href="{$clinicUrl}"
                               style="display:inline-block;background-color:#d97706;color:#ffffff;padding:10px 24px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:700;">
                              Pay Now →
                            </a>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>

PAYBLOCK;
    }

    $body .= <<<CLOSE

        </td>
      </tr>
    </table>

CLOSE;

    return emailBaseHtml($body);
}
