<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/load_env.php';

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', __DIR__);

date_default_timezone_set('Asia/Kuala_Lumpur');
session_name('detabot_session');
$sessionPath = ROOT_PATH . '/data/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0775, true);
}
session_save_path($sessionPath);
session_start();

$defaultConfig = [
    'app_name' => 'Detabot',
    'clinic_name' => 'Clinic Putra Dental',
    'db_driver' => getenv('DETABOT_DB_DRIVER') ?: 'sqlite',
    'sqlite_path' => ROOT_PATH . '/data/detabot.sqlite',
    'mysql' => [
        'host' => getenv('DETABOT_DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('DETABOT_DB_PORT') ?: 3306),
        'database' => getenv('DETABOT_DB_NAME') ?: 'detabot',
        'username' => getenv('DETABOT_DB_USER') ?: 'root',
        'password' => getenv('DETABOT_DB_PASS') ?: '',
    ],
    'slots' => [
        'start' => '09:00',
        'end' => '17:00',
        'interval_minutes' => 60,
    ],
    'mail' => [
        'driver' => getenv('DETABOT_MAIL_DRIVER') ?: 'log',
        'from_email' => getenv('DETABOT_MAIL_FROM') ?: 'no-reply@detabot.local',
        'from_name' => getenv('DETABOT_MAIL_FROM_NAME') ?: 'Detabot',
        'log_path' => ROOT_PATH . '/data/mail.log',
        'smtp' => [
            'host' => getenv('DETABOT_SMTP_HOST') ?: 'smtp.gmail.com',
            'port' => (int) (getenv('DETABOT_SMTP_PORT') ?: 465),
            'encryption' => getenv('DETABOT_SMTP_ENCRYPTION') ?: 'ssl',
            'username' => getenv('DETABOT_SMTP_USERNAME') ?: '',
            'password' => getenv('DETABOT_SMTP_PASSWORD') ?: '',
            'timeout' => (int) (getenv('DETABOT_SMTP_TIMEOUT') ?: 15),
        ],
    ],
    'password_reset' => [
        'otp_minutes' => 10,
        'max_attempts' => 5,
    ],
    'reward_points_per_completed_appointment' => 20,
];

$localConfig = ROOT_PATH . '/config.php';
$config = file_exists($localConfig)
    ? array_replace_recursive($defaultConfig, require $localConfig)
    : $defaultConfig;

function config(?string $key = null, mixed $fallback = null): mixed
{
    global $config;

    if ($key === null) {
        return $config;
    }

    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $fallback;
        }
        $value = $value[$segment];
    }

    return $value;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $driver = strtolower((string) config('db_driver', 'sqlite'));

    if ($driver === 'mysql') {
        $mysql = config('mysql');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $mysql['host'],
            $mysql['port'],
            $mysql['database']
        );
        $pdo = new PDO($dsn, $mysql['username'], $mysql['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } else {
        $path = (string) config('sqlite_path');
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    initialize_database($pdo);

    return $pdo;
}

function initialize_database(PDO $pdo): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $id = $driver === 'mysql' ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $created = 'DATETIME DEFAULT CURRENT_TIMESTAMP';
    $suffix = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    $statements = [
        "CREATE TABLE IF NOT EXISTS tbl_user (
            userID $id,
            username VARCHAR(50) NOT NULL,
            userEmail VARCHAR(100) NOT NULL UNIQUE,
            userPassword VARCHAR(255) NOT NULL,
            userPhone VARCHAR(20) NOT NULL,
            userAge INT,
            userGender VARCHAR(10),
            userChronicHealthProblems TEXT,
            userAvatar VARCHAR(255),
            userRole VARCHAR(20) NOT NULL DEFAULT 'patient',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            createdDate $created
        )$suffix",

        "CREATE TABLE IF NOT EXISTS tbl_clinic (
            clinicID $id,
            clinicName VARCHAR(100) NOT NULL,
            location VARCHAR(255) NOT NULL,
            operatingHours VARCHAR(100) NOT NULL,
            contactNumber VARCHAR(20) NOT NULL,
            dentistName VARCHAR(100) NOT NULL,
            services TEXT NOT NULL,
            promotions TEXT,
            createdDate $created,
            updatedDate $created
        )$suffix",

        "CREATE TABLE IF NOT EXISTS tbl_appointment (
            appointmentID $id,
            userID INT NOT NULL,
            clinicID INT NOT NULL,
            appointmentDate DATE NOT NULL,
            appointmentTime TIME NOT NULL,
            serviceType VARCHAR(100) NOT NULL,
            duration INT NOT NULL DEFAULT 60,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            patientAge INT,
            healthProblemCategory VARCHAR(30) NOT NULL DEFAULT 'none',
            healthProblemDetail TEXT,
            notes TEXT,
            paymentReceipt VARCHAR(255),
            createdDate $created,
            updatedDate $created
        )$suffix",

        "CREATE TABLE IF NOT EXISTS tbl_feedback (
            feedbackID $id,
            userID INT NOT NULL,
            appointmentID INT NOT NULL,
            rating INT NOT NULL,
            comments TEXT NOT NULL,
            feedbackDate $created,
            adminResponse TEXT,
            responseDate DATETIME
        )$suffix",

        "CREATE TABLE IF NOT EXISTS tbl_report (
            reportID $id,
            reportType VARCHAR(50) NOT NULL,
            generatedBy VARCHAR(50) NOT NULL,
            reportDate $created,
            reportData TEXT NOT NULL,
            parameters TEXT,
            exportFormat VARCHAR(20) NOT NULL DEFAULT 'screen'
        )$suffix",

        "CREATE TABLE IF NOT EXISTS tbl_reward (
            rewardID $id,
            userID INT NOT NULL,
            pointsEarned INT NOT NULL DEFAULT 0,
            pointsRedeemed INT NOT NULL DEFAULT 0,
            currentBalance INT NOT NULL DEFAULT 0,
            transactionType VARCHAR(20) NOT NULL,
            transactionDate $created,
            rewardDescription TEXT NOT NULL
        )$suffix",

        "CREATE TABLE IF NOT EXISTS tbl_reward_catalog (
            rewardCatalogID $id,
            rewardName VARCHAR(100) NOT NULL,
            pointsRequired INT NOT NULL,
            description TEXT NOT NULL,
            isActive INT NOT NULL DEFAULT 1
        )$suffix",

        "CREATE TABLE IF NOT EXISTS tbl_activity_log (
            logID $id,
            userID INT,
            action VARCHAR(80) NOT NULL,
            details TEXT,
            createdDate $created
        )$suffix",

        "CREATE TABLE IF NOT EXISTS tbl_password_reset_otp (
            resetID $id,
            userID INT NOT NULL,
            userEmail VARCHAR(100) NOT NULL,
            otpHash VARCHAR(255) NOT NULL,
            expiresAt DATETIME NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            usedAt DATETIME,
            createdDate $created
        )$suffix",

        "CREATE TABLE IF NOT EXISTS tbl_health_book (
            entryID $id,
            userID INT NOT NULL,
            appointmentID INT,
            createdBy INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            `condition` TEXT NOT NULL,
            nextTreatment VARCHAR(200),
            nextDate DATE,
            notes TEXT,
            createdDate $created
        )$suffix",

        "CREATE TABLE IF NOT EXISTS tbl_health_book_file (
            fileID $id,
            entryID INT NOT NULL,
            originalName VARCHAR(255) NOT NULL,
            storedName VARCHAR(255) NOT NULL,
            mimeType VARCHAR(100) NOT NULL,
            fileSize INT NOT NULL DEFAULT 0,
            uploadedDate $created
        )$suffix",

        "CREATE TABLE IF NOT EXISTS tbl_chatbot (
            chatID $id,
            userID INT,
            sessionID VARCHAR(128) NOT NULL,
            messageText TEXT NOT NULL,
            responseText TEXT NOT NULL,
            messageType VARCHAR(30) NOT NULL DEFAULT 'chat',
            createdDate $created
        )$suffix",

        "CREATE TABLE IF NOT EXISTS tbl_dental_record (
            recordID $id,
            userID INT NOT NULL,
            appointmentID INT,
            toothNumber VARCHAR(50),
            diagnosis TEXT NOT NULL,
            treatmentDone TEXT NOT NULL,
            toothCondition VARCHAR(30) NOT NULL DEFAULT 'good',
            nextAction TEXT,
            dentistNotes TEXT,
            recordedBy INT NOT NULL,
            recordDate $created
        )$suffix",

        "CREATE TABLE IF NOT EXISTS tbl_payment (
            paymentID $id,
            appointmentID INT NOT NULL,
            userID INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            paymentMethod VARCHAR(30) NOT NULL,
            bankName VARCHAR(100),
            referenceNo VARCHAR(100) NOT NULL,
            paymentDate DATE NOT NULL,
            paymentStatus VARCHAR(30) NOT NULL DEFAULT 'pending_verification',
            proofPath VARCHAR(255),
            notes TEXT,
            verifiedBy INT,
            verifiedDate DATETIME,
            createdDate $created
        )$suffix",

        "CREATE TABLE IF NOT EXISTS tbl_invoice (
            invoiceID $id,
            appointmentID INT NOT NULL,
            userID INT NOT NULL,
            invoiceNo VARCHAR(20) NOT NULL,
            baseService VARCHAR(100) NOT NULL,
            baseAmount DECIMAL(10,2) NOT NULL,
            additionalItems TEXT,
            subtotal DECIMAL(10,2) NOT NULL,
            discount DECIMAL(10,2) NOT NULL DEFAULT 0,
            discountReason VARCHAR(255),
            totalAmount DECIMAL(10,2) NOT NULL,
            invoiceStatus VARCHAR(20) NOT NULL DEFAULT 'unpaid',
            notes TEXT,
            generatedBy INT NOT NULL,
            generatedDate $created,
            updatedDate $created
        )$suffix",
    ];

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    ensure_database_columns($pdo, 'tbl_appointment', [
        'patientAge' => 'INT',
        'healthProblemCategory' => "VARCHAR(30) NOT NULL DEFAULT 'none'",
        'healthProblemDetail' => 'TEXT',
        'paymentReceipt' => 'VARCHAR(255)',
        'paymentStatus' => "VARCHAR(20) NOT NULL DEFAULT 'unpaid'",
        'reminderSent'  => 'TINYINT(1) NOT NULL DEFAULT 0',
    ]);

    ensure_database_columns($pdo, 'tbl_user', [
        'userAge'                   => 'INT',
        'userGender'                => 'VARCHAR(10)',
        'userChronicHealthProblems' => 'TEXT',
        'userAllergies'             => 'TEXT',
        'userAvatar'                => 'VARCHAR(255)',
    ]);

    ensure_database_columns($pdo, 'tbl_clinic', [
        'clinicEmail'     => 'VARCHAR(100)',
        'clinicHoursJSON' => 'TEXT',
    ]);

    ensure_database_columns($pdo, 'tbl_health_book', [
        'chartData'          => 'TEXT',
        'treatmentPerformed' => 'TEXT',
    ]);

    ensure_database_columns($pdo, 'tbl_health_book_file', [
        'fileType'           => "VARCHAR(50) NOT NULL DEFAULT 'other'",
    ]);

    if ($driver === 'mysql') {
        $indexes = [
            "CREATE INDEX idx_appointment_slot ON tbl_appointment (clinicID, appointmentDate, appointmentTime, status)",
            "CREATE INDEX idx_appointment_user ON tbl_appointment (userID)",
            "CREATE INDEX idx_feedback_user ON tbl_feedback (userID)",
            "CREATE INDEX idx_reward_user ON tbl_reward (userID)",
            "CREATE INDEX idx_password_reset_user ON tbl_password_reset_otp (userID, usedAt, expiresAt)",
            "CREATE INDEX idx_password_reset_email ON tbl_password_reset_otp (userEmail, usedAt, expiresAt)",
            "CREATE INDEX idx_dental_record_user ON tbl_dental_record (userID)",
        ];
    } else {
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_appointment_slot ON tbl_appointment (clinicID, appointmentDate, appointmentTime, status)",
            "CREATE INDEX IF NOT EXISTS idx_appointment_user ON tbl_appointment (userID)",
            "CREATE INDEX IF NOT EXISTS idx_feedback_user ON tbl_feedback (userID)",
            "CREATE INDEX IF NOT EXISTS idx_reward_user ON tbl_reward (userID)",
            "CREATE INDEX IF NOT EXISTS idx_password_reset_user ON tbl_password_reset_otp (userID, usedAt, expiresAt)",
            "CREATE INDEX IF NOT EXISTS idx_password_reset_email ON tbl_password_reset_otp (userEmail, usedAt, expiresAt)",
            "CREATE INDEX IF NOT EXISTS idx_dental_record_user ON tbl_dental_record (userID)",
        ];
    }

    foreach ($indexes as $index) {
        try {
            $pdo->exec($index);
        } catch (PDOException) {
            // Index already exists in MySQL.
        }
    }

    seed_database($pdo);

    // Migration: ensure existing admin and staff use the new domain
    try {
        $pdo->exec("UPDATE tbl_user SET userEmail = 'admin@dentalputra.com' WHERE userEmail = 'admin@detabot.local'");
        $pdo->exec("UPDATE tbl_user SET userEmail = 'staff@dentalputra.com' WHERE userEmail = 'staff@detabot.local'");
    } catch (PDOException) {
        // Ignore if errors occur
    }

    $initialized = true;
}

