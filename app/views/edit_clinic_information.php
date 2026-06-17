<?php
declare(strict_types=1);

function page_edit_clinic_information(array $user): void
{
    $clinic = clinic();

    /* ── Parse per-day hours from clinicHoursJSON, else defaults ── */
    $hoursJSON = trim((string) ($clinic['clinicHoursJSON'] ?? ''));
    $hoursData = [];
    if ($hoursJSON !== '') {
        $decoded = json_decode($hoursJSON, true);
        if (is_array($decoded)) {
            $hoursData = $decoded;
        }
    }

    /* Default: Mon–Sat 09:00–17:00, Sun closed */
    $defaultHours = ['open' => '09:00', 'close' => '17:00', 'closed' => false];
    $days = [
        1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
        4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday',
    ];
    foreach ($days as $idx => $name) {
        if (!isset($hoursData[$idx])) {
            $hoursData[$idx] = $idx === 0
                ? ['open' => '09:00', 'close' => '17:00', 'closed' => true]
                : $defaultHours;
        }
    }

    /* ── Parse services ── */
    $servicesRaw = trim((string) ($clinic['services'] ?? ''));
    $serviceList = $servicesRaw !== ''
        ? array_values(array_filter(array_map('trim', explode("\n", $servicesRaw))))
        : [];

    $clinicEmail = trim((string) ($clinic['clinicEmail'] ?? ''));
    ?>
<style>
/* ── Edit Clinic Information (ec-) ────────────────────────────── */
.ec-topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:20px}
.ec-topbar-title{font-family:'Sora',sans-serif;font-size:16px;font-weight:700;color:#1a0e2e}
.ec-save-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.ec-btn-save{background:linear-gradient(135deg,#3b0764,#5b21b6);color:#fff;border:none;border-radius:9px;padding:9px 22px;font-size:13px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;display:inline-flex;align-items:center;gap:7px;transition:opacity .15s}
.ec-btn-save:hover{opacity:.88}
.ec-btn-save:disabled{opacity:.55;cursor:not-allowed}
.ec-save-msg{font-size:13px;font-weight:600}
.ec-save-msg.ok{color:#16845c}
.ec-save-msg.err{color:#b42318}

/* Cards */
.ec-card{background:#fff;border:1px solid #ede8f8;border-radius:14px;padding:22px 24px;box-shadow:0 2px 8px rgba(59,7,100,.05);margin-bottom:18px}
.ec-card-hd{display:flex;align-items:center;gap:10px;margin-bottom:18px}
.ec-card-icon{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#3b0764,#7c3aed);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ec-card-icon svg{width:17px;height:17px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.ec-card-title{font-family:'Sora',sans-serif;font-size:14.5px;font-weight:700;color:#1a0e2e}

/* Form grid */
.ec-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:620px){.ec-grid{grid-template-columns:1fr}}
.ec-full{grid-column:1/-1}
.ec-field{display:flex;flex-direction:column;gap:5px}
.ec-label{font-size:11px;font-weight:700;color:#9b8ad4;text-transform:uppercase;letter-spacing:.05em}
.ec-inp{padding:9px 13px;border:1.5px solid #e5ddf5;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13.5px;color:#1a0e2e;background:#fff;outline:none;transition:border-color .18s;box-sizing:border-box;width:100%}
.ec-inp:focus{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.07)}
.ec-inp::placeholder{color:#c4b8d4}
textarea.ec-inp{resize:vertical;min-height:90px;line-height:1.6}

/* Operating hours table */
.ec-hours-tbl{width:100%;border-collapse:collapse}
.ec-hours-tbl th{text-align:left;font-size:11px;font-weight:700;color:#72647a;text-transform:uppercase;letter-spacing:.05em;padding:0 10px 10px;border-bottom:1.5px solid #ede8f8}
.ec-hours-tbl td{padding:10px 10px;border-bottom:1px solid #f0ebf8;vertical-align:middle}
.ec-hours-tbl tr:last-child td{border-bottom:none}
.ec-day-name{font-size:13.5px;font-weight:600;color:#1a0e2e;min-width:100px}

/* Toggle switch */
.ec-toggle-wrap{display:flex;align-items:center;gap:8px}
.ec-toggle{position:relative;display:inline-block;width:38px;height:22px;flex-shrink:0}
.ec-toggle input{opacity:0;width:0;height:0;position:absolute}
.ec-toggle-slider{position:absolute;inset:0;background:#e0d8f0;border-radius:11px;cursor:pointer;transition:background .2s}
.ec-toggle-slider::before{content:'';position:absolute;width:16px;height:16px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.18)}
.ec-toggle input:checked + .ec-toggle-slider{background:#7c3aed}
.ec-toggle input:checked + .ec-toggle-slider::before{transform:translateX(16px)}
.ec-toggle-lbl{font-size:12.5px;color:#72647a;min-width:50px}

.ec-time-pair{display:flex;align-items:center;gap:8px;flex-wrap:nowrap}
.ec-time-inp{padding:7px 10px;border:1.5px solid #e5ddf5;border-radius:7px;font-family:'DM Sans',sans-serif;font-size:13px;color:#1a0e2e;background:#fff;outline:none;width:110px;transition:border-color .18s,opacity .18s}
.ec-time-inp:focus{border-color:#7c3aed}
.ec-time-inp:disabled{background:#f5f2ff;color:#b8aed4;opacity:.6;cursor:not-allowed}
.ec-time-sep{font-size:12px;color:#72647a;flex-shrink:0}
.ec-closed-lbl{font-size:12.5px;color:#b42318;font-weight:600;min-width:120px}

/* Services section */
.ec-tags{display:flex;flex-wrap:wrap;gap:8px;min-height:36px;margin-bottom:14px}
.ec-tag{display:inline-flex;align-items:center;gap:6px;background:#f3f0ff;border:1px solid #ede8f8;color:#3b0764;border-radius:8px;padding:5px 12px;font-size:13px;font-weight:600;font-family:'DM Sans',sans-serif}
.ec-tag-remove{background:none;border:none;cursor:pointer;color:#9b8ad4;font-size:14px;line-height:1;padding:0;display:flex;align-items:center;transition:color .15s}
.ec-tag-remove:hover{color:#b42318}
.ec-add-row{display:flex;gap:8px;align-items:center}
.ec-add-inp{flex:1;max-width:280px}
.ec-btn-add-svc{background:#f3f0ff;color:#5b21b6;border:1.5px solid #ede8f8;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;transition:background .15s;white-space:nowrap}
.ec-btn-add-svc:hover{background:#e3dcfc}
</style>

<script>
window.userID   = <?= (int) ($user['userID'] ?? 0) ?>;
window.userRole = <?= json_encode($user['userRole'] ?? '') ?>;
window.DETABOT_CSRF = <?= json_encode(csrf_token()) ?>;
</script>

<!-- ── Top action bar ── -->
<div class="ec-topbar">
    <div class="ec-save-row" style="margin-left:auto">
        <span class="ec-save-msg" id="ecSaveMsg"></span>
        <button class="ec-btn-save" id="ecSaveBtn" onclick="ecSave()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Save Changes
        </button>
    </div>
</div>

<!-- ── Section 1: Basic Details ── -->
<div class="ec-card">
    <div class="ec-card-hd">
        <div class="ec-card-icon">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div class="ec-card-title">Basic Details</div>
    </div>
    <div class="ec-grid">
        <div class="ec-field">
            <label class="ec-label" for="ecClinicName">Clinic Name</label>
            <input class="ec-inp" id="ecClinicName" type="text" maxlength="100"
                   value="<?= e((string) ($clinic['clinicName'] ?? '')) ?>" placeholder="e.g. Clinic Putra Dental">
        </div>
        <div class="ec-field">
            <label class="ec-label" for="ecPhone">Contact Number</label>
            <input class="ec-inp" id="ecPhone" type="tel" maxlength="20"
                   value="<?= e((string) ($clinic['contactNumber'] ?? '')) ?>" placeholder="e.g. 07-453 8899">
        </div>
        <div class="ec-field ec-full">
            <label class="ec-label" for="ecLocation">Location / Address</label>
            <input class="ec-inp" id="ecLocation" type="text" maxlength="255"
                   value="<?= e((string) ($clinic['location'] ?? '')) ?>" placeholder="e.g. Taman Universiti, Parit Raja, Johor">
        </div>
        <div class="ec-field">
            <label class="ec-label" for="ecEmail">Email</label>
            <input class="ec-inp" id="ecEmail" type="email" maxlength="100"
                   value="<?= e($clinicEmail) ?>" placeholder="e.g. info@putradental.my">
        </div>
    </div>
</div>

<!-- ── Section 2: Operating Hours ── -->
<div class="ec-card">
    <div class="ec-card-hd">
        <div class="ec-card-icon">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="ec-card-title">Operating Hours</div>
    </div>
    <table class="ec-hours-tbl">
        <thead>
            <tr>
                <th>Day</th>
                <th>Status</th>
                <th>Hours</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($days as $idx => $dayName):
                $h      = $hoursData[$idx];
                $closed = !empty($h['closed']);
                $open   = (string) ($h['open']  ?? '09:00');
                $close  = (string) ($h['close'] ?? '17:00');
            ?>
            <tr>
                <td class="ec-day-name"><?= e($dayName) ?></td>
                <td>
                    <div class="ec-toggle-wrap">
                        <label class="ec-toggle">
                            <input type="checkbox"
                                   id="ecOpen-<?= $idx ?>"
                                   <?= $closed ? '' : 'checked' ?>
                                   onchange="ecDayToggle(<?= $idx ?>)">
                            <span class="ec-toggle-slider"></span>
                        </label>
                        <span class="ec-toggle-lbl" id="ecTogLbl-<?= $idx ?>"><?= $closed ? 'Closed' : 'Open' ?></span>
                    </div>
                </td>
                <td>
                    <div class="ec-time-pair" id="ecTimes-<?= $idx ?>" <?= $closed ? 'style="display:none"' : '' ?>>
                        <input class="ec-time-inp" type="time"
                               id="ecFrom-<?= $idx ?>"
                               value="<?= e($open) ?>"
                               <?= $closed ? 'disabled' : '' ?>>
                        <span class="ec-time-sep">to</span>
                        <input class="ec-time-inp" type="time"
                               id="ecTo-<?= $idx ?>"
                               value="<?= e($close) ?>"
                               <?= $closed ? 'disabled' : '' ?>>
                    </div>
                    <?php if ($closed): ?>
                    <span class="ec-closed-lbl" id="ecClosedLbl-<?= $idx ?>">Closed</span>
                    <?php else: ?>
                    <span class="ec-closed-lbl" id="ecClosedLbl-<?= $idx ?>" style="display:none">Closed</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ── Section 3: Services Offered ── -->
<div class="ec-card">
    <div class="ec-card-hd">
        <div class="ec-card-icon">
            <svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        </div>
        <div class="ec-card-title">Services Offered</div>
    </div>
    <div class="ec-tags" id="ecTagsWrap">
        <?php foreach ($serviceList as $svc): ?>
        <span class="ec-tag">
            <?= e($svc) ?>
            <button type="button" class="ec-tag-remove"
                    aria-label="Remove <?= e($svc) ?>"
                    onclick="ecRemoveTag(this)">×</button>
        </span>
        <?php endforeach; ?>
    </div>
    <div class="ec-add-row">
        <input class="ec-inp ec-add-inp" id="ecNewSvc" type="text" maxlength="80"
               placeholder="Add a new service…"
               onkeydown="if(event.key==='Enter'){event.preventDefault();ecAddTag()}">
        <button type="button" class="ec-btn-add-svc" onclick="ecAddTag()">Add</button>
    </div>
</div>

<!-- ── Section 4: Promotions ── -->
<div class="ec-card">
    <div class="ec-card-hd">
        <div class="ec-card-icon">
            <svg viewBox="0 0 24 24"><rect x="3" y="8" width="18" height="13" rx="2"/><path d="M12 8v13M3 12h18"/><path d="M7.5 8A2.5 2.5 0 1 1 12 6.5V8"/><path d="M16.5 8A2.5 2.5 0 1 0 12 6.5V8"/></svg>
        </div>
        <div class="ec-card-title">Promotions</div>
    </div>
    <div class="ec-field">
        <label class="ec-label" for="ecPromos">Promotion Text</label>
        <textarea class="ec-inp" id="ecPromos" rows="4"
                  placeholder="Describe any current promotions or special offers…"><?= e((string) ($clinic['promotions'] ?? '')) ?></textarea>
    </div>
</div>

<script>
(function() {
    /* ── Day toggle ── */
    window.ecDayToggle = function(idx) {
        var chk   = document.getElementById('ecOpen-' + idx);
        var lbl   = document.getElementById('ecTogLbl-' + idx);
        var times = document.getElementById('ecTimes-' + idx);
        var cLbl  = document.getElementById('ecClosedLbl-' + idx);
        var from  = document.getElementById('ecFrom-' + idx);
        var to    = document.getElementById('ecTo-' + idx);
        var isOpen = chk.checked;

        lbl.textContent = isOpen ? 'Open' : 'Closed';

        if (isOpen) {
            times.style.display = '';
            cLbl.style.display  = 'none';
            from.disabled = false;
            to.disabled   = false;
        } else {
            times.style.display = 'none';
            cLbl.style.display  = '';
            from.disabled = true;
            to.disabled   = true;
        }
    };

    /* ── Service tags ── */
    window.ecAddTag = function() {
        var inp = document.getElementById('ecNewSvc');
        var val = inp.value.trim();
        if (!val) return;

        /* Duplicate check */
        var existing = Array.from(document.querySelectorAll('#ecTagsWrap .ec-tag'))
            .map(function(t){ return t.childNodes[0].textContent.trim().toLowerCase(); });
        if (existing.includes(val.toLowerCase())) {
            inp.value = '';
            return;
        }

        var tag = document.createElement('span');
        tag.className = 'ec-tag';
        tag.innerHTML = document.createTextNode(val).textContent
            + ' <button type="button" class="ec-tag-remove" onclick="ecRemoveTag(this)">×</button>';
        /* safer construction */
        tag.textContent = '';
        var txt = document.createTextNode(val + ' ');
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ec-tag-remove';
        btn.setAttribute('aria-label', 'Remove ' + val);
        btn.textContent = '×';
        btn.onclick = function(){ ecRemoveTag(btn); };
        tag.appendChild(txt);
        tag.appendChild(btn);

        document.getElementById('ecTagsWrap').appendChild(tag);
        inp.value = '';
        inp.focus();
    };

    window.ecRemoveTag = function(btn) {
        btn.closest('.ec-tag').remove();
    };

    /* ── Collect hours JSON ── */
    function ecCollectHours() {
        var dayIdxs = [1, 2, 3, 4, 5, 6, 0];
        var result  = {};
        dayIdxs.forEach(function(idx) {
            var chk    = document.getElementById('ecOpen-' + idx);
            var closed = !chk.checked;
            result[idx] = {
                closed: closed,
                open:   closed ? '09:00' : (document.getElementById('ecFrom-' + idx).value || '09:00'),
                close:  closed ? '17:00' : (document.getElementById('ecTo-'   + idx).value || '17:00'),
            };
        });
        return result;
    }

    /* ── Collect services ── */
    function ecCollectServices() {
        return Array.from(document.querySelectorAll('#ecTagsWrap .ec-tag'))
            .map(function(t){ return t.childNodes[0].textContent.trim(); })
            .filter(Boolean);
    }

    /* ── Save ── */
    window.ecSave = function() {
        var clinicName = document.getElementById('ecClinicName').value.trim();
        var location   = document.getElementById('ecLocation').value.trim();
        var phone      = document.getElementById('ecPhone').value.trim();
        var email      = document.getElementById('ecEmail').value.trim();
        var promos     = document.getElementById('ecPromos').value.trim();
        var btn        = document.getElementById('ecSaveBtn');
        var msg        = document.getElementById('ecSaveMsg');

        if (!clinicName) { ecMsg(msg, 'Clinic name is required.', false); return; }
        if (!location)   { ecMsg(msg, 'Location is required.', false); return; }
        if (!phone)      { ecMsg(msg, 'Contact number is required.', false); return; }

        var services   = ecCollectServices();
        var hoursObj   = ecCollectHours();

        btn.disabled = true;
        ecMsg(msg, '', false);

        var fd = new FormData();
        fd.append('_csrf_token', window.DETABOT_CSRF);
        fd.append('clinicName',    clinicName);
        fd.append('location',      location);
        fd.append('contactNumber', phone);
        fd.append('email',         email);
        fd.append('promotions',    promos);
        fd.append('hoursJSON',     JSON.stringify(hoursObj));
        services.forEach(function(s){ fd.append('services[]', s); });

        fetch('update_clinic_info.php', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (d.success) {
                    ecMsg(msg, d.message || 'Clinic information updated successfully!', true);
                } else {
                    ecMsg(msg, d.message || 'Save failed.', false);
                }
                btn.disabled = false;
            })
            .catch(function() {
                ecMsg(msg, 'Network error — please try again.', false);
                btn.disabled = false;
            });
    };

    function ecMsg(el, text, ok) {
        el.textContent = text;
        el.className = 'ec-save-msg ' + (text ? (ok ? 'ok' : 'err') : '');
    }
})();
</script>
<?php
}
