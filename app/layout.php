<?php
declare(strict_types=1);

function render_guest(): void
{
    $view = $_GET['view'] ?? 'login';
    $isRegister = $view === 'register';
    $isForgot = $view === 'forgot';
    $isReset = $view === 'reset';
    $isLogin = !$isRegister && !$isForgot && !$isReset;
    $resetEmail = strtolower((string) ($_GET['email'] ?? ''));
    $chronicProblems = chronic_health_problem_options();
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Detabot | Clinic Putra Dental</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=DM+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="assets/app.css">
    </head>
    <body class="guest-shell">
        <main class="auth-layout">

            <section class="auth-panel">
                <div class="auth-blob auth-blob-1"></div>
                <div class="auth-blob auth-blob-2"></div>
                <div class="auth-blob auth-blob-3"></div>
                <div class="auth-panel-inner">
                    <div>
                        <span class="auth-portal-badge">Dental Portal</span>
                        <div class="auth-panel-logo">
                            <img src="assets/detabot-logo.svg" alt="Detabot">
                        </div>
                        <h1>Your Smile,<br>Our Priority</h1>
                        <p class="auth-panel-sub">Manage appointments, track dental health and get personalised care — all in one place.</p>
                    </div>
                    <div class="auth-trust-cards">
                        <div class="auth-trust-card">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8V4"/><rect x="5" y="8" width="14" height="10" rx="4"/><path d="M8 18l-2.5 2.5V17"/><circle cx="9" cy="13" r="1"/><circle cx="15" cy="13" r="1"/><path d="M10 16h4"/></svg>
                            <div>
                                <strong>Detabot</strong>
                                <span>Your Assistant Powered by AI</span>
                            </div>
                        </div>
                        <div class="auth-trust-card">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01"/></svg>
                            <div>
                                <strong>Easy Scheduling</strong>
                                <span>Book appointments in minutes</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="auth-card">
                <?php render_flash(); ?>

                <?php if ($isRegister): ?>
                    <h2 class="auth-card-heading">Create your account</h2>
                    <form method="post" class="auth-form-new">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="register">
                        <div class="auth-field-new">
                            <label class="auth-label-new">Full Name</label>
                            <div class="auth-input-new">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                                <input name="username" required maxlength="50" autocomplete="name" placeholder="Enter your full name">
                            </div>
                        </div>
                        <div class="auth-field-new">
                            <label class="auth-label-new">Email</label>
                            <div class="auth-input-new">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                                <input name="userEmail" inputmode="email" required maxlength="100" autocomplete="email" placeholder="you@example.com">
                            </div>
                        </div>
                        <div class="auth-field-new">
                            <label class="auth-label-new">Phone</label>
                            <div class="auth-input-new">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.07 9.81 19.79 19.79 0 0 1 1 1.18 2 2 0 0 1 2.96 0h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L7.09 7.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 21 15z"/></svg>
                                <input name="userPhone" required maxlength="20" autocomplete="tel" placeholder="e.g. 011-1234 5678">
                            </div>
                        </div>
                        <div class="auth-row-2">
                            <div class="auth-field-new">
                                <label class="auth-label-new">Age</label>
                                <div class="auth-input-new">
                                    <input name="userAge" type="number" min="1" max="120" required placeholder="e.g. 25">
                                </div>
                            </div>
                            <div class="auth-field-new">
                                <label class="auth-label-new">Gender</label>
                                <div class="auth-gender-row">
                                    <label class="auth-gender-opt"><input type="radio" name="userGender" value="male" required><span>Male</span></label>
                                    <label class="auth-gender-opt"><input type="radio" name="userGender" value="female" required><span>Female</span></label>
                                </div>
                            </div>
                        </div>
                        <div class="auth-field-new">
                            <label class="auth-label-new">Chronic Health Problem</label>
                            <div class="auth-checkbox-grid">
                                <?php foreach ($chronicProblems as $problem): ?>
                                    <label class="auth-check">
                                        <input type="checkbox" name="chronicHealthProblems[]" value="<?= e($problem) ?>">
                                        <span><?= e($problem) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="auth-field-new">
                            <label class="auth-label-new">Password</label>
                            <div class="auth-input-new">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                <input name="userPassword" id="reg-password" type="password" required minlength="6" autocomplete="new-password" placeholder="Min. 6 characters">
                                <button type="button" class="auth-show-btn" onclick="const p=document.getElementById('reg-password');p.type=p.type==='password'?'text':'password';this.textContent=p.type==='password'?'Show':'Hide'">Show</button>
                            </div>
                        </div>
                        <button class="auth-submit-btn" type="submit">Create Account</button>
                    </form>
                    <div class="auth-or-divider"><span>or</span></div>
                    <a class="auth-outline-btn" href="index.php">Sign in instead</a>

                <?php elseif ($isForgot || $isReset): ?>
                    <h2 class="auth-card-heading">Reset your password</h2>
                    <p class="auth-card-sub">Enter your registered email and choose a new password.</p>
                    <form method="post" class="auth-form-new">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reset_password">
                        <div class="auth-field-new">
                            <label class="auth-label-new">Email</label>
                            <div class="auth-input-new">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                                <input name="userEmail" inputmode="email" required maxlength="100" autocomplete="email" value="<?= e($resetEmail) ?>" placeholder="you@example.com">
                            </div>
                        </div>
                        <div class="auth-field-new">
                            <label class="auth-label-new">New Password</label>
                            <div class="auth-input-new">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                <input name="userPassword" type="password" required minlength="6" autocomplete="new-password" placeholder="Min. 6 characters">
                            </div>
                        </div>
                        <button class="auth-submit-btn" type="submit">Reset Password</button>
                    </form>
                    <div class="auth-or-divider"><span>or</span></div>
                    <a class="auth-outline-btn" href="index.php">Back to login</a>

                <?php else: ?>
                    <h2 class="auth-card-heading">Sign in to your account</h2>
                    <form method="post" class="auth-form-new">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="login">
                        <div class="auth-field-new">
                            <label class="auth-label-new">Email Address</label>
                            <div class="auth-input-new">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                                <input name="userEmail" inputmode="email" required autocomplete="email" placeholder="you@example.com">
                            </div>
                        </div>
                        <div class="auth-field-new">
                            <label class="auth-label-new">Password</label>
                            <div class="auth-input-new">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                <input name="userPassword" id="login-password" type="password" required autocomplete="current-password" placeholder="••••••••">
                                <button type="button" class="auth-show-btn" onclick="const p=document.getElementById('login-password');p.type=p.type==='password'?'text':'password';this.textContent=p.type==='password'?'Show':'Hide'">Show</button>
                            </div>
                            <a class="auth-forgot-link" href="index.php?view=forgot">Forgot password?</a>
                        </div>
                        <button class="auth-submit-btn" type="submit">Login</button>
                    </form>
                    <div class="auth-or-divider"><span>or</span></div>
                    <a class="auth-outline-btn" href="index.php?view=register">Create an account</a>
                <?php endif; ?>
            </section>
        </main>

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
                    <div class="chatbot-bubble">Hi! I'm Detabot 🤖 Your AI dental assistant for Putra Dental Clinic, Parit Raja.</div>
                    <div class="chatbot-quick-replies">
                        <button class="chatbot-quick-btn" data-msg="Book appointment">📅 Book appointment</button>
                        <button class="chatbot-quick-btn" data-msg="Clinic hours">🕐 Clinic hours</button>
                        <button class="chatbot-quick-btn" data-msg="Our services">🦷 Our services</button>
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
        <script src="assets/chat.js"></script>
    </body>
    </html>
    <?php
}

