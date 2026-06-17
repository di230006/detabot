<?php
declare(strict_types=1);

function page_users(array $user): void
{
    $editing = isset($_GET['edit'])
        ? db_one('SELECT * FROM tbl_user WHERE userID = ?', [(int) $_GET['edit']])
        : null;
    $users = db_all('SELECT * FROM tbl_user ORDER BY userRole ASC, username ASC');
    ?>
    <section class="two-column align-start">
        <div class="panel">
            <div class="panel-head"><h2><?= $editing ? 'Edit User' : 'Create User' ?></h2></div>
            <form method="post" class="form-grid">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_user">
                <input type="hidden" name="userID" value="<?= e($editing['userID'] ?? 0) ?>">
                <label class="span-2">Full Name <input name="username" value="<?= e($editing['username'] ?? '') ?>" required></label>
                <label class="span-2">Email <input inputmode="email" name="userEmail" value="<?= e($editing['userEmail'] ?? '') ?>" required></label>
                <label>Phone <input name="userPhone" value="<?= e($editing['userPhone'] ?? '') ?>" required></label>
                <label>Age <input type="number" name="userAge" min="1" max="120" value="<?= e($editing['userAge'] ?? '') ?>" placeholder="e.g. 25"></label>
                <label>Role
                    <select name="userRole">
                        <?php foreach (['patient', 'staff', 'admin'] as $role): ?>
                            <option value="<?= e($role) ?>" <?= ($editing['userRole'] ?? 'patient') === $role ? 'selected' : '' ?>><?= e(ucfirst($role)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Status
                    <select name="status">
                        <?php foreach (['active', 'inactive'] as $status): ?>
                            <option value="<?= e($status) ?>" <?= ($editing['status'] ?? 'active') === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="span-2">Password <input type="password" name="userPassword" placeholder="<?= $editing ? 'Leave blank to keep current password' : '' ?>"></label>
                <button class="btn primary span-2" type="submit"><?= $editing ? 'Update User' : 'Create User' ?></button>
            </form>
        </div>

        <div class="panel">
            <div class="panel-head"><h2>User Summary</h2></div>
            <div class="mini-stats">
                <span>Patients <strong><?= e(db_one("SELECT COUNT(*) AS total FROM tbl_user WHERE userRole = 'patient'")['total']) ?></strong></span>
                <span>Staff <strong><?= e(db_one("SELECT COUNT(*) AS total FROM tbl_user WHERE userRole = 'staff'")['total']) ?></strong></span>
                <span>Admins <strong><?= e(db_one("SELECT COUNT(*) AS total FROM tbl_user WHERE userRole = 'admin'")['total']) ?></strong></span>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Users</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Age</th><th>Role</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($users as $row): ?>
                        <tr>
                            <td><?= e($row['username']) ?></td>
                            <td><?= e($row['userEmail']) ?></td>
                            <td><?= e($row['userPhone']) ?></td>
                            <td><?= $row['userAge'] ? e($row['userAge']) . ' yrs' : '<span class="muted">—</span>' ?></td>
                            <td><?= e($row['userRole']) ?></td>
                            <td><span class="status <?= e($row['status']) ?>"><?= e($row['status']) ?></span></td>
                            <td class="row-actions">
                                <a class="btn small" href="<?= e(page_url('users', ['edit' => $row['userID']])) ?>">Edit</a>
                                <?php if ((int) $row['userID'] !== (int) $user['userID'] && $row['status'] === 'active'): ?>
                                    <form method="post" data-confirm="Deactivate this user?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="userID" value="<?= e($row['userID']) ?>">
                                        <button class="btn small danger" type="submit">Deactivate</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
}