function seed_database(PDO $pdo): void
{
    $userCount = (int) $pdo->query('SELECT COUNT(*) FROM tbl_user')->fetchColumn();
    if ($userCount === 0) {
        $stmt = $pdo->prepare('INSERT INTO tbl_user (username, userEmail, userPassword, userPhone, userRole, status) VALUES (?, ?, ?, ?, ?, ?)');
        $users = [
            ['System Admin', 'admin@dentalputra.com', password_hash('admin123', PASSWORD_DEFAULT), '011-1000 1000', 'admin', 'active'],
            ['Clinic Staff', 'staff@dentalputra.com', password_hash('staff123', PASSWORD_DEFAULT), '011-2000 2000', 'staff', 'active'],
            ['Demo Patient', 'patient@detabot.local', password_hash('patient123', PASSWORD_DEFAULT), '011-3000 3000', 'patient', 'active'],
        ];

        foreach ($users as $user) {
            $stmt->execute($user);
        }
    }

    $clinicCount = (int) $pdo->query('SELECT COUNT(*) FROM tbl_clinic')->fetchColumn();
    if ($clinicCount === 0) {
        $stmt = $pdo->prepare('INSERT INTO tbl_clinic (clinicName, location, operatingHours, contactNumber, dentistName, services, promotions) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            'Clinic Putra Dental',
            'Taman Universiti, Parit Raja, Batu Pahat, Johor',
            'Monday to Saturday, 9:00 AM - 5:00 PM',
            '07-453 8899',
            'Dr. Putra Dental Team',
            implode("\n", default_clinic_service_names()),
            'Earn 20 reward points after every completed appointment.',
        ]);
    }

    $catalogCount = (int) $pdo->query('SELECT COUNT(*) FROM tbl_reward_catalog')->fetchColumn();
    if ($catalogCount === 0) {
        $stmt = $pdo->prepare('INSERT INTO tbl_reward_catalog (rewardName, pointsRequired, description, isActive) VALUES (?, ?, ?, ?)');
        $items = [
            ['RM10 treatment discount', 80, 'Redeem for a discount during the next eligible treatment.', 1],
            ['Free dental kit', 120, 'Redeem for a toothbrush, toothpaste, and floss kit.', 1],
            ['Scaling discount voucher', 180, 'Redeem for a selected scaling and polishing discount.', 1],
        ];

        foreach ($items as $item) {
            $stmt->execute($item);
        }
    }
}