function render_header(array $user, string $page): void
{
    $nav = nav_items($user);
    $pendingBadgeCount = has_role($user, ['admin', 'staff'])
        ? (int) (db_one("SELECT COUNT(*) AS c FROM tbl_appointment WHERE status = 'pending'")['c'] ?? 0)
        : 0;
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e(page_title($page)) ?> | Detabot</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="assets/app.css?v=<?= filemtime(ROOT_PATH . '/public/assets/app.css') ?>">
        <script>
            window.DETABOT_CSRF = '<?= e(csrf_token()) ?>';
        </script>
    </head>
    <body>
        <div class="app-shell" id="sidebarApp">
            <aside class="sidebar" id="mainSidebar">
                <!-- Brand -->
                <div class="sb-brand">
                    <a class="sb-logo-link" href="<?= e(page_url('dashboard')) ?>">
                        <div class="sb-logo-box">
                            <img src="assets/detabot-logo.svg" alt="Detabot">
                        </div>
                        <div class="sb-brand-text">
                            <strong class="sb-brand-name">Detabot</strong>
                            <small class="sb-brand-sub">Putra Dental Clinic</small>
                        </div>
                    </a>
                </div>

                <!-- Clinic info card -->
                <div class="sb-clinic-card">
                    <img class="sb-clinic-logo" src="assets/clinic-logo.png" alt="Putra Dental Clinic">
                    <div>
                        <span class="sb-clinic-name">Putra Dental Clinic</span>
                        <span class="sb-clinic-loc">Parit Raja, Johor</span>
                    </div>
                </div>

                <!-- Navigation -->
                <?php
                $mainNav = array_filter($nav, fn($i) => ($i['group'] ?? 'more') === 'main');
                $moreNav = array_filter($nav, fn($i) => ($i['group'] ?? 'more') === 'more');
                ?>
                <nav class="sb-nav">
                    <div class="sb-nav-group">
                        <span class="sb-nav-label">Main</span>
                        <?php foreach ($mainNav as $item): ?>
                            <a class="sb-nav-item <?= $page === $item['page'] ? 'active' : '' ?>" href="<?= e(page_url($item['page'])) ?>" title="<?= e($item['label']) ?>">
                                <span class="sb-nav-icon"><?= render_nav_icon($item['icon']) ?></span>
                                <span class="sb-nav-text"><?= e($item['label']) ?></span>
                                <?php if ($item['page'] === 'appointments' && $pendingBadgeCount > 0): ?>
                                    <span class="sb-badge sb-badge-pink"><?= $pendingBadgeCount ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <div class="sb-nav-group">
                        <span class="sb-nav-label">More</span>
                        <?php foreach ($moreNav as $item): ?>
                            <a class="sb-nav-item <?= $page === $item['page'] ? 'active' : '' ?>" href="<?= e(page_url($item['page'])) ?>" title="<?= e($item['label']) ?>">
                                <span class="sb-nav-icon"><?= render_nav_icon($item['icon']) ?></span>
                                <span class="sb-nav-text"><?= e($item['label']) ?></span>
                                <?php if ($item['page'] === 'rewards'): ?>
                                    <span class="sb-badge sb-badge-amber">New</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </nav>

                <!-- User profile -->
                <?php
                $initials   = strtoupper(substr((string) ($user['username'] ?? 'U'), 0, 2));
                $sbAvatarUrl = user_avatar_url($user);
                ?>
                <div class="sb-user">
                    <div class="sb-user-avatar">
                        <?php if ($sbAvatarUrl): ?>
                            <img src="<?= e($sbAvatarUrl) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
                        <?php else: ?>
                            <?= e($initials) ?>
                        <?php endif; ?>
                    </div>
                    <div class="sb-user-info">
                        <span class="sb-user-name"><?= e($user['username']) ?></span>
                        <span class="sb-user-role"><?= e(ucfirst((string) $user['userRole'])) ?></span>
                    </div>
                    <form method="post" class="sb-logout-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="logout">
                        <button class="sb-logout-btn" type="submit" title="Logout">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        </button>
                    </form>
                </div>
            </aside>

            <main class="main-panel">
                <header class="topbar">
                    <div>
                        <p class="eyebrow"><?= e(strtoupper($user['userRole'])) ?></p>
                        <h1><?= e(page_title($page)) ?></h1>
                    </div>
                    <div class="user-menu">
                        <a href="<?= e(page_url(has_role($user, ['admin', 'staff']) ? 'staff_profile' : 'profile')) ?>" class="topbar-avatar-link" title="My Profile" id="topbar-profile-link">
                            <?php $avatarUrl = user_avatar_url($user); ?>
                            <?php if ($avatarUrl): ?>
                                <img class="topbar-avatar" src="<?= e($avatarUrl) ?>" alt="Profile picture of <?= e($user['username']) ?>">
                            <?php else: ?>
                                <span class="topbar-avatar topbar-avatar-initials"><?= e(strtoupper(substr((string) $user['username'], 0, 1))) ?></span>
                            <?php endif; ?>
                        </a>
                        <span class="topbar-username"><?= e($user['username']) ?></span>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="logout">
                            <button class="btn ghost" type="submit">Logout</button>
                        </form>
                    </div>
                </header>
                <?php render_flash(); ?>
    <?php
}

