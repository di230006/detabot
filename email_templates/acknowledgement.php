<?php
declare(strict_types=1);

/**
 * buildAcknowledgementEmail — Sent immediately on booking (status = pending).
 *
 * @param array{
 *   patientName: string,
 *   appointmentID: int,
 *   receiptNo: string,
 *   serviceType: string,
 *   dentistName: string,
 *   date: string,
 *   time: string,
 *   duration: string
 * } $data
 */
function buildAcknowledgementEmail(array $data): string
{
    $name       = htmlspecialchars((string) ($data['patientName']   ?? 'Patient'),     ENT_QUOTES);
    $receiptNo  = htmlspecialchars((string) ($data['receiptNo']     ?? ''),            ENT_QUOTES);
    $service    = htmlspecialchars((string) ($data['serviceType']   ?? ''),            ENT_QUOTES);
    $dentist    = htmlspecialchars((string) ($data['dentistName']   ?? 'Dental Team'), ENT_QUOTES);
    $date       = htmlspecialchars((string) ($data['date']          ?? ''),            ENT_QUOTES);
    $time       = htmlspecialchars((string) ($data['time']          ?? ''),            ENT_QUOTES);
    $duration   = htmlspecialchars((string) ($data['duration']      ?? ''),            ENT_QUOTES);
    $clinicName  = CLINIC_NAME;
    $clinicAddr  = CLINIC_ADDRESS;
    $clinicPhone = CLINIC_PHONE;
    $clinicUrl   = CLINIC_URL;

    $body = <<<HTML

    <!-- Received banner -->
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#fefce8;border-bottom:3px solid #fde047;">
      <tr>
        <td style="padding:24px 36px;text-align:center;">
          <div style="font-size:40px;line-height:1;">📬</div>
          <p style="margin:8px 0 4px;font-size:22px;font-weight:800;color:#854d0e;letter-spacing:-0.3px;">Booking Request Received!</p>
          <p style="margin:0;font-size:13px;color:#a16207;">Pending clinic confirmation — we'll notify you shortly.</p>
        </td>
      </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0">
      <tr>
        <td style="padding:32px 36px 0;">

          <!-- Greeting -->
          <p style="margin:0 0 6px;font-size:21px;font-weight:800;color:#1a0e2e;">Hi {$name}! 👋</p>
          <p style="margin:0 0 28px;font-size:14px;color:#4b3a6e;line-height:1.7;">
            Thank you for choosing <strong>{$clinicName}</strong>! Your appointment request has been
            received and is <strong style="color:#c77712;">pending confirmation</strong> by our clinic staff.
            You will receive a confirmation email with your official receipt once approved — usually within a few hours.
          </p>

          <!-- Request summary box -->
          <table width="100%" cellpadding="0" cellspacing="0" style="border:2px solid #7c3aed;border-radius:12px;overflow:hidden;margin-bottom:24px;">
            <tr>
              <td style="background-color:#5b21b6;padding:14px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td>
                      <span style="font-size:13px;font-weight:700;color:#c4b2f0;text-transform:uppercase;letter-spacing:0.08em;">📋 Appointment Request</span>
                    </td>
                    <td align="right">
                      <span style="font-size:14px;font-weight:800;color:#ffffff;">{$receiptNo}</span>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td style="padding:20px 20px 16px;">
                <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13.5px;border-collapse:collapse;">
                  <tr>
                    <td style="color:#72647a;padding:7px 0;width:110px;border-bottom:1px solid #f3eeff;">Service</td>
                    <td style="font-weight:700;color:#3b0764;padding:7px 0;border-bottom:1px solid #f3eeff;">{$service}</td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:7px 0;border-bottom:1px solid #f3eeff;">Dentist</td>
                    <td style="font-weight:600;color:#1a0e2e;padding:7px 0;border-bottom:1px solid #f3eeff;">{$dentist}</td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:7px 0;border-bottom:1px solid #f3eeff;">Date</td>
                    <td style="font-weight:600;color:#1a0e2e;padding:7px 0;border-bottom:1px solid #f3eeff;">{$date}</td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:7px 0;border-bottom:1px solid #f3eeff;">Time</td>
                    <td style="padding:7px 0;border-bottom:1px solid #f3eeff;">
                      <strong style="color:#7c3aed;font-size:15px;">{$time}</strong>
                    </td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:7px 0;">Status</td>
                    <td style="padding:7px 0;">
                      <span style="background-color:#fefce8;color:#c77712;padding:4px 12px;border-radius:100px;font-size:11.5px;font-weight:700;">⏳ Pending Confirmation</span>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>

          <!-- What happens next -->
          <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;margin-bottom:20px;">
            <tr>
              <td style="padding:18px 20px;">
                <p style="margin:0 0 12px;font-size:13px;font-weight:700;color:#065f46;">What happens next?</p>
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td style="padding:3px 0;font-size:13px;color:#047857;">📬&nbsp;&nbsp;Our staff will review your request during clinic hours.</td>
                  </tr>
                  <tr>
                    <td style="padding:3px 0;font-size:13px;color:#047857;">✅&nbsp;&nbsp;You'll receive a <strong>confirmation email</strong> with your official receipt once approved.</td>
                  </tr>
                  <tr>
                    <td style="padding:3px 0;font-size:13px;color:#047857;">⏰&nbsp;&nbsp;Please arrive <strong>10 minutes early</strong> on the day of your appointment.</td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>

          <!-- Clinic info -->
          <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#faf8ff;border:1px solid #ede8f8;border-radius:10px;margin-bottom:24px;">
            <tr>
              <td style="padding:18px 20px;">
                <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#3b0764;">📍 {$clinicName}</p>
                <p style="margin:0 0 3px;font-size:13px;color:#72647a;">{$clinicAddr}</p>
                <p style="margin:0;font-size:13px;color:#72647a;">Tel: {$clinicPhone}</p>
              </td>
            </tr>
          </table>

          <!-- CTA button -->
          <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:8px;">
            <tr>
              <td align="center">
                <a href="{$clinicUrl}"
                   style="display:inline-block;background-color:#7c3aed;color:#ffffff;padding:14px 36px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:700;letter-spacing:0.02em;">
                  View My Appointment →
                </a>
              </td>
            </tr>
          </table>

        </td>
      </tr>
    </table>

HTML;

    return emailBaseHtml($body);
}