function ensure_database_columns(PDO $pdo, string $table, array $columns): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $existing = [];

    if ($driver === 'mysql') {
        foreach ($pdo->query('DESCRIBE ' . $table)->fetchAll() as $column) {
            $existing[] = (string) $column['Field'];
        }
    } else {
        foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll() as $column) {
            $existing[] = (string) $column['name'];
        }
    }

    foreach ($columns as $name => $definition) {
        if (in_array($name, $existing, true)) {
            continue;
        }

        $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $name . ' ' . $definition);
    }
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function user_avatar_dir(): string
{
    return ROOT_PATH . '/public/assets/avatars';
}

function user_avatar_url(?array $user): string
{
    if ($user && !empty($user['userAvatar'])) {
        return 'assets/avatars/' . rawurlencode((string) $user['userAvatar']);
    }
    return '';
}

function db_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return $row ?: null;
}

function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function db_execute(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['_csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Invalid form token. Please refresh and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function consume_flash(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);

    return $messages;
}

function redirect_to(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function page_url(string $page, array $params = []): string
{
    $files = [
        'dashboard'           => 'dashboard.php',
        'appointments'        => 'appointments.php',
        'clinic'              => 'clinic.php',
        'feedback'            => 'feedback.php',
        'rewards'             => 'rewards.php',
        'reports'             => 'reports.php',
        'users'               => 'users.php',
        'healthbook'          => 'healthbook.php',
        'activity'            => 'activity.php',
        'profile'             => 'profile.php',
        'patients'             => 'patients.php',
        'manage_patients'      => 'manage_patients.php',
        'staff_health_record'  => 'staff_health_record.php',
        'manage_appointments'  => 'manage_appointments.php',
        'manage_feedback'      => 'manage_feedback.php',
        'manage_rewards'          => 'manage_rewards.php',
        'edit_clinic_information' => 'edit_clinic_information.php',
        'staff_profile'           => 'staff_profile.php',
        'manage_staff'            => 'manage_staff.php',
    ];

    $file = $files[$page] ?? 'dashboard.php';
    $query = http_build_query($params);

    return $query ? $file . '?' . $query : $file;
}

function current_user(): ?array
{
    if (empty($_SESSION['userID'])) {
        return null;
    }

    return db_one('SELECT * FROM tbl_user WHERE userID = ? AND status = ?', [(int) $_SESSION['userID'], 'active']);
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect_to('index.php');
    }

    return $user;
}

function has_role(array $user, array|string $roles): bool
{
    $roles = is_array($roles) ? $roles : [$roles];

    return in_array($user['userRole'], $roles, true);
}

function require_role(array|string $roles): array
{
    $user = require_login();
    if (!has_role($user, $roles)) {
        flash('danger', 'You do not have permission to access that module.');
        redirect_to(page_url('dashboard'));
    }

    return $user;
}

function log_activity(string $action, string $details = '', ?int $userID = null): void
{
    $actor = $userID ?? ($_SESSION['userID'] ?? null);
    db_execute(
        'INSERT INTO tbl_activity_log (userID, action, details) VALUES (?, ?, ?)',
        [$actor, $action, $details]
    );
}

function generate_password_reset_otp(): string
{
    return (string) random_int(100000, 999999);
}

function format_password_reset_otp(string $otp): string
{
    $digits = preg_replace('/\D/', '', $otp);

    return strlen((string) $digits) === 6
        ? substr((string) $digits, 0, 3) . '-' . substr((string) $digits, 3, 3)
        : $otp;
}

function create_password_reset_otp(array $user, string $otp): void
{
    $minutes = (int) config('password_reset.otp_minutes', 10);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $minutes . ' minutes'));

    db_execute(
        'UPDATE tbl_password_reset_otp SET usedAt = CURRENT_TIMESTAMP WHERE userID = ? AND usedAt IS NULL',
        [(int) $user['userID']]
    );
    db_execute(
        'INSERT INTO tbl_password_reset_otp (userID, userEmail, otpHash, expiresAt) VALUES (?, ?, ?, ?)',
        [(int) $user['userID'], strtolower((string) $user['userEmail']), password_hash($otp, PASSWORD_DEFAULT), $expiresAt]
    );
}

