<?php
declare(strict_types=1);

function page_patients(array $user): void
{
    $patients = db_all(
        "SELECT u.userID, u.username, u.userEmail, u.userPhone, u.userAge, u.userGender,
                u.userChronicHealthProblems,
                COUNT(a.appointmentID)                                                      AS totalAppts,
                SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END)                    AS completedAppts,
                MAX(CASE WHEN a.status = 'completed' THEN a.appointmentDate ELSE NULL END)  AS lastVisit
         FROM tbl_user u
         LEFT JOIN tbl_appointment a ON a.userID = u.userID
         WHERE u.userRole = 'patient' AND u.status = 'active'
         GROUP BY u.userID
         ORDER BY u.username ASC",
        []
    );

    $totalPatients    = count($patients);
    $withAppts        = count(array_filter($patients, fn($p) => (int) ($p['totalAppts'] ?? 0) > 0));
    $withCompleted    = count(array_filter($patients, fn($p) => (int) ($p['completedAppts'] ?? 0) > 0));
    ?>
<style>
/* ── Patients Page ───────────────────────────────────────────── */
.pt-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:22px}
@media(max-width:700px){.pt-stats{grid-template-columns:1fr 1fr}}
.pt-stat{background:#fff;border-radius:12px;border:1px solid #ede8f8;border-left:3.5px solid transparent;padding:16px;box-shadow:0 2px 8px rgba(59,7,100,.05)}
.pt-stat.purple{border-left-color:#7c3aed}
.pt-stat.blue{border-left-color:#1686c2}
.pt-stat.green{border-left-color:#16845c}
.pt-stat-num{font-family:'Sora',sans-serif;font-size:26px;font-weight:700;color:#1a0e2e;line-height:1;margin-bottom:3px}
.pt-stat-lbl{font-size:12px;color:#72647a;font-weight:500}

/* Search & filter */
.pt-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.pt-search-wrap{position:relative;flex:1;max-width:340px}
.pt-search-icon{position:absolute;left:11px;top:50%;transform:translateY(-50%);width:14px;height:14px;stroke:#a78bdb;pointer-events:none}
.pt-search-input{width:100%;padding:9px 12px 9px 34px;border:1.5px solid #e5ddf5;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;color:#1a0e2e;background:#fff;outline:none;transition:border-color .18s;box-sizing:border-box}
.pt-search-input:focus{border-color:#7c3aed}
.pt-count{font-size:12.5px;color:#72647a;white-space:nowrap}

/* Table */
.pt-tbl-wrap{overflow-x:auto}
.pt-tbl{width:100%;border-collapse:collapse;font-size:13px}
.pt-tbl th{text-align:left;padding:8px 12px;font-size:11px;font-weight:700;color:#72647a;text-transform:uppercase;letter-spacing:.05em;border-bottom:2px solid #ede8f8;white-space:nowrap}
.pt-tbl td{padding:11px 12px;border-bottom:1px solid #f0ebf8;vertical-align:middle}
.pt-tbl tr:last-child td{border-bottom:none}
.pt-tbl tr:hover td{background:#faf8ff}
.pt-av{width:30px;height:30px;border-radius:50%;background:#eeedfe;display:flex;align-items:center;justify-content:center;font-family:'Sora',sans-serif;font-size:11px;font-weight:700;color:#5b21b6;flex-shrink:0}
.pt-name-cell{display:flex;align-items:center;gap:10px}
.pt-name{font-weight:700;color:#1a0e2e}
.pt-email{font-size:11.5px;color:#72647a;margin-top:1px}
.pt-chip{display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:#f3f0ff;color:#5b21b6}
.pt-zero{color:#c4b8d4}
.pt-action-link{font-size:12px;font-weight:600;text-decoration:none;color:#7c3aed;margin-right:10px;white-space:nowrap}
.pt-action-link:hover{text-decoration:underline}
.pt-action-link.danger{color:#b42318}
.pt-empty{text-align:center;padding:40px;color:#72647a;font-size:13.5px}
</style>

<!-- Stat Cards -->
<div class="pt-stats">
    <div class="pt-stat purple">
        <div class="pt-stat-num"><?= $totalPatients ?></div>
        <div class="pt-stat-lbl">Total Active Patients</div>
    </div>
    <div class="pt-stat blue">
        <div class="pt-stat-num"><?= $withAppts ?></div>
        <div class="pt-stat-lbl">With Appointments</div>
    </div>
    <div class="pt-stat green">
        <div class="pt-stat-num"><?= $withCompleted ?></div>
        <div class="pt-stat-lbl">Completed Visits</div>
    </div>
</div>

<!-- Table -->
<div class="panel">
    <div class="pt-toolbar">
        <div class="pt-search-wrap">
            <svg class="pt-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input id="ptSearch" class="pt-search-input" type="text" placeholder="Search by name, email, or phone…" autocomplete="off" oninput="ptFilter(this.value)">
        </div>
        <span class="pt-count" id="ptCount"><?= $totalPatients ?> patient<?= $totalPatients !== 1 ? 's' : '' ?></span>
    </div>

    <?php if (empty($patients)): ?>
        <div class="pt-empty">No active patients found.</div>
    <?php else: ?>
    <div class="pt-tbl-wrap">
        <table class="pt-tbl" id="ptTable">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Age / Gender</th>
                    <th>Phone</th>
                    <th>Appts</th>
                    <th>Completed</th>
                    <th>Last Visit</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="ptBody">
            <?php foreach ($patients as $p):
                $av       = strtoupper(substr((string) ($p['username'] ?? 'P'), 0, 2));
                $total    = (int) ($p['totalAppts'] ?? 0);
                $done     = (int) ($p['completedAppts'] ?? 0);
                $lastVisit = $p['lastVisit'] ? date('d M Y', strtotime((string) $p['lastVisit'])) : null;
                $searchTxt = strtolower((string) ($p['username'] ?? '') . ' ' . ($p['userEmail'] ?? '') . ' ' . ($p['userPhone'] ?? ''));
            ?>
            <tr data-search="<?= e($searchTxt) ?>">
                <td>
                    <div class="pt-name-cell">
                        <div class="pt-av"><?= e($av) ?></div>
                        <div>
                            <div class="pt-name"><?= e($p['username'] ?? '') ?></div>
                            <div class="pt-email"><?= e($p['userEmail'] ?? '') ?></div>
                        </div>
                    </div>
                </td>
                <td><?= $p['userAge'] ? e((string) $p['userAge']) . (($p['userGender'] ?? '') !== '' ? ' / ' . e(ucfirst((string) $p['userGender'])) : '') : '<span class="pt-zero">—</span>' ?></td>
                <td><?= $p['userPhone'] ? e((string) $p['userPhone']) : '<span class="pt-zero">—</span>' ?></td>
                <td><?= $total > 0 ? $total : '<span class="pt-zero">0</span>' ?></td>
                <td><?= $done > 0 ? $done : '<span class="pt-zero">0</span>' ?></td>
                <td><?= $lastVisit ?? '<span class="pt-zero">—</span>' ?></td>
                <td>
                    <a class="pt-action-link" href="staff_health_record.php?patient=<?= (int) $p['userID'] ?>">🦷 Records</a>
                    <a class="pt-action-link" href="appointments.php?patient=<?= (int) $p['userID'] ?>">📅 Appts</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
function ptFilter(q) {
    var rows  = document.querySelectorAll('#ptBody tr');
    var term  = q.toLowerCase().trim();
    var shown = 0;
    rows.forEach(function (r) {
        var match = term === '' || (r.dataset.search || '').indexOf(term) !== -1;
        r.style.display = match ? '' : 'none';
        if (match) shown++;
    });
    var countEl = document.getElementById('ptCount');
    if (countEl) countEl.textContent = shown + ' patient' + (shown !== 1 ? 's' : '');
}
</script>
    <?php
}
