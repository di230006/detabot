<?php
declare(strict_types=1);

function page_reports(array $user): void
{
    /* ── Service → price map (no price column in tbl_appointment) ── */
    $servicePrices = [
        'Consultation'    => 30,
        'Scaling'         => 70,
        'Filling'         => 60,
        'Extraction'      => 80,
        'Root Canal'      => 350,
        'Whitening'       => 400,
        'Dental Check-up' => 50,
        'Braces'          => 500,
        'X-Ray'           => 40,
    ];

    /* ── Default period ── */
    $period = preg_replace('/[^a-z]/', '', strtolower((string) ($_GET['period'] ?? 'month')));
    if (!in_array($period, ['week', 'month', 'year', 'custom'], true)) {
        $period = 'month';
    }

    $customFrom = (string) ($_GET['from'] ?? '');
    $customTo   = (string) ($_GET['to']   ?? '');

    [$dateFrom, $dateTo] = rp_date_range($period, $customFrom, $customTo);
    [$prevFrom, $prevTo] = rp_prev_range($period, $dateFrom, $dateTo);

    /* ── Current period stats ── */
    $totalAppts  = (int) (db_one(
        "SELECT COUNT(*) AS n FROM tbl_appointment WHERE appointmentDate BETWEEN ? AND ?",
        [$dateFrom, $dateTo]
    )['n'] ?? 0);

    $completedRows = db_all(
        "SELECT serviceType FROM tbl_appointment WHERE status='completed' AND appointmentDate BETWEEN ? AND ?",
        [$dateFrom, $dateTo]
    );
    $revenue = array_sum(array_map(fn($r) => $servicePrices[(string)($r['serviceType'] ?? '')] ?? 0, $completedRows));

    $newPatients = (int) (db_one(
        "SELECT COUNT(*) AS n FROM tbl_user WHERE userRole='patient' AND DATE(createdDate) BETWEEN ? AND ?",
        [$dateFrom, $dateTo]
    )['n'] ?? 0);

    $avgRatingRow = db_one(
        "SELECT ROUND(AVG(f.rating),1) AS avg FROM tbl_feedback f
         JOIN tbl_appointment a ON a.appointmentID = f.appointmentID
         WHERE DATE(f.feedbackDate) BETWEEN ? AND ?",
        [$dateFrom, $dateTo]
    );
    $avgRating = $avgRatingRow ? (float) ($avgRatingRow['avg'] ?? 0) : 0.0;

    /* ── Previous period stats (for trends) ── */
    $prevAppts = (int) (db_one(
        "SELECT COUNT(*) AS n FROM tbl_appointment WHERE appointmentDate BETWEEN ? AND ?",
        [$prevFrom, $prevTo]
    )['n'] ?? 0);

    $prevCompleted = db_all(
        "SELECT serviceType FROM tbl_appointment WHERE status='completed' AND appointmentDate BETWEEN ? AND ?",
        [$prevFrom, $prevTo]
    );
    $prevRevenue = array_sum(array_map(fn($r) => $servicePrices[(string)($r['serviceType'] ?? '')] ?? 0, $prevCompleted));

    $prevPatients = (int) (db_one(
        "SELECT COUNT(*) AS n FROM tbl_user WHERE userRole='patient' AND DATE(createdDate) BETWEEN ? AND ?",
        [$prevFrom, $prevTo]
    )['n'] ?? 0);

    /* ── Appointment trend (group by date) ── */
    $trendRows = db_all(
        "SELECT appointmentDate AS d, COUNT(*) AS n
         FROM tbl_appointment
         WHERE appointmentDate BETWEEN ? AND ?
         GROUP BY appointmentDate
         ORDER BY appointmentDate ASC",
        [$dateFrom, $dateTo]
    );

    /* ── Status breakdown ── */
    $statusRows = db_all(
        "SELECT status, COUNT(*) AS n FROM tbl_appointment
         WHERE appointmentDate BETWEEN ? AND ?
         GROUP BY status",
        [$dateFrom, $dateTo]
    );
    $statusMap = [];
    foreach ($statusRows as $sr) {
        $statusMap[(string)$sr['status']] = (int) $sr['n'];
    }

    /* ── Popular treatments ── */
    $treatments = db_all(
        "SELECT serviceType, COUNT(*) AS bookings,
                SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed
         FROM tbl_appointment
         WHERE appointmentDate BETWEEN ? AND ?
         GROUP BY serviceType
         ORDER BY bookings DESC",
        [$dateFrom, $dateTo]
    );
    $maxBookings = $treatments ? max(array_column($treatments, 'bookings')) : 1;

    /* ── Dentist performance: staff who recorded treatments ── */
    $dentists = db_all(
        "SELECT u.username,
                COUNT(DISTINCT a.appointmentID) AS apptCount,
                ROUND(AVG(f.rating), 1) AS avgRating
         FROM tbl_user u
         JOIN tbl_appointment a ON a.clinicID > 0
         LEFT JOIN tbl_feedback f ON f.appointmentID = a.appointmentID
         WHERE u.userRole IN ('staff','admin')
           AND a.appointmentDate BETWEEN ? AND ?
         GROUP BY u.userID, u.username
         ORDER BY apptCount DESC
         LIMIT 10",
        [$dateFrom, $dateTo]
    );

    /* Fallback: just list staff with total appointments in period via a simpler query */
    if (empty($dentists)) {
        $dentists = db_all(
            "SELECT u.username,
                    (SELECT COUNT(*) FROM tbl_appointment WHERE appointmentDate BETWEEN ? AND ?) AS apptCount,
                    NULL AS avgRating
             FROM tbl_user u
             WHERE u.userRole IN ('staff','admin')
             ORDER BY u.username ASC
             LIMIT 10",
            [$dateFrom, $dateTo]
        );
    }

    /* ── Chart data → JSON for JS ── */
    $trendLabels = json_encode(array_column($trendRows, 'd'));
    $trendCounts = json_encode(array_map(fn($r) => (int) $r['n'], $trendRows));

    $statusLabels = json_encode(['Completed', 'Pending', 'Confirmed', 'Cancelled']);
    $statusData   = json_encode([
        $statusMap['completed']  ?? 0,
        $statusMap['pending']    ?? 0,
        $statusMap['confirmed']  ?? 0,
        $statusMap['cancelled']  ?? 0,
    ]);

    /* ── Trend % helper ── */
    $trendPct = fn(int $cur, int $prev): string => $prev === 0
        ? ($cur > 0 ? '+100%' : '0%')
        : sprintf('%+.0f%%', (($cur - $prev) / $prev) * 100);

    $apptTrend = $trendPct($totalAppts, $prevAppts);
    $revTrend  = $trendPct($revenue, $prevRevenue);
    $ptTrend   = $trendPct($newPatients, $prevPatients);

    $apptUp = str_starts_with($apptTrend, '+');
    $revUp  = str_starts_with($revTrend,  '+');
    $ptUp   = str_starts_with($ptTrend,   '+');

    /* ── Revenue for treatments (completed × price) ── */
    $treatmentRevenue = [];
    foreach ($treatments as $t) {
        $price = $servicePrices[(string)($t['serviceType'] ?? '')] ?? 0;
        $treatmentRevenue[(string)($t['serviceType'] ?? '')] = $price * (int) $t['completed'];
    }
    ?>
<style>
/* ── Reports (rp-) ────────────────────────────────────────────── */
.rp-toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:20px}
.rp-period-form{display:flex;align-items:center;gap:8px;flex-wrap:wrap;flex:1}
.rp-period-select{font-family:'DM Sans',sans-serif;font-size:13px;padding:7px 12px;border:1.5px solid #e5ddf5;border-radius:8px;color:#1a0e2e;background:#fff;outline:none;cursor:pointer;transition:border-color .18s}
.rp-period-select:focus{border-color:#7c3aed}
.rp-custom-dates{display:flex;align-items:center;gap:6px}
.rp-date-inp{font-family:'DM Sans',sans-serif;font-size:13px;padding:7px 10px;border:1.5px solid #e5ddf5;border-radius:8px;color:#1a0e2e;background:#fff;outline:none;transition:border-color .18s}
.rp-date-inp:focus{border-color:#7c3aed}
.rp-btn-apply{background:linear-gradient(135deg,#3b0764,#5b21b6);color:#fff;border:none;border-radius:8px;padding:7px 16px;font-size:13px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;transition:opacity .15s}
.rp-btn-apply:hover{opacity:.88}
.rp-btn-export{background:#fff;border:1.5px solid #7c3aed;color:#5b21b6;border-radius:8px;padding:7px 16px;font-size:13px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;display:inline-flex;align-items:center;gap:6px;transition:background .15s;white-space:nowrap}
.rp-btn-export:hover{background:#f3f0ff}
.rp-custom-wrap{display:none}
.rp-custom-wrap.show{display:flex}

/* Stat cards */
.rp-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
@media(max-width:920px){.rp-stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:480px){.rp-stats{grid-template-columns:1fr 1fr}}

.rp-stat{background:#fff;border:1px solid #ede8f8;border-radius:12px;padding:18px 16px 14px;box-shadow:0 2px 8px rgba(59,7,100,.05);position:relative;overflow:hidden}
.rp-stat-bg{position:absolute;right:-10px;bottom:-10px;width:64px;height:64px;border-radius:50%;opacity:.08}
.rp-stat-bg.purple{background:#7c3aed}
.rp-stat-bg.green{background:#16845c}
.rp-stat-bg.blue{background:#1686c2}
.rp-stat-bg.amber{background:#c77712}
.rp-stat-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:12px;flex-shrink:0}
.rp-stat-icon.purple{background:#f0ebfc}
.rp-stat-icon.green{background:#eaf3de}
.rp-stat-icon.blue{background:#e8f4fd}
.rp-stat-icon.amber{background:#fef3c7}
.rp-stat-icon svg{width:18px;height:18px}
.rp-stat-icon.purple svg{stroke:#7c3aed}
.rp-stat-icon.green svg{stroke:#16845c}
.rp-stat-icon.blue svg{stroke:#1686c2}
.rp-stat-icon.amber svg{stroke:#c77712}
.rp-stat-num{font-family:'Sora',sans-serif;font-size:28px;font-weight:800;color:#1a0e2e;line-height:1;margin-bottom:4px}
.rp-stat-num.purple{color:#5b21b6}
.rp-stat-num.green{color:#16845c}
.rp-stat-num.blue{color:#1686c2}
.rp-stat-num.amber{color:#c77712}
.rp-stat-lbl{font-size:12px;color:#72647a;margin-bottom:8px}
.rp-trend{display:inline-flex;align-items:center;gap:3px;font-size:11.5px;font-weight:700;padding:2px 7px;border-radius:6px}
.rp-trend.up{background:#eaf3de;color:#16845c}
.rp-trend.down{background:#fcebeb;color:#b42318}
.rp-trend.flat{background:#f3f4f6;color:#72647a}

/* Charts row */
.rp-charts{display:grid;grid-template-columns:1.6fr 1fr;gap:14px;margin-bottom:20px}
@media(max-width:860px){.rp-charts{grid-template-columns:1fr}}
.rp-chart-card{background:#fff;border:1px solid #ede8f8;border-radius:12px;padding:18px 20px;box-shadow:0 2px 8px rgba(59,7,100,.05)}
.rp-chart-title{font-family:'Sora',sans-serif;font-size:14px;font-weight:700;color:#1a0e2e;margin-bottom:16px}
.rp-chart-wrap{position:relative;height:220px}
.rp-donut-legend{margin-top:14px;display:flex;flex-direction:column;gap:7px}
.rp-legend-row{display:flex;align-items:center;justify-content:space-between;gap:8px}
.rp-legend-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.rp-legend-lbl{font-size:12.5px;color:#72647a;flex:1}
.rp-legend-val{font-size:12.5px;font-weight:700;color:#1a0e2e}

/* Tables row */
.rp-tables{display:grid;grid-template-columns:1.3fr 1fr;gap:14px;margin-bottom:20px}
@media(max-width:860px){.rp-tables{grid-template-columns:1fr}}
.rp-tbl-card{background:#fff;border:1px solid #ede8f8;border-radius:12px;padding:18px 20px;box-shadow:0 2px 8px rgba(59,7,100,.05)}
.rp-tbl-title{font-family:'Sora',sans-serif;font-size:14px;font-weight:700;color:#1a0e2e;margin-bottom:14px}
.rp-tbl{width:100%;border-collapse:collapse;font-size:12.5px}
.rp-tbl th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#72647a;font-weight:700;padding:0 8px 8px;border-bottom:1.5px solid #ede8f8}
.rp-tbl td{padding:9px 8px;border-bottom:1px solid #f5f2ff;color:#1a0e2e;vertical-align:middle}
.rp-tbl tr:last-child td{border-bottom:none}
.rp-tbl-rank{font-family:'Sora',sans-serif;font-size:11px;font-weight:800;color:#72647a;background:#f5f2ff;border-radius:5px;padding:2px 6px;display:inline-block;min-width:20px;text-align:center}
.rp-mini-bar-wrap{width:80px;flex-shrink:0}
.rp-mini-bar{height:6px;background:#f0ebf8;border-radius:100px;overflow:hidden}
.rp-mini-bar-fill{height:100%;background:#7c3aed;border-radius:100px}
.rp-star-chip{display:inline-flex;align-items:center;gap:3px;font-size:11.5px;font-weight:700;padding:2px 8px;border-radius:6px;background:#fef3c7;color:#c77712}
.rp-empty{text-align:center;padding:28px 20px;color:#72647a;font-size:13px}

/* Print stylesheet */
@media print{
    .sb-sidebar,.topbar,.rp-toolbar,.rp-btn-export{display:none!important}
    body,html{background:#fff!important}
    .app-shell{display:block!important}
    .app-main{margin-left:0!important;padding:0!important}
    .rp-stat,.rp-chart-card,.rp-tbl-card{box-shadow:none!important;break-inside:avoid}
    .rp-charts,.rp-tables{display:grid!important}
    canvas{max-width:100%!important}
}
</style>

<!-- ── Toolbar ── -->
<form class="rp-toolbar" id="rpForm" method="get" action="reports.php">
    <div class="rp-period-form">
        <select class="rp-period-select" name="period" id="rpPeriodSel" onchange="rpToggleCustom(this.value)">
            <option value="week"   <?= $period==='week'   ? 'selected' : '' ?>>This Week</option>
            <option value="month"  <?= $period==='month'  ? 'selected' : '' ?>>This Month</option>
            <option value="year"   <?= $period==='year'   ? 'selected' : '' ?>>This Year</option>
            <option value="custom" <?= $period==='custom' ? 'selected' : '' ?>>Custom Range</option>
        </select>
        <div class="rp-custom-wrap rp-custom-dates <?= $period==='custom' ? 'show' : '' ?>" id="rpCustomDates">
            <input class="rp-date-inp" type="date" name="from" value="<?= e($customFrom) ?>" id="rpFrom">
            <span style="font-size:12px;color:#72647a">to</span>
            <input class="rp-date-inp" type="date" name="to"   value="<?= e($customTo)   ?>" id="rpTo">
        </div>
        <button class="rp-btn-apply" type="submit">Apply</button>
    </div>
    <button class="rp-btn-export" type="button" onclick="rpExport()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export PDF
    </button>
</form>

<!-- ── Stat Cards ── -->
<div class="rp-stats">
    <!-- Total Appointments -->
    <div class="rp-stat">
        <div class="rp-stat-bg purple"></div>
        <div class="rp-stat-icon purple">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
        </div>
        <div class="rp-stat-num purple"><?= number_format($totalAppts) ?></div>
        <div class="rp-stat-lbl">Total Appointments</div>
        <span class="rp-trend <?= $apptUp ? 'up' : ($totalAppts === $prevAppts ? 'flat' : 'down') ?>">
            <?= $apptUp ? '▲' : ($totalAppts === $prevAppts ? '—' : '▼') ?> <?= e($apptTrend) ?> vs prev
        </span>
    </div>
    <!-- Revenue -->
    <div class="rp-stat">
        <div class="rp-stat-bg green"></div>
        <div class="rp-stat-icon green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="rp-stat-num green">RM <?= number_format($revenue) ?></div>
        <div class="rp-stat-lbl">Revenue (Completed)</div>
        <span class="rp-trend <?= $revUp ? 'up' : ($revenue === $prevRevenue ? 'flat' : 'down') ?>">
            <?= $revUp ? '▲' : ($revenue === $prevRevenue ? '—' : '▼') ?> <?= e($revTrend) ?> vs prev
        </span>
    </div>
    <!-- New Patients -->
    <div class="rp-stat">
        <div class="rp-stat-bg blue"></div>
        <div class="rp-stat-icon blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
        </div>
        <div class="rp-stat-num blue"><?= number_format($newPatients) ?></div>
        <div class="rp-stat-lbl">New Patients</div>
        <span class="rp-trend <?= $ptUp ? 'up' : ($newPatients === $prevPatients ? 'flat' : 'down') ?>">
            <?= $ptUp ? '▲' : ($newPatients === $prevPatients ? '—' : '▼') ?> <?= e($ptTrend) ?> vs prev
        </span>
    </div>
    <!-- Avg Rating -->
    <div class="rp-stat">
        <div class="rp-stat-bg amber"></div>
        <div class="rp-stat-icon amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        </div>
        <div class="rp-stat-num amber"><?= $avgRating > 0 ? number_format($avgRating, 1) : '—' ?></div>
        <div class="rp-stat-lbl">Avg Rating (Period)</div>
        <span class="rp-trend flat" style="color:#c77712;background:#fef3c7">
            <?php if ($avgRating > 0): ?>
                <?php for ($s = 1; $s <= 5; $s++): ?><?= $s <= round($avgRating) ? '★' : '☆' ?><?php endfor; ?>
            <?php else: ?>
                No ratings
            <?php endif; ?>
        </span>
    </div>
</div>

<!-- ── Charts ── -->
<div class="rp-charts">
    <div class="rp-chart-card">
        <div class="rp-chart-title">Appointments Trend</div>
        <div class="rp-chart-wrap">
            <canvas id="rpBarChart"></canvas>
        </div>
    </div>
    <div class="rp-chart-card">
        <div class="rp-chart-title">Appointment Status</div>
        <div class="rp-chart-wrap" style="height:180px">
            <canvas id="rpDonutChart"></canvas>
        </div>
        <div class="rp-donut-legend">
            <?php
            $donutColors = [
                'Completed'  => '#16845c',
                'Pending'    => '#c77712',
                'Confirmed'  => '#1686c2',
                'Cancelled'  => '#b42318',
            ];
            $donutData = [
                'Completed'  => $statusMap['completed']  ?? 0,
                'Pending'    => $statusMap['pending']    ?? 0,
                'Confirmed'  => $statusMap['confirmed']  ?? 0,
                'Cancelled'  => $statusMap['cancelled']  ?? 0,
            ];
            foreach ($donutData as $lbl => $val): ?>
                <div class="rp-legend-row">
                    <span class="rp-legend-dot" style="background:<?= $donutColors[$lbl] ?>"></span>
                    <span class="rp-legend-lbl"><?= e($lbl) ?></span>
                    <span class="rp-legend-val"><?= $val ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ── Tables ── -->
<div class="rp-tables">
    <!-- Popular Treatments -->
    <div class="rp-tbl-card">
        <div class="rp-tbl-title">Most Popular Treatments</div>
        <?php if (empty($treatments)): ?>
            <div class="rp-empty">No appointment data for this period.</div>
        <?php else: ?>
        <table class="rp-tbl">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Treatment</th>
                    <th>Bookings</th>
                    <th>Revenue (RM)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($treatments as $i => $t):
                    $svc  = (string) ($t['serviceType'] ?? '—');
                    $bk   = (int) $t['bookings'];
                    $rev  = $treatmentRevenue[$svc] ?? 0;
                    $pct  = $maxBookings > 0 ? round($bk / $maxBookings * 100) : 0;
                ?>
                <tr>
                    <td><span class="rp-tbl-rank"><?= $i + 1 ?></span></td>
                    <td><?= e($svc) ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px">
                            <span style="font-weight:700;min-width:24px"><?= $bk ?></span>
                            <div class="rp-mini-bar" style="width:70px">
                                <div class="rp-mini-bar-fill" style="width:<?= $pct ?>%"></div>
                            </div>
                        </div>
                    </td>
                    <td style="font-weight:700;color:#16845c"><?= number_format($rev) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Dentist Performance -->
    <div class="rp-tbl-card">
        <div class="rp-tbl-title">Staff Performance</div>
        <?php if (empty($dentists)): ?>
            <div class="rp-empty">No staff data available.</div>
        <?php else: ?>
        <table class="rp-tbl">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Appts</th>
                    <th>Avg Rating</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dentists as $d):
                    $rating = isset($d['avgRating']) && $d['avgRating'] !== null
                        ? number_format((float) $d['avgRating'], 1)
                        : null;
                ?>
                <tr>
                    <td style="font-weight:600"><?= e((string) ($d['username'] ?? '—')) ?></td>
                    <td><?= (int) ($d['apptCount'] ?? 0) ?></td>
                    <td>
                        <?php if ($rating !== null && (float) $rating > 0): ?>
                            <span class="rp-star-chip">★ <?= e($rating) ?></span>
                        <?php else: ?>
                            <span style="color:#72647a;font-size:12px">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- ── Chart.js ── -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js" crossorigin="anonymous"></script>
<script>
(function(){
    var trendLabels = <?= $trendLabels ?>;
    var trendCounts = <?= $trendCounts ?>;
    var statusData  = <?= $statusData ?>;

    /* Bar chart */
    var barCtx = document.getElementById('rpBarChart');
    if(barCtx){
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: trendLabels.length ? trendLabels : ['No data'],
                datasets:[{
                    label: 'Appointments',
                    data: trendCounts.length ? trendCounts : [0],
                    backgroundColor: 'rgba(124,58,237,0.75)',
                    borderRadius: 5,
                    borderSkipped: false,
                }]
            },
            options:{
                responsive: true,
                maintainAspectRatio: false,
                plugins:{legend:{display:false},tooltip:{callbacks:{title:function(i){return i[0].label}}}},
                scales:{
                    x:{grid:{display:false},ticks:{font:{family:'DM Sans',size:11},color:'#72647a',maxTicksLimit:10}},
                    y:{beginAtZero:true,grid:{color:'#f0ebf8'},ticks:{font:{family:'DM Sans',size:11},color:'#72647a',stepSize:1}}
                }
            }
        });
    }

    /* Donut chart */
    var donutCtx = document.getElementById('rpDonutChart');
    if(donutCtx){
        new Chart(donutCtx, {
            type: 'doughnut',
            data: {
                labels: ['Completed','Pending','Confirmed','Cancelled'],
                datasets:[{
                    data: statusData,
                    backgroundColor: ['#16845c','#c77712','#1686c2','#b42318'],
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 6
                }]
            },
            options:{
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins:{
                    legend:{display:false},
                    tooltip:{callbacks:{label:function(ctx){return ' '+ctx.label+': '+ctx.parsed}}}
                }
            }
        });
    }
})();

function rpToggleCustom(val){
    var el = document.getElementById('rpCustomDates');
    if(el) el.classList.toggle('show', val === 'custom');
}

function rpExport(){
    /* Save report metadata via AJAX then print */
    var fd = new FormData();
    fd.append('_csrf_token', window.DETABOT_CSRF || '');
    fd.append('action', 'save_report');
    fd.append('period', document.getElementById('rpPeriodSel').value);
    fetch('save_report.php', {method:'POST', body:fd}).catch(function(){});
    setTimeout(function(){ window.print(); }, 300);
}
</script>
<?php
}

/* ── Helpers ─────────────────────────────────────────────────── */

function rp_date_range(string $period, string $customFrom, string $customTo): array
{
    $today = date('Y-m-d');

    return match($period) {
        'week'   => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week'))],
        'year'   => [date('Y-01-01'), date('Y-12-31')],
        'custom' => [
            ($customFrom ?: date('Y-m-01')),
            ($customTo   ?: $today),
        ],
        default  => [date('Y-m-01'), date('Y-m-t')], /* month */
    };
}

function rp_prev_range(string $period, string $from, string $to): array
{
    $days = max(1, (int) round((strtotime($to) - strtotime($from)) / 86400) + 1);

    $prevTo   = date('Y-m-d', strtotime($from) - 86400);
    $prevFrom = date('Y-m-d', strtotime($prevTo) - ($days - 1) * 86400);

    return [$prevFrom, $prevTo];
}
