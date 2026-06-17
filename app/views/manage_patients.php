<?php
declare(strict_types=1);

function page_manage_patients(array $user): void
{
    $patients = db_all(
        "SELECT u.userID, u.username, u.userEmail, u.userPhone, u.userAge, u.userGender,
                u.userChronicHealthProblems, u.userAvatar, u.status, u.createdDate,
                COUNT(DISTINCT a.appointmentID) AS totalAppts
         FROM tbl_user u
         LEFT JOIN tbl_appointment a ON a.userID = u.userID
         WHERE u.userRole = 'patient'
         GROUP BY u.userID
         ORDER BY u.createdDate DESC",
        []
    );

    $totalPatients   = count($patients);
    $activePatients  = count(array_filter($patients, fn($p) => ($p['status'] ?? 'active') === 'active'));
    $withAppts       = count(array_filter($patients, fn($p) => (int) ($p['totalAppts'] ?? 0) > 0));
    $withHealthConds = count(array_filter($patients, fn($p) => trim((string) ($p['userChronicHealthProblems'] ?? '')) !== ''));
    ?>
<style>
/* ── Manage Patients (mp-) ─────────────────────────────────────────── */
.mp-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px}
@media(max-width:900px){.mp-stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:480px){.mp-stats{grid-template-columns:1fr 1fr}}

