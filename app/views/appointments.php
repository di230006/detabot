<?php
declare(strict_types=1);

if (!function_exists('extract_dentist_name')) {
    function extract_dentist_name(string $notes): string
    {
        if (preg_match('/^Dentist:\s*(.+)$/m', $notes, $m)) {
            return trim($m[1]);
        }
        return 'Dental Team';
    }
}

function page_appointments(array $user): void
{
    if (has_role($user, ['admin', 'staff'])) {
        page_appointments_staff($user);
        return;
    }

    $uid   = (int) $user['userID'];
    $today = date('Y-m-d');

    $appointments = db_all(
        "SELECT a.*, c.clinicName FROM tbl_appointment a
         JOIN tbl_clinic c ON c.clinicID = a.clinicID
         WHERE a.userID = ?
         ORDER BY a.appointmentDate DESC, a.appointmentTime DESC",
        [$uid]
    );

    $totalCount     = count($appointments);
    $pendingCount   = count(array_filter($appointments, fn($a) => $a['status'] === 'pending'));
    $completedCount = count(array_filter($appointments, fn($a) => $a['status'] === 'completed'));

    $nextAppt = db_one(
        "SELECT * FROM tbl_appointment
         WHERE userID = ? AND status IN ('pending','confirmed') AND appointmentDate >= ?
         ORDER BY appointmentDate ASC, appointmentTime ASC LIMIT 1",
        [$uid, $today]
    );

    $dentists = [
        ['name' => 'Dr. Muhammad Firdaus', 'image' => 'assets/dentist-dr-muhammad-firdaus.png', 'spec' => 'General Dentist'],
        ['name' => 'Dr. Siti Zafirah',     'image' => 'assets/dentist-dr-siti-zafirah.png',     'spec' => 'Cosmetic Specialist'],
        ['name' => 'Dr. Alia Suhana',      'image' => 'assets/dentist-dr-alia-suhana.png',      'spec' => 'Orthodontist'],
    ];

    $date          = normalize_appointment_date($_GET['date'] ?? null);
    $clinicID      = (int) (clinic()['clinicID'] ?? 1);
    $slots         = appointment_slot_list($date, $clinicID);
    $serviceGroups = group_services_by_category(clinic_services_with_duration());
    $catNames      = array_keys($serviceGroups);
    $firstCat      = (string) ($catNames[0] ?? '');
    $commonProbs   = common_health_problem_options();
    $timeSlots     = build_time_slots();
    ?>

<style>
.appt-summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
@media(max-width:900px){.appt-summary-grid{grid-template-columns:repeat(2,1fr)}}
.summary-card{background:#fff;border-radius:12px;border:1px solid #ede8f8;padding:18px 20px;box-shadow:0 2px 10px rgba(59,7,100,.06)}
.summary-icon-box{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:12px}
.summary-icon-box.purple{background:#f3f0ff}.summary-icon-box.amber{background:#fff8e6}.summary-icon-box.green{background:#eaf3de}.summary-icon-box.blue{background:#e8f4fd}
.summary-number{font-family:'Sora',sans-serif;font-size:28px;font-weight:700;color:#1a0e2e;line-height:1;margin-bottom:4px}
.summary-label{font-size:12.5px;color:#72647a;font-weight:500}
.summary-sub{font-size:11.5px;color:#7c3aed;margin-top:4px;font-weight:600}

.filter-tabs-row{display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap}
.filter-tab{padding:7px 18px;border-radius:20px;border:1.5px solid #ede8f8;background:#fff;color:#72647a;font-size:13px;font-weight:600;cursor:pointer;transition:all .18s;font-family:'DM Sans',sans-serif}
.filter-tab:hover{border-color:#7c3aed;color:#7c3aed}
.filter-tab.active{background:#7c3aed;border-color:#7c3aed;color:#fff}

.appt-cards-list{display:flex;flex-direction:column;gap:14px}
.appt-card{background:#fff;border-radius:12px;border:1.5px solid #ede8f8;padding:16px 20px;display:flex;align-items:flex-start;gap:16px;transition:box-shadow .18s}
.appt-card:hover{box-shadow:0 4px 18px rgba(124,58,237,.10)}
.appt-date-box{border-radius:10px;padding:10px 14px;text-align:center;min-width:56px;flex-shrink:0}
.appt-date-box.pending,.appt-date-box.confirmed{background:#eeedfe}
.appt-date-box.completed{background:#eaf3de}
.appt-date-box.cancelled{background:#fcebeb}
.appt-date-box .aday{font-family:'Sora',sans-serif;font-size:22px;font-weight:700;color:#3b0764;line-height:1}
.appt-date-box .amon{font-size:10.5px;font-weight:700;letter-spacing:.05em;color:#7c3aed;text-transform:uppercase;margin-top:3px}
.appt-date-box.completed .aday,.appt-date-box.completed .amon{color:#16845c}
.appt-date-box.cancelled .aday,.appt-date-box.cancelled .amon{color:#b42318}
.appt-card-body{flex:1;min-width:0}
.appt-card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:8px}
.appt-card-service{font-weight:700;font-size:15px;color:#1a0e2e}
.appt-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:100px;font-size:11.5px;font-weight:600;white-space:nowrap;flex-shrink:0}
.appt-badge.pending{background:#fff8e6;color:#c77712}
.appt-badge.confirmed{background:#e8f4fd;color:#1686c2}
.appt-badge.completed{background:#eaf3de;color:#16845c}
.appt-badge.cancelled{background:#fcebeb;color:#b42318}
.appt-card-meta{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:12px}
.appt-meta-item{display:flex;align-items:center;gap:5px;font-size:12.5px;color:#72647a}
.appt-meta-item svg{width:13px;height:13px;stroke:#7c3aed;flex-shrink:0}
.appt-card-actions{display:flex;gap:8px;flex-wrap:wrap}
.aab{padding:5px 14px;border-radius:8px;font-size:12.5px;font-weight:600;cursor:pointer;border:1.5px solid;transition:all .15s;font-family:'DM Sans',sans-serif;background:transparent}
.aab.purple{border-color:#7c3aed;color:#7c3aed}.aab.purple:hover{background:#7c3aed;color:#fff}
.aab.red{border-color:#ef4444;color:#ef4444}.aab.red:hover{background:#ef4444;color:#fff}
.aab.green{border-color:#16845c;color:#16845c}.aab.green:hover{background:#16845c;color:#fff}
.aab.blue{border-color:#1686c2;color:#1686c2}.aab.blue:hover{background:#1686c2;color:#fff}

.appt-empty{text-align:center;padding:48px 24px;color:#72647a}
.appt-empty svg{width:52px;height:52px;stroke:#c4b8d4;margin-bottom:14px;display:block;margin-left:auto;margin-right:auto}
.appt-empty h3{font-size:16px;font-weight:600;color:#3b0764;margin:0 0 8px}
.appt-empty p{font-size:13.5px;margin:0 0 18px}

/* Stepper */
.appt-stepper{display:flex;align-items:flex-start;margin-bottom:32px;overflow-x:auto;padding-bottom:4px}
.step-wrap{display:flex;flex-direction:column;align-items:center;gap:5px}
.step-line{flex:1;height:3px;border-radius:2px;margin:0 8px;margin-top:16px;transition:background .2s}
.step-line.done{background:#7c3aed}.step-line.inactive{background:#e9e0f4}
.step-circle{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;transition:all .2s}
.step-circle.done{background:#eaf3de;color:#16845c}
.step-circle.active{background:#7c3aed;color:#fff;box-shadow:0 0 0 4px rgba(124,58,237,.2)}
.step-circle.inactive{background:#e9e0f4;color:#72647a}
.step-lbl{font-size:11px;font-weight:600;white-space:nowrap}
.step-lbl.done{color:#16845c}.step-lbl.active{color:#7c3aed}.step-lbl.inactive{color:#72647a}

/* Wizard steps */
.wiz-step{display:none}.wiz-step.active{display:block}
.wiz-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:24px;padding-top:16px;border-top:1px solid #ede8f8}
.wiz-actions.split{justify-content:space-between}

/* Form */
.fg{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
.flbl{font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#72647a}
.finput{width:100%;padding:10px 12px;border:1.5px solid #e5ddf5;border-radius:8px;font-size:14px;font-family:'DM Sans',sans-serif;color:#1a0e2e;background:#fff;outline:none;transition:border-color .18s;box-sizing:border-box}
.finput:focus{border-color:#7c3aed}.finput[readonly]{background:#f9f7ff;color:#72647a}
select.finput{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2372647a' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:36px}

/* Treatment info */
.treat-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:12px 0}
.treat-info-box{border-radius:10px;padding:12px 16px;display:flex;flex-direction:column;gap:3px}
.treat-info-box.dur{background:#eeedfe;border:1px solid #d8d0fc}
.treat-info-box.pri{background:#eaf3de;border:1px solid #c6e3b0}
.treat-info-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#72647a}
.treat-info-val{font-size:15px;font-weight:700}
.treat-info-box.dur .treat-info-val{color:#5b21b6}
.treat-info-box.pri .treat-info-val{color:#16845c}

/* Dentist cards */
.dentist-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:8px}
@media(max-width:700px){.dentist-grid{grid-template-columns:1fr}}
.dentist-lbl{display:block;cursor:pointer}
.dentist-lbl input{display:none}
.dentist-inner{border:2px solid #e9e0f4;border-radius:12px;padding:14px;text-align:center;transition:all .18s}
.dentist-lbl input:checked + .dentist-inner{border-color:#7c3aed;background:#f5f0ff}
.dentist-avatar{width:60px;height:60px;border-radius:50%;object-fit:cover;margin:0 auto 8px;display:block;background:#e9e0f4}
.dentist-name{font-weight:700;font-size:13px;color:#1a0e2e;display:block}
.dentist-spec{font-size:11.5px;color:#72647a}

/* Review */
.rev-card{background:#faf8ff;border:1.5px solid #e0d5f5;border-radius:12px;padding:18px;margin-bottom:16px}
.rev-card h4{font-size:12px;font-weight:700;color:#7c3aed;margin:0 0 12px;text-transform:uppercase;letter-spacing:.06em}
.rev-row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #ede8f8;font-size:13px;gap:12px}
.rev-row:last-child{border-bottom:none}
.rev-row dt{color:#72647a;flex-shrink:0}
.rev-row dd{font-weight:600;color:#1a0e2e;text-align:right;margin:0}

/* Payment info */
.pay-info-card{background:#faf8ff;border:1.5px solid #e0d5f5;border-radius:12px;padding:24px;max-width:440px;margin:0 auto}
.pay-info-card h3{font-size:16px;font-weight:700;color:#3b0764;margin:0 0 12px;text-align:center}
.pay-method{display:flex;align-items:center;gap:8px;padding:8px 12px;background:#fff;border-radius:8px;border:1px solid #e5ddf5;font-size:13px;color:#3b0764;font-weight:500;margin-bottom:8px}
.pay-amount{text-align:center;background:#7c3aed;color:#fff;border-radius:10px;padding:14px;margin:16px 0;font-size:22px;font-weight:700;font-family:'Sora',sans-serif}
.pay-reminder{background:#fff8e6;border:1px solid #f5d78f;border-radius:8px;padding:10px 14px;font-size:12.5px;color:#c77712;text-align:center;font-weight:500}

/* Modals */
.appt-overlay{position:fixed;inset:0;background:rgba(20,10,40,.55);z-index:1000;display:flex;align-items:center;justify-content:center;padding:24px;backdrop-filter:blur(2px)}
.appt-overlay[hidden]{display:none}
.appt-modal{background:#fff;border-radius:16px;padding:28px;width:min(480px,100%);max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(59,7,100,.22)}
.modal-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
.modal-hd h3{font-size:17px;font-weight:700;color:#1a0e2e;margin:0}
.modal-close-btn{background:none;border:none;cursor:pointer;color:#72647a;padding:4px;border-radius:6px;display:flex;align-items:center;justify-content:center;transition:background .15s}
.modal-close-btn:hover{background:#f0ebf8}
.modal-close-btn svg{width:18px;height:18px}
.modal-acts{display:flex;gap:10px;justify-content:flex-end;margin-top:18px}
.mbtn-pri{padding:9px 22px;background:#7c3aed;color:#fff;border:none;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;font-family:'DM Sans',sans-serif;transition:background .15s}
.mbtn-pri:hover{background:#6d28d9}
.mbtn-sec{padding:9px 22px;background:#f5f0ff;color:#7c3aed;border:1.5px solid #e0d5f5;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .15s}
.mbtn-sec:hover{background:#ede8f8}
.mbtn-danger{padding:9px 22px;background:#ef4444;color:#fff;border:none;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;font-family:'DM Sans',sans-serif;transition:background .15s}
.mbtn-danger:hover{background:#dc2626}

/* Stars */
.star-row{display:flex;gap:6px;margin-bottom:16px}
.star-row button{background:none;border:none;font-size:28px;cursor:pointer;color:#ddd;transition:color .15s,transform .1s;padding:0;line-height:1}
.star-row button:hover,.star-row button.lit{color:#f59e0b;transform:scale(1.15)}

/* AJAX msg */
.amsg{padding:11px 15px;border-radius:8px;font-size:13px;font-weight:500;margin-bottom:14px;display:none}
.amsg.ok{background:#eaf3de;color:#16845c;border:1px solid #b6dfa0}
.amsg.err{background:#fcebeb;color:#b42318;border:1px solid #f3b8b8}

/* Section head */
.sec-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
.sec-hd h2{font-family:'Sora',sans-serif;font-size:17px;font-weight:700;color:#1a0e2e;margin:0}

/* Chatbot override */
.chatbot-quick-replies{flex-wrap:wrap}

/* ── Step 4 Payment Info ── */
.pay4-success-banner{background:#eaf3de;border-radius:12px;padding:28px 24px;text-align:center;margin-bottom:20px}
.pay4-success-icon-circle{width:60px;height:60px;background:#16845c;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px}
.pay4-success-hdg{font-family:'Sora',sans-serif;font-size:18px;font-weight:700;color:#16845c;margin:0 0 6px}
.pay4-success-sub{font-size:13.5px;color:#3b6c3b;margin:0}
.pay4-info-card{background:#faf8ff;border:1.5px solid #e0d5f5;border-radius:12px;padding:20px;margin-bottom:16px}
.pay4-card-title{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#3b0764;margin:0 0 14px}
.pay4-sum-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid #ede8f8;font-size:13px}
.pay4-sum-row:last-child{border-bottom:none}
.pay4-sum-lbl{color:#72647a}
.pay4-sum-val{font-weight:600;color:#1a0e2e;text-align:right}
.pay4-amount-box{background:#f4f3ff;border-radius:10px;padding:18px;text-align:center;margin-bottom:14px}
.pay4-amount-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#7c3aed;margin-bottom:4px}
.pay4-amount-val{font-family:'Sora',sans-serif;font-size:36px;font-weight:700;color:#7c3aed;line-height:1.1}
.pay4-notice{background:#fff8e6;border-left:4px solid #f97316;border-radius:0 8px 8px 0;padding:12px 16px;margin-bottom:16px}
.pay4-notice strong{display:block;font-size:13px;color:#92400e;margin-bottom:4px}
.pay4-notice p{font-size:12.5px;color:#92400e;line-height:1.5;margin:0}
.pay4-methods-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-top:4px}
.pay4-method-pill{border:1.5px solid #ede8f8;border-radius:10px;padding:12px 8px;text-align:center;background:#fff}
.pay4-method-icon{font-size:20px;margin-bottom:4px}
.pay4-method-name{font-size:12px;font-weight:700;color:#1a0e2e}
.pay4-method-sub{font-size:10.5px;color:#72647a}
.pay4-next-step{display:flex;gap:12px;padding:10px 0;border-bottom:1px solid #ede8f8}
.pay4-next-step:last-child{border-bottom:none}
.pay4-step-num{width:28px;height:28px;min-width:28px;border-radius:50%;background:#7c3aed;color:#fff;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;margin-top:1px}
.pay4-step-ttl{font-size:13px;font-weight:700;color:#1a0e2e;margin-bottom:2px}
.pay4-step-desc{font-size:12px;color:#72647a;line-height:1.5;margin:0}
.pay4-act-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:20px}
.pay4-act-pri{padding:12px;background:#7c3aed;color:#fff;border:none;border-radius:10px;font-weight:700;font-size:13.5px;cursor:pointer;font-family:'DM Sans',sans-serif;transition:background .15s}
.pay4-act-pri:hover{background:#6d28d9}
.pay4-act-sec{padding:12px;background:#fff;color:#7c3aed;border:2px solid #7c3aed;border-radius:10px;font-weight:700;font-size:13.5px;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .15s}
.pay4-act-sec:hover{background:#f5f0ff}

/* Payment badges */
.pay-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:100px;font-size:11px;font-weight:600;white-space:nowrap;flex-shrink:0}
.pay-badge-unpaid{background:#f3f4f6;color:#6b7280}
.pay-badge-verifying{background:#fff8e6;color:#c77712}
.pay-badge-paid{background:#eaf3de;color:#16845c}
.pay-badge-rejected{background:#fcebeb;color:#b42318}
/* Payment method selector cards */
.pm-method-card{border:2px solid #ede8f8;border-radius:12px;padding:14px;text-align:center;cursor:pointer;transition:all .15s;background:#fff;display:block}
.pm-method-card.selected{border-color:#7c3aed;background:#f5f0ff}
</style>

<!-- ── Summary Cards ── -->
<div class="appt-summary-grid">
    <div class="summary-card">
        <div class="summary-icon-box purple">📅</div>
        <div class="summary-number"><?= $totalCount ?></div>
        <div class="summary-label">Total Appointments</div>
    </div>
    <div class="summary-card">
        <div class="summary-icon-box amber">⏳</div>
        <div class="summary-number"><?= $pendingCount ?></div>
        <div class="summary-label">Pending</div>
    </div>
    <div class="summary-card">
        <div class="summary-icon-box green">✅</div>
        <div class="summary-number"><?= $completedCount ?></div>
        <div class="summary-label">Completed</div>
    </div>
    <div class="summary-card">
        <div class="summary-icon-box blue">📅</div>
        <?php if ($nextAppt): ?>
            <div class="summary-number" style="font-size:18px"><?= e(date('d M', strtotime($nextAppt['appointmentDate']))) ?></div>
            <div class="summary-label">Next Visit</div>
            <div class="summary-sub"><?= e($nextAppt['serviceType']) ?></div>
        <?php else: ?>
            <div class="summary-number" style="font-size:14px;color:#72647a;font-weight:600">No upcoming</div>
            <div class="summary-label">Next Visit</div>
        <?php endif; ?>
    </div>
</div>

<!-- ── My Appointments ── -->
<section class="panel" id="appointmentListSection" style="margin-bottom:1.5rem">
    <div class="sec-hd">
        <h2>My Appointments</h2>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-tabs-row">
        <button class="filter-tab active" data-ftab="all">All</button>
        <button class="filter-tab" data-ftab="upcoming">Upcoming</button>
        <button class="filter-tab" data-ftab="completed">Completed</button>
        <button class="filter-tab" data-ftab="cancelled">Cancelled</button>
    </div>

    <!-- Cards -->
    <div class="appt-cards-list" id="apptCardsList">
        <?php if (empty($appointments)): ?>
        <div class="appt-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01"/></svg>
            <h3>No appointments found</h3>
            <p>You have not made any appointments yet.</p>
            <button class="btn primary" onclick="document.getElementById('bookSection').scrollIntoView({behavior:'smooth'})">Book Now</button>
        </div>
        <?php else: ?>
            <?php foreach ($appointments as $appt):
                $st    = (string) $appt['status'];
                $dateTs = strtotime((string) $appt['appointmentDate']);
                $isUp  = in_array($st, ['pending', 'confirmed']) && $appt['appointmentDate'] >= $today;
                $fgrp  = $isUp ? 'upcoming' : $st;
                $dn    = extract_dentist_name((string)($appt['notes'] ?? ''));
                $tm    = substr((string) $appt['appointmentTime'], 0, 5);
            ?>
            <div class="appt-card" data-st="<?= e($st) ?>" data-fgrp="<?= e($fgrp) ?>">
                <div class="appt-date-box <?= e($st) ?>">
                    <div class="aday"><?= e(date('d', $dateTs)) ?></div>
                    <div class="amon"><?= e(date('M', $dateTs)) ?></div>
                </div>
                <div class="appt-card-body">
                    <div class="appt-card-top">
                        <span class="appt-card-service"><?= e($appt['serviceType']) ?></span>
                        <span class="appt-badge <?= e($st) ?>"><?= e(ucfirst($st)) ?></span>
                        <?php if ($st === 'confirmed'):
                            $pst = (string) ($appt['paymentStatus'] ?? 'unpaid');
                            $pstLabel = ['unpaid'=>'Unpaid','pending_verification'=>'Verifying','paid'=>'Paid','rejected'=>'Payment Rejected'][$pst] ?? 'Unpaid';
                            $pstClass = ['unpaid'=>'pay-badge-unpaid','pending_verification'=>'pay-badge-verifying','paid'=>'pay-badge-paid','rejected'=>'pay-badge-rejected'][$pst] ?? 'pay-badge-unpaid';
                        ?>
                        <span class="pay-badge <?= e($pstClass) ?>"><?= e($pstLabel) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="appt-card-meta">
                        <span class="appt-meta-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                            <?= e($dn) ?>
                        </span>
                        <span class="appt-meta-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <?= e($tm) ?>
                        </span>
                        <span class="appt-meta-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v4l3 3"/></svg>
                            <?= e(format_duration((int)$appt['duration'])) ?>
                        </span>
                    </div>
                    <div class="appt-card-actions">
                        <?php if (in_array($st, ['pending', 'confirmed'])): ?>
                        <button class="aab purple"
                            onclick="openReschedule(<?= (int)$appt['appointmentID'] ?>,'<?= e(addslashes($appt['serviceType'])) ?>','<?= e($appt['appointmentDate']) ?>','<?= e($tm) ?>')">
                            Reschedule
                        </button>
                        <button class="aab red"
                            onclick="openCancel(<?= (int)$appt['appointmentID'] ?>,'<?= e(addslashes($appt['serviceType'])) ?>','<?= e($appt['appointmentDate']) ?>')">
                            Cancel
                        </button>
                        <?php elseif ($st === 'completed'): ?>
                        <button class="aab green"
                            onclick="openFeedback(<?= (int)$appt['appointmentID'] ?>,'<?= e(addslashes($appt['serviceType'])) ?>')">
                            Feedback
                        </button>
                        <button class="aab blue"
                            onclick="rebookAppt('<?= e(addslashes($appt['serviceType'])) ?>','<?= e(addslashes($dn)) ?>')">
                            Rebook
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <div class="appt-empty" id="noFilterResult" style="display:none">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
                <h3>No appointments found</h3>
                <p>No appointments in this category.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
$confirmedForPayment = array_filter($appointments, fn($a) => $a['status'] === 'confirmed');
if (!empty($confirmedForPayment)):
?>
<!-- ── Confirmed Appointments — Payment ── -->
<section class="panel" id="paymentSection" style="margin-bottom:1.5rem">
    <div class="sec-hd">
        <h2>Confirmed Appointments — Payment</h2>
        <span style="font-size:12px;color:#72647a">Pay for your confirmed appointments online</span>
    </div>
    <div class="appt-cards-list">
        <?php foreach ($confirmedForPayment as $appt):
            $pst      = (string) ($appt['paymentStatus'] ?? 'unpaid');
            $dn       = extract_dentist_name((string)($appt['notes'] ?? ''));
            $tm       = substr((string) $appt['appointmentTime'], 0, 5);
            $dateTs   = strtotime((string) $appt['appointmentDate']);
            $priceMin = service_price_min((string) $appt['serviceType']);
            $priceLabel = $priceMin > 0 ? 'RM ' . number_format($priceMin, 0) : 'Price on consultation';
            $borderColor = match($pst) {
                'paid'                 => '#16845c',
                'pending_verification' => '#c77712',
                'rejected'             => '#ef4444',
                default                => '#7c3aed',
            };
            [$pstLabel2, $pstClass2] = match($pst) {
                'paid'                 => ['Paid', 'pay-badge-paid'],
                'pending_verification' => ['Verifying Payment', 'pay-badge-verifying'],
                'rejected'             => ['Payment Rejected — Resubmit', 'pay-badge-rejected'],
                default                => ['Unpaid', 'pay-badge-unpaid'],
            };
        ?>
        <div class="appt-card" style="border-left:3px solid <?= $borderColor ?>">
            <div class="appt-date-box confirmed">
                <div class="aday"><?= e(date('d', $dateTs)) ?></div>
                <div class="amon"><?= e(date('M', $dateTs)) ?></div>
            </div>
            <div class="appt-card-body">
                <div class="appt-card-top">
                    <span class="appt-card-service"><?= e($appt['serviceType']) ?></span>
                    <span class="pay-badge <?= e($pstClass2) ?>"><?= e($pstLabel2) ?></span>
                </div>
                <div class="appt-card-meta">
                    <span class="appt-meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                        <?= e($dn) ?>
                    </span>
                    <span class="appt-meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <?= e($tm) ?>
                    </span>
                    <span class="appt-meta-item" style="font-weight:700;color:#7c3aed"><?= e($priceLabel) ?></span>
                </div>
                <?php if ($pst === 'unpaid' || $pst === 'rejected'): ?>
                <div class="appt-card-actions">
                    <button class="aab purple"
                        onclick="openPaymentModal(
                            <?= (int)$appt['appointmentID'] ?>,
                            '<?= e(addslashes($appt['serviceType'])) ?>',
                            '<?= e(addslashes($dn)) ?>',
                            '<?= e($appt['appointmentDate']) ?>',
                            '<?= e($tm) ?>',
                            '<?= e(format_duration((int)$appt['duration'])) ?>',
                            <?= $priceMin ?>
                        )">💳 Pay Now</button>
                </div>
                <?php elseif ($pst === 'pending_verification'): ?>
                <div class="appt-card-actions">
                    <span style="font-size:12px;color:#c77712;font-weight:600">⏳ Awaiting clinic verification (within 24 hours)</span>
                </div>
                <?php elseif ($pst === 'paid'): ?>
                <div class="appt-card-actions">
                    <span style="font-size:12px;color:#16845c;font-weight:600">✓ Payment verified by clinic</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ── Book New Appointment ── -->
<section class="panel" id="bookSection">
    <div class="sec-hd"><h2>Book New Appointment</h2></div>

    <!-- Stepper -->
    <div class="appt-stepper" id="apptStepper">
        <?php
        $stepLabels = ['Service Details', 'Patient Details', 'Review & Submit', 'Payment Info'];
        foreach ($stepLabels as $si => $slabel):
            $sn = $si + 1;
        ?>
        <?php if ($si > 0): ?><div class="step-line inactive" data-sline="<?= $sn ?>"></div><?php endif; ?>
        <div class="step-wrap">
            <div class="step-circle <?= $sn === 1 ? 'active' : 'inactive' ?>" data-scirc="<?= $sn ?>"><?= $sn ?></div>
            <div class="step-lbl <?= $sn === 1 ? 'active' : 'inactive' ?>" data-slbl="<?= $sn ?>"><?= e($slabel) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- AJAX message -->
    <div class="amsg" id="bookMsg"></div>

    <!-- Step 1 -->
    <div class="wiz-step active" id="wizStep1">
        <div class="fg">
            <label class="flbl">Service Type</label>
            <select class="finput" id="svcCat" onchange="onCatChange()">
                <?php foreach ($serviceGroups as $cat => $svcs): ?>
                    <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="fg">
            <label class="flbl">Treatment</label>
            <select class="finput" id="svcTreat" onchange="onTreatChange()">
                <?php foreach ($serviceGroups as $cat => $svcs): ?>
                    <?php foreach ($svcs as $svc): ?>
                    <option value="<?= e($svc['name']) ?>"
                        data-cat="<?= e($cat) ?>"
                        data-dur="<?= (int)$svc['duration'] ?>"
                        data-durlbl="<?= e(format_duration((int)$svc['duration'])) ?>"
                        data-price="<?= e($svc['priceLabel'] ?? format_price((float)($svc['priceMin'] ?? 0))) ?>"
                        <?= $cat !== $firstCat ? 'style="display:none"' : '' ?>
                    ><?= e($svc['displayName'] ?? $svc['name']) ?></option>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="treat-info-grid" id="treatInfoGrid" style="display:none">
            <div class="treat-info-box dur">
                <span class="treat-info-lbl">Treatment Duration</span>
                <span class="treat-info-val" id="treatDur">-</span>
            </div>
            <div class="treat-info-box pri">
                <span class="treat-info-lbl">Price</span>
                <span class="treat-info-val" id="treatPrice">-</span>
            </div>
        </div>

        <div class="fg">
            <label class="flbl">Choose Dentist</label>
            <div class="dentist-grid">
                <?php foreach ($dentists as $di => $dent): ?>
                <label class="dentist-lbl">
                    <input type="radio" name="wiz_dent" value="<?= e($dent['name']) ?>" <?= $di === 0 ? 'checked' : '' ?>>
                    <div class="dentist-inner">
                        <img src="<?= e($dent['image']) ?>" alt="<?= e($dent['name']) ?>" class="dentist-avatar"
                            onerror="this.style.display='none'">
                        <span class="dentist-name"><?= e($dent['name']) ?></span>
                        <span class="dentist-spec"><?= e($dent['spec']) ?></span>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="fg">
                <label class="flbl">Appointment Date</label>
                <input type="date" class="finput" id="wizDate" min="<?= e($today) ?>" value="<?= e($date) ?>" onchange="validateDateInput(this)">
            </div>
            <div class="fg">
                <label class="flbl">Appointment Time</label>
                <select class="finput" id="wizTime">
                    <?php foreach ($timeSlots as $slot): ?>
                        <option value="<?= e($slot) ?>"><?= e($slot) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="slot-list" style="margin-bottom:16px">
            <?php foreach ($slots as $slot): ?>
            <button class="slot-button <?= e($slot['status']) ?>" type="button"
                onclick="pickSlot('<?= e($slot['time']) ?>',<?= $slot['available'] ? 'true' : 'false' ?>)"
                <?= $slot['available'] ? '' : 'disabled' ?>>
                <span class="slot-dot" aria-hidden="true"></span>
                <span><?= e($slot['time']) ?></span>
            </button>
            <?php endforeach; ?>
        </div>

        <div class="wiz-actions">
            <button class="btn primary" onclick="gotoStep(2)">Next</button>
        </div>
    </div>

    <!-- Step 2 -->
    <div class="wiz-step" id="wizStep2">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="fg">
                <label class="flbl">Full Name</label>
                <input class="finput" value="<?= e($user['username']) ?>" readonly>
            </div>
            <div class="fg">
                <label class="flbl">Email</label>
                <input class="finput" value="<?= e($user['userEmail']) ?>" readonly>
            </div>
            <div class="fg">
                <label class="flbl">Phone</label>
                <input class="finput" value="<?= e($user['userPhone']) ?>" readonly>
            </div>
            <div class="fg">
                <label class="flbl">Age</label>
                <input type="number" class="finput" id="wizAge" value="<?= e((string)($user['userAge'] ?? '')) ?>" min="1" max="120" placeholder="Enter age">
            </div>
            <div class="fg">
                <label class="flbl">Gender</label>
                <input class="finput" value="<?= e(ucfirst((string)($user['userGender'] ?? '-'))) ?>" readonly>
            </div>
            <div class="fg">
                <label class="flbl">Health Problem Category</label>
                <select class="finput" id="wizHealthCat" onchange="toggleHealthDetail()">
                    <option value="none">None</option>
                    <option value="common">Common Health Problem</option>
                </select>
            </div>
        </div>
        <div class="fg" id="healthDetailGrp" style="display:none">
            <label class="flbl">Health Problem Detail</label>
            <textarea class="finput" id="wizHealthDetail" rows="3" placeholder="Describe your concern..."></textarea>
        </div>
        <div class="fg">
            <label class="flbl">Additional Notes (optional)</label>
            <textarea class="finput" id="wizNotes" rows="2" placeholder="Any additional notes..."></textarea>
        </div>
        <div class="wiz-actions split">
            <button class="btn" onclick="gotoStep(1)">Back</button>
            <button class="btn primary" onclick="gotoStep(3)">Next</button>
        </div>
    </div>

    <!-- Step 3 -->
    <div class="wiz-step" id="wizStep3">
        <div class="rev-card">
            <h4>Service Details</h4>
            <dl>
                <div class="rev-row"><dt>Service Type</dt><dd id="rv_cat">-</dd></div>
                <div class="rev-row"><dt>Treatment</dt><dd id="rv_treat">-</dd></div>
                <div class="rev-row"><dt>Doctor</dt><dd id="rv_doc">-</dd></div>
                <div class="rev-row"><dt>Date &amp; Time</dt><dd id="rv_sched">-</dd></div>
                <div class="rev-row"><dt>Duration</dt><dd id="rv_dur">-</dd></div>
                <div class="rev-row"><dt>Price</dt><dd id="rv_price">-</dd></div>
            </dl>
        </div>
        <div class="rev-card">
            <h4>Patient Details</h4>
            <dl>
                <div class="rev-row"><dt>Name</dt><dd><?= e($user['username']) ?></dd></div>
                <div class="rev-row"><dt>Phone</dt><dd><?= e($user['userPhone']) ?></dd></div>
                <div class="rev-row"><dt>Age</dt><dd id="rv_age">-</dd></div>
                <div class="rev-row"><dt>Health Concern</dt><dd id="rv_health">None</dd></div>
                <div class="rev-row"><dt>Notes</dt><dd id="rv_notes">-</dd></div>
            </dl>
        </div>
        <div class="wiz-actions split">
            <button class="btn" onclick="gotoStep(2)">Back</button>
            <button class="btn primary" id="wizBookBtn" onclick="submitBooking()">Confirm Booking</button>
        </div>
    </div>

    <!-- Step 4: Payment Info -->
    <div class="wiz-step" id="wizStep4">

        <!-- Success Banner -->
        <div class="pay4-success-banner">
            <div class="pay4-success-icon-circle">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <div class="pay4-success-hdg">Appointment Booked Successfully!</div>
            <div class="pay4-success-sub">Your appointment has been saved. Please review the payment details below.</div>
        </div>

        <!-- Appointment Summary -->
        <div class="pay4-info-card">
            <div class="pay4-card-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><polyline points="9 16 11 18 15 14"/></svg>
                Appointment Summary
            </div>
            <div class="pay4-sum-row"><span class="pay4-sum-lbl">Service</span><span class="pay4-sum-val" id="p4Service">-</span></div>
            <div class="pay4-sum-row"><span class="pay4-sum-lbl">Dentist</span><span class="pay4-sum-val" id="p4Dentist">-</span></div>
            <div class="pay4-sum-row"><span class="pay4-sum-lbl">Date</span><span class="pay4-sum-val" id="p4Date">-</span></div>
            <div class="pay4-sum-row"><span class="pay4-sum-lbl">Time</span><span class="pay4-sum-val" id="p4Time">-</span></div>
            <div class="pay4-sum-row"><span class="pay4-sum-lbl">Duration</span><span class="pay4-sum-val" id="p4Dur">-</span></div>
            <div class="pay4-sum-row"><span class="pay4-sum-lbl">Status</span><span class="pay4-sum-val" style="color:#c77712">Pending Confirmation</span></div>
        </div>

        <!-- Payment Details -->
        <div class="pay4-info-card">
            <div class="pay4-card-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                Payment Details
            </div>
            <div class="pay4-amount-box">
                <div class="pay4-amount-lbl">Total Amount to Pay</div>
                <div class="pay4-amount-val" id="p4Amount">-</div>
            </div>
            <div class="pay4-notice">
                <strong>Payment at clinic only</strong>
                <p>Payment is to be made at the clinic counter on the day of your appointment. Please arrive 10 minutes early and inform the receptionist of your booking.</p>
            </div>
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#72647a;margin-bottom:10px">Accepted Payment Methods</div>
            <div class="pay4-methods-row">
                <div class="pay4-method-pill">
                    <div class="pay4-method-icon" style="color:#16845c">💵</div>
                    <div class="pay4-method-name">Cash</div>
                    <div class="pay4-method-sub">At counter</div>
                </div>
                <div class="pay4-method-pill">
                    <div class="pay4-method-icon" style="color:#1686c2">🏦</div>
                    <div class="pay4-method-name">Online Transfer</div>
                    <div class="pay4-method-sub">FPX / IBG</div>
                </div>
                <div class="pay4-method-pill">
                    <div class="pay4-method-icon" style="color:#7c3aed">📱</div>
                    <div class="pay4-method-name">QR Pay</div>
                    <div class="pay4-method-sub">DuitNow</div>
                </div>
            </div>
        </div>

        <!-- What Happens Next -->
        <div class="pay4-info-card">
            <div class="pay4-card-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><polyline points="3 6 4 7 6 5"/><polyline points="3 12 4 13 6 11"/><polyline points="3 18 4 19 6 17"/></svg>
                What Happens Next
            </div>
            <div class="pay4-next-step">
                <div class="pay4-step-num">1</div>
                <div>
                    <div class="pay4-step-ttl">Wait for clinic confirmation</div>
                    <p class="pay4-step-desc">Your appointment is Pending. The clinic will confirm it shortly via the system.</p>
                </div>
            </div>
            <div class="pay4-next-step">
                <div class="pay4-step-num">2</div>
                <div>
                    <div class="pay4-step-ttl">Arrive at the clinic early</div>
                    <p class="pay4-step-desc">Please arrive at Klinik Pergigian Putra at least 10 minutes before your appointment time.</p>
                </div>
            </div>
            <div class="pay4-next-step">
                <div class="pay4-step-num">3</div>
                <div>
                    <div class="pay4-step-ttl">Make payment at the counter</div>
                    <p class="pay4-step-desc" id="p4PayDesc">Pay at the clinic reception on the day. We accept Cash, Online Transfer, or QR Pay.</p>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="pay4-act-row">
            <button class="pay4-act-pri" onclick="viewMyAppointments()">View My Appointments</button>
            <button class="pay4-act-sec" onclick="location.href='dashboard.php'">Back to Dashboard</button>
        </div>
    </div>
</section>

<!-- ── Reschedule Modal ── -->
<div class="appt-overlay" id="reschModal" hidden>
    <div class="appt-modal">
        <div class="modal-hd">
            <h3>Reschedule Appointment</h3>
            <button class="modal-close-btn" onclick="closeModal('reschModal')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <div id="reschInfo" style="background:#f5f0ff;border-radius:8px;padding:11px 14px;margin-bottom:16px;font-size:13px;color:#5b21b6;font-weight:500"></div>
        <div class="fg">
            <label class="flbl">New Date</label>
            <input type="date" class="finput" id="reschDate" min="<?= e($today) ?>">
        </div>
        <div class="fg">
            <label class="flbl">New Time</label>
            <select class="finput" id="reschTime">
                <?php foreach ($timeSlots as $slot): ?>
                    <option value="<?= e($slot) ?>"><?= e($slot) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="amsg" id="reschMsg"></div>
        <div class="modal-acts">
            <button class="mbtn-sec" onclick="closeModal('reschModal')">Cancel</button>
            <button class="mbtn-pri" onclick="saveReschedule()">Save Changes</button>
        </div>
    </div>
</div>

<!-- ── Cancel Modal ── -->
<div class="appt-overlay" id="cancelModal" hidden>
    <div class="appt-modal">
        <div class="modal-hd">
            <h3>Cancel Appointment</h3>
            <button class="modal-close-btn" onclick="closeModal('cancelModal')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <p style="color:#72647a;font-size:13.5px;margin:0 0 12px">Are you sure you want to cancel this appointment?</p>
        <div id="cancelInfo" style="background:#fcebeb;border-radius:8px;padding:11px 14px;margin-bottom:16px;font-size:13px;color:#b42318;font-weight:500"></div>
        <div class="amsg" id="cancelMsg"></div>
        <div class="modal-acts">
            <button class="mbtn-sec" onclick="closeModal('cancelModal')">No, Keep It</button>
            <button class="mbtn-danger" onclick="doCancel()">Yes, Cancel</button>
        </div>
    </div>
</div>

<!-- ── Feedback Modal ── -->
<div class="appt-overlay" id="feedbackModal" hidden>
    <div class="appt-modal">
        <div class="modal-hd">
            <h3>Leave Feedback</h3>
            <button class="modal-close-btn" onclick="closeModal('feedbackModal')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <p style="font-size:13px;color:#72647a;margin:0 0 14px" id="fbkLabel">-</p>
        <div class="fg">
            <label class="flbl">Rating</label>
            <div class="star-row" id="starRow">
                <?php for ($s = 1; $s <= 5; $s++): ?>
                    <button type="button" data-star="<?= $s ?>" onclick="setStar(<?= $s ?>)">★</button>
                <?php endfor; ?>
            </div>
        </div>
        <div class="fg">
            <label class="flbl">Comment</label>
            <textarea class="finput" id="fbkComment" rows="3" placeholder="Share your experience..."></textarea>
        </div>
        <div class="amsg" id="fbkMsg"></div>
        <div class="modal-acts">
            <button class="mbtn-sec" onclick="closeModal('feedbackModal')">Cancel</button>
            <button class="mbtn-pri" onclick="submitFeedback()">Submit</button>
        </div>
    </div>
</div>

<!-- ── Payment Modal ── -->
<div class="appt-overlay" id="paymentModal" hidden>
    <div class="appt-modal" style="max-width:520px;width:min(520px,100%)">
        <div class="modal-hd">
            <h3>💳 Online Payment</h3>
            <button class="modal-close-btn" onclick="closeModal('paymentModal')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <!-- Appointment summary -->
        <div class="rev-card">
            <h4>Appointment Summary</h4>
            <dl>
                <div class="rev-row"><dt>Service</dt><dd id="pm-service">-</dd></div>
                <div class="rev-row"><dt>Dentist</dt><dd id="pm-dentist">-</dd></div>
                <div class="rev-row"><dt>Date &amp; Time</dt><dd id="pm-schedule">-</dd></div>
                <div class="rev-row"><dt>Duration</dt><dd id="pm-duration">-</dd></div>
                <div class="rev-row"><dt>Amount</dt><dd id="pm-amount" style="font-weight:700;color:#7c3aed">-</dd></div>
            </dl>
        </div>

        <!-- Payment method -->
        <div class="fg">
            <label class="flbl">Payment Method</label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:4px">
                <label id="pmTngCard" class="pm-method-card">
                    <input type="radio" name="pmMethod" value="tng_qr" id="pmTng" onchange="pmSelectMethod('tng_qr')" style="display:none">
                    <div style="font-size:22px;margin-bottom:6px">📱</div>
                    <div style="font-weight:700;font-size:13px">Touch 'n Go QR</div>
                    <div style="font-size:11px;color:#72647a">Scan eWallet QR</div>
                </label>
                <label id="pmFpxCard" class="pm-method-card">
                    <input type="radio" name="pmMethod" value="fpx" id="pmFpx" onchange="pmSelectMethod('fpx')" style="display:none">
                    <div style="font-size:22px;margin-bottom:6px">🏦</div>
                    <div style="font-weight:700;font-size:13px">FPX Online Banking</div>
                    <div style="font-size:11px;color:#72647a">Internet banking</div>
                </label>
            </div>
        </div>

        <!-- TNG section -->
        <div id="pmTngSection" style="display:none">
            <div style="background:#f4f3ff;border-radius:10px;padding:16px;text-align:center;margin-bottom:14px">
                <div style="font-weight:700;color:#3b0764;margin-bottom:4px">Klinik Pergigian Putra</div>
                <div style="font-size:12px;color:#72647a;margin-bottom:10px">Scan with Touch 'n Go eWallet</div>
                <div style="background:#fff;border:2px dashed #c4b2f0;border-radius:8px;padding:20px;max-width:160px;margin:0 auto">
                    <div style="font-size:30px;margin-bottom:4px">📲</div>
                    <div style="font-size:11px;color:#9b8ad4">TnG QR Code</div>
                    <div style="font-size:13px;font-weight:700;color:#7c3aed" id="pmTngAmount">-</div>
                </div>
                <div style="font-size:11px;color:#72647a;margin-top:8px">Contact clinic for QR: <strong>07-453 8899</strong></div>
            </div>
        </div>

        <!-- FPX section -->
        <div id="pmFpxSection" style="display:none">
            <div class="fg">
                <label class="flbl">Select Bank</label>
                <select class="finput" id="pmBankName">
                    <option value="">-- Select Bank --</option>
                    <option>Maybank2u</option>
                    <option>CIMB Clicks</option>
                    <option>Public Bank</option>
                    <option>RHB</option>
                    <option>Hong Leong Connect</option>
                    <option>Bank Islam</option>
                    <option>AmBank</option>
                    <option>BSN</option>
                </select>
            </div>
            <div style="background:#f9f7fe;border:1.5px solid #e0d5f5;border-radius:10px;padding:14px;margin-bottom:14px">
                <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#72647a;margin-bottom:8px">Clinic Bank Account</div>
                <div style="font-size:13px;margin-bottom:4px"><strong>Bank:</strong> Maybank</div>
                <div style="font-size:13px;margin-bottom:4px"><strong>Account Name:</strong> Klinik Pergigian Putra</div>
                <div style="font-size:13px;margin-bottom:4px"><strong>Account No:</strong> 1234 5678 9012</div>
                <div style="font-size:13px"><strong>Reference:</strong> <span id="pmFpxRef">APT-00000</span></div>
            </div>
        </div>

        <!-- Proof upload -->
        <div id="pmProofSection" style="display:none">
            <div class="fg">
                <label class="flbl">Upload Payment Screenshot / Slip <span style="color:#ef4444">*</span></label>
                <label style="display:flex;flex-direction:column;align-items:center;gap:8px;padding:18px;border:2px dashed #c4b2f0;border-radius:10px;cursor:pointer;background:#faf8ff;text-align:center" id="pmDropZone">
                    <input type="file" id="pmProofInput" accept="image/jpeg,image/png,image/webp,application/pdf" style="display:none" onchange="pmProofChanged(this)">
                    <span style="font-size:26px">📄</span>
                    <span style="font-size:13px;font-weight:600;color:#5b21b6">Click to upload proof</span>
                    <span style="font-size:11px;color:#9ca3af">JPG, PNG, PDF · max 2 MB</span>
                </label>
                <div id="pmProofPreview" style="display:none;align-items:center;gap:10px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px">
                    <img id="pmProofThumb" src="" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:6px;display:none">
                    <span id="pmProofName" style="font-size:13px;font-weight:600;color:#065f46;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0">-</span>
                    <button type="button" onclick="pmRemoveProof()" style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:16px;padding:2px;flex-shrink:0">✕</button>
                </div>
            </div>
        </div>

        <!-- Payment details -->
        <div id="pmDetailsSection" style="display:none">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="fg" style="margin-bottom:0">
                    <label class="flbl">Reference / Transaction No. <span style="color:#ef4444">*</span></label>
                    <input class="finput" id="pmRefNo" placeholder="e.g. 20241234567">
                </div>
                <div class="fg" style="margin-bottom:0">
                    <label class="flbl">Payment Date</label>
                    <input type="date" class="finput" id="pmPayDate">
                </div>
            </div>
            <div class="fg" style="margin-top:12px">
                <label class="flbl">Amount Paid (RM) <span style="color:#ef4444">*</span></label>
                <input type="number" class="finput" id="pmAmountInput" min="0" step="0.01" placeholder="0.00">
            </div>
        </div>

        <div class="amsg" id="pmMsg"></div>

        <div class="modal-acts">
            <button class="mbtn-sec" onclick="closeModal('paymentModal')">Cancel</button>
            <button class="mbtn-pri" id="pmSubmitBtn" onclick="submitPayment()" style="display:none">💳 Confirm Payment</button>
        </div>
    </div>
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
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="chatbot-body" id="chatbotBody">
            <div class="chatbot-bubble">Hi <?= e($user['username']) ?>! Need help booking an appointment? I can guide you step by step! 😊</div>
            <div class="chatbot-quick-replies">
                <button class="chatbot-quick-btn" data-msg="I want to book an appointment">📅 Book Appointment</button>
                <button class="chatbot-quick-btn" data-msg="Show my bookings">📋 Check My Bookings</button>
                <button class="chatbot-quick-btn" data-msg="What slots are available?">🕐 Available Slots</button>
                <button class="chatbot-quick-btn" data-msg="I want to cancel an appointment">❌ Cancel Appointment</button>
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
window.DETABOT_USER_ID      = <?= (int) $user['userID'] ?>;
window.DETABOT_USER_AGE     = <?= (int) ($user['userAge'] ?? 0) ?>;
window.DETABOT_PAGE_CONTEXT = 'appointments';
</script>
<script src="assets/chat.js"></script>
<script>
(function(){
'use strict';
var CSRF = window.DETABOT_CSRF || '';
var curStep = 1;

// ── Filter tabs ──
document.querySelectorAll('.filter-tab').forEach(function(btn){
    btn.addEventListener('click', function(){
        document.querySelectorAll('.filter-tab').forEach(function(b){ b.classList.remove('active'); });
        btn.classList.add('active');
        var f = btn.dataset.ftab;
        var cards = document.querySelectorAll('#apptCardsList .appt-card');
        var vis = 0;
        cards.forEach(function(c){
            var show = f === 'all' || c.dataset.fgrp === f || c.dataset.st === f;
            c.style.display = show ? '' : 'none';
            if(show) vis++;
        });
        var noRes = document.getElementById('noFilterResult');
        if(noRes) noRes.style.display = vis === 0 ? '' : 'none';
    });
});

// ── Stepper ──
function gotoStep(n){
    if(n > curStep && !validateStep(curStep)) return;
    if(n === 3) fillReview();
    document.querySelectorAll('.wiz-step').forEach(function(el){ el.classList.remove('active'); });
    var step = document.getElementById('wizStep' + n);
    if(step) step.classList.add('active');

    document.querySelectorAll('[data-scirc]').forEach(function(el){
        var i = parseInt(el.dataset.scirc);
        el.className = 'step-circle ' + (i < n ? 'done' : i === n ? 'active' : 'inactive');
        el.textContent = (i < n || n === 4) ? '✓' : i;
    });
    document.querySelectorAll('[data-slbl]').forEach(function(el){
        var i = parseInt(el.dataset.slbl);
        el.className = 'step-lbl ' + (i < n ? 'done' : i === n ? 'active' : 'inactive');
    });
    document.querySelectorAll('[data-sline]').forEach(function(el){
        var i = parseInt(el.dataset.sline);
        el.className = 'step-line ' + (i <= n ? 'done' : 'inactive');
    });
    curStep = n;
    document.getElementById('bookSection').scrollIntoView({behavior:'smooth', block:'start'});
}
window.gotoStep = gotoStep;

function validateStep(n){
    if(n === 1){
        var d = document.getElementById('wizDate').value;
        var t = document.getElementById('wizTime').value;
        var tod = new Date().toISOString().slice(0,10);
        if(!d || d < tod){ alert('Please select a valid future date.'); return false; }
        var day = new Date(d+'T00:00:00').getDay();
        if(day === 0){ alert('We are closed on Sundays. Please choose another date.'); return false; }
        if(!t){ alert('Please select a time slot.'); return false; }
        if(!document.querySelector('input[name="wiz_dent"]:checked')){ alert('Please choose a dentist.'); return false; }
    }
    if(n === 2){
        var age = parseInt(document.getElementById('wizAge').value);
        if(!age || age < 1 || age > 120){ alert('Please enter a valid age (1–120).'); return false; }
    }
    return true;
}

// ── Service category ──
function onCatChange(){
    var cat = document.getElementById('svcCat').value;
    var opts = document.querySelectorAll('#svcTreat option');
    opts.forEach(function(o){
        var show = o.dataset.cat === cat;
        o.style.display = show ? '' : 'none';
        o.disabled = !show;
    });
    var first = Array.from(opts).find(function(o){ return o.dataset.cat === cat; });
    if(first){ document.getElementById('svcTreat').value = first.value; onTreatChange(); }
}
window.onCatChange = onCatChange;

function onTreatChange(){
    var sel = document.getElementById('svcTreat');
    var opt = sel.selectedOptions[0];
    if(!opt) return;
    document.getElementById('treatDur').textContent = opt.dataset.durlbl || '-';
    document.getElementById('treatPrice').textContent = opt.dataset.price || '-';
    document.getElementById('treatInfoGrid').style.display = 'grid';
}
window.onTreatChange = onTreatChange;

// Init treatment on load
onTreatChange();

// ── Slot picker ──
function pickSlot(t, avail){
    if(!avail) return;
    document.getElementById('wizTime').value = t;
}
window.pickSlot = pickSlot;

function validateDateInput(input){
    var d = new Date(input.value+'T00:00:00');
    if(d.getDay() === 0){ alert('We are closed on Sundays. Please choose another date.'); input.value = ''; }
}
window.validateDateInput = validateDateInput;

// ── Health detail toggle ──
function toggleHealthDetail(){
    var v = document.getElementById('wizHealthCat').value;
    document.getElementById('healthDetailGrp').style.display = v !== 'none' ? '' : 'none';
}
window.toggleHealthDetail = toggleHealthDetail;

// ── Fill review ──
function fillReview(){
    var cat   = document.getElementById('svcCat').value;
    var treat = document.getElementById('svcTreat').selectedOptions[0]?.textContent || '-';
    var doc   = document.querySelector('input[name="wiz_dent"]:checked')?.value || '-';
    var date  = document.getElementById('wizDate').value;
    var time  = document.getElementById('wizTime').value;
    var age   = document.getElementById('wizAge').value;
    var hcat  = document.getElementById('wizHealthCat').value;
    var hdet  = document.getElementById('wizHealthDetail').value;
    var notes = document.getElementById('wizNotes').value;
    document.getElementById('rv_cat').textContent    = cat;
    document.getElementById('rv_treat').textContent  = treat;
    document.getElementById('rv_doc').textContent    = doc;
    document.getElementById('rv_sched').textContent  = date + ' at ' + time;
    document.getElementById('rv_dur').textContent    = document.getElementById('treatDur').textContent;
    document.getElementById('rv_price').textContent  = document.getElementById('treatPrice').textContent;
    document.getElementById('rv_age').textContent    = age || '-';
    document.getElementById('rv_health').textContent = hcat === 'none' ? 'None' : (hdet || hcat);
    document.getElementById('rv_notes').textContent  = notes || '-';
}

// ── Submit booking ──
async function submitBooking() {
    var btn = document.getElementById('wizBookBtn');
    var origText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Booking…';

    var fd = new FormData();
    fd.append('_csrf_token',           CSRF);
    fd.append('serviceType',           document.getElementById('svcTreat').value);
    fd.append('serviceCategory',       document.getElementById('svcCat').value);
    fd.append('dentistName',           document.querySelector('input[name="wiz_dent"]:checked')?.value || '');
    fd.append('appointmentDate',       document.getElementById('wizDate').value);
    fd.append('appointmentTime',       document.getElementById('wizTime').value);
    fd.append('patientAge',            document.getElementById('wizAge').value);
    fd.append('healthProblemCategory', document.getElementById('wizHealthCat').value);
    fd.append('healthProblemDetail',   document.getElementById('wizHealthDetail').value);
    fd.append('notes',                 document.getElementById('wizNotes').value);

    try {
        var res  = await fetch('book_appointment.php', { method: 'POST', body: fd });
        var data = await res.json();
        if (data.success) {
            var bk = {
                service:  document.getElementById('svcTreat').selectedOptions[0]?.textContent || document.getElementById('svcTreat').value,
                dentist:  document.querySelector('input[name="wiz_dent"]:checked')?.value || '-',
                date:     document.getElementById('wizDate').value,
                time:     document.getElementById('wizTime').value,
                duration: document.getElementById('treatDur').textContent,
                price:    document.getElementById('treatPrice').textContent
            };
            populateStep4(bk);
            gotoStep(4);
        } else {
            showMsg(document.getElementById('bookMsg'), data.error || 'Booking failed. Please try again.', 'err');
            btn.disabled = false;
            btn.textContent = origText;
        }
    } catch (e) {
        showMsg(document.getElementById('bookMsg'), 'Network error. Please try again.', 'err');
        btn.disabled = false;
        btn.textContent = origText;
    }
}
window.submitBooking = submitBooking;

function populateStep4(bk) {
    document.getElementById('p4Service').textContent = bk.service || '-';
    document.getElementById('p4Dentist').textContent = bk.dentist || '-';
    var formatted = bk.date;
    if (bk.date) {
        var d = new Date(bk.date + 'T00:00:00');
        formatted = d.toLocaleDateString('en-MY', {weekday:'long', day:'numeric', month:'long', year:'numeric'});
    }
    document.getElementById('p4Date').textContent = formatted;
    document.getElementById('p4Time').textContent = bk.time || '-';
    document.getElementById('p4Dur').textContent  = bk.duration || '-';
    document.getElementById('p4Amount').textContent = bk.price || '-';
    document.getElementById('p4PayDesc').textContent = 'Pay ' + (bk.price || '') + ' at the clinic reception on the day. We accept Cash, Online Transfer, or QR Pay.';
}

function viewMyAppointments() {
    location.href = 'appointments.php';
}
window.viewMyAppointments = viewMyAppointments;

// ── Modals ──
var activeApptID = null;

function closeModal(id){ document.getElementById(id).hidden = true; }
window.closeModal = closeModal;

function openReschedule(id, svc, date, time){
    activeApptID = id;
    document.getElementById('reschInfo').textContent = svc + ' — ' + date + ' at ' + time;
    document.getElementById('reschDate').value = date;
    document.getElementById('reschTime').value = time;
    document.getElementById('reschMsg').style.display = 'none';
    document.getElementById('reschModal').hidden = false;
}
window.openReschedule = openReschedule;

async function saveReschedule(){
    var msg  = document.getElementById('reschMsg');
    var date = document.getElementById('reschDate').value;
    var time = document.getElementById('reschTime').value;
    if(!date||!time){ showMsg(msg,'Please select a date and time.','err'); return; }
    var d = new Date(date+'T00:00:00');
    if(d.getDay()===0){ showMsg(msg,'Clinic is closed on Sundays.','err'); return; }
    var tod = new Date().toISOString().slice(0,10);
    if(date < tod){ showMsg(msg,'Please select a future date.','err'); return; }
    var fd = new FormData();
    fd.append('_csrf_token',CSRF); fd.append('appointmentID',activeApptID);
    fd.append('newDate',date); fd.append('newTime',time);
    try {
        var res = await fetch('reschedule_appointment.php',{method:'POST',body:fd});
        var data = await res.json();
        if(data.success){ closeModal('reschModal'); location.reload(); }
        else showMsg(msg, data.error||'Reschedule failed.','err');
    } catch(e){ showMsg(msg,'Network error.','err'); }
}
window.saveReschedule = saveReschedule;

function openCancel(id, svc, date){
    activeApptID = id;
    document.getElementById('cancelInfo').textContent = svc + ' — ' + date;
    document.getElementById('cancelMsg').style.display = 'none';
    document.getElementById('cancelModal').hidden = false;
}
window.openCancel = openCancel;

async function doCancel(){
    var msg = document.getElementById('cancelMsg');
    var fd = new FormData();
    fd.append('_csrf_token',CSRF); fd.append('appointmentID',activeApptID);
    try {
        var res = await fetch('cancel_appointment.php',{method:'POST',body:fd});
        var data = await res.json();
        if(data.success){ closeModal('cancelModal'); location.reload(); }
        else showMsg(msg, data.error||'Cancel failed.','err');
    } catch(e){ showMsg(msg,'Network error.','err'); }
}
window.doCancel = doCancel;

function openFeedback(id, svc){
    activeApptID = id;
    document.getElementById('fbkLabel').textContent = 'For: ' + svc;
    document.getElementById('fbkComment').value = '';
    document.getElementById('fbkMsg').style.display = 'none';
    setStar(0);
    document.getElementById('feedbackModal').hidden = false;
}
window.openFeedback = openFeedback;

var curStar = 0;
function setStar(n){
    curStar = n;
    document.querySelectorAll('#starRow button').forEach(function(b){
        b.classList.toggle('lit', parseInt(b.dataset.star) <= n);
    });
}
window.setStar = setStar;

async function submitFeedback(){
    var msg = document.getElementById('fbkMsg');
    if(curStar < 1){ showMsg(msg,'Please select a rating.','err'); return; }
    var comment = document.getElementById('fbkComment').value.trim();
    if(!comment){ showMsg(msg,'Please leave a comment.','err'); return; }
    var fd = new FormData();
    fd.append('_csrf_token',CSRF); fd.append('appointmentID',activeApptID);
    fd.append('rating',curStar); fd.append('comments',comment);
    try {
        var res = await fetch('submit_feedback.php',{method:'POST',body:fd});
        var data = await res.json();
        if(data.success){ closeModal('feedbackModal'); showMsg(document.getElementById('bookMsg'),'Thank you for your feedback!','ok'); }
        else showMsg(msg, data.error||'Submission failed.','err');
    } catch(e){ showMsg(msg,'Network error.','err'); }
}
window.submitFeedback = submitFeedback;

// ── Rebook ──
function rebookAppt(svc, doctor){
    var sel = document.getElementById('svcTreat');
    for(var i=0;i<sel.options.length;i++){
        var opt = sel.options[i];
        if(opt.value.toLowerCase() === svc.toLowerCase()){
            var catSel = document.getElementById('svcCat');
            for(var j=0;j<catSel.options.length;j++){
                if(catSel.options[j].value === opt.dataset.cat){
                    catSel.value = catSel.options[j].value;
                    onCatChange();
                    break;
                }
            }
            sel.value = opt.value;
            onTreatChange();
            break;
        }
    }
    document.querySelectorAll('input[name="wiz_dent"]').forEach(function(r){
        r.checked = r.value === doctor;
    });
    gotoStep(1);
}
window.rebookAppt = rebookAppt;

// ── Reset wizard ──
function resetWizard(){
    curStep = 1;
    document.querySelectorAll('.wiz-step').forEach(function(el){ el.classList.remove('active'); });
    document.getElementById('wizStep1').classList.add('active');
    document.querySelectorAll('[data-scirc]').forEach(function(el){
        var i=parseInt(el.dataset.scirc);
        el.className='step-circle '+(i===1?'active':'inactive');
        el.textContent=i;
    });
    document.querySelectorAll('[data-slbl]').forEach(function(el){
        el.className='step-lbl '+(parseInt(el.dataset.slbl)===1?'active':'inactive');
    });
    document.querySelectorAll('[data-sline]').forEach(function(el){
        el.className='step-line inactive';
    });
    document.getElementById('bookMsg').style.display='none';
    document.getElementById('appointmentListSection').scrollIntoView({behavior:'smooth'});
}
window.resetWizard = resetWizard;

// ── Payment Modal ──
var pmApptID = null;

function openPaymentModal(apptID, service, dentist, date, time, duration, price) {
    pmApptID = apptID;
    document.getElementById('pm-service').textContent  = service;
    document.getElementById('pm-dentist').textContent  = dentist;
    document.getElementById('pm-schedule').textContent = date + ' at ' + time;
    document.getElementById('pm-duration').textContent = duration;
    var priceLabel = price > 0 ? 'RM ' + Number(price).toLocaleString('en-MY', {minimumFractionDigits:0}) : 'Price on consultation';
    document.getElementById('pm-amount').textContent   = priceLabel;
    document.getElementById('pmTngAmount').textContent = priceLabel;
    document.getElementById('pmFpxRef').textContent    = 'APT-' + String(apptID).padStart(5, '0');
    document.getElementById('pmPayDate').value = new Date().toISOString().slice(0, 10);
    if (price > 0) document.getElementById('pmAmountInput').value = Number(price).toFixed(2);

    document.getElementById('pmTng').checked = false;
    document.getElementById('pmFpx').checked = false;
    document.getElementById('pmTngCard').classList.remove('selected');
    document.getElementById('pmFpxCard').classList.remove('selected');
    document.getElementById('pmTngSection').style.display     = 'none';
    document.getElementById('pmFpxSection').style.display     = 'none';
    document.getElementById('pmProofSection').style.display   = 'none';
    document.getElementById('pmDetailsSection').style.display = 'none';
    document.getElementById('pmSubmitBtn').style.display      = 'none';
    document.getElementById('pmMsg').style.display            = 'none';
    document.getElementById('pmRefNo').value = '';
    pmRemoveProof();
    document.getElementById('paymentModal').hidden = false;
}
window.openPaymentModal = openPaymentModal;

function pmSelectMethod(method) {
    document.getElementById('pmTngSection').style.display     = method === 'tng_qr' ? '' : 'none';
    document.getElementById('pmFpxSection').style.display     = method === 'fpx'    ? '' : 'none';
    document.getElementById('pmProofSection').style.display   = '';
    document.getElementById('pmDetailsSection').style.display = '';
    document.getElementById('pmSubmitBtn').style.display      = '';
    document.getElementById('pmTngCard').classList.toggle('selected', method === 'tng_qr');
    document.getElementById('pmFpxCard').classList.toggle('selected', method === 'fpx');
}
window.pmSelectMethod = pmSelectMethod;

function pmProofChanged(input) {
    var file = input.files && input.files[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) { alert('File too large. Max 2 MB.'); input.value = ''; return; }
    document.getElementById('pmProofName').textContent = file.name;
    if (file.type.startsWith('image/')) {
        var reader = new FileReader();
        reader.onload = function(ev) {
            var t = document.getElementById('pmProofThumb');
            t.src = ev.target.result; t.style.display = '';
        };
        reader.readAsDataURL(file);
    } else {
        document.getElementById('pmProofThumb').style.display = 'none';
    }
    document.getElementById('pmDropZone').style.display = 'none';
    document.getElementById('pmProofPreview').style.display = 'flex';
}
window.pmProofChanged = pmProofChanged;

function pmRemoveProof() {
    var inp = document.getElementById('pmProofInput');
    if (inp) inp.value = '';
    document.getElementById('pmDropZone').style.display = '';
    document.getElementById('pmProofPreview').style.display = 'none';
    var t = document.getElementById('pmProofThumb');
    t.src = ''; t.style.display = 'none';
    document.getElementById('pmProofName').textContent = '-';
}
window.pmRemoveProof = pmRemoveProof;

async function submitPayment() {
    var method = document.querySelector('input[name="pmMethod"]:checked');
    if (!method) { showMsg(document.getElementById('pmMsg'), 'Please select a payment method.', 'err'); return; }
    var refNo = document.getElementById('pmRefNo').value.trim();
    if (!refNo) { showMsg(document.getElementById('pmMsg'), 'Please enter the reference/transaction number.', 'err'); return; }
    var amount = parseFloat(document.getElementById('pmAmountInput').value);
    if (!amount || amount <= 0) { showMsg(document.getElementById('pmMsg'), 'Please enter the amount paid.', 'err'); return; }
    var proofInput = document.getElementById('pmProofInput');
    if (!proofInput.files || !proofInput.files.length) {
        showMsg(document.getElementById('pmMsg'), 'Please upload a payment screenshot or slip.', 'err'); return;
    }
    var btn = document.getElementById('pmSubmitBtn');
    btn.disabled = true; btn.textContent = 'Submitting…';
    var fd = new FormData();
    fd.append('_csrf_token',   CSRF);
    fd.append('appointmentID', pmApptID);
    fd.append('paymentMethod', method.value);
    fd.append('bankName',      (document.getElementById('pmBankName') || {}).value || '');
    fd.append('referenceNo',   refNo);
    fd.append('paymentDate',   document.getElementById('pmPayDate').value);
    fd.append('amount',        amount);
    fd.append('paymentProof',  proofInput.files[0]);
    try {
        var res  = await fetch('submit_payment.php', { method: 'POST', body: fd });
        var data = await res.json();
        if (data.success) {
            closeModal('paymentModal');
            showMsg(document.getElementById('bookMsg'), 'Payment submitted! The clinic will verify within 24 hours.', 'ok');
            setTimeout(function() { location.reload(); }, 2500);
        } else {
            showMsg(document.getElementById('pmMsg'), data.error || 'Submission failed.', 'err');
            btn.disabled = false; btn.textContent = '💳 Confirm Payment';
        }
    } catch (e) {
        showMsg(document.getElementById('pmMsg'), 'Network error. Please try again.', 'err');
        btn.disabled = false; btn.textContent = '💳 Confirm Payment';
    }
}
window.submitPayment = submitPayment;

// ── Utility ──
function showMsg(el, msg, type){
    el.textContent = msg;
    el.className = 'amsg ' + type;
    el.style.display = '';
    setTimeout(function(){ el.style.display='none'; }, 6000);
}

// Close modals on overlay click
document.querySelectorAll('.appt-overlay').forEach(function(ov){
    ov.addEventListener('click', function(e){ if(e.target===ov) ov.hidden=true; });
});
})();
</script>
    <?php
}

// ── Staff / Admin view ──────────────────────────────────────────────────────

function page_appointments_staff(array $user): void
{
    $canManage = has_role($user, ['admin', 'staff']);
    $patients  = $canManage
        ? db_all("SELECT userID, username, userEmail FROM tbl_user WHERE userRole = 'patient' AND status = 'active' ORDER BY username")
        : [];
    $appointments = db_all(
        "SELECT a.*, u.username, u.userPhone, c.clinicName
         FROM tbl_appointment a
         JOIN tbl_user u ON u.userID = a.userID
         JOIN tbl_clinic c ON c.clinicID = a.clinicID
         ORDER BY a.appointmentDate DESC, a.appointmentTime DESC
         LIMIT 80"
    );
    $today  = date('Y-m-d');
    $date   = normalize_appointment_date($_GET['date'] ?? null);
    $clinicID = (int) (clinic()['clinicID'] ?? 1);
    $slots  = appointment_slot_list($date, $clinicID);
    $serviceGroups  = group_services_by_category(clinic_services_with_duration());
    $catNames       = array_keys($serviceGroups);
    $firstCat       = (string) ($catNames[0] ?? '');
    $commonProblems = common_health_problem_options();
    $dentists = [
        ['name' => 'Dr. Muhammad Firdaus', 'image' => 'assets/dentist-dr-muhammad-firdaus.png'],
        ['name' => 'Dr. Siti Zafirah',     'image' => 'assets/dentist-dr-siti-zafirah.png'],
        ['name' => 'Dr. Alia Suhana',      'image' => 'assets/dentist-dr-alia-suhana.png'],
    ];
    ?>
    <section class="panel appointment-booking-panel" style="margin-bottom:1rem">
        <div class="panel-head"><h2>Book Appointment</h2></div>
        <form method="post" enctype="multipart/form-data" class="appointment-wizard" data-appointment-wizard>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="book_appointment">
            <div class="appointment-stepper" aria-label="Appointment booking steps">
                <span class="active">1 Service Details</span>
                <span>2 Patient Details</span>
                <span>3 Review / Submit</span>
                <span>4 Payment</span>
            </div>

            <section class="wizard-step active" data-step-panel="1">
                <div class="wizard-step-head"><span>Step 1</span><h3>Service Details</h3></div>
                <div class="form-grid">
                    <label class="span-2">Service Type
                        <select name="serviceCategory" required data-service-category>
                            <?php foreach ($serviceGroups as $cat => $svcs): ?>
                                <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="span-2">Treatment
                        <select name="serviceType" required data-treatment-select>
                            <?php foreach ($serviceGroups as $cat => $svcs): ?>
                                <?php foreach ($svcs as $svc): ?>
                                    <option value="<?= e($svc['name']) ?>"
                                        data-category="<?= e($cat) ?>"
                                        data-treatment-label="<?= e($svc['displayName'] ?? $svc['name']) ?>"
                                        data-duration="<?= (int)$svc['duration'] ?>"
                                        data-duration-label="<?= e(format_duration((int)$svc['duration'])) ?>"
                                        data-price="<?= e($svc['priceLabel'] ?? format_price((float)($svc['priceMin'] ?? 0))) ?>"
                                        data-price-label="<?= e($svc['priceLabel'] ?? format_price((float)($svc['priceMin'] ?? 0))) ?>"
                                        <?= $cat === $firstCat ? '' : 'hidden disabled' ?>
                                    ><?= e($svc['displayName'] ?? $svc['name']) ?></option>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="span-2 treatment-duration-box">
                        <span class="field-label">Treatment Duration</span>
                        <strong data-treatment-duration>-</strong>
                    </div>
                    <div class="span-2 treatment-price-box">
                        <span class="field-label">Price</span>
                        <strong data-treatment-price>-</strong>
                    </div>
                    <div class="span-2 dentist-choice-group">
                        <span class="field-label">Choose Dentist</span>
                        <div class="dentist-choice-list">
                            <?php foreach ($dentists as $idx => $dent): ?>
                            <label class="dentist-choice">
                                <input type="radio" name="dentistName" value="<?= e($dent['name']) ?>" data-dentist-option <?= $idx === 0 ? 'checked' : '' ?> required>
                                <img src="<?= e($dent['image']) ?>" alt="<?= e($dent['name']) ?>" loading="lazy">
                                <span><strong><?= e($dent['name']) ?></strong></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="wizard-actions">
                    <button class="btn primary" type="button" data-step-next data-next-step="2">Next</button>
                </div>
            </section>

            <section class="wizard-step" data-step-panel="2" hidden>
                <div class="wizard-step-head"><span>Step 2</span><h3>Patient Details</h3></div>
                <div class="form-grid">
                    <label class="span-2">User Name
                        <select name="userID" required>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?= e($p['userID']) ?>"><?= e($p['username']) ?> — <?= e($p['userEmail']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Date <input id="bookingDate" type="date" name="appointmentDate" min="<?= e($today) ?>" value="<?= e($date) ?>" required></label>
                    <label>Time
                        <select id="bookingTime" name="appointmentTime" required>
                            <?php foreach (build_time_slots() as $slot): ?>
                                <option value="<?= e($slot) ?>"><?= e($slot) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Age <input type="number" name="patientAge" min="1" max="120" required inputmode="numeric" placeholder="Enter age"></label>
                    <div class="span-2 health-choice-group" data-health-choice-group>
                        <span class="field-label">Health Problem Option</span>
                        <div class="health-choice-list">
                            <label class="health-choice"><input type="radio" name="healthProblemCategory" value="none" checked data-health-option> No</label>
                            <label class="health-choice"><input type="radio" name="healthProblemCategory" value="common" data-health-option> Common Health Problem</label>
                        </div>
                    </div>
                    <div class="span-2 health-problem-panel" data-health-panel="common" hidden>
                        <span class="field-label">Common Health Problem</span>
                        <div class="checkbox-grid">
                            <?php foreach ($commonProblems as $p): ?>
                                <label class="check"><input type="checkbox" name="commonHealthProblems[]" value="<?= e($p) ?>" data-health-problem="common"> <?= e($p) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="slots-inline-panel">
                    <div class="slots-inline-head">
                        <strong>Available Slots</strong>
                        <div class="inline-form" data-slot-check-form>
                            <input type="hidden" name="page" value="appointments">
                            <input type="date" name="date" value="<?= e($date) ?>" min="<?= e($today) ?>" data-slot-date-input>
                            <button class="btn small" type="button" data-slot-check>Check</button>
                        </div>
                    </div>
                    <div class="slot-list" data-slot-picker data-slot-date="<?= e($date) ?>">
                        <?php foreach ($slots as $slot): ?>
                        <button class="slot-button <?= e($slot['status']) ?>" type="button"
                            data-slot="<?= e($slot['time']) ?>" data-status="<?= e($slot['status']) ?>"
                            data-label="<?= e($slot['label']) ?>" <?= $slot['available'] ? '' : 'disabled' ?>
                            aria-label="<?= e($slot['time'].' '.$slot['label']) ?>">
                            <span class="slot-dot" aria-hidden="true"></span>
                            <span><?= e($slot['time']) ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="wizard-actions split">
                    <button class="btn" type="button" data-step-go="1">Back</button>
                    <button class="btn primary" type="button" data-step-next data-next-step="3">Review</button>
                </div>
            </section>

            <section class="wizard-step" data-step-panel="3" hidden>
                <div class="wizard-step-head"><span>Step 3</span><h3>Review / Submit</h3></div>
                <div class="review-grid">
                    <article class="review-card">
                        <div><span class="field-label">Service Details</span><button class="btn small" type="button" data-step-go="1">Edit</button></div>
                        <dl class="review-list">
                            <dt>Service Type</dt><dd data-review-service-category>-</dd>
                            <dt>Treatment</dt><dd data-review-service>-</dd>
                            <dt>Duration</dt><dd data-review-duration>-</dd>
                            <dt>Price</dt><dd data-review-price>-</dd>
                            <dt>Dentist</dt><dd data-review-dentist>-</dd>
                        </dl>
                    </article>
                    <article class="review-card">
                        <div><span class="field-label">Patient Details</span><button class="btn small" type="button" data-step-go="2">Edit</button></div>
                        <dl class="review-list">
                            <dt>Patient</dt><dd data-review-patient>-</dd>
                            <dt>Age</dt><dd data-review-age>-</dd>
                            <dt>Date &amp; Time</dt><dd data-review-schedule>-</dd>
                            <dt>Health Problem</dt><dd data-review-health>-</dd>
                        </dl>
                    </article>
                </div>
                <div class="wizard-actions split">
                    <button class="btn" type="button" data-step-go="2">Back</button>
                    <button class="btn primary" type="button" data-step-next data-next-step="4">Proceed to Payment</button>
                </div>
            </section>

            <section class="wizard-step" data-step-panel="4" hidden>
                <div class="wizard-step-head"><span>Step 4</span><h3>Payment</h3></div>
                <div class="payment-layout">
                    <div class="payment-options">
                        <span class="field-label">Payment Method</span>
                        <label class="payment-card">
                            <input type="radio" name="paymentMethod" value="counter" checked>
                            <span>
                                <strong>Pay at clinic counter</strong>
                                <small class="muted">Payment is completed at the clinic after appointment confirmation.</small>
                                <span class="payment-card-amount" data-counter-amount>
                                    <span class="field-label">Cash to bring</span>
                                    <strong data-payment-price-counter>-</strong>
                                </span>
                            </span>
                        </label>
                        <label class="payment-card">
                            <input type="radio" name="paymentMethod" value="qr" data-payment-qr-toggle>
                            <span>
                                <strong>QR Bank / E-wallet</strong>
                                <small class="muted">Choose TNG or bank QR payment before submitting.</small>
                                <span class="payment-card-amount" data-qr-amount>
                                    <span class="field-label">Amount to pay</span>
                                    <strong data-payment-price-qr>-</strong>
                                </span>
                            </span>
                        </label>
                        <div class="qr-payment-panel" data-qr-payment-panel hidden>
                            <span class="field-label">Choose QR Payment</span>
                            <div class="qr-method-list">
                                <label class="qr-method-card">
                                    <input type="radio" name="qrPaymentType" value="tng" data-qr-method>
                                    <img src="assets/logo-tng.png" alt="Touch 'n Go" class="qr-method-logo">
                                    <strong>Touch 'n Go</strong>
                                </label>
                                <label class="qr-method-card">
                                    <input type="radio" name="qrPaymentType" value="fpx" data-qr-method>
                                    <img src="assets/logo-fpx.png" alt="FPX" class="qr-method-logo">
                                    <strong>FPX</strong>
                                </label>
                            </div>
                            <div class="qr-preview" data-qr-preview hidden>
                                <span class="field-label" data-qr-preview-title>QR Payment</span>
                                <img src="" alt="QR Code" class="qr-code-image" data-qr-image hidden>
                                <a href="#" download="Putra_Dental_Clinic_QR.jpg" class="qr-download-btn" data-qr-download-btn style="display:none">📥 Download Now</a>
                                <small class="muted" data-qr-instruction>Scan the QR code with your e-wallet or banking app to pay.</small>
                            </div>
                            <div class="receipt-upload-section" data-receipt-upload-section hidden>
                                <span class="field-label">Upload Payment Receipt</span>
                                <label class="receipt-upload-area" data-receipt-drop-zone>
                                    <input type="file" name="paymentReceipt" accept="image/jpeg,image/png,image/webp,application/pdf" data-receipt-input hidden>
                                    <span class="receipt-upload-icon">📄</span>
                                    <span class="receipt-upload-text">Click or drag to upload receipt</span>
                                    <small class="muted">JPG, PNG, WEBP or PDF (max 5 MB)</small>
                                </label>
                                <div class="receipt-preview" data-receipt-preview hidden>
                                    <img data-receipt-preview-img alt="Receipt preview">
                                    <div class="receipt-preview-info">
                                        <strong data-receipt-file-name>-</strong>
                                        <button class="btn small danger" type="button" data-receipt-remove>Remove</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <aside class="payment-summary">
                        <span class="field-label">Booking Receipt</span>
                        <div class="receipt-invoice" data-receipt-invoice>
                            <div class="receipt-invoice-header">
                                <img src="assets/clinic-logo.png" alt="Klinik Pergigian Putra" class="receipt-logo">
                                <small class="muted">Taman Universiti, Parit Raja, Johor</small>
                            </div>
                            <hr class="receipt-divider">
                            <dl class="receipt-invoice-list">
                                <dt>Treatment</dt><dd data-receipt-service>-</dd>
                                <dt>Dentist</dt><dd data-receipt-dentist>-</dd>
                                <dt>Patient</dt><dd data-receipt-patient>-</dd>
                                <dt>Date & Time</dt><dd data-receipt-schedule>-</dd>
                                <dt>Duration</dt><dd data-receipt-duration>-</dd>
                                <dt>Payment</dt><dd data-receipt-method>-</dd>
                            </dl>
                            <hr class="receipt-divider">
                            <div class="receipt-invoice-total">
                                <span>Total</span>
                                <strong data-receipt-total>-</strong>
                            </div>
                        </div>
                    </aside>
                </div>
                <div class="wizard-actions split">
                    <div style="display:flex;gap:10px">
                        <button class="btn" type="button" data-step-go="3">Back</button>
                        <button class="btn danger" type="button" data-cancel-booking>Cancel</button>
                    </div>
                    <button class="btn primary" type="submit">Submit Booking</button>
                </div>
            </section>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Appointment Schedule</h2></div>
        <?php render_appointment_table($appointments, $user, true); ?>
    </section>
    <?php
}

function render_appointment_table(array $appointments, array $user, bool $showPatient): void
{
    if (!$appointments) {
        echo '<p class="empty">No appointments found.</p>';
        return;
    }
    $canManage = has_role($user, ['admin', 'staff']);
    ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th><th>Time</th>
                    <?php if ($showPatient): ?><th>Patient</th><?php endif; ?>
                    <th>Health</th><th>Service</th><th>Duration</th><th>Status</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $a): ?>
                <tr>
                    <?php if ($canManage): ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_appointment">
                            <input type="hidden" name="appointmentID" value="<?= e($a['appointmentID']) ?>">
                            <td><input class="table-input" type="date" name="appointmentDate" value="<?= e($a['appointmentDate']) ?>"></td>
                            <td>
                                <select class="table-input" name="appointmentTime">
                                    <?php foreach (build_time_slots() as $slot): ?>
                                        <option value="<?= e($slot) ?>" <?= substr((string)$a['appointmentTime'],0,5)===$slot?'selected':'' ?>><?= e($slot) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <?php if ($showPatient): ?><td><?= e($a['username'] ?? 'Patient') ?></td><?php endif; ?>
                            <td><?= e(format_appointment_health_summary($a)) ?></td>
                            <td><?= e($a['serviceType']) ?></td>
                            <td><?= e(format_duration((int)$a['duration'])) ?></td>
                            <td>
                                <select class="table-input" name="status">
                                    <?php foreach (['pending','confirmed','completed','cancelled'] as $s): ?>
                                        <option value="<?= e($s) ?>" <?= $a['status']===$s?'selected':'' ?>><?= e(ucfirst($s)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><button class="btn small" type="submit">Save</button></td>
                        </form>
                    <?php else: ?>
                        <td><?= e($a['appointmentDate']) ?></td>
                        <td><?= e(substr((string)$a['appointmentTime'],0,5)) ?></td>
                        <?php if ($showPatient): ?><td><?= e($a['username'] ?? '') ?></td><?php endif; ?>
                        <td><?= e(format_appointment_health_summary($a)) ?></td>
                        <td><?= e($a['serviceType']) ?></td>
                        <td><?= e(format_duration((int)$a['duration'])) ?></td>
                        <td><span class="status <?= e($a['status']) ?>"><?= e($a['status']) ?></span></td>
                        <td>
                            <?php if (in_array($a['status'], ['pending','confirmed'], true)): ?>
                            <form method="post" data-confirm="Cancel this appointment?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="cancel_appointment">
                                <input type="hidden" name="appointmentID" value="<?= e($a['appointmentID']) ?>">
                                <button class="btn small danger" type="submit">Cancel</button>
                            </form>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function format_appointment_health_summary(array $a): string
{
    $parts = [];
    $age = (int)($a['patientAge'] ?? 0);
    if ($age > 0) $parts[] = 'Age ' . $age;
    $cat = (string)($a['healthProblemCategory'] ?? 'none');
    if ($cat === 'common' || $cat === 'chronic') {
        $detail = trim((string)($a['healthProblemDetail'] ?? ''));
        $parts[] = health_problem_category_label($cat) . ($detail !== '' ? ': ' . $detail : '');
    } else {
        $parts[] = 'No health problem';
    }
    return implode(' — ', $parts);
}
