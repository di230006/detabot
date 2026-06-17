<?php
declare(strict_types=1);

function page_manage_rewards(array $user): void
{
    /* ── Catalog with per-item redemption counts ── */
    $catalog = db_all(
        "SELECT rc.*,
                (SELECT COUNT(*) FROM tbl_reward r
                 WHERE r.transactionType = 'redeemed'
                   AND r.rewardDescription = 'Redeemed: ' || rc.rewardName
                ) AS redemptionCount
         FROM tbl_reward_catalog rc
         ORDER BY rc.pointsRequired ASC",
        []
    );

    /* ── Stat aggregates ── */
    $activeRewards  = count(array_filter($catalog, fn($c) => (int)($c['isActive'] ?? 0) === 1));
    $totalRedRow    = db_one("SELECT COUNT(*) AS n FROM tbl_reward WHERE transactionType = 'redeemed'", []);
    $totalRedCount  = (int) ($totalRedRow['n'] ?? 0);
    $ptsRedRow      = db_one("SELECT COALESCE(SUM(pointsRedeemed),0) AS s FROM tbl_reward WHERE transactionType = 'redeemed'", []);
    $totalPtsRed    = (int) ($ptsRedRow['s'] ?? 0);

    /* ── Recent redemptions ── */
    $redemptions = db_all(
        "SELECT r.rewardID, r.pointsRedeemed, r.transactionDate, r.rewardDescription,
                u.username, u.userAvatar
         FROM tbl_reward r
         JOIN tbl_user u ON u.userID = r.userID
         WHERE r.transactionType = 'redeemed'
         ORDER BY r.transactionDate DESC
         LIMIT 10",
        []
    );

    /* ── Reward icon map (by points tier) ── */
    $iconFn = function(int $pts): string {
        if ($pts <= 80)  return '🎁';
        if ($pts <= 150) return '🦷';
        if ($pts <= 250) return '💎';
        return '👑';
    };
    $iconBg = function(int $pts): string {
        if ($pts <= 80)  return '#f3f0ff';
        if ($pts <= 150) return '#e8f4fd';
        if ($pts <= 250) return '#fef3c7';
        return '#fcebeb';
    };
    ?>
<style>
/* ── Manage Rewards (mr-) ──────────────────────────────────────── */
.mr-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px}
@media(max-width:700px){.mr-stats{grid-template-columns:1fr 1fr}}
@media(max-width:400px){.mr-stats{grid-template-columns:1fr}}

