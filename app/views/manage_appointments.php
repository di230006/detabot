<?php
declare(strict_types=1);

function page_manage_appointments(array $user): void
{
    $appointments = db_all(
        "SELECT a.*, u.username, u.userEmail, u.userAge, u.userPhone,
                u.userGender, u.userChronicHealthProblems
         FROM tbl_appointment a
         JOIN tbl_user u ON u.userID = a.userID
         ORDER BY a.appointmentDate ASC, a.appointmentTime ASC",
        []
    );

    $cnt = ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];
    foreach ($appointments as $a) {
        $cnt['total']++;
        $st = (string) ($a['status'] ?? '');
        if (array_key_exists($st, $cnt)) $cnt[$st]++;
    }

    $dentistNames = ['Dr. Muhammad Firdaus', 'Dr. Siti Zafirah', 'Dr. Alia Suhana'];
    $todayStr = date('Y-m-d');

    try {
        $pendingPayments = db_all(
            "SELECT p.*, a.appointmentDate, a.appointmentTime, a.serviceType,
                    u.username AS patientName, u.userEmail AS patientEmail
             FROM tbl_payment p
             JOIN tbl_appointment a ON a.appointmentID = p.appointmentID
             JOIN tbl_user u ON u.userID = p.userID
             WHERE p.paymentStatus = 'pending_verification'
             ORDER BY p.createdDate DESC",
            []
        );
    } catch (\Exception $e) {
        $pendingPayments = [];
    }
    $pendingPaymentCnt = count($pendingPayments);
    ?>