function send_password_reset_otp(array $user, string $otp): bool
{
    $minutes = (int) config('password_reset.otp_minutes', 10);
    $subject = 'Detabot password reset OTP';
    $body = "Hi " . $user['username'] . " your 6 digit OTP code is : " . format_password_reset_otp($otp) . "\n\n"
        . "This code expires in " . $minutes . " minutes. If you did not request this, you can ignore this email.\n\n"
        . "Clinic Putra Dental";

    return send_mail_message((string) $user['userEmail'], $subject, $body);
}

function send_appointment_receipt(array $patient, int $appointmentID, string $date, string $time, string $service, string $dentistName, string $paymentLabel, int $duration): bool
{
    $clinicName = 'Klinik Pergigian Putra';
    $clinicContact = '07-453 8899';
    $clinicLocation = 'Taman Universiti, Parit Raja, Johor';
    $subject = 'Appointment Booking Confirmation – ' . $clinicName;

    $body = "Dear " . $patient['username'] . ",\n\n"
        . "Your appointment has been submitted successfully. Here is your booking receipt:\n\n"
        . "============================================\n"
        . "   APPOINTMENT BOOKING RECEIPT\n"
        . "   " . $clinicName . "\n"
        . "============================================\n\n"
        . "Booking Reference : #" . $appointmentID . "\n"
        . "Treatment         : " . $service . "\n"
        . "Dentist           : " . $dentistName . "\n"
        . "Date              : " . date('l, d F Y', (int) strtotime($date)) . "\n"
        . "Time              : " . $time . "\n"
        . "Duration          : " . format_duration($duration) . "\n"
        . "Payment Method    : " . $paymentLabel . "\n"
        . "Status            : Pending Confirmation\n\n"
        . "============================================\n\n"
        . "Your slot is pending clinic review. You will be notified once it is confirmed.\n\n"
        . "Location : " . $clinicLocation . "\n"
        . "Contact  : " . $clinicContact . "\n\n"
        . "Thank you for choosing " . $clinicName . ".\n\n"
        . "This is an automated message. Please do not reply to this email.";

    return send_mail_message((string) $patient['userEmail'], $subject, $body);
}

function reset_password_with_otp(string $email, string $otp, string $password): bool
{
    $email = strtolower(trim($email));
    $row = db_one(
        'SELECT * FROM tbl_password_reset_otp WHERE userEmail = ? AND usedAt IS NULL ORDER BY resetID DESC LIMIT 1',
        [$email]
    );

    if (!$row || (string) $row['expiresAt'] < date('Y-m-d H:i:s')) {
        return false;
    }

    $maxAttempts = (int) config('password_reset.max_attempts', 5);
    if ((int) $row['attempts'] >= $maxAttempts) {
        return false;
    }

    if (!password_verify($otp, (string) $row['otpHash'])) {
        db_execute('UPDATE tbl_password_reset_otp SET attempts = attempts + 1 WHERE resetID = ?', [(int) $row['resetID']]);
        return false;
    }

    db_execute(
        'UPDATE tbl_user SET userPassword = ? WHERE userID = ?',
        [password_hash($password, PASSWORD_DEFAULT), (int) $row['userID']]
    );
    db_execute('UPDATE tbl_password_reset_otp SET usedAt = CURRENT_TIMESTAMP WHERE resetID = ?', [(int) $row['resetID']]);
    log_activity('reset_password', 'Password reset with OTP', (int) $row['userID']);

    return true;
}

