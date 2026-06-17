<?php
declare(strict_types=1);

function page_manage_staff(array $user): void
{
    $allUsers = db_all(
        'SELECT userID, username, userEmail, userPhone, userRole, status, userAvatar, createdDate
         FROM tbl_user ORDER BY createdDate DESC'
    );

    $totalCount      = count($allUsers);
    $staffAdminCount = 0;
    $patientCount    = 0;
    $inactiveCount   = 0;
    foreach ($allUsers as $u) {
        if (in_array((string) $u['userRole'], ['admin', 'staff'], true)) {
            $staffAdminCount++;
        }
        if ((string) $u['userRole'] === 'patient') {
            $patientCount++;
        }
        if ((string) $u['status'] !== 'active') {
            $inactiveCount++;
        }
    }

    $meID = (int) $user['userID'];
    ?>
    <style>
    .ms-page-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;gap:1rem;flex-wrap:wrap}
    .ms-page-title{font-family:'Sora',sans-serif;font-size:1.35rem;font-weight:700;color:#1a0e2e;margin:0 0 .2rem}
    .ms-page-sub{font-family:'DM Sans',sans-serif;font-size:.85rem;color:#72647a;margin:0}
    .ms-btn-primary{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#3b0764,#5b21b6);color:#fff;border:none;padding:9px 18px;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:700;cursor:pointer;transition:opacity .15s;white-space:nowrap}
    .ms-btn-primary:hover{opacity:.88}
    .ms-btn-primary svg{width:16px;height:16px;flex-shrink:0}

    .ms-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem}
    @media(max-width:900px){.ms-stats{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:500px){.ms-stats{grid-template-columns:1fr}}
    .ms-stat-card{background:#fff;border-radius:14px;padding:1.1rem 1.25rem;box-shadow:0 2px 8px rgba(60,20,90,.07);display:flex;align-items:center;gap:1rem}
    .ms-stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .ms-stat-icon svg{width:22px;height:22px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
    .ms-stat-val{font-family:'Sora',sans-serif;font-size:1.65rem;font-weight:800;color:#1a0e2e;line-height:1;margin-bottom:.15rem}
    .ms-stat-label{font-family:'DM Sans',sans-serif;font-size:.78rem;color:#72647a;font-weight:600}

    .ms-section{background:#fff;border-radius:14px;padding:1.4rem 1.5rem;box-shadow:0 2px 8px rgba(60,20,90,.07);margin-bottom:1.25rem}
    .ms-section-title{font-family:'Sora',sans-serif;font-size:1rem;font-weight:700;color:#1a0e2e;margin:0 0 1.1rem}
    .ms-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:.85rem}
    @media(max-width:650px){.ms-form-grid{grid-template-columns:1fr}}
    .ms-field{display:flex;flex-direction:column;gap:5px}
    .ms-field label{font-family:'DM Sans',sans-serif;font-size:12px;font-weight:700;color:#3b0764;letter-spacing:.02em}
    .ms-input{width:100%;padding:9px 13px;border:1.5px solid #e4d6f4;border-radius:9px;font-family:'DM Sans',sans-serif;font-size:13.5px;color:#1a0e2e;background:#fff;box-sizing:border-box;outline:none;transition:border-color .15s}
    .ms-input:focus{border-color:#7c3aed}
    .ms-input:disabled{background:#f7f4fb;color:#72647a;cursor:not-allowed}
    .ms-pw-wrap{position:relative}
    .ms-pw-wrap .ms-input{padding-right:52px}
    .ms-pw-toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:#7c3aed;font-size:11.5px;font-weight:700;cursor:pointer;padding:2px 4px}
    .ms-form-actions{display:flex;gap:.75rem;align-items:center;margin-top:1rem;flex-wrap:wrap}
    .ms-btn-cancel{background:none;border:1.5px solid #e4d6f4;color:#72647a;padding:9px 18px;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:700;cursor:pointer;transition:border-color .15s}
    .ms-btn-cancel:hover{border-color:#7c3aed;color:#3b0764}

    .ms-alert{padding:10px 16px;border-radius:9px;font-family:'DM Sans',sans-serif;font-size:13.5px;font-weight:600;margin-bottom:1rem;display:none}
    .ms-alert-success{background:#f0fdf4;color:#16845c;border:1px solid #bbf7d0;display:block}
    .ms-alert-error{background:#fff1f0;color:#c0530c;border:1px solid #ffd6c8;display:block}

    .ms-toolbar{display:flex;gap:.75rem;align-items:center;margin-bottom:1rem;flex-wrap:wrap}
    .ms-search-wrap{flex:1;min-width:200px;position:relative}
    .ms-search{width:100%;padding:9px 13px 9px 36px;border:1.5px solid #e4d6f4;border-radius:9px;font-family:'DM Sans',sans-serif;font-size:13.5px;color:#1a0e2e;outline:none;box-sizing:border-box;background:#fff;transition:border-color .15s}
    .ms-search:focus{border-color:#7c3aed}
    .ms-search-icon{position:absolute;left:11px;top:50%;transform:translateY(-50%);width:17px;height:17px;stroke:#72647a;fill:none;stroke-width:2;stroke-linecap:round;pointer-events:none}
    .ms-filter-sel{padding:9px 13px;border:1.5px solid #e4d6f4;border-radius:9px;font-family:'DM Sans',sans-serif;font-size:13px;color:#1a0e2e;background:#fff;outline:none;cursor:pointer;transition:border-color .15s}
    .ms-filter-sel:focus{border-color:#7c3aed}

    .ms-tabs{display:flex;gap:2px;border-bottom:2px solid #e4d6f4;margin-bottom:1rem}
    .ms-tab{background:none;border:none;padding:9px 18px;font-family:'DM Sans',sans-serif;font-size:13.5px;font-weight:600;color:#72647a;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;transition:color .15s,border-color .15s;white-space:nowrap}
    .ms-tab.active{color:#7c3aed;border-bottom-color:#7c3aed}
    .ms-tab:hover:not(.active){color:#3b0764}

    .ms-table-wrap{background:#fff;border-radius:14px;box-shadow:0 2px 8px rgba(60,20,90,.07);overflow-x:auto}
    .ms-table{width:100%;border-collapse:collapse;font-family:'DM Sans',sans-serif}
    .ms-table th{background:#faf5ff;color:#3b0764;font-size:11.5px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding:11px 14px;text-align:left;border-bottom:1px solid #e4d6f4;white-space:nowrap}
    .ms-table td{padding:11px 14px;border-bottom:1px solid #f3ecfb;color:#1a0e2e;font-size:13.5px;vertical-align:middle}
    .ms-table tr:last-child td{border-bottom:none}
    .ms-table tbody tr.ms-row:hover td{background:#fdf9ff}

    .ms-user-cell{display:flex;align-items:center;gap:10px}
    .ms-avatar-img{width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0}
    .ms-avatar-init{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:'Sora',sans-serif;font-size:12px;font-weight:700;color:#fff;flex-shrink:0;background:#7c3aed}
    .ms-av-admin{background:linear-gradient(135deg,#3b0764,#7c3aed)}
    .ms-av-staff{background:linear-gradient(135deg,#0c4a6e,#1686c2)}
    .ms-av-patient{background:linear-gradient(135deg,#065f46,#16845c)}
    .ms-uname{font-weight:600;color:#1a0e2e;line-height:1.3}
    .ms-me-tag{display:inline-block;background:#f0e6ff;color:#7c3aed;font-size:10px;font-weight:800;padding:1px 6px;border-radius:999px;margin-left:5px;vertical-align:middle}

    .ms-badge{display:inline-block;border-radius:999px;padding:3px 10px;font-size:11.5px;font-weight:700;line-height:1.4}
    .ms-role-badge[data-role="admin"]{background:#f5f0ff;color:#7c3aed}
    .ms-role-badge[data-role="staff"]{background:#eff8ff;color:#1686c2}
    .ms-role-badge[data-role="patient"]{background:#f0fdf4;color:#16845c}
    .ms-status-badge[data-status="active"]{background:#f0fdf4;color:#16845c}
    .ms-status-badge[data-status="inactive"]{background:#fff7f0;color:#c0530c}

    .ms-actions{display:flex;gap:5px;flex-wrap:nowrap;align-items:center}
    .ms-btn-sm{padding:5px 11px;border-radius:7px;font-family:'DM Sans',sans-serif;font-size:12px;font-weight:700;cursor:pointer;border:none;transition:opacity .15s;white-space:nowrap}
    .ms-btn-sm:disabled{opacity:.5;cursor:not-allowed}
    .ms-btn-edit{background:#f5f0ff;color:#7c3aed}
    .ms-btn-edit:hover{background:#ede3ff}
    .ms-btn-deactivate{background:#fff7f0;color:#c0530c}
    .ms-btn-deactivate:hover{background:#ffe4d0}
    .ms-btn-activate{background:#f0fdf4;color:#16845c}
    .ms-btn-activate:hover{background:#dcfce7}
    .ms-btn-delete{background:#fff0f0;color:#dc2626}
    .ms-btn-delete:hover{background:#ffd6d6}

    .ms-empty{text-align:center;padding:2.5rem;color:#72647a;font-family:'DM Sans',sans-serif;font-size:14px}

    .ms-modal-overlay{position:fixed;inset:0;background:rgba(10,0,30,.5);display:none;align-items:center;justify-content:center;z-index:999;padding:1rem}
    .ms-modal{background:#fff;border-radius:18px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(60,20,90,.25)}
    .ms-modal-hd{display:flex;align-items:center;justify-content:space-between;padding:1.2rem 1.5rem;border-bottom:1px solid #e4d6f4}
    .ms-modal-title{font-family:'Sora',sans-serif;font-size:1rem;font-weight:700;color:#1a0e2e;margin:0}
    .ms-modal-close{background:none;border:none;font-size:1.5rem;color:#72647a;cursor:pointer;line-height:1;padding:2px 8px;border-radius:6px}
    .ms-modal-close:hover{background:#f3ecfb;color:#3b0764}
    .ms-modal-body{padding:1.3rem 1.5rem}
    .ms-modal-foot{display:flex;gap:.75rem;padding:1rem 1.5rem;border-top:1px solid #e4d6f4;flex-wrap:wrap}
    .ms-modal-alert{padding:8px 13px;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;margin-bottom:.85rem;display:none}
    .ms-modal-alert.success{background:#f0fdf4;color:#16845c;border:1px solid #bbf7d0;display:block}
    .ms-modal-alert.error{background:#fff1f0;color:#c0530c;border:1px solid #ffd6c8;display:block}
    </style>

    <!-- Page header -->
    <div class="ms-page-hd">
        <div>
            <h2 class="ms-page-title">Manage Staff & Users</h2>
            <p class="ms-page-sub">Create and manage user accounts across all roles</p>
        </div>
        <button class="ms-btn-primary" id="msToggleAddBtn" onclick="msToggleAdd()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Staff Account
        </button>
    </div>

    <!-- Stat cards -->
    <div class="ms-stats">
        <div class="ms-stat-card">
            <div class="ms-stat-icon" style="background:linear-gradient(135deg,#3b0764,#7c3aed)">
                <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div>
                <div class="ms-stat-val"><?= $totalCount ?></div>
                <div class="ms-stat-label">Total Users</div>
            </div>
        </div>
        <div class="ms-stat-card">
            <div class="ms-stat-icon" style="background:linear-gradient(135deg,#0c4a6e,#1686c2)">
                <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2M12 12v4m-2-2h4"/></svg>
            </div>
            <div>
                <div class="ms-stat-val"><?= $staffAdminCount ?></div>
                <div class="ms-stat-label">Staff Accounts</div>
            </div>
        </div>
        <div class="ms-stat-card">
            <div class="ms-stat-icon" style="background:linear-gradient(135deg,#065f46,#16845c)">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div>
                <div class="ms-stat-val"><?= $patientCount ?></div>
                <div class="ms-stat-label">Patients</div>
            </div>
        </div>
        <div class="ms-stat-card">
            <div class="ms-stat-icon" style="background:linear-gradient(135deg,#7f1d1d,#dc2626)">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
            </div>
            <div>
                <div class="ms-stat-val"><?= $inactiveCount ?></div>
                <div class="ms-stat-label">Inactive</div>
            </div>
        </div>
    </div>

    <!-- Add staff form (collapsible) -->
    <div class="ms-section" id="msAddSection" style="display:none">
        <h3 class="ms-section-title">Add New Staff Account</h3>
        <div id="msAddAlert" class="ms-alert"></div>
        <form id="msAddForm" onsubmit="msSubmitAdd(event)">
            <div class="ms-form-grid">
                <div class="ms-field">
                    <label for="addUsername">Full Name</label>
                    <input class="ms-input" id="addUsername" name="username" type="text" required maxlength="50" placeholder="e.g. Dr. Sarah Lee">
                </div>
                <div class="ms-field">
                    <label for="addEmail">Email Address</label>
                    <input class="ms-input" id="addEmail" name="userEmail" type="email" required maxlength="100" placeholder="you@example.com">
                </div>
                <div class="ms-field">
                    <label for="addPhone">Phone Number</label>
                    <input class="ms-input" id="addPhone" name="userPhone" type="text" required maxlength="20" placeholder="e.g. 011-1234 5678">
                </div>
                <div class="ms-field">
                    <label for="addRole">Role</label>
                    <select class="ms-input" id="addRole" name="userRole" required>
                        <option value="">— Select Role —</option>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="ms-field">
                    <label for="addPw">Password</label>
                    <div class="ms-pw-wrap">
                        <input class="ms-input" id="addPw" name="userPassword" type="password" required minlength="8" placeholder="Min. 8 chars + 1 number">
                        <button type="button" class="ms-pw-toggle" onclick="const f=document.getElementById('addPw');f.type=f.type==='password'?'text':'password';this.textContent=f.type==='password'?'Show':'Hide'">Show</button>
                    </div>
                </div>
                <div class="ms-field">
                    <label for="addPw2">Confirm Password</label>
                    <div class="ms-pw-wrap">
                        <input class="ms-input" id="addPw2" name="confirmPassword" type="password" required minlength="8" placeholder="Re-enter password">
                        <button type="button" class="ms-pw-toggle" onclick="const f=document.getElementById('addPw2');f.type=f.type==='password'?'text':'password';this.textContent=f.type==='password'?'Show':'Hide'">Show</button>
                    </div>
                </div>
            </div>
            <div class="ms-form-actions">
                <button type="submit" class="ms-btn-primary" id="msAddSubmitBtn">Create Account</button>
                <button type="button" class="ms-btn-cancel" onclick="msToggleAdd()">Cancel</button>
            </div>
        </form>
    </div>

    <!-- Page-level alert (for toggle / delete feedback) -->
    <div id="msPageAlert" class="ms-alert"></div>

    <!-- Toolbar -->
    <div class="ms-toolbar">
        <div class="ms-search-wrap">
            <svg class="ms-search-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input class="ms-search" type="search" id="msSearch" placeholder="Search name or email…" oninput="msFilter()" autocomplete="off">
        </div>
        <select class="ms-filter-sel" id="msRoleFilter" onchange="msFilter()">
            <option value="all">All Roles</option>
            <option value="admin">Admin</option>
            <option value="staff">Staff</option>
            <option value="patient">Patient</option>
        </select>
        <select class="ms-filter-sel" id="msStatusFilter" onchange="msFilter()">
            <option value="all">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>
    </div>

    <!-- Tabs -->
    <div class="ms-tabs">
        <button class="ms-tab active" data-tab="all" onclick="msSetTab('all',this)">
            All Users (<?= $totalCount ?>)
        </button>
        <button class="ms-tab" data-tab="staff_admin" onclick="msSetTab('staff_admin',this)">
            Staff & Admin (<?= $staffAdminCount ?>)
        </button>
        <button class="ms-tab" data-tab="patients" onclick="msSetTab('patients',this)">
            Patients (<?= $patientCount ?>)
        </button>
    </div>

    <!-- User table -->
    <div class="ms-table-wrap">
        <table class="ms-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="msTableBody">
                <?php foreach ($allUsers as $u):
                    $uid       = (int)    $u['userID'];
                    $uRole     = (string) $u['userRole'];
                    $uStatus   = (string) $u['status'];
                    $uName     = (string) $u['username'];
                    $uEmail    = (string) $u['userEmail'];
                    $uPhone    = (string) ($u['userPhone'] ?? '');
                    $uAvatar   = (string) ($u['userAvatar'] ?? '');
                    $uJoined   = (string) ($u['createdDate'] ?? '');
                    $isMe      = $uid === $meID;
                    $isAdmin   = $uRole === 'admin';
                    $avUrl     = $uAvatar !== '' ? 'assets/avatars/' . rawurlencode($uAvatar) : '';
                    $initials  = strtoupper(substr($uName, 0, 2));
                    $avClass   = 'ms-avatar-init ms-av-' . $uRole;
                    $joinedStr = $uJoined !== '' ? date('d M Y', (int) strtotime($uJoined)) : '—';
                ?>
                <tr class="ms-row"
                    data-id="<?= $uid ?>"
                    data-role="<?= e($uRole) ?>"
                    data-status="<?= e($uStatus) ?>"
                    data-search="<?= e(strtolower($uName . ' ' . $uEmail)) ?>"
                    data-name="<?= e($uName) ?>"
                    data-email="<?= e($uEmail) ?>"
                    data-phone="<?= e($uPhone) ?>">
                    <td>
                        <div class="ms-user-cell">
                            <?php if ($avUrl !== ''): ?>
                                <img class="ms-avatar-img" src="<?= e($avUrl) ?>" alt="">
                            <?php else: ?>
                                <div class="<?= e($avClass) ?>"><?= e($initials) ?></div>
                            <?php endif; ?>
                            <div class="ms-uname">
                                <?= e($uName) ?>
                                <?php if ($isMe): ?>
                                    <span class="ms-me-tag">You</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td style="color:#72647a;font-size:13px"><?= e($uEmail) ?></td>
                    <td style="color:#72647a;font-size:13px"><?= e($uPhone !== '' ? $uPhone : '—') ?></td>
                    <td>
                        <span class="ms-badge ms-role-badge" data-role="<?= e($uRole) ?>">
                            <?= e(ucfirst($uRole)) ?>
                        </span>
                    </td>
                    <td>
                        <span class="ms-badge ms-status-badge" data-status="<?= e($uStatus) ?>">
                            <?= e(ucfirst($uStatus)) ?>
                        </span>
                    </td>
                    <td style="color:#72647a;font-size:13px;white-space:nowrap"><?= e($joinedStr) ?></td>
                    <td>
                        <div class="ms-actions">
                            <button class="ms-btn-sm ms-btn-edit" onclick="msOpenEdit(this)">Edit</button>
                            <?php if (!$isAdmin): ?>
                                <button class="ms-btn-sm <?= $uStatus === 'active' ? 'ms-btn-deactivate' : 'ms-btn-activate' ?>"
                                        onclick="msToggleStatus(this)">
                                    <?= $uStatus === 'active' ? 'Deactivate' : 'Activate' ?>
                                </button>
                                <button class="ms-btn-sm ms-btn-delete" onclick="msDeleteUser(this)">Delete</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr id="msEmptyRow" style="display:none">
                    <td colspan="7">
                        <div class="ms-empty">No users match the current filters.</div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Edit modal -->
    <div class="ms-modal-overlay" id="msModal" onclick="if(event.target===this)msCloseEdit()">
        <div class="ms-modal" role="dialog" aria-modal="true" aria-labelledby="msModalTitle">
            <div class="ms-modal-hd">
                <h3 class="ms-modal-title" id="msModalTitle">Edit User</h3>
                <button class="ms-modal-close" onclick="msCloseEdit()" aria-label="Close">&times;</button>
            </div>
            <div class="ms-modal-body">
                <div id="msModalAlert" class="ms-modal-alert"></div>
                <form id="msEditForm" onsubmit="msSubmitEdit(event)">
                    <input type="hidden" id="editUserID" name="userID">
                    <div class="ms-form-grid">
                        <div class="ms-field">
                            <label for="editUsername">Full Name</label>
                            <input class="ms-input" id="editUsername" name="username" type="text" required maxlength="50">
                        </div>
                        <div class="ms-field">
                            <label for="editEmail">Email Address</label>
                            <input class="ms-input" id="editEmail" name="userEmail" type="email" required maxlength="100">
                        </div>
                        <div class="ms-field">
                            <label for="editPhone">Phone Number</label>
                            <input class="ms-input" id="editPhone" name="userPhone" type="text" required maxlength="20">
                        </div>
                        <div class="ms-field">
                            <label for="editRole">Role</label>
                            <select class="ms-input" id="editRole" name="userRole">
                                <option value="admin">Admin</option>
                                <option value="staff">Staff</option>
                                <option value="patient">Patient</option>
                            </select>
                        </div>
                        <div class="ms-field" style="grid-column:1/-1">
                            <label for="editStatus">Status</label>
                            <select class="ms-input" id="editStatus" name="status" style="max-width:260px">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="ms-modal-foot">
                <button type="submit" form="msEditForm" class="ms-btn-primary" id="msEditSubmitBtn">Save Changes</button>
                <button type="button" class="ms-btn-cancel" onclick="msCloseEdit()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
    window.MS_ME_ID = <?= $meID ?>;
    let msCurrentTab = 'all';

    function msToggleAdd() {
        const sec = document.getElementById('msAddSection');
        const btn = document.getElementById('msToggleAddBtn');
        const open = sec.style.display === 'none';
        sec.style.display = open ? 'block' : 'none';
        btn.innerHTML = open
            ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true" style="width:16px;height:16px"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Close Form'
            : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true" style="width:16px;height:16px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Staff Account';
        if (open) document.getElementById('addUsername').focus();
    }

    function msSetTab(tab, btn) {
        msCurrentTab = tab;
        document.querySelectorAll('.ms-tab').forEach(t => t.classList.remove('active'));
        btn.classList.add('active');
        msFilter();
    }

    function msFilter() {
        const search   = document.getElementById('msSearch').value.toLowerCase().trim();
        const roleF    = document.getElementById('msRoleFilter').value;
        const statusF  = document.getElementById('msStatusFilter').value;
        let visible    = 0;

        document.querySelectorAll('#msTableBody .ms-row').forEach(row => {
            const role   = row.dataset.role;
            const status = row.dataset.status;
            const srch   = row.dataset.search;
            let show = true;

            if (msCurrentTab === 'staff_admin' && !['admin','staff'].includes(role)) show = false;
            if (msCurrentTab === 'patients'    && role !== 'patient')                 show = false;
            if (roleF   !== 'all' && role   !== roleF)                               show = false;
            if (statusF !== 'all' && status !== statusF)                             show = false;
            if (search && !srch.includes(search))                                    show = false;

            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        document.getElementById('msEmptyRow').style.display = visible === 0 ? '' : 'none';
    }

    function msOpenEdit(btn) {
        const tr  = btn.closest('tr');
        const uid = parseInt(tr.dataset.id, 10);

        document.getElementById('editUserID').value   = uid;
        document.getElementById('editUsername').value = tr.dataset.name;
        document.getElementById('editEmail').value    = tr.dataset.email;
        document.getElementById('editPhone').value    = tr.dataset.phone;
        document.getElementById('editRole').value     = tr.dataset.role;
        document.getElementById('editStatus').value   = tr.dataset.status;

        const isAdminRow = tr.dataset.role === 'admin';
        const isOwnRow   = uid === window.MS_ME_ID;

        document.getElementById('editStatus').disabled = isAdminRow;
        document.getElementById('editRole').disabled   = isOwnRow;

        const alertEl = document.getElementById('msModalAlert');
        alertEl.className = 'ms-modal-alert';
        alertEl.style.display = 'none';

        document.getElementById('msEditSubmitBtn').disabled = false;
        document.getElementById('msEditSubmitBtn').textContent = 'Save Changes';

        document.getElementById('msModal').style.display = 'flex';
        document.getElementById('editUsername').focus();
    }

    function msCloseEdit() {
        document.getElementById('msModal').style.display = 'none';
    }

    async function msSubmitAdd(e) {
        e.preventDefault();
        const form = document.getElementById('msAddForm');
        const btn  = document.getElementById('msAddSubmitBtn');
        const alertEl = document.getElementById('msAddAlert');
        const fd   = new FormData(form);
        fd.set('_csrf_token', window.DETABOT_CSRF);

        btn.disabled = true;
        btn.textContent = 'Creating…';

        try {
            const res  = await fetch('create_staff.php', { method: 'POST', body: fd });
            const json = await res.json();

            alertEl.className = 'ms-alert ' + (json.success ? 'ms-alert-success' : 'ms-alert-error');
            alertEl.textContent = json.message;

            if (json.success) {
                form.reset();
                setTimeout(() => location.reload(), 900);
            }
        } catch (_) {
            alertEl.className = 'ms-alert ms-alert-error';
            alertEl.textContent = 'Network error. Please try again.';
        }

        btn.disabled = false;
        btn.textContent = 'Create Account';
    }

    async function msSubmitEdit(e) {
        e.preventDefault();
        const form   = document.getElementById('msEditForm');
        const btn    = document.getElementById('msEditSubmitBtn');
        const alertEl = document.getElementById('msModalAlert');
        const fd     = new FormData(form);
        fd.set('_csrf_token', window.DETABOT_CSRF);

        const roleEl   = document.getElementById('editRole');
        const statusEl = document.getElementById('editStatus');
        if (roleEl.disabled)   fd.set('userRole', roleEl.value);
        if (statusEl.disabled) fd.set('status',   statusEl.value);

        btn.disabled = true;
        btn.textContent = 'Saving…';

        try {
            const res  = await fetch('update_user.php', { method: 'POST', body: fd });
            const json = await res.json();

            alertEl.className = 'ms-modal-alert ' + (json.success ? 'success' : 'error');
            alertEl.textContent = json.message;

            if (json.success) {
                setTimeout(() => { msCloseEdit(); location.reload(); }, 700);
            }
        } catch (_) {
            alertEl.className = 'ms-modal-alert error';
            alertEl.textContent = 'Network error. Please try again.';
        }

        btn.disabled = false;
        btn.textContent = 'Save Changes';
    }

    async function msToggleStatus(btn) {
        const tr     = btn.closest('tr');
        const uid    = tr.dataset.id;
        const name   = tr.dataset.name;
        const action = tr.dataset.status === 'active' ? 'deactivate' : 'activate';

        if (!confirm(`Are you sure you want to ${action} "${name}"?`)) return;

        btn.disabled = true;

        const fd = new FormData();
        fd.set('userID', uid);
        fd.set('_csrf_token', window.DETABOT_CSRF);

        try {
            const res  = await fetch('toggle_user_status.php', { method: 'POST', body: fd });
            const json = await res.json();

            if (json.success) {
                const ns = json.newStatus;
                tr.dataset.status = ns;

                const badge = tr.querySelector('.ms-status-badge');
                if (badge) {
                    badge.dataset.status = ns;
                    badge.textContent = ns.charAt(0).toUpperCase() + ns.slice(1);
                }

                btn.className = 'ms-btn-sm ' + (ns === 'active' ? 'ms-btn-deactivate' : 'ms-btn-activate');
                btn.textContent = ns === 'active' ? 'Deactivate' : 'Activate';

                msShowPageAlert(true, `"${name}" has been ${ns === 'active' ? 'activated' : 'deactivated'}.`);
                msFilter();
            } else {
                alert(json.message);
            }
        } catch (_) {
            alert('Network error. Please try again.');
        }

        btn.disabled = false;
    }

    async function msDeleteUser(btn) {
        const tr   = btn.closest('tr');
        const uid  = tr.dataset.id;
        const name = tr.dataset.name;

        if (!confirm(`Permanently delete "${name}"?\n\nThis action cannot be undone.`)) return;

        btn.disabled = true;

        const fd = new FormData();
        fd.set('userID', uid);
        fd.set('_csrf_token', window.DETABOT_CSRF);

        try {
            const res  = await fetch('delete_user.php', { method: 'POST', body: fd });
            const json = await res.json();

            if (json.success) {
                tr.style.transition = 'opacity .25s';
                tr.style.opacity    = '0';
                setTimeout(() => { tr.remove(); msFilter(); }, 260);
                msShowPageAlert(true, `"${name}" has been deleted.`);
            } else {
                alert(json.message);
                btn.disabled = false;
            }
        } catch (_) {
            alert('Network error. Please try again.');
            btn.disabled = false;
        }
    }

    function msShowPageAlert(success, message) {
        const el = document.getElementById('msPageAlert');
        el.className = 'ms-alert ' + (success ? 'ms-alert-success' : 'ms-alert-error');
        el.textContent = message;
        clearTimeout(el._hide);
        el._hide = setTimeout(() => { el.className = 'ms-alert'; el.textContent = ''; }, 5000);
    }

    // Run on load to show empty state if no users exist
    msFilter();
    </script>
    <?php
}
