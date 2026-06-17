<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

if (empty($_SESSION['userID'])) { header('Location: login.php'); exit; }

$userID = (int) $_SESSION['userID'];
$user   = db_one('SELECT * FROM tbl_user WHERE userID = ? AND status = ?', [$userID, 'active']);
if (!$user) { session_destroy(); header('Location: login.php'); exit; }

// ── Appointments ──────────────────────────────────────────────────────────────
$today          = date('Y-m-d');
$appointments   = db_all('SELECT * FROM tbl_appointment WHERE userID = ? ORDER BY appointmentDate DESC LIMIT 10', [$userID]);
$totalApptCount = (int) (db_one('SELECT COUNT(*) AS c FROM tbl_appointment WHERE userID = ?', [$userID])['c'] ?? 0);
$completedCount = (int) (db_one("SELECT COUNT(*) AS c FROM tbl_appointment WHERE userID = ? AND status='completed'", [$userID])['c'] ?? 0);
$nextAppt       = db_one("SELECT * FROM tbl_appointment WHERE userID=? AND appointmentDate>=? AND status IN ('pending','confirmed') ORDER BY appointmentDate ASC, appointmentTime ASC LIMIT 1", [$userID, $today]);

// ── Rewards ───────────────────────────────────────────────────────────────────
$rewardRow     = db_one('SELECT currentBalance FROM tbl_reward WHERE userID=? ORDER BY rewardID DESC LIMIT 1', [$userID]);
$rewardBalance = (int) ($rewardRow['currentBalance'] ?? 0);
$totalEarned   = (int) (db_one('SELECT COALESCE(SUM(pointsEarned),0) AS t FROM tbl_reward WHERE userID=?', [$userID])['t'] ?? 0);
$recentRewards = db_all('SELECT * FROM tbl_reward WHERE userID=? ORDER BY rewardID DESC LIMIT 5', [$userID]);
$rewardCatalog = db_all('SELECT * FROM tbl_reward_catalog WHERE isActive=1 ORDER BY pointsRequired ASC', []);
$nextReward    = null;
foreach ($rewardCatalog as $r) { if ((int)$r['pointsRequired'] > $rewardBalance) { $nextReward = $r; break; } }
$progressPct   = $nextReward ? min(100, (int) round($rewardBalance / max(1,(int)$nextReward['pointsRequired']) * 100)) : 100;

// ── Health & clinic ───────────────────────────────────────────────────────────
$clinic      = db_one('SELECT * FROM tbl_clinic ORDER BY clinicID ASC LIMIT 1') ?? [];
$chronicRaw  = trim((string) ($user['userChronicHealthProblems'] ?? ''));
$chronicList = $chronicRaw !== '' ? array_values(array_filter(array_map('trim', explode(',', $chronicRaw)))) : [];
$healthStatus = empty($chronicList) ? 'Good' : 'See Doctor';
$initials     = strtoupper(substr((string) $user['username'], 0, 2));
$avatarUrl    = user_avatar_url($user);

// ── Dental records ────────────────────────────────────────────────────────────
$dentalRecords = db_all(
    'SELECT dr.*, u.username AS dentistName, a.serviceType AS appointmentService
       FROM tbl_dental_record dr
       JOIN tbl_user u ON dr.recordedBy = u.userID
       LEFT JOIN tbl_appointment a ON dr.appointmentID = a.appointmentID
      WHERE dr.userID = ?
      ORDER BY dr.recordDate DESC
      LIMIT 5',
    [$userID]
);
$dentalTotal = (int) ((db_one('SELECT COUNT(*) AS c FROM tbl_dental_record WHERE userID=?', [$userID]) ?? [])['c'] ?? 0);

$dConditions = array_column($dentalRecords, 'toothCondition');
if (empty($dConditions)) {
    $healthScore = 'No Data'; $hsBg = '#f3f4f6'; $hsCol = '#6b7280'; $hsBorder = '#d1d5db';
} elseif (in_array('needs_treatment', $dConditions)) {
    $healthScore = 'Needs Attention'; $hsBg = '#fee2e2'; $hsCol = '#dc2626'; $hsBorder = '#fca5a5';
} elseif (in_array('monitor', $dConditions)) {
    $healthScore = 'Fair'; $hsBg = '#fef3c7'; $hsCol = '#d97706'; $hsBorder = '#fde68a';
} else {
    $healthScore = 'Excellent'; $hsBg = '#d1fae5'; $hsCol = '#059669'; $hsBorder = '#6ee7b7';
}
$lastDentalRec   = $dentalRecords[0] ?? null;
$lastCheckupDate = $lastDentalRec ? date('d M Y', (int) strtotime((string) $lastDentalRec['recordDate'])) : null;
$nextCheckupDate = $lastDentalRec ? date('d M Y', (int) strtotime('+3 months', (int) strtotime((string) $lastDentalRec['recordDate']))) : null;

// Tooth condition map: tooth# → ['condition','diagnosis','treatment','date']
$toothMap = [];
foreach ($dentalRecords as $rec) {
    $tn = trim((string) ($rec['toothNumber'] ?? ''));
    if ($tn !== '' && ctype_digit($tn)) {
        $n = (int) $tn;
        if ($n >= 1 && $n <= 32 && !isset($toothMap[$n])) {
            $toothMap[$n] = [
                'condition' => (string) $rec['toothCondition'],
                'diagnosis' => (string) $rec['diagnosis'],
                'treatment' => (string) $rec['treatmentDone'],
                'date'      => date('d M Y', (int) strtotime((string) $rec['recordDate'])),
            ];
        }
    }
}

// ── Timeline grouping ─────────────────────────────────────────────────────────
$timelineGroups = [];
foreach ($appointments as $a) {
    $k = date('F Y', (int) strtotime((string) $a['appointmentDate']));
    $timelineGroups[$k][] = $a;
}

