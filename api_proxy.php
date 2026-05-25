<?php
// ================================================================
//  api_proxy.php — Groq LLaMA AI (FREE, No Quota Issues!)
//  Same API key as voice_stt.php — get from https://console.groq.com
//  Free tier: 14,400 requests/day ✅
// ================================================================
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Allow-Headers: Content-Type');
    exit(0);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

// ── ✅ SAME GROQ KEY AS voice_stt.php ────────────────────────────
define('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: 'REPLACE_WITH_YOUR_GROQ_API_KEY');
define('GROQ_MODEL',   'llama-3.3-70b-versatile');  // Best free model
// ================================================================

// ── Rate limiter: 20 requests per 60 seconds ─────────────────────
$now = time();
if (!isset($_SESSION['cb_calls'])) $_SESSION['cb_calls'] = [];
$_SESSION['cb_calls'] = array_values(array_filter(
    $_SESSION['cb_calls'], fn($t) => ($now - $t) < 60
));
if (count($_SESSION['cb_calls']) >= 20) {
    echo json_encode(['choices' => [['message' => ['content' =>
        "CheeseBot is catching a quick breath! 🧀 Please wait 10 seconds and try again. Meanwhile, check out our Hot Deals! 🔥"
    ]]]]);
    exit;
}
$_SESSION['cb_calls'][] = $now;

// ── Read request body ─────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);
if (!$body || empty($body['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// ── Build payload (Groq uses OpenAI format — easy!) ───────────────
$payload = [
    'model'       => GROQ_MODEL,
    'messages'    => $body['messages'],
    'max_tokens'  => 600,
    'temperature' => 0.7,
    'stream'      => false,
];

// ── Call Groq API ─────────────────────────────────────────────────
$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY,
    ],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

// ── Handle errors ─────────────────────────────────────────────────
if ($curlErr) {
    echo json_encode(['choices' => [['message' => ['content' =>
        'Connection issue! Please check your internet. 📶'
    ]]]]); exit;
}

if ($httpCode === 429) {
    echo json_encode(['choices' => [['message' => ['content' =>
        "CheeseBot is very busy! 🧀 Please wait 30 seconds and try again."
    ]]]]); exit;
}

if ($httpCode !== 200) {
    $errData = json_decode($response, true);
    $msg = $errData['error']['message'] ?? ('API error — HTTP ' . $httpCode);
    $log = date('Y-m-d H:i:s') . " | Groq LLM Error $httpCode: $msg\n";
    @file_put_contents(__DIR__ . '/api_errors.log', $log, FILE_APPEND);
    echo json_encode(['choices' => [['message' => ['content' =>
        "Sorry, I had a small hiccup! 🧀 Please try again."
    ]]]]); exit;
}

// ── Return response (already in OpenAI format!) ───────────────────
$data = json_decode($response, true);
$text = $data['choices'][0]['message']['content'] ?? "Sorry, I couldn't process that. 🧀";
echo json_encode(['choices' => [['message' => ['content' => $text]]]]);
?>