function send_mail_message(string $to, string $subject, string $body): bool
{
    $driver = strtolower((string) config('mail.driver', 'log'));

    if ($driver === 'smtp') {
        return smtp_send_mail($to, $subject, $body);
    }

    return log_mail_message($to, $subject, $body);
}

function log_mail_message(string $to, string $subject, string $body): bool
{
    $path = (string) config('mail.log_path', ROOT_PATH . '/data/mail.log');
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $entry = "==== " . date('Y-m-d H:i:s') . " ====\n"
        . "To: " . $to . "\n"
        . "Subject: " . $subject . "\n\n"
        . $body . "\n\n";

    return file_put_contents($path, $entry, FILE_APPEND | LOCK_EX) !== false;
}

function smtp_send_mail(string $to, string $subject, string $body): bool
{
    $smtp = config('mail.smtp');
    $host = (string) ($smtp['host'] ?? 'smtp.gmail.com');
    $port = (int) ($smtp['port'] ?? 465);
    $encryption = strtolower((string) ($smtp['encryption'] ?? 'ssl'));
    $username = (string) ($smtp['username'] ?? '');
    $password = (string) ($smtp['password'] ?? '');
    $timeout = (int) ($smtp['timeout'] ?? 15);
    $fromEmail = (string) config('mail.from_email', $username ?: 'no-reply@detabot.local');
    $fromName = (string) config('mail.from_name', 'Detabot');

    if ($username === '' || $password === '' || $password === 'PASTE_GOOGLE_APP_PASSWORD_HERE') {
        return false;
    }

    $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        return false;
    }

    stream_set_timeout($socket, $timeout);

    try {
        smtp_expect($socket, [220]);
        smtp_command($socket, 'EHLO ' . smtp_domain($fromEmail), [250]);

        if ($encryption === 'tls') {
            smtp_command($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Unable to start TLS.');
            }
            smtp_command($socket, 'EHLO ' . smtp_domain($fromEmail), [250]);
        }

        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode($username), [334]);
        smtp_command($socket, base64_encode($password), [235]);
        smtp_command($socket, 'MAIL FROM:<' . mail_sanitize_header($fromEmail) . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . mail_sanitize_header($to) . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);

        $headers = [
            'From: ' . mail_address_header($fromEmail, $fromName),
            'To: ' . mail_address_header($to, ''),
            'Subject: ' . mail_subject_header($subject),
            'Date: ' . date(DATE_RFC2822),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];
        fwrite($socket, smtp_escape_message(implode("\r\n", $headers) . "\r\n\r\n" . $body) . "\r\n.\r\n");
        smtp_expect($socket, [250]);
        smtp_command($socket, 'QUIT', [221]);
        fclose($socket);

        return true;
    } catch (Throwable) {
        fclose($socket);
        return false;
    }
}

function smtp_command(mixed $socket, string $command, array $expected): void
{
    fwrite($socket, $command . "\r\n");
    smtp_expect($socket, $expected);
}

function smtp_expect(mixed $socket, array $expected): void
{
    $code = 0;

    while (($line = fgets($socket, 515)) !== false) {
        $code = (int) substr($line, 0, 3);
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    if (!in_array($code, $expected, true)) {
        throw new RuntimeException('Unexpected SMTP response.');
    }
}

function smtp_escape_message(string $message): string
{
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $message));
    $lines = array_map(static fn (string $line): string => str_starts_with($line, '.') ? '.' . $line : $line, $lines);

    return implode("\r\n", $lines);
}

function smtp_domain(string $email): string
{
    $parts = explode('@', $email);

    return $parts[1] ?? 'localhost';
}

function mail_sanitize_header(string $value): string
{
    return trim(str_replace(["\r", "\n"], '', $value));
}

function mail_subject_header(string $subject): string
{
    return '=?UTF-8?B?' . base64_encode(mail_sanitize_header($subject)) . '?=';
}

function mail_address_header(string $email, string $name): string
{
    $email = mail_sanitize_header($email);
    $name = trim(mail_sanitize_header($name));

    if ($name === '') {
        return '<' . $email . '>';
    }

    return '"' . str_replace('"', '\"', $name) . '" <' . $email . '>';
}

function clinic(): array
{
    return db_one('SELECT * FROM tbl_clinic ORDER BY clinicID ASC LIMIT 1') ?? [];
}

function clinic_services(): array
{
    $services = preg_split('/\r\n|\r|\n|,/', (string) (clinic()['services'] ?? ''));
    $services = array_values(array_filter(array_map('trim', $services)));

    return $services ?: default_clinic_service_names();
}

