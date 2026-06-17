<?php
require_once __DIR__ . '/load_env.php';

// ── Gmail SMTP Configuration ─────────────────────────────────────────────────
// All credentials loaded from .env (local) or Railway env vars (production).
// Generate App Password at: Google Account → Security → 2-Step Verification → App Passwords

define('MAIL_HOST',       'smtp.gmail.com');
define('MAIL_PORT',       587);
define('MAIL_USERNAME',   getenv('MAIL_USERNAME'));
define('MAIL_PASSWORD',   getenv('MAIL_PASSWORD'));
define('MAIL_FROM_NAME',  'Klinik Pergigian Putra - Detabot');
define('MAIL_FROM_EMAIL', getenv('MAIL_USERNAME'));

// ── Clinic details (used in email footers) ───────────────────────────────────
define('CLINIC_NAME',    'Klinik Pergigian Putra');
define('CLINIC_ADDRESS', 'Taman Universiti, Parit Raja, Batu Pahat, Johor');
define('CLINIC_PHONE',   '07-453 8899');

$appUrl = rtrim(getenv('APP_URL') ?: 'http://localhost/Detabot_VSCode/public', '/');
define('APP_URL',        $appUrl);
define('CLINIC_URL',     $appUrl . '/appointments.php');
