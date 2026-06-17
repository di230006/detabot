<?php
declare(strict_types=1);

function page_manage_feedback(array $user): void
{
    $feedback = db_all(
        "SELECT f.feedbackID, f.userID, f.appointmentID, f.rating, f.comments,
                f.feedbackDate, f.adminResponse, f.responseDate,
                u.username, u.userAvatar,
                a.serviceType, a.appointmentDate
         FROM tbl_feedback f
         JOIN tbl_user u ON u.userID = f.userID
         JOIN tbl_appointment a ON a.appointmentID = f.appointmentID
         ORDER BY f.feedbackDate DESC",
        []
    );

    $totalReviews = count($feedback);
    $replied      = count(array_filter($feedback, fn($f) => !empty($f['adminResponse'])));
    $awaiting     = $totalReviews - $replied;

    $ratingSum = array_sum(array_map(fn($f) => (int) ($f['rating'] ?? 0), $feedback));
    $avgRating = $totalReviews > 0 ? round($ratingSum / $totalReviews, 1) : 0.0;

    $ratingCounts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    foreach ($feedback as $f) {
        $r = (int) ($f['rating'] ?? 0);
        if (array_key_exists($r, $ratingCounts)) {
            $ratingCounts[$r]++;
        }
    }

    $fiveStar = $ratingCounts[5];
    $lowStar  = $ratingCounts[1] + $ratingCounts[2];
    ?>
<style>
/* ── Manage Feedback (mf-) ─────────────────────────────────────── */
.mf-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
@media(max-width:920px){.mf-stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:480px){.mf-stats{grid-template-columns:1fr 1fr}}

.mf-stat{background:#fff;border:1px solid #ede8f8;border-radius:12px;padding:16px;display:flex;align-items:center;gap:12px;box-shadow:0 2px 8px rgba(59,7,100,.05);cursor:pointer;transition:box-shadow .15s}
.mf-stat:hover{box-shadow:0 4px 14px rgba(59,7,100,.10)}
.mf-stat-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:19px;flex-shrink:0}
.mf-stat-icon.amber{background:#fef3c7}
.mf-stat-icon.blue{background:#e8f4fd}
.mf-stat-icon.green{background:#eaf3de}
.mf-stat-icon.red{background:#fcebeb}
.mf-stat-num{font-family:'Sora',sans-serif;font-size:26px;font-weight:700;color:#1a0e2e;line-height:1;margin-bottom:2px}
.mf-stat-num.amber{color:#c77712}
.mf-stat-lbl{font-size:12px;color:#72647a}

/* Rating overview */
.mf-overview{background:#fff;border:1px solid #ede8f8;border-radius:12px;padding:20px 22px;margin-bottom:18px;box-shadow:0 2px 8px rgba(59,7,100,.05)}
.mf-ov-row{display:flex;align-items:center;gap:32px;flex-wrap:wrap}
.mf-ov-score{text-align:center;flex-shrink:0;min-width:80px}
.mf-ov-big{font-family:'Sora',sans-serif;font-size:54px;font-weight:800;color:#1a0e2e;line-height:1;margin-bottom:4px}
.mf-ov-stars{display:flex;gap:3px;justify-content:center;font-size:19px;margin-bottom:5px}
.mf-ov-stars .on{color:#f59e0b}
.mf-ov-stars .off{color:#e5e7eb}
.mf-ov-base{font-size:12px;color:#72647a}
.mf-ov-bars{flex:1;min-width:180px}
.mf-bar-row{display:flex;align-items:center;gap:10px;margin-bottom:7px}
.mf-bar-row:last-child{margin-bottom:0}
.mf-bar-lbl{font-size:12px;color:#72647a;width:22px;text-align:right;flex-shrink:0}
.mf-bar-track{flex:1;height:9px;background:#f0ebf8;border-radius:100px;overflow:hidden}
.mf-bar-fill{height:100%;background:#f59e0b;border-radius:100px;transition:width .5s}
.mf-bar-ct{font-size:11px;color:#72647a;width:24px;text-align:right;flex-shrink:0}

/* Filter chips */
.mf-chips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;align-items:center}
.mf-chip{display:inline-flex;align-items:center;gap:4px;padding:6px 14px;border-radius:100px;border:1.5px solid #ede8f8;background:#fff;font-size:12.5px;font-weight:600;color:#72647a;cursor:pointer;transition:all .15s;font-family:'DM Sans',sans-serif;white-space:nowrap}
.mf-chip:hover{border-color:#a78bdb;color:#5b21b6}
.mf-chip.active{background:#7c3aed;border-color:#7c3aed;color:#fff}
.mf-chip-ct{font-size:10.5px;font-weight:700;padding:1px 6px;border-radius:8px;background:rgba(0,0,0,.09);margin-left:2px}
.mf-chip.active .mf-chip-ct{background:rgba(255,255,255,.25)}
.mf-shown{font-size:12.5px;color:#72647a;margin-left:auto;white-space:nowrap}

/* Review cards */
.mf-list{display:flex;flex-direction:column;gap:14px}
.mf-card{background:#fff;border:1px solid #ede8f8;border-radius:12px;padding:18px 20px;box-shadow:0 2px 8px rgba(59,7,100,.05);transition:box-shadow .15s}
.mf-card:hover{box-shadow:0 4px 14px rgba(59,7,100,.08)}

/* Card header */
.mf-card-hd{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px;flex-wrap:wrap}
.mf-user-row{display:flex;align-items:center;gap:10px}
.mf-av{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#3b0764,#7c3aed);color:#fff;display:flex;align-items:center;justify-content:center;font-family:'Sora',sans-serif;font-size:13px;font-weight:700;flex-shrink:0;overflow:hidden}
.mf-av img{width:100%;height:100%;object-fit:cover;display:block;border-radius:50%}
.mf-user-name{font-size:13.5px;font-weight:700;color:#1a0e2e;line-height:1.3}
.mf-user-dt{font-size:11.5px;color:#72647a;margin-top:1px}
.mf-stars-row{display:flex;gap:2px;font-size:16px;flex-shrink:0;margin-top:2px}
.mf-stars-row .on{color:#f59e0b}
.mf-stars-row .off{color:#e5e7eb}

/* Service + comment */
.mf-service{font-size:12.5px;color:#72647a;margin-bottom:10px;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.mf-svc-tag{display:inline-flex;align-items:center;padding:2px 9px;border-radius:100px;background:#f3f0ff;color:#5b21b6;font-size:11.5px;font-weight:600}
.mf-comment{font-size:13.5px;color:#374151;line-height:1.65;font-style:italic;margin:0 0 14px;padding-left:12px;border-left:2.5px solid #ede8f8}

/* Reply states */
.mf-awaiting-tag{display:inline-flex;align-items:center;gap:5px;font-size:12px;color:#b42318;font-weight:600;background:#fcebeb;padding:3px 10px;border-radius:8px;margin-bottom:8px}
.mf-reply-display{background:linear-gradient(135deg,#f0fdfa,#ccfbf1);border:1px solid #99f6e4;border-left:3px solid #0d9488;border-radius:0 10px 10px 0;padding:12px 16px;font-size:13px;color:#0f766e;margin-bottom:8px}
.mf-reply-display-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;flex-wrap:wrap;gap:5px}
.mf-reply-label{font-size:11px;color:#0d9488;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.mf-reply-date{font-size:11px;color:#0d9488;opacity:.75}
.mf-reply-text{line-height:1.55}

/* Reply form */
.mf-form-wrap{display:flex;flex-direction:column;gap:8px;margin-top:6px}
.mf-reply-inp{width:100%;padding:9px 13px;border:1.5px solid #e5ddf5;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;color:#1a0e2e;background:#fff;outline:none;transition:border-color .18s;box-sizing:border-box;resize:vertical;min-height:72px;line-height:1.5}
.mf-reply-inp:focus{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.08)}
.mf-form-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end}
.mf-form-msg{font-size:12.5px;font-weight:600;flex:1;min-width:0}
.mf-form-msg.ok{color:#16845c}
.mf-form-msg.err{color:#b42318}
.mf-btn-reply{background:linear-gradient(135deg,#3b0764,#5b21b6);color:#fff;border:none;border-radius:8px;padding:7px 16px;font-size:12.5px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px;font-family:'DM Sans',sans-serif;transition:opacity .15s;white-space:nowrap}
.mf-btn-reply:hover{opacity:.88}
.mf-btn-reply:disabled{opacity:.55;cursor:not-allowed}
.mf-btn-cancel{background:#f3f4f6;border:1px solid #e5e7eb;border-radius:8px;padding:7px 12px;font-size:12.5px;font-weight:600;cursor:pointer;color:#72647a;font-family:'DM Sans',sans-serif;transition:background .15s}
.mf-btn-cancel:hover{background:#e5e7eb}
.mf-btn-edit{background:#f3f0ff;border:1px solid #ede8f8;border-radius:7px;padding:5px 12px;font-size:12px;font-weight:600;cursor:pointer;color:#5b21b6;font-family:'DM Sans',sans-serif;transition:background .15s}
.mf-btn-edit:hover{background:#e3dcfc}

/* Empty state */
.mf-empty{text-align:center;padding:48px 20px;color:#72647a;font-size:13.5px}
.mf-empty-icon{font-size:42px;margin-bottom:12px}
.mf-empty h3{font-family:'Sora',sans-serif;font-size:16px;font-weight:700;color:#1a0e2e;margin:0 0 6px}
</style>

<!-- ── Stat Cards ── -->
<div class="mf-stats">
    <div class="mf-stat" onclick="mfSetFilter('all')" title="Show all reviews">
        <div class="mf-stat-icon amber">⭐</div>
        <div>
            <div class="mf-stat-num amber"><?= $avgRating > 0 ? number_format($avgRating, 1) : '—' ?></div>
            <div class="mf-stat-lbl">Average Rating</div>
        </div>
    </div>
    <div class="mf-stat" onclick="mfSetFilter('all')" title="Show all reviews">
        <div class="mf-stat-icon blue">💬</div>
        <div>
            <div class="mf-stat-num"><?= $totalReviews ?></div>
            <div class="mf-stat-lbl">Total Reviews</div>
        </div>
    </div>
    <div class="mf-stat" onclick="mfSetFilter('replied')" title="Show replied reviews">
        <div class="mf-stat-icon green">✅</div>
        <div>
            <div class="mf-stat-num" id="mfStatReplied"><?= $replied ?></div>
            <div class="mf-stat-lbl">Replied</div>
        </div>
    </div>
    <div class="mf-stat" onclick="mfSetFilter('awaiting')" title="Show awaiting reviews">
        <div class="mf-stat-icon red">⏳</div>
        <div>
            <div class="mf-stat-num" id="mfStatAwaiting"><?= $awaiting ?></div>
            <div class="mf-stat-lbl">Awaiting Reply</div>
        </div>
    </div>
</div>

<!-- ── Rating Overview ── -->
<?php if ($totalReviews > 0): ?>
<div class="mf-overview">
    <div class="mf-ov-row">
        <div class="mf-ov-score">
            <div class="mf-ov-big"><?= number_format($avgRating, 1) ?></div>
            <div class="mf-ov-stars">
                <?php $rounded = round($avgRating);
                for ($s = 1; $s <= 5; $s++): ?>
                    <span class="<?= $s <= $rounded ? 'on' : 'off' ?>">★</span>
                <?php endfor; ?>
            </div>
            <div class="mf-ov-base">Based on <?= $totalReviews ?> review<?= $totalReviews !== 1 ? 's' : '' ?></div>
        </div>
        <div class="mf-ov-bars">
            <?php for ($star = 5; $star >= 1; $star--):
                $ct  = $ratingCounts[$star];
                $pct = $totalReviews > 0 ? round($ct / $totalReviews * 100) : 0;
            ?>
            <div class="mf-bar-row">
                <span class="mf-bar-lbl"><?= $star ?>★</span>
                <div class="mf-bar-track">
                    <div class="mf-bar-fill" style="width:<?= $pct ?>%"></div>
                </div>
                <span class="mf-bar-ct"><?= $ct ?></span>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Filter Chips ── -->
<div class="mf-chips">
    <button class="mf-chip active" data-filter="all" onclick="mfSetFilter('all')">
        All Reviews <span class="mf-chip-ct"><?= $totalReviews ?></span>
    </button>
    <button class="mf-chip" data-filter="awaiting" onclick="mfSetFilter('awaiting')">
        ⏳ Awaiting Reply <span class="mf-chip-ct" id="mfChipAwaiting"><?= $awaiting ?></span>
    </button>
    <button class="mf-chip" data-filter="replied" onclick="mfSetFilter('replied')">
        ✅ Replied <span class="mf-chip-ct" id="mfChipReplied"><?= $replied ?></span>
    </button>
    <button class="mf-chip" data-filter="5star" onclick="mfSetFilter('5star')">
        ⭐ 5 Star <span class="mf-chip-ct"><?= $fiveStar ?></span>
    </button>
    <button class="mf-chip" data-filter="low" onclick="mfSetFilter('low')">
        &#9888; Low Ratings <span class="mf-chip-ct"><?= $lowStar ?></span>
    </button>
    <span class="mf-shown" id="mfShown"><?= $totalReviews ?> review<?= $totalReviews !== 1 ? 's' : '' ?></span>
</div>

<!-- ── Review Cards ── -->
<div class="mf-list" id="mfList">
<?php if (empty($feedback)): ?>
    <div class="mf-empty">
        <div class="mf-empty-icon">💬</div>
        <h3>No feedback yet</h3>
        <p>Patient reviews will appear here after they rate their completed appointments.</p>
    </div>
<?php else: ?>
    <?php foreach ($feedback as $fb):
        $fid        = (int) $fb['feedbackID'];
        $rating     = (int) ($fb['rating'] ?? 0);
        $hasReply   = !empty($fb['adminResponse']);
        $status     = $hasReply ? 'replied' : 'awaiting';
        $name       = (string) ($fb['username'] ?? '');
        $initials   = strtoupper(substr($name ?: 'P', 0, 2));
        $avatarFile = (string) ($fb['userAvatar'] ?? '');
        $fbDate     = $fb['feedbackDate'] ? date('d M Y', strtotime((string) $fb['feedbackDate'])) : '';
        $apptDate   = $fb['appointmentDate'] ? date('d M Y', strtotime((string) $fb['appointmentDate'])) : '';
        $replyDate  = ($hasReply && $fb['responseDate']) ? date('d M Y', strtotime((string) $fb['responseDate'])) : '';
    ?>
    <div class="mf-card"
         id="mfc-<?= $fid ?>"
         data-fid="<?= $fid ?>"
         data-status="<?= e($status) ?>"
         data-rating="<?= $rating ?>">

        <!-- Header: avatar + name + date | stars -->
        <div class="mf-card-hd">
            <div class="mf-user-row">
                <div class="mf-av">
                    <?php if ($avatarFile): ?>
                        <img src="assets/avatars/<?= e(rawurlencode($avatarFile)) ?>" alt="">
                    <?php else: ?>
                        <?= e($initials) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="mf-user-name"><?= e($name) ?></div>
                    <div class="mf-user-dt"><?= e($fbDate) ?></div>
                </div>
            </div>
            <div class="mf-stars-row">
                <?php for ($s = 1; $s <= 5; $s++): ?>
                    <span class="<?= $s <= $rating ? 'on' : 'off' ?>">★</span>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Service info -->
        <div class="mf-service">
            <span class="mf-svc-tag">🦷 <?= e($fb['serviceType'] ?? '') ?></span>
            <span style="color:#c4b8d4">·</span>
            <?= e($apptDate) ?>
        </div>

        <!-- Comment -->
        <p class="mf-comment">"<?= e($fb['comments'] ?? '') ?>"</p>

        <!-- Reply area -->
        <div id="mf-area-<?= $fid ?>">
            <!-- Status display -->
            <div id="mf-status-<?= $fid ?>">
                <?php if ($hasReply): ?>
                <div class="mf-reply-display">
                    <div class="mf-reply-display-hd">
                        <span class="mf-reply-label">&#8617; Your Reply</span>
                        <?php if ($replyDate): ?>
                            <span class="mf-reply-date"><?= e($replyDate) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="mf-reply-text" id="mf-rtext-<?= $fid ?>"><?= e($fb['adminResponse']) ?></div>
                </div>
                <button class="mf-btn-edit" onclick="mfToggleEdit(<?= $fid ?>)">Edit Reply</button>
                <?php else: ?>
                <span class="mf-awaiting-tag">● Awaiting reply</span>
                <?php endif; ?>
            </div>

            <!-- Reply / Edit form -->
            <div class="mf-form-wrap" id="mf-form-<?= $fid ?>" <?= $hasReply ? 'style="display:none"' : '' ?>>
                <textarea class="mf-reply-inp" id="mf-inp-<?= $fid ?>"
                          placeholder="Write your reply to this patient…"><?= $hasReply ? e($fb['adminResponse']) : '' ?></textarea>
                <div class="mf-form-row">
                    <span class="mf-form-msg" id="mf-msg-<?= $fid ?>"></span>
                    <?php if ($hasReply): ?>
                    <button class="mf-btn-cancel" id="mf-cancel-<?= $fid ?>" onclick="mfToggleEdit(<?= $fid ?>)">Cancel</button>
                    <?php endif; ?>
                    <button class="mf-btn-reply" id="mf-btn-<?= $fid ?>" onclick="mfSubmitReply(<?= $fid ?>)">
                        &#8617; <?= $hasReply ? 'Update Reply' : 'Send Reply' ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>

<script>
window.userID   = <?= (int) ($_SESSION['userID'] ?? 0) ?>;
window.userRole = <?= json_encode($_SESSION['userRole'] ?? '') ?>;

/* ── HTML escape ─────────────────────────────────────────────── */
function mfEsc(str) {
    return String(str == null ? '' : str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/* ── Filter chips ─────────────────────────────────────────────── */
var mfActiveFilter = 'all';

function mfSetFilter(filter) {
    mfActiveFilter = filter;
    document.querySelectorAll('.mf-chip').forEach(function (c) {
        c.classList.toggle('active', c.dataset.filter === filter);
    });

    var shown = 0;
    document.querySelectorAll('#mfList .mf-card[data-fid]').forEach(function (card) {
        var status = card.dataset.status;
        var rating = parseInt(card.dataset.rating, 10);
        var visible = false;

        if      (filter === 'all')     visible = true;
        else if (filter === 'awaiting') visible = status === 'awaiting';
        else if (filter === 'replied')  visible = status === 'replied';
        else if (filter === '5star')    visible = rating === 5;
        else if (filter === 'low')      visible = rating <= 2;

        card.style.display = visible ? '' : 'none';
        if (visible) shown++;
    });

    var el = document.getElementById('mfShown');
    if (el) el.textContent = shown + ' review' + (shown !== 1 ? 's' : '');
}

/* ── Toggle edit form ─────────────────────────────────────────── */
function mfToggleEdit(fid) {
    var form = document.getElementById('mf-form-' + fid);
    if (!form) return;
    var open = form.style.display === 'none' || form.style.display === '';
    form.style.display = (form.style.display === 'none') ? '' : 'none';
    if (form.style.display !== 'none') {
        var inp = document.getElementById('mf-inp-' + fid);
        if (inp) inp.focus();
    }
}

/* ── Submit reply (AJAX) ──────────────────────────────────────── */
function mfSubmitReply(fid) {
    var inp  = document.getElementById('mf-inp-' + fid);
    var btn  = document.getElementById('mf-btn-' + fid);
    var msg  = document.getElementById('mf-msg-' + fid);
    var card = document.getElementById('mfc-' + fid);

    var text = inp ? inp.value.trim() : '';
    if (!text) {
        msg.className   = 'mf-form-msg err';
        msg.textContent = 'Reply cannot be empty.';
        return;
    }

    var wasAwaiting = card && card.dataset.status === 'awaiting';

    btn.disabled    = true;
    btn.textContent = 'Sending…';
    msg.textContent = '';

    var body = new URLSearchParams();
    body.append('_csrf_token', window.DETABOT_CSRF);
    body.append('feedbackID', fid);
    body.append('adminResponse', text);

    fetch('reply_feedback.php', { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.disabled = false;

            if (data.success) {
                // Update teal reply display
                var statusEl = document.getElementById('mf-status-' + fid);
                if (statusEl) {
                    statusEl.innerHTML =
                        '<div class="mf-reply-display">' +
                        '<div class="mf-reply-display-hd">' +
                        '<span class="mf-reply-label">&#8617; Your Reply</span>' +
                        '<span class="mf-reply-date">Just now</span>' +
                        '</div>' +
                        '<div class="mf-reply-text" id="mf-rtext-' + fid + '">' + mfEsc(text) + '</div>' +
                        '</div>' +
                        '<button class="mf-btn-edit" onclick="mfToggleEdit(' + fid + ')">Edit Reply</button>';
                }

                // Update form for edit mode: ensure Cancel button exists
                var formRow = document.querySelector('#mf-form-' + fid + ' .mf-form-row');
                if (formRow && !document.getElementById('mf-cancel-' + fid)) {
                    var cancelBtn       = document.createElement('button');
                    cancelBtn.id        = 'mf-cancel-' + fid;
                    cancelBtn.className = 'mf-btn-cancel';
                    cancelBtn.textContent = 'Cancel';
                    cancelBtn.onclick   = function () { mfToggleEdit(fid); };
                    formRow.insertBefore(cancelBtn, btn);
                }
                btn.textContent = '&#8617; Update Reply';

                // Hide form
                var form = document.getElementById('mf-form-' + fid);
                if (form) form.style.display = 'none';

                // Update card state
                if (card) {
                    card.dataset.status = 'replied';
                    if (wasAwaiting) {
                        // Update stat counters
                        var rEl = document.getElementById('mfStatReplied');
                        var aEl = document.getElementById('mfStatAwaiting');
                        if (rEl) rEl.textContent = parseInt(rEl.textContent, 10) + 1;
                        if (aEl) aEl.textContent = Math.max(0, parseInt(aEl.textContent, 10) - 1);
                        // Update chip badge counts
                        var awChip = document.getElementById('mfChipAwaiting');
                        var repChip = document.getElementById('mfChipReplied');
                        if (awChip)  awChip.textContent  = Math.max(0, parseInt(awChip.textContent, 10) - 1);
                        if (repChip) repChip.textContent = parseInt(repChip.textContent, 10) + 1;
                        // Hide card if filter excludes it
                        if (mfActiveFilter === 'awaiting') {
                            card.style.display = 'none';
                            mfUpdateShownCount();
                        }
                    }
                }
            } else {
                msg.className   = 'mf-form-msg err';
                msg.textContent = data.message || 'Failed to send reply.';
            }
        })
        .catch(function () {
            btn.disabled    = false;
            btn.textContent = '&#8617; Send Reply';
            msg.className   = 'mf-form-msg err';
            msg.textContent = 'Network error. Please try again.';
        });
}

/* ── Update shown count ───────────────────────────────────────── */
function mfUpdateShownCount() {
    var shown = 0;
    document.querySelectorAll('#mfList .mf-card[data-fid]').forEach(function (card) {
        if (card.style.display !== 'none') shown++;
    });
    var el = document.getElementById('mfShown');
    if (el) el.textContent = shown + ' review' + (shown !== 1 ? 's' : '');
}
</script>
    <?php
}
