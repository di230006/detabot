<?php
declare(strict_types=1);

/**
 * buildInvoiceEmail — Sent when staff generates and sends an invoice.
 */
function buildInvoiceEmail(array $invoiceData, array $apptData): string
{
    $name       = htmlspecialchars((string) ($apptData['patientName'] ?? 'Patient'), ENT_QUOTES);
    $dentist    = htmlspecialchars((string) ($apptData['dentistName'] ?? 'Dental Team'), ENT_QUOTES);
    $date       = htmlspecialchars((string) ($apptData['date'] ?? ''), ENT_QUOTES);
    $time       = htmlspecialchars((string) ($apptData['time'] ?? ''), ENT_QUOTES);

    $invoiceNo   = htmlspecialchars((string) ($invoiceData['invoiceNo'] ?? ''), ENT_QUOTES);
    $apptRef     = htmlspecialchars((string) ($invoiceData['appointmentRef'] ?? ''), ENT_QUOTES);
    $invoiceDate = htmlspecialchars((string) ($invoiceData['invoiceDate'] ?? ''), ENT_QUOTES);
    $baseService = htmlspecialchars((string) ($invoiceData['baseService'] ?? ''), ENT_QUOTES);
    $baseAmount  = htmlspecialchars((string) ($invoiceData['baseAmount'] ?? '0.00'), ENT_QUOTES);
    $subtotal    = htmlspecialchars((string) ($invoiceData['subtotal'] ?? '0.00'), ENT_QUOTES);
    $discount    = (float) ($invoiceData['discount'] ?? 0);
    $discReason  = htmlspecialchars((string) ($invoiceData['discountReason'] ?? ''), ENT_QUOTES);
    $totalAmount = htmlspecialchars((string) ($invoiceData['totalAmount'] ?? '0.00'), ENT_QUOTES);
    $notes       = (string) ($invoiceData['notes'] ?? '');
    $items       = (array) ($invoiceData['additionalItems'] ?? []);

    $clinicName  = CLINIC_NAME;
    $clinicAddr  = CLINIC_ADDRESS;
    $clinicPhone = CLINIC_PHONE;
    $clinicUrl   = CLINIC_URL;
    $appUrl      = APP_URL;

    // Build items rows
    $itemsHtml = '<tr>
        <td style="padding:8px 12px;border-bottom:1px solid #eff6ff;font-weight:600;color:#1a0e2e;">' . $baseService . '</td>
        <td style="padding:8px 12px;border-bottom:1px solid #eff6ff;text-align:center;color:#72647a;">1</td>
        <td style="padding:8px 12px;border-bottom:1px solid #eff6ff;text-align:right;color:#72647a;">RM ' . $baseAmount . '</td>
        <td style="padding:8px 12px;border-bottom:1px solid #eff6ff;text-align:right;font-weight:600;color:#1a0e2e;">RM ' . $baseAmount . '</td>
    </tr>';

    foreach ($items as $item) {
        $iName  = htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES);
        $iQty   = (int) ($item['qty'] ?? 1);
        $iPrice = number_format((float) ($item['price'] ?? 0), 2);
        $iTotal = number_format((float) ($item['total'] ?? 0), 2);
        $itemsHtml .= '<tr>
            <td style="padding:8px 12px;border-bottom:1px solid #eff6ff;font-weight:600;color:#1a0e2e;">' . $iName . '</td>
            <td style="padding:8px 12px;border-bottom:1px solid #eff6ff;text-align:center;color:#72647a;">' . $iQty . '</td>
            <td style="padding:8px 12px;border-bottom:1px solid #eff6ff;text-align:right;color:#72647a;">RM ' . $iPrice . '</td>
            <td style="padding:8px 12px;border-bottom:1px solid #eff6ff;text-align:right;font-weight:600;color:#1a0e2e;">RM ' . $iTotal . '</td>
        </tr>';
    }

    $discountHtml = '';
    if ($discount > 0) {
        $discFmt = number_format($discount, 2);
        $discLabel = $discReason !== '' ? "Discount ({$discReason})" : 'Discount';
        $discountHtml = '<tr>
            <td colspan="3" style="padding:6px 12px;text-align:right;font-size:13px;color:#72647a;">' . $discLabel . '</td>
            <td style="padding:6px 12px;text-align:right;font-size:13px;color:#dc2626;font-weight:600;">- RM ' . $discFmt . '</td>
        </tr>';
    }

    $notesHtml = '';
    if ($notes !== '') {
        $escapedNotes = nl2br(htmlspecialchars($notes, ENT_QUOTES));
        $notesHtml = <<<NOTES

          <!-- Notes -->
          <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#faf8ff;border:1px solid #ede8f8;border-radius:10px;margin-bottom:24px;">
            <tr>
              <td style="padding:18px 20px;">
                <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#3b0764;">📝 Notes from your dentist</p>
                <p style="margin:0;font-size:13px;color:#72647a;line-height:1.6;">{$escapedNotes}</p>
              </td>
            </tr>
          </table>
NOTES;
    }

    $body = <<<HTML

    <!-- Invoice banner -->
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#eff6ff;border-bottom:3px solid #93c5fd;">
      <tr>
        <td style="padding:24px 36px;text-align:center;">
          <div style="font-size:40px;line-height:1;">🧾</div>
          <p style="margin:8px 0 4px;font-size:22px;font-weight:800;color:#1e40af;letter-spacing:-0.3px;">Your Invoice is Ready</p>
          <p style="margin:0;font-size:13px;color:#1d4ed8;">Please review the details and settle payment at your convenience.</p>
        </td>
      </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0">
      <tr>
        <td style="padding:32px 36px 0;">

          <!-- Greeting -->
          <p style="margin:0 0 6px;font-size:21px;font-weight:800;color:#1a0e2e;">Hi {$name}! 😊</p>
          <p style="margin:0 0 28px;font-size:14px;color:#4b3a6e;line-height:1.7;">
            Thank you for visiting <strong>{$clinicName}</strong>. Please find your invoice below.
            You can pay anytime through the Detabot system or at the clinic counter.
          </p>

          <!-- Invoice box -->
          <table width="100%" cellpadding="0" cellspacing="0" style="border:2px solid #3b82f6;border-radius:12px;overflow:hidden;margin-bottom:24px;">
            <tr>
              <td style="background-color:#1e40af;padding:14px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td>
                      <span style="font-size:13px;font-weight:700;color:#bfdbfe;text-transform:uppercase;letter-spacing:0.08em;">🧾 Invoice</span>
                    </td>
                    <td align="right">
                      <span style="font-size:14px;font-weight:800;color:#ffffff;">{$invoiceNo}</span>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td style="padding:16px 20px 8px;">
                <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;border-collapse:collapse;">
                  <tr>
                    <td style="color:#72647a;padding:4px 0;width:130px;">Appointment Ref</td>
                    <td style="font-weight:600;color:#1e40af;padding:4px 0;">{$apptRef}</td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:4px 0;">Date</td>
                    <td style="font-weight:600;color:#1a0e2e;padding:4px 0;">{$invoiceDate}</td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:4px 0;">Patient</td>
                    <td style="font-weight:600;color:#1a0e2e;padding:4px 0;">{$name}</td>
                  </tr>
                  <tr>
                    <td style="color:#72647a;padding:4px 0;">Dentist</td>
                    <td style="font-weight:600;color:#1a0e2e;padding:4px 0;">{$dentist}</td>
                  </tr>
                </table>
              </td>
            </tr>
            <!-- Items table -->
            <tr>
              <td style="padding:8px 20px 0;">
                <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;border-collapse:collapse;border-top:2px solid #dbeafe;">
                  <tr style="background-color:#eff6ff;">
                    <th style="padding:10px 12px;text-align:left;font-weight:700;color:#1e40af;font-size:11.5px;text-transform:uppercase;">Item</th>
                    <th style="padding:10px 12px;text-align:center;font-weight:700;color:#1e40af;font-size:11.5px;text-transform:uppercase;">Qty</th>
                    <th style="padding:10px 12px;text-align:right;font-weight:700;color:#1e40af;font-size:11.5px;text-transform:uppercase;">Unit Price</th>
                    <th style="padding:10px 12px;text-align:right;font-weight:700;color:#1e40af;font-size:11.5px;text-transform:uppercase;">Amount</th>
                  </tr>
                  {$itemsHtml}
                  <tr>
                    <td colspan="3" style="padding:8px 12px;text-align:right;font-size:13px;font-weight:600;color:#72647a;">Subtotal</td>
                    <td style="padding:8px 12px;text-align:right;font-size:13px;font-weight:600;color:#1a0e2e;">RM {$subtotal}</td>
                  </tr>
                  {$discountHtml}
                </table>
              </td>
            </tr>
            <!-- Total row -->
            <tr>
              <td style="background-color:#f0fdf4;border-top:2px solid #bbf7d0;padding:14px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td style="font-size:14px;font-weight:700;color:#065f46;">Total Amount</td>
                    <td align="right" style="font-size:20px;font-weight:800;color:#7c3aed;">RM {$totalAmount}</td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>

          <!-- Payment methods -->
          <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#faeeda;border:1px solid #f5d78f;border-radius:10px;margin-bottom:24px;">
            <tr>
              <td style="padding:18px 20px;">
                <p style="margin:0 0 14px;font-size:13px;font-weight:700;color:#92400e;">💳 Pay Now</p>
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td width="48%" style="background:#fff;border:1px solid #f5d78f;border-radius:8px;padding:14px;vertical-align:top;">
                      <p style="margin:0 0 6px;font-size:13px;font-weight:700;color:#92400e;">📱 Touch 'n Go QR</p>
                      <p style="margin:0;font-size:12px;color:#a16207;line-height:1.5;">Scan our TnG QR code in the Detabot system or at the clinic counter.</p>
                    </td>
                    <td width="4%"></td>
                    <td width="48%" style="background:#fff;border:1px solid #f5d78f;border-radius:8px;padding:14px;vertical-align:top;">
                      <p style="margin:0 0 6px;font-size:13px;font-weight:700;color:#92400e;">🏦 FPX / Online Banking</p>
                      <p style="margin:0;font-size:12px;color:#a16207;line-height:1.5;">Use reference: <strong>{$invoiceNo}</strong></p>
                    </td>
                  </tr>
                </table>
                <p style="margin:12px 0 0;font-size:12px;color:#92400e;text-align:center;">Upload your payment proof in the Detabot system after paying.</p>
              </td>
            </tr>
          </table>

          {$notesHtml}

          <!-- CTA button -->
          <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
            <tr>
              <td align="center">
                <a href="{$clinicUrl}"
                   style="display:inline-block;background-color:#7c3aed;color:#ffffff;padding:14px 36px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:700;letter-spacing:0.02em;">
                  View &amp; Pay Invoice →
                </a>
              </td>
            </tr>
          </table>

          <!-- Clinic info -->
          <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#faf8ff;border:1px solid #ede8f8;border-radius:10px;margin-bottom:8px;">
            <tr>
              <td style="padding:18px 20px;">
                <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#3b0764;">📍 {$clinicName}</p>
                <p style="margin:0 0 3px;font-size:13px;color:#72647a;">{$clinicAddr}</p>
                <p style="margin:0;font-size:13px;color:#72647a;">Tel: {$clinicPhone}</p>
              </td>
            </tr>
          </table>

        </td>
      </tr>
    </table>

HTML;

    return emailBaseHtml($body);
}
