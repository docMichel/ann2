<?php

/**
 * Système de notifications Telegram
 */

class TelegramNotifier
{

    private $botToken;
    private $enabled;

    public function __construct()
    {
        $configFile = __DIR__ . '/config/users.json';

        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
            $this->botToken = $config['telegram']['bot_token'] ?? null;
            $this->enabled = $config['telegram']['enabled'] ?? false;
        } else {
            $this->enabled = false;
        }
    }

    /**
     * Envoyer une notification
     */
    public function send($chatId, $message, $parseMode = 'HTML')
    {
        if (!$this->enabled || !$this->botToken || !$chatId) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";

        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => true
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    /**
     * Notification de nouveaux messages
     */
    public function notifyNewMessages($chatId, $stats)
    {
        $message = "🔔 <b>Nouveaux messages Annonces.nc</b>\n\n";
        $message .= "📨 <b>{$stats['messages_count']}</b> nouveau(x) message(s)\n";
        $message .= "💬 Dans <b>{$stats['conversations_count']}</b> conversation(s)\n";

        if (!empty($stats['annonces_titles'])) {
            $message .= "\n📋 <b>Annonces concernées:</b>\n";
            foreach (array_slice($stats['annonces_titles'], 0, 5) as $title) {
                $message .= "  • " . htmlspecialchars($title) . "\n";
            }

            if (count($stats['annonces_titles']) > 5) {
                $message .= "  • ... et " . (count($stats['annonces_titles']) - 5) . " autre(s)\n";
            }
        }

        $message .= "\n⏰ " . date('d/m/Y à H:i');

        return $this->send($chatId, $message);
    }

    /**
     * Notification d'erreur scraper
     */
    public function notifyScraperError($chatId, $error)
    {
        $message = "❌ <b>Erreur Scraper Annonces.nc</b>\n\n";
        $message .= "Erreur: " . htmlspecialchars($error) . "\n";
        $message .= "\n⏰ " . date('d/m/Y à H:i');

        return $this->send($chatId, $message);
    }

    /**
     * Notification de fin de scraping
     */
    public function notifyScraperComplete($chatId, $stats)
    {
        $message = "✅ <b>Scraping terminé</b>\n\n";
        $message .= "📊 <b>Résumé:</b>\n";
        $message .= "  • Total: {$stats['total']} conversations\n";
        $message .= "  • Succès: {$stats['succeeded']}\n";
        $message .= "  • Échecs: {$stats['failed']}\n";
        $message .= "\n⏰ " . date('d/m/Y à H:i');

        return $this->send($chatId, $message);
    }

    /**
     * Tester la configuration Telegram
     */
    public function test($chatId)
    {
        $message = "✅ <b>Test notification Telegram</b>\n\n";
        $message .= "Votre configuration Telegram fonctionne correctement !\n";
        $message .= "\n⏰ " . date('d/m/Y à H:i');

        return $this->send($chatId, $message);
    }
}

// ========== UTILISATION STANDALONE ==========

if (php_sapi_name() === 'cli') {
    // Usage CLI: php telegram-notify.php test CHAT_ID
    // ou: php telegram-notify.php notify CHAT_ID "Message"

    $action = $argv[1] ?? 'test';
    $chatId = $argv[2] ?? null;

    if (!$chatId) {
        echo "Usage: php telegram-notify.php test CHAT_ID\n";
        echo "   ou: php telegram-notify.php notify CHAT_ID \"Message\"\n";
        exit(1);
    }

    $notifier = new TelegramNotifier();

    if ($action === 'test') {
        $result = $notifier->test($chatId);
        echo $result ? "✅ Message envoyé\n" : "❌ Erreur envoi\n";
    } elseif ($action === 'notify') {
        $message = $argv[3] ?? 'Test message';
        $result = $notifier->send($chatId, $message);
        echo $result ? "✅ Message envoyé\n" : "❌ Erreur envoi\n";
    }
}
