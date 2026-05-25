<?php
// ================================================================
//  whisper_proxy.php — Transcribe recorded audio chunks via Groq
// ================================================================
session_start();
header('Content-Type: application/json');

// ⚠️ Put your Groq API Key here (Free tier allows plenty of requests/min)
define('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: 'REPLACE_WITH_YOUR_GROQ_API_KEY');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['audio'])) {
    echo json_encode(['success' => false, 'error' => 'No audio file received']);
    exit;
}

$audioFile = $_FILES['audio']['tmp_name'];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.groq.com/openai/v1/audio/transcriptions',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . GROQ_API_KEY
    ],
    CURLOPT_POSTFIELDS => [
        'file' => new CURLFile($audioFile, 'audio/wav', 'recording.wav'),
        'model' => 'whisper-large-v3',
        'response_format' => 'json',
        'language' => 'en'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode(['success' => false, 'error' => 'Whisper API Error: ' . $httpCode, 'raw' => $response]);
    exit;
}

$data = json_decode($response, true);
echo json_encode([
    'success' => true,
    'text' => $data['text'] ?? ''
]);