<?php
declare(strict_types=1);

function page_rewards(array $user): void
{
    $uid       = (int) $user['userID'];
    $isPatient = $user['userRole'] === 'patient';
    $canManage = has_role($user, 'admin');

    $catalog = db_all('SELECT * FROM tbl_reward_catalog WHERE isActive = 1 ORDER BY pointsRequired ASC');
    $transactions = has_role($user, ['admin', 'staff'])
        ? db_all(
            "SELECT r.*, u.username
             FROM tbl_reward r
             JOIN tbl_user u ON u.userID = r.userID
             ORDER BY r.transactionDate DESC LIMIT 80"
        )
        : db_all('SELECT * FROM tbl_reward WHERE userID = ? ORDER BY transactionDate DESC', [$uid]);

    if ($isPatient) {
        $currentBalance = reward_balance($uid);
        $earnedRow      = db_one('SELECT COALESCE(SUM(pointsEarned),0) AS t FROM tbl_reward WHERE userID = ?', [$uid]);
        $totalEarned    = (int) ($earnedRow['t'] ?? 0);
        $redeemedRow    = db_one('SELECT COALESCE(SUM(pointsRedeemed),0) AS t FROM tbl_reward WHERE userID = ?', [$uid]);
        $totalRedeemed  = (int) ($redeemedRow['t'] ?? 0);

        $nextReward = null;
        foreach ($catalog as $item) {
            if ((int) $item['pointsRequired'] > $currentBalance) {
                $nextReward = $item;
                break;
            }
        }
        if ($nextReward === null && !empty($catalog)) {
            $nextReward = $catalog[count($catalog) - 1];
        }

        $ptsNeeded   = $nextReward !== null ? max(0, (int) $nextReward['pointsRequired'] - $currentBalance) : 0;
        $progressPct = ($nextReward !== null && (int) $nextReward['pointsRequired'] > 0)
            ? min(100, (int) round($currentBalance / (int) $nextReward['pointsRequired'] * 100))
            : 100;
        $apptNeeded  = $ptsNeeded > 0 ? (int) ceil($ptsNeeded / 20) : 0;
    }

    $catalogDefs = [
        ['bg' => '#ede9fe', 'color' => '#7c3aed', 'icon' => 'gift'],
        ['bg' => '#d1fae5', 'color' => '#059669', 'icon' => 'box'],
        ['bg' => '#fce7f3', 'color' => '#be185d', 'icon' => 'tag'],
    ];

    $svgPaths = [
        'gift' => '<rect x="3" y="8" width="18" height="13" rx="2"/><path d="M12 8v13M3 12h18"/><path d="M7.5 8A2.5 2.5 0 1 1 12 6.5V8"/><path d="M16.5 8A2.5 2.5 0 1 0 12 6.5V8"/>',
        'box'  => '<path d="m16.5 9.4-9-5.19"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
        'tag'  => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
    ];
    ?>
    <style>
    /* ── Rewards page scoped styles (rw- prefix) ── */
    .rw-hero {
        background: #3b0764;
        border-radius: 14px;
        padding: 30px 32px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 24px;
        margin-bottom: 18px;
        position: relative;
        overflow: hidden;
        color: #fff;
    }
    .rw-hero-blob {
        position: absolute;
        top: -70px;
        right: -70px;
        width: 240px;
        height: 240px;
        background: rgba(236, 72, 153, 0.32);
        border-radius: 50%;
        filter: blur(55px);
        pointer-events: none;
    }
    .rw-hero-left { position: relative; z-index: 1; }
    .rw-hero-label {
        font-size: 11px;
        color: rgba(255,255,255,.6);
        text-transform: uppercase;
        letter-spacing: .08em;
        font-weight: 700;
        display: block;
        margin-bottom: 8px;
    }
    .rw-hero-balance {
        font-family: 'Sora', sans-serif;
        font-size: 52px;
        font-weight: 800;
        line-height: 1;
        margin-bottom: 10px;
    }
    .rw-hero-sub {
        font-size: 13px;
        color: rgba(255,255,255,.65);
        margin: 0;
    }
    .rw-hero-right { position: relative; z-index: 1; flex-shrink: 0; }
    .rw-hero-icon-box {
        width: 76px;
        height: 76px;
        border-radius: 20px;
        background: rgba(236, 72, 153, 0.22);
        border: 1px solid rgba(236, 72, 153, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .rw-hero-icon-box svg {
        width: 40px;
        height: 40px;
        stroke: #f9a8d4;
        stroke-width: 1.5;
    }

    /* Stats row */
    .rw-stats-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
        margin-bottom: 18px;
    }
    .rw-stat-card {
        background: #fff;
        border-radius: 12px;
        padding: 18px 20px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 2px 8px rgba(80,29,99,.06);
    }
    .rw-stat-icon {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 20px;
    }
    .rw-stat-icon.blue  { background: #dbeafe; }
    .rw-stat-icon.amber { background: #fef3c7; }
    .rw-stat-num {
        font-family: 'Sora', sans-serif;
        font-size: 28px;
        font-weight: 800;
        color: #2d1f32;
        line-height: 1;
    }
    .rw-stat-lbl { font-size: 12px; color: #72647a; margin-top: 4px; }

    /* Progress card */
    .rw-progress-card { margin-bottom: 18px; }
    .rw-prog-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 14px;
    }
    .rw-prog-head h3 { font-size: 15px; font-weight: 700; color: #2d1f32; margin: 0; }
    .rw-prog-goal {
        font-size: 12px;
        background: #f3e8ff;
        color: #7c3aed;
        padding: 4px 11px;
        border-radius: 20px;
        font-weight: 600;
        white-space: nowrap;
    }
    .rw-prog-bar-wrap {
        height: 10px;
        background: #f0e8f8;
        border-radius: 99px;
        overflow: hidden;
        margin-bottom: 10px;
    }
    .rw-prog-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #7c3aed, #c084fc);
        border-radius: 99px;
        transition: width .9s cubic-bezier(.4,0,.2,1);
        width: 0%;
    }
    .rw-prog-text { font-size: 13px; color: #72647a; margin: 0; }

    /* How to earn */
    .rw-earn-card {
        background: #e1f5ee;
        border-radius: 14px;
        padding: 20px 24px;
        margin-bottom: 18px;
    }
    .rw-earn-head {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 16px;
    }
    .rw-earn-head h3 { font-size: 15px; font-weight: 700; color: #065f46; margin: 0; }
    .rw-earn-steps {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
    }
    .rw-earn-step {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
        color: #065f46;
        font-weight: 500;
    }
    .rw-step-num {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #059669;
        color: #fff;
        font-size: 13px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    /* Catalog section */
    .rw-catalog-section { margin-bottom: 18px; }
    .rw-section-head {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 14px;
    }
    .rw-section-head h2 { font-size: 17px; font-weight: 700; color: #2d1f32; margin: 0; }
    .rw-section-head svg { width: 20px; height: 20px; flex-shrink: 0; }
    .rw-catalog-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }
    .rw-cat-card {
        background: #fff;
        border-radius: 14px;
        padding: 20px;
        box-shadow: 0 2px 12px rgba(80,29,99,.07);
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .rw-cat-ribbon {
        position: absolute;
        top: 14px;
        right: -22px;
        background: #16a34a;
        color: #fff;
        font-size: 9px;
        font-weight: 800;
        padding: 3px 32px;
        transform: rotate(35deg);
        letter-spacing: .07em;
        text-transform: uppercase;
        box-shadow: 0 1px 4px rgba(0,0,0,.15);
    }
    .rw-cat-icon-box {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .rw-cat-icon-box svg { width: 24px; height: 24px; }
    .rw-cat-name { font-size: 14px; font-weight: 700; color: #2d1f32; line-height: 1.3; }
    .rw-cat-desc { font-size: 12px; color: #72647a; line-height: 1.5; flex: 1; }
    .rw-cat-pts {
        font-family: 'Sora', sans-serif;
        font-size: 26px;
        font-weight: 800;
        color: #7c3aed;
        line-height: 1;
    }
    .rw-cat-pts span { font-size: 13px; font-weight: 600; color: #9ca3af; }
    .rw-cat-btn {
        width: 100%;
        padding: 10px;
        border-radius: 9px;
        border: none;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: opacity .15s, transform .1s;
        font-family: inherit;
    }
    .rw-cat-btn.available { background: #16a34a; color: #fff; }
    .rw-cat-btn.available:hover { opacity: .88; transform: translateY(-1px); }
    .rw-cat-btn.locked { background: #f3f4f6; color: #9ca3af; cursor: not-allowed; }

    /* Transactions */
    .rw-tx-panel { margin-bottom: 30px; }
    .rw-tx-panel .panel-head h2 {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .rw-tx-panel .panel-head svg { width: 18px; height: 18px; }
    .rw-tx-empty {
        text-align: center;
        padding: 44px 20px;
    }
    .rw-tx-empty svg {
        width: 52px;
        height: 52px;
        stroke: #d1c4e9;
        display: block;
        margin: 0 auto 14px;
        stroke-width: 1.5;
    }
    .rw-tx-empty p { font-size: 14px; color: #72647a; margin: 0; }
    .rw-tx-badge {
        display: inline-block;
        font-size: 11px;
        font-weight: 700;
        padding: 3px 9px;
        border-radius: 6px;
        text-transform: capitalize;
    }
    .rw-tx-badge.earned   { background: #d1fae5; color: #065f46; }
    .rw-tx-badge.redeemed { background: #fee2e2; color: #b91c1c; }
    .rw-pts-earned   { color: #16a34a; font-weight: 700; }
    .rw-pts-redeemed { color: #dc2626; font-weight: 700; }

    /* Redemption modal */
    .rw-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.5);
        z-index: 8888;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .rw-modal {
        background: #fff;
        border-radius: 18px;
        padding: 28px;
        max-width: 400px;
        width: 100%;
        box-shadow: 0 24px 64px rgba(0,0,0,.22);
    }
    .rw-modal-icon {
        width: 54px;
        height: 54px;
        border-radius: 16px;
        background: #f3e8ff;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 16px;
    }
    .rw-modal-icon svg { width: 28px; height: 28px; stroke: #7c3aed; }
    .rw-modal h3 {
        font-family: 'Sora', sans-serif;
        font-size: 18px;
        font-weight: 800;
        color: #2d1f32;
        margin: 0 0 8px;
    }
    .rw-modal p { font-size: 14px; color: #72647a; margin: 0 0 6px; line-height: 1.6; }
    .rw-modal p.rw-modal-note { font-size: 12px; color: #9ca3af; margin-bottom: 22px; }
    .rw-modal-actions { display: flex; gap: 10px; }
    .rw-modal-actions button {
        flex: 1;
        padding: 12px;
        border-radius: 10px;
        border: none;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        font-family: inherit;
        transition: opacity .15s;
    }
    #rwModalCancel  { background: #f3f4f6; color: #6b7280; }
    #rwModalConfirm { background: #7c3aed; color: #fff; }
    #rwModalConfirm:hover { opacity: .9; }
    #rwModalConfirm:disabled { opacity: .6; cursor: not-allowed; }

    /* Responsive */
    @media (max-width: 900px) {
        .rw-catalog-grid { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 640px) {
        .rw-hero { flex-direction: column; }
        .rw-hero-balance { font-size: 38px; }
        .rw-stats-row { grid-template-columns: 1fr; }
        .rw-earn-steps { grid-template-columns: 1fr; }
        .rw-catalog-grid { grid-template-columns: 1fr; }
    }
    </style>

    <?php if ($isPatient): ?>

    <!-- ── Section 1: Points Hero Banner ── -->
    <div class="rw-hero">
        <div class="rw-hero-blob" aria-hidden="true"></div>
        <div class="rw-hero-left">
            <span class="rw-hero-label">Your Reward Balance</span>
            <div class="rw-hero-balance" id="rwHeroBalance"><?= $currentBalance ?> pts</div>
            <p class="rw-hero-sub">Earn 20 points after every completed appointment 🦷</p>
        </div>
        <div class="rw-hero-right">
            <div class="rw-hero-icon-box" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="8" width="18" height="13" rx="2"/>
                    <path d="M12 8v13M3 12h18"/>
                    <path d="M7.5 8A2.5 2.5 0 1 1 12 6.5V8"/>
                    <path d="M16.5 8A2.5 2.5 0 1 0 12 6.5V8"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- ── Section 2: Mini Stats ── -->
    <div class="rw-stats-row">
        <div class="rw-stat-card">
            <div class="rw-stat-icon blue" aria-hidden="true">📈</div>
            <div>
                <div class="rw-stat-num"><?= number_format($totalEarned) ?></div>
                <div class="rw-stat-lbl">Total Points Earned</div>
            </div>
        </div>
        <div class="rw-stat-card">
            <div class="rw-stat-icon amber" aria-hidden="true">🎟️</div>
            <div>
                <div class="rw-stat-num"><?= number_format($totalRedeemed) ?></div>
                <div class="rw-stat-lbl">Points Redeemed</div>
            </div>
        </div>
    </div>

    <!-- ── Section 3: Progress to Next Reward ── -->
    <div class="panel rw-progress-card">
        <div class="rw-prog-head">
            <h3>Progress to your next reward</h3>
            <?php if ($nextReward !== null): ?>
                <span class="rw-prog-goal"><?= e($nextReward['rewardName']) ?> · <?= (int) $nextReward['pointsRequired'] ?> pts</span>
            <?php endif; ?>
        </div>
        <div class="rw-prog-bar-wrap" role="progressbar" aria-valuenow="<?= $progressPct ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="rw-prog-bar-fill" id="rwProgFill" data-pct="<?= $progressPct ?>"></div>
        </div>
        <?php if ($ptsNeeded > 0): ?>
            <p class="rw-prog-text">You need <strong><?= $ptsNeeded ?></strong> more point<?= $ptsNeeded !== 1 ? 's' : '' ?> — that's <strong><?= $apptNeeded ?></strong> completed appointment<?= $apptNeeded !== 1 ? 's' : '' ?> away!</p>
        <?php else: ?>
            <p class="rw-prog-text">🎉 You've reached this reward tier! Go ahead and redeem below.</p>
        <?php endif; ?>
    </div>

    <!-- ── Section 4: How to Earn Points ── -->
    <div class="rw-earn-card">
        <div class="rw-earn-head">
            <span style="font-size:22px;line-height:1" aria-hidden="true">💡</span>
            <h3>How to earn points</h3>
        </div>
        <div class="rw-earn-steps">
            <div class="rw-earn-step">
                <span class="rw-step-num" aria-hidden="true">1</span>
                <span>Book an appointment</span>
            </div>
            <div class="rw-earn-step">
                <span class="rw-step-num" aria-hidden="true">2</span>
                <span>Complete your visit</span>
            </div>
            <div class="rw-earn-step">
                <span class="rw-step-num" aria-hidden="true">3</span>
                <span>Earn 20 points instantly</span>
            </div>
        </div>
    </div>

    <!-- ── Section 5: Reward Catalog ── -->
    <div class="rw-catalog-section">
        <div class="rw-section-head">
            <svg viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="8" width="18" height="13" rx="2"/>
                <path d="M12 8v13M3 12h18"/>
                <path d="M7.5 8A2.5 2.5 0 1 1 12 6.5V8"/>
                <path d="M16.5 8A2.5 2.5 0 1 0 12 6.5V8"/>
            </svg>
            <h2>Reward Catalog</h2>
        </div>
        <?php if (!empty($catalog)): ?>
        <div class="rw-catalog-grid">
            <?php foreach ($catalog as $i => $item):
                $def       = $catalogDefs[$i % count($catalogDefs)];
                $pts       = (int) $item['pointsRequired'];
                $canRedeem = $currentBalance >= $pts;
                $ptsShort  = $pts - $currentBalance;
            ?>
            <div class="rw-cat-card">
                <?php if ($canRedeem): ?>
                    <div class="rw-cat-ribbon" aria-label="Available to redeem">Available</div>
                <?php endif; ?>
                <div class="rw-cat-icon-box" style="background:<?= $def['bg'] ?>" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="<?= $def['color'] ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <?= $svgPaths[$def['icon']] ?>
                    </svg>
                </div>
                <div class="rw-cat-name"><?= e($item['rewardName']) ?></div>
                <div class="rw-cat-desc"><?= e($item['description']) ?></div>
                <div class="rw-cat-pts"><?= $pts ?> <span>pts</span></div>
                <?php if ($canRedeem): ?>
                    <button class="rw-cat-btn available"
                        data-redeem-id="<?= (int) $item['rewardCatalogID'] ?>"
                        data-redeem-name="<?= e($item['rewardName']) ?>"
                        data-redeem-points="<?= $pts ?>">
                        Redeem
                    </button>
                <?php else: ?>
                    <button class="rw-cat-btn locked" disabled aria-label="Need <?= $ptsShort ?> more points to unlock">
                        Need <?= $ptsShort ?> more pts
                    </button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="panel" style="padding:30px;text-align:center;color:#72647a">No reward items available yet.</div>
        <?php endif; ?>
    </div>

    <!-- ── Section 6: Reward Transactions ── -->
    <div class="panel rw-tx-panel">
        <div class="panel-head">
            <h2>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/>
                    <path d="M14 8H8M16 12H8M12 16H8"/>
                </svg>
                Reward Transactions
            </h2>
        </div>
        <?php if (!$transactions): ?>
            <div class="rw-tx-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/>
                    <path d="M14 8H8M16 12H8M12 16H8"/>
                </svg>
                <p>No reward transactions yet. Complete your first appointment to start earning! 🦷</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Type</th>
                            <th>Points</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $row):
                            $earned   = (int) $row['pointsEarned'];
                            $redeemed = (int) $row['pointsRedeemed'];
                            $txType   = (string) $row['transactionType'];
                            $ts       = strtotime((string) $row['transactionDate']);
                        ?>
                            <tr>
                                <td><?= e($ts ? date('d M Y, g:ia', $ts) : (string) $row['transactionDate']) ?></td>
                                <td><?= e($row['rewardDescription']) ?></td>
                                <td><span class="rw-tx-badge <?= $txType === 'earned' ? 'earned' : 'redeemed' ?>"><?= e(ucfirst($txType)) ?></span></td>
                                <td>
                                    <?php if ($earned > 0): ?>
                                        <span class="rw-pts-earned">+<?= $earned ?></span>
                                    <?php elseif ($redeemed > 0): ?>
                                        <span class="rw-pts-redeemed">-<?= $redeemed ?></span>
                                    <?php else: ?>
                                        <span>—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int) $row['currentBalance'] ?> pts</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── Redemption Confirmation Modal ── -->
    <div class="rw-modal-overlay" id="rwModalOverlay" role="dialog" aria-modal="true" aria-labelledby="rwModalTitle">
        <div class="rw-modal">
            <div class="rw-modal-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="8" width="18" height="13" rx="2"/>
                    <path d="M12 8v13M3 12h18"/>
                    <path d="M7.5 8A2.5 2.5 0 1 1 12 6.5V8"/>
                    <path d="M16.5 8A2.5 2.5 0 1 0 12 6.5V8"/>
                </svg>
            </div>
            <h3 id="rwModalTitle">Confirm Redemption</h3>
            <p>You are about to redeem <strong id="rwModalRewardName"></strong> for <strong id="rwModalPoints"></strong>.</p>
            <p class="rw-modal-note">Please show the redemption record at the clinic counter. This cannot be undone.</p>
            <div class="rw-modal-actions">
                <button type="button" id="rwModalCancel">Cancel</button>
                <button type="button" id="rwModalConfirm">Confirm Redeem</button>
            </div>
        </div>
    </div>

    <!-- ── Floating Detabot Chatbot ── -->
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
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="chatbot-body" id="chatbotBody">
                <div class="chatbot-bubble">Hi <?= e($user['username']) ?>! 🎁 You have <?= $currentBalance ?> point<?= $currentBalance !== 1 ? 's' : '' ?>. Want to know how to earn more or redeem a reward?</div>
                <div class="chatbot-quick-replies">
                    <button class="chatbot-quick-btn" data-msg="How do I earn more reward points?">💡 How to Earn Points</button>
                    <button class="chatbot-quick-btn" data-msg="How do I redeem a reward?">🎁 Redeem Reward</button>
                    <button class="chatbot-quick-btn" data-msg="What is my current reward balance?">💰 My Balance</button>
                    <button class="chatbot-quick-btn" data-msg="What rewards are available in the catalog?">🏷️ View Catalog</button>
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
    window.DETABOT_PAGE_CONTEXT = 'rewards';
    </script>
    <script src="assets/chat.js"></script>

    <script>
    (function () {
        'use strict';

        /* Animate progress bar on load */
        var fill = document.getElementById('rwProgFill');
        if (fill) {
            var pct = parseFloat(fill.dataset.pct || '0');
            setTimeout(function () { fill.style.width = pct + '%'; }, 200);
        }

        /* Redeem flow */
        var pendingID = null;
        var overlay   = document.getElementById('rwModalOverlay');

        document.querySelectorAll('[data-redeem-id]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                pendingID = this.dataset.redeemId;
                document.getElementById('rwModalRewardName').textContent = this.dataset.redeemName;
                document.getElementById('rwModalPoints').textContent = this.dataset.redeemPoints + ' pts';
                overlay.style.display = 'flex';
            });
        });

        document.getElementById('rwModalCancel').addEventListener('click', function () {
            overlay.style.display = 'none';
        });

        overlay.addEventListener('click', function (e) {
            if (e.target === this) this.style.display = 'none';
        });

        document.getElementById('rwModalConfirm').addEventListener('click', async function () {
            if (!pendingID) return;

            overlay.style.display = 'none';

            var confirmBtn = this;
            var origText   = confirmBtn.textContent;
            confirmBtn.textContent = 'Redeeming…';
            confirmBtn.disabled    = true;

            try {
                var fd = new FormData();
                fd.append('_csrf_token', window.DETABOT_CSRF || '');
                fd.append('rewardCatalogID', pendingID);

                var resp = await fetch('redeem_reward.php', { method: 'POST', body: fd });
                var data = await resp.json();

                if (data.success) {
                    var balEl = document.getElementById('rwHeroBalance');
                    if (balEl) balEl.textContent = data.newBalance + ' pts';
                    setTimeout(function () { window.location.reload(); }, 900);
                } else {
                    alert(data.message || 'Redemption failed. Please try again.');
                    confirmBtn.textContent = origText;
                    confirmBtn.disabled    = false;
                }
            } catch (err) {
                alert('Network error. Please try again.');
                confirmBtn.textContent = origText;
                confirmBtn.disabled    = false;
            }
        });
    })();
    </script>

    <?php else: /* Admin / Staff view */ ?>

    <!-- ── Admin / Staff Metrics ── -->
    <section class="grid metrics-grid">
        <?php
        $issuedRow = db_one('SELECT COALESCE(SUM(pointsEarned),0) AS t FROM tbl_reward');
        metric_card('Points Issued', (int) ($issuedRow['t'] ?? 0), 'teal');
        $redemptionsRow = db_one("SELECT COUNT(*) AS t FROM tbl_reward WHERE transactionType = 'redeemed'");
        metric_card('Redemptions', (int) ($redemptionsRow['t'] ?? 0), 'amber');
        $allCatalog = db_all('SELECT * FROM tbl_reward_catalog ORDER BY pointsRequired ASC');
        metric_card('Catalog Items', count($allCatalog), 'blue');
        ?>
    </section>

    <section class="two-column align-start">
        <div class="panel">
            <div class="panel-head"><h2>Reward Catalog</h2></div>
            <div class="catalog-list">
                <?php foreach ($allCatalog as $item): ?>
                    <article class="<?= (int) $item['isActive'] === 1 ? '' : 'inactive' ?>">
                        <div>
                            <strong><?= e($item['rewardName']) ?></strong>
                            <p><?= e($item['description']) ?></p>
                        </div>
                        <span><?= e($item['pointsRequired']) ?> pts</span>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($canManage): ?>
            <div class="panel">
                <div class="panel-head"><h2>Manage Catalog</h2></div>
                <form method="post" class="form-stack">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_reward_item">
                    <label>Reward Name <input name="rewardName" required></label>
                    <label>Points Required <input name="pointsRequired" type="number" min="1" required></label>
                    <label>Description <textarea name="description" rows="3" required></textarea></label>
                    <label class="check"><input type="checkbox" name="isActive" checked> Active</label>
                    <button class="btn primary" type="submit">Add Reward Item</button>
                </form>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Reward Transactions</h2></div>
        <?php if (!$transactions): ?>
            <p class="empty">No reward transactions yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Patient</th>
                            <th>Description</th>
                            <th>Earned</th>
                            <th>Redeemed</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $row): ?>
                            <tr>
                                <td><?= e($row['transactionDate']) ?></td>
                                <td><?= e($row['username'] ?? '—') ?></td>
                                <td><?= e($row['rewardDescription']) ?></td>
                                <td><?= e($row['pointsEarned']) ?></td>
                                <td><?= e($row['pointsRedeemed']) ?></td>
                                <td><?= e($row['currentBalance']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php endif; /* end isPatient / admin-staff */ ?>
    <?php
}
