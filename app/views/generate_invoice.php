<?php
declare(strict_types=1);

function page_generate_invoice(array $user): void
{
    $apptID = (int) ($_GET['appointmentID'] ?? 0);
    if ($apptID <= 0) {
        echo '<div class="panel" style="padding:40px;text-align:center;color:#b42318">Invalid appointment ID.</div>';
        return;
    }

    $appt = db_one(
        "SELECT a.*, u.username AS patientName, u.userAge, u.userEmail, u.userPhone,
                u.userChronicHealthProblems, u.userGender
         FROM tbl_appointment a
         JOIN tbl_user u ON u.userID = a.userID
         WHERE a.appointmentID = ?",
        [$apptID]
    );

    if (!$appt) {
        echo '<div class="panel" style="padding:40px;text-align:center;color:#b42318">Appointment not found.</div>';
        return;
    }
    if ((string) $appt['status'] !== 'completed') {
        echo '<div class="panel" style="padding:40px;text-align:center;color:#b42318">Invoice can only be generated for completed appointments.</div>';
        return;
    }

    $existing = db_one('SELECT invoiceNo FROM tbl_invoice WHERE appointmentID = ?', [$apptID]);
    if ($existing) {
        echo '<div class="panel" style="padding:40px;text-align:center;color:#c77712">An invoice (<strong>' . htmlspecialchars((string) $existing['invoiceNo'], ENT_QUOTES) . '</strong>) has already been generated for this appointment.</div>';
        return;
    }

    $service   = (string) ($appt['serviceType'] ?? '');
    $dentist   = extract_dentist_name((string) ($appt['notes'] ?? ''));
    $basePrice = service_price_min($service);
    $dateStr   = date('l, d F Y', strtotime((string) ($appt['appointmentDate'] ?? '')));
    $timeStr   = date('g:i A', strtotime('1970-01-01 ' . substr((string) ($appt['appointmentTime'] ?? ''), 0, 8)));
    $duration  = (int) ($appt['duration'] ?? 30);
    $patName   = htmlspecialchars((string) ($appt['patientName'] ?? ''), ENT_QUOTES);
    $patAge    = (int) ($appt['userAge'] ?? 0);
    $patGender = htmlspecialchars((string) ($appt['userGender'] ?? ''), ENT_QUOTES);
    $patHealth = htmlspecialchars((string) ($appt['userChronicHealthProblems'] ?? 'None'), ENT_QUOTES);
    $csrf      = csrf_token();
    ?>

<style>
.inv-page{max-width:840px;margin:0 auto}
.inv-section{background:#fff;border-radius:12px;border:1px solid #ede8f8;padding:24px;margin-bottom:16px;box-shadow:0 2px 8px rgba(59,7,100,.05)}
.inv-section-title{font-family:'Sora',sans-serif;font-size:16px;font-weight:700;color:#3b0764;margin:0 0 16px;display:flex;align-items:center;gap:8px}
.inv-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
@media(max-width:700px){.inv-grid{grid-template-columns:1fr}}
.inv-field label{font-size:11.5px;font-weight:600;color:#72647a;text-transform:uppercase;letter-spacing:.04em}
.inv-field p{margin:4px 0 0;font-size:14px;font-weight:600;color:#1a0e2e}
.inv-tbl{width:100%;border-collapse:collapse;font-size:13.5px}
.inv-tbl th{padding:10px 12px;text-align:left;font-size:11.5px;font-weight:700;color:#7c3aed;text-transform:uppercase;background:#f9f7fe;border-bottom:2px solid #ede8f8}
.inv-tbl td{padding:10px 12px;border-bottom:1px solid #f3eeff;vertical-align:middle}
.inv-tbl .total-col{text-align:right;font-weight:600;color:#1a0e2e;min-width:90px}
.inv-tbl input[type=text],.inv-tbl input[type=number]{width:100%;padding:7px 10px;border:1.5px solid #e5ddf5;border-radius:7px;font-size:13px;font-family:'DM Sans',sans-serif;outline:none;box-sizing:border-box;color:#1a0e2e}
.inv-tbl input:focus{border-color:#7c3aed}
.inv-quick-btns{display:flex;flex-wrap:wrap;gap:6px;margin-top:12px}
.inv-quick-btn{padding:6px 14px;border-radius:20px;border:1.5px solid #e5ddf5;background:#fff;color:#72647a;font-size:12.5px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .15s}
.inv-quick-btn:hover{border-color:#7c3aed;color:#7c3aed;background:#f9f7fe}
.inv-btn-add{display:inline-flex;align-items:center;gap:5px;padding:8px 18px;border-radius:8px;border:1.5px solid #7c3aed;background:#fff;color:#7c3aed;font-size:13px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .15s;margin-top:12px}
.inv-btn-add:hover{background:#f3f0ff}
.inv-btn-rm{background:none;border:none;color:#b42318;font-size:16px;cursor:pointer;padding:4px 8px;border-radius:6px;transition:background .15s}
.inv-btn-rm:hover{background:#fcebeb}
.inv-summary{display:flex;flex-direction:column;align-items:flex-end;gap:6px;margin-top:16px}
.inv-summary-row{display:flex;align-items:center;gap:16px;font-size:14px}
.inv-summary-row label{color:#72647a;font-weight:600;min-width:130px;text-align:right}
.inv-summary-row .val{font-weight:700;color:#1a0e2e;min-width:100px;text-align:right}
.inv-summary-row.total{border-top:2px solid #ede8f8;padding-top:10px;margin-top:4px}
.inv-summary-row.total .val{font-size:20px;color:#7c3aed}
.inv-summary-row input[type=number],.inv-summary-row input[type=text]{padding:7px 10px;border:1.5px solid #e5ddf5;border-radius:7px;font-size:13px;font-family:'DM Sans',sans-serif;outline:none;color:#1a0e2e;width:120px}
.inv-summary-row input:focus{border-color:#7c3aed}
.inv-notes-area{width:100%;min-height:80px;padding:10px 14px;border:1.5px solid #e5ddf5;border-radius:8px;font-size:13px;font-family:'DM Sans',sans-serif;outline:none;color:#1a0e2e;resize:vertical;box-sizing:border-box}
.inv-notes-area:focus{border-color:#7c3aed}
.inv-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:8px}
.inv-btn-primary{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#7c3aed,#5b21b6);color:#fff;border:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .15s}
.inv-btn-primary:hover{background:linear-gradient(135deg,#6d28d9,#4c1d95);transform:translateY(-1px)}
.inv-btn-secondary{display:inline-flex;align-items:center;gap:6px;background:#fff;color:#7c3aed;border:2px solid #7c3aed;padding:11px 24px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .15s}
.inv-btn-secondary:hover{background:#f3f0ff}
.inv-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:11.5px;font-weight:700}
.inv-badge.completed{background:#eaf3de;color:#16845c}
</style>

<div class="inv-page">
    <div style="margin-bottom:16px">
        <a href="manage_appointments.php" style="color:#7c3aed;font-size:13px;font-weight:600;text-decoration:none">← Back to Appointments</a>
    </div>

    <!-- Section 1: Patient & Appointment Info -->
    <div class="inv-section">
        <div class="inv-section-title">👤 Patient & Appointment Info</div>
        <div class="inv-grid">
            <div class="inv-field"><label>Patient</label><p><?= $patName ?></p></div>
            <div class="inv-field"><label>Age / Gender</label><p><?= $patAge > 0 ? $patAge : '—' ?><?= $patGender ? " / $patGender" : '' ?></p></div>
            <div class="inv-field"><label>Health Conditions</label><p><?= $patHealth ?></p></div>
            <div class="inv-field"><label>Service Booked</label><p><?= htmlspecialchars($service, ENT_QUOTES) ?></p></div>
            <div class="inv-field"><label>Dentist</label><p><?= htmlspecialchars($dentist, ENT_QUOTES) ?></p></div>
            <div class="inv-field"><label>Date & Time</label><p><?= $dateStr ?> at <?= $timeStr ?></p></div>
            <div class="inv-field"><label>Duration</label><p><?= $duration ?> min</p></div>
            <div class="inv-field"><label>Status</label><p><span class="inv-badge completed">✅ Completed</span></p></div>
        </div>
    </div>

    <!-- Section 2: Invoice Builder -->
    <div class="inv-section">
        <div class="inv-section-title">🧾 Invoice Builder</div>
        <table class="inv-tbl" id="invItemsTbl">
            <thead>
                <tr>
                    <th style="width:40%">Item</th>
                    <th style="width:12%">Qty</th>
                    <th style="width:20%">Unit Price (RM)</th>
                    <th style="width:20%">Total (RM)</th>
                    <th style="width:8%"></th>
                </tr>
            </thead>
            <tbody>
                <tr id="invBaseRow" data-type="base">
                    <td><input type="text" id="invBaseName" value="<?= htmlspecialchars($service, ENT_QUOTES) ?>"></td>
                    <td><input type="number" id="invBaseQty" value="1" min="1" onchange="invCalcBase()" oninput="invCalcBase()"></td>
                    <td><input type="number" id="invBasePrice" value="<?= $basePrice ?>" min="0" step="0.01" onchange="invCalcBase()" oninput="invCalcBase()"></td>
                    <td class="total-col" id="invBaseTotal">RM <?= number_format($basePrice, 2) ?></td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        <div class="inv-quick-btns">
            <span style="font-size:12px;color:#72647a;font-weight:600;padding:6px 0">Quick add:</span>
            <button type="button" class="inv-quick-btn" onclick="invAddQuick('Tooth Filling',1,60)">Tooth Filling (RM60)</button>
            <button type="button" class="inv-quick-btn" onclick="invAddQuick('Scaling',1,70)">Scaling (RM70)</button>
            <button type="button" class="inv-quick-btn" onclick="invAddQuick('Extraction',1,80)">Extraction (RM80)</button>
            <button type="button" class="inv-quick-btn" onclick="invAddQuick('Antibiotic',1,15)">Antibiotic (RM15)</button>
            <button type="button" class="inv-quick-btn" onclick="invAddQuick('Pain Relief',1,10)">Pain Relief (RM10)</button>
            <button type="button" class="inv-quick-btn" onclick="invAddQuick('X-Ray',1,50)">X-Ray (RM50)</button>
        </div>
        <button type="button" class="inv-btn-add" onclick="invAddRow()">+ Add Item</button>
    </div>

    <!-- Section 3: Invoice Summary -->
    <div class="inv-section">
        <div class="inv-section-title">📊 Invoice Summary</div>
        <div class="inv-summary">
            <div class="inv-summary-row">
                <label>Subtotal:</label>
                <div class="val" id="invSubtotal">RM <?= number_format($basePrice, 2) ?></div>
            </div>
            <div class="inv-summary-row">
                <label>Discount (RM):</label>
                <input type="number" id="invDiscount" value="0" min="0" step="0.01" onchange="invCalcTotal()" oninput="invCalcTotal()">
            </div>
            <div class="inv-summary-row">
                <label>Discount Reason:</label>
                <input type="text" id="invDiscountReason" placeholder="e.g. Loyalty discount" style="width:200px">
            </div>
            <div class="inv-summary-row total">
                <label>TOTAL:</label>
                <div class="val" id="invTotal">RM <?= number_format($basePrice, 2) ?></div>
            </div>
        </div>
    </div>

    <!-- Section 4: Notes -->
    <div class="inv-section">
        <div class="inv-section-title">📝 Notes</div>
        <textarea class="inv-notes-area" id="invNotes" placeholder="Additional notes for the patient (optional)..."></textarea>
    </div>

    <!-- Section 5: Actions -->
    <div class="inv-section">
        <div class="inv-actions">
            <button type="button" class="inv-btn-secondary" onclick="invSave('draft')" id="invBtnDraft">💾 Save Draft</button>
            <button type="button" class="inv-btn-primary" onclick="invSave('send')" id="invBtnSend">📧 Generate & Send Invoice</button>
        </div>
    </div>
</div>

<script>
var invRowCount = 0;

function invCalcBase() {
    var qty   = parseInt(document.getElementById('invBaseQty').value) || 1;
    var price = parseFloat(document.getElementById('invBasePrice').value) || 0;
    document.getElementById('invBaseTotal').textContent = 'RM ' + (qty * price).toFixed(2);
    invCalcTotal();
}

function invAddRow(name, qty, price) {
    invRowCount++;
    var id = invRowCount;
    var n = name || '', q = qty || 1, p = price || 0;
    var tr = document.createElement('tr');
    tr.id = 'invRow-' + id;
    tr.dataset.type = 'additional';
    tr.innerHTML =
        '<td><input type="text" value="' + n + '" placeholder="Item name" onchange="invCalcTotal()"></td>' +
        '<td><input type="number" value="' + q + '" min="1" onchange="invCalcRow(' + id + ')" oninput="invCalcRow(' + id + ')"></td>' +
        '<td><input type="number" value="' + p + '" min="0" step="0.01" onchange="invCalcRow(' + id + ')" oninput="invCalcRow(' + id + ')"></td>' +
        '<td class="total-col" id="invRowTotal-' + id + '">RM ' + (q * p).toFixed(2) + '</td>' +
        '<td><button type="button" class="inv-btn-rm" onclick="invRemoveRow(' + id + ')" title="Remove">✕</button></td>';
    document.getElementById('invItemsTbl').querySelector('tbody').appendChild(tr);
    invCalcTotal();
}

function invAddQuick(name, qty, price) { invAddRow(name, qty, price); }

function invCalcRow(id) {
    var row = document.getElementById('invRow-' + id);
    if (!row) return;
    var inputs = row.querySelectorAll('input[type=number]');
    var q = parseInt(inputs[0].value) || 1;
    var p = parseFloat(inputs[1].value) || 0;
    document.getElementById('invRowTotal-' + id).textContent = 'RM ' + (q * p).toFixed(2);
    invCalcTotal();
}

function invRemoveRow(id) {
    var row = document.getElementById('invRow-' + id);
    if (row) row.remove();
    invCalcTotal();
}

function invCalcTotal() {
    var baseQty   = parseInt(document.getElementById('invBaseQty').value) || 1;
    var basePrice = parseFloat(document.getElementById('invBasePrice').value) || 0;
    var subtotal  = baseQty * basePrice;

    var rows = document.querySelectorAll('#invItemsTbl tbody tr[data-type=additional]');
    rows.forEach(function (r) {
        var inputs = r.querySelectorAll('input[type=number]');
        var q = parseInt(inputs[0].value) || 0;
        var p = parseFloat(inputs[1].value) || 0;
        subtotal += q * p;
    });

    var discount = parseFloat(document.getElementById('invDiscount').value) || 0;
    var total = Math.max(0, subtotal - discount);

    document.getElementById('invSubtotal').textContent = 'RM ' + subtotal.toFixed(2);
    document.getElementById('invTotal').textContent    = 'RM ' + total.toFixed(2);
}

function invSave(action) {
    var baseName  = document.getElementById('invBaseName').value.trim();
    var baseQty   = parseInt(document.getElementById('invBaseQty').value) || 1;
    var basePrice = parseFloat(document.getElementById('invBasePrice').value) || 0;
    var baseAmount = baseQty * basePrice;

    if (!baseName || baseAmount <= 0) {
        alert('Base service name and amount are required.');
        return;
    }

    var additionalItems = [];
    var rows = document.querySelectorAll('#invItemsTbl tbody tr[data-type=additional]');
    rows.forEach(function (r) {
        var nameInp = r.querySelector('input[type=text]');
        var numInps = r.querySelectorAll('input[type=number]');
        var n = nameInp.value.trim();
        var q = parseInt(numInps[0].value) || 1;
        var p = parseFloat(numInps[1].value) || 0;
        if (n && p > 0) {
            additionalItems.push({ name: n, qty: q, price: p, total: q * p });
        }
    });

    var discount       = parseFloat(document.getElementById('invDiscount').value) || 0;
    var discountReason = document.getElementById('invDiscountReason').value.trim();
    var notes          = document.getElementById('invNotes').value.trim();

    var subtotal = baseAmount;
    additionalItems.forEach(function (i) { subtotal += i.total; });
    var totalAmount = Math.max(0, subtotal - discount);

    var btnSend  = document.getElementById('invBtnSend');
    var btnDraft = document.getElementById('invBtnDraft');
    btnSend.disabled = true;
    btnDraft.disabled = true;

    var fd = new FormData();
    fd.append('_csrf_token', '<?= $csrf ?>');
    fd.append('appointmentID', '<?= $apptID ?>');
    fd.append('baseService', baseName);
    fd.append('baseAmount', baseAmount.toString());
    fd.append('additionalItems', JSON.stringify(additionalItems));
    fd.append('discount', discount.toString());
    fd.append('discountReason', discountReason);
    fd.append('totalAmount', totalAmount.toString());
    fd.append('notes', notes);
    fd.append('action', action);

    fetch('save_invoice.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                alert((action === 'send' ? 'Invoice sent! ' : 'Draft saved! ') + data.invoiceNo);
                window.location.href = 'manage_appointments.php';
            } else {
                alert(data.error || 'Failed to save invoice.');
                btnSend.disabled = false;
                btnDraft.disabled = false;
            }
        })
        .catch(function () {
            alert('Network error. Please try again.');
            btnSend.disabled = false;
            btnDraft.disabled = false;
        });
}
</script>

<?php
}
