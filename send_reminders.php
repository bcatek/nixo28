<?php
require 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset("utf8mb4");

// Fetch due reminders
$result = $conn->query(
    "SELECT id, chat_id, message 
     FROM reminders 
     WHERE sent = 0 AND remind_at <= NOW()
     LIMIT 50"
);

while ($row = $result->fetch_assoc()) {

    // Send reminder
    file_get_contents(
        "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage?" .
        http_build_query([
            'chat_id' => $row['chat_id'],
            'text' => "⏰ Reminder:\n" . $row['message']
        ])
    );

    // Mark as sent
    $conn->query(
        "UPDATE reminders SET sent = 1 WHERE id = " . (int)$row['id']
    );
}
