<?php
declare(strict_types=1);

function page_profile(array $user): void
{
    $totalAppts     = (int) (db_one('SELECT COUNT(*) AS total FROM tbl_appointment WHERE userID = ?', [(int) $user['userID']])['total'] ?? 0);
    $completedAppts = (int) (db_one("SELECT COUNT(*) AS total FROM tbl_appointment WHERE userID = ? AND status = 'completed'", [(int) $user['userID']])['total'] ?? 0);
    $rewardPoints   = reward_balance((int) $user['userID']);
    $chronicProblems = chronic_health_problem_options();
    $selectedChronicProblems = user_chronic_health_problems($user);
    $memberSince    = isset($user['createdDate']) ? date('d M Y', strtotime((string) $user['createdDate'])) : '—';
    ?>

    <!-- ── Profile Hero ── -->
    <section class="profile-hero">
        <div class="profile-avatar-wrap">
            <?php $avatarUrl = user_avatar_url($user); ?>
            <?php if ($avatarUrl): ?>
                <img class="profile-avatar profile-avatar-img" src="<?= e($avatarUrl) ?>" alt="Profile picture">
            <?php else: ?>
                <div class="profile-avatar">
                    <span><?= e(strtoupper(substr((string) $user['username'], 0, 1))) ?></span>
                </div>
            <?php endif; ?>
            <label class="avatar-upload-btn" title="Change profile picture" for="avatar-quick-upload" id="avatar-upload-label">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
            </label>
            <form method="post" enctype="multipart/form-data" id="avatar-quick-form" style="display:none">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload_avatar">
                <input type="file" name="avatar" id="avatar-quick-upload" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none">
            </form>
        </div>
        <div class="profile-hero-info">
            <h2><?= e($user['username']) ?></h2>
            <span class="status active"><?= e(ucfirst((string) $user['userRole'])) ?></span>
            <p class="muted"><?= e($user['userEmail']) ?></p>
        </div>
        <div class="profile-hero-stats">
            <div class="profile-stat">
                <strong><?= $totalAppts ?></strong>
                <span>Appointments</span>
            </div>
            <div class="profile-stat">
                <strong><?= $completedAppts ?></strong>
                <span>Completed</span>
            </div>
            <div class="profile-stat">
                <strong><?= $rewardPoints ?></strong>
                <span>Points</span>
            </div>
        </div>
    </section>

    <!-- ── Account Info ── -->
    <section class="panel profile-info-panel" id="profile-info-panel">

        <div class="panel-head">
            <h2>Account Info</h2>
            <button class="btn ghost small" type="button" id="btn-edit-profile" onclick="toggleProfileEdit(true)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit
            </button>
        </div>

        <!-- View mode -->
        <div id="profile-view-mode">
            <ul class="profile-info-list">
                <li>
                    <span class="profile-info-label">Email</span>
                    <span><?= e($user['userEmail']) ?></span>
                </li>
                <li>
                    <span class="profile-info-label">Phone</span>
                    <span><?= e($user['userPhone']) ?></span>
                </li>
                <li>
                    <span class="profile-info-label">Age</span>
                    <span><?= $user['userAge'] ? e($user['userAge']) . ' years old' : '<em class="muted">Not set</em>' ?></span>
                </li>
                <li>
                    <span class="profile-info-label">Gender</span>
                    <span class="gender-display">
                        <?php if (!empty($user['userGender']) && $user['userGender'] === 'male'): ?>
                            <span class="gender-badge male">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/><line x1="17" y1="4" x2="21" y2="4"/><line x1="21" y1="4" x2="21" y2="8"/><line x1="17" y1="8" x2="21" y2="4"/></svg>
                                Male
                            </span>
                        <?php elseif (!empty($user['userGender']) && $user['userGender'] === 'female'): ?>
                            <span class="gender-badge female">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/><line x1="12" y1="17" x2="12" y2="21"/><line x1="9" y1="19" x2="15" y2="19"/></svg>
                                Female
                            </span>
                        <?php else: ?>
                            <em class="muted">Not set</em>
                        <?php endif; ?>
                    </span>
                </li>
                <li>
                    <span class="profile-info-label">Role</span>
                    <span><?= e(ucfirst((string) $user['userRole'])) ?></span>
                </li>
                <li>
                    <span class="profile-info-label">Chronic Health Problem</span>
                    <span><?= e(format_user_chronic_health_problems($user)) ?></span>
                </li>
                <li>
                    <span class="profile-info-label">Allergies / Medical</span>
                    <span><?= !empty($user['userAllergies']) ? nl2br(e($user['userAllergies'])) : '<em class="muted">None</em>' ?></span>
                </li>
                <li>
                    <span class="profile-info-label">Member Since</span>
                    <span><?= e($memberSince) ?></span>
                </li>
            </ul>
        </div>

        <!-- Edit mode (hidden by default) -->
        <div id="profile-edit-mode" style="display:none">
            <form method="post" class="form-stack" id="form-edit-profile">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_profile">

                <label for="edit-phone">Phone
                    <input id="edit-phone" name="userPhone" type="tel" value="<?= e($user['userPhone']) ?>" required maxlength="20" autocomplete="tel">
                </label>

                <label for="edit-age">Age
                    <input id="edit-age" name="userAge" type="number" min="1" max="120" value="<?= e($user['userAge'] ?? '') ?>" placeholder="e.g. 25" required>
                </label>

                <div class="gender-field">
                    <span class="gender-field-label">Gender</span>
                    <div class="gender-options">
                        <label class="gender-card" id="edit-gender-male-label">
                            <input type="radio" name="userGender" value="male" id="edit-gender-male" required
                                <?= (!empty($user['userGender']) && $user['userGender'] === 'male') ? 'checked' : '' ?>>
                            <span class="gender-icon male-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/><line x1="17" y1="4" x2="21" y2="4"/><line x1="21" y1="4" x2="21" y2="8"/><line x1="17" y1="8" x2="21" y2="4"/></svg>
                            </span>
                            <span class="gender-label">Male</span>
                        </label>
                        <label class="gender-card" id="edit-gender-female-label">
                            <input type="radio" name="userGender" value="female" id="edit-gender-female" required
                                <?= (!empty($user['userGender']) && $user['userGender'] === 'female') ? 'checked' : '' ?>>
                            <span class="gender-icon female-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/><line x1="12" y1="17" x2="12" y2="21"/><line x1="9" y1="19" x2="15" y2="19"/></svg>
                            </span>
                            <span class="gender-label">Female</span>
                        </label>
                    </div>
                </div>

                <div class="health-profile-field">
                    <span class="field-label">Chronic Health Problem</span>
                    <div class="checkbox-grid">
                        <?php foreach ($chronicProblems as $problem): ?>
                            <label class="check">
                                <input
                                    type="checkbox"
                                    name="chronicHealthProblems[]"
                                    value="<?= e($problem) ?>"
                                    <?= in_array($problem, $selectedChronicProblems, true) ? 'checked' : '' ?>
                                >
                                <?= e($problem) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <label for="edit-allergies">Allergies and Additional Medical History
                    <textarea id="edit-allergies" name="userAllergies" rows="3" placeholder="e.g., Penicillin allergy, currently taking blood thinners..."><?= e($user['userAllergies'] ?? '') ?></textarea>
                </label>

                <div class="profile-edit-actions">
                    <button class="btn primary" type="submit" id="btn-save-profile">Save Changes</button>
                    <button class="btn ghost" type="button" onclick="toggleProfileEdit(false)">Cancel</button>
                </div>
            </form>
        </div>

    </section>


    <script>
    function toggleProfileEdit(show) {
        document.getElementById('profile-view-mode').style.display = show ? 'none' : '';
        document.getElementById('profile-edit-mode').style.display = show ? '' : 'none';
        document.getElementById('btn-edit-profile').style.display  = show ? 'none' : '';
    }
    </script>

    <style>
        /* ── Profile Hero ── */
        .profile-hero {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 1.75rem 2rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        /* ── Circular Avatar ── */
        .profile-avatar-wrap {
            position: relative;
            flex-shrink: 0;
            width: 82px;
            height: 82px;
        }
        .profile-avatar-wrap .profile-avatar { width: 82px; height: 82px; }
        .profile-avatar {
            width: 82px;
            height: 82px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), #49bde8);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
            border: 3px solid var(--primary);
            box-shadow: 0 4px 14px rgba(179,25,127,0.22);
        }
        .profile-avatar-img {
            width: 82px;
            height: 82px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            box-shadow: 0 4px 14px rgba(179,25,127,0.22);
            display: block;
        }
        .avatar-upload-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 28px;
            height: 28px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px solid #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            transition: background .2s;
        }
        .avatar-upload-btn:hover { background: var(--primary-dark); }
        .avatar-upload-btn svg { width: 13px; height: 13px; stroke: #fff; display: block; }

        .profile-hero-info { flex: 1; min-width: 160px; }
        .profile-hero-info h2 { margin: 0 0 .25rem; font-size: 1.25rem; }
        .profile-hero-info p  { margin: .25rem 0 0; font-size: .85rem; }

        .profile-hero-stats { display: flex; gap: 2rem; }
        .profile-stat { text-align: center; }
        .profile-stat strong { display: block; font-size: 1.5rem; font-weight: 700; color: var(--primary); }
        .profile-stat span   { font-size: .75rem; color: var(--muted); }

        /* ── Account Info List ── */
        .profile-info-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
        }
        .profile-info-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: .65rem 0;
            border-bottom: 1px solid var(--line);
            font-size: .9rem;
        }
        .profile-info-list li:last-child { border-bottom: none; }
        .profile-info-label {
            font-weight: 600;
            color: var(--muted);
            min-width: 120px;
        }

        /* ── Edit mode actions row ── */
        .profile-edit-actions {
            display: flex;
            gap: 10px;
            margin-top: 4px;
        }

        /* ── Danger Zone ── */
        .profile-danger-panel { border-color: #ef444433 !important; background: #fff8f8; }
        .profile-danger-panel .panel-head h2 { color: #ef4444; }

        /* ── Gender Badge ── */
        .gender-display { display: flex; }
        .gender-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }
        .gender-badge.male   { background: #e0f2fe; color: #0369a1; }
        .gender-badge.male svg   { stroke: #0369a1; }
        .gender-badge.female { background: #fce7f3; color: #be185d; }
        .gender-badge.female svg { stroke: #be185d; }

        /* ── Gender Selector (edit form) ── */
        .gender-field { display: grid; gap: 7px; }
        .gender-field-label { font-size: 14px; font-weight: 650; color: #4a3351; }
        .gender-options { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .gender-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 14px 10px;
            border: 2px solid #dfc7e6;
            border-radius: 10px;
            cursor: pointer;
            background: #fff;
            transition: border-color .15s, background .15s, box-shadow .15s;
            font-weight: 700;
            font-size: 13px;
            position: relative;
        }
        .gender-card input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
        .gender-card:hover { border-color: var(--primary); background: #fff7fb; }
        .gender-card:has(input:checked) {
            border-color: var(--primary);
            background: #fff0fa;
            box-shadow: 0 0 0 3px rgba(179,25,127,0.12);
        }
        .gender-icon { width: 46px; height: 46px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .gender-icon svg { width: 24px; height: 24px; }
        .male-icon   { background: #e0f2fe; }
        .male-icon svg   { stroke: #0369a1; }
        .female-icon { background: #fce7f3; }
        .female-icon svg { stroke: #be185d; }
        .gender-label { color: var(--ink); }

        /* ── Panel head pencil button ── */
        .panel-head .btn.ghost.small {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
    </style>
    <?php
}
