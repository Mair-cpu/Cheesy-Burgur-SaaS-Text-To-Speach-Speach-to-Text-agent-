<?php
// ================================================================
//  voice_stt.php — Speech-to-Text via Groq Whisper API (FREE)
//  GET YOUR FREE KEY: https://console.groq.com → API Keys
// ================================================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
include_once __DIR__ . '/env.php';

// ── ✅ PASTE YOUR GROQ API KEY HERE ──────────────────────────────
$groq_api_key = getenv('GROQ_API_KEY') ?: 'your_local_fallback_key_here';define('GROQ_MODEL', 'whisper-large-v3-turbo');
define('MAX_AUDIO_MB', 25);

// ── Validate request ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']); exit;
}

if (empty($_FILES['audio'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No audio file received']); exit;
}

$file    = $_FILES['audio'];
$tmpPath = $file['tmp_name'];
$fileSize= $file['size'];

// ── Log every request for debugging ──────────────────────────────
$log = date('Y-m-d H:i:s') . " | size={$fileSize} type={$file['type']}\n";
@file_put_contents(__DIR__ . '/voice_errors.log', $log, FILE_APPEND);

if ($fileSize > MAX_AUDIO_MB * 1024 * 1024) {
    echo json_encode(['error' => 'Audio too large']); exit;
}
if ($fileSize < 200) {
    echo json_encode(['text' => '']); exit;
}

// ── Determine MIME + extension (Robust fix for Groq endpoint) ─────
$clientMime = $file['type'] ?? 'audio/webm';
$mime = $clientMime;
$ext = 'webm'; // Default setup

if (str_contains($mime, 'ogg'))       { $mime = 'audio/ogg'; $ext = 'ogg'; }
elseif (str_contains($mime, 'mp4'))   { $mime = 'audio/mp4'; $ext = 'mp4'; }
elseif (str_contains($mime, 'mpeg'))  { $mime = 'audio/mpeg'; $ext = 'mp3'; }
elseif (str_contains($mime, 'wav'))   { $mime = 'audio/wav'; $ext = 'wav'; }
else                                  { $mime = 'audio/webm'; $ext = 'webm'; }

// ── Copy temp file with correct extension (Groq needs it) ─────────
$namedFile = sys_get_temp_dir() . '/groq_audio_' . time() . '.' . $ext;
if (!copy($tmpPath, $namedFile)) {
    $namedFile = $tmpPath; // fallback if temp dir permissions fail
}

// ── Call Groq Whisper API ─────────────────────────────────────────
$curlFile = new CURLFile($namedFile, $mime, 'recording.' . $ext);

$ch = curl_init('https://api.groq.com/openai/v1/audio/transcriptions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => [
        'file'            => $curlFile,
        'model'           => GROQ_MODEL,
        'language'        => 'en',
        'response_format' => 'json',
        'temperature'     => '0',
    ],
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . GROQ_API_KEY],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,   // ✅ XAMPP Mac fix — SSL certification bypass
    CURLOPT_SSL_VERIFYHOST => false,   // ✅ XAMPP Mac fix
    CURLOPT_FOLLOWLOCATION => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

// ── Cleanup temp file safely ──────────────────────────────────────
if (file_exists($namedFile) && $namedFile !== $tmpPath) {
    @unlink($namedFile);
}

// ── Log result ────────────────────────────────────────────────────
$log2 = date('Y-m-d H:i:s') . " | HTTP={$httpCode} curlErr={$curlErr} resp=" . substr($response,0,120) . "\n";
@file_put_contents(__DIR__ . '/voice_errors.log', $log2, FILE_APPEND);

// ── Handle errors ─────────────────────────────────────────────────
if ($curlErr) {
    echo json_encode(['error' => 'CURL Error: ' . $curlErr]); exit;
}

if ($httpCode !== 200) {
    $errData = json_decode($response, true);
    $msg = $errData['error']['message'] ?? ('HTTP Status ' . $httpCode . ' — Response: ' . substr($response,0,200));
    echo json_encode(['error' => $msg]); exit;
}

// ── Return transcript ─────────────────────────────────────────────
$data = json_decode($response, true);
echo json_encode(['text' => trim($data['text'] ?? '')]);
?>