function default_clinic_services(): array
{
    return [
        ['category' => 'Examination & Diagnosis', 'name' => 'Dental consultation', 'duration' => 30, 'price_min' => 30, 'price_max' => 100],
        ['category' => 'Examination & Diagnosis', 'name' => 'Dental X-ray', 'duration' => 20, 'price_min' => 20, 'price_max' => 80],
        ['category' => "Children's Dentistry & Prevention", 'name' => 'Children dental prevention', 'display_name' => "Children's dental prevention", 'duration' => 45, 'price_min' => 50, 'price_max' => 150],
        ['category' => 'Basic Dental Treatment', 'name' => 'Tooth extraction', 'duration' => 45, 'price_min' => 80, 'price_max' => 600],
        ['category' => 'Basic Dental Treatment', 'name' => 'Tooth filling', 'duration' => 60, 'price_min' => 100, 'price_max' => 400],
        ['category' => 'Basic Dental Treatment', 'name' => 'Scaling / teeth cleaning', 'duration' => 45, 'price_min' => 100, 'price_max' => 300],
        ['category' => 'Restorative Dental Treatment', 'name' => 'Dentures', 'duration' => 90, 'price_min' => 500, 'price_max' => 3000],
        ['category' => 'Restorative Dental Treatment', 'name' => 'Crown', 'duration' => 90, 'price_min' => 1000, 'price_max' => 3500],
        ['category' => 'Restorative Dental Treatment', 'name' => 'Bridge', 'duration' => 90, 'price_min' => 1000, 'price_max' => 3500],
        ['category' => 'Restorative Dental Treatment', 'name' => 'FRC bridge', 'display_name' => 'Fibre Reinforced Composite (FRC) Bridge', 'duration' => 90, 'price_min' => 800, 'price_max' => 2500],
        ['category' => 'Restorative Dental Treatment', 'name' => 'Root canal treatment', 'duration' => 90, 'price_min' => 500, 'price_max' => 2000],
        ['category' => 'Cosmetic Dental Treatment', 'name' => 'Teeth whitening', 'duration' => 60, 'price_min' => 500, 'price_max' => 2500],
        ['category' => 'Cosmetic Dental Treatment', 'name' => 'Icon treatment for fluorosis', 'duration' => 60, 'price_min' => 300, 'price_max' => 800],
        ['category' => 'Cosmetic Dental Treatment', 'name' => 'Veneer', 'duration' => 90, 'price_min' => 250, 'price_max' => 3000],
        ['category' => 'Cosmetic Dental Treatment', 'name' => 'Braces', 'duration' => 90, 'price_min' => 5000, 'price_max' => 7500],
        ['category' => 'Cosmetic Dental Treatment', 'name' => 'Retainer', 'duration' => 45, 'price_min' => 300, 'price_max' => 1000],
        ['category' => 'Minor Oral Surgery', 'name' => 'Minor oral surgery', 'duration' => 90, 'price_min' => 600, 'price_max' => 2000],
    ];
}

function default_clinic_service_names(): array
{
    return array_column(default_clinic_services(), 'name');
}

function normalize_service_name(string $service): string
{
    return strtolower(trim(preg_replace('/\s+/', ' ', $service) ?? $service));
}

function service_price_min(string $service): float
{
    $norm = normalize_service_name($service);
    foreach (default_clinic_services() as $svc) {
        if (normalize_service_name((string) $svc['name']) === $norm) {
            return (float) ($svc['price_min'] ?? 0);
        }
    }
    return 0.0;
}

function service_category(string $service): string
{
    $norm = normalize_service_name($service);
    foreach (default_clinic_services() as $svc) {
        if (normalize_service_name((string) $svc['name']) === $norm) {
            return (string) ($svc['category'] ?? '');
        }
    }
    return '';
}

function extract_dentist_name(string $notes): string
{
    if (preg_match('/^Dentist:\s*(.+)$/m', $notes, $m)) {
        return trim($m[1]);
    }
    return 'Dental Team';
}

function service_durations(): array
{
    $durations = [];
    foreach (default_clinic_services() as $service) {
        $durations[normalize_service_name($service['name'])] = (int) $service['duration'];
    }

    return $durations + [
        'dental check-up' => 30,
        'check-up' => 30,
        'consultation' => 30,
        'dental check-up' => 30,
        'consultation and x-ray' => 45,
        'scaling and polishing' => 45,
        'cuci gigi' => 45,
        'tooth filling' => 60,
        'tampalan gigi' => 60,
        'tooth extraction' => 45,
        'cabutan gigi' => 45,
        'braces consultation' => 60,
        'pendakap gigi' => 90,
        'whitening consultation' => 45,
        'pemutihan gigi' => 60,
        'gigi palsu' => 90,
        'rawatan kanal akar' => 90,
    ];
}

function service_duration_minutes(string $service): int
{
    $key = normalize_service_name($service);

    return service_durations()[$key] ?? 60;
}

function common_health_problem_options(): array
{
    return [
        'Fever or flu symptoms',
        'Cough or sore throat',
        'Headache or migraine',
        'Mild allergy',
        'Gastric or stomach discomfort',
        'Recent toothache',
        'Gum bleeding',
        'Mouth ulcer',
    ];
}

function chronic_health_problem_options(): array
{
    return [
        'Diabetes',
        'High blood pressure',
        'Heart disease',
        'Asthma or COPD',
        'Kidney disease',
        'Liver disease',
        'Epilepsy',
        'Blood clotting disorder',
        'Immune system condition',
        'Long-term medication',
    ];
}

function health_problem_category_label(?string $category): string
{
    return [
        'none' => 'No',
        'common' => 'Common Health Problem',
        'chronic' => 'Chronic Health Problem',
    ][$category ?: 'none'] ?? 'No';
}

function normalize_chronic_health_problems(mixed $problems): array
{
    $problems = is_array($problems) ? $problems : [];
    $allowed = chronic_health_problem_options();

    return array_values(array_intersect(array_map('strval', $problems), $allowed));
}

function user_chronic_health_problems(array $user): array
{
    $stored = trim((string) ($user['userChronicHealthProblems'] ?? ''));

    if ($stored === '') {
        return [];
    }

    return normalize_chronic_health_problems(array_map('trim', explode(',', $stored)));
}

function format_user_chronic_health_problems(array $user): string
{
    $problems = user_chronic_health_problems($user);

    return $problems ? implode(', ', $problems) : 'No chronic health problem';
}

