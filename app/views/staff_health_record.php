<?php
declare(strict_types=1);

function page_staff_health_record(array $user): void
{
    // Support ?patientID=X (new) and ?patient=X (legacy)
    $preselectedID = (int) ($_GET['patientID'] ?? $_GET['patient'] ?? 0);

    $allPatients = db_all(
        "SELECT u.userID, u.username, u.userEmail, u.userAge,
                COUNT(DISTINCT a.appointmentID) AS visitCount
         FROM tbl_user u
         LEFT JOIN tbl_appointment a ON a.userID = u.userID
         WHERE u.userRole = 'patient' AND u.status = 'active'
         GROUP BY u.userID
         ORDER BY u.username ASC",
        []
    );
    ?>
<style>
/* ── Staff Health Record (shr-) ─────────────────────────────── */
.shr-layout{display:grid;grid-template-columns:268px 1fr;gap:20px;align-items:flex-start}
@media(max-width:920px){.shr-layout{grid-template-columns:1fr}}

/* Patient selector panel */
.shr-selector{background:#fff;border:1px solid #ede8f8;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(59,7,100,.05);position:sticky;top:20px}
.shr-selector-hd{padding:14px 14px 10px;border-bottom:1px solid #f0ebf8}
.shr-selector-hd h2{font-family:'Sora',sans-serif;font-size:14px;font-weight:700;color:#1a0e2e;margin:0 0 10px}
.shr-search-wrap{position:relative}
.shr-search-icon{position:absolute;left:11px;top:50%;transform:translateY(-50%);pointer-events:none;width:13px;height:13px;stroke:#a78bdb}
.shr-search-input{width:100%;padding:8px 12px 8px 32px;border:1.5px solid #e5ddf5;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;color:#1a0e2e;background:#fff;outline:none;transition:border-color .18s;box-sizing:border-box}
.shr-search-input:focus{border-color:#7c3aed}
.shr-patient-list{max-height:480px;overflow-y:auto}
.shr-patient-item{display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:pointer;border-bottom:1px solid #f9f7fe;border-left:3px solid transparent;transition:background .15s}
.shr-patient-item:hover{background:#faf8ff}
.shr-patient-item.active{background:#f3f0ff;border-left-color:#7c3aed}
.shr-patient-item:last-child{border-bottom:none}
.shr-pat-av{width:30px;height:30px;border-radius:50%;background:#eeedfe;display:flex;align-items:center;justify-content:center;font-family:'Sora',sans-serif;font-size:11px;font-weight:700;color:#5b21b6;flex-shrink:0}
.shr-pat-name{font-size:13px;font-weight:600;color:#1a0e2e;line-height:1.3}
.shr-pat-meta{font-size:11px;color:#72647a;margin-top:1px}
.shr-no-match{padding:20px;text-align:center;font-size:13px;color:#72647a}

/* Right panel states */
.shr-right-msg{text-align:center;padding:64px 20px;color:#72647a;font-size:14px}
.shr-empty-state{text-align:center;padding:64px 20px}
.shr-empty-icon{font-size:44px;margin-bottom:14px}
.shr-empty-state h3{font-family:'Sora',sans-serif;font-size:18px;font-weight:700;color:#1a0e2e;margin:0 0 6px}
.shr-empty-state p{font-size:13.5px;color:#72647a;margin:0}

/* Patient profile */
.shr-profile-bar{display:flex;align-items:center;gap:16px}
.shr-big-av{width:52px;height:52px;border-radius:50%;background:#eeedfe;display:flex;align-items:center;justify-content:center;font-family:'Sora',sans-serif;font-size:17px;font-weight:700;color:#5b21b6;flex-shrink:0;overflow:hidden}
.shr-big-av img{width:100%;height:100%;object-fit:cover;display:block;border-radius:50%}
.shr-profile-name{font-family:'Sora',sans-serif;font-size:18px;font-weight:700;color:#1a0e2e;margin:0 0 3px}
.shr-profile-meta{font-size:12.5px;color:#72647a;line-height:1.7}
.shr-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:12px}
.shr-chip{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:600;background:#f3f0ff;color:#5b21b6}
.shr-chip.warn{background:#fcebeb;color:#b42318}
.shr-chip.muted{background:#f0ebf8;color:#72647a}

/* Dental records */
.shr-rec-hd{display:flex;align-items:center;margin-bottom:12px}
.shr-rec-hd h3{font-family:'Sora',sans-serif;font-size:14px;font-weight:700;color:#1a0e2e;margin:0}
.shr-rec-card{background:#f9f7fe;border:1px solid #ede8f8;border-radius:10px;padding:14px 16px;margin-bottom:10px}
.shr-rec-card:last-child{margin-bottom:0}
.shr-rec-top{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:10px}
.shr-rec-tooth{font-family:'Sora',sans-serif;font-size:13.5px;font-weight:700;color:#1a0e2e}
.shr-rec-dt{font-size:11px;color:#72647a;margin-top:2px}
.shr-cond-pill{display:inline-flex;padding:2px 9px;border-radius:100px;font-size:11px;font-weight:600}
.shr-cond-pill.good{background:#eaf3de;color:#16845c}
.shr-cond-pill.monitor{background:#fff8e6;color:#c77712}
.shr-cond-pill.needs_treatment{background:#fcebeb;color:#b42318}
.shr-cond-pill.extracted{background:#f0ebf8;color:#72647a}
.shr-rec-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
@media(max-width:680px){.shr-rec-grid{grid-template-columns:1fr}}
.shr-rec-field label{font-size:10.5px;font-weight:700;color:#9b8ad4;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:3px}
.shr-rec-field p{font-size:13px;color:#1a0e2e;margin:0;line-height:1.5}
.shr-rec-by{font-size:11px;color:#a78bdb;margin-top:10px;text-align:right}
.shr-rec-empty{text-align:center;padding:24px;color:#72647a;font-size:13px}

/* Form */
.shr-form-hd{font-family:'Sora',sans-serif;font-size:14px;font-weight:700;color:#1a0e2e;margin:0 0 16px;padding-bottom:12px;border-bottom:1px solid #ede8f8}
.shr-fgrid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:680px){.shr-fgrid{grid-template-columns:1fr}}
.shr-f{display:flex;flex-direction:column;gap:5px}
.shr-f.full{grid-column:span 2}
.shr-lbl{font-size:12px;font-weight:700;color:#4b3a6e}
.shr-lbl .req{color:#ef4444;margin-left:2px}
.shr-inp,.shr-sel,.shr-ta{width:100%;padding:9px 12px;border:1.5px solid #e5ddf5;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;color:#1a0e2e;background:#fff;outline:none;transition:border-color .18s;box-sizing:border-box}
.shr-inp:focus,.shr-sel:focus,.shr-ta:focus{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.08)}
.shr-ta{resize:vertical;min-height:80px;line-height:1.5}
.shr-sel{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%237c3aed' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;padding-right:32px}

/* Condition chips (form) */
.shr-cond-chips{display:flex;gap:8px;flex-wrap:wrap}
.shr-cond-chip{padding:7px 15px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:2px solid #e5ddf5;background:#fff;color:#72647a;transition:all .15s;font-family:'DM Sans',sans-serif;line-height:1}
.shr-cond-chip:hover{border-color:#a78bdb}
.shr-cond-chip.sel-good{background:#eaf3de;border-color:#16845c;color:#16845c}
.shr-cond-chip.sel-monitor{background:#fff8e6;border-color:#c77712;color:#c77712}
.shr-cond-chip.sel-needs_treatment{background:#fcebeb;border-color:#b42318;color:#b42318}
.shr-cond-chip.sel-extracted{background:#f0ebf8;border-color:#72647a;color:#5b21b6}

.shr-actions{display:flex;align-items:center;justify-content:space-between;margin-top:8px;padding-top:14px;border-top:1px solid #ede8f8;gap:12px}
.shr-submit{background:linear-gradient(135deg,#7c3aed,#5b21b6);color:#fff;border:none;padding:10px 26px;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13.5px;font-weight:700;cursor:pointer;transition:all .18s;white-space:nowrap}
.shr-submit:hover{background:linear-gradient(135deg,#6d28d9,#4c1d95);transform:translateY(-1px)}
.shr-submit:disabled{opacity:.6;cursor:not-allowed;transform:none}
.shr-form-msg{font-size:13px;font-weight:600;flex:1}
.shr-form-msg.ok{color:#16845c}
.shr-form-msg.err{color:#b42318}
.shr-panel{background:#fff;border:1px solid #ede8f8;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(59,7,100,.05);margin-bottom:16px}
.shr-panel:last-child{margin-bottom:0}
</style>

<div class="shr-layout">

    <!-- ── Left: Patient Selector ── -->
    <div class="shr-selector">
        <div class="shr-selector-hd">
            <h2>Select Patient</h2>
            <div class="shr-search-wrap">
                <svg class="shr-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input id="shrSearch" class="shr-search-input" type="text" placeholder="Search name or email…" autocomplete="off" oninput="shrFilterList(this.value)">
            </div>
        </div>
        <div class="shr-patient-list" id="shrList">
            <?php if (empty($allPatients)): ?>
                <div class="shr-no-match">No active patients found.</div>
            <?php else: ?>
                <?php foreach ($allPatients as $p):
                    $pID  = (int) $p['userID'];
                    $av   = strtoupper(substr((string) ($p['username'] ?? 'P'), 0, 2));
                    $age  = $p['userAge'] ? (string) $p['userAge'] . ' yrs' : '';
                    $vis  = (int) ($p['visitCount'] ?? 0);
                    $meta = implode(' · ', array_filter([$age, $vis . ' visit' . ($vis !== 1 ? 's' : '')]));
                ?>
                <div class="shr-patient-item"
                     id="shrItem-<?= $pID ?>"
                     data-pid="<?= $pID ?>"
                     data-name="<?= e(strtolower((string) ($p['username'] ?? ''))) ?>"
                     data-email="<?= e(strtolower((string) ($p['userEmail'] ?? ''))) ?>"
                     onclick="shrSelectPatient(<?= $pID ?>, <?= e(json_encode($p['username'])) ?>)">
                    <div class="shr-pat-av"><?= e($av) ?></div>
                    <div>
                        <div class="shr-pat-name"><?= e($p['username'] ?? '') ?></div>
                        <div class="shr-pat-meta"><?= e($meta) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Right: Details + Form ── -->
    <div id="shrRight">
        <div class="shr-panel">
            <div class="shr-empty-state">
                <div class="shr-empty-icon">🦷</div>
                <h3>Select a Patient</h3>
                <p>Choose a patient from the list on the left to view their dental records and add a new entry.</p>
            </div>
        </div>
    </div>

</div>

<script>
window.userID   = <?= (int) ($_SESSION['userID'] ?? 0) ?>;
window.userRole = <?= json_encode($_SESSION['userRole'] ?? '') ?>;

/* ── HTML escape helper ───────────────────────────────────────── */
function shrEsc(str) {
    return String(str == null ? '' : str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function shrCap(str) {
    return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
}

/* ── Patient search filter ────────────────────────────────────── */
function shrFilterList(q) {
    var items = document.querySelectorAll('#shrList .shr-patient-item');
    var term  = q.toLowerCase().trim();
    var shown = 0;
    items.forEach(function (el) {
        var match = term === '' ||
            (el.dataset.name  || '').indexOf(term) !== -1 ||
            (el.dataset.email || '').indexOf(term) !== -1;
        el.style.display = match ? '' : 'none';
        if (match) shown++;
    });
    var noEl = document.getElementById('shrNoMatch');
    if (shown === 0 && !noEl) {
        var d = document.createElement('div');
        d.id = 'shrNoMatch';
        d.className = 'shr-no-match';
        d.textContent = 'No patients match "' + q + '"';
        document.getElementById('shrList').appendChild(d);
    } else if (shown > 0 && noEl) {
        noEl.remove();
    }
}

/* ── Select a patient (AJAX) ──────────────────────────────────── */
function shrSelectPatient(pid, name) {
    document.querySelectorAll('#shrList .shr-patient-item').forEach(function (el) {
        el.classList.toggle('active', parseInt(el.dataset.pid, 10) === pid);
    });

    document.getElementById('shrRight').innerHTML =
        '<div class="shr-panel"><div class="shr-right-msg">Loading records for ' + shrEsc(name) + '…</div></div>';

    fetch('get_patient_records.php?patientID=' + pid)
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) {
                document.getElementById('shrRight').innerHTML =
                    '<div class="shr-panel"><div class="shr-right-msg" style="color:#b42318">Failed to load patient data.</div></div>';
                return;
            }
            shrRenderRight(data);
        })
        .catch(function () {
            document.getElementById('shrRight').innerHTML =
                '<div class="shr-panel"><div class="shr-right-msg" style="color:#b42318">Network error. Please try again.</div></div>';
        });
}

/* ── Render right panel ───────────────────────────────────────── */
function shrRenderRight(data) {
    var p    = data.patient;
    var appts = data.completedAppointments || [];
    var recs  = data.dentalRecords || [];
    var pid   = parseInt(p.userID, 10);

    var initials   = (p.username || 'P').substring(0, 2).toUpperCase();
    var avatarHtml = p.userAvatar
        ? '<img src="assets/avatars/' + shrEsc(encodeURIComponent(p.userAvatar)) + '" alt="">'
        : shrEsc(initials);

    var ageParts = [];
    if (p.userAge) ageParts.push('Age ' + shrEsc(p.userAge));
    if (p.userGender) ageParts.push(shrCap(p.userGender));

    var chips = '';
    if (p.userChronicHealthProblems && p.userChronicHealthProblems.trim()) {
        p.userChronicHealthProblems.split(',').forEach(function (prob) {
            var t = prob.trim();
            if (t) chips += '<span class="shr-chip">' + shrEsc(t) + '</span>';
        });
    }
    if (p.userAllergies && p.userAllergies.trim()) {
        chips += '<span class="shr-chip warn">&#9888; Allergy: ' + shrEsc(p.userAllergies) + '</span>';
    }
    if (!chips) {
        chips = '<span class="shr-chip muted">No reported conditions</span>';
    }

    var profileHtml =
        '<div class="shr-panel">' +
        '<div class="shr-profile-bar">' +
        '<div class="shr-big-av">' + avatarHtml + '</div>' +
        '<div style="flex:1">' +
        '<h2 class="shr-profile-name">' + shrEsc(p.username) + '</h2>' +
        '<div class="shr-profile-meta">' + shrEsc(p.userEmail) +
        (ageParts.length ? ' &nbsp;&middot;&nbsp; ' + shrEsc(ageParts.join(' · ')) : '') +
        (p.userPhone ? ' &nbsp;&middot;&nbsp; ' + shrEsc(p.userPhone) : '') + '</div>' +
        '<div class="shr-chips">' + chips + '</div>' +
        '</div></div></div>';

    // Records panel
    var recHtml =
        '<div class="shr-panel" id="shrRecordsPanel">' +
        '<div class="shr-rec-hd"><h3>Dental Records <span style="font-weight:400;font-size:12px;color:#72647a;margin-left:6px" id="shrRecCount">' +
        recs.length + ' entr' + (recs.length === 1 ? 'y' : 'ies') + '</span></h3></div>' +
        '<div id="shrRecList">' + shrRenderRecords(recs) + '</div>' +
        '</div>';

    // Appointment dropdown
    var apptOpts = '<option value="">— No linked appointment —</option>';
    appts.forEach(function (a) {
        var dt = a.appointmentDate ? a.appointmentDate.substring(0, 10) : '';
        var tm = a.appointmentTime ? a.appointmentTime.substring(0, 5) : '';
        apptOpts += '<option value="' + parseInt(a.appointmentID, 10) + '">' +
            shrEsc(dt) + (tm ? ' · ' + shrEsc(tm) : '') + ' · ' + shrEsc(a.serviceType) + '</option>';
    });

    // Add form
    var formHtml =
        '<div class="shr-panel">' +
        '<h3 class="shr-form-hd">+ Add New Dental Record</h3>' +
        '<form id="shrForm" onsubmit="shrSubmitForm(event)">' +
        '<input type="hidden" name="_csrf_token" value="' + shrEsc(window.DETABOT_CSRF) + '">' +
        '<input type="hidden" name="patientID" value="' + pid + '">' +
        '<input type="hidden" name="toothCondition" id="shrCondVal" value="good">' +
        '<div class="shr-fgrid">' +

        '<div class="shr-f full">' +
        '<label class="shr-lbl">For Appointment</label>' +
        '<select name="appointmentID" class="shr-sel">' + apptOpts + '</select>' +
        '</div>' +

        '<div class="shr-f">' +
        '<label class="shr-lbl">Tooth Number / Area</label>' +
        '<input type="text" name="toothNumber" class="shr-inp" placeholder="e.g. 16, 46, Upper left…" maxlength="50">' +
        '</div>' +

        '<div class="shr-f">' +
        '<label class="shr-lbl">Condition After Treatment <span class="req">*</span></label>' +
        '<div class="shr-cond-chips">' +
        '<button type="button" class="shr-cond-chip sel-good"      data-cond="good"            onclick="shrPickCond(this)">Good</button>' +
        '<button type="button" class="shr-cond-chip"               data-cond="monitor"          onclick="shrPickCond(this)">Monitor</button>' +
        '<button type="button" class="shr-cond-chip"               data-cond="needs_treatment"  onclick="shrPickCond(this)">Needs Treatment</button>' +
        '<button type="button" class="shr-cond-chip"               data-cond="extracted"        onclick="shrPickCond(this)">Extracted</button>' +
        '</div></div>' +

        '<div class="shr-f full">' +
        '<label class="shr-lbl">Diagnosis <span class="req">*</span></label>' +
        '<textarea name="diagnosis" class="shr-ta" required placeholder="Clinical findings and diagnosis…"></textarea>' +
        '</div>' +

        '<div class="shr-f full">' +
        '<label class="shr-lbl">Treatment Done <span class="req">*</span></label>' +
        '<textarea name="treatmentDone" class="shr-ta" required placeholder="Procedures performed during this visit…"></textarea>' +
        '</div>' +

        '<div class="shr-f full">' +
        '<label class="shr-lbl">Next Recommended Action</label>' +
        '<textarea name="nextAction" class="shr-ta" style="min-height:66px" placeholder="Follow-up treatment, monitoring schedule, next visit…"></textarea>' +
        '</div>' +

        '<div class="shr-f full">' +
        '<label class="shr-lbl">Dentist Notes</label>' +
        '<textarea name="dentistNotes" class="shr-ta" style="min-height:66px" placeholder="Additional clinical observations…"></textarea>' +
        '</div>' +

        '</div>' +
        '<div class="shr-actions">' +
        '<span class="shr-form-msg" id="shrFormMsg"></span>' +
        '<button type="submit" class="shr-submit" id="shrSubmitBtn">Save Health Record</button>' +
        '</div>' +
        '</form></div>';

    document.getElementById('shrRight').innerHTML = profileHtml + recHtml + formHtml;
}

/* ── Render dental records list ───────────────────────────────── */
function shrRenderRecords(recs) {
    if (!recs || recs.length === 0) {
        return '<div class="shr-rec-empty">No dental records yet. Add the first entry below.</div>';
    }
    var condLabel = { good: 'Good', monitor: 'Monitor', needs_treatment: 'Needs Treatment', extracted: 'Extracted' };
    var html = '';
    recs.forEach(function (r) {
        var cond = (r.toothCondition || 'good').toLowerCase();
        var dt   = r.recordDate ? r.recordDate.substring(0, 10) : '';
        html += '<div class="shr-rec-card">' +
            '<div class="shr-rec-top"><div>' +
            '<div class="shr-rec-tooth">&#129463; ' + (r.toothNumber ? 'Tooth ' + shrEsc(r.toothNumber) : 'General') + '</div>' +
            '<div class="shr-rec-dt">' + shrEsc(dt) + '</div>' +
            '</div>' +
            '<span class="shr-cond-pill ' + shrEsc(cond) + '">' + shrEsc(condLabel[cond] || shrCap(cond)) + '</span>' +
            '</div>' +
            '<div class="shr-rec-grid">' +
            '<div class="shr-rec-field"><label>Diagnosis</label><p>' + shrEsc(r.diagnosis) + '</p></div>' +
            '<div class="shr-rec-field"><label>Treatment Done</label><p>' + shrEsc(r.treatmentDone) + '</p></div>';
        if (r.nextAction) html += '<div class="shr-rec-field"><label>Next Action</label><p>' + shrEsc(r.nextAction) + '</p></div>';
        if (r.dentistNotes) html += '<div class="shr-rec-field"><label>Dentist Notes</label><p>' + shrEsc(r.dentistNotes) + '</p></div>';
        html += '</div><div class="shr-rec-by">Recorded by ' + shrEsc(r.dentistName || 'Staff') + '</div></div>';
    });
    return html;
}

/* ── Condition chip picker ────────────────────────────────────── */
function shrPickCond(btn) {
    ['good', 'monitor', 'needs_treatment', 'extracted'].forEach(function (c) {
        var el = document.querySelector('.shr-cond-chip[data-cond="' + c + '"]');
        if (el) el.classList.remove('sel-' + c);
    });
    var sel = btn.dataset.cond;
    btn.classList.add('sel-' + sel);
    document.getElementById('shrCondVal').value = sel;
}

/* ── AJAX form submit ─────────────────────────────────────────── */
function shrSubmitForm(e) {
    e.preventDefault();
    var form = document.getElementById('shrForm');
    var btn  = document.getElementById('shrSubmitBtn');
    var msg  = document.getElementById('shrFormMsg');

    var diagnosis  = (form.querySelector('[name="diagnosis"]') || {}).value || '';
    var treatment  = (form.querySelector('[name="treatmentDone"]') || {}).value || '';
    var cond       = document.getElementById('shrCondVal').value;

    if (!diagnosis.trim() || !treatment.trim()) {
        msg.className   = 'shr-form-msg err';
        msg.textContent = 'Diagnosis and treatment done are required.';
        return;
    }

    btn.disabled    = true;
    btn.textContent = 'Saving…';
    msg.textContent = '';

    var body = new URLSearchParams();
    new FormData(form).forEach(function (val, key) { body.append(key, val); });

    fetch('save_dental_record.php', { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.disabled    = false;
            btn.textContent = 'Save Health Record';
            if (data.success) {
                msg.className   = 'shr-form-msg ok';
                msg.textContent = data.message || 'Record saved successfully.';
                form.reset();
                // Reset condition chips to Good
                ['good', 'monitor', 'needs_treatment', 'extracted'].forEach(function (c) {
                    var el = document.querySelector('.shr-cond-chip[data-cond="' + c + '"]');
                    if (el) el.classList.remove('sel-' + c);
                });
                var goodChip = document.querySelector('.shr-cond-chip[data-cond="good"]');
                if (goodChip) goodChip.classList.add('sel-good');
                document.getElementById('shrCondVal').value = 'good';
                // Refresh records list
                if (data.dentalRecords) {
                    var recList  = document.getElementById('shrRecList');
                    var recCount = document.getElementById('shrRecCount');
                    var n = data.dentalRecords.length;
                    if (recList)  recList.innerHTML  = shrRenderRecords(data.dentalRecords);
                    if (recCount) recCount.textContent = n + ' entr' + (n === 1 ? 'y' : 'ies');
                }
            } else {
                msg.className   = 'shr-form-msg err';
                msg.textContent = data.message || 'Failed to save record.';
            }
        })
        .catch(function () {
            btn.disabled    = false;
            btn.textContent = 'Save Health Record';
            msg.className   = 'shr-form-msg err';
            msg.textContent = 'Network error. Please try again.';
        });
}

/* ── Auto-select preselected patient on page load ─────────────── */
(function () {
    var pre = <?= $preselectedID ?>;
    if (pre > 0) {
        var el = document.getElementById('shrItem-' + pre);
        if (el) {
            el.scrollIntoView({ block: 'nearest' });
            var nameEl = el.querySelector('.shr-pat-name');
            shrSelectPatient(pre, nameEl ? nameEl.textContent.trim() : '');
        }
    }
})();
</script>
    <?php
}