.mp-stat{background:#fff;border-radius:12px;border:1px solid #ede8f8;padding:16px;box-shadow:0 2px 8px rgba(59,7,100,.05);display:flex;align-items:center;gap:13px}
.mp-stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.mp-stat-icon.purple{background:#f3f0ff}
.mp-stat-icon.green{background:#eaf3de}
.mp-stat-icon.blue{background:#e8f4fd}
.mp-stat-icon.amber{background:#fff8e6}
.mp-stat-num{font-family:'Sora',sans-serif;font-size:26px;font-weight:700;color:#1a0e2e;line-height:1;margin-bottom:3px}
.mp-stat-lbl{font-size:12px;color:#72647a;font-weight:500}

/* Toolbar */
.mp-toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px}
.mp-search-wrap{position:relative;flex:1;min-width:180px;max-width:300px}
.mp-search-icon{position:absolute;left:10px;top:50%;transform:translateY(-50%);width:13px;height:13px;stroke:#a78bdb;pointer-events:none}
.mp-search-inp{width:100%;padding:9px 12px 9px 32px;border:1.5px solid #e5ddf5;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;color:#1a0e2e;background:#fff;outline:none;transition:border-color .18s;box-sizing:border-box}
.mp-search-inp:focus{border-color:#7c3aed}
.mp-filter-sel{padding:8px 30px 8px 11px;border:1.5px solid #e5ddf5;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;color:#1a0e2e;background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24' fill='none' stroke='%237c3aed' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 9px center;appearance:none;outline:none;cursor:pointer;transition:border-color .18s}
.mp-filter-sel:focus{border-color:#7c3aed}
.mp-count{font-size:12.5px;color:#72647a;margin-left:auto;white-space:nowrap}

/* Table */
.mp-tbl-wrap{overflow-x:auto}
.mp-tbl{width:100%;border-collapse:collapse;font-size:13px}
.mp-tbl th{text-align:left;padding:9px 12px;font-size:11px;font-weight:700;color:#72647a;text-transform:uppercase;letter-spacing:.05em;border-bottom:2px solid #ede8f8;white-space:nowrap;background:#fdfcff}
.mp-tbl td{padding:11px 12px;border-bottom:1px solid #f0ebf8;vertical-align:middle}
.mp-tbl tr:last-child td{border-bottom:none}
.mp-tbl tr:hover td{background:#faf8ff}

/* Patient cell */
.mp-pat-cell{display:flex;align-items:center;gap:10px}
.mp-av{width:34px;height:34px;border-radius:50%;background:#eeedfe;display:flex;align-items:center;justify-content:center;font-family:'Sora',sans-serif;font-size:12px;font-weight:700;color:#5b21b6;flex-shrink:0;overflow:hidden}
.mp-av img{width:100%;height:100%;object-fit:cover;display:block}
.mp-name{font-weight:700;color:#1a0e2e;line-height:1.3}
.mp-email{font-size:11.5px;color:#72647a;margin-top:1px}

/* Health pills */
.mp-pill{display:inline-flex;align-items:center;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mp-pill.none{background:#eaf3de;color:#16845c}
.mp-pill.has{background:#fff8e6;color:#c77712}

/* Zero value */
.mp-zero{color:#c4b8d4}

/* Action buttons */
.mp-btns{display:flex;gap:5px;align-items:center;flex-wrap:nowrap}
.mp-btn{display:inline-flex;align-items:center;gap:3px;padding:5px 10px;border-radius:6px;font-size:11.5px;font-weight:600;border:none;cursor:pointer;transition:all .15s;white-space:nowrap;text-decoration:none;font-family:'DM Sans',sans-serif;line-height:1}
.mp-btn-record{background:#f3f0ff;color:#5b21b6}.mp-btn-record:hover{background:#e3dcfc}
.mp-btn-history{background:#e8f4fd;color:#1686c2}.mp-btn-history:hover{background:#cce8f9}
.mp-btn-view{background:#f9f7fe;color:#72647a;border:1px solid #ede8f8}.mp-btn-view:hover{background:#f3f0ff;color:#3b0764}

.mp-empty{text-align:center;padding:42px;color:#72647a;font-size:13.5px}

/* Modal overlay */
.mp-overlay{position:fixed;inset:0;background:rgba(15,5,30,.45);z-index:1000;display:none;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(2px)}
.mp-overlay.open{display:flex}
.mp-modal{background:#fff;border-radius:16px;width:100%;max-width:620px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(59,7,100,.25)}
.mp-modal-hd{display:flex;align-items:center;justify-content:space-between;padding:18px 20px 14px;border-bottom:1px solid #ede8f8;position:sticky;top:0;background:#fff;z-index:1;border-radius:16px 16px 0 0}
.mp-modal-hd h2{font-family:'Sora',sans-serif;font-size:16px;font-weight:700;color:#1a0e2e;margin:0}
.mp-modal-close{background:none;border:none;cursor:pointer;width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#72647a;transition:background .15s}
.mp-modal-close:hover{background:#f0ebf8;color:#3b0764}
.mp-modal-close svg{width:16px;height:16px;stroke:currentColor;stroke-width:2.5;fill:none}
.mp-modal-body{padding:18px 20px 22px}
.mp-modal-sec{margin-bottom:20px}
.mp-modal-sec:last-child{margin-bottom:0}
.mp-modal-sec-title{font-size:11px;font-weight:700;color:#9b8ad4;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;padding-bottom:6px;border-bottom:1px solid #f0ebf8}
.mp-modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
@media(max-width:500px){.mp-modal-grid{grid-template-columns:1fr}}
.mp-modal-field label{font-size:11px;font-weight:700;color:#9b8ad4;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:3px}
.mp-modal-field p{font-size:13.5px;color:#1a0e2e;margin:0;line-height:1.5;word-break:break-word}

/* Appointment summary boxes */
.mp-appt-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:4px}
@media(max-width:460px){.mp-appt-summary{grid-template-columns:repeat(2,1fr)}}
.mp-sum-box{background:#f9f7fe;border:1px solid #ede8f8;border-radius:8px;padding:10px;text-align:center}
.mp-sum-num{font-family:'Sora',sans-serif;font-size:20px;font-weight:700;color:#1a0e2e}
.mp-sum-lbl{font-size:10.5px;color:#72647a;margin-top:3px}

/* History items */
.mp-hist-item{background:#f9f7fe;border:1px solid #ede8f8;border-radius:10px;padding:12px 14px;margin-bottom:8px}
.mp-hist-item:last-child{margin-bottom:0}
.mp-hist-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;gap:8px;flex-wrap:wrap}
.mp-hist-svc{font-weight:700;color:#1a0e2e;font-size:13px}
.mp-hist-date{font-size:11.5px;color:#72647a;margin-top:2px}
.mp-hist-badge{display:inline-flex;align-items:center;padding:2px 9px;border-radius:100px;font-size:10.5px;font-weight:600}
.mp-hist-badge.completed{background:#eaf3de;color:#16845c}
.mp-hist-badge.pending{background:#fff8e6;color:#c77712}
.mp-hist-badge.confirmed{background:#e8f4fd;color:#1686c2}
.mp-hist-badge.cancelled{background:#fcebeb;color:#b42318}

/* Dental record items */
.mp-rec-item{background:#fff;border:1px solid #ede8f8;border-radius:8px;padding:10px 12px;margin-bottom:7px;font-size:12.5px;line-height:1.5}
.mp-rec-item:last-child{margin-bottom:0}
.mp-rec-row{display:flex;gap:8px;justify-content:space-between;align-items:flex-start;margin-bottom:4px}
.mp-rec-tooth{font-weight:700;color:#3b0764;font-size:13px}
.mp-rec-date{color:#9b8ad4;font-size:11px;white-space:nowrap}
.mp-rec-diag{color:#1a0e2e;margin-bottom:4px}
.mp-rec-cond{display:inline-block;padding:1px 8px;border-radius:10px;font-size:10.5px;font-weight:600}
.mp-rec-cond.good{background:#eaf3de;color:#16845c}
.mp-rec-cond.monitor{background:#fff8e6;color:#c77712}
.mp-rec-cond.needs_treatment{background:#fcebeb;color:#b42318}
.mp-rec-cond.extracted{background:#f0ebf8;color:#72647a}

/* Modal loading / error states */
.mp-modal-loading{text-align:center;padding:32px;color:#72647a;font-size:13px}
.mp-modal-err{text-align:center;padding:20px;color:#b42318;font-size:13px}
.mp-modal-empty{color:#72647a;font-size:13px;margin:0}

/* Quick links */
.mp-modal-links{display:flex;gap:8px;flex-wrap:wrap}
.mp-modal-link{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:8px;font-size:12.5px;font-weight:600;text-decoration:none;transition:all .15s}
.mp-modal-link.purple{background:#f3f0ff;color:#5b21b6}.mp-modal-link.purple:hover{background:#e3dcfc}
.mp-modal-link.blue{background:#e8f4fd;color:#1686c2}.mp-modal-link.blue:hover{background:#cce8f9}

/* Reward badge */
.mp-reward-pts{display:inline-flex;align-items:center;gap:6px;background:#f3f0ff;border:1px solid #ede8f8;border-radius:20px;padding:5px 14px;font-size:13px;font-weight:700;color:#5b21b6}
</style>

<!-- Stat Cards -->
<div class="mp-stats">
    <div class="mp-stat">
        <div class="mp-stat-icon purple">👥</div>
        <div>
            <div class="mp-stat-num"><?= $totalPatients ?></div>
            <div class="mp-stat-lbl">Total Patients</div>
        </div>
    </div>
    <div class="mp-stat">
        <div class="mp-stat-icon green">✅</div>
        <div>
            <div class="mp-stat-num"><?= $activePatients ?></div>
            <div class="mp-stat-lbl">Active</div>
        </div>
    </div>
    <div class="mp-stat">
        <div class="mp-stat-icon blue">📅</div>
        <div>
            <div class="mp-stat-num"><?= $withAppts ?></div>
            <div class="mp-stat-lbl">With Appointments</div>
        </div>
    </div>
    <div class="mp-stat">
        <div class="mp-stat-icon amber">🏥</div>
        <div>
            <div class="mp-stat-num"><?= $withHealthConds ?></div>
            <div class="mp-stat-lbl">With Health Conditions</div>
        </div>
    </div>
</div>

<!-- Table Panel -->
<div class="panel">
    <!-- Toolbar -->
    <div class="mp-toolbar">
        <div class="mp-search-wrap">
            <svg class="mp-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input id="mpSearch" class="mp-search-inp" type="text" placeholder="Search by name, email, or phone…" autocomplete="off" oninput="mpSearch(this.value)">
        </div>
        <select id="mpFilter" class="mp-filter-sel" onchange="mpSetFilter(this.value)">
            <option value="all">All Patients</option>
            <option value="health">With Health Conditions</option>
            <option value="appts">With Appointments</option>
        </select>
        <select id="mpSort" class="mp-filter-sel" onchange="mpSetSort(this.value)">
            <option value="newest">Newest First</option>
            <option value="name">Name A–Z</option>
            <option value="visits">Most Visits</option>
        </select>
        <span class="mp-count" id="mpCount"><?= $totalPatients ?> patient<?= $totalPatients !== 1 ? 's' : '' ?></span>
    </div>

    <?php if (empty($patients)): ?>
        <div class="mp-empty">No patients found.</div>
    <?php else: ?>
    <div class="mp-tbl-wrap">
        <table class="mp-tbl">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Phone</th>
                    <th>Age / Gender</th>
                    <th>Health</th>
                    <th>Visits</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="mpBody">
            <?php foreach ($patients as $p):
                $pid        = (int) $p['userID'];
                $name       = (string) ($p['username'] ?? '');
                $email      = (string) ($p['userEmail'] ?? '');
                $phone      = (string) ($p['userPhone'] ?? '');
                $age        = $p['userAge'] ? (string) $p['userAge'] : '';
                $gender     = $p['userGender'] ? ucfirst((string) $p['userGender']) : '';
                $health     = trim((string) ($p['userChronicHealthProblems'] ?? ''));
                $visits     = (int) ($p['totalAppts'] ?? 0);
                $avatarFile = (string) ($p['userAvatar'] ?? '');
                $initials   = strtoupper(substr($name ?: 'P', 0, 2));
                $ts         = $p['createdDate'] ? (string) strtotime((string) $p['createdDate']) : '0';

                $searchStr  = strtolower($name . ' ' . $email . ' ' . $phone);
                $filterTags = ($health !== '' ? 'health ' : '') . ($visits > 0 ? 'appts' : '');
            ?>
            <tr data-pid="<?= $pid ?>"
                data-search="<?= e($searchStr) ?>"
                data-filter="<?= e(trim($filterTags)) ?>"
                data-name="<?= e(strtolower($name)) ?>"
                data-date="<?= e($ts) ?>"
                data-visits="<?= $visits ?>">
                <td>
                    <div class="mp-pat-cell">
                        <div class="mp-av">
                            <?php if ($avatarFile): ?>
                                <img src="assets/avatars/<?= e(rawurlencode($avatarFile)) ?>" alt="">
                            <?php else: ?>
                                <?= e($initials) ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="mp-name"><?= e($name) ?></div>
                            <div class="mp-email"><?= e($email) ?></div>
                        </div>
                    </div>
                </td>
                <td><?= $phone ? e($phone) : '<span class="mp-zero">—</span>' ?></td>
                <td><?= ($age || $gender) ? e($age . ($age && $gender ? ' / ' : '') . $gender) : '<span class="mp-zero">—</span>' ?></td>
                <td>
                    <?php if ($health === ''): ?>
                        <span class="mp-pill none">No conditions</span>
                    <?php else: ?>
                        <span class="mp-pill has" title="<?= e($health) ?>"><?= e(mb_strimwidth($health, 0, 28, '…')) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= $visits > 0 ? $visits : '<span class="mp-zero">0</span>' ?></td>
                <td>
                    <div class="mp-btns">
                        <a class="mp-btn mp-btn-record" href="staff_health_record.php?patient=<?= $pid ?>">🦷 Record</a>
                        <button class="mp-btn mp-btn-history" onclick="mpOpenHistory(<?= $pid ?>, <?= e(json_encode($name)) ?>)">📋 History</button>
                        <button class="mp-btn mp-btn-view" onclick="mpOpenView(<?= $pid ?>, <?= e(json_encode($name)) ?>)">👤 View</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- History Modal -->
<div class="mp-overlay" id="mpHistOverlay">
    <div class="mp-modal" role="dialog" aria-modal="true" aria-labelledby="mpHistTitle">
        <div class="mp-modal-hd">
            <h2 id="mpHistTitle">Patient History</h2>
            <button class="mp-modal-close" onclick="mpCloseHistory()" aria-label="Close">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="mp-modal-body" id="mpHistBody">
            <div class="mp-modal-loading">Loading…</div>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="mp-overlay" id="mpViewOverlay">
    <div class="mp-modal" role="dialog" aria-modal="true" aria-labelledby="mpViewTitle">
        <div class="mp-modal-hd">
            <h2 id="mpViewTitle">Patient Profile</h2>
            <button class="mp-modal-close" onclick="mpCloseView()" aria-label="Close">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="mp-modal-body" id="mpViewBody">
            <div class="mp-modal-loading">Loading…</div>
        </div>
    </div>
</div>

<script>
window.userID   = <?= (int) ($_SESSION['userID'] ?? 0) ?>;
window.userRole = <?= json_encode($_SESSION['userRole'] ?? '') ?>;

/* ── Client-side filter / sort ──────────────────────────────────── */
(function () {
    var allRows    = [];
    var searchTerm = '';
    var filterVal  = 'all';
    var sortVal    = 'newest';

    function init() {
        var tbody = document.getElementById('mpBody');
        if (!tbody) return;
        allRows = Array.from(tbody.querySelectorAll('tr[data-pid]'));
    }

    function apply() {
        var tbody = document.getElementById('mpBody');
        if (!tbody) return;

        var sorted = allRows.slice();

        if (sortVal === 'name') {
            sorted.sort(function (a, b) {
                return (a.dataset.name || '').localeCompare(b.dataset.name || '');
            });
        } else if (sortVal === 'visits') {
            sorted.sort(function (a, b) {
                return parseInt(b.dataset.visits || '0', 10) - parseInt(a.dataset.visits || '0', 10);
            });
        } else {
            sorted.sort(function (a, b) {
                return parseInt(b.dataset.date || '0', 10) - parseInt(a.dataset.date || '0', 10);
            });
        }

        var shown = 0;
        sorted.forEach(function (row) {
            var matchSearch = !searchTerm || (row.dataset.search || '').indexOf(searchTerm) !== -1;
            var tags        = row.dataset.filter || '';
            var matchFilter = filterVal === 'all' ||
                (filterVal === 'health' && tags.indexOf('health') !== -1) ||
                (filterVal === 'appts'  && tags.indexOf('appts')  !== -1);

            if (matchSearch && matchFilter) {
                row.style.display = '';
                tbody.appendChild(row);
                shown++;
            } else {
                row.style.display = 'none';
            }
        });

        var el = document.getElementById('mpCount');
        if (el) el.textContent = shown + ' patient' + (shown !== 1 ? 's' : '');
    }

    window.mpSearch = function (val) {
        searchTerm = val.toLowerCase().trim();
        apply();
    };

    window.mpSetFilter = function (val) {
        filterVal = val;
        apply();
    };

    window.mpSetSort = function (val) {
        sortVal = val;
        apply();
    };

    init();
})();

/* ── Modal helpers ─────────────────────────────────────────────── */
function mpEsc(str) {
    return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function mpCap(str) {
    return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
}

function mpCloseHistory() {
    document.getElementById('mpHistOverlay').classList.remove('open');
}

function mpCloseView() {
    document.getElementById('mpViewOverlay').classList.remove('open');
}

document.getElementById('mpHistOverlay').addEventListener('click', function (e) {
    if (e.target === this) mpCloseHistory();
});
document.getElementById('mpViewOverlay').addEventListener('click', function (e) {
    if (e.target === this) mpCloseView();
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { mpCloseHistory(); mpCloseView(); }
});

/* ── History modal ─────────────────────────────────────────────── */
function mpOpenHistory(pid, name) {
    var overlay = document.getElementById('mpHistOverlay');
    var title   = document.getElementById('mpHistTitle');
    var body    = document.getElementById('mpHistBody');

    title.textContent = name + ' — History';
    body.innerHTML    = '<div class="mp-modal-loading">Loading history…</div>';
    overlay.classList.add('open');

    fetch('get_patient_details.php?patientID=' + pid)
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) {
                body.innerHTML = '<div class="mp-modal-err">Failed to load history.</div>';
                return;
            }

            var html = '';

            /* Appointments */
            html += '<div class="mp-modal-sec">';
            html += '<div class="mp-modal-sec-title">Appointments (' + (data.appointments ? data.appointments.length : 0) + ')</div>';

            if (!data.appointments || data.appointments.length === 0) {
                html += '<p class="mp-modal-empty">No appointments on record.</p>';
            } else {
                data.appointments.forEach(function (a) {
                    var st  = (a.status || 'pending').toLowerCase();
                    var tm  = a.appointmentTime ? a.appointmentTime.substring(0, 5) : '';
                    html += '<div class="mp-hist-item">';
                    html += '<div class="mp-hist-top">';
                    html += '<span class="mp-hist-svc">' + mpEsc(a.serviceType) + '</span>';
                    html += '<span class="mp-hist-badge ' + mpEsc(st) + '">' + mpEsc(mpCap(st)) + '</span>';
                    html += '</div>';
                    html += '<div class="mp-hist-date">' + mpEsc(a.appointmentDate) + (tm ? ' &nbsp;·&nbsp; ' + mpEsc(tm) : '') + '</div>';
                    html += '</div>';
                });
            }

            html += '</div>';

            /* Dental records */
            html += '<div class="mp-modal-sec">';
            html += '<div class="mp-modal-sec-title">Dental Records (' + (data.dentalRecords ? data.dentalRecords.length : 0) + ')</div>';

            if (!data.dentalRecords || data.dentalRecords.length === 0) {
                html += '<p class="mp-modal-empty">No dental records yet.</p>';
            } else {
                var condLabel = { good: 'Good', monitor: 'Monitor', needs_treatment: 'Needs Treatment', extracted: 'Extracted' };
                data.dentalRecords.forEach(function (r) {
                    var cond = (r.toothCondition || 'good').toLowerCase();
                    html += '<div class="mp-rec-item">';
                    html += '<div class="mp-rec-row">';
                    html += '<span class="mp-rec-tooth">Tooth ' + mpEsc(r.toothNumber || '—') + '</span>';
                    html += '<span class="mp-rec-date">' + mpEsc(r.recordDate ? r.recordDate.substring(0, 10) : '') + '</span>';
                    html += '</div>';
                    html += '<div class="mp-rec-diag">' + mpEsc(r.diagnosis) + '</div>';
                    html += '<span class="mp-rec-cond ' + mpEsc(cond) + '">' + mpEsc(condLabel[cond] || mpCap(cond)) + '</span>';
                    html += '</div>';
                });
            }

            html += '</div>';
            body.innerHTML = html;
        })
        .catch(function () {
            body.innerHTML = '<div class="mp-modal-err">Error loading history. Please try again.</div>';
        });
}

/* ── View (full profile) modal ─────────────────────────────────── */
function mpOpenView(pid, name) {
    var overlay = document.getElementById('mpViewOverlay');
    var title   = document.getElementById('mpViewTitle');
    var body    = document.getElementById('mpViewBody');

    title.textContent = name + ' — Profile';
    body.innerHTML    = '<div class="mp-modal-loading">Loading profile…</div>';
    overlay.classList.add('open');

    fetch('get_patient_details.php?patientID=' + pid)
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) {
                body.innerHTML = '<div class="mp-modal-err">Failed to load profile.</div>';
                return;
            }

            var p = data.patient;
            var s = data.appointmentSummary || {};
            var html = '';

            /* Personal info */
            html += '<div class="mp-modal-sec">';
            html += '<div class="mp-modal-sec-title">Personal Information</div>';
            html += '<div class="mp-modal-grid">';
            html += '<div class="mp-modal-field"><label>Full Name</label><p>' + mpEsc(p.username) + '</p></div>';
            html += '<div class="mp-modal-field"><label>Email</label><p>' + mpEsc(p.userEmail) + '</p></div>';
            html += '<div class="mp-modal-field"><label>Phone</label><p>' + mpEsc(p.userPhone || '—') + '</p></div>';
            var agegen = (p.userAge ? mpEsc(p.userAge) : '') + (p.userAge && p.userGender ? ' / ' : '') + (p.userGender ? mpEsc(mpCap(p.userGender)) : '');
            html += '<div class="mp-modal-field"><label>Age / Gender</label><p>' + (agegen || '—') + '</p></div>';
            html += '<div class="mp-modal-field"><label>Account Status</label><p>' + mpEsc(mpCap(p.status || 'active')) + '</p></div>';
            html += '<div class="mp-modal-field"><label>Member Since</label><p>' + mpEsc(p.createdDate ? p.createdDate.substring(0, 10) : '—') + '</p></div>';
            html += '</div>';
            if (p.userChronicHealthProblems && p.userChronicHealthProblems.trim()) {
                html += '<div class="mp-modal-field" style="margin-top:10px"><label>Health Conditions</label><p>' + mpEsc(p.userChronicHealthProblems) + '</p></div>';
            }
            html += '</div>';

            /* Appointment summary */
            html += '<div class="mp-modal-sec">';
            html += '<div class="mp-modal-sec-title">Appointment Summary</div>';
            html += '<div class="mp-appt-summary">';
            html += '<div class="mp-sum-box"><div class="mp-sum-num">' + (s.total || 0) + '</div><div class="mp-sum-lbl">Total</div></div>';
            html += '<div class="mp-sum-box"><div class="mp-sum-num" style="color:#16845c">' + (s.completed || 0) + '</div><div class="mp-sum-lbl">Completed</div></div>';
            html += '<div class="mp-sum-box"><div class="mp-sum-num" style="color:#c77712">' + (s.pending || 0) + '</div><div class="mp-sum-lbl">Pending</div></div>';
            html += '<div class="mp-sum-box"><div class="mp-sum-num" style="color:#b42318">' + (s.cancelled || 0) + '</div><div class="mp-sum-lbl">Cancelled</div></div>';
            html += '</div></div>';

            /* Dental records summary */
            html += '<div class="mp-modal-sec">';
            html += '<div class="mp-modal-sec-title">Dental Records</div>';
            var recCount = data.dentalRecords ? data.dentalRecords.length : 0;
            if (recCount === 0) {
                html += '<p class="mp-modal-empty">No dental records on file.</p>';
            } else {
                html += '<p class="mp-modal-empty">' + recCount + ' record' + (recCount !== 1 ? 's' : '') + ' on file.</p>';
            }
            html += '</div>';

            /* Reward points */
            html += '<div class="mp-modal-sec">';
            html += '<div class="mp-modal-sec-title">Reward Points</div>';
            html += '<div class="mp-reward-pts">⭐ ' + (data.rewardBalance || 0) + ' pts</div>';
            html += '</div>';

            /* Quick links */
            html += '<div class="mp-modal-sec">';
            html += '<div class="mp-modal-sec-title">Quick Actions</div>';
            html += '<div class="mp-modal-links">';
            html += '<a class="mp-modal-link purple" href="staff_health_record.php?patient=' + pid + '">🦷 Add Health Record</a>';
            html += '<a class="mp-modal-link blue" href="manage_appointments.php?patient=' + pid + '">📅 View Appointments</a>';
            html += '</div></div>';

            body.innerHTML = html;
        })
        .catch(function () {
            body.innerHTML = '<div class="mp-modal-err">Error loading profile. Please try again.</div>';
        });
}
</script>
    <?php
}
