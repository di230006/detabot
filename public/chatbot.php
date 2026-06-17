<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ── cURL availability check ──────────────────────────────────────────────────
if (!function_exists('curl_init')) {
    echo json_encode(['error' => 'cURL is not enabled. Please uncomment extension=curl in php.ini and restart Apache.']);
    exit;
}

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../chatbot-config.php';

db(); // initialise DB + create tables (including tbl_chatbot)

// ── Input ────────────────────────────────────────────────────────────────────
$raw       = json_decode((string) file_get_contents('php://input'), true) ?? [];
$message     = trim((string) ($raw['message']     ?? $_POST['message']     ?? ''));
$userID      = (int)         ($raw['userID']      ?? $_POST['userID']      ?? 0);
$sessionID   = trim((string) ($raw['sessionID']   ?? $_POST['sessionID']   ?? ''));
$pageContext = trim((string) ($raw['pageContext']  ?? $_POST['pageContext'] ?? 'app'));

if ($message === '' || $sessionID === '') {
    http_response_code(400);
    echo json_encode(['error' => 'message and sessionID are required']);
    exit;
}

// ── Clinic info from DB ──────────────────────────────────────────────────────
$clinic = db_one('SELECT * FROM tbl_clinic ORDER BY clinicID ASC LIMIT 1') ?? [];

$clinicContext = '';
if ($clinic) {
    $clinicContext = "\n\nLIVE CLINIC DATA (use this if it differs from your defaults):\n"
        . '- Clinic name: '    . ($clinic['clinicName']      ?? '') . "\n"
        . '- Location: '       . ($clinic['location']        ?? '') . "\n"
        . '- Hours: '          . ($clinic['operatingHours']  ?? '') . "\n"
        . '- Contact: '        . ($clinic['contactNumber']   ?? '') . "\n"
        . '- Services: '       . ($clinic['services']        ?? '') . "\n"
        . '- Promotions: '     . ($clinic['promotions']      ?? '');
}

// ── Chat history (last 10 exchanges for context) ─────────────────────────────
$history = db_all(
    'SELECT messageText, responseText
       FROM tbl_chatbot
      WHERE sessionID = ?
      ORDER BY chatID DESC
      LIMIT 10',
    [$sessionID]
);
$history = array_reverse($history);

// ── Choose system prompt and model params based on page context ──────────────
$isBookingContext = ($pageContext === 'appointments');

if ($isBookingContext) {
    $basePrompt  = CHATBOT_BOOKING_SYSTEM_PROMPT;
    $maxTokens   = 600;
    $temperature = 0.25; // deterministic for structured JSON
    $clinicContext = ''; // booking prompt is self-contained
} elseif ($pageContext === 'login') {
    $basePrompt  = CHATBOT_LOGIN_SYSTEM_PROMPT;
    $maxTokens   = 512;
    $temperature = 0.7;
} else {
    $basePrompt  = CHATBOT_SYSTEM_PROMPT;
    $maxTokens   = 512;
    $temperature = 0.7;
}

// ── Build Groq message array ─────────────────────────────────────────────────
$messages = [
    ['role' => 'system', 'content' => $basePrompt . $clinicContext],
];

foreach ($history as $row) {
    if (trim((string) $row['messageText']) !== '') {
        $messages[] = ['role' => 'user',      'content' => $row['messageText']];
    }
    if (trim((string) $row['responseText']) !== '') {
        $messages[] = ['role' => 'assistant', 'content' => $row['responseText']];
    }
}

$messages[] = ['role' => 'user', 'content' => $message];

// ── API key sanity check ─────────────────────────────────────────────────────
if (!defined('GROQ_API_KEY') || GROQ_API_KEY === '' || strpos(GROQ_API_KEY, 'gsk_') !== 0) {
    echo json_encode(['error' => 'DEBUG: API key missing or invalid format']);
    exit;
}

// ── Call Groq API ────────────────────────────────────────────────────────────
$groqRequest = [
    'model'       => GROQ_MODEL,
    'messages'    => $messages,
    'max_tokens'  => $maxTokens,
    'temperature' => $temperature,
];
// Force JSON output so the booking prompt is always parseable
if ($isBookingContext) {
    $groqRequest['response_format'] = ['type' => 'json_object'];
}
$payload = (string) json_encode($groqRequest);

$ch = curl_init(GROQ_ENDPOINT);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY,
    ],
    // SSL bypass for XAMPP localhost (missing CA bundle)
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
]);

$response  = curl_exec($ch);
$curlErrno = curl_errno($ch);
$curlError = curl_error($ch);
$httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlErrno !== 0) {
    error_log('[Detabot] cURL error #' . $curlErrno . ': ' . $curlError);
    echo json_encode(['error' => 'Connection error (' . $curlError . '). Check that Apache can reach the internet.']);
    exit;
}

if ($httpCode !== 200) {
    $groqErr = json_decode((string) $response, true);
    $detail  = (string) ($groqErr['error']['message'] ?? $response);
    error_log('[Detabot] Groq HTTP ' . $httpCode . ': ' . $detail);
    echo json_encode(['error' => 'Groq API error (HTTP ' . $httpCode . '): ' . $detail]);
    exit;
}

$data  = json_decode((string) $response, true);
$reply = trim((string) ($data['choices'][0]['message']['content'] ?? ''));

if ($reply === '') {
    error_log('[Detabot] Groq returned empty content. Raw: ' . $response);
    echo json_encode(['error' => 'No response from AI. Please try again.']);
    exit;
}

// ── Booking context: parse structured JSON response ───────────────────────────
if ($isBookingContext) {
    // Strip markdown code fences if model wrapped in ```json … ```
    $clean = (string) preg_replace('/^```(?:json)?\s*/i', '', trim($reply));
    $clean = (string) preg_replace('/\s*```$/', '', $clean);

    $parsed = json_decode($clean, true);
    if (is_array($parsed) && isset($parsed['action'])) {
        $replyText   = trim((string) ($parsed['reply'] ?? $reply));
        $messageType = ($parsed['action'] === 'book_appointment') ? 'booking' : 'chat';

        // Store the human-readable reply text (not raw JSON) so future context is clean
        db_execute(
            'INSERT INTO tbl_chatbot (userID, sessionID, messageText, responseText, messageType)
             VALUES (?, ?, ?, ?, ?)',
            [$userID ?: null, $sessionID, $message, $replyText, $messageType]
        );

        // Return the full action object so the client can handle booking confirmation
        echo json_encode($parsed);
        exit;
    }
    // Malformed JSON fallback: $reply stays unchanged, falls through to plain persist below
}

// ── Persist exchange ─────────────────────────────────────────────────────────
db_execute(
    'INSERT INTO tbl_chatbot (userID, sessionID, messageText, responseText, messageType)
     VALUES (?, ?, ?, ?, ?)',
    [$userID ?: null, $sessionID, $message, $reply, 'chat']
);

echo json_encode(['reply' => $reply]);
