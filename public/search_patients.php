<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json');

$user = current_user();
if (!$user || !has_role($user, ['admin', 'staff'])) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));

if ($q === '') {
    $patients = db_all(
        "SELECT userID, username, userEmail, userPhone, userAge, userGender
         FROM tbl_user WHERE userRole = 'patient' AND status = 'active'
         ORDER BY username ASC LIMIT 30",
        []
    );
} else {
    $like = '%' . $q . '%';
    $patients = db_all(
        "SELECT userID, username, userEmail, userPhone, userAge, userGender
         FROM tbl_user WHERE userRole = 'patient' AND status = 'active'
           AND (username LIKE ? OR userEmail LIKE ? OR userPhone LIKE ?)
         ORDER BY username ASC LIMIT 30",
        [$like, $like, $like]
    );
}

echo json_encode($patients);
