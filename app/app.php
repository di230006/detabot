<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/actions.php';
require_once __DIR__ . '/layout.php';

function detabot_boot(): void
{
    db();

    if (isset($_GET['export'])) {
        handle_export();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        handle_post();
    }
}

function render_protected_page(string $page, array|string|null $roles = null): void
{
    detabot_boot();

    $user = require_login();

    if ($roles !== null) {
        $user = require_role($roles);
    }

    require_once APP_PATH . '/views/appointments.php';
    require_once APP_PATH . '/views/' . $page . '.php';

    $function = 'page_' . $page;
    if (!function_exists($function)) {
        http_response_code(404);
        exit('Page not found.');
    }

    render_header($user, $page);
    $function($user);
    render_footer();
}
