<?php
declare(strict_types=1);

/**
 * buildPaymentEmail — Sent when patient submits payment (pending_verification).
 *
 * @param array{
 *   patientName: string,
 *   serviceType: string,
 *   dentistName: string,
 *   date: string,
 *   time: string
 * } $apptData
 *
 * @param array{
 *   paymentRef: string,
 *   appointmentRef: string,
 *   method: string,
 *   bankName: string,
 *   referenceNo: string,
 *   paymentDate: string,
 *   amount: string
 * } $paymentData
 */
function buildPaymentEmail(array $apptData, array $paymentData): string
{
    $name       = htmlspecialchars((string) ($apptData['patientName'] ?? 'Patient'),     ENT_QUOTES);
    $service    = htmlspecialchars((string) ($apptData['serviceType'] ?? ''),            ENT_QUOTES);
    $dentist    = htmlspecialchars((string) ($apptData['dentistName'] ?? 'Dental Team'), ENT_QUOTES);
    $date       = htmlspecialchars((string) ($apptData['date']        ?? ''),            ENT_QUOTES);
    $time       = htmlspecialchars((string) ($apptData['time']        ?? ''),            ENT_QUOTES);

    $payRef     = htmlspecialchars((string) ($paymentData['paymentRef']     ?? ''), ENT_QUOTES);
    $apptRef    = htmlspecialchars((string) ($paymentData['appointmentRef'] ?? ''), ENT_QUOTES);
    $method     = (string) ($paymentData['method'] ?? '');
    $bankName   = htmlspecialchars((string) ($paymentData['bankName']    ?? ''), ENT_QUOTES);
    $refNo      = htmlspecialchars((string) ($paymentData['referenceNo'] ?? ''), ENT_QUOTES);
    $payDate    = htmlspecialchars((string) ($paymentData['paymentDate'] ?? ''), ENT_QUOTES);
    $amount     = htmlspecialchars((string) ($paymentData['amount']     ?? ''), ENT_QUOTES);

    $methodLabel = match ($method) {
        'tng_qr' => "Touch 'n Go QR",
        'fpx'    => 'FPX Online Banking',
        default  => htmlspecialchars($method, ENT_QUOTES),
    };

    $clinicName  = CLINIC_NAME;
    $clinicAddr  = CLINIC_ADDRESS;
    $clinicPhone = CLINIC_PHONE;
    $clinicUrl   = CLINIC_URL;

    $bankRow = $bankName !== '' ? <<<ROW
                  <tr>
                    <td style="color:#72647a;padding:7px 0;width:170px;border-bottom:1px solid #eff6ff;">Bank</td>
                    <td style="font-weight:600;color:#1a0e2e;padding:7px 0;border-bottom:1px solid #eff6ff;">{$bankName}</td>
                  </tr>
ROW : '';

    $body = <<<HTML

    <!-- Payment submitted banner -->
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#eff6ff;border-bottom:3px solid #93c5fd;">
      <tr>
        <td style="padding:24px 36px;text-align:center;">
          <div style="font-size:40px;line-height:1;">💳</div>
          <p style="margin:8px 0 4px;font-size:22px;font-weight:800;color:#1e40af;letter-spacing:-0.3px;">Payment Submitted Successfully!</p>
          <p style="margin:0;font-size:13px;color:#1d4ed8;">We have received your payment and it is pending clinic verification.</p>
        </td>
      </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0">
      <tr>
        <td style="padding:32px 36px 0;">

          <!-- Greeting -->
          <p style="margin:0 0 6px;font-size:21px;font-weight:800;color:#1a0e2e;">Hi {$name}! 💙</p>
          <p style="margin:0 0 28px;font-size:14px;color:#4b3a6e;line-height:1.7;">
            Thank you for submitting your payment to <strong>{$clinicName}</strong>.
            Our staff will review and verify your payment — usually within 24 hours. You will
            receive a notification once it has been confirmed.
          </p>

          <!-- Payment receipt box -->
          <table width="100%" cellpadding="0" cellspacing="0" style="border:2px solid #3b82f6;border-radius:12px;overflow:hidden;margin-bottom:24px;">
            <tr>
              <td style="background-color:#1e40af;padding:14px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td>
                      <span style="font-size:13px;font-weight:700;color:#bfdbfe;text-transform:uppercase;letter-spacing:0.08em;">💳 Payment Receipt</span>
                    </td>
                    <td align="right">
                      <span style="font-size:14px;font-weight:800;color:#ffffff;">{$payRef}</span>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td style="padding:20px 20px 0;">
                <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13.5px;border-collapse:collapse;">
                  <tr>
                    <td style="color:#72647a;padding:7px 0;width:170px;border-bottom:1px solid #eff6ff;">Appointment Ref</td>
                    <td style="font-weight:700;color:#1e40af;padding:7px 0;border-bottom:1px solid #eff6ff;">{$apptRef}</td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:7px 0;border-bottom:1px solid #eff6ff;">Service</td>
                    <td style="font-weight:600;color:#1a0e2e;padding:7px 0;border-bottom:1px solid #eff6ff;">{$service}</td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:7px 0;border-bottom:1px solid #eff6ff;">Dentist</td>
                    <td style="font-weight:600;color:#1a0e2e;padding:7px 0;border-bottom:1px solid #eff6ff;">{$dentist}</td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:7px 0;border-bottom:1px solid #eff6ff;">Date</td>
                    <td style="font-weight:600;color:#1a0e2e;padding:7px 0;border-bottom:1px solid #eff6ff;">{$date}</td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:7px 0;border-bottom:1px solid #eff6ff;">Time</td>
                    <td style="padding:7px 0;border-bottom:1px solid #eff6ff;">
                      <strong style="color:#7c3aed;font-size:15px;">{$time}</strong>
                    </td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:7px 0;border-bottom:1px solid #eff6ff;">Payment Method</td>
                    <td style="font-weight:600;color:#1a0e2e;padding:7px 0;border-bottom:1px solid #eff6ff;">{$methodLabel}</td>
                  </tr>
                  {$bankRow}
                  <tr>
                    <td style="color:#72647a;padding:7px 0;border-bottom:1px solid #eff6ff;">Transaction Ref</td>
                    <td style="font-weight:600;color:#1a0e2e;padding:7px 0;border-bottom:1px solid #eff6ff;font-family:monospace;">{$refNo}</td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:7px 0;">Payment Date</td>
                    <td style="font-weight:600;color:#1a0e2e;padding:7px 0;">{$payDate}</td>
                  </tr>
                </table>
              </td>
            </tr>
            <!-- Amount row -->
            <tr>
              <td style="background-color:#f0fdf4;border-top:2px solid #bbf7d0;padding:14px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td style="font-size:13px;font-weight:700;color:#065f46;">Amount Paid</td>
                    <td align="right" style="font-size:17px;font-weight:800;color:#15803d;">RM {$amount}</td>
                  </tr>
                </table>
              </td>
            </tr>
            <!-- Status row -->
            <tr>
              <td style="background-color:#fffbeb;border-top:1px solid #fde68a;padding:10px 20px;text-align:center;">
                <span style="background-color:#fefce8;color:#c77712;padding:5px 16px;border-radius:100px;font-size:12px;font-weight:700;border:1px solid #fde047;">⏳ Pending Verification</span>
              </td>
            </tr>
          </table>

          <!-- Staff verification info box -->
          <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#faeeda;border:1px solid #f5d78f;border-radius:10px;margin-bottom:20px;">
            <tr>
              <td style="padding:18px 20px;">
                <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#92400e;">⏳ What happens next?</p>
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td style="padding:3px 0;font-size:13px;color:#a16207;">📋&nbsp;&nbsp;Our staff will review your payment proof within <strong>24 hours</strong>.</td>
                  </tr>
                  <tr>
                    <td style="padding:3px 0;font-size:13px;color:#a16207;">✅&nbsp;&nbsp;You will receive a <strong>confirmation email</strong> once your payment is verified.</td>
                  </tr>
                  <tr>
                    <td style="padding:3px 0;font-size:13px;color:#a16207;">📞&nbsp;&nbsp;Questions? Call us at <strong>{$clinicPhone}</strong>.</td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>

          <!-- Appointment reminder box -->
          <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#faf8ff;border:1px solid #ede8f8;border-radius:10px;margin-bottom:24px;">
            <tr>
              <td style="padding:18px 20px;">
                <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#3b0764;">📅 Your Appointment</p>
                <p style="margin:0 0 3px;font-size:14px;color:#1a0e2e;font-weight:600;">{$date} at <span style="color:#7c3aed;">{$time}</span></p>
                <p style="margin:4px 0 0;font-size:13px;color:#72647a;">Please arrive <strong>10 minutes early</strong> for check-in.</p>
              </td>
            </tr>
          </table>

          <!-- CTA button -->
          <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:8px;">
            <tr>
              <td align="center">
                <a href="{$clinicUrl}"
                   style="display:inline-block;background-color:#7c3aed;color:#ffffff;padding:14px 36px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:700;letter-spacing:0.02em;">
                  View Payment Status →
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


/**
 * buildPaymentVerifiedEmail — Sent when staff verifies a payment.
 *
 * @param array{
 *   patientName: string,
 *   paymentRef: string,
 *   appointmentRef: string,
 *   serviceType: string,
 *   dentistName: string,
 *   date: string,
 *   time: string,
 *   amount: string,
 *   paymentMethod: string,
 *   bankName: string
 * } $data
 */
function buildPaymentVerifiedEmail(array $data): string
{
    $name       = htmlspecialchars((string) ($data['patientName']    ?? 'Patient'),     ENT_QUOTES);
    $payRef     = htmlspecialchars((string) ($data['paymentRef']     ?? ''),            ENT_QUOTES);
    $apptRef    = htmlspecialchars((string) ($data['appointmentRef'] ?? ''),            ENT_QUOTES);
    $service    = htmlspecialchars((string) ($data['serviceType']    ?? ''),            ENT_QUOTES);
    $dentist    = htmlspecialchars((string) ($data['dentistName']    ?? 'Dental Team'), ENT_QUOTES);
    $date       = htmlspecialchars((string) ($data['date']           ?? ''),            ENT_QUOTES);
    $time       = htmlspecialchars((string) ($data['time']           ?? ''),            ENT_QUOTES);
    $amount     = htmlspecialchars((string) ($data['amount']         ?? ''),            ENT_QUOTES);
    $method     = (string) ($data['paymentMethod'] ?? '');
    $bankName   = htmlspecialchars((string) ($data['bankName']       ?? ''),            ENT_QUOTES);

    $methodLabel = match ($method) {
        'tng_qr' => "Touch 'n Go QR",
        'fpx'    => 'FPX Online Banking',
        default  => htmlspecialchars($method, ENT_QUOTES),
    };

    $clinicName  = CLINIC_NAME;
    $clinicAddr  = CLINIC_ADDRESS;
    $clinicPhone = CLINIC_PHONE;
    $clinicUrl   = CLINIC_URL;

    $bankRow = $bankName !== '' ? <<<ROW
                  <tr>
                    <td style="color:#72647a;padding:7px 0;width:170px;border-bottom:1px solid #f0fdf4;">Bank</td>
                    <td style="font-weight:600;color:#1a0e2e;padding:7px 0;border-bottom:1px solid #f0fdf4;">{$bankName}</td>
                  </tr>
ROW : '';

    $body = <<<HTML

    <!-- Payment verified banner -->
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0fdf4;border-bottom:3px solid #86efac;">
      <tr>
        <td style="padding:24px 36px;text-align:center;">
          <div style="font-size:40px;line-height:1;">✅</div>
          <p style="margin:8px 0 4px;font-size:22px;font-weight:800;color:#15803d;letter-spacing:-0.3px;">Payment Verified!</p>
          <p style="margin:0;font-size:13px;color:#166534;">Your payment has been confirmed by our clinic staff.</p>
        </td>
      </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0">
      <tr>
        <td style="padding:32px 36px 0;">

          <!-- Greeting -->
          <p style="margin:0 0 6px;font-size:21px;font-weight:800;color:#1a0e2e;">Hi {$name}! 🎉</p>
          <p style="margin:0 0 28px;font-size:14px;color:#4b3a6e;line-height:1.7;">
            Your payment to <strong>{$clinicName}</strong> has been reviewed and
            <strong style="color:#15803d;">verified by our staff</strong>. You are all set for your upcoming appointment!
          </p>

          <!-- Payment summary box -->
          <table width="100%" cellpadding="0" cellspacing="0" style="border:2px solid #22c55e;border-radius:12px;overflow:hidden;margin-bottom:24px;">
            <tr>
              <td style="background-color:#15803d;padding:14px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td>
                      <span style="font-size:13px;font-weight:700;color:#bbf7d0;text-transform:uppercase;letter-spacing:0.08em;">✅ Payment Confirmed</span>
                    </td>
                    <td align="right">
                      <span style="font-size:14px;font-weight:800;color:#ffffff;">{$payRef}</span>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td style="padding:20px 20px 0;">
                <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13.5px;border-collapse:collapse;">
                  <tr>
                    <td style="color:#72647a;padding:7px 0;width:170px;border-bottom:1px solid #f0fdf4;">Appointment Ref</td>
                    <td style="font-weight:700;color:#15803d;padding:7px 0;border-bottom:1px solid #f0fdf4;">{$apptRef}</td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:7px 0;border-bottom:1px solid #f0fdf4;">Service</td>
                    <td style="font-weight:600;color:#1a0e2e;padding:7px 0;border-bottom:1px solid #f0fdf4;">{$service}</td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:7px 0;border-bottom:1px solid #f0fdf4;">Dentist</td>
                    <td style="font-weight:600;color:#1a0e2e;padding:7px 0;border-bottom:1px solid #f0fdf4;">{$dentist}</td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:7px 0;border-bottom:1px solid #f0fdf4;">Date</td>
                    <td style="font-weight:600;color:#1a0e2e;padding:7px 0;border-bottom:1px solid #f0fdf4;">{$date}</td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:7px 0;border-bottom:1px solid #f0fdf4;">Time</td>
                    <td style="padding:7px 0;border-bottom:1px solid #f0fdf4;">
                      <strong style="color:#7c3aed;font-size:15px;">{$time}</strong>
                    </td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:7px 0;border-bottom:1px solid #f0fdf4;">Payment Method</td>
                    <td style="font-weight:600;color:#1a0e2e;padding:7px 0;border-bottom:1px solid #f0fdf4;">{$methodLabel}</td>
                  </tr>
                  {$bankRow}
                  <tr>
                    <td style="color:#72647a;padding:7px 0;">Status</td>
                    <td style="padding:7px 0;">
                      <span style="background-color:#f0fdf4;color:#15803d;padding:4px 12px;border-radius:100px;font-size:11.5px;font-weight:700;">✅ Verified</span>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <!-- Amount row -->
            <tr>
              <td style="background-color:#f0fdf4;border-top:2px solid #bbf7d0;padding:14px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td style="font-size:13px;font-weight:700;color:#065f46;">Total Paid</td>
                    <td align="right" style="font-size:17px;font-weight:800;color:#15803d;">RM {$amount}</td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>

          <!-- All set box -->
          <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#faf8ff;border:1px solid #ede8f8;border-radius:10px;margin-bottom:20px;">
            <tr>
              <td style="padding:18px 20px;">
                <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#3b0764;">📅 You are all set!</p>
                <p style="margin:0 0 3px;font-size:14px;color:#1a0e2e;font-weight:600;">
                  Your appointment is on <strong>{$date}</strong> at <strong style="color:#7c3aed;">{$time}</strong>.
                </p>
                <p style="margin:6px 0 0;font-size:13px;color:#72647a;">Please arrive <strong>10 minutes early</strong> and bring your IC / MyKad for check-in.</p>
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
