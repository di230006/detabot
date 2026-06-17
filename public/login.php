<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

if (!empty($_SESSION['userID'])) {
    $role = (string) ($_SESSION['userRole'] ?? 'patient');
    header('Location: ' . ($role === 'admin' ? 'admin_dashboard.php' : ($role === 'staff' ? 'dashboard.php' : 'health_book.php')));
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detabot | Login — Putra Dental Clinic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=DM+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body class="guest-shell">
<main class="auth-layout">

    <!-- Left branding panel -->
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
                    <div><strong>Detabot</strong><span>Your Assistant Powered by AI</span></div>
                </div>
                <div class="auth-trust-card">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01"/></svg>
                    <div><strong>Easy Scheduling</strong><span>Book appointments in minutes</span></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Right login card -->
    <section class="auth-card">
        <h2 class="auth-card-heading">Sign in to your account</h2>

        <div id="loginError" style="display:none;background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;border-radius:10px;padding:10px 14px;font-size:13.5px;margin-bottom:14px;font-weight:500;"></div>

        <form id="loginForm" class="auth-form-new" novalidate>
            <div class="auth-field-new">
                <label class="auth-label-new" for="loginEmail">Email Address</label>
                <div class="auth-input-new">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                    <input id="loginEmail" name="userEmail" type="email" inputmode="email" required autocomplete="email" placeholder="you@example.com">
                </div>
            </div>
            <div class="auth-field-new">
                <label class="auth-label-new" for="loginPassword">Password</label>
                <div class="auth-input-new">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <input id="loginPassword" name="userPassword" type="password" required autocomplete="current-password" placeholder="••••••••">
                    <button type="button" class="auth-show-btn" onclick="var p=document.getElementById('loginPassword');p.type=p.type==='password'?'text':'password';this.textContent=p.type==='password'?'Show':'Hide'">Show</button>
                </div>
            </div>
            <button class="auth-submit-btn" id="loginSubmitBtn" type="submit">Login</button>
        </form>

        <div class="auth-or-divider"><span>or</span></div>
        <a class="auth-outline-btn" href="register.php">Create an account</a>
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
