<?php
if (php_sapi_name() !== 'cli') die('CLI uniquement');

define('CONFIG_FILE', __DIR__ . '/config/users.json');

echo "🔧 Configuration initiale\n\n";
echo "Bot Telegram token: ";
$botToken = trim(fgets(STDIN));
echo "Votre Telegram chat ID: ";
$adminChatId = trim(fgets(STDIN));

$config = [
    'users' => [],
    'telegram_bot_token' => $botToken,
    'admin_telegram_chat_id' => $adminChatId
];

file_put_contents(CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
echo "\n✅ Config créée\n";