<style>
/* ── Manage Appointments ─────────────────────────────────────── */
.ma-stats{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:20px}
@media(max-width:1000px){.ma-stats{grid-template-columns:repeat(3,1fr)}}
@media(max-width:600px){.ma-stats{grid-template-columns:repeat(2,1fr)}}
.ma-stat{background:#fff;border-radius:11px;border:1px solid #ede8f8;border-left:3.5px solid transparent;padding:14px 14px 12px;box-shadow:0 2px 6px rgba(59,7,100,.05)}
.ma-stat.purple{border-left-color:#7c3aed}
.ma-stat.amber{border-left-color:#c77712}
.ma-stat.blue{border-left-color:#1686c2}
.ma-stat.green{border-left-color:#16845c}
.ma-stat.red{border-left-color:#b42318}
.ma-stat-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;margin-bottom:8px}
.ma-stat-icon.purple{background:#f3f0ff}
.ma-stat-icon.amber{background:#fff8e6}
.ma-stat-icon.blue{background:#e8f4fd}
.ma-stat-icon.green{background:#eaf3de}
.ma-stat-icon.red{background:#fcebeb}
.ma-stat-num{font-family:'Sora',sans-serif;font-size:24px;font-weight:700;color:#1a0e2e;line-height:1;margin-bottom:3px}
.ma-stat-lbl{font-size:11.5px;color:#72647a;font-weight:500}

/* Toolbar */
.ma-toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.ma-toolbar-search{position:relative;flex:1;min-width:200px;max-width:280px}
.ma-search-icon{position:absolute;left:10px;top:50%;transform:translateY(-50%);width:13px;height:13px;stroke:#a78bdb;pointer-events:none}
.ma-search-inp{width:100%;padding:8px 12px 8px 32px;border:1.5px solid #e5ddf5;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;color:#1a0e2e;background:#fff;outline:none;transition:border-color .18s;box-sizing:border-box}
.ma-search-inp:focus{border-color:#7c3aed}
.ma-filter-sel{padding:8px 30px 8px 11px;border:1.5px solid #e5ddf5;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;color:#1a0e2e;background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24' fill='none' stroke='%237c3aed' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 9px center;appearance:none;outline:none;cursor:pointer;transition:border-color .18s}
.ma-filter-sel:focus{border-color:#7c3aed}
.ma-toolbar-right{margin-left:auto;display:flex;gap:8px;flex-shrink:0}
.ma-btn-book{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#7c3aed,#5b21b6);color:#fff;border:none;padding:8px 18px;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;transition:all .15s}
.ma-btn-book:hover{background:linear-gradient(135deg,#6d28d9,#4c1d95);transform:translateY(-1px)}

/* Date filter bar */
.ma-datebar{background:#fff;border:1px solid #ede8f8;border-radius:10px;padding:11px 14px;margin-bottom:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.ma-chips{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
.ma-chip{display:inline-flex;align-items:center;padding:5px 13px;border-radius:20px;font-family:'DM Sans',sans-serif;font-size:12.5px;font-weight:600;border:1.5px solid #e5ddf5;background:#fff;color:#72647a;cursor:pointer;transition:all .15s;white-space:nowrap;line-height:1}
.ma-chip:hover{border-color:#c4b2f0;background:#faf8ff;color:#3b0764}
.ma-chip.active{border-color:#7c3aed;background:#7c3aed;color:#fff}
.ma-datebar-sep{width:1px;height:22px;background:#e5ddf5;flex-shrink:0}
.ma-datebar-right{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-left:auto}
.ma-exact-inp{padding:7px 10px;border:1.5px solid #e5ddf5;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;color:#1a0e2e;outline:none;transition:border-color .18s;cursor:pointer}
.ma-exact-inp:focus{border-color:#7c3aed}
.ma-range-toggle{display:inline-flex;align-items:center;gap:5px;padding:7px 12px;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:12.5px;font-weight:600;border:1.5px solid #e5ddf5;background:#fff;color:#72647a;cursor:pointer;transition:all .15s;white-space:nowrap}
.ma-range-toggle:hover{border-color:#c4b2f0;color:#3b0764;background:#faf8ff}
.ma-range-toggle.open{border-color:#7c3aed;color:#7c3aed;background:#f3f0ff}

/* Date range row (collapsible) */
.ma-range-row{display:none;align-items:center;gap:8px;flex-wrap:wrap;background:#f9f7fe;border:1px solid #ede8f8;border-radius:8px;padding:10px 14px;margin-bottom:12px}
.ma-range-row.open{display:flex}
.ma-range-label{font-size:12px;font-weight:600;color:#72647a;white-space:nowrap}
.ma-range-inp{padding:7px 10px;border:1.5px solid #e5ddf5;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;color:#1a0e2e;outline:none;transition:border-color .18s;cursor:pointer}
.ma-range-inp:focus{border-color:#7c3aed}
.ma-range-sep{color:#9b8ad4;font-weight:600;font-size:13px}
.ma-btn-apply-range{padding:7px 16px;background:#7c3aed;color:#fff;border:none;border-radius:7px;font-family:'DM Sans',sans-serif;font-size:12.5px;font-weight:600;cursor:pointer;transition:background .15s}
.ma-btn-apply-range:hover{background:#6d28d9}
.ma-btn-clear-range{padding:7px 10px;background:none;border:1.5px solid #e5ddf5;border-radius:7px;font-size:12px;font-family:'DM Sans',sans-serif;color:#72647a;cursor:pointer;transition:all .15s}
.ma-btn-clear-range:hover{border-color:#b42318;color:#b42318}

/* Filter label */
.ma-filter-label{display:flex;align-items:center;gap:8px;margin-bottom:10px;font-size:12.5px;color:#72647a;flex-wrap:wrap;min-height:22px}
.ma-filter-label strong{color:#1a0e2e}
.ma-active-pill{display:inline-flex;align-items:center;gap:4px;background:#f3f0ff;color:#5b21b6;border-radius:20px;padding:2px 10px;font-size:12px;font-weight:600}
.ma-pill-clear{background:none;border:none;cursor:pointer;color:#9b8ad4;font-size:13px;padding:0 1px;line-height:1;transition:color .15s}
.ma-pill-clear:hover{color:#b42318}

/* Tabs */
.ma-tabs{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:14px;border-bottom:2px solid #ede8f8}
.ma-tab{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px 8px 0 0;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;color:#72647a;cursor:pointer;border:none;background:none;transition:all .15s;border-bottom:2px solid transparent;margin-bottom:-2px}
.ma-tab:hover{color:#3b0764;background:#f3f0ff}
.ma-tab.active{color:#3b0764;border-bottom-color:#7c3aed;background:#f3f0ff}
.ma-tab-badge{display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:18px;padding:0 5px;border-radius:10px;font-size:10.5px;font-weight:700;background:#ede8f8;color:#5b21b6}
.ma-tab.active .ma-tab-badge{background:#7c3aed;color:#fff}

/* Table */
.ma-tbl-wrap{overflow-x:auto}
.ma-tbl{width:100%;border-collapse:collapse;font-size:13px}
.ma-tbl th{text-align:left;padding:9px 12px;font-size:11px;font-weight:700;color:#72647a;text-transform:uppercase;letter-spacing:.05em;border-bottom:2px solid #ede8f8;white-space:nowrap;background:#fdfcff}
.ma-tbl td{padding:11px 12px;border-bottom:1px solid #f0ebf8;vertical-align:middle}
.ma-tbl tr:last-child td{border-bottom:none}
.ma-tbl tr:hover td{background:#faf8ff}
.ma-date-hdr-row td{background:linear-gradient(90deg,#f3f0ff,#faf8ff);color:#3b0764;font-family:'Sora',sans-serif;font-size:12px;font-weight:700;padding:7px 14px;border-bottom:1px solid #ddd0fc;letter-spacing:.01em;pointer-events:none}
.ma-tbl .ma-date-hdr-row:hover td{background:linear-gradient(90deg,#f3f0ff,#faf8ff)}
.ma-tbl-empty td{text-align:center;padding:38px 20px;color:#72647a;font-size:13.5px}

/* Patient cell */
.ma-pat-cell{display:flex;align-items:flex-start;gap:9px}
.ma-pat-av{width:32px;height:32px;border-radius:50%;background:#eeedfe;display:flex;align-items:center;justify-content:center;font-family:'Sora',sans-serif;font-size:11px;font-weight:700;color:#5b21b6;flex-shrink:0;margin-top:1px}
.ma-pat-name{font-weight:700;color:#1a0e2e;line-height:1.3}
.ma-pat-meta{font-size:11px;color:#72647a;margin-top:2px}
.ma-pat-health{font-size:11px;color:#c77712;margin-top:2px}
.ma-date-main{font-weight:600;color:#3b0764;white-space:nowrap}
.ma-date-time{font-size:11.5px;color:#72647a;margin-top:2px}

/* Status badges */
.ma-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:100px;font-size:11px;font-weight:600;white-space:nowrap}
.ma-badge.pending{background:#fff8e6;color:#c77712}
.ma-badge.confirmed{background:#e8f4fd;color:#1686c2}
.ma-badge.completed{background:#eaf3de;color:#16845c}
.ma-badge.cancelled{background:#fcebeb;color:#b42318}

/* Action buttons */
.ma-btns{display:flex;gap:5px;flex-wrap:nowrap;align-items:center}
.ma-btn{display:inline-flex;align-items:center;gap:3px;padding:5px 10px;border-radius:6px;font-size:11.5px;font-weight:600;border:none;cursor:pointer;transition:all .15s;white-space:nowrap;text-decoration:none;font-family:'DM Sans',sans-serif;line-height:1}
.ma-btn:disabled{opacity:.5;cursor:not-allowed}
.ma-btn-confirm{background:#eaf3de;color:#16845c}.ma-btn-confirm:hover:not(:disabled){background:#d1edbc}
.ma-btn-complete{background:#e8f4fd;color:#1686c2}.ma-btn-complete:hover:not(:disabled){background:#cce8f9}
.ma-btn-cancel{background:#fcebeb;color:#b42318}.ma-btn-cancel:hover:not(:disabled){background:#fbd5d5}
.ma-btn-record{background:#f3f0ff;color:#5b21b6}.ma-btn-record:hover{background:#e3dcfc}
.ma-btn-view{background:#f9f7fe;color:#72647a;border:1px solid #ede8f8}.ma-btn-view:hover{background:#f3f0ff;color:#3b0764}

/* Payment verification */
.ma-pay-tbl th{text-align:left;padding:9px 12px;font-size:11px;font-weight:700;color:#72647a;text-transform:uppercase;letter-spacing:.05em;border-bottom:2px solid #ede8f8;white-space:nowrap;background:#fdfcff}
.ma-pay-tbl td{padding:11px 12px;border-bottom:1px solid #f0ebf8;vertical-align:middle;font-size:13px}
.ma-pay-tbl tr:last-child td{border-bottom:none}
.ma-pay-tbl tr:hover td{background:#faf8ff}
.ma-pay-badge-pending{display:inline-flex;align-items:center;padding:3px 10px;border-radius:100px;font-size:11px;font-weight:600;background:#fff8e6;color:#c77712}
.ma-pay-badge-paid{display:inline-flex;align-items:center;padding:3px 10px;border-radius:100px;font-size:11px;font-weight:600;background:#eaf3de;color:#16845c}
.ma-pay-badge-rejected{display:inline-flex;align-items:center;padding:3px 10px;border-radius:100px;font-size:11px;font-weight:600;background:#fcebeb;color:#b42318}
.ma-btn-verify{background:#eaf3de;color:#16845c}.ma-btn-verify:hover:not(:disabled){background:#d1edbc}
.ma-btn-reject-pay{background:#fcebeb;color:#b42318}.ma-btn-reject-pay:hover:not(:disabled){background:#fbd5d5}
.ma-proof-overlay{position:fixed;inset:0;background:rgba(15,5,30,.7);z-index:1100;display:flex;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(3px)}

/* Modal */
.ma-overlay{position:fixed;inset:0;background:rgba(15,5,30,.45);z-index:1000;display:flex;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(2px)}
.ma-modal{background:#fff;border-radius:16px;width:100%;max-width:580px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(59,7,100,.25)}
.ma-modal-hd{display:flex;align-items:center;justify-content:space-between;padding:18px 20px 14px;border-bottom:1px solid #ede8f8;position:sticky;top:0;background:#fff;z-index:1;border-radius:16px 16px 0 0}
.ma-modal-hd h2{font-family:'Sora',sans-serif;font-size:16px;font-weight:700;color:#1a0e2e;margin:0}
.ma-modal-close{background:none;border:none;cursor:pointer;width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#72647a;font-size:16px;transition:background .15s}
.ma-modal-close:hover{background:#f0ebf8;color:#3b0764}
.ma-modal-body{padding:18px 20px 20px}
.ma-modal-sec{margin-bottom:18px}
.ma-modal-sec:last-child{margin-bottom:0}
.ma-modal-sec-title{font-size:11px;font-weight:700;color:#9b8ad4;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px}
.ma-modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
@media(max-width:500px){.ma-modal-grid{grid-template-columns:1fr}}
.ma-modal-field label{font-size:11px;font-weight:700;color:#9b8ad4;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:3px}
.ma-modal-field p{font-size:13.5px;color:#1a0e2e;margin:0;line-height:1.5;word-break:break-word}
.ma-modal-notes{background:#f9f7fe;border:1px solid #ede8f8;border-radius:8px;padding:10px 12px;font-size:13px;color:#1a0e2e;line-height:1.6;white-space:pre-wrap;word-break:break-word}
</style>

<!-- Stat Cards -->
<div class="ma-stats">
    <div class="ma-stat purple">
        <div class="ma-stat-icon purple">📋</div>
        <div class="ma-stat-num" id="ma-cnt-total"><?= $cnt['total'] ?></div>
        <div class="ma-stat-lbl">Total</div>
    </div>
    <div class="ma-stat amber">
        <div class="ma-stat-icon amber">⏳</div>
        <div class="ma-stat-num" id="ma-cnt-pending"><?= $cnt['pending'] ?></div>
        <div class="ma-stat-lbl">Pending</div>
    </div>
    <div class="ma-stat blue">
        <div class="ma-stat-icon blue">✅</div>
        <div class="ma-stat-num" id="ma-cnt-confirmed"><?= $cnt['confirmed'] ?></div>
        <div class="ma-stat-lbl">Confirmed</div>
    </div>
    <div class="ma-stat green">
        <div class="ma-stat-icon green">🎉</div>
        <div class="ma-stat-num" id="ma-cnt-completed"><?= $cnt['completed'] ?></div>
        <div class="ma-stat-lbl">Completed</div>
    </div>
    <div class="ma-stat red">
        <div class="ma-stat-icon red">❌</div>
        <div class="ma-stat-num" id="ma-cnt-cancelled"><?= $cnt['cancelled'] ?></div>
        <div class="ma-stat-lbl">Cancelled</div>
    </div>
</div>

<!-- Toolbar -->
<div class="ma-toolbar">
    <div class="ma-toolbar-search">
        <svg class="ma-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input id="maSearch" class="ma-search-inp" type="text" placeholder="Search patient or service…" autocomplete="off" oninput="maFilter()">
    </div>
    <select id="maDentistFilter" class="ma-filter-sel" onchange="maFilter()">
        <option value="">All Dentists</option>
        <?php foreach ($dentistNames as $dn): ?>
            <option value="<?= e($dn) ?>"><?= e($dn) ?></option>
        <?php endforeach; ?>
    </select>
    <select id="maSortSelect" class="ma-filter-sel" onchange="maSortChanged(this.value)" title="Sort order">
        <option value="upcoming">Upcoming first</option>
        <option value="newest">Newest first</option>
        <option value="oldest">Oldest first</option>
    </select>
    <div class="ma-toolbar-right">
        <a class="ma-btn-book" href="appointments.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="width:13px;height:13px" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Book Appointment
        </a>
    </div>
</div>

<!-- Date Filter Bar -->
<div class="ma-datebar">
    <div class="ma-chips">
        <button class="ma-chip active" data-mode="all"      onclick="maSetDateMode(this,'all')">All Dates</button>
        <button class="ma-chip"        data-mode="today"    onclick="maSetDateMode(this,'today')">Today</button>
        <button class="ma-chip"        data-mode="tomorrow" onclick="maSetDateMode(this,'tomorrow')">Tomorrow</button>
        <button class="ma-chip"        data-mode="week"     onclick="maSetDateMode(this,'week')">This Week</button>
        <button class="ma-chip"        data-mode="month"    onclick="maSetDateMode(this,'month')">This Month</button>
    </div>
    <div class="ma-datebar-sep"></div>
    <div class="ma-datebar-right">
        <input id="maExactDate" class="ma-exact-inp" type="date" title="Pick exact date" onchange="maSetExactDate(this.value)">
        <button id="maRangeToggle" class="ma-range-toggle" onclick="maToggleRange()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="width:12px;height:12px" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
            Date Range
        </button>
    </div>
</div>

<!-- Date Range Row (collapsible) -->
<div class="ma-range-row" id="maRangeRow">
    <span class="ma-range-label">From:</span>
    <input id="maRangeFrom" class="ma-range-inp" type="date">
    <span class="ma-range-sep">—</span>
    <span class="ma-range-label">To:</span>
    <input id="maRangeTo" class="ma-range-inp" type="date">
    <button class="ma-btn-apply-range" onclick="maApplyRange()">Apply Range</button>
    <button class="ma-btn-clear-range" onclick="maClearRange()">✕ Clear</button>
</div>

<!-- Filter label + result count -->
<div class="ma-filter-label" id="maFilterLabel">
    <strong id="maResultCount"><?= count($appointments) ?></strong>
    <span id="maResultSuffix">appointment<?= count($appointments) !== 1 ? 's' : '' ?></span>
</div>

<!-- Filter Tabs -->
<div class="ma-tabs" role="tablist">
    <button class="ma-tab active" data-tab="all"       onclick="maSetTab(this,'all')"       role="tab">All       <span class="ma-tab-badge" id="ma-tab-cnt-all"><?= $cnt['total'] ?></span></button>
    <button class="ma-tab"        data-tab="pending"   onclick="maSetTab(this,'pending')"   role="tab">Pending   <span class="ma-tab-badge" id="ma-tab-cnt-pending"><?= $cnt['pending'] ?></span></button>
    <button class="ma-tab"        data-tab="confirmed" onclick="maSetTab(this,'confirmed')" role="tab">Confirmed <span class="ma-tab-badge" id="ma-tab-cnt-confirmed"><?= $cnt['confirmed'] ?></span></button>
    <button class="ma-tab"        data-tab="completed" onclick="maSetTab(this,'completed')" role="tab">Completed <span class="ma-tab-badge" id="ma-tab-cnt-completed"><?= $cnt['completed'] ?></span></button>
    <button class="ma-tab"        data-tab="cancelled" onclick="maSetTab(this,'cancelled')" role="tab">Cancelled <span class="ma-tab-badge" id="ma-tab-cnt-cancelled"><?= $cnt['cancelled'] ?></span></button>
    <button class="ma-tab" id="ma-tab-payment" onclick="maSetPaymentTab(this)" role="tab" style="margin-left:auto">💳 Payment Verification <span class="ma-tab-badge" id="ma-tab-cnt-payment" style="background:<?= $pendingPaymentCnt > 0 ? '#ef4444' : '#ede8f8' ?>;color:<?= $pendingPaymentCnt > 0 ? '#fff' : '#5b21b6' ?>"><?= $pendingPaymentCnt ?></span></button>
</div>

<!-- Appointments Table -->
<div class="panel" id="maTablePanel" style="padding:0;overflow:hidden">
    <?php if (empty($appointments)): ?>
        <div style="text-align:center;padding:40px;color:#72647a;font-size:13.5px">No appointments found.</div>
    <?php else: ?>
    <div class="ma-tbl-wrap">
        <table class="ma-tbl" id="maTable">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Date &amp; Time</th>
                    <th>Service</th>
                    <th>Dentist</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="maBody">
            <?php foreach ($appointments as $a):
                $aID     = (int) $a['appointmentID'];
                $uID     = (int) $a['userID'];
                $aSt     = (string) ($a['status'] ?? 'pending');
                $aTm     = substr((string) ($a['appointmentTime'] ?? ''), 0, 5);
                $aDt     = (string) ($a['appointmentDate'] ?? '');
                $aDn     = extract_dentist_name((string) ($a['notes'] ?? ''));
                $aHlth   = format_appointment_health_summary($a);
                $patInit = strtoupper(substr((string) ($a['username'] ?? 'P'), 0, 2));

                $rowJson = (string) json_encode([
                    'id'       => $aID,
                    'userID'   => $uID,
                    'patient'  => (string) ($a['username'] ?? ''),
                    'email'    => (string) ($a['userEmail'] ?? ''),
                    'phone'    => (string) ($a['userPhone'] ?? ''),
                    'age'      => (int) ($a['userAge'] ?? 0),
                    'gender'   => ucfirst((string) ($a['userGender'] ?? '')),
                    'service'  => (string) ($a['serviceType'] ?? ''),
                    'date'     => $aDt,
                    'time'     => $aTm,
                    'duration' => format_duration((int) ($a['duration'] ?? 0)),
                    'dentist'  => $aDn,
                    'status'   => $aSt,
                    'health'   => $aHlth,
                    'notes'    => (string) ($a['notes'] ?? ''),
                    'receipt'  => (string) ($a['paymentReceipt'] ?? ''),
                ]);
            ?>
            <tr id="ma-row-<?= $aID ?>"
                data-status="<?= e($aSt) ?>"
                data-date="<?= e($aDt) ?>"
                data-time="<?= e($aTm) ?>"
                data-dentist="<?= e($aDn) ?>"
                data-search="<?= e(strtolower((string) ($a['username'] ?? '') . ' ' . (string) ($a['serviceType'] ?? ''))) ?>"
                data-json="<?= e($rowJson) ?>">
                <td>
                    <div class="ma-pat-cell">
                        <div class="ma-pat-av"><?= e($patInit) ?></div>
                        <div>
                            <div class="ma-pat-name"><?= e($a['username'] ?? '') ?></div>
                            <div class="ma-pat-meta">
                                <?php if ((int) ($a['userAge'] ?? 0) > 0): ?><?= (int) $a['userAge'] ?> y/o<?php endif; ?>
                                <?php if (!empty($a['userPhone'])): ?> · <?= e($a['userPhone']) ?><?php endif; ?>
                            </div>
                            <?php if ($aHlth !== '' && !str_contains($aHlth, 'No health')): ?>
                            <div class="ma-pat-health"><?= e($aHlth) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="ma-date-main"><?= e(date('d M Y', strtotime($aDt))) ?></div>
                    <div class="ma-date-time"><?= e($aTm) ?></div>
                </td>
                <td><?= e($a['serviceType'] ?? '') ?></td>
                <td style="font-size:12.5px;color:#4b3a6e"><?= e($aDn) ?></td>
                <td>
                    <span class="ma-badge <?= e($aSt) ?>" id="ma-badge-<?= $aID ?>"><?= e(ucfirst($aSt)) ?></span>
                </td>
                <td>
                    <div class="ma-btns" id="ma-btns-<?= $aID ?>">
                        <?php if ($aSt === 'pending'): ?>
                            <button class="ma-btn ma-btn-confirm" onclick="maUpdateStatus(<?= $aID ?>,'confirmed',this,'pending')">✓ Confirm</button>
                            <button class="ma-btn ma-btn-cancel"  onclick="maUpdateStatus(<?= $aID ?>,'cancelled',this,'pending')">✕ Cancel</button>
                        <?php elseif ($aSt === 'confirmed'): ?>
                            <button class="ma-btn ma-btn-complete" onclick="maUpdateStatus(<?= $aID ?>,'completed',this,'confirmed')">✓ Complete</button>
                            <button class="ma-btn ma-btn-cancel"   onclick="maUpdateStatus(<?= $aID ?>,'cancelled',this,'confirmed')">✕ Cancel</button>
                        <?php elseif ($aSt === 'completed'): ?>
                            <a class="ma-btn ma-btn-record" href="staff_health_record.php?patient=<?= $uID ?>">+ Record</a>
                            <a class="ma-btn ma-btn-confirm" href="generate_invoice.php?appointmentID=<?= $aID ?>" style="background:#7c3aed;color:#fff;border-color:#7c3aed">🧾 Invoice</a>
                        <?php endif; ?>
                        <button class="ma-btn ma-btn-view" onclick="maOpenModal(<?= $aID ?>)">👁 View</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <!-- Filled by JS when empty -->
            <tr id="maEmptyRow" class="ma-tbl-empty" style="display:none">
                <td colspan="6">No appointments found for this filter. 📅</td>
            </tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Payment Verification Panel (hidden until Payment tab active) -->
<div id="maPaymentPanel" style="display:none">
    <div class="panel" style="padding:0;overflow:hidden">
        <?php if (empty($pendingPayments)): ?>
            <div style="text-align:center;padding:40px;color:#72647a;font-size:13.5px">
                <div style="font-size:36px;margin-bottom:10px">💳</div>
                No pending payment verifications.
            </div>
        <?php else: ?>
        <div class="ma-tbl-wrap">
            <table class="ma-tbl ma-pay-tbl" id="maPayTbl">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Service / Date</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Reference No.</th>
                        <th>Proof</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pendingPayments as $pm):
                    $pmID    = (int) $pm['paymentID'];
                    $pmApptD = (string) ($pm['appointmentDate'] ?? '');
                    $pmSvc   = (string) ($pm['serviceType'] ?? '');
                    $pmAmt   = number_format((float) ($pm['amount'] ?? 0), 2);
                    $pmMeth  = (string) ($pm['paymentMethod'] ?? '');
                    $pmMethLabel = $pmMeth === 'tng_qr' ? 'Touch \'n Go QR' : ($pmMeth === 'fpx' ? 'FPX' : ucfirst($pmMeth));
                    $pmRef   = (string) ($pm['referenceNo'] ?? '');
                    $pmProof = (string) ($pm['proofPath'] ?? '');
                    $pmPat   = (string) ($pm['patientName'] ?? '');
                    $pmInit  = strtoupper(substr($pmPat, 0, 2));
                    $pmBank  = (string) ($pm['bankName'] ?? '');
                ?>
                <tr id="ma-pay-row-<?= $pmID ?>">
                    <td>
                        <div class="ma-pat-cell">
                            <div class="ma-pat-av"><?= e($pmInit) ?></div>
                            <div>
                                <div class="ma-pat-name"><?= e($pmPat) ?></div>
                                <div class="ma-pat-meta"><?= e((string) ($pm['patientEmail'] ?? '')) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="ma-date-main"><?= e($pmSvc) ?></div>
                        <div class="ma-date-time"><?= e($pmApptD ? date('d M Y', strtotime($pmApptD)) : '—') ?></div>
                    </td>
                    <td style="font-weight:700;color:#7c3aed">RM <?= e($pmAmt) ?></td>
                    <td>
                        <?= e($pmMethLabel) ?>
                        <?php if ($pmBank): ?><div class="ma-date-time"><?= e($pmBank) ?></div><?php endif; ?>
                    </td>
                    <td style="font-family:monospace;font-size:12.5px"><?= e($pmRef) ?></td>
                    <td>
                        <?php if ($pmProof): ?>
                        <button class="ma-btn ma-btn-view" onclick="maViewProof('<?= e(addslashes($pmProof)) ?>')">🖼 View</button>
                        <?php else: ?>
                        <span style="color:#72647a;font-size:12px">No file</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="ma-btns" id="ma-pay-btns-<?= $pmID ?>">
                            <button class="ma-btn ma-btn-verify"     onclick="maVerifyPayment(<?= $pmID ?>,'verify',this)">✓ Verify</button>
                            <button class="ma-btn ma-btn-reject-pay" onclick="maVerifyPayment(<?= $pmID ?>,'reject',this)">✕ Reject</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- View Modal -->
<div class="ma-overlay" id="maModal" style="display:none" onclick="if(event.target===this)maCloseModal()">
    <div class="ma-modal" role="dialog" aria-modal="true" aria-labelledby="maModalTitle">
        <div class="ma-modal-hd">
            <h2 id="maModalTitle">Appointment Details</h2>
            <button class="ma-modal-close" onclick="maCloseModal()" aria-label="Close">✕</button>
        </div>
        <div class="ma-modal-body" id="maModalBody"></div>
    </div>
</div>

<script>
var MA_CSRF   = window.DETABOT_CSRF || '';
var MA_TODAY  = '<?= $todayStr ?>';

// ── Filter state ───────────────────────────────────────────────
var maActiveTab  = 'all';
var maDateMode   = 'all';
var maDateExact  = '';
var maDateFrom   = '';
var maDateTo     = '';
var maSortMode   = 'upcoming';

// ── Date matching ──────────────────────────────────────────────
function maMatchDate(rowDate) {
    if (!rowDate) return true;
    var rd    = new Date(rowDate + 'T00:00:00');
    var today = new Date(MA_TODAY + 'T00:00:00');

    if (maDateMode === 'all')      return true;
    if (maDateMode === 'today')    return rowDate === MA_TODAY;
    if (maDateMode === 'tomorrow') {
        var tom = new Date(today); tom.setDate(today.getDate() + 1);
        return rd.getTime() === tom.getTime();
    }
    if (maDateMode === 'week') {
        var dow = today.getDay(); // 0=Sun
        var monOffset = dow === 0 ? -6 : 1 - dow;
        var mon = new Date(today); mon.setDate(today.getDate() + monOffset);
        var sat = new Date(mon); sat.setDate(mon.getDate() + 5);
        return rd >= mon && rd <= sat;
    }
    if (maDateMode === 'month') {
        return rd.getFullYear() === today.getFullYear() && rd.getMonth() === today.getMonth();
    }
    if (maDateMode === 'exact') return rowDate === maDateExact;
    if (maDateMode === 'range') {
        if (maDateFrom && rowDate < maDateFrom) return false;
        if (maDateTo   && rowDate > maDateTo)   return false;
        return true;
    }
    return true;
}

// ── Main filter + count + sort + group ────────────────────────
function maFilter() {
    var search  = (document.getElementById('maSearch').value  || '').toLowerCase().trim();
    var dentist = (document.getElementById('maDentistFilter').value || '').toLowerCase();
    var rows    = document.querySelectorAll('#maBody tr:not(.ma-date-hdr-row):not(.ma-tbl-empty)');

    var statusCounts = { pending: 0, confirmed: 0, completed: 0, cancelled: 0 };
    var visibleInTab = 0;

    rows.forEach(function (r) {
        var okSearch  = !search  || (r.dataset.search  || '').indexOf(search)  !== -1;
        var okDentist = !dentist || (r.dataset.dentist || '').toLowerCase() === dentist;
        var okDate    = maMatchDate(r.dataset.date || '');
        var okBase    = okSearch && okDentist && okDate;

        if (okBase) {
            var st = r.dataset.status;
            if (statusCounts[st] !== undefined) statusCounts[st]++;
        }

        var okTab = maActiveTab === 'all' || r.dataset.status === maActiveTab;
        var show  = okBase && okTab;
        r.style.display = show ? '' : 'none';
        if (show) visibleInTab++;
    });

    // Update stat cards with filtered counts
    var total = Object.values(statusCounts).reduce(function (s, n) { return s + n; }, 0);
    maSetCount('total', total);
    Object.keys(statusCounts).forEach(function (st) { maSetCount(st, statusCounts[st]); });

    // Update tab badges (counts for base filters, not tab filter)
    maSetTabCount('all', total);
    Object.keys(statusCounts).forEach(function (st) { maSetTabCount(st, statusCounts[st]); });

    // Filter label
    maUpdateLabel(visibleInTab);

    // Sort then group
    maSortRows();
    maGroupByDate();

    // Empty state row
    var emptyRow = document.getElementById('maEmptyRow');
    if (emptyRow) emptyRow.style.display = visibleInTab === 0 ? '' : 'none';
}

// ── Set tab ────────────────────────────────────────────────────
function maSetTab(btn, tab) {
    document.querySelectorAll('.ma-tab').forEach(function (t) { t.classList.remove('active'); });
    btn.classList.add('active');
    maActiveTab = tab;
    var tp = document.getElementById('maTablePanel');
    var pp = document.getElementById('maPaymentPanel');
    if (tp) tp.style.display = '';
    if (pp) pp.style.display = 'none';
    var tl = document.getElementById('maFilterLabel');
    if (tl) tl.style.display = '';
    maFilter();
}

function maSetPaymentTab(btn) {
    document.querySelectorAll('.ma-tab').forEach(function (t) { t.classList.remove('active'); });
    btn.classList.add('active');
    var tp = document.getElementById('maTablePanel');
    var pp = document.getElementById('maPaymentPanel');
    if (tp) tp.style.display = 'none';
    if (pp) pp.style.display = '';
    var tl = document.getElementById('maFilterLabel');
    if (tl) tl.style.display = 'none';
}

// ── Date mode ─────────────────────────────────────────────────
function maSetDateMode(btn, mode) {
    document.querySelectorAll('.ma-chip').forEach(function (c) { c.classList.remove('active'); });
    btn.classList.add('active');
    maDateMode  = mode;
    maDateExact = '';
    maDateFrom  = '';
    maDateTo    = '';
    document.getElementById('maExactDate').value = '';
    document.getElementById('maRangeFrom').value = '';
    document.getElementById('maRangeTo').value   = '';
    maCloseRangeRow();
    maFilter();
}

function maSetExactDate(val) {
    maDateExact = val;
    if (val) {
        maDateMode  = 'exact';
        maDateFrom  = '';
        maDateTo    = '';
        document.querySelectorAll('.ma-chip').forEach(function (c) { c.classList.remove('active'); });
        maCloseRangeRow();
    } else {
        maDateMode = 'all';
        document.querySelector('.ma-chip[data-mode="all"]').classList.add('active');
    }
    maFilter();
}

// ── Date range ─────────────────────────────────────────────────
function maToggleRange() {
    var row    = document.getElementById('maRangeRow');
    var toggle = document.getElementById('maRangeToggle');
    var isOpen = row.classList.contains('open');
    if (isOpen) {
        maCloseRangeRow();
    } else {
        row.classList.add('open');
        toggle.classList.add('open');
    }
}

function maCloseRangeRow() {
    document.getElementById('maRangeRow').classList.remove('open');
    document.getElementById('maRangeToggle').classList.remove('open');
}

function maApplyRange() {
    maDateFrom  = document.getElementById('maRangeFrom').value || '';
    maDateTo    = document.getElementById('maRangeTo').value   || '';
    if (!maDateFrom && !maDateTo) { maClearRange(); return; }
    maDateMode  = 'range';
    maDateExact = '';
    document.getElementById('maExactDate').value = '';
    document.querySelectorAll('.ma-chip').forEach(function (c) { c.classList.remove('active'); });
    maFilter();
}

function maClearRange() {
    maDateFrom = '';
    maDateTo   = '';
    document.getElementById('maRangeFrom').value = '';
    document.getElementById('maRangeTo').value   = '';
    if (maDateMode === 'range') {
        maDateMode = 'all';
        document.querySelector('.ma-chip[data-mode="all"]').classList.add('active');
    }
    maCloseRangeRow();
    maFilter();
}

// ── Sort ───────────────────────────────────────────────────────
function maSortChanged(val) {
    maSortMode = val;
    maSortRows();
    maGroupByDate();
}

function maSortRows() {
    var body = document.getElementById('maBody');
    var rows = Array.from(body.querySelectorAll('tr:not(.ma-date-hdr-row):not(.ma-tbl-empty)'));

    rows.sort(function (a, b) {
        var da = a.dataset.date || '';
        var ta = a.dataset.time || '';
        var db = b.dataset.date || '';
        var tb = b.dataset.time || '';

        if (maSortMode === 'newest') {
            return db !== da ? (db > da ? -1 : 1) : (tb > ta ? -1 : 1);
        }
        if (maSortMode === 'oldest') {
            return da !== db ? (da > db ? 1 : -1) : (ta > tb ? 1 : -1);
        }
        // 'upcoming': today/future (score=0) first ASC, then past (score=1) ASC
        var aFut = da >= MA_TODAY ? '0' + da : '1' + da;
        var bFut = db >= MA_TODAY ? '0' + db : '1' + db;
        return aFut !== bFut ? (aFut > bFut ? 1 : -1) : (ta > tb ? 1 : -1);
    });

    rows.forEach(function (r) { body.appendChild(r); });
    var emptyRow = document.getElementById('maEmptyRow');
    if (emptyRow) body.appendChild(emptyRow); // keep empty row at end
}

// ── Group by date ──────────────────────────────────────────────
function maGroupByDate() {
    var body = document.getElementById('maBody');
    body.querySelectorAll('.ma-date-hdr-row').forEach(function (el) { el.remove(); });

    var today  = MA_TODAY;
    var tom    = new Date(today + 'T00:00:00');
    tom.setDate(tom.getDate() + 1);
    var tomStr = tom.toISOString().slice(0, 10);

    var visible = Array.from(body.querySelectorAll('tr:not(.ma-date-hdr-row):not(.ma-tbl-empty)')).filter(function (r) {
        return r.style.display !== 'none';
    });

    var lastDate = '';
    visible.forEach(function (row) {
        var d = row.dataset.date || '';
        if (d !== lastDate) {
            var label = formatDate(d);
            var prefix = d === today ? '📅 Today — ' : (d === tomStr ? '📅 Tomorrow — ' : '📅 ');
            var hdr = document.createElement('tr');
            hdr.className = 'ma-date-hdr-row';
            hdr.innerHTML = '<td colspan="6">' + prefix + label + '</td>';
            row.parentNode.insertBefore(hdr, row);
            lastDate = d;
        }
    });
}

// ── Label ──────────────────────────────────────────────────────
function maUpdateLabel(shown) {
    var countEl  = document.getElementById('maResultCount');
    var suffixEl = document.getElementById('maResultSuffix');
    var labelEl  = document.getElementById('maFilterLabel');
    if (countEl)  countEl.textContent  = shown;
    if (suffixEl) suffixEl.textContent = 'appointment' + (shown !== 1 ? 's' : '');

    var desc = maGetFilterDesc();
    var old  = labelEl ? labelEl.querySelector('.ma-active-pill') : null;
    if (old) old.remove();

    if (desc && labelEl) {
        var pill = document.createElement('span');
        pill.className = 'ma-active-pill';
        pill.innerHTML = desc + ' <button class="ma-pill-clear" onclick="maClearAllDateFilters()" title="Clear date filter">✕</button>';
        labelEl.appendChild(pill);
    }
}

function maGetFilterDesc() {
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var t = new Date(MA_TODAY + 'T00:00:00');

    if (maDateMode === 'today')    return 'Today (' + t.getDate() + ' ' + months[t.getMonth()] + ' ' + t.getFullYear() + ')';
    if (maDateMode === 'tomorrow') {
        var tom = new Date(t); tom.setDate(t.getDate() + 1);
        return 'Tomorrow (' + tom.getDate() + ' ' + months[tom.getMonth()] + ' ' + tom.getFullYear() + ')';
    }
    if (maDateMode === 'week')  return 'This Week';
    if (maDateMode === 'month') return 'This Month (' + months[t.getMonth()] + ' ' + t.getFullYear() + ')';
    if (maDateMode === 'exact') return formatDate(maDateExact);
    if (maDateMode === 'range') {
        if (maDateFrom && maDateTo)  return formatDate(maDateFrom) + ' – ' + formatDate(maDateTo);
        if (maDateFrom) return 'From ' + formatDate(maDateFrom);
        if (maDateTo)   return 'Until ' + formatDate(maDateTo);
    }
    return '';
}

function maClearAllDateFilters() {
    maDateMode  = 'all';
    maDateExact = '';
    maDateFrom  = '';
    maDateTo    = '';
    document.getElementById('maExactDate').value = '';
    document.getElementById('maRangeFrom').value = '';
    document.getElementById('maRangeTo').value   = '';
    document.querySelectorAll('.ma-chip').forEach(function (c) { c.classList.remove('active'); });
    document.querySelector('.ma-chip[data-mode="all"]').classList.add('active');
    maCloseRangeRow();
    maFilter();
}

// ── Count helpers ──────────────────────────────────────────────
function maSetCount(key, n) {
    var el = document.getElementById('ma-cnt-' + key);
    if (el) el.textContent = n;
}
function maSetTabCount(key, n) {
    var el = document.getElementById('ma-tab-cnt-' + key);
    if (el) el.textContent = n;
}

// ── Status update via AJAX ─────────────────────────────────────
function maUpdateStatus(apptID, newStatus, btn, prevStatus) {
    if (newStatus === 'cancelled' && !confirm('Cancel this appointment?')) return;

    var btns = document.querySelectorAll('#ma-btns-' + apptID + ' .ma-btn');
    btns.forEach(function (b) { b.disabled = true; });
    btn.textContent = '…';

    var row = document.getElementById('ma-row-' + apptID);
    var fd  = new FormData();
    fd.append('_csrf_token', MA_CSRF);
    fd.append('appointmentID', apptID);
    fd.append('newStatus', newStatus);

    fetch('update_appointment_status.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                maApplyStatusChange(apptID, newStatus, row);
                if (d.invoiceReminder) {
                    if (confirm('Appointment completed! Generate an invoice for this patient now?')) {
                        window.location.href = d.invoiceUrl;
                    }
                }
            } else {
                alert(d.error || 'Failed to update status.');
                btns.forEach(function (b) { b.disabled = false; });
            }
        })
        .catch(function () {
            alert('Network error. Please try again.');
            btns.forEach(function (b) { b.disabled = false; });
        });
}

function maApplyStatusChange(apptID, newStatus, row) {
    var badge = document.getElementById('ma-badge-' + apptID);
    if (badge) {
        badge.className = 'ma-badge ' + newStatus;
        badge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
    }
    if (row) {
        row.dataset.status = newStatus;
        try { var d = JSON.parse(row.dataset.json); d.status = newStatus; row.dataset.json = JSON.stringify(d); } catch (e) {}
    }
    maRenderButtons(apptID, newStatus, row);
    maFilter(); // recalculates all counts + re-groups
}

function maRenderButtons(apptID, newStatus, row) {
    var cell = document.getElementById('ma-btns-' + apptID);
    if (!cell) return;
    var uID = '';
    if (row) { try { uID = JSON.parse(row.dataset.json).userID; } catch (e) {} }
    var html = '';
    if (newStatus === 'pending') {
        html += '<button class="ma-btn ma-btn-confirm" onclick="maUpdateStatus(' + apptID + ',\'confirmed\',this,\'pending\')">✓ Confirm</button>';
        html += '<button class="ma-btn ma-btn-cancel"  onclick="maUpdateStatus(' + apptID + ',\'cancelled\',this,\'pending\')">✕ Cancel</button>';
    } else if (newStatus === 'confirmed') {
        html += '<button class="ma-btn ma-btn-complete" onclick="maUpdateStatus(' + apptID + ',\'completed\',this,\'confirmed\')">✓ Complete</button>';
        html += '<button class="ma-btn ma-btn-cancel"   onclick="maUpdateStatus(' + apptID + ',\'cancelled\',this,\'confirmed\')">✕ Cancel</button>';
    } else if (newStatus === 'completed') {
        html += '<a class="ma-btn ma-btn-record" href="staff_health_record.php?patient=' + uID + '">+ Record</a>';
        html += '<a class="ma-btn ma-btn-confirm" href="generate_invoice.php?appointmentID=' + apptID + '" style="background:#7c3aed;color:#fff;border-color:#7c3aed">🧾 Invoice</a>';
    }
    html += '<button class="ma-btn ma-btn-view" onclick="maOpenModal(' + apptID + ')">👁 View</button>';
    cell.innerHTML = html;
}

// ── View Modal ─────────────────────────────────────────────────
function maOpenModal(apptID) {
    var row = document.getElementById('ma-row-' + apptID);
    if (!row) return;
    var d;
    try { d = JSON.parse(row.dataset.json); } catch (e) { return; }

    var statusHtml = '<span class="ma-badge ' + d.status + '">' + d.status.charAt(0).toUpperCase() + d.status.slice(1) + '</span>';
    var html = '<div class="ma-modal-sec">' +
        '<div class="ma-modal-sec-title">Patient</div>' +
        '<div class="ma-modal-grid">' +
        '<div class="ma-modal-field"><label>Name</label><p>' + esc(d.patient) + '</p></div>' +
        '<div class="ma-modal-field"><label>Email</label><p>' + esc(d.email) + '</p></div>' +
        '<div class="ma-modal-field"><label>Phone</label><p>' + (d.phone || '—') + '</p></div>' +
        '<div class="ma-modal-field"><label>Age / Gender</label><p>' + (d.age > 0 ? d.age + (d.gender ? ' / ' + d.gender : '') : '—') + '</p></div>' +
        '</div></div>' +
        '<div class="ma-modal-sec">' +
        '<div class="ma-modal-sec-title">Appointment</div>' +
        '<div class="ma-modal-grid">' +
        '<div class="ma-modal-field"><label>Date</label><p>' + formatDate(d.date) + '</p></div>' +
        '<div class="ma-modal-field"><label>Time</label><p>' + esc(d.time) + '</p></div>' +
        '<div class="ma-modal-field"><label>Service</label><p>' + esc(d.service) + '</p></div>' +
        '<div class="ma-modal-field"><label>Duration</label><p>' + esc(d.duration) + '</p></div>' +
        '<div class="ma-modal-field"><label>Dentist</label><p>' + esc(d.dentist) + '</p></div>' +
        '<div class="ma-modal-field"><label>Status</label><p>' + statusHtml + '</p></div>' +
        '</div></div>';

    if (d.health && d.health.indexOf('No health') === -1) {
        html += '<div class="ma-modal-sec"><div class="ma-modal-sec-title">Health Concern</div><p style="font-size:13.5px;color:#1a0e2e;margin:0">' + esc(d.health) + '</p></div>';
    }
    if (d.notes) {
        html += '<div class="ma-modal-sec"><div class="ma-modal-sec-title">Notes</div><div class="ma-modal-notes">' + esc(d.notes) + '</div></div>';
    }
    if (d.receipt) {
        html += '<div class="ma-modal-sec"><div class="ma-modal-sec-title">Payment Receipt</div><p style="font-size:13px;color:#72647a;margin:0">' + esc(d.receipt) + '</p></div>';
    }

    document.getElementById('maModalBody').innerHTML = html;
    document.getElementById('maModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function maCloseModal() {
    document.getElementById('maModal').style.display = 'none';
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function (e) { if (e.key === 'Escape') maCloseModal(); });

// ── Helpers ────────────────────────────────────────────────────
function esc(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function formatDate(dateStr) {
    if (!dateStr) return '—';
    var d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

// ── Payment verification ───────────────────────────────────────
function maVerifyPayment(paymentID, action, btn) {
    var label = action === 'verify' ? 'verify' : 'reject';
    if (!confirm('Are you sure you want to ' + label + ' this payment?')) return;

    var cell = document.getElementById('ma-pay-btns-' + paymentID);
    if (cell) cell.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
    btn.textContent = '…';

    var fd = new FormData();
    fd.append('_csrf_token', MA_CSRF);
    fd.append('paymentID', paymentID);
    fd.append('action', action);

    fetch('verify_payment.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                var row = document.getElementById('ma-pay-row-' + paymentID);
                if (row) {
                    var badge = document.createElement('span');
                    badge.className = action === 'verify' ? 'ma-pay-badge-paid' : 'ma-pay-badge-rejected';
                    badge.textContent = action === 'verify' ? 'Verified' : 'Rejected';
                    if (cell) { cell.innerHTML = ''; cell.appendChild(badge); }
                }
                var cntEl = document.getElementById('ma-tab-cnt-payment');
                if (cntEl) {
                    var n = Math.max(0, parseInt(cntEl.textContent) - 1);
                    cntEl.textContent = n;
                    cntEl.style.background = n > 0 ? '#ef4444' : '#ede8f8';
                    cntEl.style.color      = n > 0 ? '#fff'    : '#5b21b6';
                }
            } else {
                alert(d.error || 'Action failed.');
                if (cell) cell.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
            }
        })
        .catch(function () {
            alert('Network error. Please try again.');
            if (cell) cell.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
        });
}

function maViewProof(filename) {
    var ext = filename.split('.').pop().toLowerCase();
    var url = 'uploads/payments/' + filename;
    if (ext === 'pdf') {
        window.open(url, '_blank');
        return;
    }
    var overlay = document.createElement('div');
    overlay.className = 'ma-proof-overlay';
    overlay.innerHTML = '<div style="position:relative;max-width:90vw;max-height:90vh">' +
        '<img src="' + esc(url) + '" style="max-width:90vw;max-height:85vh;border-radius:10px;display:block;box-shadow:0 20px 60px rgba(0,0,0,.4)">' +
        '<button onclick="this.closest(\'.ma-proof-overlay\').remove()" style="position:absolute;top:-12px;right:-12px;width:30px;height:30px;border-radius:50%;background:#ef4444;color:#fff;border:none;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;font-weight:700">✕</button>' +
        '</div>';
    overlay.addEventListener('click', function (e) { if (e.target === overlay) overlay.remove(); });
    document.body.appendChild(overlay);
}

// ── Initial render ─────────────────────────────────────────────
(function () { maFilter(); }());
</script>
    <?php
}