$statusCfg = [
    'completed' => ['bg'=>'#d1fae5','col'=>'#059669','icon'=>'<polyline points="20 6 9 17 4 12"/>'],
    'confirmed' => ['bg'=>'#dbeafe','col'=>'#1d4ed8','icon'=>'<polyline points="20 6 9 17 4 12"/>'],
    'pending'   => ['bg'=>'#fef3c7','col'=>'#d97706','icon'=>'<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'],
    'cancelled' => ['bg'=>'#fee2e2','col'=>'#dc2626','icon'=>'<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>'],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Health Record | Detabot</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css">
<style>
/* ── Sidebar collapse ──────────────────────────────────────── */
.app-shell.sb-collapsed { grid-template-columns: 68px minmax(0,1fr); }
.app-shell.sb-collapsed .sidebar { width: 68px; overflow: visible; }
.app-shell.sb-collapsed .sb-brand-text,
.app-shell.sb-collapsed .sb-clinic-card,
.app-shell.sb-collapsed .sb-nav-text,
.app-shell.sb-collapsed .sb-nav-label,
.app-shell.sb-collapsed .sb-badge,
.app-shell.sb-collapsed .sb-user-info { display: none; }
.app-shell.sb-collapsed .sb-nav-item { justify-content: center; padding: 10px 0; }
.app-shell.sb-collapsed .sb-nav-icon { margin: 0; }
.app-shell.sb-collapsed .sb-user { justify-content: center; gap: 0; }
.app-shell.sb-collapsed .sb-brand { padding: 14px 12px; }
.sb-toggle-btn {
    display: flex; align-items: center; justify-content: center;
    width: 30px; height: 30px; border-radius: 8px; border: none; cursor: pointer;
    background: rgba(255,255,255,.12); color: #fff; flex-shrink: 0;
    transition: background .15s; margin-left: auto;
}
.sb-toggle-btn:hover { background: rgba(255,255,255,.22); }
.sb-toggle-btn svg { width: 16px; height: 16px; }

/* ── Health Record content ─────────────────────────────────── */
.hr-content { padding: 24px 28px 48px; display: flex; flex-direction: column; gap: 20px; }

/* Profile card */
.hr-profile { background:#fff; border-radius: 18px; padding: 24px 28px; border: 1px solid #ede9fe; box-shadow: 0 1px 8px rgba(59,7,100,.07); display: flex; align-items: center; gap: 24px; flex-wrap: wrap; }
.hr-avatar { width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg,#c84fce,#8b5cf6); display: flex; align-items: center; justify-content: center; font-family:'Sora',sans-serif; font-size:24px; font-weight:800; color:#fff; flex-shrink:0; box-shadow:0 4px 18px rgba(139,92,246,.35); overflow:hidden; }
.hr-avatar img { width:100%; height:100%; object-fit:cover; display:block; }
.hr-profile-info { flex:1; min-width: 200px; }
.hr-profile-name { font-family:'Sora',sans-serif; font-size:20px; font-weight:800; color:#1e1b4b; margin:0 0 4px; }
.hr-profile-detail { font-size:13px; color:#6b7280; margin: 2px 0; }
.hr-profile-detail strong { color:#374151; }
.hr-active-badge { display:inline-block; background:#d1fae5; color:#065f46; font-size:11.5px; font-weight:700; padding:4px 12px; border-radius:20px; margin-bottom:10px; }
.hr-profile-actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-top:10px; }
.hr-edit-btn { display:inline-flex; align-items:center; gap:6px; background: linear-gradient(135deg,#c84fce,#8b5cf6); color:#fff; font-size:13px; font-weight:600; padding:8px 18px; border-radius:10px; text-decoration:none; transition:opacity .15s; }
.hr-edit-btn:hover { opacity:.88; }

/* Summary grid */
.hr-summary-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; }
@media(max-width:960px){ .hr-summary-grid{ grid-template-columns:repeat(2,1fr); } }
.hr-summary-card { background:#fff; border-radius:16px; padding:20px 22px; border:1px solid #ede9fe; box-shadow:0 1px 6px rgba(59,7,100,.06); }
.hr-summary-icon { font-size:26px; margin-bottom:10px; }
.hr-summary-val { font-family:'Sora',sans-serif; font-size:26px; font-weight:800; color:#1e1b4b; line-height:1.1; margin-bottom:3px; }
.hr-summary-val.good { color:#059669; }
.hr-summary-val.warn { color:#d97706; }
.hr-summary-label { font-size:12px; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:.04em; }
.hr-summary-sub { font-size:11.5px; color:#7c3aed; font-weight:600; margin-top:5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

/* Section wrapper */
.hr-section { background:#fff; border-radius:16px; padding:22px 24px; border:1px solid #ede9fe; box-shadow:0 1px 6px rgba(59,7,100,.06); }
.hr-sec-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
.hr-sec-title { font-family:'Sora',sans-serif; font-size:15px; font-weight:700; color:#1e1b4b; margin:0; }
.hr-sec-link { font-size:13px; font-weight:600; color:#7c3aed; text-decoration:none; display:inline-flex; align-items:center; gap:4px; }
.hr-sec-link:hover { color:#6d28d9; }
.hr-note { font-size:12px; color:#9ca3af; margin-top:10px; font-style:italic; }

/* Conditions */
.hr-pill { display:inline-block; padding:5px 14px; border-radius:20px; font-size:12.5px; font-weight:600; margin:3px; }
.hr-pill-amber { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }
.hr-pill-green { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; }

/* Timeline */
.hr-tl-month { font-size:11.5px; font-weight:700; color:#7c3aed; text-transform:uppercase; letter-spacing:.06em; margin:14px 0 8px; }
.hr-tl-month:first-child { margin-top: 0; }
.hr-tl-entry { display:flex; gap:14px; align-items:flex-start; padding:10px; border-radius:12px; transition:background .15s; }
.hr-tl-entry:hover { background:#faf8ff; }
.hr-status-dot { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.hr-status-dot svg { width:16px; height:16px; }
.hr-tl-body { flex:1; min-width:0; }
.hr-tl-service { font-size:14px; font-weight:700; color:#1e1b4b; }
.hr-tl-meta { font-size:12px; color:#6b7280; margin-top:2px; }
.hr-tl-notes { font-size:11.5px; color:#9ca3af; font-style:italic; margin-top:3px; }
.hr-tl-right { display:flex; flex-direction:column; align-items:flex-end; gap:6px; flex-shrink:0; }
.hr-dur-badge { font-size:11px; font-weight:600; color:#7c3aed; background:#f5f3ff; padding:3px 10px; border-radius:20px; white-space:nowrap; }
.hr-status-label { font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; text-transform:capitalize; }
.hr-empty { text-align:center; color:#9ca3af; padding:32px 0 16px; }
.hr-empty p { font-size:14px; margin-bottom:14px; }

/* Rewards */
.hr-rewards-cols { display:grid; grid-template-columns:200px 1fr; gap:28px; }
@media(max-width:800px){ .hr-rewards-cols{ grid-template-columns:1fr; } }
.hr-balance-box { text-align:center; padding:20px 12px; background:linear-gradient(135deg,#f5f3ff,#ede9fe); border-radius:14px; }
.hr-balance-num { font-family:'Sora',sans-serif; font-size:52px; font-weight:800; color:#6d28d9; line-height:1; display:block; }
.hr-balance-label { font-size:12px; color:#7c3aed; font-weight:700; margin-top:4px; text-transform:uppercase; letter-spacing:.05em; }
.hr-progress-track { background:#ede9fe; border-radius:20px; height:9px; overflow:hidden; margin:14px 0 6px; }
.hr-progress-fill { height:100%; border-radius:20px; background:linear-gradient(90deg,#c84fce,#8b5cf6); transition:width .5s; }
.hr-progress-hint { font-size:11.5px; color:#7c3aed; font-weight:600; }
.hr-catalog-item { display:flex; align-items:center; gap:12px; padding:11px 0; border-bottom:1px solid #f5f3ff; }
.hr-catalog-item:last-child { border-bottom:none; }
.hr-catalog-pts { font-family:'Sora',sans-serif; font-size:16px; font-weight:800; color:#6d28d9; min-width:40px; }
.hr-catalog-info { flex:1; }
.hr-catalog-name { font-size:13.5px; font-weight:700; color:#1e1b4b; }
.hr-catalog-desc { font-size:11.5px; color:#6b7280; }
.hr-redeem-btn { padding:6px 14px; border-radius:20px; font-size:12px; font-weight:700; border:none; cursor:pointer; transition:opacity .15s; }
.hr-redeem-active { background:#d1fae5; color:#065f46; }
.hr-redeem-inactive { background:#f3f4f6; color:#9ca3af; cursor:default; font-size:11px; }
.hr-txn-row { display:flex; justify-content:space-between; align-items:center; padding:7px 0; border-bottom:1px solid #f5f3ff; font-size:13px; }
.hr-txn-row:last-child { border-bottom:none; }
.hr-txn-desc { color:#374151; flex:1; padding-right:8px; }
.hr-txn-pts { font-weight:700; }

/* Dental sections */
.dental-overview { border-left: 5px solid #6d28d9; }
.dental-score-badge { display:inline-flex; align-items:center; gap:8px; padding:8px 18px; border-radius:24px; font-weight:700; font-size:15px; }
.teeth-chart-wrap { overflow-x:auto; padding-bottom:8px; }
.teeth-chart { display:flex; flex-direction:column; align-items:center; gap:6px; min-width:520px; }
.teeth-row { display:flex; gap:4px; align-items:flex-end; }
.teeth-row.upper .tooth { border-radius:6px 6px 10px 10px; }
.teeth-row.lower .tooth { border-radius:10px 10px 6px 6px; }
.teeth-midline { width:100%; border-top:2px dashed #d8b4fe; margin:2px 0; opacity:.6; }
.tooth { width:26px; height:32px; border-radius:6px; border:1.5px solid #d1d5db; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:8px; font-weight:700; color:#6b7280; transition:transform .12s, box-shadow .12s; position:relative; }
.tooth:hover { transform:scale(1.18); box-shadow:0 2px 10px rgba(109,40,217,.25); z-index:10; }
.tooth[data-c="good"]             { background:#eaf3de; border-color:#86c04e; }
.tooth[data-c="monitor"]          { background:#faeeda; border-color:#f59e0b; }
.tooth[data-c="needs_treatment"]  { background:#fcebeb; border-color:#ef4444; }
.tooth[data-c="extracted"]        { background:#d3d1c7; border-color:#9ca3af; color:#888; }
.tooth[data-c="none"]             { background:#fff;    border-color:#e5e7eb; }
.teeth-legend { display:flex; gap:16px; flex-wrap:wrap; justify-content:center; margin-top:10px; }
.teeth-legend-item { display:flex; align-items:center; gap:5px; font-size:12px; color:#6b7280; }
.teeth-legend-dot { width:12px; height:12px; border-radius:3px; flex-shrink:0; }
.tooth-tooltip { position:fixed; background:#1e1b4b; color:#fff; font-size:12px; padding:7px 12px; border-radius:8px; pointer-events:none; display:none; z-index:9999; max-width:220px; line-height:1.5; white-space:pre-line; }
.dr-card { border:1px solid #ede9fe; border-radius:14px; padding:16px 18px; margin-bottom:14px; background:#faf8ff; transition:border-color .15s; }
.dr-card:hover { border-color:#c4b5fd; }
.dr-card-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px; gap:12px; flex-wrap:wrap; }
.dr-date { font-size:13px; font-weight:700; color:#1e1b4b; }
.dr-dentist { font-size:12px; color:#7c3aed; font-weight:600; }
.dr-service { font-size:11.5px; color:#9ca3af; }
.dr-tooth-badge { background:#ede9fe; color:#6d28d9; font-size:11.5px; font-weight:700; padding:3px 10px; border-radius:20px; white-space:nowrap; }
.dr-row { display:flex; gap:8px; margin-bottom:7px; font-size:13.5px; align-items:flex-start; }
.dr-label { color:#9ca3af; font-size:12px; font-weight:600; min-width:100px; flex-shrink:0; padding-top:1px; }
.dr-value { color:#1e1b4b; flex:1; line-height:1.5; }
.dr-next { color:#6b7280; font-style:italic; font-size:12.5px; display:flex; align-items:flex-start; gap:5px; margin-top:6px; }
.dr-notes-toggle { font-size:12px; color:#7c3aed; cursor:pointer; font-weight:600; background:none; border:none; padding:0; margin-top:6px; }
.dr-notes-body { font-size:12.5px; color:#6b7280; margin-top:6px; background:#fff; padding:10px 12px; border-radius:8px; border:1px solid #ede9fe; display:none; }
.dr-cond-badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11.5px; font-weight:700; }

/* Quick access */
.hr-quick-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; }
@media(max-width:900px){ .hr-quick-grid{ grid-template-columns:repeat(2,1fr); } }
.hr-quick-card { background:linear-gradient(135deg,#f5f3ff,#ede9fe); border:1.5px solid #ddd6fe; border-radius:14px; padding:22px 16px; text-align:center; cursor:pointer; transition:transform .15s, box-shadow .15s, background .15s; }
.hr-quick-card:hover { transform:translateY(-3px); box-shadow:0 6px 20px rgba(109,40,217,.14); background:linear-gradient(135deg,#ede9fe,#ddd6fe); }
.hr-quick-icon { font-size:30px; margin-bottom:8px; }
.hr-quick-title { font-size:13.5px; font-weight:700; color:#1e1b4b; }
.hr-quick-desc { font-size:11.5px; color:#7c3aed; margin-top:4px; line-height:1.4; }
</style>
</head>
<body>
<div class="app-shell" id="appShell">

<!-- ═══════════════════════ SIDEBAR ═══════════════════════ -->
<aside class="sidebar" id="mainSidebar">

    <!-- Brand + toggle -->
    <div class="sb-brand" style="display:flex;align-items:center;gap:8px;">
        <a class="sb-logo-link" href="health_record.php" style="flex:1;">
            <div class="sb-logo-box"><img src="assets/detabot-logo.svg" alt="Detabot"></div>
            <div class="sb-brand-text">
                <strong class="sb-brand-name">Detabot</strong>
                <small class="sb-brand-sub">Putra Dental Clinic</small>
            </div>
        </a>
        <button class="sb-toggle-btn" id="sidebarToggle" title="Collapse sidebar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
    </div>

    <!-- Clinic card -->
    <div class="sb-clinic-card">
        <img class="sb-clinic-logo" src="assets/clinic-logo.png" alt="Putra Dental Clinic">
        <div>
            <span class="sb-clinic-name"><?= e((string) ($clinic['clinicName'] ?? 'Putra Dental Clinic')) ?></span>
            <span class="sb-clinic-loc"><?= e((string) ($clinic['location'] ?? 'Parit Raja, Johor')) ?></span>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sb-nav">
        <div class="sb-nav-group">
            <span class="sb-nav-label">Main</span>
            <a class="sb-nav-item" href="dashboard.php">
                <span class="sb-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>
                <span class="sb-nav-text">Dashboard</span>
            </a>
            <a class="sb-nav-item" href="appointments.php">
                <span class="sb-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg></span>
                <span class="sb-nav-text">Appointments</span>
            </a>
            <a class="sb-nav-item active" href="health_record.php">
                <span class="sb-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg></span>
                <span class="sb-nav-text">Health Record</span>
            </a>
        </div>
        <div class="sb-nav-group">
            <span class="sb-nav-label">More</span>
            <a class="sb-nav-item" href="clinic.php">
                <span class="sb-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21V7l8-4 8 4v14"/><path d="M9 21v-6h6v6"/><path d="M10 9h4M12 7v4"/><path d="M4 21h16"/></svg></span>
                <span class="sb-nav-text">Clinic Information</span>
            </a>
            <a class="sb-nav-item" href="feedback.php">
                <span class="sb-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 0 1-8 8H7l-4 3v-6.5A8 8 0 1 1 21 12Z"/><path d="M8 12h8M8 15h5"/></svg></span>
                <span class="sb-nav-text">Feedback</span>
            </a>
            <a class="sb-nav-item" href="rewards.php">
                <span class="sb-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="13" rx="2"/><path d="M12 8v13M3 12h18"/><path d="M7.5 8A2.5 2.5 0 1 1 12 6.5V8"/><path d="M16.5 8A2.5 2.5 0 1 0 12 6.5V8"/></svg></span>
                <span class="sb-nav-text">Rewards</span>
                <span class="sb-badge sb-badge-amber">New</span>
            </a>
            <a class="sb-nav-item" href="profile.php">
                <span class="sb-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></span>
                <span class="sb-nav-text">My Profile</span>
            </a>
        </div>
    </nav>

    <!-- User bottom -->
    <div class="sb-user">
        <div class="sb-user-avatar">
            <?php if ($avatarUrl): ?><img src="<?= e($avatarUrl) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;"><?php else: ?><?= e($initials) ?><?php endif; ?>
        </div>
        <div class="sb-user-info">
            <span class="sb-user-name"><?= e((string) $user['username']) ?></span>
            <span class="sb-user-role"><?= e(ucfirst((string) $user['userRole'])) ?></span>
        </div>
        <a class="sb-logout-btn" href="logout.php" title="Logout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </a>
    </div>
</aside>

<!-- ═══════════════════════ MAIN ═══════════════════════════ -->
<main class="main-panel">

    <!-- Topbar -->
    <header class="topbar">
        <div>
            <p class="eyebrow">PATIENT</p>
            <h1>Health Record</h1>
        </div>
        <div class="user-menu">
            <div class="hr-avatar" style="width:36px;height:36px;font-size:13px;box-shadow:none;">
                <?php if ($avatarUrl): ?><img src="<?= e($avatarUrl) ?>" alt=""><?php else: ?><?= e($initials) ?><?php endif; ?>
            </div>
            <span class="topbar-username"><?= e((string) $user['username']) ?></span>
            <a href="logout.php" class="btn ghost">Logout</a>
        </div>
    </header>

    <div class="hr-content">

        <!-- ── Section 1: Patient Profile ──────────────────── -->
        <div class="hr-profile">
            <div class="hr-avatar">
                <?php if ($avatarUrl): ?>
                    <img src="<?= e($avatarUrl) ?>" alt="<?= e((string) $user['username']) ?>">
                <?php else: ?>
                    <?= e($initials) ?>
                <?php endif; ?>
            </div>
            <div class="hr-profile-info">
                <span class="hr-active-badge">✓ Active Patient</span>
                <h2 class="hr-profile-name"><?= e((string) $user['username']) ?></h2>
                <p class="hr-profile-detail">📧 <strong>Email:</strong> <?= e((string) $user['userEmail']) ?></p>
                <p class="hr-profile-detail">📞 <strong>Phone:</strong> <?= e((string) ($user['userPhone'] ?? '—')) ?></p>
                <p class="hr-profile-detail">🎂 <strong>Age:</strong> <?= e((string) ($user['userAge'] ?? '—')) ?> &nbsp;|&nbsp; 👥 <strong>Gender:</strong> <?= e(ucfirst((string) ($user['userGender'] ?? '—'))) ?></p>
                <div class="hr-profile-actions">
                    <a class="hr-edit-btn" href="profile.php">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Edit Profile
                    </a>
                </div>
            </div>
        </div>

        <!-- ── Section 2: Health Summary Cards ─────────────── -->
        <div class="hr-summary-grid">

            <div class="hr-summary-card">
                <div class="hr-summary-icon">🦷</div>
                <div class="hr-summary-val"><?= $completedCount ?></div>
                <div class="hr-summary-label">Total Visits</div>
                <div class="hr-summary-sub"><?= $totalApptCount ?> total appointments</div>
            </div>

            <div class="hr-summary-card">
                <div class="hr-summary-icon">📅</div>
                <?php if ($nextAppt): ?>
                    <div class="hr-summary-val" style="font-size:16px;line-height:1.3;">
                        <?= e(date('d M Y', (int) strtotime((string) $nextAppt['appointmentDate']))) ?>
                    </div>
                    <div class="hr-summary-label">Next Appointment</div>
                    <div class="hr-summary-sub"><?= e((string) $nextAppt['serviceType']) ?></div>
                <?php else: ?>
                    <div class="hr-summary-val" style="font-size:15px;color:#9ca3af;">No upcoming</div>
                    <div class="hr-summary-label">Next Appointment</div>
                    <div class="hr-summary-sub"><a href="appointments.php" style="color:#7c3aed;">Book now →</a></div>
                <?php endif; ?>
            </div>

            <div class="hr-summary-card">
                <div class="hr-summary-icon">⭐</div>
                <div class="hr-summary-val"><?= $rewardBalance ?></div>
                <div class="hr-summary-label">Reward Points</div>
                <div class="hr-summary-sub"><?= $totalEarned ?> pts earned total</div>
            </div>

            <div class="hr-summary-card">
                <div class="hr-summary-icon"><?= $healthStatus === 'Good' ? '💚' : '⚠️' ?></div>
                <div class="hr-summary-val <?= $healthStatus === 'Good' ? 'good' : 'warn' ?>"><?= e($healthStatus) ?></div>
                <div class="hr-summary-label">Health Status</div>
                <div class="hr-summary-sub"><?= $healthStatus === 'Good' ? 'No chronic conditions' : count($chronicList) . ' condition(s) noted' ?></div>
            </div>

        </div>

        <!-- ── Section 3: Health Conditions ────────────────── -->
        <div class="hr-section">
            <div class="hr-sec-header">
                <h3 class="hr-sec-title">Health Conditions</h3>
                <a class="hr-sec-link" href="profile.php">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit
                </a>
            </div>
            <div>
                <?php if (empty($chronicList)): ?>
                    <span class="hr-pill hr-pill-green">✓ No known health conditions</span>
                <?php else: ?>
                    <?php foreach ($chronicList as $cond): ?>
                        <span class="hr-pill hr-pill-amber"><?= e($cond) ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <p class="hr-note">This information helps your dentist provide safer and more personalised treatment.</p>
        </div>

        <!-- ── Section 4: Appointment Timeline ─────────────── -->
        <div class="hr-section">
            <div class="hr-sec-header">
                <h3 class="hr-sec-title">Appointment History</h3>
                <?php if ($totalApptCount > 10): ?>
                    <a class="hr-sec-link" href="appointments.php">View all (<?= $totalApptCount ?>) →</a>
                <?php endif; ?>
            </div>

            <?php if (empty($appointments)): ?>
                <div class="hr-empty">
                    <p>No appointment history yet.</p>
                    <a href="appointments.php" class="hr-edit-btn" style="display:inline-flex;">📅 Book Now</a>
                </div>
            <?php else: ?>
                <?php foreach ($timelineGroups as $monthYear => $entries): ?>
                    <div class="hr-tl-month"><?= e($monthYear) ?></div>
                    <?php foreach ($entries as $appt): ?>
                        <?php
                        $st  = (string) $appt['status'];
                        $cfg = $statusCfg[$st] ?? $statusCfg['pending'];
                        $dur = (int) ($appt['duration'] ?? 60);
                        ?>
                        <div class="hr-tl-entry">
                            <div class="hr-status-dot" style="background:<?= $cfg['bg'] ?>;color:<?= $cfg['col'] ?>;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="<?= $cfg['col'] ?>" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><?= $cfg['icon'] ?></svg>
                            </div>
                            <div class="hr-tl-body">
                                <div class="hr-tl-service"><?= e((string) $appt['serviceType']) ?></div>
                                <div class="hr-tl-meta">
                                    <?= e(date('d M Y', (int) strtotime((string) $appt['appointmentDate']))) ?>
                                    &nbsp;·&nbsp;
                                    <?= e(date('g:i A', strtotime((string) $appt['appointmentTime']))) ?>
                                </div>
                                <?php if (!empty($appt['notes'])): ?>
                                    <div class="hr-tl-notes"><?= e((string) $appt['notes']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="hr-tl-right">
                                <span class="hr-status-label" style="background:<?= $cfg['bg'] ?>;color:<?= $cfg['col'] ?>;"><?= e(ucfirst($st)) ?></span>
                                <?php if ($dur > 0): ?>
                                    <span class="hr-dur-badge"><?= e(format_duration($dur)) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ── Section A: Teeth Health Overview ────────────── -->
        <div class="hr-section dental-overview" style="border-left-color:<?= $hsBorder ?>;">
            <div class="hr-sec-header">
                <h3 class="hr-sec-title">🦷 Dental Health Overview</h3>
                <?php if (has_role($user, ['admin','staff'])): ?>
                    <a class="hr-sec-link" href="add_dental_record.php">+ Add Record</a>
                <?php endif; ?>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:24px;align-items:center;">
                <div>
                    <div class="dental-score-badge" style="background:<?= $hsBg ?>;color:<?= $hsCol ?>;">
                        <?= $healthScore === 'Excellent' ? '✅' : ($healthScore === 'Fair' ? '⚠️' : ($healthScore === 'Needs Attention' ? '🔴' : '⬜')) ?>
                        <?= e($healthScore) ?>
                    </div>
                    <p style="font-size:12px;color:#9ca3af;margin:8px 0 0;">Overall dental health score</p>
                </div>
                <div style="display:flex;gap:20px;flex-wrap:wrap;">
                    <div>
                        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#7c3aed;font-weight:700;margin-bottom:3px;">Last Checkup</div>
                        <div style="font-size:15px;font-weight:700;color:#1e1b4b;"><?= $lastCheckupDate ?? '—' ?></div>
                    </div>
                    <div>
                        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#7c3aed;font-weight:700;margin-bottom:3px;">Next Recommended</div>
                        <div style="font-size:15px;font-weight:700;color:#1e1b4b;"><?= $nextCheckupDate ?? '—' ?></div>
                    </div>
                    <div>
                        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#7c3aed;font-weight:700;margin-bottom:3px;">Total Records</div>
                        <div style="font-size:15px;font-weight:700;color:#1e1b4b;"><?= $dentalTotal ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Section B: Teeth Condition Chart ─────────────── -->
        <div class="hr-section">
            <div class="hr-sec-header">
                <h3 class="hr-sec-title">Teeth Condition Map</h3>
                <span style="font-size:12px;color:#9ca3af;">Hover over a tooth for details</span>
            </div>
            <div class="teeth-chart-wrap">
                <div class="teeth-chart">
                    <?php
                    // Upper jaw display order: 1→16 (left on screen = patient's right)
                    $upper = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16];
                    // Lower jaw display order: 32→17 (mirrors upper)
                    $lower = [32,31,30,29,28,27,26,25,24,23,22,21,20,19,18,17];

                    function toothHtml(int $n, array $toothMap): string {
                        $d = $toothMap[$n] ?? null;
                        $c = $d ? $d['condition'] : 'none';
                        $tip = "Tooth $n";
                        $tip .= "\nCondition: " . ($d ? ucwords(str_replace('_',' ',$c)) : 'No record');
                        if ($d) {
                            $tip .= "\nTreatment: " . $d['treatment'];
                            $tip .= "\nDate: " . $d['date'];
                        }
                        return sprintf(
                            '<div class="tooth" data-c="%s" data-tip="%s">%d</div>',
                            htmlspecialchars($c, ENT_QUOTES), htmlspecialchars($tip, ENT_QUOTES), $n
                        );
                    }
                    ?>
                    <div style="font-size:10px;color:#9ca3af;text-align:center;margin-bottom:2px;letter-spacing:.05em;">UPPER JAW</div>
                    <div class="teeth-row upper">
                        <?php foreach ($upper as $n) echo toothHtml($n, $toothMap); ?>
                    </div>
                    <div class="teeth-midline"></div>
                    <div class="teeth-row lower">
                        <?php foreach ($lower as $n) echo toothHtml($n, $toothMap); ?>
                    </div>
                    <div style="font-size:10px;color:#9ca3af;text-align:center;margin-top:2px;letter-spacing:.05em;">LOWER JAW</div>
                </div>
            </div>
            <div class="teeth-legend">
                <div class="teeth-legend-item"><div class="teeth-legend-dot" style="background:#eaf3de;border:1.5px solid #86c04e;"></div> Healthy</div>
                <div class="teeth-legend-item"><div class="teeth-legend-dot" style="background:#faeeda;border:1.5px solid #f59e0b;"></div> Monitor</div>
                <div class="teeth-legend-item"><div class="teeth-legend-dot" style="background:#fcebeb;border:1.5px solid #ef4444;"></div> Needs Treatment</div>
                <div class="teeth-legend-item"><div class="teeth-legend-dot" style="background:#d3d1c7;border:1.5px solid #9ca3af;"></div> Extracted</div>
                <div class="teeth-legend-item"><div class="teeth-legend-dot" style="background:#fff;border:1.5px solid #e5e7eb;"></div> No Record</div>
            </div>
        </div>

        <!-- ── Section C: Dental Treatment History ───────────── -->
        <div class="hr-section">
            <div class="hr-sec-header">
                <h3 class="hr-sec-title">Dental Treatment Records</h3>
                <?php if ($dentalTotal > 5): ?>
                    <button class="hr-sec-link" id="loadMoreDental" style="background:none;border:none;cursor:pointer;">
                        View all (<?= $dentalTotal ?>) →
                    </button>
                <?php endif; ?>
            </div>

            <?php if (empty($dentalRecords)): ?>
                <div class="hr-empty" style="padding:32px 0;">
                    <div style="font-size:40px;margin-bottom:12px;">🦷</div>
                    <p style="color:#9ca3af;font-size:14px;margin:0;">No dental records yet. Your dentist will add notes after your visit.</p>
                </div>
            <?php else: ?>
                <div id="dentalRecordsList">
                <?php
                $condBadgeCfg = [
                    'good'             => ['bg'=>'#d1fae5','col'=>'#059669','label'=>'Good'],
                    'monitor'          => ['bg'=>'#fef3c7','col'=>'#d97706','label'=>'Monitor'],
                    'needs_treatment'  => ['bg'=>'#fee2e2','col'=>'#dc2626','label'=>'Needs Treatment'],
                    'extracted'        => ['bg'=>'#f3f4f6','col'=>'#6b7280','label'=>'Extracted'],
                ];
                foreach ($dentalRecords as $idx => $dr):
                    $cb  = $condBadgeCfg[$dr['toothCondition']] ?? ['bg'=>'#f3f4f6','col'=>'#6b7280','label'=>ucfirst((string)$dr['toothCondition'])];
                ?>
                <div class="dr-card">
                    <div class="dr-card-header">
                        <div>
                            <div class="dr-date"><?= e(date('d F Y', (int) strtotime((string) $dr['recordDate']))) ?></div>
                            <div class="dr-dentist">Dr. <?= e((string) $dr['dentistName']) ?></div>
                            <?php if (!empty($dr['appointmentService'])): ?>
                                <div class="dr-service"><?= e((string) $dr['appointmentService']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                            <?php if (!empty($dr['toothNumber'])): ?>
                                <span class="dr-tooth-badge">Tooth <?= e((string) $dr['toothNumber']) ?></span>
                            <?php endif; ?>
                            <span class="dr-cond-badge" style="background:<?= $cb['bg'] ?>;color:<?= $cb['col'] ?>;"><?= e($cb['label']) ?></span>
                        </div>
                    </div>
                    <div class="dr-row">
                        <span class="dr-label">Diagnosis</span>
                        <span class="dr-value"><?= e((string) $dr['diagnosis']) ?></span>
                    </div>
                    <div class="dr-row">
                        <span class="dr-label">Treatment Done</span>
                        <span class="dr-value"><?= e((string) $dr['treatmentDone']) ?></span>
                    </div>
                    <?php if (!empty($dr['nextAction'])): ?>
                        <div class="dr-next">
                            <span>→</span>
                            <span><?= e((string) $dr['nextAction']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($dr['dentistNotes'])): ?>
                        <button class="dr-notes-toggle" onclick="var n=this.nextElementSibling;n.style.display=n.style.display==='block'?'none':'block';this.textContent=n.style.display==='block'?'▲ Hide notes':'▼ Dentist notes'">▼ Dentist notes</button>
                        <div class="dr-notes-body"><?= e((string) $dr['dentistNotes']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                </div>
                <div id="dentalLoadMore"></div>
            <?php endif; ?>
        </div>

        <!-- Floating tooth tooltip -->
        <div class="tooth-tooltip" id="toothTooltip"></div>

        <!-- ── Section 5: Reward Points Tracker ────────────── -->
        <div class="hr-section">
            <div class="hr-sec-header">
                <h3 class="hr-sec-title">Reward Points Tracker</h3>
                <a class="hr-sec-link" href="rewards.php">View all →</a>
            </div>
            <div class="hr-rewards-cols">

                <!-- Balance + progress -->
                <div>
                    <div class="hr-balance-box">
                        <span class="hr-balance-num"><?= $rewardBalance ?></span>
                        <div class="hr-balance-label">Current Balance</div>
                    </div>
                    <?php if ($nextReward): ?>
                        <div class="hr-progress-track" style="margin-top:16px;">
                            <div class="hr-progress-fill" style="width:<?= $progressPct ?>%;"></div>
                        </div>
                        <div class="hr-progress-hint">
                            <?= $progressPct ?>% · <?= max(0, (int)$nextReward['pointsRequired'] - $rewardBalance) ?> more pts to unlock
                            <strong><?= e((string) $nextReward['rewardName']) ?></strong>
                        </div>
                    <?php else: ?>
                        <p style="font-size:12.5px;color:#059669;font-weight:600;margin-top:14px;text-align:center;">🎉 You can redeem all rewards!</p>
                    <?php endif; ?>

                    <?php if ($recentRewards): ?>
                        <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#7c3aed;margin:20px 0 6px;">Recent Transactions</p>
                        <?php foreach ($recentRewards as $rw): ?>
                            <div class="hr-txn-row">
                                <span class="hr-txn-desc"><?= e((string) $rw['rewardDescription']) ?></span>
                                <span class="hr-txn-pts" style="color:<?= $rw['transactionType']==='earned' ? '#059669' : '#dc2626' ?>;">
                                    <?= $rw['transactionType']==='earned' ? '+' : '-' ?><?= (int) ($rw['pointsEarned'] ?: $rw['pointsRedeemed']) ?> pts
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Reward catalog -->
                <div>
                    <p style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#7c3aed;margin:0 0 12px;">Available Rewards</p>
                    <?php if ($rewardCatalog): ?>
                        <?php foreach ($rewardCatalog as $r): ?>
                            <?php $canRedeem = $rewardBalance >= (int)$r['pointsRequired']; ?>
                            <div class="hr-catalog-item">
                                <div class="hr-catalog-pts"><?= (int)$r['pointsRequired'] ?><span style="font-size:10px;font-weight:600;color:#9ca3af;display:block;line-height:1;">pts</span></div>
                                <div class="hr-catalog-info">
                                    <div class="hr-catalog-name"><?= e((string) $r['rewardName']) ?></div>
                                    <div class="hr-catalog-desc"><?= e((string) $r['description']) ?></div>
                                </div>
                                <?php if ($canRedeem): ?>
                                    <button class="hr-redeem-btn hr-redeem-active" onclick="detabotAsk('I want to redeem <?= e((string)$r['rewardName']) ?>')">Redeem</button>
                                <?php else: ?>
                                    <span class="hr-redeem-btn hr-redeem-inactive">Need <?= (int)$r['pointsRequired'] - $rewardBalance ?> more pts</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:#9ca3af;font-size:13px;">No rewards available yet.</p>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <!-- ── Section 6: Ask Detabot Quick Access ──────────── -->
        <div class="hr-section">
            <div class="hr-sec-header">
                <h3 class="hr-sec-title">Ask Detabot</h3>
            </div>
            <div class="hr-quick-grid">
                <div class="hr-quick-card" onclick="detabotAsk('I would like to book a dental appointment')">
                    <div class="hr-quick-icon">📅</div>
                    <div class="hr-quick-title">Book Appointment</div>
                    <div class="hr-quick-desc">Schedule your next dental visit</div>
                </div>
                <div class="hr-quick-card" onclick="detabotAsk('Tell me about my dental health records and what I should know')">
                    <div class="hr-quick-icon">📋</div>
                    <div class="hr-quick-title">Check My Records</div>
                    <div class="hr-quick-desc">Get insights on your dental health</div>
                </div>
                <div class="hr-quick-card" onclick="detabotAsk('Give me some useful dental health tips and home care advice')">
                    <div class="hr-quick-icon">🦷</div>
                    <div class="hr-quick-title">Dental Tips</div>
                    <div class="hr-quick-desc">Home care & prevention advice</div>
                </div>
                <div class="hr-quick-card" onclick="detabotAsk('Show me my reward points balance and what rewards I can redeem')">
                    <div class="hr-quick-icon">⭐</div>
                    <div class="hr-quick-title">My Rewards</div>
                    <div class="hr-quick-desc">Check points and redeem rewards</div>
                </div>
            </div>
        </div>

    </div><!-- /hr-content -->
</main>
</div><!-- /app-shell -->

<!-- ═══════════════════ FLOATING CHATBOT ══════════════════════ -->
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
            <div class="chatbot-bubble">Hi <?= e((string) $user['username']) ?>! 🦷 How can I help you today?</div>
            <div class="chatbot-quick-replies">
                <button class="chatbot-quick-btn" data-msg="Book an appointment">📅 Book Appointment</button>
                <button class="chatbot-quick-btn" data-msg="Give me dental health tips">🦷 Dental Tips</button>
                <button class="chatbot-quick-btn" data-msg="Show my reward points">⭐ My Rewards</button>
                <button class="chatbot-quick-btn" data-msg="What are the clinic hours and location?">🏥 Clinic Info</button>
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
window.DETABOT_USER_ID  = <?= (int) $userID ?>;
window.DETABOT_USERNAME = <?= json_encode((string) $user['username']) ?>;

/* Sidebar toggle */
document.getElementById('sidebarToggle').addEventListener('click', function () {
    document.getElementById('appShell').classList.toggle('sb-collapsed');
});

/* Detabot quick-access trigger */
function detabotAsk(msg) {
    document.dispatchEvent(new CustomEvent('detabot:ask', { detail: msg }));
}

/* ── Tooth tooltip ───────────────────────────────────────── */
(function () {
    var tip = document.getElementById('toothTooltip');
    if (!tip) return;
    document.querySelectorAll('.tooth').forEach(function (t) {
        t.addEventListener('mouseenter', function (e) {
            tip.textContent = t.dataset.tip || '';
            tip.style.display = 'block';
        });
        t.addEventListener('mousemove', function (e) {
            var x = e.clientX + 14, y = e.clientY - 10;
            if (x + 230 > window.innerWidth) x = e.clientX - 230;
            tip.style.left = x + 'px';
            tip.style.top  = y + 'px';
        });
        t.addEventListener('mouseleave', function () { tip.style.display = 'none'; });
    });
})();

/* ── Load-more dental records ────────────────────────────── */
(function () {
    var btn    = document.getElementById('loadMoreDental');
    var list   = document.getElementById('dentalRecordsList');
    var more   = document.getElementById('dentalLoadMore');
    var offset = 5;
    if (!btn || !list) return;

    var condCfg = {
        good:            { bg:'#d1fae5', col:'#059669', label:'Good' },
        monitor:         { bg:'#fef3c7', col:'#d97706', label:'Monitor' },
        needs_treatment: { bg:'#fee2e2', col:'#dc2626', label:'Needs Treatment' },
        extracted:       { bg:'#f3f4f6', col:'#6b7280', label:'Extracted' },
    };

    function esc(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    }

    btn.addEventListener('click', function () {
        btn.textContent = 'Loading…';
        btn.disabled = true;
        fetch('get_dental_records.php?userID=<?= $userID ?>&offset=' + offset)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.records || !data.records.length) { btn.remove(); return; }
                data.records.forEach(function (dr) {
                    var cb = condCfg[dr.toothCondition] || { bg:'#f3f4f6', col:'#6b7280', label: dr.toothCondition };
                    var html = '<div class="dr-card">'
                        + '<div class="dr-card-header"><div>'
                        + '<div class="dr-date">' + esc(dr.recordDateFormatted) + '</div>'
                        + '<div class="dr-dentist">Dr. ' + esc(dr.dentistName) + '</div>'
                        + (dr.appointmentService ? '<div class="dr-service">' + esc(dr.appointmentService) + '</div>' : '')
                        + '</div><div style="display:flex;gap:6px;flex-wrap:wrap;">'
                        + (dr.toothNumber ? '<span class="dr-tooth-badge">Tooth ' + esc(dr.toothNumber) + '</span>' : '')
                        + '<span class="dr-cond-badge" style="background:' + cb.bg + ';color:' + cb.col + ';">' + esc(cb.label) + '</span>'
                        + '</div></div>'
                        + '<div class="dr-row"><span class="dr-label">Diagnosis</span><span class="dr-value">' + esc(dr.diagnosis) + '</span></div>'
                        + '<div class="dr-row"><span class="dr-label">Treatment Done</span><span class="dr-value">' + esc(dr.treatmentDone) + '</span></div>'
                        + (dr.nextAction ? '<div class="dr-next"><span>→</span><span>' + esc(dr.nextAction) + '</span></div>' : '')
                        + '</div>';
                    list.insertAdjacentHTML('beforeend', html);
                });
                offset += data.records.length;
                if (data.hasMore) {
                    btn.textContent = 'Load more →';
                    btn.disabled = false;
                } else {
                    btn.remove();
                }
            })
            .catch(function () { btn.textContent = 'Load more →'; btn.disabled = false; });
    });
})();
</script>
<script src="assets/chat.js"></script>
</body>
</html>
