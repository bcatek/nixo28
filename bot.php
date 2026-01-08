<?php
// ---------- DEBUG (keep for now) ----------
file_put_contents(__DIR__ . "/webhook.log", date('c') . " OK\n", FILE_APPEND);

// ---------- CONFIG ----------
require 'config.php';

// ---------- ALLOW ONLY POST ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// ---------- READ TELEGRAM JSON ----------
$raw = file_get_contents("php://input");
$update = json_decode($raw, true);

if (!$update || !isset($update['message'])) {
    exit;
}

$chatId = $update['message']['chat']['id'];
$text   = trim(strtolower($update['message']['text'] ?? ''));

if ($text === '') exit;

// ---------- QUICK TEST COMMAND ----------
if ($text === '/start') {
    sendMessage(
        $chatId,
        "👋 Hi!\n\nTry:\n• Remind me in 2 minutes\n• Tomorrow at 9am\n• 15 Jan 6pm"
    );
    exit;
}

// ---------- SIMPLE NLP FIX ----------
$text = str_replace('next ', '', $text);

// ---------- PARSE TIME ----------
$timestamp = strtotime($text);

if (!$timestamp || $timestamp <= time()) {
    sendMessage(
        $chatId,
        "❌ I couldn't understand the time.\n\nTry:\n• Remind me in 2 minutes\n• Tomorrow at 9am"
    );
    exit;
}

// ---------- SAVE REMINDER ----------
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset("utf8mb4");

$remindAt = date("Y-m-d H:i:s", $timestamp);

$stmt = $conn->prepare(
    "INSERT INTO reminders (chat_id, message, remind_at) VALUES (?, ?, ?)"
);
$stmt->bind_param("iss", $chatId, $text, $remindAt);
$stmt->execute();

// ---------- CONFIRM ----------
sendMessage(
    $chatId,
    "✅ Reminder set for " . date("d M Y, h:i A", $timestamp)
);

// ---------- FUNCTION ----------
function sendMessage($chatId, $text) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";

    $data = [
        'chat_id' => $chatId,
        'text' => $text
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    curl_exec($ch);
    curl_close($ch);
}

