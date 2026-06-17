<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

if (!empty($_SESSION['userID'])) {
    log_activity('user_logout', 'User signed out', (int) $_SESSION['userID']);
}

session_destroy();
header('Location: login.php');
exit;
