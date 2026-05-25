<?php
// ================================================================
//  test_groq.php — Run this in browser to test Groq connection
//  URL: http://localhost/cheesyburgers/test_groq.php
//  DELETE this file after testing!
// ================================================================
?>
<!DOCTYPE html>
<html>
<head><title>Groq Test</title>
<style>
  body{font-family:monospace;background:#111;color:#eee;padding:30px;max-width:700px}
  .ok{color:#4ade80}.err{color:#f87171}.warn{color:#fbbf24}
  .box{background:#1e1e1e;border-radius:10px;padding:20px;margin:15px 0;border:1px solid #333}
  pre{background:#0a0a0a;padding:12px;border-radius:6px;font-size:12px;color:#aaa;overflow-x:auto}
</style>
</head>
<body>
<h2>🎤 Groq API Connection Test</h2>

<?php
// ── PASTE YOUR KEY HERE ───────────────────────────────────────────
$GROQ_KEY = getenv('GROQ_API_KEY') ?: 'REPLACE_WITH_YOUR_GROQ_API_KEY';  // <-- set env var or replace locally
// ─────────────────────────────────────────────────────────────────

echo '<div class="box">';
echo '<b>Step 1: Testing CURL to Groq...</b><br><br>';

// Test basic connectivity
$ch = curl_init('https://api.groq.com/openai/v1/models');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $GROQ_KEY],
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($err) {
    echo '<span class="err">❌ CURL Error: ' . htmlspecialchars($err) . '</span>';
    echo '<br><span class="warn">→ Fix: Check your internet connection or XAMPP CURL settings</span>';
} elseif ($code === 401) {
    echo '<span class="err">❌ HTTP 401 — API Key is WRONG or expired</span>';
    echo '<br><span class="warn">→ Fix: Get a new key from https://console.groq.com/keys</span>';
} elseif ($code === 200) {
    $data = json_decode($res, true);
    $models = array_column($data['data'] ?? [], 'id');
    $whisper = array_filter($models, fn($m) => str_contains($m, 'whisper'));
    echo '<span class="ok">✅ Connected to Groq! HTTP 200</span><br>';
    echo '<span class="ok">✅ API Key is VALID</span><br><br>';
    echo '<b>Available Whisper models:</b><br>';
    foreach ($whisper as $m) echo '<span class="ok">  ✅ ' . $m . '</span><br>';
} else {
    echo '<span class="err">❌ HTTP ' . $code . '</span>';
    echo '<pre>' . htmlspecialchars(substr($res,0,300)) . '</pre>';
}
echo '</div>';

// Check voice_errors.log
echo '<div class="box">';
echo '<b>Step 2: voice_errors.log (last 10 lines)</b><br><br>';
$logFile = __DIR__ . '/voice_errors.log';
if (file_exists($logFile)) {
    $lines = array_slice(file($logFile), -10);
    echo '<pre>' . htmlspecialchars(implode('', $lines)) . '</pre>';
} else {
    echo '<span class="warn">⚠️ No log file yet — voice_stt.php has not been called yet</span>';
}
echo '</div>';

// Check api_errors.log (Gemini)
echo '<div class="box">';
echo '<b>Step 3: api_errors.log (Gemini errors)</b><br><br>';
$apiLog = __DIR__ . '/api_errors.log';
if (file_exists($apiLog)) {
    $lines = array_slice(file($apiLog), -5);
    echo '<pre>' . htmlspecialchars(implode('', $lines)) . '</pre>';
} else {
    echo '<span class="ok">✅ No Gemini errors logged</span>';
}
echo '</div>';
?>
</body>
</html>