function format_duration(int $minutes): string
{
    if ($minutes < 60) {
        return $minutes . ' minutes';
    }

    $hours = intdiv($minutes, 60);
    $remaining = $minutes % 60;

    if ($remaining === 0) {
        return $hours === 1 ? '1 hour' : $hours . ' hours';
    }

    return $hours . ' hour ' . $remaining . ' minutes';
}

function clinic_services_with_duration(): array
{
    $catalog = [];
    foreach (default_clinic_services() as $service) {
        $catalog[normalize_service_name($service['name'])] = $service;
    }

    return array_map(
        static function (string $service) use ($catalog): array {
            $duration = service_duration_minutes($service);
            $matched = $catalog[normalize_service_name($service)] ?? [];
            $priceMin = (float) ($matched['price_min'] ?? 0);
            $priceMax = (float) ($matched['price_max'] ?? 0);

            return [
                'name' => $service,
                'category' => $matched['category'] ?? 'Other Services',
                'duration' => $duration,
                'priceMin' => $priceMin,
                'priceMax' => $priceMax,
                'displayName' => $matched['display_name'] ?? $service,
                'label' => ($matched['display_name'] ?? $service) . ' - ' . format_duration($duration),
                'priceLabel' => format_price($priceMin, $priceMax),
            ];
        },
        clinic_services()
    );
}

function format_price(float $min, float $max = 0): string
{
    if ($min <= 0) {
        return 'Price on consultation';
    }

    return 'RM ' . number_format($min, 0);
}

function group_services_by_category(array $services): array
{
    $groups = [];

    foreach ($services as $service) {
        $category = (string) ($service['category'] ?? 'Other Services');
        $groups[$category][] = $service;
    }

    return $groups;
}

function build_time_slots(): array
{
    $start = new DateTime((string) config('slots.start', '09:00'));
    $end = new DateTime((string) config('slots.end', '17:00'));
    $interval = (int) config('slots.interval_minutes', 60);
    $slots = [];

    while ($start < $end) {
        $slots[] = $start->format('H:i');
        $start->modify('+' . $interval . ' minutes');
    }

    return $slots;
}

function booked_slots(string $date, int $clinicID = 1): array
{
    $rows = db_all(
        "SELECT appointmentTime FROM tbl_appointment
         WHERE clinicID = ? AND appointmentDate = ? AND status IN ('pending', 'confirmed', 'completed')",
        [$clinicID, $date]
    );

    return array_map(static fn (array $row): string => substr((string) $row['appointmentTime'], 0, 5), $rows);
}

function available_slots(string $date, int $clinicID = 1): array
{
    $booked = booked_slots($date, $clinicID);

    return array_values(array_diff(build_time_slots(), $booked));
}

function normalize_appointment_date(?string $date): string
{
    $today = date('Y-m-d');
    $fallback = date('Y-m-d', strtotime('+1 day'));
    $date = (string) $date;

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < $today) {
        return $fallback;
    }

    return $date;
}

function appointment_slot_statuses(string $date, int $clinicID = 1): array
{
    $rows = db_all(
        "SELECT appointmentTime, status FROM tbl_appointment
         WHERE clinicID = ? AND appointmentDate = ? AND status IN ('pending', 'confirmed', 'completed')",
        [$clinicID, $date]
    );

    $statuses = [];
    foreach ($rows as $row) {
        $statuses[substr((string) $row['appointmentTime'], 0, 5)] = (string) $row['status'];
    }

    return $statuses;
}

function appointment_meets_minimum_notice(string $date, string $time, int $hours = 6): bool
{
    try {
        $slotAt = new DateTimeImmutable($date . ' ' . substr($time, 0, 5));
    } catch (Exception) {
        return false;
    }

    $minimumTime = (new DateTimeImmutable())->modify('+' . $hours . ' hours');

    return $slotAt >= $minimumTime;
}

function appointment_slot_list(string $date, int $clinicID = 1): array
{
    $statuses = appointment_slot_statuses($date, $clinicID);
    $labels = [
        'available' => 'Available',
        'too-soon' => 'Unavailable',
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'completed' => 'Taken',
    ];

    return array_map(
        static function (string $slot) use ($statuses, $labels, $date): array {
            $status = $statuses[$slot] ?? 'available';
            if ($status === 'available' && !appointment_meets_minimum_notice($date, $slot)) {
                $status = 'too-soon';
            }

            return [
                'time' => $slot,
                'status' => $status,
                'label' => $labels[$status] ?? 'Taken',
                'available' => $status === 'available',
            ];
        },
        build_time_slots()
    );
}

function appointment_conflict(int $clinicID, string $date, string $time, ?int $ignoreAppointmentID = null): bool
{
    $params = [$clinicID, $date, $time . ':00'];
    $sql = "SELECT appointmentID FROM tbl_appointment
            WHERE clinicID = ? AND appointmentDate = ? AND appointmentTime = ?
            AND status IN ('pending', 'confirmed', 'completed')";

    if ($ignoreAppointmentID) {
        $sql .= ' AND appointmentID != ?';
        $params[] = $ignoreAppointmentID;
    }

    return db_one($sql, $params) !== null;
}

function reward_balance(int $userID): int
{
    $row = db_one('SELECT currentBalance FROM tbl_reward WHERE userID = ? ORDER BY rewardID DESC LIMIT 1', [$userID]);

    return (int) ($row['currentBalance'] ?? 0);
}

function add_reward_transaction(int $userID, int $earned, int $redeemed, string $type, string $description): void
{
    $balance = reward_balance($userID) + $earned - $redeemed;
    db_execute(
        'INSERT INTO tbl_reward (userID, pointsEarned, pointsRedeemed, currentBalance, transactionType, rewardDescription) VALUES (?, ?, ?, ?, ?, ?)',
        [$userID, $earned, $redeemed, max(0, $balance), $type, $description]
    );
}

