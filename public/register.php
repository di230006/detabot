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
    <title>Detabot | Create Account — Putra Dental Clinic</title>
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
                <h1>Join Putra Dental<br>Clinic Today</h1>
                <p class="auth-panel-sub">Create your account to book appointments, track your dental health and get personalised AI-powered care.</p>
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

    <!-- Right registration card -->
    <section class="auth-card">
        <h2 class="auth-card-heading">Create your account</h2>

        <div id="registerError" style="display:none;background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;border-radius:10px;padding:10px 14px;font-size:13.5px;margin-bottom:14px;font-weight:500;"></div>
        <div id="registerSuccess" style="display:none;background:#dcfce7;color:#16a34a;border:1px solid #86efac;border-radius:10px;padding:10px 14px;font-size:13.5px;margin-bottom:14px;font-weight:500;"></div>

        <form id="registerForm" class="auth-form-new" novalidate>

            <!-- Full Name -->
            <div class="auth-field-new">
                <label class="auth-label-new" for="regName">Full Name</label>
                <div class="auth-input-new">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                    <input id="regName" name="username" type="text" required autocomplete="name" placeholder="e.g. Ahmad Firdaus">
                </div>
            </div>

            <!-- Email -->
            <div class="auth-field-new">
                <label class="auth-label-new" for="regEmail">Email Address</label>
                <div class="auth-input-new">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                    <input id="regEmail" name="userEmail" type="email" inputmode="email" required autocomplete="email" placeholder="you@example.com">
                </div>
            </div>

            <!-- Phone + Age row -->
            <div class="auth-row-2">
                <div class="auth-field-new">
                    <label class="auth-label-new" for="regPhone">Phone Number</label>
                    <div class="auth-input-new">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 11.5a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.6a16 16 0 0 0 6 6l.96-.96a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        <input id="regPhone" name="userPhone" type="tel" inputmode="tel" required autocomplete="tel" placeholder="0123456789">
                    </div>
                </div>
                <div class="auth-field-new">
                    <label class="auth-label-new" for="regAge">Age</label>
                    <div class="auth-input-new">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
                        <input id="regAge" name="userAge" type="number" inputmode="numeric" required min="1" max="120" placeholder="25">
                    </div>
                </div>
            </div>

            <!-- Gender -->
            <div class="auth-field-new">
                <label class="auth-label-new" for="regGender">Gender</label>
                <div class="auth-input-new">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
                    <select id="regGender" name="userGender" required style="flex:1;border:none;outline:none;background:transparent;font-family:inherit;font-size:14px;color:#1a0e2e;cursor:pointer;padding-right:4px;">
                        <option value="" disabled selected>Select gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>
            </div>

            <!-- Password -->
            <div class="auth-field-new">
                <label class="auth-label-new" for="regPassword">Password</label>
                <div class="auth-input-new">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <input id="regPassword" name="userPassword" type="password" required autocomplete="new-password" placeholder="Min 8 chars, 1 number">
                    <button type="button" class="auth-show-btn" onclick="var p=document.getElementById('regPassword');p.type=p.type==='password'?'text':'password';this.textContent=p.type==='password'?'Show':'Hide'">Show</button>
                </div>
            </div>

            <!-- Confirm Password -->
            <div class="auth-field-new">
                <label class="auth-label-new" for="regConfirm">Confirm Password</label>
                <div class="auth-input-new">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <input id="regConfirm" name="userConfirm" type="password" required autocomplete="new-password" placeholder="Re-enter your password">
                    <button type="button" class="auth-show-btn" onclick="var p=document.getElementById('regConfirm');p.type=p.type==='password'?'text':'password';this.textContent=p.type==='password'?'Show':'Hide'">Show</button>
                </div>
            </div>

            <button class="auth-submit-btn" id="registerSubmitBtn" type="submit">Create Account</button>
        </form>

        <div class="auth-or-divider"><span>or</span></div>
        <a class="auth-outline-btn" href="login.php">Already have an account? Login</a>
    </section>

</main>

<!-- Detabot Floating Chatbot -->
<script>window.DETABOT_PAGE_CONTEXT = 'register';</script>
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
            <div class="chatbot-bubble">Hi! 👋 Need help creating your account? I can guide you through it step by step!</div>
            <div class="chatbot-quick-replies">
                <button class="chatbot-quick-btn" data-msg="What info do I need to register?">📋 What do I need?</button>
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
<script>
(function () {
    'use strict';

    var form       = document.getElementById('registerForm');
    var submitBtn  = document.getElementById('registerSubmitBtn');
    var errBox     = document.getElementById('registerError');
    var successBox = document.getElementById('registerSuccess');

    function showError(msg) {
        errBox.textContent = msg;
        errBox.style.display = 'block';
        successBox.style.display = 'none';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Create Account';
    }

    function showSuccess(msg) {
        successBox.textContent = msg;
        successBox.style.display = 'block';
        errBox.style.display = 'none';
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var name    = document.getElementById('regName').value.trim();
        var email   = document.getElementById('regEmail').value.trim();
        var phone   = document.getElementById('regPhone').value.trim();
        var age     = parseInt(document.getElementById('regAge').value, 10);
        var gender  = document.getElementById('regGender').value;
        var pass    = document.getElementById('regPassword').value;
        var confirm = document.getElementById('regConfirm').value;

        if (!name || !email || !phone || !age || !gender || !pass || !confirm) {
            showError('All fields are required.'); return;
        }
        if (name.length < 3 || !/^[a-zA-Z\s]+$/.test(name)) {
            showError('Full name must be letters only, minimum 3 characters.'); return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showError('Please enter a valid email address.'); return;
        }
        if (phone.replace(/\D/g, '').length < 10) {
            showError('Please enter a valid phone number (minimum 10 digits).'); return;
        }
        if (isNaN(age) || age < 1 || age > 120) {
            showError('Please enter a valid age.'); return;
        }
        if (pass.length < 8 || !/\d/.test(pass)) {
            showError('Password must be at least 8 characters and contain at least 1 number.'); return;
        }
        if (pass !== confirm) {
            showError('Passwords do not match.'); return;
        }

        errBox.style.display = 'none';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Creating account…';

        fetch('register_handler.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                username: name, userEmail: email, userPhone: phone,
                userAge: age, userGender: gender, userPassword: pass,
            }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                showSuccess('Account created successfully! Redirecting to login…');
                setTimeout(function () { window.location.href = 'login.php'; }, 1500);
            } else {
                showError(data.message || 'Something went wrong. Please try again.');
            }
        })
        .catch(function () {
            showError('Connection error. Please check your connection and try again.');
        });
    });
}());
</script>
</body>
</html>
