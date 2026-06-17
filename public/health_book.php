<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

// ── Auth guard ────────────────────────────────────────────────────────────────
if (empty($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}

$userID = (int) $_SESSION['userID'];
$user   = db_one('SELECT * FROM tbl_user WHERE userID = ? AND status = ?', [$userID, 'active']);

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// ── Data ──────────────────────────────────────────────────────────────────────
$appointments = db_all(
    "SELECT * FROM tbl_appointment WHERE userID = ? ORDER BY appointmentDate DESC, appointmentTime DESC LIMIT 20",
    [$userID]
);

$rewardRow      = db_one('SELECT currentBalance FROM tbl_reward WHERE userID = ? ORDER BY rewardID DESC LIMIT 1', [$userID]);
$rewardBalance  = (int) ($rewardRow['currentBalance'] ?? 0);
$totalEarned    = (int) (db_one('SELECT COALESCE(SUM(pointsEarned),0) AS t FROM tbl_reward WHERE userID = ?', [$userID])['t'] ?? 0);
$totalRedeemed  = (int) (db_one('SELECT COALESCE(SUM(pointsRedeemed),0) AS t FROM tbl_reward WHERE userID = ?', [$userID])['t'] ?? 0);
$recentRewards  = db_all('SELECT * FROM tbl_reward WHERE userID = ? ORDER BY rewardID DESC LIMIT 5', [$userID]);

$chronicProblems = trim((string) ($user['userChronicHealthProblems'] ?? ''));
$initials        = strtoupper(substr((string) $user['username'], 0, 2));

$statusColors = [
    'pending'   => '#f59e0b',
    'confirmed' => '#10b981',
    'completed' => '#6d28d9',
    'cancelled' => '#ef4444',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Health Book | Detabot</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
    <style>
        /* ── Health Book extras ─────────────────────────── */
        .hb-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; margin-bottom: 24px; }
        .hb-card { background: #fff; border-radius: 16px; padding: 22px 24px; box-shadow: 0 1px 6px rgba(59,7,100,.06); border: 1px solid #ede9fe; }
        .hb-card-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #7c3aed; margin: 0 0 14px; }
        .hb-info-row { display: flex; justify-content: space-between; align-items: baseline; padding: 7px 0; border-bottom: 1px solid #f5f3ff; font-size: 13.5px; }
        .hb-info-row:last-child { border-bottom: none; }
        .hb-info-label { color: #6b7280; font-weight: 500; }
        .hb-info-value { color: #1e1b4b; font-weight: 600; text-align: right; max-width: 60%; word-break: break-word; }
        .hb-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11.5px; font-weight: 700; text-transform: capitalize; }
        .hb-pts-row { display: flex; gap: 12px; }
        .hb-pts-box { flex: 1; background: linear-gradient(135deg, #f5f3ff, #ede9fe); border-radius: 12px; padding: 14px 16px; text-align: center; }
        .hb-pts-num { font-family: 'Sora', sans-serif; font-size: 22px; font-weight: 800; color: #6d28d9; display: block; }
        .hb-pts-label { font-size: 11px; color: #7c3aed; font-weight: 600; }
        .hb-pts-balance { background: linear-gradient(135deg, #3b0764, #6d28d9); }
        .hb-pts-balance .hb-pts-num, .hb-pts-balance .hb-pts-label { color: #fff; }
        .hb-table-wrap { overflow-x: auto; }
        .hb-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        .hb-table th { text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #7c3aed; padding: 0 12px 10px; }
        .hb-table td { padding: 11px 12px; border-top: 1px solid #f5f3ff; color: #374151; vertical-align: middle; }
        .hb-table tr:hover td { background: #faf8ff; }
        .hb-empty { text-align: center; color: #9ca3af; padding: 32px 0; font-size: 14px; }
        .hb-avatar { width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(135deg,#c84fce,#8b5cf6); display: flex; align-items: center; justify-content: center; font-family: 'Sora',sans-serif; font-weight: 800; color: #fff; font-size: 15px; flex-shrink: 0; }
        .section-heading { font-size: 16px; font-weight: 700; color: #1e1b4b; margin: 0 0 14px; }
    </style>
</head>
<body>
<div class="app-shell">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sb-brand">
            <a class="sb-logo-link" href="health_book.php">
                <div class="sb-logo-box"><img src="assets/detabot-logo.svg" alt="Detabot"></div>
                <div class="sb-brand-text">
                    <strong class="sb-brand-name">Detabot</strong>
                    <small class="sb-brand-sub">Putra Dental Clinic</small>
                </div>
            </a>
        </div>

        <div class="sb-clinic-card">
            <img class="sb-clinic-logo" src="assets/clinic-logo.png" alt="Putra Dental Clinic">
            <div>
                <span class="sb-clinic-name">Putra Dental Clinic</span>
                <span class="sb-clinic-loc">Parit Raja, Johor</span>
            </div>
        </div>

        <nav class="sb-nav">
            <div class="sb-nav-group">
                <span class="sb-nav-label">Patient</span>
                <a class="sb-nav-item active" href="health_book.php">
                    <span class="sb-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg></span>
                    <span class="sb-nav-text">Health Book</span>
                </a>
                <a class="sb-nav-item" href="appointments.php">
                    <span class="sb-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg></span>
                    <span class="sb-nav-text">Appointments</span>
                </a>
                <a class="sb-nav-item" href="rewards.php">
                    <span class="sb-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="13" rx="2"/><path d="M12 8v13M3 12h18"/><path d="M7.5 8A2.5 2.5 0 1 1 12 6.5V8"/><path d="M16.5 8A2.5 2.5 0 1 0 12 6.5V8"/></svg></span>
                    <span class="sb-nav-text">Rewards</span>
                </a>
            </div>
        </nav>

        <div class="sb-user">
            <div class="sb-user-avatar"><?= e($initials) ?></div>
            <div class="sb-user-info">
                <span class="sb-user-name"><?= e((string) $user['username']) ?></span>
                <span class="sb-user-role"><?= e(ucfirst((string) $user['userRole'])) ?></span>
            </div>
            <a class="sb-logout-btn" href="logout.php" title="Logout">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </a>
        </div>
    </aside>

    <!-- Main -->
    <main class="main-panel">
        <header class="topbar">
            <div>
                <p class="eyebrow">PATIENT</p>
                <h1>Health Book</h1>
            </div>
            <div class="user-menu">
                <div class="hb-avatar"><?= e($initials) ?></div>
                <span class="topbar-username"><?= e((string) $user['username']) ?></span>
                <a href="logout.php" class="btn ghost">Logout</a>
            </div>
        </header>

        <div style="padding: 24px 28px;">

            <!-- Patient info + reward points -->
            <div class="hb-grid">

                <!-- Patient profile -->
                <div class="hb-card">
                    <p class="hb-card-title">Patient Profile</p>
                    <div class="hb-info-row">
                        <span class="hb-info-label">Name</span>
                        <span class="hb-info-value"><?= e((string) $user['username']) ?></span>
                    </div>
                    <div class="hb-info-row">
                        <span class="hb-info-label">Email</span>
                        <span class="hb-info-value"><?= e((string) $user['userEmail']) ?></span>
                    </div>
                    <div class="hb-info-row">
                        <span class="hb-info-label">Phone</span>
                        <span class="hb-info-value"><?= e((string) ($user['userPhone'] ?? '—')) ?></span>
                    </div>
                    <div class="hb-info-row">
                        <span class="hb-info-label">Age</span>
                        <span class="hb-info-value"><?= e((string) ($user['userAge'] ?? '—')) ?></span>
                    </div>
                    <div class="hb-info-row">
                        <span class="hb-info-label">Gender</span>
                        <span class="hb-info-value"><?= e(ucfirst((string) ($user['userGender'] ?? '—'))) ?></span>
                    </div>
                    <?php if ($chronicProblems !== ''): ?>
                    <div class="hb-info-row">
                        <span class="hb-info-label">Chronic Conditions</span>
                        <span class="hb-info-value" style="color:#ef4444;"><?= e($chronicProblems) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Reward points -->
                <div class="hb-card">
                    <p class="hb-card-title">Reward Points</p>
                    <div class="hb-pts-row" style="margin-bottom:14px;">
                        <div class="hb-pts-box hb-pts-balance">
                            <span class="hb-pts-num"><?= $rewardBalance ?></span>
                            <span class="hb-pts-label">Current Balance</span>
                        </div>
                    </div>
                    <div class="hb-pts-row">
                        <div class="hb-pts-box">
                            <span class="hb-pts-num"><?= $totalEarned ?></span>
                            <span class="hb-pts-label">Total Earned</span>
                        </div>
                        <div class="hb-pts-box">
                            <span class="hb-pts-num"><?= $totalRedeemed ?></span>
                            <span class="hb-pts-label">Total Redeemed</span>
                        </div>
                    </div>
                    <?php if ($recentRewards): ?>
                    <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#7c3aed;margin:18px 0 8px;">Recent Transactions</p>
                    <?php foreach ($recentRewards as $rw): ?>
                        <div style="display:flex;justify-content:space-between;font-size:12.5px;padding:5px 0;border-bottom:1px solid #f5f3ff;">
                            <span style="color:#374151;"><?= e((string) $rw['rewardDescription']) ?></span>
                            <span style="font-weight:700;color:<?= $rw['transactionType'] === 'earned' ? '#10b981' : '#ef4444' ?>;">
                                <?= $rw['transactionType'] === 'earned' ? '+' : '-' ?><?= (int) ($rw['pointsEarned'] ?: $rw['pointsRedeemed']) ?> pts
                            </span>
                        </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Appointment history -->
            <div class="hb-card">
                <p class="section-heading">Appointment History</p>
                <?php if ($appointments): ?>
                <div class="hb-table-wrap">
                    <table class="hb-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Service</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($appointments as $appt): ?>
                            <?php
                            $status = (string) $appt['status'];
                            $color  = $statusColors[$status] ?? '#6b7280';
                            $bg     = $color . '18';
                            ?>
                            <tr>
                                <td><?= e(date('d M Y', strtotime((string) $appt['appointmentDate']))) ?></td>
                                <td><?= e(substr((string) $appt['appointmentTime'], 0, 5)) ?></td>
                                <td><?= e((string) $appt['serviceType']) ?></td>
                                <td>
                                    <span class="hb-badge" style="color:<?= $color ?>;background:<?= $bg ?>;">
                                        <?= e(ucfirst($status)) ?>
                                    </span>
                                </td>
                                <td style="color:#9ca3af;font-size:12.5px;"><?= e((string) ($appt['notes'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="hb-empty">No appointments yet. Book your first appointment with Detabot! 🦷</p>
                <?php endif; ?>
            </div>

        </div><!-- /padding wrapper -->
    </main>
</div><!-- /app-shell -->

<!-- Detabot Floating Chatbot -->
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
            <div class="chatbot-bubble">Hi <?= e((string) $user['username']) ?>! 👋 How can I help you today?</div>
            <div class="chatbot-quick-replies">
                <button class="chatbot-quick-btn" data-msg="Book appointment">📅 Book appointment</button>
                <button class="chatbot-quick-btn" data-msg="My reward points">⭐ My reward points</button>
                <button class="chatbot-quick-btn" data-msg="Clinic hours">🕐 Clinic hours</button>
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

<script>window.DETABOT_USER_ID = <?= (int) $userID ?>;</script>
<script src="assets/chat.js"></script>
</body>
</html>
