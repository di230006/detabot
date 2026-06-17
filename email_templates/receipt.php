<?php
declare(strict_types=1);

/**
 * buildReceiptEmail — Appointment confirmation receipt (sent when status → confirmed).
 *
 * @param array{
 *   patientName: string,
 *   appointmentID: int,
 *   receiptNo: string,
 *   serviceCategory: string,
 *   serviceType: string,
 *   dentistName: string,
 *   date: string,
 *   time: string,
 *   duration: string,
 *   price: string
 * } $data
 */
function buildReceiptEmail(array $data): string
{
    $name        = htmlspecialchars((string) ($data['patientName']     ?? 'Patient'),     ENT_QUOTES);
    $receiptNo   = htmlspecialchars((string) ($data['receiptNo']       ?? ''),            ENT_QUOTES);
    $category    = htmlspecialchars((string) ($data['serviceCategory'] ?? ''),            ENT_QUOTES);
    $service     = htmlspecialchars((string) ($data['serviceType']     ?? ''),            ENT_QUOTES);
    $dentist     = htmlspecialchars((string) ($data['dentistName']     ?? 'Dental Team'), ENT_QUOTES);
    $date        = htmlspecialchars((string) ($data['date']            ?? ''),            ENT_QUOTES);
    $time        = htmlspecialchars((string) ($data['time']            ?? ''),            ENT_QUOTES);
    $duration    = htmlspecialchars((string) ($data['duration']        ?? ''),            ENT_QUOTES);
    $price       = htmlspecialchars((string) ($data['price']           ?? 'Price on consultation'), ENT_QUOTES);
    $clinicName  = CLINIC_NAME;
    $clinicAddr  = CLINIC_ADDRESS;
    $clinicPhone = CLINIC_PHONE;
    $clinicUrl   = CLINIC_URL;

    $body = <<<HTML

    <!-- ② Confirmed Banner -->
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#eaf3de;border-bottom:3px solid #86efac;">
      <tr>
        <td style="padding:24px 36px;text-align:center;">
          <div style="font-size:40px;line-height:1;">✅</div>
          <p style="margin:8px 0 4px;font-size:22px;font-weight:800;color:#15803d;letter-spacing:-0.3px;">Appointment Confirmed!</p>
          <p style="margin:0;font-size:13px;color:#166534;">Your booking has been approved by our clinic staff.</p>
        </td>
      </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0">
      <tr>
        <td style="padding:32px 36px 0;">

          <!-- ③ Greeting -->
          <p style="margin:0 0 6px;font-size:21px;font-weight:800;color:#1a0e2e;">Hi {$name}! 🎉</p>
          <p style="margin:0 0 28px;font-size:14px;color:#4b3a6e;line-height:1.7;">
            Great news — your dental appointment is <strong style="color:#15803d;">confirmed</strong>.
            Here is your official appointment receipt. Please save this for your records and
            present it at the clinic reception on arrival.
          </p>

          <!-- ④ Receipt Box -->
          <table width="100%" cellpadding="0" cellspacing="0" style="border:2px solid #7c3aed;border-radius:12px;overflow:hidden;margin-bottom:24px;">
            <!-- Receipt header -->
            <tr>
              <td style="background-color:#3b0764;padding:14px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td>
                      <span style="font-size:13px;font-weight:700;color:#c4b2f0;text-transform:uppercase;letter-spacing:0.08em;">🦷 Appointment Receipt</span>
                    </td>
                    <td align="right">
                      <span style="font-size:14px;font-weight:800;color:#ffffff;">{$receiptNo}</span>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <!-- Receipt rows -->
            <tr>
              <td style="padding:20px 20px 0;">
                <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13.5px;border-collapse:collapse;">
                  <tr>
                    <td style="color:#72647a;padding:7px 0;width:130px;border-bottom:1px solid #f3eeff;">Service</td>
                    <td style="font-weight:700;color:#3b0764;padding:7px 0;border-bottom:1px solid #f3eeff;">{$service}</td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:7px 0;border-bottom:1px solid #f3eeff;">Category</td>
                    <td style="font-weight:600;color:#1a0e2e;padding:7px 0;border-bottom:1px solid #f3eeff;">{$category}</td>
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
                    <td style="color:#72647a;padding:7px 0;border-bottom:1px solid #f3eeff;">Duration</td>
                    <td style="font-weight:600;color:#1a0e2e;padding:7px 0;border-bottom:1px solid #f3eeff;">{$duration}</td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:7px 0;border-bottom:1px solid #f3eeff;">Patient</td>
                    <td style="font-weight:600;color:#1a0e2e;padding:7px 0;border-bottom:1px solid #f3eeff;">{$name}</td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:7px 0;">Status</td>
                    <td style="padding:7px 0;">
                      <span style="background-color:#eaf3de;color:#15803d;padding:4px 12px;border-radius:100px;font-size:11.5px;font-weight:700;">✅ Confirmed</span>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <!-- Total row -->
            <tr>
              <td style="background-color:#f4f3ff;border-top:2px solid #ede8f8;padding:14px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td style="font-size:13px;font-weight:700;color:#3b0764;">Estimated Amount</td>
                    <td align="right" style="font-size:15px;font-weight:800;color:#7c3aed;">{$price}</td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>

          <!-- ⑤ Clinic Location -->
          <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#faf8ff;border:1px solid #ede8f8;border-radius:10px;margin-bottom:20px;">
            <tr>
              <td style="padding:18px 20px;">
                <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#3b0764;">📍 Find Us</p>
                <p style="margin:0 0 3px;font-size:13px;color:#1a0e2e;font-weight:600;">{$clinicName}</p>
                <p style="margin:0 0 3px;font-size:13px;color:#72647a;">{$clinicAddr}</p>
                <p style="margin:0;font-size:13px;color:#72647a;">Tel: {$clinicPhone}</p>
              </td>
            </tr>
          </table>

          <!-- ⑥ Before You Arrive Checklist -->
          <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#e6f1fb;border:1px solid #bfdbfe;border-radius:10px;margin-bottom:20px;">
            <tr>
              <td style="padding:18px 20px;">
                <p style="margin:0 0 12px;font-size:13px;font-weight:700;color:#1e40af;">✓ Before You Arrive</p>
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td style="padding:3px 0;font-size:13px;color:#1d4ed8;">✔&nbsp;&nbsp;Arrive <strong>10 minutes early</strong> to complete registration</td>
                  </tr>
                  <tr>
                    <td style="padding:3px 0;font-size:13px;color:#1d4ed8;">✔&nbsp;&nbsp;Bring your <strong>IC / MyKad</strong> for identification</td>
                  </tr>
                  <tr>
                    <td style="padding:3px 0;font-size:13px;color:#1d4ed8;">✔&nbsp;&nbsp;Inform our staff of any <strong>allergies or current medications</strong></td>
                  </tr>
                  <tr>
                    <td style="padding:3px 0;font-size:13px;color:#1d4ed8;">✔&nbsp;&nbsp;Prepare your <strong>payment method</strong> (see below)</td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>

          <!-- ⑦ Payment Section -->
          <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#faeeda;border:1px solid #f5d78f;border-radius:10px;margin-bottom:24px;">
            <tr>
              <td style="padding:18px 20px;">
                <p style="margin:0 0 14px;font-size:13px;font-weight:700;color:#92400e;">💳 Payment — Pay at the Clinic</p>
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <!-- TNG Card -->
                    <td width="48%" style="background-color:#ffffff;border:1px solid #f5d78f;border-radius:8px;padding:14px;vertical-align:top;">
                      <p style="margin:0 0 6px;font-size:13px;font-weight:700;color:#92400e;">📱 Touch 'n Go QR</p>
                      <p style="margin:0;font-size:12px;color:#a16207;line-height:1.5;">Scan our in-clinic TnG QR code at the counter. Fast and cashless.</p>
                    </td>
                    <td width="4%"></td>
                    <!-- FPX Card -->
                    <td width="48%" style="background-color:#ffffff;border:1px solid #f5d78f;border-radius:8px;padding:14px;vertical-align:top;">
                      <p style="margin:0 0 6px;font-size:13px;font-weight:700;color:#92400e;">🏦 FPX / Online Banking</p>
                      <p style="margin:0;font-size:12px;color:#a16207;line-height:1.5;">Transfer via online banking to: <strong>Klinik Pergigian Putra</strong> · Maybank</p>
                    </td>
                  </tr>
                </table>
                <p style="margin:12px 0 0;font-size:12px;color:#92400e;text-align:center;">Cash is also accepted at the counter.</p>
              </td>
            </tr>
          </table>

          <!-- ⑧ CTA Button -->
          <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
            <tr>
              <td align="center">
                <a href="{$clinicUrl}"
                   style="display:inline-block;background-color:#7c3aed;color:#ffffff;padding:14px 36px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:700;letter-spacing:0.02em;">
                  View My Appointment →
                </a>
              </td>
            </tr>
          </table>

          <!-- ⑨ Reward Points -->
          <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#eeedfe;border:1px solid #c4b2f0;border-radius:10px;margin-bottom:8px;">
            <tr>
              <td style="padding:16px 20px;text-align:center;">
                <p style="margin:0 0 4px;font-size:13px;font-weight:700;color:#3b0764;">🌟 Reward Points</p>
                <p style="margin:0;font-size:13px;color:#4b3a6e;">
                  Complete your visit and earn <strong style="color:#7c3aed;">+20 reward points</strong>
                  automatically added to your Detabot account!
                </p>
              </td>
            </tr>
          </table>

        </td>
      </tr>
    </table>

HTML;

    return emailBaseHtml($body);
}