function render_footer(): void
{
    ?>
            </main>
        </div>
        <script src="assets/app.js?v=<?= filemtime(ROOT_PATH . '/public/assets/app.js') ?>"></script>
    </body>
    </html>
    <?php
}

function nav_items(array $user): array
{
    if (has_role($user, ['admin', 'staff'])) {
        // ── Staff / Admin navigation (no Rewards) ──
        $items = [
            ['page' => 'dashboard',            'label' => 'Dashboard',      'icon' => 'dashboard',  'group' => 'main'],
            ['page' => 'manage_appointments',  'label' => 'Appointments',   'icon' => 'calendar',   'group' => 'main'],
            ['page' => 'manage_patients',      'label' => 'Patients',       'icon' => 'users',      'group' => 'main'],
            ['page' => 'staff_health_record',  'label' => 'Health Records', 'icon' => 'healthbook', 'group' => 'main'],
            ['page' => 'manage_feedback',      'label' => 'Feedback',       'icon' => 'feedback',   'group' => 'more'],
            ['page' => 'manage_rewards',       'label' => 'Rewards',        'icon' => 'gift',       'group' => 'more'],
            ['page' => 'clinic',               'label' => 'Clinic Info',    'icon' => 'clinic',     'group' => 'more'],
            ['page' => 'reports',              'label' => 'Reports',        'icon' => 'reports',    'group' => 'more'],
        ];
        if (has_role($user, 'admin')) {
            $items[] = ['page' => 'users',    'label' => 'Users',    'icon' => 'users',    'group' => 'more'];
            $items[] = ['page' => 'activity', 'label' => 'Activity', 'icon' => 'activity', 'group' => 'more'];
        }
        $items[] = ['page' => 'staff_profile', 'label' => 'My Profile', 'icon' => 'profile', 'group' => 'more'];
    } else {
        // ── Patient navigation ──
        $items = [
            ['page' => 'dashboard',    'label' => 'Dashboard',          'icon' => 'dashboard',  'group' => 'main'],
            ['page' => 'appointments', 'label' => 'Appointments',       'icon' => 'calendar',   'group' => 'main'],
            ['page' => 'healthbook',   'label' => 'Health Record',      'icon' => 'healthbook', 'group' => 'main'],
            ['page' => 'clinic',       'label' => 'Clinic Information', 'icon' => 'clinic',     'group' => 'more'],
            ['page' => 'feedback',     'label' => 'Feedback',           'icon' => 'feedback',   'group' => 'more'],
            ['page' => 'rewards',      'label' => 'Rewards',            'icon' => 'gift',       'group' => 'more'],
            ['page' => 'profile',      'label' => 'My Profile',         'icon' => 'profile',    'group' => 'more'],
        ];
    }

    return $items;
}