.mr-stat{background:#fff;border:1px solid #ede8f8;border-radius:12px;padding:18px 16px;box-shadow:0 2px 8px rgba(59,7,100,.05);display:flex;align-items:center;gap:13px}
.mr-stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:21px;flex-shrink:0}
.mr-stat-icon.purple{background:#f3f0ff}
.mr-stat-icon.amber{background:#fef3c7}
.mr-stat-icon.blue{background:#e8f4fd}
.mr-stat-num{font-family:'Sora',sans-serif;font-size:28px;font-weight:800;color:#1a0e2e;line-height:1;margin-bottom:3px}
.mr-stat-num.purple{color:#5b21b6}
.mr-stat-num.amber{color:#c77712}
.mr-stat-num.blue{color:#1686c2}
.mr-stat-lbl{font-size:12px;color:#72647a;font-weight:500}

/* Section heading row */
.mr-section-hd{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px;flex-wrap:wrap}
.mr-section-title{font-family:'Sora',sans-serif;font-size:15px;font-weight:700;color:#1a0e2e}
.mr-btn-add{background:linear-gradient(135deg,#3b0764,#5b21b6);color:#fff;border:none;border-radius:9px;padding:8px 18px;font-size:13px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;display:inline-flex;align-items:center;gap:6px;transition:opacity .15s}
.mr-btn-add:hover{opacity:.88}

/* Add/Edit form card */
.mr-form-card{background:#fff;border:1px solid #ede8f8;border-radius:12px;padding:20px 22px;box-shadow:0 2px 8px rgba(59,7,100,.05);margin-bottom:20px}
.mr-form-title{font-family:'Sora',sans-serif;font-size:14px;font-weight:700;color:#1a0e2e;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.mr-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
@media(max-width:600px){.mr-form-grid{grid-template-columns:1fr}}
.mr-form-full{grid-column:1/-1}
.mr-field{display:flex;flex-direction:column;gap:5px}
.mr-label{font-size:11px;font-weight:700;color:#9b8ad4;text-transform:uppercase;letter-spacing:.05em}
.mr-inp{padding:9px 12px;border:1.5px solid #e5ddf5;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13.5px;color:#1a0e2e;background:#fff;outline:none;transition:border-color .18s;box-sizing:border-box}
.mr-inp:focus{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.07)}
.mr-inp::placeholder{color:#b8aed4}
textarea.mr-inp{resize:vertical;min-height:80px;line-height:1.55}
.mr-form-btns{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.mr-btn-save{background:linear-gradient(135deg,#3b0764,#5b21b6);color:#fff;border:none;border-radius:8px;padding:9px 22px;font-size:13px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;transition:opacity .15s}
.mr-btn-save:hover{opacity:.88}
.mr-btn-save:disabled{opacity:.55;cursor:not-allowed}
.mr-btn-cancel{background:#f3f4f6;border:1px solid #e5e7eb;border-radius:8px;padding:9px 16px;font-size:13px;font-weight:600;cursor:pointer;color:#72647a;font-family:'DM Sans',sans-serif;transition:background .15s}
.mr-btn-cancel:hover{background:#e5e7eb}
.mr-form-msg{font-size:12.5px;font-weight:600;flex:1;min-width:0}
.mr-form-msg.ok{color:#16845c}
.mr-form-msg.err{color:#b42318}

/* Catalog grid */
.mr-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:22px}
@media(max-width:900px){.mr-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:560px){.mr-grid{grid-template-columns:1fr}}

.mr-card{background:#fff;border:1px solid #ede8f8;border-radius:14px;padding:18px;box-shadow:0 2px 8px rgba(59,7,100,.05);transition:box-shadow .15s;display:flex;flex-direction:column;gap:0}
.mr-card:hover{box-shadow:0 6px 18px rgba(59,7,100,.09)}
.mr-card.inactive{opacity:.62;border-style:dashed}

.mr-card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:12px}
.mr-card-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.mr-card-badges{display:flex;flex-direction:column;align-items:flex-end;gap:5px}
.mr-badge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:8px;font-size:11px;font-weight:700;white-space:nowrap}
.mr-badge.active{background:#eaf3de;color:#16845c}
.mr-badge.inactive{background:#f3f4f6;color:#72647a}
.mr-pts-badge{font-family:'Sora',sans-serif;font-size:13px;font-weight:800;color:#5b21b6;background:#f3f0ff;padding:3px 10px;border-radius:8px}

.mr-card-name{font-family:'Sora',sans-serif;font-size:14.5px;font-weight:700;color:#1a0e2e;margin-bottom:6px;line-height:1.3}
.mr-card-desc{font-size:12.5px;color:#72647a;line-height:1.6;margin-bottom:12px;flex:1}
.mr-card-meta{font-size:12px;color:#9b8ad4;margin-bottom:14px}

.mr-card-actions{display:flex;gap:7px;flex-wrap:wrap}
.mr-act-btn{display:inline-flex;align-items:center;gap:4px;padding:6px 13px;border-radius:7px;font-size:12px;font-weight:700;border:none;cursor:pointer;transition:all .15s;font-family:'DM Sans',sans-serif;white-space:nowrap}
.mr-act-edit{background:#f3f0ff;color:#5b21b6}.mr-act-edit:hover{background:#e3dcfc}
.mr-act-toggle.on{background:#fef3c7;color:#c77712}.mr-act-toggle.on:hover{background:#fde68a}
.mr-act-toggle.off{background:#eaf3de;color:#16845c}.mr-act-toggle.off:hover{background:#d1fae5}
.mr-act-del{background:#fcebeb;color:#b42318;margin-left:auto}.mr-act-del:hover{background:#fecaca}
.mr-act-btn:disabled{opacity:.5;cursor:not-allowed}

/* Empty catalog state */
.mr-catalog-empty{text-align:center;padding:48px 20px;color:#72647a;font-size:13.5px;grid-column:1/-1}
.mr-catalog-empty-icon{font-size:40px;margin-bottom:12px}
.mr-catalog-empty h3{font-family:'Sora',sans-serif;font-size:15px;font-weight:700;color:#1a0e2e;margin:0 0 6px}

/* Recent redemptions table */
.mr-red-card{background:#fff;border:1px solid #ede8f8;border-radius:12px;padding:18px 20px;box-shadow:0 2px 8px rgba(59,7,100,.05)}
.mr-tbl-wrap{overflow-x:auto}
.mr-tbl{width:100%;border-collapse:collapse;font-size:13px}
.mr-tbl th{text-align:left;padding:9px 12px;font-size:11px;font-weight:700;color:#72647a;text-transform:uppercase;letter-spacing:.05em;border-bottom:1.5px solid #ede8f8;white-space:nowrap;background:#fdfcff}
.mr-tbl td{padding:11px 12px;border-bottom:1px solid #f0ebf8;vertical-align:middle}
.mr-tbl tr:last-child td{border-bottom:none}
.mr-tbl tr:hover td{background:#faf8ff}
.mr-av{width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#3b0764,#7c3aed);color:#fff;display:flex;align-items:center;justify-content:center;font-family:'Sora',sans-serif;font-size:11px;font-weight:700;flex-shrink:0;overflow:hidden}
.mr-av img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.mr-user-cell{display:flex;align-items:center;gap:9px}
.mr-reward-pill{display:inline-flex;align-items:center;padding:3px 10px;border-radius:8px;background:#f3f0ff;color:#5b21b6;font-size:12px;font-weight:600;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mr-pts-used{font-family:'Sora',sans-serif;font-size:13px;font-weight:700;color:#c77712}
.mr-red-empty{text-align:center;padding:32px;color:#72647a;font-size:13px}
</style>

<script>
window.userID   = <?= (int) ($user['userID'] ?? 0) ?>;
window.userRole = <?= json_encode($user['userRole'] ?? '') ?>;
window.DETABOT_CSRF = <?= json_encode(csrf_token()) ?>;
</script>

<!-- ── Stat Cards ── -->
<div class="mr-stats">
    <div class="mr-stat">
        <div class="mr-stat-icon purple">🏆</div>
        <div>
            <div class="mr-stat-num purple" id="mrActiveCount"><?= $activeRewards ?></div>
            <div class="mr-stat-lbl">Active Rewards</div>
        </div>
    </div>
    <div class="mr-stat">
        <div class="mr-stat-icon amber">🎟️</div>
        <div>
            <div class="mr-stat-num amber"><?= number_format($totalRedCount) ?></div>
            <div class="mr-stat-lbl">Total Redemptions</div>
        </div>
    </div>
    <div class="mr-stat">
        <div class="mr-stat-icon blue">💎</div>
        <div>
            <div class="mr-stat-num blue"><?= number_format($totalPtsRed) ?></div>
            <div class="mr-stat-lbl">Points Redeemed</div>
        </div>
    </div>
</div>

<!-- ── Catalog Section Heading ── -->
<div class="mr-section-hd">
    <span class="mr-section-title">Reward Catalog</span>
    <button class="mr-btn-add" onclick="mrToggleForm(null)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add New Reward
    </button>
</div>

<!-- ── Add / Edit Form ── -->
<div class="mr-form-card" id="mrFormCard" style="display:none">
    <div class="mr-form-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px"><rect x="3" y="8" width="18" height="13" rx="2"/><path d="M12 8v13M3 12h18"/><path d="M7.5 8A2.5 2.5 0 1 1 12 6.5V8"/><path d="M16.5 8A2.5 2.5 0 1 0 12 6.5V8"/></svg>
        <span id="mrFormHeading">Add New Reward</span>
    </div>
    <input type="hidden" id="mrEditID" value="0">
    <div class="mr-form-grid">
        <div class="mr-field">
            <label class="mr-label" for="mrRewardName">Reward Name</label>
            <input class="mr-inp" id="mrRewardName" type="text" maxlength="100" placeholder="e.g. Free Dental Kit" autocomplete="off">
        </div>
        <div class="mr-field">
            <label class="mr-label" for="mrPoints">Points Required</label>
            <input class="mr-inp" id="mrPoints" type="number" min="1" max="99999" placeholder="e.g. 120">
        </div>
        <div class="mr-field mr-form-full">
            <label class="mr-label" for="mrDesc">Description</label>
            <textarea class="mr-inp" id="mrDesc" rows="3" placeholder="Describe what the patient receives when redeeming this reward…"></textarea>
        </div>
    </div>
    <div class="mr-form-btns">
        <span class="mr-form-msg" id="mrFormMsg"></span>
        <button class="mr-btn-cancel" type="button" onclick="mrHideForm()">Cancel</button>
        <button class="mr-btn-save" type="button" id="mrSaveBtn" onclick="mrSave()">Save Reward</button>
    </div>
</div>

<!-- ── Catalog Cards Grid ── -->
<div class="mr-grid" id="mrGrid">
<?php if (empty($catalog)): ?>
    <div class="mr-catalog-empty">
        <div class="mr-catalog-empty-icon">🏆</div>
        <h3>No rewards yet</h3>
        <p>Click "Add New Reward" to create your first catalog item.</p>
    </div>
<?php else: ?>
    <?php foreach ($catalog as $item):
        $cid     = (int)    $item['rewardCatalogID'];
        $name    = (string) $item['rewardName'];
        $pts     = (int)    $item['pointsRequired'];
        $desc    = (string) $item['description'];
        $active  = (int)    ($item['isActive'] ?? 1) === 1;
        $rcount  = (int)    ($item['redemptionCount'] ?? 0);
        $icon    = $iconFn($pts);
        $bg      = $iconBg($pts);
    ?>
    <div class="mr-card <?= $active ? '' : 'inactive' ?>" id="mrCard-<?= $cid ?>">
        <div class="mr-card-top">
            <div class="mr-card-icon" style="background:<?= e($bg) ?>"><?= $icon ?></div>
            <div class="mr-card-badges">
                <span class="mr-badge <?= $active ? 'active' : 'inactive' ?>" id="mrBadge-<?= $cid ?>">
                    <?= $active ? 'Active' : 'Inactive' ?>
                </span>
                <span class="mr-pts-badge"><?= $pts ?> pts</span>
            </div>
        </div>
        <div class="mr-card-name"><?= e($name) ?></div>
        <div class="mr-card-desc"><?= e($desc) ?></div>
        <div class="mr-card-meta"><?= $rcount ?> redemption<?= $rcount !== 1 ? 's' : '' ?></div>
        <div class="mr-card-actions">
            <button class="mr-act-btn mr-act-edit"
                    onclick="mrToggleForm(<?= $cid ?>, <?= e(json_encode($name)) ?>, <?= $pts ?>, <?= e(json_encode($desc)) ?>)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:12px;height:12px"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit
            </button>
            <button class="mr-act-btn mr-act-toggle <?= $active ? 'on' : 'off' ?>"
                    id="mrTogBtn-<?= $cid ?>"
                    onclick="mrToggle(<?= $cid ?>, this)">
                <?= $active ? 'Deactivate' : 'Activate' ?>
            </button>
            <button class="mr-act-btn mr-act-del"
                    onclick="mrDelete(<?= $cid ?>, <?= e(json_encode($name)) ?>, <?= $rcount ?>)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:12px;height:12px"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                Delete
            </button>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>

<!-- ── Recent Redemptions ── -->
<div class="mr-section-hd" style="margin-top:4px">
    <span class="mr-section-title">Recent Redemptions</span>
</div>
<div class="mr-red-card">
<?php if (empty($redemptions)): ?>
    <div class="mr-red-empty">No redemptions recorded yet.</div>
<?php else: ?>
    <div class="mr-tbl-wrap">
        <table class="mr-tbl">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Reward</th>
                    <th>Points Used</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($redemptions as $red):
                    $name    = (string) ($red['username'] ?? '');
                    $initials = strtoupper(substr($name ?: 'P', 0, 2));
                    $avatar   = (string) ($red['userAvatar'] ?? '');
                    $rewardLabel = (string) ($red['rewardDescription'] ?? '');
                    /* strip "Redeemed: " prefix */
                    if (str_starts_with($rewardLabel, 'Redeemed: ')) {
                        $rewardLabel = substr($rewardLabel, 10);
                    }
                    $date = $red['transactionDate']
                        ? date('d M Y', strtotime((string) $red['transactionDate'])) : '—';
                ?>
                <tr>
                    <td>
                        <div class="mr-user-cell">
                            <div class="mr-av">
                                <?php if ($avatar && file_exists(ROOT_PATH . '/public/' . $avatar)): ?>
                                    <img src="<?= e($avatar) ?>" alt="">
                                <?php else: ?>
                                    <?= e($initials) ?>
                                <?php endif; ?>
                            </div>
                            <span style="font-weight:600;color:#1a0e2e"><?= e($name) ?></span>
                        </div>
                    </td>
                    <td><span class="mr-reward-pill"><?= e($rewardLabel) ?></span></td>
                    <td><span class="mr-pts-used"><?= (int) ($red['pointsRedeemed'] ?? 0) ?> pts</span></td>
                    <td style="color:#72647a;font-size:12.5px"><?= e($date) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>

<script>
(function() {
    /* ── Form toggle ── */
    window.mrToggleForm = function(cid, name, pts, desc) {
        var card = document.getElementById('mrFormCard');
        var heading = document.getElementById('mrFormHeading');
        var editID = document.getElementById('mrEditID');
        var nameEl = document.getElementById('mrRewardName');
        var ptsEl  = document.getElementById('mrPoints');
        var descEl = document.getElementById('mrDesc');
        var msg    = document.getElementById('mrFormMsg');

        msg.textContent = '';
        msg.className = 'mr-form-msg';

        if (cid === null) {
            /* Add mode */
            editID.value = 0;
            nameEl.value = '';
            ptsEl.value  = '';
            descEl.value = '';
            heading.textContent = 'Add New Reward';
        } else {
            /* Edit mode */
            editID.value = cid;
            nameEl.value = name || '';
            ptsEl.value  = pts || '';
            descEl.value = desc || '';
            heading.textContent = 'Edit Reward';
        }

        card.style.display = 'block';
        nameEl.focus();
        card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    window.mrHideForm = function() {
        document.getElementById('mrFormCard').style.display = 'none';
    };

    /* ── Save (insert or update) ── */
    window.mrSave = function() {
        var cid   = parseInt(document.getElementById('mrEditID').value) || 0;
        var name  = document.getElementById('mrRewardName').value.trim();
        var pts   = parseInt(document.getElementById('mrPoints').value) || 0;
        var desc  = document.getElementById('mrDesc').value.trim();
        var msg   = document.getElementById('mrFormMsg');
        var btn   = document.getElementById('mrSaveBtn');

        if (!name) { mrSetMsg(msg, 'Reward name is required.', false); return; }
        if (pts <= 0) { mrSetMsg(msg, 'Points must be greater than 0.', false); return; }
        if (!desc) { mrSetMsg(msg, 'Description is required.', false); return; }

        btn.disabled = true;
        msg.textContent = '';

        var fd = new FormData();
        fd.append('_csrf_token', window.DETABOT_CSRF);
        fd.append('rewardName', name);
        fd.append('pointsRequired', pts);
        fd.append('description', desc);
        if (cid > 0) fd.append('rewardCatalogID', cid);

        fetch('save_reward.php', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (d.success) {
                    mrSetMsg(msg, d.message || 'Saved!', true);
                    /* Refresh page to rebuild grid */
                    setTimeout(function(){ location.reload(); }, 700);
                } else {
                    mrSetMsg(msg, d.message || 'Save failed.', false);
                    btn.disabled = false;
                }
            })
            .catch(function() {
                mrSetMsg(msg, 'Network error.', false);
                btn.disabled = false;
            });
    };

    function mrSetMsg(el, text, ok) {
        el.textContent = text;
        el.className = 'mr-form-msg ' + (ok ? 'ok' : 'err');
    }

    /* ── Toggle active/inactive ── */
    window.mrToggle = function(cid, btn) {
        btn.disabled = true;

        var fd = new FormData();
        fd.append('_csrf_token', window.DETABOT_CSRF);
        fd.append('rewardCatalogID', cid);

        fetch('toggle_reward.php', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (d.success) {
                    var isActive = d.isActive === 1;
                    var card   = document.getElementById('mrCard-' + cid);
                    var badge  = document.getElementById('mrBadge-' + cid);
                    var togBtn = document.getElementById('mrTogBtn-' + cid);

                    /* Card opacity */
                    if (isActive) {
                        card.classList.remove('inactive');
                    } else {
                        card.classList.add('inactive');
                    }

                    /* Status badge */
                    badge.textContent = isActive ? 'Active' : 'Inactive';
                    badge.className = 'mr-badge ' + (isActive ? 'active' : 'inactive');

                    /* Toggle button */
                    togBtn.textContent = isActive ? 'Deactivate' : 'Activate';
                    togBtn.className = 'mr-act-btn mr-act-toggle ' + (isActive ? 'on' : 'off');

                    /* Update stat card */
                    var counter = document.getElementById('mrActiveCount');
                    if (counter) {
                        counter.textContent = parseInt(counter.textContent) + (isActive ? 1 : -1);
                    }
                } else {
                    alert(d.message || 'Toggle failed.');
                }
                btn.disabled = false;
            })
            .catch(function() {
                alert('Network error.');
                btn.disabled = false;
            });
    };

    /* ── Delete ── */
    window.mrDelete = function(cid, name, rcount) {
        var msg = rcount > 0
            ? 'Cannot delete "' + name + '" — it has ' + rcount + ' redemption' + (rcount !== 1 ? 's' : '') + '. Deactivate it instead.'
            : 'Delete reward "' + name + '"? This cannot be undone.';

        if (rcount > 0) {
            alert(msg);
            return;
        }

        if (!confirm(msg)) return;

        var fd = new FormData();
        fd.append('_csrf_token', window.DETABOT_CSRF);
        fd.append('rewardCatalogID', cid);

        fetch('delete_reward.php', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (d.success) {
                    var card = document.getElementById('mrCard-' + cid);
                    if (card) {
                        card.style.transition = 'opacity .3s';
                        card.style.opacity = '0';
                        setTimeout(function(){ card.remove(); }, 320);
                    }
                } else {
                    alert(d.message || 'Delete failed.');
                }
            })
            .catch(function() { alert('Network error.'); });
    };
})();
</script>
<?php
}
