<?php
declare(strict_types=1);

function page_activity(array $user): void
{
    $logs = db_all(
        "SELECT l.*, u.username
         FROM tbl_activity_log l
         LEFT JOIN tbl_user u ON u.userID = l.userID
         ORDER BY l.createdDate DESC
         LIMIT 120"
    );
    ?>
    <section class="panel">
        <div class="panel-head"><h2>System Activity</h2></div>
        <?php if (!$logs): ?>
            <p class="empty">No activity recorded yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Date</th><th>User</th><th>Action</th><th>Details</th></tr></thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= e($log['createdDate']) ?></td>
                                <td><?= e($log['username'] ?? 'System') ?></td>
                                <td><?= e($log['action']) ?></td>
                                <td><?= e($log['details']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <?php
}