function award_completion_points(array $appointment): void
{
    $exists = db_one(
        'SELECT rewardID FROM tbl_reward WHERE userID = ? AND transactionType = ? AND rewardDescription LIKE ? LIMIT 1',
        [(int) $appointment['userID'], 'earned', '%Appointment #' . $appointment['appointmentID'] . '%']
    );

    if ($exists) {
        return;
    }

    $points = (int) config('reward_points_per_completed_appointment', 20);
    add_reward_transaction(
        (int) $appointment['userID'],
        $points,
        0,
        'earned',
        'Completed appointment #' . $appointment['appointmentID']
    );
}

function report_payload(string $type): array
{
    $type = $type ?: 'appointment';

    if ($type === 'patient') {
        return [
            'title' => 'Patient Report',
            'summary' => [
                'totalPatients' => (int) db_one("SELECT COUNT(*) AS total FROM tbl_user WHERE userRole = 'patient'")['total'],
                'activePatients' => (int) db_one("SELECT COUNT(*) AS total FROM tbl_user WHERE userRole = 'patient' AND status = 'active'")['total'],
                'patientsWithAppointments' => (int) db_one('SELECT COUNT(DISTINCT userID) AS total FROM tbl_appointment')['total'],
            ],
            'rows' => db_all(
                "SELECT u.username, u.userEmail, COUNT(a.appointmentID) AS appointments
                 FROM tbl_user u
                 LEFT JOIN tbl_appointment a ON a.userID = u.userID
                 WHERE u.userRole = 'patient'
                 GROUP BY u.userID, u.username, u.userEmail
                 ORDER BY appointments DESC, u.username ASC"
            ),
        ];
    }

    if ($type === 'feedback') {
        return [
            'title' => 'Feedback Report',
            'summary' => [
                'totalFeedback' => (int) db_one('SELECT COUNT(*) AS total FROM tbl_feedback')['total'],
                'averageRating' => round((float) (db_one('SELECT AVG(rating) AS avgRating FROM tbl_feedback')['avgRating'] ?? 0), 2),
                'responded' => (int) db_one('SELECT COUNT(*) AS total FROM tbl_feedback WHERE adminResponse IS NOT NULL AND adminResponse != ""')['total'],
            ],
            'rows' => db_all(
                "SELECT f.rating, f.comments, f.feedbackDate, u.username
                 FROM tbl_feedback f
                 JOIN tbl_user u ON u.userID = f.userID
                 ORDER BY f.feedbackDate DESC
                 LIMIT 20"
            ),
        ];
    }

    if ($type === 'reward') {
        return [
            'title' => 'Reward Report',
            'summary' => [
                'pointsIssued' => (int) db_one('SELECT COALESCE(SUM(pointsEarned), 0) AS total FROM tbl_reward')['total'],
                'pointsRedeemed' => (int) db_one('SELECT COALESCE(SUM(pointsRedeemed), 0) AS total FROM tbl_reward')['total'],
                'redemptions' => (int) db_one("SELECT COUNT(*) AS total FROM tbl_reward WHERE transactionType = 'redeemed'")['total'],
            ],
            'rows' => db_all(
                "SELECT r.transactionDate, u.username, r.transactionType, r.pointsEarned, r.pointsRedeemed, r.currentBalance, r.rewardDescription
                 FROM tbl_reward r
                 JOIN tbl_user u ON u.userID = r.userID
                 ORDER BY r.transactionDate DESC
                 LIMIT 30"
            ),
        ];
    }

    return [
        'title' => 'Appointment Report',
        'summary' => [
            'totalAppointments' => (int) db_one('SELECT COUNT(*) AS total FROM tbl_appointment')['total'],
            'pending' => (int) db_one("SELECT COUNT(*) AS total FROM tbl_appointment WHERE status = 'pending'")['total'],
            'confirmed' => (int) db_one("SELECT COUNT(*) AS total FROM tbl_appointment WHERE status = 'confirmed'")['total'],
            'completed' => (int) db_one("SELECT COUNT(*) AS total FROM tbl_appointment WHERE status = 'completed'")['total'],
            'cancelled' => (int) db_one("SELECT COUNT(*) AS total FROM tbl_appointment WHERE status = 'cancelled'")['total'],
        ],
        'rows' => db_all(
            "SELECT serviceType, status, COUNT(*) AS total
             FROM tbl_appointment
             GROUP BY serviceType, status
             ORDER BY total DESC"
        ),
    ];
}

function health_book_upload_dir(): string
{
    $dir = ROOT_PATH . '/public/uploads/healthbook';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function payment_receipt_upload_dir(): string
{
    $dir = ROOT_PATH . '/public/uploads/receipts';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function payment_proof_upload_dir(): string
{
    $dir = ROOT_PATH . '/public/uploads/payments';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function health_book_entries(int $userID): array
{
    return db_all(
        "SELECT h.*, u.username AS staffName
         FROM tbl_health_book h
         JOIN tbl_user u ON u.userID = h.createdBy
         WHERE h.userID = ?
         ORDER BY h.createdDate DESC",
        [$userID]
    );
}

function health_book_entry(int $entryID): ?array
{
    return db_one('SELECT * FROM tbl_health_book WHERE entryID = ?', [$entryID]);
}

function health_book_files(int $entryID): array
{
    return db_all('SELECT * FROM tbl_health_book_file WHERE entryID = ? ORDER BY uploadedDate ASC', [$entryID]);
}

function health_book_all_patients(): array
{
    return db_all("SELECT userID, username, userEmail FROM tbl_user WHERE userRole = 'patient' AND status = 'active' ORDER BY username ASC");
}
