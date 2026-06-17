<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

if (empty($_SESSION['userID'])) { header('Location: login.php'); exit; }

$actor = db_one('SELECT * FROM tbl_user WHERE userID = ? AND status = ?', [(int) $_SESSION['userID'], 'active']);
if (!$actor || !has_role($actor, ['admin', 'staff'])) {
    header('Location: dashboard.php'); exit;
}

// ── AJAX: patient search ──────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'search_patients') {
    header('Content-Type: application/json');
    $q = '%' . trim((string) ($_GET['q'] ?? '')) . '%';
    $rows = db_all(
        "SELECT userID, username, userEmail FROM tbl_user WHERE status='active' AND userRole='patient'
          AND (username LIKE ? OR userEmail LIKE ?) ORDER BY username ASC LIMIT 10",
        [$q, $q]
    );
    echo json_encode($rows);
    exit;
}

// ── AJAX: patient appointments ────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_appointments') {
    header('Content-Type: application/json');
    $uid = (int) ($_GET['userID'] ?? 0);
    $rows = db_all(
        "SELECT appointmentID, appointmentDate, appointmentTime, serviceType, status
           FROM tbl_appointment WHERE userID = ? AND status IN ('completed','confirmed')
           ORDER BY appointmentDate DESC LIMIT 20",
        [$uid]
    );
    echo json_encode($rows);
    exit;
}

// ── POST: insert record ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $raw = json_decode((string) file_get_contents('php://input'), true) ?? [];

    $patientID    = (int) ($raw['userID']        ?? 0);
    $appointmentID = ($raw['appointmentID'] !== '' && $raw['appointmentID'] !== null)
        ? (int) $raw['appointmentID'] : null;
    $toothNumber  = trim((string) ($raw['toothNumber']   ?? ''));
    $diagnosis    = trim((string) ($raw['diagnosis']     ?? ''));
    $treatment    = trim((string) ($raw['treatmentDone'] ?? ''));
    $condition    = trim((string) ($raw['toothCondition']?? 'good'));
    $nextAction   = trim((string) ($raw['nextAction']    ?? ''));
    $notes        = trim((string) ($raw['dentistNotes']  ?? ''));
    $recordedBy   = (int) $_SESSION['userID'];

    $allowed = ['good','monitor','needs_treatment','extracted'];
    if (!$patientID || $diagnosis === '' || $treatment === '' || !in_array($condition, $allowed, true)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
        exit;
    }

    db_execute(
        'INSERT INTO tbl_dental_record
            (userID, appointmentID, toothNumber, diagnosis, treatmentDone, toothCondition, nextAction, dentistNotes, recordedBy)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$patientID, $appointmentID, $toothNumber ?: null, $diagnosis, $treatment, $condition,
         $nextAction ?: null, $notes ?: null, $recordedBy]
    );

    log_activity('add_dental_record', 'Dental record added for userID ' . $patientID, $recordedBy);
    echo json_encode(['success' => true, 'message' => 'Dental record added successfully.']);
    exit;
}

