<?php
declare(strict_types=1);

function sp_time_ago(string $dateStr): string
{
    $ts = strtotime($dateStr);
    if ($ts === false || $ts === 0) return '—';
    $diff = time() - $ts;
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return (int) ($diff / 60) . 'm ago';
    if ($diff < 86400)  return (int) ($diff / 3600) . 'h ago';
    if ($diff < 172800) return 'Yesterday';
    return date('d M Y', $ts);
}

function sp_activity_icon(string $action): string
{
    return match (true) {
        str_contains($action, 'login')       => '🔑',
        str_contains($action, 'logout')      => '🚪',
        str_contains($action, 'password')    => '🔒',
        str_contains($action, 'avatar')      => '🖼️',
        str_contains($action, 'profile')     => '👤',
        str_contains($action, 'report')      => '📄',
        str_contains($action, 'reward')      => '🎁',
        str_contains($action, 'clinic')      => '🏥',
        str_contains($action, 'appointment') => '📅',
        str_contains($action, 'feedback')    => '💬',
        str_contains($action, 'patient')     => '👥',
        str_contains($action, 'record')      => '🦷',
        default                              => '📋',
    };
}

function page_staff_profile(array $user): void
{
    $uid = (int) $user['userID'];

    $recentActivity = db_all(
        'SELECT * FROM tbl_activity_log WHERE userID = ? ORDER BY createdDate DESC LIMIT 8',
        [$uid]
    );

    $avatarUrl = user_avatar_url($user);
    $initials  = strtoupper(substr(trim((string) ($user['username'] ?? 'U')), 0, 2));
    $roleLabel = ucfirst((string) ($user['userRole'] ?? 'staff'));
    ?>
<style>
/* ── Staff Profile (sp-) ─────────────────────────────────────────── */

/* Hero banner */
.sp-hero{background:linear-gradient(135deg,#1a0e2e 0%,#3b0764 55%,#5b21b6 100%);border-radius:16px;padding:32px 28px;display:flex;align-items:center;gap:24px;margin-bottom:22px;flex-wrap:wrap;box-shadow:0 4px 24px rgba(59,7,100,.25)}
.sp-hero-avatar-wrap{position:relative;flex-shrink:0}
.sp-hero-avatar{width:96px;height:96px;border-radius:50%;border:3px solid rgba(255,255,255,.3);background:linear-gradient(135deg,#7c3aed,#a855f7);display:flex;align-items:center;justify-content:center;font-family:'Sora',sans-serif;font-size:28px;font-weight:700;color:#fff;overflow:hidden;flex-shrink:0}
.sp-hero-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;display:block}
.sp-hero-cam-btn{position:absolute;bottom:2px;right:2px;width:30px;height:30px;background:#7c3aed;border-radius:50%;border:2px solid #fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background .15s;box-shadow:0 2px 8px rgba(0,0,0,.25)}
.sp-hero-cam-btn:hover{background:#5b21b6}
.sp-hero-cam-btn svg{width:13px;height:13px;stroke:#fff;display:block}
.sp-hero-info{flex:1;min-width:180px}
.sp-hero-name{font-family:'Sora',sans-serif;font-size:22px;font-weight:700;color:#fff;margin:0 0 4px}
.sp-hero-email{font-size:13.5px;color:rgba(255,255,255,.72);margin:0 0 12px}
.sp-hero-badges{display:flex;gap:8px;flex-wrap:wrap}
.sp-badge-role{display:inline-flex;align-items:center;gap:5px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);color:#e0d0ff;border-radius:100px;padding:4px 12px;font-size:12px;font-weight:600}
.sp-badge-active{display:inline-flex;align-items:center;gap:5px;background:rgba(22,132,92,.3);border:1px solid rgba(22,132,92,.5);color:#6ee7b7;border-radius:100px;padding:4px 12px;font-size:12px;font-weight:600}
.sp-badge-active::before{content:'';display:inline-block;width:6px;height:6px;background:#34d399;border-radius:50%}

/* Two-column grid */
.sp-grid{display:grid;grid-template-columns:1.55fr 1fr;gap:18px;align-items:start}
@media(max-width:880px){.sp-grid{grid-template-columns:1fr}}
.sp-right-stack{display:flex;flex-direction:column;gap:18px}

/* Cards */
.sp-card{background:#fff;border:1px solid #ede8f8;border-radius:14px;box-shadow:0 2px 8px rgba(59,7,100,.05);overflow:hidden}
.sp-card-head{display:flex;align-items:center;gap:10px;padding:16px 20px 14px;border-bottom:1px solid #f0ebf8}
.sp-card-head-icon{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.sp-card-head-icon.purple{background:#f3f0ff}
.sp-card-head-icon.amber{background:#fff8e6}
.sp-card-head-icon.blue{background:#e8f4fd}
.sp-card-head h3{font-family:'Sora',sans-serif;font-size:14px;font-weight:700;color:#1a0e2e;margin:0}
.sp-card-head p{font-size:12px;color:#72647a;margin:3px 0 0}
.sp-card-body{padding:20px}

/* Form fields */
.sp-field{display:flex;flex-direction:column;gap:5px;margin-bottom:14px}
.sp-field:last-of-type{margin-bottom:0}
.sp-label{font-size:12.5px;font-weight:600;color:#4a3351}
.sp-input{width:100%;padding:9px 12px;border:1.5px solid #ddd5f0;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13.5px;color:#1a0e2e;background:#fff;outline:none;box-sizing:border-box;transition:border-color .15s}
.sp-input:focus{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.1)}
.sp-input:disabled{background:#f8f5ff;color:#72647a;cursor:not-allowed;border-color:#e5ddf5}
.sp-input-wrap{position:relative}
.sp-input-wrap .sp-input{padding-right:42px}
.sp-eye-btn{position:absolute;right:11px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#7c3aed;padding:2px;line-height:1;display:flex;align-items:center}
.sp-eye-btn svg{width:16px;height:16px}
.sp-hint{font-size:11.5px;color:#72647a;margin-top:3px}

/* Submit button */
.sp-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:10px 22px;border-radius:9px;font-family:'DM Sans',sans-serif;font-size:13.5px;font-weight:700;border:none;cursor:pointer;transition:all .15s;line-height:1}
.sp-btn.primary{background:linear-gradient(135deg,#5b21b6,#7c3aed);color:#fff;box-shadow:0 2px 8px rgba(124,58,237,.3)}
.sp-btn.primary:hover{background:linear-gradient(135deg,#4c1d95,#6d28d9);box-shadow:0 4px 14px rgba(124,58,237,.4)}
.sp-btn.primary:disabled{opacity:.6;cursor:not-allowed}
.sp-btn-row{display:flex;align-items:center;gap:10px;margin-top:18px}

/* Inline alert */
.sp-alert{display:none;padding:9px 14px;border-radius:8px;font-size:13px;font-weight:600;margin-top:14px}
.sp-alert.success{background:#eaf3de;color:#16845c;border:1px solid #b2ddb5}
.sp-alert.error{background:#fcebeb;color:#b42318;border:1px solid #f0b8b8}

/* Password strength meter */
.sp-strength-bar{height:4px;border-radius:2px;background:#e5ddf5;margin-top:6px;overflow:hidden}
.sp-strength-fill{height:100%;border-radius:2px;transition:width .3s,background .3s;width:0}

/* Activity list */
.sp-activity-list{display:flex;flex-direction:column}
.sp-activity-item{display:flex;align-items:flex-start;gap:12px;padding:11px 0;border-bottom:1px solid #f4f0fb}
.sp-activity-item:last-child{border-bottom:none;padding-bottom:0}
.sp-activity-icon{width:34px;height:34px;border-radius:9px;background:#f3f0ff;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.sp-activity-body{flex:1;min-width:0}
.sp-activity-action{font-size:13px;font-weight:600;color:#1a0e2e;text-transform:capitalize;margin-bottom:2px}
.sp-activity-detail{font-size:12px;color:#72647a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sp-activity-time{font-size:11.5px;color:#a38bc4;flex-shrink:0;margin-top:2px;white-space:nowrap}
.sp-activity-empty{text-align:center;padding:20px;color:#72647a;font-size:13px}

/* Upload progress overlay */
.sp-avatar-uploading{position:absolute;inset:0;border-radius:50%;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center}
.sp-avatar-uploading span{color:#fff;font-size:11px;font-weight:700}
</style>

<!-- Hero Banner -->
<div class="sp-hero">
    <div class="sp-hero-avatar-wrap">
        <div class="sp-hero-avatar" id="spAvatarWrap">
            <?php if ($avatarUrl): ?>
                <img src="<?= e($avatarUrl) ?>" alt="" id="spAvatarImg">
            <?php else: ?>
                <span id="spAvatarInitials"><?= e($initials) ?></span>
            <?php endif; ?>
            <div class="sp-avatar-uploading" id="spAvatarUploading"><span>…</span></div>
        </div>
        <label class="sp-hero-cam-btn" for="spAvatarInput" title="Change photo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        </label>
        <input type="file" id="spAvatarInput" accept="image/jpeg,image/png,image/webp" style="display:none">
    </div>
    <div class="sp-hero-info">
        <h2 class="sp-hero-name" id="spHeroName"><?= e($user['username']) ?></h2>
        <p class="sp-hero-email"><?= e($user['userEmail']) ?></p>
        <div class="sp-hero-badges">
            <span class="sp-badge-role">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:12px;height:12px"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                <?= e($roleLabel) ?> Account
            </span>
            <span class="sp-badge-active">Active</span>
        </div>
    </div>
</div>

<!-- Two-column grid -->
<div class="sp-grid">

    <!-- LEFT: Account Details -->
    <div class="sp-card">
        <div class="sp-card-head">
            <div class="sp-card-head-icon purple">👤</div>
            <div>
                <h3>Account Details</h3>
                <p>Update your name, email and phone number</p>
            </div>
        </div>
        <div class="sp-card-body">
            <div class="sp-field">
                <label class="sp-label" for="spName">Full Name</label>
                <input class="sp-input" id="spName" type="text" value="<?= e($user['username']) ?>" maxlength="50" autocomplete="name" placeholder="Your full name">
            </div>
            <div class="sp-field">
                <label class="sp-label" for="spEmail">Email Address</label>
                <input class="sp-input" id="spEmail" type="email" value="<?= e($user['userEmail']) ?>" maxlength="100" autocomplete="email" placeholder="you@example.com">
            </div>
            <div class="sp-field">
                <label class="sp-label" for="spPhone">Phone Number</label>
                <input class="sp-input" id="spPhone" type="tel" value="<?= e($user['userPhone']) ?>" maxlength="20" autocomplete="tel" placeholder="e.g. 011-1234 5678">
            </div>
            <div class="sp-field">
                <label class="sp-label">Role</label>
                <input class="sp-input" type="text" value="<?= e($roleLabel) ?>" disabled>
            </div>
            <div class="sp-btn-row">
                <button class="sp-btn primary" id="spSaveDetailsBtn" onclick="spSaveDetails()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    Save Details
                </button>
            </div>
            <div class="sp-alert" id="spDetailsAlert"></div>
        </div>
    </div>

    <!-- RIGHT: stacked -->
    <div class="sp-right-stack">

        <!-- Change Password -->
        <div class="sp-card">
            <div class="sp-card-head">
                <div class="sp-card-head-icon amber">🔒</div>
                <div>
                    <h3>Change Password</h3>
                    <p>Min 8 characters, at least 1 number</p>
                </div>
            </div>
            <div class="sp-card-body">
                <div class="sp-field">
                    <label class="sp-label" for="spCurPwd">Current Password</label>
                    <div class="sp-input-wrap">
                        <input class="sp-input" id="spCurPwd" type="password" autocomplete="current-password" placeholder="Enter current password">
                        <button type="button" class="sp-eye-btn" onclick="spTogglePwd('spCurPwd',this)" title="Show/Hide">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>
                <div class="sp-field">
                    <label class="sp-label" for="spNewPwd">New Password</label>
                    <div class="sp-input-wrap">
                        <input class="sp-input" id="spNewPwd" type="password" autocomplete="new-password" placeholder="Min 8 chars, 1 number" oninput="spCheckStrength(this.value)">
                        <button type="button" class="sp-eye-btn" onclick="spTogglePwd('spNewPwd',this)" title="Show/Hide">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="sp-strength-bar"><div class="sp-strength-fill" id="spStrengthFill"></div></div>
                    <span class="sp-hint" id="spStrengthHint"></span>
                </div>
                <div class="sp-field">
                    <label class="sp-label" for="spConfPwd">Confirm New Password</label>
                    <div class="sp-input-wrap">
                        <input class="sp-input" id="spConfPwd" type="password" autocomplete="new-password" placeholder="Repeat new password">
                        <button type="button" class="sp-eye-btn" onclick="spTogglePwd('spConfPwd',this)" title="Show/Hide">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>
                <div class="sp-btn-row">
                    <button class="sp-btn primary" id="spPwdBtn" onclick="spChangePassword()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Update Password
                    </button>
                </div>
                <div class="sp-alert" id="spPwdAlert"></div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="sp-card">
            <div class="sp-card-head">
                <div class="sp-card-head-icon blue">📋</div>
                <div>
                    <h3>Recent Activity</h3>
                    <p>Your last 8 actions in the system</p>
                </div>
            </div>
            <div class="sp-card-body" style="padding:14px 20px">
                <?php if (empty($recentActivity)): ?>
                    <div class="sp-activity-empty">No activity recorded yet.</div>
                <?php else: ?>
                <div class="sp-activity-list">
                    <?php foreach ($recentActivity as $log):
                        $actionKey = (string) ($log['action'] ?? '');
                        $details   = (string) ($log['details'] ?? '');
                        $date      = (string) ($log['createdDate'] ?? '');
                        $icon      = sp_activity_icon($actionKey);
                        $label     = ucwords(str_replace(['_', '-'], ' ', $actionKey));
                    ?>
                    <div class="sp-activity-item">
                        <div class="sp-activity-icon"><?= $icon ?></div>
                        <div class="sp-activity-body">
                            <div class="sp-activity-action"><?= e($label) ?></div>
                            <?php if ($details !== ''): ?>
                                <div class="sp-activity-detail"><?= e($details) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="sp-activity-time"><?= e(sp_time_ago($date)) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /.sp-right-stack -->
</div><!-- /.sp-grid -->

<script>
window.SP_USER_ID   = <?= $uid ?>;
window.SP_USER_ROLE = <?= json_encode($user['userRole']) ?>;

/* ── Show / hide alert ── */
function spShowAlert(id, type, msg) {
    var el = document.getElementById(id);
    if (!el) return;
    el.className = 'sp-alert ' + type;
    el.textContent = msg;
    el.style.display = 'block';
    if (type === 'success') {
        setTimeout(function () { el.style.display = 'none'; }, 4000);
    }
}

/* ── Toggle password visibility ── */
window.spTogglePwd = function (inputId, btn) {
    var inp = document.getElementById(inputId);
    if (!inp) return;
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.style.opacity = inp.type === 'text' ? '1' : '0.5';
};

/* ── Password strength ── */
window.spCheckStrength = function (val) {
    var fill = document.getElementById('spStrengthFill');
    var hint = document.getElementById('spStrengthHint');
    if (!fill || !hint) return;

    var score = 0;
    if (val.length >= 8)                     score++;
    if (/[0-9]/.test(val))                   score++;
    if (/[A-Z]/.test(val))                   score++;
    if (/[^A-Za-z0-9]/.test(val))           score++;

    var colors = ['#b42318', '#c77712', '#1686c2', '#16845c'];
    var labels = ['Weak', 'Fair', 'Good', 'Strong'];
    var pcts   = ['25%', '50%', '75%', '100%'];

    if (val.length === 0) {
        fill.style.width = '0';
        hint.textContent = '';
        return;
    }

    var i = Math.max(0, score - 1);
    fill.style.width      = pcts[i];
    fill.style.background = colors[i];
    hint.textContent      = labels[i];
    hint.style.color      = colors[i];
};

/* ── Save Details ── */
window.spSaveDetails = function () {
    var btn  = document.getElementById('spSaveDetailsBtn');
    var name  = (document.getElementById('spName').value  || '').trim();
    var email = (document.getElementById('spEmail').value || '').trim();
    var phone = (document.getElementById('spPhone').value || '').trim();

    if (!name)  { spShowAlert('spDetailsAlert', 'error', 'Full name is required.'); return; }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        spShowAlert('spDetailsAlert', 'error', 'Please enter a valid email address.'); return;
    }
    if (!phone) { spShowAlert('spDetailsAlert', 'error', 'Phone number is required.'); return; }

    btn.disabled = true;
    btn.textContent = 'Saving…';

    var fd = new FormData();
    fd.append('_csrf_token', window.DETABOT_CSRF || '');
    fd.append('userID',    window.SP_USER_ID);
    fd.append('username',  name);
    fd.append('userEmail', email);
    fd.append('userPhone', phone);

    fetch('update_staff_profile.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                spShowAlert('spDetailsAlert', 'success', d.message || 'Profile updated.');
                /* Update hero name live */
                var heroName = document.getElementById('spHeroName');
                if (heroName) heroName.textContent = name;
            } else {
                spShowAlert('spDetailsAlert', 'error', d.message || 'Failed to update profile.');
            }
        })
        .catch(function () {
            spShowAlert('spDetailsAlert', 'error', 'Network error. Please try again.');
        })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Save Details';
        });
};

/* ── Change Password ── */
window.spChangePassword = function () {
    var btn     = document.getElementById('spPwdBtn');
    var curPwd  = document.getElementById('spCurPwd').value;
    var newPwd  = document.getElementById('spNewPwd').value;
    var confPwd = document.getElementById('spConfPwd').value;

    if (!curPwd) { spShowAlert('spPwdAlert', 'error', 'Enter your current password.'); return; }
    if (newPwd.length < 8) { spShowAlert('spPwdAlert', 'error', 'New password must be at least 8 characters.'); return; }
    if (!/[0-9]/.test(newPwd)) { spShowAlert('spPwdAlert', 'error', 'New password must include at least 1 number.'); return; }
    if (newPwd !== confPwd)   { spShowAlert('spPwdAlert', 'error', 'New passwords do not match.'); return; }

    btn.disabled = true;
    btn.textContent = 'Updating…';

    var fd = new FormData();
    fd.append('_csrf_token',     window.DETABOT_CSRF || '');
    fd.append('userID',          window.SP_USER_ID);
    fd.append('currentPassword', curPwd);
    fd.append('newPassword',     newPwd);

    fetch('update_staff_password.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                spShowAlert('spPwdAlert', 'success', d.message || 'Password updated successfully.');
                document.getElementById('spCurPwd').value  = '';
                document.getElementById('spNewPwd').value  = '';
                document.getElementById('spConfPwd').value = '';
                var fill = document.getElementById('spStrengthFill');
                var hint = document.getElementById('spStrengthHint');
                if (fill) fill.style.width = '0';
                if (hint) hint.textContent = '';
            } else {
                spShowAlert('spPwdAlert', 'error', d.message || 'Failed to update password.');
            }
        })
        .catch(function () {
            spShowAlert('spPwdAlert', 'error', 'Network error. Please try again.');
        })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Update Password';
        });
};

/* ── Avatar upload ── */
(function () {
    var input     = document.getElementById('spAvatarInput');
    var wrap      = document.getElementById('spAvatarWrap');
    var overlay   = document.getElementById('spAvatarUploading');
    var initEl    = document.getElementById('spAvatarInitials');
    var imgEl     = document.getElementById('spAvatarImg');
    var sidebarAvatar = document.querySelector('.sb-user-avatar img, .sb-user-avatar');
    var topbarAvatar  = document.querySelector('.topbar-avatar');

    if (!input) return;

    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file) return;

        if (file.size > 2 * 1024 * 1024) {
            alert('Image must be under 2 MB.'); input.value = ''; return;
        }
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            alert('Only JPG, PNG, or WebP images are allowed.'); input.value = ''; return;
        }

        /* Show overlay */
        if (overlay) { overlay.style.display = 'flex'; }

        var fd = new FormData();
        fd.append('_csrf_token', window.DETABOT_CSRF || '');
        fd.append('avatar', file);

        fetch('upload_avatar.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success && d.avatarUrl) {
                    /* Replace or create img in hero */
                    if (imgEl) {
                        imgEl.src = d.avatarUrl + '?t=' + Date.now();
                    } else {
                        var img = document.createElement('img');
                        img.id  = 'spAvatarImg';
                        img.src = d.avatarUrl + '?t=' + Date.now();
                        img.alt = '';
                        if (initEl) { initEl.replaceWith(img); } else { wrap.prepend(img); }
                    }
                    /* Update sidebar and topbar avatars */
                    document.querySelectorAll('.sb-user-avatar img').forEach(function (el) {
                        el.src = d.avatarUrl + '?t=' + Date.now();
                    });
                    if (topbarAvatar && topbarAvatar.tagName === 'IMG') {
                        topbarAvatar.src = d.avatarUrl + '?t=' + Date.now();
                    }
                } else {
                    alert(d.message || 'Upload failed. Please try again.');
                }
            })
            .catch(function () { alert('Network error during upload.'); })
            .finally(function () {
                if (overlay) { overlay.style.display = 'none'; }
                input.value = '';
            });
    });
}());
</script>
    <?php
}
