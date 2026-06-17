<?php
declare(strict_types=1);

function page_healthbook(array $user): void
{
    $canManage = has_role($user, ['admin', 'staff']);
    $patients = $canManage ? health_book_all_patients() : [];
    $selectedPatientID = $canManage
        ? (int) ($_GET['patient'] ?? ($patients[0]['userID'] ?? 0))
        : (int) $user['userID'];
    $selectedPatient = $canManage ? health_book_selected_patient($patients, $selectedPatientID) : $user;
    $entries = $selectedPatientID > 0 ? health_book_view_entries($selectedPatientID) : [];
    $appointments = $canManage && $selectedPatientID > 0 ? health_book_patient_appointments($selectedPatientID) : [];
    $fileCount = $selectedPatientID > 0
        ? (int) db_one(
            'SELECT COUNT(*) AS total FROM tbl_health_book_file f JOIN tbl_health_book h ON h.entryID = f.entryID WHERE h.userID = ?',
            [$selectedPatientID]
        )['total']
        : 0;
    $nextFollowUp = $selectedPatientID > 0
        ? db_one('SELECT nextDate FROM tbl_health_book WHERE userID = ? AND nextDate IS NOT NULL AND nextDate >= ? ORDER BY nextDate ASC LIMIT 1', [$selectedPatientID, date('Y-m-d')])
        : null;
    ?>
    
    <?php if ($selectedPatientID > 0 && $selectedPatient): ?>
    <section class="panel patient-info-panel">
        <div class="panel-head"><h2>Patient Info: <?= e($selectedPatient['username']) ?></h2></div>
        <div class="patient-info-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 10px;">
            <div class="info-group">
                <span class="info-label" style="font-weight:600; color:var(--muted); display:block;">Phone:</span>
                <span class="info-value"><?= e($selectedPatient['userPhone'] ?? '-') ?></span>
            </div>
            <div class="info-group">
                <span class="info-label" style="font-weight:600; color:var(--muted); display:block;">Age / Gender:</span>
                <span class="info-value"><?= e($selectedPatient['userAge'] ?? '-') ?> / <?= e(ucfirst($selectedPatient['userGender'] ?? '-')) ?></span>
            </div>
            <div class="info-group">
                <span class="info-label" style="font-weight:600; color:var(--muted); display:block;">Chronic Problems:</span>
                <span class="info-value"><?= e(format_user_chronic_health_problems($selectedPatient)) ?></span>
            </div>
            <div class="info-group">
                <span class="info-label" style="font-weight:600; color:var(--muted); display:block;">Allergies / Medical History:</span>
                <span class="info-value" style="color:var(--danger); font-weight:600;"><?= !empty($selectedPatient['userAllergies']) ? nl2br(e($selectedPatient['userAllergies'])) : '<em class="muted" style="color:var(--ink)">None reported</em>' ?></span>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="grid metrics-grid">
        <?php metric_card('Health Entries', count($entries), 'teal'); ?>
        <?php metric_card('Attached Files', $fileCount, 'blue'); ?>
        <?php metric_card('Next Follow-up', $nextFollowUp['nextDate'] ?? '-', 'amber'); ?>
    </section>

    <?php if ($canManage): ?>
        <section class="two-column align-start">
            <div class="panel">
                <div class="panel-head"><h2>Patient Records</h2></div>
                <?php if (!$patients): ?>
                    <p class="empty">No active patients found.</p>
                <?php else: ?>
                    <form class="form-stack" method="get">
                        <label>Patient
                            <select name="patient" onchange="this.form.submit()">
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?= e($patient['userID']) ?>" <?= (int) $patient['userID'] === $selectedPatientID ? 'selected' : '' ?>>
                                        <?= e($patient['username']) ?> - <?= e($patient['userEmail']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </form>
                <?php endif; ?>
            </div>

            <div class="panel">
                <div class="panel-head"><h2>Add Entry</h2></div>
                <?php if ($selectedPatient): ?>
                    <form method="post" class="form-grid" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_health_entry">
                        <input type="hidden" name="userID" value="<?= e($selectedPatientID) ?>">
                        <label class="span-2">Appointment
                            <select name="appointmentID">
                                <option value="">No linked appointment</option>
                                <?php foreach ($appointments as $appointment): ?>
                                    <option value="<?= e($appointment['appointmentID']) ?>">
                                        <?= e($appointment['appointmentDate']) ?> <?= e(substr((string) $appointment['appointmentTime'], 0, 5)) ?> - <?= e($appointment['serviceType']) ?> (<?= e($appointment['status']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="span-2">Title
                            <input name="title" maxlength="200" required placeholder="Treatment summary or diagnosis">
                        </label>
                        <label class="span-2">Condition
                            <textarea name="condition" rows="4" required></textarea>
                        </label>
                        
                        <label class="span-2">Treatments Performed
                            <textarea name="treatmentPerformed" rows="2" placeholder="e.g. Fillings on 46, Scaling & Polishing"></textarea>
                        </label>

                        <div class="span-2 dental-charting-container">
                            <span class="field-label" style="display:block; margin-bottom:8px; font-weight:600;">Dental Charting</span>
                            <input type="hidden" name="chartData" id="dentalChartData" value="{}">
                            <div id="dental-chart-ui" class="dental-chart-ui"></div>
                        </div>

                        <label>Next Treatment
                            <input name="nextTreatment" maxlength="200">
                        </label>
                        <label>Next Date
                            <input type="date" name="nextDate" min="<?= e(date('Y-m-d')) ?>">
                        </label>
                        <label class="span-2">Notes
                            <textarea name="notes" rows="3"></textarea>
                        </label>
                        <div class="span-2">
                            <span class="field-label" style="display:block; margin-bottom:8px; font-weight:600;">Attach Imaging / Files</span>
                            <div id="file-uploads-container">
                                <div class="upload-row" style="display:flex; gap:10px; margin-bottom:10px; align-items:center;">
                                    <select name="fileTypes[]" style="width:150px;">
                                        <option value="xray">X-Ray</option>
                                        <option value="cbct">CBCT Scan</option>
                                        <option value="intraoral">Intraoral Photo</option>
                                        <option value="document">Document</option>
                                        <option value="other">Other</option>
                                    </select>
                                    <input type="file" name="files[]" accept=".pdf,image/jpeg,image/png,image/webp">
                                </div>
                            </div>
                            <button type="button" class="btn ghost small" onclick="addFileUploadRow()">+ Add Another File</button>
                            <small class="muted" style="display:block; margin-top:5px;">PDF, JPG, PNG, or WEBP up to 5 MB each.</small>
                        </div>
                        <button class="btn primary span-2" type="submit">Save Entry</button>
                    </form>
                <?php else: ?>
                    <p class="empty">Choose a patient before adding an entry.</p>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-head">
            <h2><?= $canManage && $selectedPatient ? e($selectedPatient['username']) . ' Health Record' : 'My Health Record' ?></h2>
        </div>
        <?php render_health_book_entries($entries, $canManage); ?>
    </section>
    <?php
}

function health_book_selected_patient(array $patients, int $selectedPatientID): ?array
{
    foreach ($patients as $patient) {
        if ((int) $patient['userID'] === $selectedPatientID) {
            return $patient;
        }
    }

    return $patients[0] ?? null;
}

function health_book_view_entries(int $userID): array
{
    return db_all(
        "SELECT h.*, staff.username AS staffName, a.appointmentDate, a.appointmentTime, a.serviceType
         FROM tbl_health_book h
         JOIN tbl_user staff ON staff.userID = h.createdBy
         LEFT JOIN tbl_appointment a ON a.appointmentID = h.appointmentID
         WHERE h.userID = ?
         ORDER BY h.createdDate DESC",
        [$userID]
    );
}

function health_book_patient_appointments(int $userID): array
{
    return db_all(
        "SELECT appointmentID, appointmentDate, appointmentTime, serviceType, status
         FROM tbl_appointment
         WHERE userID = ?
         ORDER BY appointmentDate DESC, appointmentTime DESC
         LIMIT 50",
        [$userID]
    );
}

function render_health_book_entries(array $entries, bool $canManage): void
{
    if (!$entries) {
        echo '<p class="empty">No health record entries yet.</p>';
        return;
    }
    ?>
    <div class="healthbook-list">
        <?php foreach ($entries as $entry): ?>
            <?php $files = health_book_files((int) $entry['entryID']); ?>
            <article class="healthbook-entry">
                <header>
                    <div>
                        <strong><?= e($entry['title']) ?></strong>
                        <span><?= e($entry['createdDate']) ?> by <?= e($entry['staffName']) ?></span>
                    </div>
                    <?php if (!empty($entry['nextDate'])): ?>
                        <span class="status pending">Next <?= e($entry['nextDate']) ?></span>
                    <?php endif; ?>
                </header>

                <dl class="info-list">
                    <dt>Condition</dt>
                    <dd><?= nl2br(e($entry['condition'])) ?></dd>
                    <dt>Appointment</dt>
                    <dd>
                        <?php if (!empty($entry['appointmentDate'])): ?>
                            <?= e($entry['appointmentDate']) ?> <?= e(substr((string) $entry['appointmentTime'], 0, 5)) ?> - <?= e($entry['serviceType']) ?>
                        <?php else: ?>
                            <span class="muted">Not linked</span>
                        <?php endif; ?>
                    </dd>
                    <dt>Treatment Plan</dt>
                    <dd><?= e($entry['nextTreatment'] ?: '-') ?></dd>
                    <?php if (!empty($entry['treatmentPerformed'])): ?>
                        <dt>Treatments Performed</dt>
                        <dd><?= nl2br(e($entry['treatmentPerformed'])) ?></dd>
                    <?php endif; ?>
                    <dt>Notes</dt>
                    <dd><?= $entry['notes'] ? nl2br(e($entry['notes'])) : '<span class="muted">-</span>' ?></dd>
                </dl>
                
                <?php if (!empty($entry['chartData']) && $entry['chartData'] !== '{}'): ?>
                    <div class="saved-dental-chart-wrapper" style="margin: 15px 0;">
                        <strong>Dental Chart:</strong>
                        <div class="saved-dental-chart" data-chart='<?= e($entry['chartData']) ?>'></div>
                    </div>
                <?php endif; ?>

                <?php if ($files): ?>
                    <div class="file-gallery" style="margin-top: 15px; border-top: 1px solid var(--line); padding-top: 10px;">
                        <?php 
                        $groupedFiles = ['xray' => [], 'cbct' => [], 'intraoral' => [], 'document' => [], 'other' => []];
                        foreach ($files as $f) {
                            $type = $f['fileType'] ?? 'other';
                            if (!isset($groupedFiles[$type])) $type = 'other';
                            $groupedFiles[$type][] = $f;
                        }
                        $typeLabels = ['xray' => 'X-Rays', 'cbct' => 'CBCT Scans', 'intraoral' => 'Intraoral Photos', 'document' => 'Documents', 'other' => 'Other Files'];
                        ?>
                        <?php foreach ($groupedFiles as $type => $typeFiles): ?>
                            <?php if ($typeFiles): ?>
                                <div class="gallery-section" style="margin-bottom: 15px;">
                                    <h4 style="margin: 0 0 8px 0; font-size: 13px; color: var(--muted); text-transform: uppercase;"><?= $typeLabels[$type] ?></h4>
                                    <div class="file-list" style="display:flex; flex-wrap:wrap; gap:10px;">
                                        <?php foreach ($typeFiles as $file): ?>
                                            <span class="file-chip" style="background:#f1f5f9; padding:8px 12px; border-radius:6px; display:inline-flex; align-items:center; gap:8px;">
                                                <span style="font-weight:600;">
                                                    <?= e($file['originalName']) ?>
                                                </span>
                                                <small style="color:var(--muted)"><?= e(health_book_file_size((int) $file['fileSize'])) ?></small>
                                                <div style="display:flex; gap: 4px; margin-left: 4px;">
                                                    <a href="<?= e('uploads/healthbook/' . rawurlencode((string) $file['storedName'])) ?>" target="_blank" rel="noopener" class="btn small ghost" style="padding: 2px 8px; font-size: 11px; text-decoration:none;">View</a>
                                                    <a href="<?= e('uploads/healthbook/' . rawurlencode((string) $file['storedName'])) ?>" download="<?= e($file['originalName']) ?>" class="btn small primary" style="padding: 2px 8px; font-size: 11px; text-decoration:none;">Download</a>
                                                </div>
                                                <?php if ($canManage): ?>
                                                    <form method="post" data-confirm="Remove this file?" style="margin:0;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="delete_health_file">
                                                        <input type="hidden" name="fileID" value="<?= e($file['fileID']) ?>">
                                                        <button class="btn small danger" type="submit" style="padding: 2px 6px; font-size: 11px;">Remove</button>
                                                    </form>
                                                <?php endif; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($canManage): ?>
                    <form class="healthbook-entry-actions" method="post" data-confirm="Delete this health record entry?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_health_entry">
                        <input type="hidden" name="entryID" value="<?= e($entry['entryID']) ?>">
                        <button class="btn small danger" type="submit">Delete Entry</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
    <?php
}

function health_book_file_size(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return $bytes . ' B';
}
