/* =============================================================
   Detabot – chat.js
   ============================================================= */
(function () {
    'use strict';

    /* ── Page context ─────────────────────────────────────────── */
    var path             = window.location.pathname.toLowerCase();
    var isNewLoginPage   = path.indexOf('login.php') !== -1;
    var isLegacyLogin    = !isNewLoginPage && path.indexOf('index') !== -1;
    var isLoginPage      = isNewLoginPage || isLegacyLogin;
    var isRegisterPage   = (window.DETABOT_PAGE_CONTEXT === 'register') || path.indexOf('register.php') !== -1;
    var isHealthRecord   = path.indexOf('health_record') !== -1;
    var isAppointments   = (window.DETABOT_PAGE_CONTEXT === 'appointments') || path.indexOf('appointments') !== -1;
    var pageContext      = isLoginPage ? 'login'
                        : isRegisterPage ? 'register'
                        : isHealthRecord ? 'health_record'
                        : isAppointments ? 'appointments'
                        : 'app';

    /* ── Persistent session ID per browser tab ────────────────── */
    var SESSION_KEY = 'detabot_session_id';
    var sessionID   = sessionStorage.getItem(SESSION_KEY);
    if (!sessionID) {
        sessionID = 'sess_' + Date.now() + '_' + Math.random().toString(36).slice(2, 10);
        sessionStorage.setItem(SESSION_KEY, sessionID);
    }

    /* ── DOM refs ─────────────────────────────────────────────── */
    var btn      = document.getElementById('chatbotBtn');
    var win      = document.getElementById('chatbotWindow');
    var closeBtn = document.getElementById('chatbotClose');
    var inp      = document.getElementById('chatbotInput');
    var send     = document.getElementById('chatbotSend');
    var body     = document.getElementById('chatbotBody');

    if (!btn) return;

    /* ── State ────────────────────────────────────────────────── */
    var isOpen         = false;
    var loginGreetDone = false;
    var botBusy        = false;

    /* ── Registration flow state ──────────────────────────────── */
    var reg = { active: false, step: 0, data: {} };

    /* ── Safe HTML escape ─────────────────────────────────────── */
    function esc(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /* ── Bubble helpers ───────────────────────────────────────── */
    function addBotBubble(text) {
        var el = document.createElement('div');
        el.className = 'chatbot-bubble';
        el.textContent = text;
        body.appendChild(el);
        body.scrollTop = body.scrollHeight;
        return el;
    }

    function addUserBubble(text) {
        var el = document.createElement('div');
        el.className = 'chatbot-user-bubble';
        el.textContent = text;
        body.appendChild(el);
        body.scrollTop = body.scrollHeight;
    }

    function clearInitialContent() {
        var bubble = body.querySelector('.chatbot-bubble');
        var qr     = body.querySelector('.chatbot-quick-replies');
        if (bubble) bubble.remove();
        if (qr)     qr.remove();
    }

    /* ── Quick-reply rows ─────────────────────────────────────── */
    function addQuickReplies(buttons) {
        removeQuickReplies();
        var wrap = document.createElement('div');
        wrap.className = 'chatbot-quick-replies';
        buttons.forEach(function (cfg) {
            var b = document.createElement('button');
            b.className = 'chatbot-quick-btn';
            b.textContent = cfg.label;
            b.addEventListener('click', function () { wrap.remove(); cfg.action(); });
            wrap.appendChild(b);
        });
        body.appendChild(wrap);
        body.scrollTop = body.scrollHeight;
    }

    function removeQuickReplies() {
        body.querySelectorAll('.chatbot-quick-replies').forEach(function (el) { el.remove(); });
    }

    /* ── Input type helpers ───────────────────────────────────── */
    function setInputPassword() { inp.type = 'password'; inp.placeholder = 'Enter password…'; }
    function setInputText()     { inp.type = 'text';     inp.placeholder = 'Type a message…'; }

    /* ── Typing indicator ─────────────────────────────────────── */
    function showTyping() {
        var el = document.createElement('div');
        el.className = 'chatbot-bubble chatbot-typing';
        el.id        = 'chatbotTyping';
        el.innerHTML = '<span></span><span></span><span></span>';
        body.appendChild(el);
        body.scrollTop = body.scrollHeight;
        botBusy = true;
    }

    function hideTyping() {
        var el = document.getElementById('chatbotTyping');
        if (el) el.remove();
        botBusy = false;
    }

    /* ── Groq API call ────────────────────────────────────────── */
    function callBot(message, onDone) {
        showTyping();
        fetch('chatbot.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                message:     message,
                userID:      window.DETABOT_USER_ID || 0,
                sessionID:   sessionID,
                pageContext: pageContext,
            }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            hideTyping();
            if (isAppointments) {
                if (data.action === 'book_appointment') {
                    handleBookingAction(data);
                    return;
                }
                if (data.action === 'recommend_treatment') {
                    handleRecommendationAction(data);
                    return;
                }
            }
            var text = data.reply || data.error || 'Sorry, something went wrong. Please try again.';
            addBotBubble(text);
            // Render AI-suggested quick-reply buttons (problem choices, dentist options, etc.)
            if (Array.isArray(data.quick_replies) && data.quick_replies.length > 0) {
                addQuickReplies(data.quick_replies.map(function (label) {
                    return { label: label, action: function () { sendMessage(label); } };
                }));
            }
            if (typeof onDone === 'function') onDone(text);
        })
        .catch(function () {
            hideTyping();
            addBotBubble('Sorry, I could not reach the server. Please check your connection.');
        });
    }

    /* ── Booking & recommendation state ──────────────────────── */
    var pendingBookingData  = null;
    var recommendedTreatment = null; // set when AI outputs recommend_treatment

    // Inject card styles once
    (function () {
        if (document.getElementById('detabot-booking-styles')) return;
        var s = document.createElement('style');
        s.id = 'detabot-booking-styles';
        s.textContent = [
            // Booking confirmation card
            '.cb-confirm-card{background:#f5f0ff;border:1.5px solid #c4b2f0;border-radius:12px;padding:14px 16px;font-size:13px;color:#1a0e2e;line-height:1.5;margin:4px 0}',
            '.cb-confirm-card h4{font-size:12px;font-weight:700;color:#7c3aed;margin:0 0 10px;letter-spacing:.05em;text-transform:uppercase}',
            '.cb-confirm-card .cb-row{display:flex;gap:6px;padding:5px 0;border-bottom:1px solid #e0d5f5}',
            '.cb-confirm-card .cb-row:last-child{border-bottom:none}',
            '.cb-confirm-card .cb-icon{width:16px;flex-shrink:0}',
            '.cb-confirm-card .cb-lbl{color:#72647a;width:72px;flex-shrink:0;font-size:12px}',
            '.cb-confirm-card .cb-val{font-weight:600;flex:1}',
            '.cb-confirm-card .cb-price{text-align:center;background:#7c3aed;color:#fff;border-radius:8px;padding:8px;margin-top:10px;font-weight:700;font-size:15px}',
            // Recommendation card
            '.cb-rec-card{background:#eaf7f0;border:1.5px solid #a8dfc4;border-radius:12px;padding:14px 16px;font-size:13px;color:#1a0e2e;margin:4px 0}',
            '.cb-rec-card h4{font-size:12px;font-weight:700;color:#16845c;margin:0 0 8px;letter-spacing:.05em;text-transform:uppercase}',
            '.cb-rec-name{font-size:15px;font-weight:700;color:#1a0e2e;margin-bottom:5px}',
            '.cb-rec-reason{font-size:12.5px;color:#3d7a5c;margin-bottom:8px;line-height:1.45}',
            '.cb-rec-meta{display:flex;gap:12px;font-size:12px;font-weight:600;color:#16845c;margin-bottom:8px}',
            '.cb-rec-note{font-size:11.5px;color:#72647a;font-style:italic;border-top:1px solid #c6e8d5;padding-top:7px;margin-top:4px}',
        ].join('');
        document.head.appendChild(s);
    }());

    var SERVICE_PRICES = {
        'Dental Consultation': 30, 'Dental X-Ray': 50, 'Tooth Extraction': 80,
        'Tooth Filling': 60, 'Scaling / Cleaning': 70, 'Root Canal Treatment': 350,
        'Teeth Whitening': 400, 'Braces Consultation': 50, 'Crown': 500,
        'Bridge': 600, 'Dentures': 200,
    };

    function getServicePrice(name) {
        for (var k in SERVICE_PRICES) {
            if (name && name.toLowerCase().indexOf(k.toLowerCase()) !== -1) return SERVICE_PRICES[k];
        }
        return null;
    }

    function fmt12(hhmm) {
        var parts = (hhmm || '').split(':');
        var h = parseInt(parts[0], 10);
        var m = parts[1] || '00';
        var ampm = h >= 12 ? 'PM' : 'AM';
        return (h % 12 || 12) + ':' + m + ' ' + ampm;
    }

    function fmtDate(ymd) {
        var d = new Date(ymd + 'T00:00:00');
        return d.toLocaleDateString('en-MY', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    }

    function handleBookingAction(data) {
        pendingBookingData = data.data;
        var d = data.data;
        var price = getServicePrice(d.serviceType);
        var priceStr = price != null ? 'RM ' + price : '—';

        var el = document.createElement('div');
        el.className = 'chatbot-bubble';
        el.innerHTML =
            '<div class="cb-confirm-card">' +
            '<h4>📋 Appointment Summary</h4>' +
            '<div class="cb-row"><span class="cb-icon">🦷</span><span class="cb-lbl">Treatment</span><span class="cb-val">' + esc(d.serviceType) + '</span></div>' +
            '<div class="cb-row"><span class="cb-icon">👨‍⚕️</span><span class="cb-lbl">Dentist</span><span class="cb-val">' + esc(d.dentistName) + '</span></div>' +
            '<div class="cb-row"><span class="cb-icon">📅</span><span class="cb-lbl">Date</span><span class="cb-val">' + esc(fmtDate(d.appointmentDate)) + '</span></div>' +
            '<div class="cb-row"><span class="cb-icon">⏰</span><span class="cb-lbl">Time</span><span class="cb-val">' + esc(fmt12(d.appointmentTime)) + '</span></div>' +
            (d.healthProblemDetail ? '<div class="cb-row"><span class="cb-icon">💊</span><span class="cb-lbl">Concern</span><span class="cb-val">' + esc(d.healthProblemDetail) + '</span></div>' : '') +
            '<div class="cb-price">💰 ' + esc(priceStr) + '</div>' +
            '</div>';
        body.appendChild(el);
        body.scrollTop = body.scrollHeight;

        // Show confirm + change buttons
        addQuickReplies([
            { label: '✅ Confirm Booking',  action: confirmAiBooking },
            { label: '✏️ Change Details',   action: function () {
                pendingBookingData = null;
                addUserBubble('I want to change the details');
                callBot('I want to change the booking details. Please start over.', null);
            }},
        ]);
    }

    /* ── Treatment recommendation card ───────────────────────── */
    function handleRecommendationAction(data) {
        // Store so health details carry into the final booking
        recommendedTreatment = {
            serviceType:    data.treatment     || '',
            price:          data.price         || 0,
            duration:       data.duration      || 30,
            healthCategory: data.healthCategory || 'common',
            healthDetail:   data.healthDetail   || '',
        };

        // Show the recommendation bubble
        addBotBubble(data.reply || ('I recommend ' + data.treatment + '.'));

        // Show styled recommendation card
        var el = document.createElement('div');
        el.className = 'chatbot-bubble';
        el.innerHTML =
            '<div class="cb-rec-card">' +
            '<h4>💡 Recommended Treatment</h4>' +
            '<div class="cb-rec-name">' + esc(data.treatment || '') + '</div>' +
            '<div class="cb-rec-reason">' + esc(data.reason || '') + '</div>' +
            '<div class="cb-rec-meta">' +
            '<span>💰 RM ' + (data.price || '—') + '</span>' +
            '<span>⏱ ' + (data.duration || '—') + ' mins</span>' +
            '</div>' +
            '<div class="cb-rec-note">The dentist will confirm the exact treatment during your visit.</div>' +
            '</div>';
        body.appendChild(el);
        body.scrollTop = body.scrollHeight;

        // Action buttons
        var treatName = data.treatment || 'this treatment';
        addQuickReplies([
            {
                label: '✅ Yes, book this treatment',
                action: function () {
                    addUserBubble('Yes, book ' + treatName);
                    callBot('Yes, I want to book ' + treatName + '. Please help me choose a dentist, date, and time.', null);
                },
            },
            {
                label: '🔄 Suggest another option',
                action: function () {
                    recommendedTreatment = null;
                    addUserBubble('Suggest another option');
                    callBot('Please suggest an alternative treatment for my condition.', null);
                },
            },
        ]);
    }

    function confirmAiBooking() {
        if (!pendingBookingData) return;
        var d = pendingBookingData;
        addUserBubble('✅ Confirm Booking');
        showTyping();

        // Merge health details from recommendation if AI didn't carry them into book_appointment
        var rec    = recommendedTreatment || {};
        var hCat   = d.healthProblemCategory || rec.healthCategory || 'none';
        var hDetail = d.healthProblemDetail  || rec.healthDetail   || '';

        fetch('book_appointment.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                _csrf_token:           window.DETABOT_CSRF || '',
                serviceType:           d.serviceType,
                dentistName:           d.dentistName,
                appointmentDate:       d.appointmentDate,
                appointmentTime:       d.appointmentTime,
                patientAge:            window.DETABOT_USER_AGE || 0,
                healthProblemCategory: hCat,
                healthProblemDetail:   hDetail,
                notes:                 'Booked via Detabot AI. ' + (d.notes || ''),
            }),
        })
        .then(function (r) { return r.json(); })
        .then(function (result) {
            hideTyping();
            pendingBookingData   = null;
            recommendedTreatment = null;
            if (result.success) {
                addBotBubble(
                    '🎉 Done! Your appointment is booked for ' +
                    fmtDate(d.appointmentDate) + ' at ' + fmt12(d.appointmentTime) +
                    '. Reference #' + result.appointmentID + '. See you then! 🦷'
                );
                addQuickReplies([
                    { label: '📋 View My Appointments', action: function () { window.location.reload(); } },
                ]);
            } else {
                var errMsg = result.error || 'Something went wrong.';
                addBotBubble('Oops! ' + errMsg + ' Would you like to choose a different time or date?');
                addQuickReplies([
                    { label: '🔄 Try Again', action: function () { sendMessage('I want to book an appointment'); } },
                ]);
            }
        })
        .catch(function () {
            hideTyping();
            pendingBookingData = null;
            addBotBubble('Sorry, I could not complete the booking. Please try the form below or contact the clinic.');
        });
    }

    /* ── Open / close ─────────────────────────────────────────── */
    function openChat() {
        if (isOpen) return;
        isOpen = true;
        win.classList.add('is-open');
        win.setAttribute('aria-hidden', 'false');
        inp.focus();
        if (isLoginPage && !loginGreetDone) {
            loginGreetDone = true;
            clearInitialContent();
            setTimeout(showLoginGreeting, 800);
        } else if (isHealthRecord && !loginGreetDone) {
            loginGreetDone = true;
            clearInitialContent();
            setTimeout(showHealthRecordGreeting, 500);
        }
    }

    function closeChat() {
        if (!isOpen) return;
        isOpen = false;
        win.classList.remove('is-open');
        win.setAttribute('aria-hidden', 'true');
    }

    function toggleChat() { isOpen ? closeChat() : openChat(); }

    /* ── Health-record greeting ───────────────────────────────── */
    function showHealthRecordGreeting() {
        var username = String(window.DETABOT_USERNAME || 'there');
        addBotBubble('Hi ' + username + '! 🦷 Welcome to your Health Record. How can I help you today?');
        addQuickReplies([
            { label: '🔍 My Diagnosis',      action: function () { sendMessage('What does my diagnosis mean?'); } },
            { label: '📅 Next Checkup',      action: function () { sendMessage('When is my next checkup?'); } },
            { label: '🪥 After Treatment',   action: function () { sendMessage('How do I care for my teeth after treatment?'); } },
            { label: '📋 Book Follow-up',    action: function () { sendMessage('Book a follow-up appointment'); } },
        ]);
    }

    /* ── Login-page greeting ──────────────────────────────────── */
    function showLoginGreeting() {
        addBotBubble("Welcome to Putra Dental Clinic! 👋 I'm Detabot, your AI dental assistant. Do you already have an account?");
        addQuickReplies([
            {
                label: '✅ Yes, I have an account',
                action: function () {
                    addUserBubble('Yes, I have an account');
                    setTimeout(function () {
                        addBotBubble("Great! Please enter your email and password in the form to log in. I'm here if you need any help! 😊");
                    }, 600);
                }
            },
            {
                label: "❌ No, I don't have an account",
                action: function () {
                    addUserBubble("No, I don't have an account");
                    setTimeout(askHowToRegister, 500);
                }
            }
        ]);
    }

    /* ── Registration entry-point: offer chat or form ────────────── */
    function askHowToRegister() {
        addBotBubble("Great! How would you like to create your account? 😊");
        addQuickReplies([
            {
                label: '💬 Register via chat',
                action: function () {
                    addUserBubble('Register via chat');
                    setTimeout(startRegistrationFlow, 400);
                }
            },
            {
                label: '📝 Open registration form',
                action: function () {
                    addUserBubble('Open registration form');
                    addBotBubble('Taking you to the registration form now…');
                    setTimeout(function () { window.location.href = 'register.php'; }, 1200);
                }
            }
        ]);
    }

    /* ================================================================
       REGISTRATION FLOW
       ================================================================ */

    function startRegistrationFlow() {
        reg.active = true;
        reg.step   = 1;
        reg.data   = {};
        setInputText();
        addBotBubble("No problem! Let me help you create your account. 😊 First, what is your full name?");
    }

    function regAsk(step) {
        reg.step = step;
        switch (step) {
            case 2: addBotBubble('Nice to meet you, ' + reg.data.name + '! 👋 What is your email address?'); break;
            case 3: addBotBubble('Got it! What is your phone number?'); break;
            case 4: addBotBubble('How old are you?'); break;
            case 5:
                addBotBubble('What is your gender?');
                addQuickReplies([
                    { label: 'Male',   action: function () { regSelectGender('male');   } },
                    { label: 'Female', action: function () { regSelectGender('female'); } }
                ]);
                break;
            case 6: addBotBubble('Almost done! Please create a password for your account.'); setInputPassword(); break;
            case 7: addBotBubble('Please confirm your password.'); break;
            case 8: setInputText(); showRegSummary(); break;
        }
    }

    function regSelectGender(gender) {
        addUserBubble(gender.charAt(0).toUpperCase() + gender.slice(1));
        reg.data.gender = gender;
        regAsk(6);
    }

    function handleRegistrationInput(text) {
        switch (reg.step) {
            case 1:
                if (text.length < 3 || !/^[a-zA-Z\s]+$/.test(text)) {
                    addBotBubble('Please enter a valid full name (letters only, minimum 3 characters).');
                    return;
                }
                reg.data.name = text;
                regAsk(2);
                break;

            case 2:
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(text)) {
                    addBotBubble('Please enter a valid email address. Example: yourname@gmail.com');
                    return;
                }
                reg.data.email = text.toLowerCase();
                regAsk(3);
                break;

            case 3:
                if (text.replace(/\D/g, '').length < 10) {
                    addBotBubble('Please enter a valid Malaysian phone number. Example: 0123456789');
                    return;
                }
                reg.data.phone = text.trim();
                regAsk(4);
                break;

            case 4:
                var age = parseInt(text, 10);
                if (isNaN(age) || age < 1 || age > 120) { addBotBubble('Please enter a valid age.'); return; }
                reg.data.age = age;
                regAsk(5);
                break;

            case 5:
                var gl = text.toLowerCase().trim();
                if (gl === 'male' || gl === 'female') {
                    removeQuickReplies();
                    regSelectGender(gl);
                } else {
                    addBotBubble('Please select your gender using the buttons below.');
                    addQuickReplies([
                        { label: 'Male',   action: function () { regSelectGender('male');   } },
                        { label: 'Female', action: function () { regSelectGender('female'); } }
                    ]);
                }
                break;

            case 6:
                if (text.length < 8 || !/\d/.test(text)) {
                    addBotBubble('Password must be at least 8 characters and contain at least 1 number. Please try again.');
                    return;
                }
                reg.data.password = text;
                regAsk(7);
                break;

            case 7:
                if (text !== reg.data.password) {
                    addBotBubble('Passwords do not match. Please try again.');
                    reg.step = 6;
                    setTimeout(function () { addBotBubble('Almost done! Please create a password for your account.'); setInputPassword(); }, 400);
                    return;
                }
                regAsk(8);
                break;

            case 8:
                addBotBubble('Please use the buttons above to confirm or edit your details.');
                showRegSummary();
                break;
        }
    }

    function showRegSummary() {
        var d = reg.data;
        var gender = d.gender ? (d.gender.charAt(0).toUpperCase() + d.gender.slice(1)) : '—';
        var rows = [
            ['👤', 'Name',     d.name  || '—'],
            ['📧', 'Email',    d.email || '—'],
            ['📞', 'Phone',    d.phone || '—'],
            ['🎂', 'Age',      d.age   || '—'],
            ['👥', 'Gender',   gender],
            ['🔒', 'Password', '••••••••'],
        ];
        var el = document.createElement('div');
        el.className = 'chatbot-bubble chatbot-reg-summary';
        el.innerHTML = '<p class="reg-title">Please review your details before we proceed:</p>' +
            rows.map(function (r) {
                return '<div class="reg-row"><span class="reg-icon">' + r[0] + '</span>' +
                    '<span class="reg-label">' + esc(r[1]) + '</span>' +
                    '<span class="reg-value">' + esc(String(r[2])) + '</span></div>';
            }).join('');
        body.appendChild(el);
        body.scrollTop = body.scrollHeight;
        addQuickReplies([
            { label: '✅ Confirm & Register', action: submitRegistration },
            { label: '✏️ Edit Details',       action: function () { reg.data = {}; startRegistrationFlow(); } }
        ]);
    }

    function submitRegistration() {
        var d = reg.data;
        showTyping();
        fetch('register_handler.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                username: d.name, userEmail: d.email, userPhone: d.phone,
                userAge: d.age, userGender: d.gender, userPassword: d.password,
                userRole: 'patient', status: 'active',
            }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            hideTyping();
            if (data.success) {
                reg.active = false;
                addBotBubble('🎉 Your account has been created successfully, ' + d.name + '! Welcome to Putra Dental Clinic!');
                setTimeout(function () {
                    addBotBubble('Redirecting you to the login page now... 🦷');
                    setTimeout(function () { window.location.href = 'login.php'; }, 2000);
                }, 1000);
            } else if (data.message === 'Email already exists') {
                addBotBubble('Oops! That email is already registered. Please use a different email address.');
                reg.step = 2;
                setTimeout(function () { addBotBubble('What is your email address?'); }, 600);
            } else {
                addBotBubble('Something went wrong. Please try again or contact the clinic at 07-453 8899.');
            }
        })
        .catch(function () {
            hideTyping();
            addBotBubble('Something went wrong. Please try again or contact the clinic at 07-453 8899.');
        });
    }

    /* ── Keyword matchers (bilingual) ─────────────────────────── */
    var KW_REGISTER = ['register', 'sign up', 'new account', 'create account', 'signup', 'daftar', 'akaun baru'];
    var KW_LOGIN    = ['login', 'log in', 'sign in', 'signin', 'masuk', 'log masuk'];
    var KW_DENTAL   = ['toothache', 'tooth', 'teeth', 'gum', 'cavity', 'decay', 'filling', 'crown',
                       'braces', 'scaling', 'whitening', 'root canal', 'veneer', 'extraction',
                       'retainer', 'denture', 'dental', 'mouth', 'jaw',
                       'sakit gigi', 'gigi', 'gusi', 'cabut', 'tampal', 'karies', 'bracket',
                       'gigi palsu', 'cuci gigi', 'rawatan'];

    function match(kws, lower) { return kws.some(function (k) { return lower.indexOf(k) !== -1; }); }

    /* ── Smart login-page message routing ─────────────────────── */
    function handleLoginMessage(text) {
        var lower = text.toLowerCase();
        if (match(KW_REGISTER, lower)) {
            addUserBubble(text);
            setTimeout(askHowToRegister, 400);
            return;
        }
        if (match(KW_LOGIN, lower)) {
            addUserBubble(text);
            setTimeout(function () {
                addBotBubble("Please fill in your email and password in the login form. I'm here if you need help! 😊");
            }, 300);
            return;
        }
        if (match(KW_DENTAL, lower)) {
            addUserBubble(text);
            callBot(text, function () {
                setTimeout(function () { addBotBubble('Please log in first to make an appointment! 😊'); }, 800);
            });
            return;
        }
        addUserBubble(text);
        callBot(text, null);
    }

    /* ── Send ─────────────────────────────────────────────────── */
    function sendMessage(text) {
        text = (text || inp.value).trim();
        if (!text || botBusy) return;
        inp.value = '';
        if (reg.active) {
            var masked = (reg.step === 6 || reg.step === 7);
            if (reg.step !== 5) removeQuickReplies();
            addUserBubble(masked ? '••••••••' : text);
            handleRegistrationInput(text);
            return;
        }
        removeQuickReplies();
        if (isLoginPage) { handleLoginMessage(text); }
        else             { addUserBubble(text); callBot(text, null); }
    }

    /* ── Form error helper (login.php) ────────────────────────── */
    function showFormError(msg) {
        var el = document.getElementById('loginError');
        if (el) { el.textContent = msg; el.style.display = 'block'; }
    }

    function hideFormError() {
        var el = document.getElementById('loginError');
        if (el) el.style.display = 'none';
    }

    /* ── Login-page wiring ────────────────────────────────────── */
    if (isLoginPage) {

        /* --- login.php: JSON fetch to auth.php ─────────────── */
        if (isNewLoginPage) {
            var newLoginForm = document.getElementById('loginForm');
            if (newLoginForm) {
                newLoginForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var emailVal = (document.getElementById('loginEmail')    || {}).value || '';
                    var passVal  = (document.getElementById('loginPassword') || {}).value || '';
                    var submitBtn = document.getElementById('loginSubmitBtn');

                    if (!emailVal.trim() || !passVal) {
                        showFormError('Please fill in all fields.');
                        return;
                    }
                    hideFormError();
                    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Signing in…'; }

                    fetch('auth.php', {
                        method:      'POST',
                        headers:     { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body:        JSON.stringify({ userEmail: emailVal.trim(), userPassword: passVal }),
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Login'; }
                        if (data.success) {
                            /* Open chat + show welcome messages then redirect */
                            openChat();
                            setTimeout(function () {
                                clearInitialContent();
                                removeQuickReplies();
                                loginGreetDone = true;
                            }, 200);
                            setTimeout(function () {
                                addBotBubble('Welcome back, ' + data.username + '! 🦷 Great to see you again at Putra Dental Clinic.');
                            }, 500);
                            setTimeout(function () {
                                addBotBubble('Let me take you to your Health Record now. Stay healthy! 😊');
                            }, 1500);
                            setTimeout(function () {
                                window.location.href = data.redirect || 'health_record.php';
                            }, 3500);
                        } else {
                            showFormError(data.message || 'Login failed. Please try again.');
                        }
                    })
                    .catch(function () {
                        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Login'; }
                        showFormError('Connection error. Please try again.');
                    });
                });
            }
        }

        /* --- index.php (legacy): FormData fetch to current page  */
        if (isLegacyLogin) {
            var loginActionInput = document.querySelector('form input[name="action"][value="login"]');
            var loginForm        = loginActionInput ? loginActionInput.closest('form') : null;
            if (loginForm) {
                loginForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var fd = new FormData(loginForm);
                    fetch(window.location.href, {
                        method: 'POST', body: fd, redirect: 'follow', credentials: 'same-origin',
                    })
                    .then(function (res) {
                        var finalUrl = res.url || '';
                        if (finalUrl.indexOf('dashboard') !== -1) {
                            return res.text().then(function (html) {
                                var m = html.match(/class="topbar-username"[^>]*>\s*([^<]+)\s*</);
                                var m2 = m || html.match(/class="sb-user-name"[^>]*>\s*([^<]+)\s*</);
                                var username = (m2 && m2[1]) ? m2[1].trim() : '';
                                openChat();
                                setTimeout(function () {
                                    clearInitialContent(); removeQuickReplies(); loginGreetDone = true;
                                    addBotBubble((username ? 'Welcome back, ' + username + '!' : 'Welcome back!') + ' 🦷 Let\'s check your dental health records.');
                                    setTimeout(function () { window.location.href = finalUrl; }, 1200);
                                }, 300);
                            });
                        } else {
                            openChat();
                            setTimeout(function () {
                                if (!loginGreetDone) { loginGreetDone = true; clearInitialContent(); }
                                removeQuickReplies();
                                addBotBubble('Invalid email or password. Please double-check and try again. 😊');
                            }, 300);
                        }
                    })
                    .catch(function () { loginForm.submit(); });
                });
            }
        }

        /* --- "Create Account" link → offer chat or form ── */
        var registerLink = document.querySelector('a.auth-outline-btn[href*="register"]');
        if (registerLink) {
            registerLink.addEventListener('click', function (e) {
                e.preventDefault();
                loginGreetDone = true;
                openChat();
                setTimeout(function () {
                    clearInitialContent(); removeQuickReplies();
                    askHowToRegister();
                }, 400);
            });
        }
    }

    /* ── External trigger: detabotAsk(msg) from any page ─────── */
    document.addEventListener('detabot:ask', function (e) {
        var msg = String(e.detail || '').trim();
        if (!msg) return;
        var wasOpen = isOpen;
        loginGreetDone = true; // skip greeting when triggered externally
        if (!isOpen) openChat();
        setTimeout(function () {
            if (!wasOpen) { clearInitialContent(); removeQuickReplies(); }
            addUserBubble(msg);
            callBot(msg, null);
        }, wasOpen ? 0 : 450);
    });

    /* ── Static quick-reply buttons (non-login, PHP-rendered) ─── */
    if (!isLoginPage) {
        body.querySelectorAll('.chatbot-quick-btn[data-msg]').forEach(function (qb) {
            qb.addEventListener('click', function () { sendMessage(qb.dataset.msg); });
        });
    }

    /* ── Core events ──────────────────────────────────────────── */
    btn.addEventListener('click', toggleChat);
    closeBtn.addEventListener('click', closeChat);
    send.addEventListener('click', function () { sendMessage(); });
    inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') sendMessage(); });

})();
