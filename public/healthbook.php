<?php
declare(strict_types=1);

require __DIR__ . '/../app/app.php';

detabot_boot();

header('Location: health_record.php');
exit;