function render_nav_icon(string $icon): string
{
    $attrs = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';

    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/>',
        'bot' => '<path d="M12 8V4"/><rect x="5" y="8" width="14" height="10" rx="4"/><path d="M8 18l-2.5 2.5V17"/><circle cx="9" cy="13" r="1"/><circle cx="15" cy="13" r="1"/><path d="M10 16h4"/>',
        'clinic' => '<path d="M4 21V7l8-4 8 4v14"/><path d="M9 21v-6h6v6"/><path d="M10 9h4M12 7v4"/><path d="M4 21h16"/>',
        'feedback' => '<path d="M21 12a8 8 0 0 1-8 8H7l-4 3v-6.5A8 8 0 1 1 21 12Z"/><path d="M8 12h8M8 15h5"/>',
        'gift' => '<rect x="3" y="8" width="18" height="13" rx="2"/><path d="M12 8v13M3 12h18"/><path d="M7.5 8A2.5 2.5 0 1 1 12 6.5V8"/><path d="M16.5 8A2.5 2.5 0 1 0 12 6.5V8"/>',
        'reports' => '<path d="M4 19V5"/><path d="M4 19h16"/><rect x="7" y="11" width="3" height="5"/><rect x="12" y="7" width="3" height="9"/><rect x="17" y="3" width="3" height="13"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'activity'    => '<path d="M4 19h16"/><path d="M6 16V8"/><path d="M10 16V4"/><path d="M14 16v-6"/><path d="M18 16V7"/>',
        'healthbook'  => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
        'profile'     => '<circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>',

    ];

    $content = $paths[$icon] ?? $paths['dashboard'];

    return '<svg ' . $attrs . ' aria-hidden="true">' . $content . '</svg>';
}

function page_title(string $page): string
{
    return [
        'dashboard'           => 'Dashboard',
        'appointments'        => 'Appointments',
        'clinic'              => 'Clinic Information',
        'feedback'            => 'Feedback',
        'rewards'             => 'Rewards',
        'reports'             => 'Reports & Analytics',
        'users'               => 'Admin / Staff Management',
        'healthbook'          => 'Dental Health Record',
        'activity'            => 'Activity Monitor',
        'profile'             => 'My Profile',
        'patients'            => 'Patients',
        'manage_patients'     => 'Manage Patients',
        'staff_health_record' => 'Health Records',
        'manage_appointments' => 'Appointments',
        'manage_feedback'     => 'Manage Feedback',
        'manage_rewards'          => 'Manage Rewards Catalog',
        'edit_clinic_information' => 'Edit Clinic Information',
        'staff_profile'           => 'My Profile',
        'manage_staff'            => 'Manage Staff & Users',
    ][$page] ?? 'Dashboard';
}

function render_flash(): void
{
    foreach (consume_flash() as $message): ?>
        <div class="alert <?= e($message['type']) ?>"><?= e($message['message']) ?></div>
    <?php endforeach;
}

function metric_card(string $label, string|int|float $value, string $tone = ''): void
{
    ?>
    <div class="metric <?= e($tone) ?>">
        <span><?= e($label) ?></span>
        <strong><?= e((string) $value) ?></strong>
    </div>
    <?php
}