$initials  = strtoupper(substr((string) $actor['username'], 0, 2));
$avatarUrl = user_avatar_url($actor);
$clinic    = db_one('SELECT * FROM tbl_clinic ORDER BY clinicID ASC LIMIT 1') ?? [];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Add Dental Record | Detabot</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css">
<style>
.form-card { background:#fff; border-radius:16px; padding:28px 32px; border:1px solid #ede9fe; box-shadow:0 1px 8px rgba(59,7,100,.07); max-width:780px; }
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
@media(max-width:700px){ .form-grid{ grid-template-columns:1fr; } }
.form-full { grid-column:1/-1; }
.field { display:flex; flex-direction:column; gap:5px; }
.field label { font-size:12.5px; font-weight:700; color:#374151; text-transform:uppercase; letter-spacing:.04em; }
.field input, .field select, .field textarea {
    border:1.5px solid #e2d9f3; border-radius:10px; padding:10px 14px; font-size:14px;
    font-family:'DM Sans',sans-serif; color:#1e1b4b; background:#faf8ff; outline:none;
    transition:border-color .15s;
}
.field input:focus, .field select:focus, .field textarea:focus { border-color:#8b5cf6; }
.field textarea { resize:vertical; min-height:80px; }
.search-results { border:1.5px solid #e2d9f3; border-radius:10px; background:#fff; max-height:180px; overflow-y:auto; display:none; position:absolute; width:100%; z-index:50; box-shadow:0 4px 18px rgba(59,7,100,.1); }
.search-result-item { padding:10px 14px; cursor:pointer; font-size:13.5px; border-bottom:1px solid #f5f3ff; }
.search-result-item:last-child { border-bottom:none; }
.search-result-item:hover { background:#f5f3ff; }
.search-result-name { font-weight:700; color:#1e1b4b; }
.search-result-email { font-size:12px; color:#9ca3af; }
.patient-selected { display:none; background:#f5f3ff; border:1.5px solid #c4b5fd; border-radius:10px; padding:10px 14px; margin-top:4px; font-size:13.5px; font-weight:600; color:#6d28d9; }
.submit-btn { background:linear-gradient(135deg,#c84fce,#8b5cf6); color:#fff; border:none; border-radius:12px; padding:13px 32px; font-size:15px; font-weight:700; cursor:pointer; transition:opacity .15s; font-family:'Sora',sans-serif; }
.submit-btn:hover { opacity:.88; }
.submit-btn:disabled { opacity:.5; cursor:not-allowed; }
.flash-success { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; border-radius:10px; padding:12px 16px; font-size:14px; font-weight:600; margin-bottom:18px; display:none; }
.flash-error   { background:#fee2e2; color:#dc2626; border:1px solid #fca5a5; border-radius:10px; padding:12px 16px; font-size:14px; font-weight:600; margin-bottom:18px; display:none; }
</style>
</head>
<body>
<div class="app-shell">

<aside class="sidebar">
    <div class="sb-brand">
        <a class="sb-logo-link" href="dashboard.php">
            <div class="sb-logo-box"><img src="assets/detabot-logo.svg" alt="Detabot"></div>
            <div class="sb-brand-text">
                <strong class="sb-brand-name">Detabot</strong>
                <small class="sb-brand-sub">Putra Dental Clinic</small>
            </div>
        </a>
    </div>
    <div class="sb-clinic-card">
        <img class="sb-clinic-logo" src="assets/clinic-logo.png" alt="Putra Dental Clinic">
        <div>
            <span class="sb-clinic-name"><?= e((string) ($clinic['clinicName'] ?? 'Putra Dental Clinic')) ?></span>
            <span class="sb-clinic-loc">Parit Raja, Johor</span>
        </div>
    </div>
    <nav class="sb-nav">
        <div class="sb-nav-group">
            <span class="sb-nav-label">Main</span>
            <a class="sb-nav-item" href="dashboard.php">
                <span class="sb-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>
                <span class="sb-nav-text">Dashboard</span>
            </a>
            <a class="sb-nav-item active" href="add_dental_record.php">
                <span class="sb-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg></span>
                <span class="sb-nav-text">Add Dental Record</span>
            </a>
        </div>
    </nav>
    <div class="sb-user">
        <div class="sb-user-avatar">
            <?php if ($avatarUrl): ?><img src="<?= e($avatarUrl) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;"><?php else: ?><?= e($initials) ?><?php endif; ?>
        </div>
        <div class="sb-user-info">
            <span class="sb-user-name"><?= e((string) $actor['username']) ?></span>
            <span class="sb-user-role"><?= e(ucfirst((string) $actor['userRole'])) ?></span>
        </div>
        <a class="sb-logout-btn" href="logout.php" title="Logout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </a>
    </div>
</aside>

<main class="main-panel">
    <header class="topbar">
        <div>
            <p class="eyebrow"><?= e(strtoupper((string) $actor['userRole'])) ?></p>
            <h1>Add Dental Record</h1>
        </div>
        <div class="user-menu">
            <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#c84fce,#8b5cf6);display:flex;align-items:center;justify-content:center;overflow:hidden;font-family:'Sora',sans-serif;font-weight:800;color:#fff;font-size:13px;flex-shrink:0;">
                <?php if ($avatarUrl): ?><img src="<?= e($avatarUrl) ?>" alt="" style="width:100%;height:100%;object-fit:cover;"><?php else: ?><?= e($initials) ?><?php endif; ?>
            </div>
            <span class="topbar-username"><?= e((string) $actor['username']) ?></span>
            <a href="logout.php" class="btn ghost">Logout</a>
        </div>
    </header>

    <div style="padding:24px 28px 48px;">
        <div class="flash-success" id="flashSuccess"></div>
        <div class="flash-error"   id="flashError"></div>

        <div class="form-card">
            <h2 style="font-family:'Sora',sans-serif;font-size:17px;font-weight:800;color:#1e1b4b;margin:0 0 22px;">New Dental Record</h2>

            <div class="form-grid">

                <!-- Patient search -->
                <div class="field form-full" style="position:relative;">
                    <label>Patient *</label>
                    <input id="patientSearch" type="text" placeholder="Search by name or email…" autocomplete="off">
                    <div class="search-results" id="searchResults"></div>
                    <input type="hidden" id="patientID" name="userID">
                    <div class="patient-selected" id="patientSelected">
                        <span id="patientSelectedName"></span>
                        <button type="button" onclick="clearPatient()" style="margin-left:10px;background:none;border:none;cursor:pointer;color:#dc2626;font-weight:700;">✕</button>
                    </div>
                </div>

                <!-- Linked appointment -->
                <div class="field form-full">
                    <label>Linked Appointment <span style="font-weight:400;color:#9ca3af;">(optional)</span></label>
                    <select id="appointmentID" name="appointmentID" disabled>
                        <option value="">— Select patient first —</option>
                    </select>
                </div>

                <!-- Tooth number -->
                <div class="field">
                    <label>Tooth Number <span style="font-weight:400;color:#9ca3af;">(optional)</span></label>
                    <select id="toothNumber" name="toothNumber">
                        <option value="">All / General</option>
                        <option value="Upper Jaw">Upper Jaw</option>
                        <option value="Lower Jaw">Lower Jaw</option>
                        <?php for ($i = 1; $i <= 32; $i++): ?>
                            <option value="<?= $i ?>">Tooth <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <!-- Condition after treatment -->
                <div class="field">
                    <label>Tooth Condition After Treatment *</label>
                    <select id="toothCondition" name="toothCondition">
                        <option value="good">Good</option>
                        <option value="monitor">Monitor</option>
                        <option value="needs_treatment">Needs Treatment</option>
                        <option value="extracted">Extracted</option>
                    </select>
                </div>

                <!-- Diagnosis -->
                <div class="field form-full">
                    <label>Diagnosis *</label>
                    <textarea id="diagnosis" name="diagnosis" placeholder="Describe the diagnosis…"></textarea>
                </div>

                <!-- Treatment done -->
                <div class="field form-full">
                    <label>Treatment Done *</label>
                    <textarea id="treatmentDone" name="treatmentDone" placeholder="Describe the treatment performed…"></textarea>
                </div>

                <!-- Next action -->
                <div class="field form-full">
                    <label>Next Recommended Action <span style="font-weight:400;color:#9ca3af;">(optional)</span></label>
                    <textarea id="nextAction" name="nextAction" style="min-height:60px;" placeholder="e.g. Schedule follow-up in 3 months…"></textarea>
                </div>

                <!-- Dentist notes -->
                <div class="field form-full">
                    <label>Dentist Notes <span style="font-weight:400;color:#9ca3af;">(private — not shown to patient)</span></label>
                    <textarea id="dentistNotes" name="dentistNotes" placeholder="Internal notes…"></textarea>
                </div>

            </div>

            <div style="margin-top:24px;">
                <button class="submit-btn" id="submitBtn" onclick="submitRecord()">Save Dental Record</button>
            </div>
        </div>
    </div>
</main>
</div>

<script>
var searchTimeout;

/* Patient search */
document.getElementById('patientSearch').addEventListener('input', function () {
    clearTimeout(searchTimeout);
    var q = this.value.trim();
    if (q.length < 2) { document.getElementById('searchResults').style.display = 'none'; return; }
    searchTimeout = setTimeout(function () {
        fetch('add_dental_record.php?action=search_patients&q=' + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(function (rows) {
                var box = document.getElementById('searchResults');
                if (!rows.length) { box.style.display = 'none'; return; }
                box.innerHTML = rows.map(function (p) {
                    return '<div class="search-result-item" onclick="selectPatient(' + p.userID + ',\'' +
                        p.username.replace(/'/g, "\\'") + '\',\'' + p.userEmail.replace(/'/g, "\\'") + '\')">'
                        + '<div class="search-result-name">' + p.username + '</div>'
                        + '<div class="search-result-email">' + p.userEmail + '</div></div>';
                }).join('');
                box.style.display = 'block';
            });
    }, 280);
});

function selectPatient(id, name, email) {
    document.getElementById('patientID').value = id;
    document.getElementById('patientSearch').value = '';
    document.getElementById('searchResults').style.display = 'none';
    document.getElementById('patientSelectedName').textContent = name + ' (' + email + ')';
    document.getElementById('patientSelected').style.display = 'block';
    loadAppointments(id);
}

function clearPatient() {
    document.getElementById('patientID').value = '';
    document.getElementById('patientSelected').style.display = 'none';
    document.getElementById('patientSelectedName').textContent = '';
    var sel = document.getElementById('appointmentID');
    sel.innerHTML = '<option value="">— Select patient first —</option>';
    sel.disabled = true;
}

function loadAppointments(uid) {
    fetch('add_dental_record.php?action=get_appointments&userID=' + uid)
        .then(function (r) { return r.json(); })
        .then(function (rows) {
            var sel = document.getElementById('appointmentID');
            sel.innerHTML = '<option value="">— None —</option>';
            rows.forEach(function (a) {
                sel.innerHTML += '<option value="' + a.appointmentID + '">'
                    + a.appointmentDate + ' ' + a.appointmentTime.substring(0,5)
                    + ' — ' + a.serviceType + ' (' + a.status + ')</option>';
            });
            sel.disabled = false;
        });
}

function submitRecord() {
    var btn  = document.getElementById('submitBtn');
    var ok   = document.getElementById('flashSuccess');
    var err  = document.getElementById('flashError');
    ok.style.display = 'none'; err.style.display = 'none';

    var uid  = document.getElementById('patientID').value;
    var diag = document.getElementById('diagnosis').value.trim();
    var trt  = document.getElementById('treatmentDone').value.trim();

    if (!uid) { err.textContent = 'Please select a patient.'; err.style.display = 'block'; return; }
    if (!diag) { err.textContent = 'Diagnosis is required.'; err.style.display = 'block'; return; }
    if (!trt)  { err.textContent = 'Treatment done is required.'; err.style.display = 'block'; return; }

    btn.disabled = true; btn.textContent = 'Saving…';

    var apptVal = document.getElementById('appointmentID').value;
    fetch('add_dental_record.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            userID:        parseInt(uid),
            appointmentID: apptVal || null,
            toothNumber:   document.getElementById('toothNumber').value,
            diagnosis:     diag,
            treatmentDone: trt,
            toothCondition:document.getElementById('toothCondition').value,
            nextAction:    document.getElementById('nextAction').value.trim(),
            dentistNotes:  document.getElementById('dentistNotes').value.trim(),
        }),
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        btn.disabled = false; btn.textContent = 'Save Dental Record';
        if (data.success) {
            ok.textContent = '✓ ' + data.message;
            ok.style.display = 'block';
            /* reset form */
            clearPatient();
            ['diagnosis','treatmentDone','nextAction','dentistNotes'].forEach(function (id) {
                document.getElementById(id).value = '';
            });
            document.getElementById('toothNumber').value = '';
            document.getElementById('toothCondition').value = 'good';
        } else {
            err.textContent = data.message;
            err.style.display = 'block';
        }
    })
    .catch(function () {
        btn.disabled = false; btn.textContent = 'Save Dental Record';
        err.textContent = 'Network error. Please try again.';
        err.style.display = 'block';
    });
}

/* Close search on outside click */
document.addEventListener('click', function (e) {
    if (!e.target.closest('#patientSearch') && !e.target.closest('#searchResults')) {
        document.getElementById('searchResults').style.display = 'none';
    }
});
</script>
</body>
</html>
