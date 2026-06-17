# Detabot: Dental Appointment System With AI Chatbot

Detabot is a PHP web system for Clinic Putra Dental, Taman Universiti, Parit Raja, Johor. It implements the modules from the final-year-project report:

- Registration and login
- Appointment booking and schedule management
- AI/NLP-style chatbot for common clinic questions
- Clinic information management
- Feedback and staff response
- Admin/staff user management
- Reports and analytics with CSV export
- Reward points and redemption catalog

## Quick Run

From this project folder, run:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8080 -t public
```

Open:

```text
http://127.0.0.1:8080
```

The app uses SQLite automatically for local review, so it runs without creating a MySQL database first.

## Demo Accounts

```text
Admin:   admin@detabot.local / admin123
Staff:   staff@detabot.local / staff123
Patient: patient@detabot.local / patient123
```

## Forgot Password

The forgot password flow resets the password directly with the registered email and a new password.

If you later want email sending for another feature, copy `config.sample.php` to `config.php` and set:

```php
'mail' => [
    'driver' => 'smtp',
    'from_email' => 'yourclinic@gmail.com',
    'from_name' => 'Detabot',
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'port' => 465,
        'encryption' => 'ssl',
        'username' => 'yourclinic@gmail.com',
        'password' => 'your-google-app-password',
    ],
],
```

Use a Google app password, not the normal Gmail login password.

## Use With XAMPP MySQL

1. Start Apache and MySQL in XAMPP.
2. Import `database/detabot_mysql.sql` using phpMyAdmin or MySQL CLI.
3. Copy `config.sample.php` to `config.php`.
4. Confirm the database settings in `config.php`.
5. Serve `public` as the web root.

Example config:

```php
<?php

return [
    'db_driver' => 'mysql',
    'mysql' => [
        'host' => '127.0.0.1',
        'database' => 'detabot',
        'username' => 'root',
        'password' => '',
    ],
];
```

## Main Tables

The database keeps the report table names: `tbl_user`, `tbl_appointment`, `tbl_chatbot`, `tbl_clinic`, `tbl_feedback`, `tbl_report`, and `tbl_reward`. Two support tables are included for practical system operation: `tbl_reward_catalog` and `tbl_activity_log`.

## PHP File Structure

The system uses separate PHP files for each module:

- `public/dashboard.php`
- `public/appointments.php`
- `public/chatbot.php`
- `public/clinic.php`
- `public/feedback.php`
- `public/rewards.php`
- `public/reports.php`
- `public/users.php`
- `public/activity.php`

Shared logic is kept in `app/`, and module views are kept in `app/views/`.
