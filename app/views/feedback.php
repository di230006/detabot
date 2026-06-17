<?php
declare(strict_types=1);

function page_feedback(array $user): void
{
    $canManage = has_role($user, ['admin', 'staff']);
    $uid       = (int) $user['userID'];

    /* ── Fetch data ── */
    if (!$canManage) {
        $eligible = db_all(
            "SELECT a.* FROM tbl_appointment a
             WHERE a.userID = ? AND a.status = 'completed'
               AND a.appointmentID NOT IN (
                   SELECT appointmentID FROM tbl_feedback WHERE userID = ?
               )
             ORDER BY a.appointmentDate DESC",
            [$uid, $uid]
        );

        $myFeedback = db_all(
            "SELECT f.*, a.serviceType, a.appointmentDate, a.notes
             FROM tbl_feedback f
             JOIN tbl_appointment a ON a.appointmentID = f.appointmentID
             WHERE f.userID = ?
             ORDER BY f.feedbackDate DESC",
            [$uid]
        );

        $statsRow      = db_one(
            "SELECT AVG(rating) AS avg_rating, COUNT(*) AS total FROM tbl_feedback WHERE userID = ?",
            [$uid]
        );
        $avgRating     = ($statsRow && (int) $statsRow['total'] > 0)
            ? round((float) $statsRow['avg_rating'], 1) : null;
        $totalReviews  = (int) ($statsRow['total'] ?? 0);
        $awaitingCount = count($eligible);
    } else {
        $feedback = db_all(
            "SELECT f.*, u.username, a.serviceType, a.appointmentDate
             FROM tbl_feedback f
             JOIN tbl_user u ON u.userID = f.userID
             JOIN tbl_appointment a ON a.appointmentID = f.appointmentID
             ORDER BY f.feedbackDate DESC"
        );
    }
    ?>

<style>
/* ── Feedback page (fb- prefix) ── */

/* Stat cards */
.fb-stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px}
@media(max-width:900px){.fb-stats-grid{grid-template-columns:1fr}}
.fb-stat-card{background:#fff;border-radius:12px;border:1px solid #ede8f8;padding:18px 20px;display:flex;align-items:center;gap:16px;box-shadow:0 2px 8px rgba(59,7,100,.05);transition:box-shadow .18s}
.fb-stat-card:hover{box-shadow:0 4px 16px rgba(59,7,100,.10)}
.fb-stat-icon{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.fb-stat-icon.amber{background:#fef3c7}
.fb-stat-icon.blue{background:#dbeafe}
.fb-stat-num{font-family:'Sora',sans-serif;font-size:28px;font-weight:800;color:#1a0e2e;line-height:1}
.fb-stat-lbl{font-size:12px;color:#72647a;margin-top:4px}

/* Section headers */
.fb-sec-head{display:flex;align-items:center;gap:10px;margin-bottom:18px}
.fb-sec-head h2{font-family:'Sora',sans-serif;font-size:17px;font-weight:700;color:#1a0e2e;margin:0}
.fb-sec-head-svg{width:20px;height:20px;flex-shrink:0;stroke:#7c3aed;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

/* Submit / reviews card wrapper */
.fb-card{background:#fff;border-radius:14px;border:1px solid #ede8f8;padding:24px;box-shadow:0 2px 12px rgba(59,7,100,.06);margin-bottom:24px}

/* Appointment selector */
.fb-select{width:100%;padding:10px 36px 10px 14px;border:1.5px solid #ede8f8;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:14px;color:#1a0e2e;background:#faf8ff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%237c3aed' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E") no-repeat right 12px center / 16px;appearance:none;outline:none;cursor:pointer;transition:border-color .15s;margin-bottom:14px;box-sizing:border-box}
.fb-select:focus{border-color:#7c3aed}

/* Appointment preview */
.fb-appt-preview{background:linear-gradient(135deg,#3b0764,#5b21b6);border-radius:12px;padding:16px 18px;margin-bottom:20px;display:flex;align-items:center;gap:16px}
.fb-appt-prev-date{background:rgba(255,255,255,.15);border-radius:8px;padding:8px 12px;text-align:center;min-width:52px;flex-shrink:0}
.fb-appt-prev-day{font-family:'Sora',sans-serif;font-size:22px;font-weight:700;color:#fff;line-height:1}
.fb-appt-prev-mon{font-size:10px;font-weight:700;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.06em;margin-top:2px}
.fb-appt-prev-svc{font-weight:700;font-size:15px;color:#fff;margin-bottom:4px}
.fb-appt-prev-meta{font-size:12px;color:rgba(255,255,255,.7);display:flex;gap:12px;flex-wrap:wrap}

/* Star rating */
.fb-field-lbl{font-size:13px;font-weight:600;color:#1a0e2e;display:block;margin-bottom:10px}
.fb-stars-row{display:flex;gap:8px;margin-bottom:6px}
.fb-star{font-size:30px;color:#e5e7eb;cursor:pointer;transition:color .1s,transform .1s;line-height:1;user-select:none}
.fb-star.active{color:#f59e0b}
.fb-star:hover{transform:scale(1.15)}
.fb-stars-hint{font-size:12px;color:#72647a;display:block;margin-bottom:18px;min-height:18px}

/* Quick tags */
.fb-tags-row{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px}
.fb-tag{display:inline-flex;align-items:center;gap:4px;padding:6px 14px;border-radius:100px;border:1.5px solid #ede8f8;background:#fff;font-size:12.5px;font-weight:600;color:#72647a;cursor:pointer;transition:all .15s;font-family:'DM Sans',sans-serif}
.fb-tag:hover{border-color:#7c3aed;color:#7c3aed;background:#f3f0ff}
.fb-tag.active{border-color:#7c3aed;background:#7c3aed;color:#fff}

/* Comment textarea */
.fb-textarea{width:100%;padding:12px 14px;border:1.5px solid #ede8f8;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:14px;color:#1a0e2e;background:#faf8ff;resize:vertical;min-height:100px;outline:none;transition:border-color .15s;box-sizing:border-box;margin-bottom:16px}
.fb-textarea:focus{border-color:#7c3aed}

/* Submit button */
.fb-submit-btn{background:#3b0764;color:#fff;border:none;border-radius:10px;padding:12px 28px;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:700;cursor:pointer;transition:opacity .15s,transform .1s;display:inline-flex;align-items:center;gap:8px}
.fb-submit-btn:hover{opacity:.88;transform:translateY(-1px)}
.fb-submit-btn:disabled{opacity:.6;cursor:not-allowed;transform:none}

/* Toast */
.fb-toast{padding:12px 18px;border-radius:10px;font-size:13.5px;font-weight:600;margin-bottom:16px;display:none}
.fb-toast.success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7}
.fb-toast.error{background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5}

/* Empty state */
.fb-empty{text-align:center;padding:40px 24px;color:#72647a}
.fb-empty svg{width:56px;height:56px;stroke:#d1c4e9;display:block;margin:0 auto 14px;stroke-width:1.5}
.fb-empty h3{font-family:'Sora',sans-serif;font-size:16px;font-weight:700;color:#1a0e2e;margin:0 0 8px}
.fb-empty p{font-size:13.5px;line-height:1.6;margin:0 auto;max-width:380px}

/* Past review items */
.fb-review-item{padding:16px 0;border-bottom:1px solid #f0ebf8}
.fb-review-item:last-child{border-bottom:none;padding-bottom:0}
.fb-review-item:first-child{padding-top:0}
.fb-review-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:6px}
.fb-review-svc{font-weight:700;font-size:14.5px;color:#1a0e2e}
.fb-review-dt{font-size:12px;color:#72647a;white-space:nowrap}
.fb-review-stars{display:flex;gap:2px;font-size:16px;margin-bottom:8px}
.fb-review-stars .on{color:#f59e0b}
.fb-review-stars .off{color:#e5e7eb}
.fb-review-comment{font-size:13.5px;color:#374151;line-height:1.6;font-style:italic;margin:0 0 10px}
.fb-reply-box{background:linear-gradient(135deg,#f0fdfa,#ccfbf1);border:1px solid #99f6e4;border-left:3px solid #0d9488;border-radius:0 10px 10px 0;padding:12px 16px;font-size:13px;color:#0f766e;margin-top:4px}
.fb-reply-box strong{display:flex;align-items:center;gap:6px;font-size:11px;color:#0d9488;font-weight:700;margin-bottom:4px;text-transform:uppercase;letter-spacing:.04em}
.fb-awaiting-reply{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#c77712;font-weight:600;background:#fef3c7;padding:4px 10px;border-radius:8px}

/* Admin card styles */
.fb-mgmt-list{display:flex;flex-direction:column;gap:14px}
.fb-mgmt-card{background:#faf8ff;border:1px solid #ede8f8;border-radius:12px;padding:18px 20px;transition:box-shadow .18s}
.fb-mgmt-card:hover{box-shadow:0 4px 16px rgba(59,7,100,.08)}
.fb-mgmt-header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px;flex-wrap:wrap}
.fb-mgmt-user{display:flex;align-items:center;gap:12px}
.fb-avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#3b0764,#7c3aed);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.95rem;flex-shrink:0}
.fb-mgmt-name{font-weight:700;font-size:14px;color:#1a0e2e}
.fb-mgmt-sub{font-size:12px;color:#72647a}
.fb-mgmt-actions{display:flex;gap:8px;align-items:center;flex-shrink:0}
.fb-stars-sm{display:flex;gap:2px;font-size:14px}
.fb-stars-sm .on{color:#f59e0b}
.fb-stars-sm .off{color:#d1d5db}
.fb-reply-form{display:flex;gap:8px;margin-top:10px}
.fb-reply-form input{flex:1;border:1.5px solid #ede8f8;border-radius:8px;padding:8px 14px;font-size:13px;background:#fff;font-family:'DM Sans',sans-serif;outline:none}
.fb-reply-form input:focus{border-color:#7c3aed}
.btn-danger{background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;border:none;border-radius:8px;padding:5px 12px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:4px;transition:opacity .15s;font-family:inherit}
.btn-danger:hover{opacity:.85}
.btn-reply{background:#3b0764;color:#fff;border:none;border-radius:8px;padding:7px 14px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;display:flex;align-items:center;gap:4px;transition:opacity .15s;font-family:inherit}
.btn-reply:hover{opacity:.85}
.btn-edit-reply{background:#f3f4f6;border:1px solid #e5e7eb;border-radius:8px;padding:5px 10px;font-size:12px;font-weight:600;cursor:pointer;color:#72647a;transition:background .15s;font-family:inherit}
.btn-edit-reply:hover{background:#e5e7eb}
.fb-pending-pill{background:#ef4444;color:#fff;border-radius:100px;padding:2px 10px;font-size:11px;font-weight:700}

@media(max-width:640px){
    .fb-appt-preview{flex-direction:column}
    .fb-stats-grid{grid-template-columns:1fr}
}
</style>

<?php if (!$canManage): ?>

<!-- ── Stat Cards ── -->
<div class="fb-stats-grid">
    <div class="fb-stat-card">
        <div class="fb-stat-icon amber" aria-hidden="true">⭐</div>
        <div>
            <div class="fb-stat-num"><?= $avgRating !== null ? e((string) $avgRating) : '—' ?></div>
            <div class="fb-stat-lbl">Your Avg Rating</div>
        </div>
    </div>
    <div class="fb-stat-card">
        <div class="fb-stat-icon blue" aria-hidden="true">💬</div>
        <div>
            <div class="fb-stat-num"><?= $totalReviews ?></div>
            <div class="fb-stat-lbl">Reviews Submitted</div>
        </div>
    </div>
    <div class="fb-stat-card">
        <div class="fb-stat-icon amber" aria-hidden="true">⏳</div>
        <div>
            <div class="fb-stat-num"><?= $awaitingCount ?></div>
            <div class="fb-stat-lbl">Awaiting Feedback</div>
        </div>
    </div>
</div>

<!-- ── Submit Feedback ── -->
<div class="fb-card" id="fbSubmitSection">
    <div class="fb-sec-head">
        <svg class="fb-sec-head-svg" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
        </svg>
        <h2>Submit Feedback</h2>
    </div>

    <div class="fb-toast" id="fbToast" role="alert" aria-live="polite"></div>

    <?php if ($eligible):
        /* Build appointment data for JS */
        $apptJs = [];
        foreach ($eligible as $a) {
            $ts = strtotime((string) $a['appointmentDate']);
            $apptJs[(int) $a['appointmentID']] = [
                'day'     => date('d', $ts),
                'mon'     => date('M', $ts),
                'date'    => date('d M Y', $ts),
                'service' => (string) $a['serviceType'],
                'dentist' => extract_dentist_name((string) ($a['notes'] ?? '')),
                'time'    => substr((string) $a['appointmentTime'], 0, 5),
            ];
        }
        $first   = reset($apptJs);
        $firstId = array_key_first($apptJs);
    ?>

        <!-- Appointment dropdown -->
        <label class="fb-field-lbl" for="fbApptSelect">Select Appointment to Review</label>
        <select class="fb-select" id="fbApptSelect">
            <?php foreach ($eligible as $a):
                $ts = strtotime((string) $a['appointmentDate']);
            ?>
                <option value="<?= e($a['appointmentID']) ?>"><?= e(date('d M Y', $ts)) ?> — <?= e($a['serviceType']) ?></option>
            <?php endforeach; ?>
        </select>

        <!-- Appointment preview -->
        <div class="fb-appt-preview" id="fbApptPreview">
            <div class="fb-appt-prev-date">
                <div class="fb-appt-prev-day" id="fbPrevDay"><?= e($first['day']) ?></div>
                <div class="fb-appt-prev-mon" id="fbPrevMon"><?= e($first['mon']) ?></div>
            </div>
            <div>
                <div class="fb-appt-prev-svc" id="fbPrevSvc"><?= e($first['service']) ?></div>
                <div class="fb-appt-prev-meta">
                    <span id="fbPrevDr">👨‍⚕️ <?= e($first['dentist']) ?></span>
                    <span id="fbPrevTm">🕐 <?= e($first['time']) ?></span>
                    <span id="fbPrevDt"><?= e($first['date']) ?></span>
                </div>
            </div>
        </div>

        <!-- Star rating -->
        <span class="fb-field-lbl">Your Rating</span>
        <div class="fb-stars-row" id="fbStarsRow" role="group" aria-label="Star rating">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <span class="fb-star" data-val="<?= $i ?>" role="button" tabindex="0" aria-label="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">★</span>
            <?php endfor; ?>
        </div>
        <span class="fb-stars-hint" id="fbStarsHint">Click a star to rate</span>

        <!-- Quick tags -->
        <span class="fb-field-lbl">
            Quick Tags
            <span style="font-weight:400;color:#9ca3af;font-size:11.5px">(optional, multi-select)</span>
        </span>
        <div class="fb-tags-row" id="fbTagsRow">
            <button type="button" class="fb-tag" data-tag="Friendly staff">😊 Friendly staff</button>
            <button type="button" class="fb-tag" data-tag="Clean clinic">✨ Clean clinic</button>
            <button type="button" class="fb-tag" data-tag="Painless treatment">💉 Painless treatment</button>
            <button type="button" class="fb-tag" data-tag="Short wait time">⏱ Short wait time</button>
            <button type="button" class="fb-tag" data-tag="Clear explanation">💡 Clear explanation</button>
        </div>

        <!-- Comment -->
        <label class="fb-field-lbl" for="fbComment">Tell us about your visit</label>
        <textarea class="fb-textarea" id="fbComment" placeholder="Tell us more about your visit…" rows="4"></textarea>

        <!-- Submit -->
        <button type="button" class="fb-submit-btn" id="fbSubmitBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px" aria-hidden="true">
                <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
            </svg>
            Submit Feedback
        </button>

        <script>window.__fbApptData = <?= json_encode($apptJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>

    <?php else: ?>

        <div class="fb-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 12a8 8 0 0 1-8 8H7l-4 3v-6.5A8 8 0 1 1 21 12Z"/>
                <path d="M8 12h8M8 15h5"/>
            </svg>
            <h3>No feedback pending</h3>
            <p>No completed appointments waiting for feedback yet. After your next visit, you'll be able to share your experience here! 🦷</p>
        </div>

    <?php endif; ?>
</div>

<!-- ── My Past Reviews ── -->
<div class="fb-card" id="fbPastReviews">
    <div class="fb-sec-head">
        <svg class="fb-sec-head-svg" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
            <path d="M3 3v5h5"/><polyline points="12 7 12 12 15 15"/>
        </svg>
        <h2>My Past Reviews</h2>
    </div>

    <?php if ($myFeedback): ?>
        <div id="fbReviewsList">
        <?php foreach ($myFeedback as $item):
            $ts = strtotime((string) $item['appointmentDate']);
        ?>
            <div class="fb-review-item">
                <div class="fb-review-top">
                    <div>
                        <div class="fb-review-svc"><?= e($item['serviceType']) ?></div>
                    </div>
                    <div class="fb-review-dt"><?= e(date('d M Y', $ts)) ?></div>
                </div>
                <div class="fb-review-stars">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                        <span class="<?= $s <= (int) $item['rating'] ? 'on' : 'off' ?>">★</span>
                    <?php endfor; ?>
                </div>
                <p class="fb-review-comment">"<?= e($item['comments']) ?>"</p>
                <?php if (!empty($item['adminResponse'])): ?>
                    <div class="fb-reply-box">
                        <strong>↩ Reply from clinic</strong>
                        <?= e($item['adminResponse']) ?>
                    </div>
                <?php else: ?>
                    <span class="fb-awaiting-reply">⏳ Awaiting clinic reply</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="fb-empty" style="padding:28px 0">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 12a8 8 0 0 1-8 8H7l-4 3v-6.5A8 8 0 1 1 21 12Z"/>
            </svg>
            <p>You haven't submitted any reviews yet.</p>
        </div>
    <?php endif; ?>
</div>

<!-- ── Floating Chatbot ── -->
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
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="chatbot-body" id="chatbotBody">
            <div class="chatbot-bubble">Hi <?= e($user['username']) ?>! 💬 Want to share feedback about your visit? I can help you with that!</div>
            <div class="chatbot-quick-replies">
                <button class="chatbot-quick-btn" data-msg="How do I give feedback?">📝 How to Give Feedback</button>
                <button class="chatbot-quick-btn" data-msg="Show my past reviews">📋 My Past Reviews</button>
                <button class="chatbot-quick-btn" data-msg="I want to rate my last visit">⭐ Rate My Last Visit</button>
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
window.DETABOT_PAGE_CONTEXT = 'feedback';
</script>
<script src="assets/chat.js"></script>

<?php if ($eligible): ?>
<script>
(function () {
    'use strict';

    var CSRF      = window.DETABOT_CSRF || '';
    var apptData  = window.__fbApptData || {};
    var curRating = 0;
    var selTags   = [];

    /* ── Appointment selector ── */
    var sel      = document.getElementById('fbApptSelect');
    var prevDay  = document.getElementById('fbPrevDay');
    var prevMon  = document.getElementById('fbPrevMon');
    var prevSvc  = document.getElementById('fbPrevSvc');
    var prevDr   = document.getElementById('fbPrevDr');
    var prevTm   = document.getElementById('fbPrevTm');
    var prevDt   = document.getElementById('fbPrevDt');

    sel && sel.addEventListener('change', function () {
        var d = apptData[this.value];
        if (!d) return;
        prevDay.textContent = d.day;
        prevMon.textContent = d.mon;
        prevSvc.textContent = d.service;
        prevDr.textContent  = '👨‍⚕️ ' + d.dentist;
        prevTm.textContent  = '🕐 ' + d.time;
        prevDt.textContent  = d.date;
    });

    /* ── Star rating ── */
    var stars    = Array.from(document.querySelectorAll('#fbStarsRow .fb-star'));
    var hint     = document.getElementById('fbStarsHint');
    var hintMsgs = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

    function paintStars(n) {
        stars.forEach(function (s, i) { s.classList.toggle('active', i < n); });
    }

    stars.forEach(function (star, idx) {
        var val = idx + 1;
        star.addEventListener('mouseenter', function () {
            paintStars(val);
            hint.textContent = val + ' out of 5 — ' + hintMsgs[val];
        });
        star.addEventListener('click', function () {
            curRating = val;
            paintStars(val);
            hint.textContent = val + ' out of 5 — ' + hintMsgs[val];
        });
        star.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.click(); }
        });
    });

    document.getElementById('fbStarsRow').addEventListener('mouseleave', function () {
        paintStars(curRating);
        hint.textContent = curRating > 0
            ? curRating + ' out of 5 — ' + hintMsgs[curRating]
            : 'Click a star to rate';
    });

    /* ── Quick tags ── */
    document.querySelectorAll('#fbTagsRow .fb-tag').forEach(function (chip) {
        chip.addEventListener('click', function () {
            var tag = this.dataset.tag;
            var idx = selTags.indexOf(tag);
            if (idx === -1) { selTags.push(tag); this.classList.add('active'); }
            else            { selTags.splice(idx, 1); this.classList.remove('active'); }
        });
    });

    /* ── Toast helper ── */
    var toast = document.getElementById('fbToast');
    function showToast(msg, type) {
        toast.textContent  = msg;
        toast.className    = 'fb-toast ' + type;
        toast.style.display = 'block';
        toast.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        if (type === 'success') setTimeout(function () { toast.style.display = 'none'; }, 5000);
    }

    /* ── Submit ── */
    document.getElementById('fbSubmitBtn').addEventListener('click', function () {
        var apptId  = sel ? sel.value : '';
        var comment = (document.getElementById('fbComment').value || '').trim();

        if (!apptId)        { showToast('Please select an appointment.', 'error'); return; }
        if (curRating === 0){ showToast('Please select a star rating.', 'error'); return; }
        if (!comment)       { showToast('Please write a comment about your visit.', 'error'); return; }

        var fullComment = selTags.length > 0
            ? comment + '\n\nHighlights: ' + selTags.join(', ')
            : comment;

        var btn = this;
        btn.disabled    = true;
        btn.textContent = 'Submitting…';

        var fd = new FormData();
        fd.append('_csrf_token',  CSRF);
        fd.append('appointmentID', apptId);
        fd.append('rating',        curRating);
        fd.append('comments',      fullComment);

        fetch('submit_feedback.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    showToast(data.message || 'Thank you for your feedback!', 'success');
                    var opt = sel.querySelector('option[value="' + apptId + '"]');
                    if (opt) opt.remove();
                    curRating = 0;
                    selTags   = [];
                    document.getElementById('fbComment').value = '';
                    paintStars(0);
                    hint.textContent = 'Click a star to rate';
                    document.querySelectorAll('#fbTagsRow .fb-tag').forEach(function (c) { c.classList.remove('active'); });
                    setTimeout(function () { window.location.reload(); }, 1500);
                } else {
                    showToast(data.message || 'Something went wrong. Please try again.', 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Submit Feedback';
                }
            })
            .catch(function () {
                showToast('Network error. Please try again.', 'error');
                btn.disabled = false;
                btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Submit Feedback';
            });
    });
}());
</script>
<?php endif; /* eligible */ ?>

<?php else: /* Admin / Staff view */
    $pending = count(array_filter($feedback, fn($f) => empty($f['adminResponse'])));
?>

<!-- ── Admin: Manage Feedback ── -->
<div class="panel" style="border-radius:14px;margin-bottom:24px">
    <div class="panel-head" style="border-radius:14px 14px 0 0">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
            <h2 style="margin:0">Manage Feedback</h2>
            <?php if ($pending > 0): ?>
                <span class="fb-pending-pill"><?= $pending ?> awaiting reply</span>
            <?php endif; ?>
        </div>
    </div>
    <div style="padding:20px">
        <?php if (!$feedback): ?>
            <p class="empty">No feedback yet.</p>
        <?php else: ?>
            <div class="fb-mgmt-list">
                <?php foreach ($feedback as $item):
                    $initials = strtoupper(substr((string) $item['username'], 0, 1));
                    $hasReply = !empty($item['adminResponse']);
                ?>
                    <div class="fb-mgmt-card">
                        <div class="fb-mgmt-header">
                            <div class="fb-mgmt-user">
                                <div class="fb-avatar"><?= e($initials) ?></div>
                                <div>
                                    <div class="fb-mgmt-name"><?= e($item['username']) ?></div>
                                    <div class="fb-mgmt-sub"><?= e($item['serviceType']) ?> · <?= e($item['appointmentDate']) ?></div>
                                </div>
                            </div>
                            <div class="fb-mgmt-actions">
                                <div class="fb-stars-sm">
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <span class="<?= $s <= (int) $item['rating'] ? 'on' : 'off' ?>">★</span>
                                    <?php endfor; ?>
                                </div>
                                <form method="post" onsubmit="return confirm('Delete this feedback?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_feedback">
                                    <input type="hidden" name="feedbackID" value="<?= e($item['feedbackID']) ?>">
                                    <button class="btn-danger" type="submit">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px" aria-hidden="true">
                                            <polyline points="3 6 5 6 21 6"/>
                                            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                            <path d="M10 11v6M14 11v6"/>
                                        </svg>
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>

                        <p style="font-size:13.5px;color:#374151;margin:.25rem 0 .75rem;font-style:italic">"<?= e($item['comments']) ?>"</p>

                        <?php if ($hasReply): ?>
                            <div class="fb-reply-box">
                                <strong>↩ Clinic Response · <?= e(substr((string) $item['responseDate'], 0, 10)) ?></strong>
                                <?= e($item['adminResponse']) ?>
                            </div>
                            <div style="margin-top:.5rem">
                                <button class="btn-edit-reply" onclick="fbToggleReply('rf<?= e($item['feedbackID']) ?>')">Edit Reply</button>
                            </div>
                        <?php else: ?>
                            <div style="display:flex;align-items:center;gap:6px;margin-top:4px">
                                <span style="width:7px;height:7px;border-radius:50%;background:#ef4444;flex-shrink:0"></span>
                                <span style="font-size:12px;color:#ef4444;font-weight:600">Awaiting reply</span>
                            </div>
                        <?php endif; ?>

                        <div id="rf<?= e($item['feedbackID']) ?>" <?= $hasReply ? 'style="display:none"' : '' ?>>
                            <form method="post" class="fb-reply-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="respond_feedback">
                                <input type="hidden" name="feedbackID" value="<?= e($item['feedbackID']) ?>">
                                <input name="adminResponse" placeholder="Type a clinic response…" required value="<?= $hasReply ? e($item['adminResponse']) : '' ?>">
                                <button class="btn-reply" type="submit">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px" aria-hidden="true">
                                        <line x1="22" y1="2" x2="11" y2="13"/>
                                        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                                    </svg>
                                    <?= $hasReply ? 'Update' : 'Reply' ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function fbToggleReply(id) {
    var el = document.getElementById(id);
    if (el) el.style.display = el.style.display === 'none' ? '' : 'none';
}
</script>

<?php endif; /* canManage */ ?>
<?php
}
