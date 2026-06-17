<?php
declare(strict_types=1);

require __DIR__ . '/../app/app.php';

if (isset($_GET['slots'])) {
    detabot_boot();
    handle_appointment_slots();
}

render_protected_page('appointments');
