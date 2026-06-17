<?php
declare(strict_types=1);

function page_clinic(array $user): void
{
    $clinic    = clinic();
    $canManage = has_role($user, ['admin', 'staff']);

    // Services
    $services = clinic_services();

    // Same canonical dentist list as book_appointment.php $allowedDentists
    $canonicalDentists = [
        ['name' => 'Dr. Muhammad Firdaus', 'specialty' => 'General Dentist',     'initials' => 'MF'],
        ['name' => 'Dr. Siti Zafirah',     'specialty' => 'Cosmetic Specialist', 'initials' => 'SZ'],
        ['name' => 'Dr. Alia Suhana',      'specialty' => 'Orthodontist',        'initials' => 'AS'],
    ];

    // Index staff users by lower-case username for avatar lookup
    $staffRows   = db_all("SELECT username, userAvatar FROM tbl_user WHERE userRole = 'staff' AND status = 'active'");
    $staffByName = [];
    foreach ($staffRows as $s) {
        $staffByName[strtolower(trim((string) $s['username']))] = $s;
    }

    // Build dentist list: attach avatar from tbl_user where name matches
    $dentists = [];
    foreach ($canonicalDentists as $doc) {
        $photo = '';
        $key   = strtolower($doc['name']);

        // Exact match first, then partial (e.g. "Firdaus" in full name)
        if (!empty($staffByName[$key]['userAvatar'])) {
            $photo = 'assets/avatars/' . rawurlencode((string) $staffByName[$key]['userAvatar']);
        } else {
            foreach ($staffByName as $sKey => $s) {
                if (!empty($s['userAvatar']) && str_contains($key, $sKey)) {
                    $photo = 'assets/avatars/' . rawurlencode((string) $s['userAvatar']);
                    break;
                }
            }
        }

        $dentists[] = $doc + ['photo' => $photo];
    }
    $dentistCount = count($dentists);

    // Current day
    $dayOfWeek = (int) date('w'); // 0 = Sun … 6 = Sat

    // Build per-day hours map: index → display string or 'Closed'
    $dayHoursDisplay = [];
    $clinicHoursJSON = trim((string) ($clinic['clinicHoursJSON'] ?? ''));
    if ($clinicHoursJSON !== '') {
        $hDecoded = json_decode($clinicHoursJSON, true);
        if (is_array($hDecoded)) {
            foreach ($hDecoded as $dIdx => $hInfo) {
                if (!empty($hInfo['closed'])) {
                    $dayHoursDisplay[(int) $dIdx] = 'Closed';
                } else {
                    $o = date('g:i A', strtotime((string) ($hInfo['open']  ?? '09:00')));
                    $c = date('g:i A', strtotime((string) ($hInfo['close'] ?? '17:00')));
                    $dayHoursDisplay[(int) $dIdx] = $o . ' – ' . $c;
                }
            }
        }
    }
    if (empty($dayHoursDisplay)) {
        // Fallback: parse old operatingHours string, apply Mon–Sat open, Sun closed
        $ohStr = trim((string) ($clinic['operatingHours'] ?? 'Monday to Saturday, 9:00 AM – 5:00 PM'));
        preg_match('/(\d{1,2}:\d{2}\s*(?:AM|PM)?)\s*[-–]\s*(\d{1,2}:\d{2}\s*(?:AM|PM)?)/i', $ohStr, $tm);
        $wh = (isset($tm[1]) ? trim($tm[1]) : '9:00 AM') . ' – ' . (isset($tm[2]) ? trim($tm[2]) : '5:00 PM');
        for ($i = 0; $i <= 6; $i++) {
            $dayHoursDisplay[$i] = ($i >= 1 && $i <= 6) ? $wh : 'Closed';
        }
    }
    $isOpenToday = ($dayHoursDisplay[$dayOfWeek] ?? 'Closed') !== 'Closed';

    // Clinic fields
    $clinicName = trim((string) ($clinic['clinicName'] ?? 'Clinic Putra Dental'));
    $location   = trim((string) ($clinic['location']   ?? 'Taman Universiti, Parit Raja, Batu Pahat, Johor'));
    $phone      = trim((string) ($clinic['contactNumber'] ?? '07-453 8899'));
    $clinicMail = trim((string) ($clinic['clinicEmail']   ?? 'info@putradental.my'));
    $promos     = trim((string) ($clinic['promotions']    ?? ''));

    // Phone variants
    $phoneTel = (string) preg_replace('/\D/', '', $phone);
    $phoneWa  = '60' . ltrim($phoneTel, '0');

    // Maps
    $mapsLat   = 1.8488046015387936;
    $mapsLng   = 103.07166742377734;
    $mapsEmbed = "https://maps.google.com/maps?q={$mapsLat},{$mapsLng}&z=16&output=embed";
    $mapsDir   = "https://www.google.com/maps/dir/?api=1&destination={$mapsLat},{$mapsLng}";

    // User
    $firstName = explode(' ', trim((string) ($user['username'] ?? 'there')))[0];

    $sa = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
    ?>

    <style>
        /* ── Clinic Information ──────────────────────────────────────────── */
        .ci-hero {
            background: linear-gradient(135deg, #3b0764 0%, #4c1d95 55%, #5b21b6 100%);
            border-radius: 14px;
            padding: 2.25rem 2.5rem;
            margin-bottom: 1.4rem;
            color: #fff;
            position: relative;
            overflow: hidden;
        }
        .ci-blob {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }
        .ci-blob-1 {
            top: -50px; right: -50px;
            width: 240px; height: 240px;
            background: rgba(255,255,255,.06);
            filter: blur(45px);
        }
        .ci-blob-2 {
            bottom: -60px; right: 70px;
            width: 160px; height: 160px;
            background: rgba(167,139,250,.14);
            filter: blur(32px);
        }
        .ci-hero-inner { position: relative; z-index: 2; }
        .ci-hero h2 {
            font-family: 'Sora', sans-serif;
            font-size: 1.85rem; font-weight: 700;
            margin: 0 0 .4rem; letter-spacing: -.01em;
        }
        .ci-hero .ci-tagline {
            margin: 0 0 1.1rem; opacity: .85;
            font-size: .95rem; max-width: 560px; line-height: 1.55;
        }
        .ci-badges { display: flex; gap: .6rem; flex-wrap: wrap; }
        .ci-badge {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .28rem .85rem; border-radius: 20px;
            font-size: .78rem; font-weight: 600;
            border: 1px solid rgba(255,255,255,.3);
            background: rgba(255,255,255,.12);
            backdrop-filter: blur(8px);
        }
        .ci-badge svg { width: 13px; height: 13px; stroke: #fff; flex-shrink: 0; }
        .ci-badge.open   { background: rgba(16,185,129,.28); border-color: rgba(16,185,129,.55); }
        .ci-badge.closed { background: rgba(239,68,68,.22);  border-color: rgba(239,68,68,.48); }

        /* ── Layout ─────────────────────────────────────────────────────── */
        .ci-two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }
        @media (max-width: 760px) { .ci-two-col { grid-template-columns: 1fr; } }

        /* ── Generic card ──────────────────────────────────────────────── */
        .ci-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 1.5rem;
        }
        .ci-card-title {
            display: flex; align-items: center; gap: .65rem;
            font-size: .97rem; font-weight: 700; color: #1f2937;
            margin: 0 0 1.25rem;
        }
        .ci-icon-box {
            width: 34px; height: 34px; border-radius: 9px;
            background: linear-gradient(135deg,#3b0764,#7c3aed);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .ci-icon-box svg { width: 17px; height: 17px; stroke: #fff; }

        /* ── Contact card ──────────────────────────────────────────────── */
        .ci-contact-row {
            display: flex; align-items: flex-start; gap: .9rem;
            padding: .7rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .ci-contact-row:last-of-type { border-bottom: none; }
        .ci-cicon {
            width: 36px; height: 36px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .ci-cicon svg { width: 16px; height: 16px; stroke: #fff; }
        .ci-clabel { font-size: .72rem; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .12rem; }
        .ci-cvalue { font-size: .9rem; font-weight: 500; color: #1f2937; }
        .ci-action-row { display: flex; gap: .6rem; margin-top: 1.25rem; flex-wrap: wrap; }
        .ci-act-btn {
            flex: 1; min-width: 76px;
            display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
            padding: .55rem .85rem; border-radius: 9px;
            font-size: .8rem; font-weight: 600;
            text-decoration: none;
            transition: opacity .2s, transform .15s;
        }
        .ci-act-btn:hover { opacity: .87; transform: translateY(-1px); }
        .ci-act-btn svg { width: 14px; height: 14px; stroke: #fff; }
        .ci-act-btn.call  { background: linear-gradient(135deg,#3b0764,#7c3aed); color: #fff; }
        .ci-act-btn.wa    { background: linear-gradient(135deg,#15803d,#22c55e); color: #fff; }
        .ci-act-btn.email { background: linear-gradient(135deg,#1d4ed8,#3b82f6); color: #fff; }

        /* ── Hours card ─────────────────────────────────────────────────── */
        .ci-hours-tbl { width: 100%; border-collapse: collapse; }
        .ci-hours-tbl td { padding: .55rem .4rem; font-size: .875rem; }
        .ci-hours-tbl td:first-child { font-weight: 600; color: #374151; }
        .ci-hours-tbl td:last-child  { text-align: right; }
        .ci-hours-tbl tr { border-bottom: 1px solid #f3f4f6; }
        .ci-hours-tbl tr:last-child  { border-bottom: none; }
        .ci-today-row { background: #faf5ff !important; }
        .ci-today-row td { color: #5b21b6; font-weight: 700; }
        .ci-today-pill {
            display: inline-flex; align-items: center;
            background: #d1fae5; color: #065f46;
            border-radius: 20px; font-size: .7rem; font-weight: 700;
            padding: .12rem .5rem; margin-left: .5rem;
        }
        .ci-open   { color: #059669; font-weight: 600; }
        .ci-closed { color: #dc2626; }

        /* ── Map card ───────────────────────────────────────────────────── */
        .ci-map-card { padding: 0; overflow: hidden; margin-bottom: 1.25rem; }
        .ci-map-head { padding: 1.4rem 1.5rem .9rem; margin-bottom: 0 !important; }
        .ci-map-iframe { width: 100%; height: 300px; border: none; display: block; }
        .ci-map-foot {
            padding: 1rem 1.5rem;
            display: flex; align-items: center; justify-content: space-between;
            gap: 1rem; flex-wrap: wrap;
            border-top: 1px solid #f3f4f6;
        }
        .ci-map-addr { display: flex; align-items: center; gap: .5rem; font-size: .86rem; color: #6b7280; }
        .ci-map-addr svg { width: 15px; height: 15px; stroke: #9ca3af; flex-shrink: 0; }
        .ci-dir-btn {
            display: inline-flex; align-items: center; gap: .4rem;
            background: linear-gradient(135deg,#3b0764,#7c3aed);
            color: #fff; text-decoration: none;
            padding: .5rem 1.15rem; border-radius: 9px;
            font-size: .82rem; font-weight: 600;
            transition: opacity .2s;
        }
        .ci-dir-btn:hover { opacity: .87; }
        .ci-dir-btn svg { width: 14px; height: 14px; stroke: #fff; }

        /* ── Promo card ─────────────────────────────────────────────────── */
        .ci-promo {
            background: #faeeda; border: 1px solid #fac775;
            border-radius: 14px; padding: 1.25rem 1.5rem;
            margin-bottom: 1.25rem;
            display: flex; align-items: flex-start; gap: 1rem;
        }
        .ci-promo-icon {
            width: 40px; height: 40px; flex-shrink: 0; border-radius: 10px;
            background: linear-gradient(135deg,#f59e0b,#d97706);
            display: flex; align-items: center; justify-content: center;
        }
        .ci-promo-icon svg { width: 20px; height: 20px; stroke: #fff; }
        .ci-promo strong { display: block; font-size: .76rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #92400e; margin-bottom: .4rem; }
        .ci-promo p { margin: 0; font-size: .9rem; color: #78350f; line-height: 1.55; white-space: pre-line; }

        /* ── Dentists ───────────────────────────────────────────────────── */
        .ci-dentist-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1rem; }
        .ci-dentist-card {
            background: #faf9ff; border: 1px solid #e5e7eb;
            border-radius: 12px; padding: 1.35rem;
            display: flex; flex-direction: column; align-items: center; text-align: center;
            gap: .55rem;
            transition: transform .2s, box-shadow .2s;
        }
        .ci-dentist-card:hover { transform: translateY(-3px); box-shadow: 0 8px 22px rgba(59,7,100,.1); }
        .ci-dentist-avatar {
            width: 70px; height: 70px; border-radius: 50%;
            background: linear-gradient(135deg,#3b0764,#7c3aed);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Sora', sans-serif;
            font-size: 1.1rem; font-weight: 700; color: #fff;
            flex-shrink: 0; overflow: hidden;
            border: 2.5px solid #ede9fe;
        }
        .ci-dentist-avatar img {
            width: 100%; height: 100%;
            object-fit: cover; border-radius: 50%;
            display: block;
        }
        .ci-dentist-name { font-size: .88rem; font-weight: 700; color: #1f2937; }
        .ci-dentist-role { font-size: .74rem; font-weight: 600; color: #7c3aed; background: #ede9fe; padding: .18rem .6rem; border-radius: 20px; }

        /* ── Services ───────────────────────────────────────────────────── */
        .ci-svc-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: .65rem;
        }
        @media (max-width: 900px) { .ci-svc-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 540px) { .ci-svc-grid { grid-template-columns: 1fr; } }
        .ci-svc-chip {
            background: #faf9ff; border: 1px solid #e5e7eb;
            border-radius: 10px; padding: .65rem .9rem;
            display: flex; align-items: center; gap: .55rem;
            font-size: .84rem; font-weight: 500; color: #374151;
            transition: background .18s, border-color .18s;
            cursor: default;
        }
        .ci-svc-chip:hover { background: #ede9fe; border-color: #c4b5fd; }
        .ci-svc-chip svg { width: 15px; height: 15px; stroke: #7c3aed; flex-shrink: 0; }

        /* ── Section spacing ──────────────────────────────────────────── */
        .ci-mb { margin-bottom: 1.25rem; }
    </style>

    <?php /* ── HERO ── */ ?>
    <div class="ci-hero">
        <div class="ci-blob ci-blob-1"></div>
        <div class="ci-blob ci-blob-2"></div>
        <div class="ci-hero-inner">
            <h2><?= e($clinicName) ?></h2>
            <p class="ci-tagline">Your trusted dental care partner in Parit Raja. Quality treatments, friendly dentists, and modern facilities.</p>
            <div class="ci-badges">
                <span class="ci-badge <?= $isOpenToday ? 'open' : 'closed' ?>" id="ciOpenBadge">
                    <svg <?= $sa ?>><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span id="ciOpenText"><?= $isOpenToday ? 'Open Today' : 'Closed Today' ?></span>
                </span>
                <span class="ci-badge">
                    <svg <?= $sa ?>><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    Trusted Clinic
                </span>
                <span class="ci-badge">
                    <svg <?= $sa ?>><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <?= $dentistCount ?> Dentists
                </span>
            </div>
        </div>
    </div>

    <?php /* ── CONTACT + HOURS ── */ ?>
    <div class="ci-two-col">

        <!-- Contact Details -->
        <div class="ci-card">
            <h3 class="ci-card-title">
                <span class="ci-icon-box">
                    <svg <?= $sa ?>><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </span>
                Contact Details
            </h3>

            <div class="ci-contact-row">
                <div class="ci-cicon" style="background:linear-gradient(135deg,#3b0764,#7c3aed)">
                    <svg <?= $sa ?>><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="3"/></svg>
                </div>
                <div>
                    <div class="ci-clabel">Location</div>
                    <div class="ci-cvalue"><?= e($location) ?></div>
                </div>
            </div>

            <div class="ci-contact-row">
                <div class="ci-cicon" style="background:linear-gradient(135deg,#0ea5e9,#2563eb)">
                    <svg <?= $sa ?>><path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.79 19.79 0 0 1 4.73 15c-.78-1.37-1.4-2.8-1.84-4.26A2 2 0 0 1 4.76 8.4l3-.28"/></svg>
                </div>
                <div>
                    <div class="ci-clabel">Phone</div>
                    <div class="ci-cvalue"><?= e($phone) ?></div>
                </div>
            </div>

            <div class="ci-contact-row">
                <div class="ci-cicon" style="background:linear-gradient(135deg,#10b981,#059669)">
                    <svg <?= $sa ?>><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                </div>
                <div>
                    <div class="ci-clabel">Email</div>
                    <div class="ci-cvalue"><?= e($clinicMail ?: 'info@putradental.my') ?></div>
                </div>
            </div>

            <div class="ci-action-row">
                <a href="tel:<?= e($phoneTel) ?>" class="ci-act-btn call">
                    <svg <?= $sa ?>><path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.79 19.79 0 0 1 4.73 15c-.78-1.37-1.4-2.8-1.84-4.26A2 2 0 0 1 4.76 8.4l3-.28"/></svg>
                    Call
                </a>
                <a href="https://wa.me/<?= e($phoneWa) ?>?text=Hello%2C%20I%20would%20like%20to%20enquire%20about%20dental%20services." target="_blank" rel="noopener noreferrer" class="ci-act-btn wa">
                    <svg <?= $sa ?>><path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.79 19.79 0 0 1 4.73 15c-.78-1.37-1.4-2.8-1.84-4.26A2 2 0 0 1 4.76 8.4l3-.28"/></svg>
                    WhatsApp
                </a>
                <a href="mailto:<?= e($clinicMail ?: 'info@putradental.my') ?>" class="ci-act-btn email">
                    <svg <?= $sa ?>><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                    Email
                </a>
            </div>
        </div>

        <!-- Operating Hours -->
        <div class="ci-card">
            <h3 class="ci-card-title">
                <span class="ci-icon-box">
                    <svg <?= $sa ?>><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </span>
                Operating Hours
            </h3>
            <?php
            $weekDays = [
                1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
                4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday',
            ];
            ?>
            <table class="ci-hours-tbl">
                <?php foreach ($weekDays as $idx => $dayName):
                    $isToday   = ($idx === $dayOfWeek);
                    $hoursText = $dayHoursDisplay[$idx] ?? 'Closed';
                    $isOpen    = $hoursText !== 'Closed';
                ?>
                <tr class="<?= $isToday ? 'ci-today-row' : '' ?>" data-day="<?= $idx ?>">
                    <td>
                        <?= e($dayName) ?>
                        <?php if ($isToday): ?>
                            <span class="ci-today-pill">Today</span>
                        <?php endif; ?>
                    </td>
                    <td class="<?= $isOpen ? 'ci-open' : 'ci-closed' ?>">
                        <?= e($hoursText) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

    </div>

    <?php /* ── MAP ── */ ?>
    <div class="ci-card ci-map-card">
        <h3 class="ci-card-title ci-map-head">
            <span class="ci-icon-box">
                <svg <?= $sa ?>><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="3"/></svg>
            </span>
            Our Location
        </h3>
        <iframe
            class="ci-map-iframe"
            src="<?= e($mapsEmbed) ?>"
            allowfullscreen
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            title="Clinic Location on Google Maps">
        </iframe>
        <div class="ci-map-foot">
            <div class="ci-map-addr">
                <svg <?= $sa ?>><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="3"/></svg>
                <?= e($location) ?>
            </div>
            <a href="<?= e($mapsDir) ?>" target="_blank" rel="noopener noreferrer" class="ci-dir-btn">
                <svg <?= $sa ?>><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                Get Directions
            </a>
        </div>
    </div>

    <?php /* ── PROMOTION ── */ if ($promos !== ''): ?>
    <div class="ci-promo">
        <div class="ci-promo-icon">
            <svg <?= $sa ?>><rect x="3" y="8" width="18" height="13" rx="2"/><path d="M12 8v13M3 12h18"/><path d="M7.5 8A2.5 2.5 0 1 1 12 6.5V8"/><path d="M16.5 8A2.5 2.5 0 1 0 12 6.5V8"/></svg>
        </div>
        <div>
            <strong>Current Promotion</strong>
            <p><?= nl2br(e($promos)) ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php /* ── DENTISTS ── */ ?>
    <div class="ci-card ci-mb">
        <h3 class="ci-card-title">
            <span class="ci-icon-box">
                <svg <?= $sa ?>><path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3"/><path d="M8 15v1a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6v-4"/><circle cx="20" cy="10" r="2"/></svg>
            </span>
            Our Dentists
        </h3>
        <div class="ci-dentist-grid">
            <?php foreach ($dentists as $doc): ?>
            <div class="ci-dentist-card">
                <div class="ci-dentist-avatar">
                    <?php if (!empty($doc['photo'])): ?>
                        <img
                            src="<?= e($doc['photo']) ?>"
                            alt="<?= e($doc['name']) ?>"
                            onerror="this.parentElement.innerHTML='<?= e($doc['initials']) ?>'">
                    <?php else: ?>
                        <?= e($doc['initials']) ?>
                    <?php endif; ?>
                </div>
                <div class="ci-dentist-name"><?= e($doc['name']) ?></div>
                <div class="ci-dentist-role"><?= e($doc['specialty']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php /* ── SERVICES OFFERED ── */ ?>
    <div class="ci-card ci-mb">
        <h3 class="ci-card-title">
            <span class="ci-icon-box">
                <svg <?= $sa ?>><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            </span>
            Services Offered
            <span style="margin-left:auto;background:linear-gradient(135deg,#3b0764,#7c3aed);color:#fff;border-radius:20px;padding:.17rem .75rem;font-size:.74rem;font-weight:700;">
                <?= count($services) ?> services
            </span>
        </h3>
        <div class="ci-svc-grid">
            <?php foreach ($services as $svc): ?>
            <div class="ci-svc-chip">
                <svg <?= $sa ?>><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="2"/></svg>
                <?= e($svc) ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php /* ── ADMIN EDIT BUTTON ── */ if (has_role($user, 'admin')): ?>
    <div style="margin-bottom:1.25rem;text-align:right">
        <a href="<?= e(page_url('edit_clinic_information')) ?>"
           style="display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#3b0764,#5b21b6);color:#fff;text-decoration:none;padding:9px 20px;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:700;transition:opacity .15s"
           onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit Clinic Information
        </a>
    </div>
    <?php endif; ?>

    <!-- Chatbot globals must appear BEFORE chat.js -->
    <script>
        window.DETABOT_USER_ID      = <?= (int) $user['userID'] ?>;
        window.DETABOT_USER_AGE     = <?= (int) ($user['userAge'] ?? 0) ?>;
        window.DETABOT_PAGE_CONTEXT = 'clinic_information';
    </script>

    <!-- Floating Chatbot Widget -->
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
                <div class="chatbot-bubble">Hi <?= e($firstName) ?>! 🦷 Want to know more about our clinic, services, or hours? Just ask!</div>
                <div class="chatbot-quick-replies">
                    <button class="chatbot-quick-btn" data-msg="What are the clinic operating hours?">🕐 Clinic Hours</button>
                    <button class="chatbot-quick-btn" data-msg="What dental services do you offer?">🦷 Our Services</button>
                    <button class="chatbot-quick-btn" data-msg="Where is the clinic located?">📍 Location &amp; Directions</button>
                    <button class="chatbot-quick-btn" data-msg="How can I contact the clinic?">📞 Contact Info</button>
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

    <script src="assets/chat.js"></script>

    <script>
    (function () {
        // Re-calculate open/closed using client JS date (timezone fallback)
        var day    = new Date().getDay(); // 0=Sun … 6=Sat
        var isOpen = (day >= 1 && day <= 6);
        var badge  = document.getElementById('ciOpenBadge');
        var text   = document.getElementById('ciOpenText');
        if (badge && text) {
            badge.className = 'ci-badge ' + (isOpen ? 'open' : 'closed');
            text.textContent = isOpen ? 'Open Today' : 'Closed Today';
        }

        // Highlight today row using JS day (ensures it matches local time)
        var rows = document.querySelectorAll('.ci-hours-tbl tr[data-day]');
        rows.forEach(function (row) {
            var rowDay = parseInt(row.getAttribute('data-day'), 10);
            if (rowDay === day) {
                row.classList.add('ci-today-row');
                // Append Today pill if not already present
                var firstCell = row.querySelector('td:first-child');
                if (firstCell && !firstCell.querySelector('.ci-today-pill')) {
                    var pill = document.createElement('span');
                    pill.className = 'ci-today-pill';
                    pill.textContent = 'Today';
                    firstCell.appendChild(pill);
                }
            }
        });
    }());
    </script>
<?php
}
