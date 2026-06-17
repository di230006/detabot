<?php
declare(strict_types=1);

require __DIR__ . '/../app/app.php';

detabot_boot();

if (current_user()) {
    redirect_to(page_url('dashboard'));
}

render_guest();
