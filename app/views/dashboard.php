<?php
declare(strict_types=1);

function page_dashboard(array $user): void
{
    // ── Admin / Staff view ──────────────────────────────────────────
    if ($user['userRole'] !== 'patient') {
        $today   = date('Y-m-d');
        $isAdmin = $user['userRole'] === 'admin';
        $hour    = (int) date('G');
        $greeting  = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $firstName = explode(' ', trim((string) ($user['username'] ?? 'there')))[0];

        // Stat data
        $todayAppts  = db_all(
            "SELECT a.*, u.username, u.userPhone
             FROM tbl_appointment a JOIN tbl_user u ON u.userID = a.userID
             WHERE a.appointmentDate = ? ORDER BY a.appointmentTime ASC",
            [$today]
        );
        $todayCnt    = count($todayAppts);
        $pendingCnt  = (int) (db_one("SELECT COUNT(*) AS c FROM tbl_appointment WHERE status = 'pending'")['c'] ?? 0);
        $patientsCnt = (int) (db_one("SELECT COUNT(*) AS c FROM tbl_user WHERE userRole = 'patient' AND status = 'active'")['c'] ?? 0);
        $avgRating   = round((float) (db_one('SELECT AVG(rating) AS r FROM tbl_feedback')['r'] ?? 0), 1);

        // Week chart — Mon to Sat of the current week
        $dow      = (int) date('N');
        $monday   = date('Y-m-d', strtotime('-' . ($dow - 1) . ' days'));
        $saturday = date('Y-m-d', strtotime('+' . (6 - $dow) . ' days'));
        $weekRows = db_all(
            "SELECT DATE(appointmentDate) AS apptDay, COUNT(*) AS cnt
             FROM tbl_appointment WHERE appointmentDate >= ? AND appointmentDate <= ?
             GROUP BY DATE(appointmentDate)",
            [$monday, $saturday]
        );
        $weekMap = [];
        foreach ($weekRows as $r) {
            $weekMap[(string) $r['apptDay']] = (int) $r['cnt'];
        }
        $weekDays = [];
        for ($i = 0; $i < 6; $i++) {
            $d = date('Y-m-d', strtotime($monday . ' +' . $i . ' days'));
            $weekDays[] = [
                'date'  => $d,
                'label' => date('D', strtotime($d)),
                'count' => $weekMap[$d] ?? 0,
                'today' => $d === $today,
            ];
        }
        $weekTotal = array_sum(array_column($weekDays, 'count'));
        $wc        = array_column($weekDays, 'count');
        $weekMax   = max(1, $wc ? max($wc) : 0);

        // Pending approvals list
        $pendingApprovals = db_all(
            "SELECT a.*, u.username, u.userPhone
             FROM tbl_appointment a JOIN tbl_user u ON u.userID = a.userID
             WHERE a.status = 'pending'
             ORDER BY a.appointmentDate ASC, a.appointmentTime ASC LIMIT 10",
            []
        );
        ?>
<style>
/* ── Staff Dashboard ─────────────────────────────────────────── */
.sd-greeting{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:22px}
.sd-greeting h2{font-family:'Sora',sans-serif;font-size:20px;font-weight:700;color:#1a0e2e;margin:0 0 3px}
.sd-greeting p{font-size:13px;color:#72647a;margin:0}
.sd-date-pill{display:flex;align-items:center;gap:7px;background:#fff;border:1.5px solid #e5ddf5;border-radius:20px;padding:8px 16px;font-size:13px;font-weight:600;color:#3b0764;white-space:nowrap;box-shadow:0 1px 4px rgba(59,7,100,.06)}

/* Stat cards */
.sd-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px}
@media(max-width:900px){.sd-stats{grid-template-columns:repeat(2,1fr)}}
.sd-stat{background:#fff;border-radius:12px;border:1px solid #ede8f8;border-left:3.5px solid transparent;padding:18px 16px 16px;box-shadow:0 2px 8px rgba(59,7,100,.05);transition:box-shadow .18s}
.sd-stat:hover{box-shadow:0 4px 16px rgba(59,7,100,.10)}
.sd-stat.purple{border-left-color:#7c3aed}
.sd-stat.amber{border-left-color:#c77712}
.sd-stat.blue{border-left-color:#1686c2}
.sd-stat.green{border-left-color:#16845c}
.sd-stat-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;margin-bottom:10px}
.sd-stat-icon.purple{background:#f3f0ff}
.sd-stat-icon.amber{background:#fff8e6}
.sd-stat-icon.blue{background:#e8f4fd}
.sd-stat-icon.green{background:#eaf3de}
.sd-stat-num{font-family:'Sora',sans-serif;font-size:28px;font-weight:700;color:#1a0e2e;line-height:1;margin-bottom:4px}
.sd-stat-lbl{font-size:12px;color:#72647a;font-weight:500}

/* Quick actions */
.sd-actions{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px}
@media(max-width:900px){.sd-actions{grid-template-columns:repeat(2,1fr)}}
.sd-action{background:#fff;border:1.5px solid #ede8f8;border-radius:12px;padding:20px 14px 16px;text-align:center;text-decoration:none;display:flex;flex-direction:column;align-items:center;gap:10px;transition:all .18s;box-shadow:0 2px 8px rgba(59,7,100,.04)}
.sd-action:hover{border-color:#c4b2f0;background:#faf9ff;box-shadow:0 4px 16px rgba(124,58,237,.10)}
.sd-action-icon{width:46px;height:46px;border-radius:12px;background:#f3f0ff;display:flex;align-items:center;justify-content:center;font-size:20px}
.sd-action-lbl{font-size:13px;font-weight:600;color:#1a0e2e}

/* Two-column row */
.sd-two-col{display:grid;grid-template-columns:1.7fr 1fr;gap:18px;margin-bottom:22px}
@media(max-width:900px){.sd-two-col{grid-template-columns:1fr}}
.sd-sec-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.sd-sec-hd h2{font-family:'Sora',sans-serif;font-size:15px;font-weight:700;color:#1a0e2e;margin:0}
.sd-sec-hd a{font-size:12.5px;color:#7c3aed;font-weight:600;text-decoration:none}
.sd-sec-hd a:hover{text-decoration:underline}

/* Today's schedule cards */
.sd-appt-list{display:flex;flex-direction:column;gap:10px}
.sd-appt-card{background:#f9f7fe;border:1px solid #ede8f8;border-radius:10px;padding:12px 14px;display:flex;align-items:flex-start;gap:12px;transition:border-color .15s}
.sd-appt-card:hover{border-color:#c4b2f0}
.sd-time-badge{background:#eeedfe;border-radius:8px;padding:8px 10px;text-align:center;min-width:50px;flex-shrink:0}
.sd-time-h{font-family:'Sora',sans-serif;font-size:15px;font-weight:700;color:#3b0764;line-height:1}
.sd-time-m{font-size:10.5px;font-weight:700;color:#7c3aed;margin-top:2px}
.sd-appt-body{flex:1;min-width:0}
.sd-appt-name{font-weight:700;font-size:14px;color:#1a0e2e;margin-bottom:2px}
.sd-appt-svc{font-size:12.5px;color:#72647a;margin-bottom:6px}
.sd-appt-meta{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-bottom:8px}
.sd-badge{display:inline-flex;align-items:center;padding:2px 9px;border-radius:100px;font-size:11px;font-weight:600}
.sd-badge.pending{background:#fff8e6;color:#c77712}
.sd-badge.confirmed{background:#e8f4fd;color:#1686c2}
.sd-badge.completed{background:#eaf3de;color:#16845c}
.sd-badge.cancelled{background:#fcebeb;color:#b42318}
.sd-appt-btns{display:flex;gap:6px;flex-wrap:wrap}
.sd-btn{display:inline-flex;align-items:center;gap:4px;padding:5px 12px;border-radius:6px;font-size:11.5px;font-weight:600;border:none;cursor:pointer;transition:all .15s;white-space:nowrap;text-decoration:none;font-family:'DM Sans',sans-serif;line-height:1}
.sd-btn:disabled{opacity:.5;cursor:not-allowed}
.sd-btn-confirm{background:#eaf3de;color:#16845c}.sd-btn-confirm:hover:not(:disabled){background:#d1edbc}
.sd-btn-complete{background:#f3f0ff;color:#5b21b6}.sd-btn-complete:hover:not(:disabled){background:#e3dcfc}
.sd-btn-cancel{background:#fcebeb;color:#b42318}.sd-btn-cancel:hover:not(:disabled){background:#fbd5d5}
.sd-btn-record{background:#e8f4fd;color:#1686c2}.sd-btn-record:hover{background:#cce8f9}

/* Week chart */
.sd-chart-wrap{height:100px;display:flex;align-items:flex-end;gap:5px;margin:10px 0 0}
.sd-bar-col{display:flex;flex-direction:column;align-items:center;flex:1;gap:3px}
.sd-bar-fill{width:100%;min-height:3px;border-radius:3px 3px 0 0;transition:height .5s ease}
.sd-bar-fill.bar-today{background:linear-gradient(180deg,#a855f7,#7c3aed)}
.sd-bar-fill.bar-norm{background:linear-gradient(180deg,#c4b2f0,#a78bdb)}
.sd-bar-cnt{font-size:9.5px;font-weight:700;color:#7c3aed;min-height:13px}
.sd-chart-xaxis{display:flex;gap:5px;margin-top:4px}
.sd-chart-xlabel{flex:1;text-align:center;font-size:9.5px;color:#72647a;font-weight:500}
.sd-week-summary{font-size:12px;color:#72647a;margin-top:10px}
.sd-week-summary strong{color:#1a0e2e}

/* Pending approvals */
.sd-badge-count{display:inline-flex;align-items:center;justify-content:center;background:#ef4444;color:#fff;border-radius:100px;font-size:11px;font-weight:700;padding:1px 8px;margin-left:7px;min-width:22px}
.sd-pending-row{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #f0ebf8;transition:opacity .3s}
.sd-pending-row:last-child{border-bottom:none}
.sd-pending-info{flex:1;min-width:0}
.sd-pending-name{font-weight:700;font-size:13.5px;color:#1a0e2e}
.sd-pending-detail{font-size:12px;color:#72647a;margin-top:2px}
.sd-pending-btns{display:flex;gap:6px;flex-shrink:0}
.sd-empty{text-align:center;padding:28px;color:#72647a;font-size:13.5px}
</style>

<!-- Greeting -->
<div class="sd-greeting">
    <div>
        <h2><?= e($greeting) ?>, <?= e($firstName) ?>! 👋</h2>
        <p>Here's what's happening at Putra Dental Clinic today.</p>
    </div>
    <div class="sd-date-pill">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="width:14px;height:14px"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
        <?= e(date('l, j F Y')) ?>
    </div>
</div>

<!-- Stat Cards -->
<div class="sd-stats">
    <div class="sd-stat purple">
        <div class="sd-stat-icon purple">📅</div>
        <div class="sd-stat-num" id="sd-stat-today"><?= $todayCnt ?></div>
        <div class="sd-stat-lbl">Today's Appointments</div>
    </div>
    <div class="sd-stat amber">
        <div class="sd-stat-icon amber">⏳</div>
        <div class="sd-stat-num" id="sd-stat-pending"><?= $pendingCnt ?></div>
        <div class="sd-stat-lbl">Pending Approval</div>
    </div>
    <div class="sd-stat blue">
        <div class="sd-stat-icon blue">👥</div>
        <div class="sd-stat-num"><?= $patientsCnt ?></div>
        <div class="sd-stat-lbl">Registered Patients</div>
    </div>
    <div class="sd-stat green">
        <div class="sd-stat-icon green">⭐</div>
        <div class="sd-stat-num"><?= $avgRating > 0 ? $avgRating . ' ★' : '—' ?></div>
        <div class="sd-stat-lbl">Avg. Patient Rating</div>
    </div>
</div>

<!-- Quick Actions -->
<div class="sd-actions">
    <a href="<?= e(page_url('appointments')) ?>" class="sd-action">
        <div class="sd-action-icon">📅</div>
        <span class="sd-action-lbl">Manage Appointments</span>
    </a>
    <a href="<?= e(page_url('patients')) ?>" class="sd-action">
        <div class="sd-action-icon">👥</div>
        <span class="sd-action-lbl">View Patients</span>
    </a>
    <a href="<?= e(page_url('staff_health_record')) ?>" class="sd-action">
        <div class="sd-action-icon">🦷</div>
        <span class="sd-action-lbl">Health Records</span>
    </a>
    <a href="<?= e(page_url('feedback')) ?>" class="sd-action">
        <div class="sd-action-icon">💬</div>
        <span class="sd-action-lbl">Manage Feedback</span>
    </a>
</div>

<!-- Two-column: Today's Schedule + Week Chart -->
<div class="sd-two-col">

    <!-- Today's Schedule -->
    <div class="panel">
        <div class="sd-sec-hd">
            <h2>Today's Schedule</h2>
            <a href="<?= e(page_url('appointments')) ?>">Manage All →</a>
        </div>
        <?php if (empty($todayAppts)): ?>
            <div class="sd-empty">No appointments scheduled for today.</div>
        <?php else: ?>
        <div class="sd-appt-list">
            <?php foreach ($todayAppts as $a):
                $aID  = (int) $a['appointmentID'];
                $aSt  = (string) $a['status'];
                $aTm  = substr((string) $a['appointmentTime'], 0, 5);
                $aTmp = explode(':', $aTm);
                $aDn  = extract_dentist_name((string) ($a['notes'] ?? ''));
                $aHlth = format_appointment_health_summary($a);
            ?>
            <div class="sd-appt-card" id="sd-today-<?= $aID ?>" data-appt-id="<?= $aID ?>">
                <div class="sd-time-badge">
                    <div class="sd-time-h"><?= e($aTmp[0] ?? $aTm) ?></div>
                    <div class="sd-time-m"><?= isset($aTmp[1]) ? ':' . $aTmp[1] : '' ?></div>
                </div>
                <div class="sd-appt-body">
                    <div class="sd-appt-name"><?= e($a['username'] ?? 'Patient') ?></div>
                    <div class="sd-appt-svc"><?= e($a['serviceType']) ?> · <?= e(format_duration((int) $a['duration'])) ?><?= $aDn !== '' ? ' · ' . e($aDn) : '' ?></div>
                    <div class="sd-appt-meta">
                        <span class="sd-badge <?= e($aSt) ?>"><?= e(ucfirst($aSt)) ?></span>
                        <span style="font-size:11.5px;color:#9b8ad4"><?= e($aHlth) ?></span>
                    </div>
                    <div class="sd-appt-btns" id="sd-btns-today-<?= $aID ?>">
                        <?php if ($aSt === 'pending'): ?>
                            <button class="sd-btn sd-btn-confirm" onclick="sdUpdateStatus(<?= $aID ?>, 'confirmed', this)">✓ Confirm</button>
                            <button class="sd-btn sd-btn-cancel" onclick="sdUpdateStatus(<?= $aID ?>, 'cancelled', this)">✕ Cancel</button>
                        <?php elseif ($aSt === 'confirmed'): ?>
                            <button class="sd-btn sd-btn-complete" onclick="sdUpdateStatus(<?= $aID ?>, 'completed', this)">✓ Complete</button>
                            <button class="sd-btn sd-btn-cancel" onclick="sdUpdateStatus(<?= $aID ?>, 'cancelled', this)">✕ Cancel</button>
                        <?php elseif ($aSt === 'completed'): ?>
                            <a class="sd-btn sd-btn-record" href="<?= e(page_url('healthbook')) ?>">+ Add Record</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- This Week Chart -->
    <div class="panel">
        <div class="sd-sec-hd">
            <h2>This Week</h2>
        </div>
        <div class="sd-chart-wrap">
            <?php foreach ($weekDays as $wd):
                $barH = $weekMax > 0 ? (int) round($wd['count'] / $weekMax * 90) : 0;
            ?>
            <div class="sd-bar-col">
                <div class="sd-bar-cnt"><?= $wd['count'] > 0 ? $wd['count'] : '' ?></div>
                <div class="sd-bar-fill <?= $wd['today'] ? 'bar-today' : 'bar-norm' ?>" style="height:<?= max(3, $barH) ?>px"></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="sd-chart-xaxis">
            <?php foreach ($weekDays as $wd): ?>
            <div class="sd-chart-xlabel" style="<?= $wd['today'] ? 'color:#7c3aed;font-weight:700' : '' ?>"><?= e($wd['label']) ?></div>
            <?php endforeach; ?>
        </div>
        <div class="sd-week-summary">
            <strong><?= $weekTotal ?></strong> appointment<?= $weekTotal !== 1 ? 's' : '' ?> this week
        </div>
    </div>
</div>

<!-- Pending Approvals -->
<div class="panel">
    <div class="sd-sec-hd">
        <h2>
            Pending Approvals
            <?php if (count($pendingApprovals) > 0): ?>
                <span class="sd-badge-count" id="sd-pending-badge"><?= count($pendingApprovals) ?></span>
            <?php endif; ?>
        </h2>
        <a href="<?= e(page_url('appointments')) ?>">View All →</a>
    </div>
    <?php if (empty($pendingApprovals)): ?>
        <div class="sd-empty">No pending approvals. All caught up! ✅</div>
    <?php else: ?>
    <div id="sd-pending-list">
        <?php foreach ($pendingApprovals as $p):
            $pID  = (int) $p['appointmentID'];
            $pDt  = strtotime((string) $p['appointmentDate']);
            $pTm  = substr((string) $p['appointmentTime'], 0, 5);
        ?>
        <div class="sd-pending-row" id="sd-pend-<?= $pID ?>" data-appt-id="<?= $pID ?>">
            <div class="sd-pending-info">
                <div class="sd-pending-name"><?= e($p['username'] ?? 'Patient') ?></div>
                <div class="sd-pending-detail">
                    <?= e(date('d M Y', $pDt)) ?> at <?= e($pTm) ?> · <?= e($p['serviceType']) ?>
                </div>
            </div>
            <div class="sd-pending-btns">
                <button class="sd-btn sd-btn-confirm" onclick="sdUpdateStatus(<?= $pID ?>, 'confirmed', this)">✓ Confirm</button>
                <button class="sd-btn sd-btn-cancel" onclick="sdUpdateStatus(<?= $pID ?>, 'cancelled', this)">✕ Cancel</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
window.SD_HEALTHBOOK_URL = <?= json_encode(page_url('healthbook')) ?>;

window.sdUpdateStatus = function (apptID, newStatus, btn) {
    if (newStatus === 'cancelled' && !confirm('Cancel this appointment?')) return;

    // Check if it was pending before we disable the buttons
    var badge = document.querySelector('[data-appt-id="' + apptID + '"] .sd-badge');
    var wasPending = badge && badge.classList.contains('pending');

    var btns = document.querySelectorAll('[data-appt-id="' + apptID + '"] .sd-btn');
    btns.forEach(function (b) { b.disabled = true; });
    btn.textContent = '…';

    var fd = new FormData();
    fd.append('_csrf_token', window.DETABOT_CSRF || '');
    fd.append('appointmentID', apptID);
    fd.append('newStatus', newStatus);

    fetch('update_appointment_status.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                sdApplyStatus(apptID, newStatus);
                if (wasPending && newStatus !== 'pending') {
                    sdDecrPending();
                }
            } else {
                alert(d.error || 'Failed to update status.');
                document.querySelectorAll('[data-appt-id="' + apptID + '"] .sd-btn')
                    .forEach(function (b) { b.disabled = false; });
            }
        })
        .catch(function () {
            alert('Network error. Please try again.');
            document.querySelectorAll('[data-appt-id="' + apptID + '"] .sd-btn')
                .forEach(function (b) { b.disabled = false; });
        });
};

function sdApplyStatus(apptID, newStatus) {
    // Update status badges everywhere this apptID appears
    document.querySelectorAll('[data-appt-id="' + apptID + '"] .sd-badge').forEach(function (el) {
        el.className = 'sd-badge ' + newStatus;
        el.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
    });

    // Update today-schedule action buttons
    var todayBtns = document.getElementById('sd-btns-today-' + apptID);
    if (todayBtns) {
        if (newStatus === 'confirmed') {
            todayBtns.innerHTML =
                '<button class="sd-btn sd-btn-complete" onclick="sdUpdateStatus(' + apptID + ', \'completed\', this)">✓ Complete</button>' +
                '<button class="sd-btn sd-btn-cancel" onclick="sdUpdateStatus(' + apptID + ', \'cancelled\', this)">✕ Cancel</button>';
        } else if (newStatus === 'completed') {
            todayBtns.innerHTML = '<a class="sd-btn sd-btn-record" href="' + (window.SD_HEALTHBOOK_URL || '') + '">+ Add Record</a>';
        } else {
            todayBtns.innerHTML = '';
        }
    }

    // Remove from pending approvals list when no longer pending
    if (newStatus !== 'pending') {
        var pendRow = document.getElementById('sd-pend-' + apptID);
        if (pendRow) {
            pendRow.style.opacity = '0';
            setTimeout(function () {
                pendRow.remove();
                sdCheckPendingEmpty();
            }, 300);
        }
    }
}

function sdDecrPending() {
    var statEl = document.getElementById('sd-stat-pending');
    if (statEl) {
        var n = parseInt(statEl.textContent, 10) || 0;
        if (n > 0) statEl.textContent = n - 1;
    }
    var badge = document.getElementById('sd-pending-badge');
    if (badge) {
        var nb = parseInt(badge.textContent, 10) || 0;
        if (nb > 1) { badge.textContent = nb - 1; }
        else { badge.style.display = 'none'; }
    }
}

function sdCheckPendingEmpty() {
    var list = document.getElementById('sd-pending-list');
    if (list && !list.querySelector('.sd-pending-row')) {
        list.innerHTML = '<div class="sd-empty">No pending approvals. All caught up! ✅</div>';
    }
}
</script>
        <?php
        return;
    }

    // ── Patient dashboard data ───────────────────────────────────────────────
    $uid   = (int) $user['userID'];
    $today = date('Y-m-d');

    // Greeting
    $hour      = (int) date('G');
    $greeting  = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $firstName = explode(' ', trim((string) ($user['username'] ?? 'there')))[0];

    // Stat counts
    $rewardBal    = reward_balance($uid);
    $totalAppts   = (int) (db_one("SELECT COUNT(*) AS c FROM tbl_appointment WHERE userID = ?", [$uid])['c'] ?? 0);
    $completedCnt = (int) (db_one("SELECT COUNT(*) AS c FROM tbl_appointment WHERE userID = ? AND status = 'completed'", [$uid])['c'] ?? 0);
    $pendingCnt   = (int) (db_one("SELECT COUNT(*) AS c FROM tbl_appointment WHERE userID = ? AND status = 'pending'", [$uid])['c'] ?? 0);
    $unpaidCnt    = (int) (db_one(
        "SELECT COUNT(*) AS c FROM tbl_appointment
         WHERE userID = ? AND status = 'confirmed' AND (paymentStatus = 'unpaid' OR paymentStatus = 'rejected')",
        [$uid]
    )['c'] ?? 0);

    // Next upcoming appointment
    $nextAppt = db_one(
        "SELECT * FROM tbl_appointment
         WHERE userID = ? AND status IN ('pending','confirmed') AND appointmentDate >= ?
         ORDER BY appointmentDate ASC, appointmentTime ASC LIMIT 1",
        [$uid, $today]
    );

    // Recent 5 appointments for table
    $recentAppts = db_all(
        "SELECT * FROM tbl_appointment WHERE userID = ? ORDER BY appointmentDate DESC, appointmentTime DESC LIMIT 5",
        [$uid]
    );

    // Dental records for health score
    $dentalRecords  = db_all(
        "SELECT toothCondition FROM tbl_dental_record WHERE userID = ? ORDER BY recordDate DESC LIMIT 20",
        [$uid]
    );

    // Health score calculation
    $healthScore = null;
    $healthLabel = 'No data yet';
    $healthColor = '#a78bdb';

    if (!empty($dentalRecords)) {
        $conds          = array_column($dentalRecords, 'toothCondition');
        $total          = count($conds);
        $goodCnt        = count(array_filter($conds, fn($c) => $c === 'good'));
        $monitorCnt     = count(array_filter($conds, fn($c) => $c === 'monitor'));
        $needsCnt       = count(array_filter($conds, fn($c) => $c === 'needs_treatment'));

        if ($needsCnt > 0) {
            $healthScore = max(20, (int) round(50 - ($needsCnt / $total * 30)));
        } elseif ($monitorCnt > 0) {
            $healthScore = (int) round(60 + ($goodCnt / $total * 22));
        } else {
            $healthScore = (int) min(98, 88 + $goodCnt);
        }
        if ($healthScore >= 85)     { $healthLabel = 'Excellent';       $healthColor = '#16845c'; }
        elseif ($healthScore >= 65) { $healthLabel = 'Good';            $healthColor = '#3b82f6'; }
        elseif ($healthScore >= 45) { $healthLabel = 'Fair';            $healthColor = '#c77712'; }
        else                        { $healthLabel = 'Needs Attention'; $healthColor = '#b42318'; }
    }

    // SVG ring
    $ringR    = 44;
    $ringCirc = round(2 * M_PI * $ringR, 2);
    $ringFinalOffset = $healthScore !== null ? round($ringCirc * (1 - $healthScore / 100), 2) : $ringCirc;

    // Rewards progress toward first tier (80 pts)
    $firstTierPts = 80;
    $rewardPct    = $rewardBal >= $firstTierPts ? 100 : (int) round($rewardBal / $firstTierPts * 100);
    $ptsNeeded    = max(0, $firstTierPts - $rewardBal);
    ?>

<style>
/* ── Dashboard layout ─────────────────────────────────────────── */
.db-greeting-bar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:22px}
.db-greeting-bar h2{font-family:'Sora',sans-serif;font-size:21px;font-weight:700;color:#1a0e2e;margin:0 0 4px}
.db-greeting-bar p{font-size:13.5px;color:#72647a;margin:0}
.db-date-pill{display:flex;align-items:center;gap:7px;background:#fff;border:1.5px solid #e5ddf5;border-radius:20px;padding:8px 16px;font-size:13px;font-weight:600;color:#3b0764;white-space:nowrap;box-shadow:0 1px 4px rgba(59,7,100,.06)}

/* ── Stat cards ───────────────────────────────────────────────── */
.db-stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
@media(max-width:900px){.db-stats-grid{grid-template-columns:repeat(2,1fr)}}
.db-stat-card{background:#fff;border-radius:12px;border:1px solid #ede8f8;padding:18px 16px 16px;box-shadow:0 2px 8px rgba(59,7,100,.05);border-left:3.5px solid transparent;transition:box-shadow .18s}
.db-stat-card:hover{box-shadow:0 4px 16px rgba(59,7,100,.10)}
.db-stat-card.amber{border-left-color:#c77712}
.db-stat-card.blue{border-left-color:#1686c2}
.db-stat-card.green{border-left-color:#16845c}
.db-stat-card.purple{border-left-color:#7c3aed}
.db-stat-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;margin-bottom:10px}
.db-stat-icon.amber{background:#fff8e6}
.db-stat-icon.blue{background:#e8f4fd}
.db-stat-icon.green{background:#eaf3de}
.db-stat-icon.purple{background:#f3f0ff}
.db-stat-num{font-family:'Sora',sans-serif;font-size:26px;font-weight:700;color:#1a0e2e;line-height:1;margin-bottom:4px}
.db-stat-lbl{font-size:12px;color:#72647a;font-weight:500}

/* ── Quick action cards ───────────────────────────────────────── */
.db-actions-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px}
@media(max-width:900px){.db-actions-grid{grid-template-columns:repeat(2,1fr)}}
.db-action-card{background:#fff;border:1.5px solid #ede8f8;border-radius:12px;padding:20px 14px 16px;text-align:center;cursor:pointer;text-decoration:none;display:flex;flex-direction:column;align-items:center;gap:10px;transition:all .18s;box-shadow:0 2px 8px rgba(59,7,100,.04)}
.db-action-card:hover{border-color:#c4b2f0;background:#faf9ff;box-shadow:0 4px 16px rgba(124,58,237,.10)}
.db-action-icon{width:46px;height:46px;border-radius:12px;background:#f3f0ff;display:flex;align-items:center;justify-content:center;font-size:20px}
.db-action-lbl{font-size:13px;font-weight:600;color:#1a0e2e}

/* ── Two-column rows ──────────────────────────────────────────── */
.db-two-col{display:grid;grid-template-columns:1.8fr 1fr;gap:18px;margin-bottom:22px}
@media(max-width:900px){.db-two-col{grid-template-columns:1fr}}

/* ── Next appointment card ────────────────────────────────────── */
.db-next-empty{text-align:center;padding:28px 16px;color:#72647a}
.db-next-empty p{font-size:13.5px;margin:0 0 14px}
.db-appt-row{display:flex;align-items:flex-start;gap:14px}
.db-date-badge{border-radius:10px;background:#eeedfe;padding:10px 14px;text-align:center;min-width:54px;flex-shrink:0}
.db-date-badge .db-day{font-family:'Sora',sans-serif;font-size:22px;font-weight:700;color:#3b0764;line-height:1}
.db-date-badge .db-mon{font-size:10.5px;font-weight:700;color:#7c3aed;text-transform:uppercase;letter-spacing:.06em;margin-top:3px}
.db-appt-body{flex:1;min-width:0}
.db-appt-service{font-weight:700;font-size:15px;color:#1a0e2e;margin-bottom:6px}
.db-appt-meta{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px}
.db-appt-meta-item{display:flex;align-items:center;gap:4px;font-size:12.5px;color:#72647a}
.db-appt-meta-item svg{width:13px;height:13px;stroke:#7c3aed;flex-shrink:0}
.db-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:100px;font-size:11.5px;font-weight:600}
.db-badge.pending{background:#fff8e6;color:#c77712}
.db-badge.confirmed{background:#e8f4fd;color:#1686c2}
.db-countdown{display:inline-flex;align-items:center;gap:6px;background:#f3f0ff;border:1px solid #d8d0fc;border-radius:8px;padding:7px 14px;font-size:13px;font-weight:700;color:#5b21b6;margin-top:6px}
.db-countdown svg{width:14px;height:14px;stroke:#7c3aed}

/* ── Health score card ────────────────────────────────────────── */
.db-health-ring-wrap{display:flex;flex-direction:column;align-items:center;padding:10px 0 6px}
.db-health-ring-svg{overflow:visible}
.db-health-ring-bg{fill:none;stroke:#e9e0f4;stroke-width:9}
.db-health-ring-fg{fill:none;stroke-width:9;stroke-linecap:round;transition:stroke-dashoffset 1s cubic-bezier(.4,0,.2,1)}
.db-health-center{text-anchor:middle;dominant-baseline:middle}
.db-health-pct{font-family:'Sora',sans-serif;font-size:20px;font-weight:700;fill:#1a0e2e}
.db-health-lbl-svg{font-size:10px;fill:#72647a;font-weight:500}
.db-health-label{font-size:14px;font-weight:700;margin-top:8px;text-align:center}
.db-health-tip{background:#eaf7f7;border:1px solid #b2ddd8;border-radius:8px;padding:10px 13px;font-size:12.5px;color:#0d6e6e;margin-top:12px;line-height:1.5}
.db-health-tip strong{font-weight:700;display:block;margin-bottom:2px}
.db-health-nodata{text-align:center;padding:20px 0;color:#72647a}
.db-health-nodata p{font-size:13px;margin:8px 0 0}

/* ── Appointments table ───────────────────────────────────────── */
.db-table-filter{display:flex;align-items:center;gap:8px}
.db-table-filter select{padding:6px 10px;border:1.5px solid #e5ddf5;border-radius:7px;font-size:12.5px;font-family:'DM Sans',sans-serif;color:#1a0e2e;background:#fff;outline:none;cursor:pointer}
.db-table-filter select:focus{border-color:#7c3aed}
.db-tbl{width:100%;border-collapse:collapse;font-size:13px}
.db-tbl th{text-align:left;padding:8px 10px;font-size:11px;font-weight:700;color:#72647a;text-transform:uppercase;letter-spacing:.05em;border-bottom:2px solid #ede8f8}
.db-tbl td{padding:10px 10px;border-bottom:1px solid #f0ebf8;vertical-align:middle}
.db-tbl tr:last-child td{border-bottom:none}
.db-tbl tr:hover td{background:#faf8ff}
.db-tbl .db-tbl-date{font-weight:600;color:#3b0764;white-space:nowrap}
.db-tbl .db-tbl-svc{color:#1a0e2e;font-weight:500}
.db-tbl .db-tbl-actions a{font-size:12px;font-weight:600;text-decoration:none;cursor:pointer}
.db-tbl .db-tbl-actions .db-act-cancel{color:#ef4444}
.db-tbl .db-tbl-actions .db-act-feedback{color:#7c3aed}
.db-tbl-empty{text-align:center;padding:28px;color:#72647a;font-size:13.5px}
.db-badge.completed{background:#eaf3de;color:#16845c}
.db-badge.cancelled{background:#fcebeb;color:#b42318}

/* ── Rewards card ─────────────────────────────────────────────── */
.db-rewards-card{background:#3b0764;border-radius:14px;padding:22px;color:#fff;display:flex;flex-direction:column;gap:16px}
.db-rewards-card h3{font-family:'Sora',sans-serif;font-size:16px;font-weight:700;margin:0;display:flex;align-items:center;justify-content:space-between}
.db-rewards-card h3 a{color:#e0d0ff;font-size:13px;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:4px}
.db-rewards-card h3 a:hover{color:#fff}
.db-reward-bal{font-family:'Sora',sans-serif;font-size:36px;font-weight:800;line-height:1;color:#fff}
.db-reward-bal span{font-size:16px;font-weight:500;color:#c4a8e8;margin-left:4px}
.db-reward-sub{font-size:12.5px;color:#c4a8e8;margin-top:2px}
.db-reward-prog-wrap{display:flex;flex-direction:column;gap:5px}
.db-reward-prog-bar{height:8px;background:rgba(255,255,255,.15);border-radius:4px;overflow:hidden}
.db-reward-prog-fill{height:100%;background:linear-gradient(90deg,#f472b6,#e879f9);border-radius:4px;transition:width .8s ease}
.db-reward-prog-lbl{font-size:11.5px;color:#d4b8f0;font-weight:500}

/* ── Section header ───────────────────────────────────────────── */
.db-sec-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.db-sec-hd h2{font-family:'Sora',sans-serif;font-size:15px;font-weight:700;color:#1a0e2e;margin:0}
.db-sec-hd a{font-size:12.5px;color:#7c3aed;font-weight:600;text-decoration:none}
.db-sec-hd a:hover{text-decoration:underline}
</style>

<!-- ── Section 1: Greeting bar ── -->
<div class="db-greeting-bar">
    <div>
        <h2><?= e($greeting) ?>, <?= e($firstName) ?>! 👋</h2>
    </div>
    <div class="db-date-pill">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="width:14px;height:14px"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
        <?= e(date('l, j F Y')) ?>
    </div>
</div>

<!-- ── Section 2: Stat cards ── -->
<div class="db-stats-grid">
    <div class="db-stat-card amber">
        <div class="db-stat-icon amber">⭐</div>
        <div class="db-stat-num"><?= $rewardBal ?></div>
        <div class="db-stat-lbl">Reward Points</div>
    </div>
    <div class="db-stat-card blue">
        <div class="db-stat-icon blue">📅</div>
        <div class="db-stat-num"><?= $totalAppts ?></div>
        <div class="db-stat-lbl">Total Appointments</div>
    </div>
    <div class="db-stat-card green">
        <div class="db-stat-icon green">✅</div>
        <div class="db-stat-num"><?= $completedCnt ?></div>
        <div class="db-stat-lbl">Completed Visits</div>
    </div>
    <div class="db-stat-card purple">
        <div class="db-stat-icon purple">⏳</div>
        <div class="db-stat-num"><?= $pendingCnt ?></div>
        <div class="db-stat-lbl">Pending</div>
    </div>
</div>

<?php if ($unpaidCnt > 0): ?>
<!-- ── Payment notice ── -->
<div style="background:#fff8e6;border:1.5px solid #f5d78f;border-radius:12px;padding:16px 20px;margin-bottom:18px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
    <div style="font-size:28px;flex-shrink:0">💳</div>
    <div style="flex:1;min-width:0">
        <div style="font-weight:700;font-size:14px;color:#92400e;margin-bottom:3px">Payment Required</div>
        <div style="font-size:13px;color:#c77712">You have <strong><?= $unpaidCnt ?></strong> confirmed appointment<?= $unpaidCnt !== 1 ? 's' : '' ?> with pending payment. Pay online to secure your slot.</div>
    </div>
    <a href="<?= e(page_url('appointments')) ?>#paymentSection" class="btn primary" style="font-size:12.5px;padding:8px 16px;white-space:nowrap;background:#c77712;border-color:#c77712">Pay Now</a>
</div>
<?php endif; ?>

<!-- ── Section 3: Quick actions ── -->
<div class="db-actions-grid">
    <a href="<?= e(page_url('appointments')) ?>" class="db-action-card">
        <div class="db-action-icon">📅</div>
        <span class="db-action-lbl">Book Appointment</span>
    </a>
    <button type="button" class="db-action-card" onclick="document.getElementById('chatbotBtn').click()">
        <div class="db-action-icon">🤖</div>
        <span class="db-action-lbl">Ask Detabot</span>
    </button>
    <a href="<?= e(page_url('healthbook')) ?>" class="db-action-card">
        <div class="db-action-icon">📖</div>
        <span class="db-action-lbl">Health Record</span>
    </a>
    <a href="<?= e(page_url('rewards')) ?>" class="db-action-card">
        <div class="db-action-icon">🎁</div>
        <span class="db-action-lbl">My Rewards</span>
    </a>
</div>

<!-- ── Section 4: Next appointment + Health score ── -->
<div class="db-two-col">

    <!-- Next Appointment -->
    <div class="panel">
        <div class="db-sec-hd">
            <h2>Next Appointment</h2>
            <a href="<?= e(page_url('appointments')) ?>" class="btn small" style="font-size:12.5px;padding:6px 14px">+ Book New</a>
        </div>
        <?php if ($nextAppt): ?>
            <?php
            $nTs  = strtotime((string) $nextAppt['appointmentDate']);
            $nTm  = substr((string) $nextAppt['appointmentTime'], 0, 5);
            $nSt  = (string) $nextAppt['status'];
            $nDn  = extract_dentist_name((string) ($nextAppt['notes'] ?? ''));
            ?>
            <div class="db-appt-row">
                <div class="db-date-badge">
                    <div class="db-day"><?= e(date('d', $nTs)) ?></div>
                    <div class="db-mon"><?= e(date('M', $nTs)) ?></div>
                </div>
                <div class="db-appt-body">
                    <div class="db-appt-service"><?= e($nextAppt['serviceType']) ?></div>
                    <div class="db-appt-meta">
                        <span class="db-appt-meta-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <?= e($nTm) ?>
                        </span>
                        <span class="db-appt-meta-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                            <?= e($nDn) ?>
                        </span>
                        <span class="db-appt-meta-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v4l3 3"/></svg>
                            <?= e(format_duration((int) $nextAppt['duration'])) ?>
                        </span>
                        <span class="db-badge <?= e($nSt) ?>"><?= e(ucfirst($nSt)) ?></span>
                    </div>
                    <div class="db-countdown" id="dbCountdown" data-date="<?= e($nextAppt['appointmentDate']) ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span id="dbCountdownText">—</span>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="db-next-empty">
                <p>No upcoming appointments.<br>Book one to get started! 🦷</p>
                <a href="<?= e(page_url('appointments')) ?>" class="btn primary" style="font-size:13px">Book Now</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Dental Health Score -->
    <div class="panel">
        <div class="db-sec-hd">
            <h2>Dental Health Score</h2>
            <a href="<?= e(page_url('healthbook')) ?>">View Record →</a>
        </div>

        <?php if ($healthScore !== null): ?>
            <div class="db-health-ring-wrap">
                <svg class="db-health-ring-svg" width="110" height="110" viewBox="0 0 110 110">
                    <circle class="db-health-ring-bg" cx="55" cy="55" r="<?= $ringR ?>"/>
                    <circle class="db-health-ring-fg"
                        id="healthRingFg"
                        cx="55" cy="55" r="<?= $ringR ?>"
                        stroke="<?= e($healthColor) ?>"
                        stroke-dasharray="<?= $ringCirc ?>"
                        stroke-dashoffset="<?= $ringCirc ?>"
                        data-offset="<?= $ringFinalOffset ?>"
                        transform="rotate(-90 55 55)"/>
                    <text class="db-health-center" x="55" y="51">
                        <tspan class="db-health-pct" fill="<?= e($healthColor) ?>"><?= $healthScore ?>%</tspan>
                    </text>
                    <text class="db-health-center db-health-lbl-svg" x="55" y="67"><?= e($healthLabel) ?></text>
                </svg>
                <div class="db-health-label" style="color:<?= e($healthColor) ?>"><?= e($healthLabel) ?></div>
            </div>
        <?php else: ?>
            <div class="db-health-nodata">
                <svg viewBox="0 0 110 110" width="96" height="96">
                    <circle cx="55" cy="55" r="44" fill="none" stroke="#e9e0f4" stroke-width="9"/>
                    <text text-anchor="middle" dominant-baseline="middle" x="55" y="55" font-size="13" fill="#a78bdb" font-family="DM Sans, sans-serif">—</text>
                </svg>
                <p>No dental records yet.<br>Visit us for your first checkup!</p>
            </div>
        <?php endif; ?>

        <div class="db-health-tip">
            <strong>💡 Daily Tip</strong>
            <span id="dbDailyTip">Loading tip…</span>
        </div>
    </div>
</div>

<!-- ── Section 5: Appointments table + Rewards ── -->
<div class="db-two-col">

    <!-- My Appointments table -->
    <div class="panel">
        <div class="db-sec-hd">
            <h2>My Appointments</h2>
            <div style="display:flex;align-items:center;gap:10px">
                <div class="db-table-filter">
                    <select id="dbStatusFilter" onchange="filterDashTable()">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <a href="<?= e(page_url('appointments')) ?>">View All →</a>
            </div>
        </div>

        <?php if (empty($recentAppts)): ?>
            <div class="db-tbl-empty">No appointments yet. <a href="<?= e(page_url('appointments')) ?>" style="color:#7c3aed;font-weight:600">Book one now!</a></div>
        <?php else: ?>
        <div id="dbApptTableWrap">
            <table class="db-tbl">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Service</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="dbApptTableBody">
                <?php foreach ($recentAppts as $a):
                    $ast  = (string) $a['status'];
                    $ats  = strtotime((string) $a['appointmentDate']);
                    $atm  = substr((string) $a['appointmentTime'], 0, 5);
                    $aId  = (int) $a['appointmentID'];
                    $aSvc = (string) $a['serviceType'];
                ?>
                    <tr data-status="<?= e($ast) ?>">
                        <td class="db-tbl-date">
                            <?= e(date('d M Y', $ats)) ?><br>
                            <span style="font-size:11.5px;color:#72647a;font-weight:400"><?= e($atm) ?></span>
                        </td>
                        <td class="db-tbl-svc"><?= e($aSvc) ?></td>
                        <td><span class="db-badge <?= e($ast) ?>"><?= e(ucfirst($ast)) ?></span></td>
                        <td class="db-tbl-actions">
                            <?php if (in_array($ast, ['pending', 'confirmed'])): ?>
                                <a class="db-act-cancel" href="#" onclick="dbQuickCancel(<?= $aId ?>,this);return false">Cancel</a>
                            <?php elseif ($ast === 'completed'): ?>
                                <a class="db-act-feedback" href="<?= e(page_url('appointments')) ?>">Feedback</a>
                            <?php else: ?>
                                <span style="color:#c4b8d4">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Rewards card -->
    <div class="db-rewards-card">
        <h3>
            🎁 Rewards
            <a href="<?= e(page_url('rewards')) ?>">
                View All
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="width:13px;height:13px"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
        </h3>
        <div>
            <div class="db-reward-bal"><?= $rewardBal ?><span>pts</span></div>
            <div class="db-reward-sub">Earn 20 points per completed visit</div>
        </div>
        <div class="db-reward-prog-wrap">
            <div class="db-reward-prog-bar">
                <div class="db-reward-prog-fill" id="dbRewardFill" style="width:0%" data-pct="<?= $rewardPct ?>"></div>
            </div>
            <?php if ($ptsNeeded > 0): ?>
                <div class="db-reward-prog-lbl"><?= $ptsNeeded ?> points to first reward: RM10 discount</div>
            <?php else: ?>
                <div class="db-reward-prog-lbl">🎉 You've unlocked the RM10 discount reward!</div>
            <?php endif; ?>
        </div>
        <div style="font-size:12px;color:#d4b8f0;border-top:1px solid rgba(255,255,255,.12);padding-top:12px">
            <div style="margin-bottom:5px">🏆 <strong>80 pts</strong> → RM10 discount</div>
            <div style="margin-bottom:5px">🎁 <strong>120 pts</strong> → Free dental kit</div>
            <div>🦷 <strong>180 pts</strong> → Scaling discount</div>
        </div>
    </div>
</div>

<!-- ── Floating Chatbot ── -->
<div class="chatbot-wrapper" id="chatbotWrapper">
    <span class="chatbot-tooltip">Ask Detabot</span>
    <button class="chatbot-btn" id="chatbotBtn" aria-label="Open Detabot chat">
        <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <line x1="16" y1="2" x2="16" y2="7" stroke="white" stroke-width="2" stroke-linecap="round"/>
            <circle cx="16" cy="1.5" r="1.5" fill="white"/>
            <rect x="5" y="7" width="22" height="16" rx="4" stroke="white" stroke-width="2"/>
            <line x1="5" y1="13.5" x2="1.5" y2="13.5" stroke="white" stroke-width="2" stroke-linecap="round"/>
            <line x1="27" y1="13.5" x2="30.5" y2="13.5" stroke="white" stroke-width="2" stroke-linecap="round"/>
            <circle cx="11.5" cy="13" r="2" fill="white"/>
            <circle cx="20.5" cy="13" r="2" fill="white"/>
            <line x1="11" y1="19.5" x2="21" y2="19.5" stroke="white" stroke-width="2" stroke-linecap="round"/>
        </svg>
    </button>
    <div class="chatbot-window" id="chatbotWindow" aria-hidden="true">
        <div class="chatbot-header">
            <div class="chatbot-header-left">
                <div class="chatbot-header-icon">
                    <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <line x1="16" y1="2" x2="16" y2="7" stroke="white" stroke-width="2" stroke-linecap="round"/>
                        <circle cx="16" cy="1.5" r="1.5" fill="white"/>
                        <rect x="5" y="7" width="22" height="16" rx="4" stroke="white" stroke-width="2"/>
                        <line x1="5" y1="13.5" x2="1.5" y2="13.5" stroke="white" stroke-width="2" stroke-linecap="round"/>
                        <line x1="27" y1="13.5" x2="30.5" y2="13.5" stroke="white" stroke-width="2" stroke-linecap="round"/>
                        <circle cx="11.5" cy="13" r="2" fill="white"/>
                        <circle cx="20.5" cy="13" r="2" fill="white"/>
                        <line x1="11" y1="19.5" x2="21" y2="19.5" stroke="white" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <div>
                    <div class="chatbot-title">Detabot</div>
                    <div class="chatbot-subtitle">Putra Dental Clinic AI</div>
                </div>
            </div>
            <button class="chatbot-close" id="chatbotClose" aria-label="Close chat">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="chatbot-body" id="chatbotBody">
            <div class="chatbot-bubble">Hi <?= e($user['username']) ?>! 🦷 Welcome to your dashboard. How can I help you today?</div>
            <div class="chatbot-quick-replies">
                <button class="chatbot-quick-btn" data-msg="I want to book an appointment">📅 Book Appointment</button>
                <button class="chatbot-quick-btn" data-msg="Show my appointments">📋 My Appointments</button>
                <button class="chatbot-quick-btn" data-msg="Give me dental tips">💡 Dental Tips</button>
                <button class="chatbot-quick-btn" data-msg="Tell me about my rewards">🎁 My Rewards</button>
            </div>
        </div>
        <div class="chatbot-input-bar">
            <input class="chatbot-input" type="text" id="chatbotInput" placeholder="Type a message…" autocomplete="off">
            <button class="chatbot-send-btn" id="chatbotSend" aria-label="Send message">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M22 2L11 13" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M22 2L15 22L11 13L2 9L22 2Z" fill="white"/>
                </svg>
            </button>
        </div>
    </div>
</div>

<script>
window.DETABOT_USER_ID      = <?= $uid ?>;
window.DETABOT_USER_AGE     = <?= (int) ($user['userAge'] ?? 0) ?>;
window.DETABOT_PAGE_CONTEXT = 'dashboard';
</script>
<script src="assets/chat.js"></script>

<script>
(function () {
'use strict';

/* ── Daily dental tips ── */
var TIPS = [
    'Brush twice a day for 2 minutes each time with fluoride toothpaste.',
    'Floss once a day to remove plaque your brush can\'t reach.',
    'Replace your toothbrush every 3 months — or sooner if bristles fray.',
    'Visit the dentist every 6 months for a checkup and professional cleaning.',
    'Limit sugary drinks and snacks; they feed the bacteria that cause cavities.',
    'Drink plenty of water — it helps wash away food particles and bacteria.',
    'Wear a mouthguard when playing contact sports to protect your teeth.',
    'Avoid chewing on ice or hard objects — this can crack your teeth.',
    'Don\'t use your teeth as tools to open packages or bottles.',
    'If you notice bleeding gums or sensitivity, visit your dentist soon!',
];
var tipEl = document.getElementById('dbDailyTip');
if (tipEl) {
    tipEl.textContent = TIPS[new Date().getDate() % TIPS.length];
}

/* ── Health ring animation ── */
var ring = document.getElementById('healthRingFg');
if (ring) {
    var targetOffset = parseFloat(ring.dataset.offset);
    setTimeout(function () { ring.style.strokeDashoffset = targetOffset; }, 200);
}

/* ── Reward progress bar animation ── */
var fill = document.getElementById('dbRewardFill');
if (fill) {
    var pct = parseInt(fill.dataset.pct, 10) || 0;
    setTimeout(function () { fill.style.width = pct + '%'; }, 300);
}

/* ── Countdown for next appointment ── */
var cdEl = document.getElementById('dbCountdown');
var cdTx = document.getElementById('dbCountdownText');
if (cdEl && cdTx) {
    var dateStr = cdEl.dataset.date;
    var today   = new Date(); today.setHours(0, 0, 0, 0);
    var apptDt  = new Date(dateStr + 'T00:00:00');
    var diff    = Math.round((apptDt - today) / 86400000);
    if (diff === 0)      cdTx.textContent = 'Today!';
    else if (diff === 1) cdTx.textContent = 'Tomorrow';
    else                 cdTx.textContent = diff + ' Days Left';
}

/* ── Status filter for appointments table ── */
window.filterDashTable = function () {
    var val  = document.getElementById('dbStatusFilter').value;
    var rows = document.querySelectorAll('#dbApptTableBody tr');
    var vis  = 0;
    rows.forEach(function (r) {
        var show = val === '' || r.dataset.status === val;
        r.style.display = show ? '' : 'none';
        if (show) vis++;
    });
    var wrap = document.getElementById('dbApptTableWrap');
    var emp  = document.getElementById('dbTblEmpty');
    if (wrap) {
        if (vis === 0 && !emp) {
            var div = document.createElement('div');
            div.className = 'db-tbl-empty'; div.id = 'dbTblEmpty';
            div.textContent = 'No ' + (val || '') + ' appointments found.';
            wrap.appendChild(div);
        } else if (vis > 0 && emp) {
            emp.remove();
        }
    }
};

/* ── Quick cancel from table ── */
window.dbQuickCancel = function (apptID, linkEl) {
    if (!confirm('Cancel this appointment?')) return;
    var csrf = window.DETABOT_CSRF || '';
    linkEl.textContent = '…';
    var fd = new FormData();
    fd.append('_csrf_token', csrf);
    fd.append('appointmentID', apptID);
    fetch('cancel_appointment.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                var row = linkEl.closest('tr');
                if (row) {
                    row.querySelector('.db-badge').textContent = 'Cancelled';
                    row.querySelector('.db-badge').className = 'db-badge cancelled';
                    row.dataset.status = 'cancelled';
                    linkEl.textContent = '—';
                    linkEl.removeAttribute('onclick');
                    linkEl.removeAttribute('href');
                    linkEl.style.cursor = 'default';
                }
            } else {
                alert(d.error || 'Could not cancel. Please try again.');
                linkEl.textContent = 'Cancel';
            }
        })
        .catch(function () {
            alert('Network error. Please try again.');
            linkEl.textContent = 'Cancel';
        });
};

}());
</script>
    <?php
}
