<?php
session_start();

require_once __DIR__ . '/../config.php';

define('CONFIG_DIR', BASE_PATH . '/config');
define('USERS_FILE', CONFIG_DIR . '/users.json');
define('PENDING_FILE', CONFIG_DIR . '/pending_users.json');

class Auth
{

    private static function loadConfig()
    {
        if (!file_exists(USERS_FILE)) {
            return ['users' => [], 'admin_telegram_chat_id' => null];
        }
        return json_decode(file_get_contents(USERS_FILE), true);
    }

    private static function saveConfig($config)
    {
        if (!is_dir(CONFIG_DIR)) {
            mkdir(CONFIG_DIR, 0755, true);
        }
        file_put_contents(USERS_FILE, json_encode($config, JSON_PRETTY_PRINT));
    }

    private static function loadPending()
    {
        if (!file_exists(PENDING_FILE)) {
            return [];
        }
        return json_decode(file_get_contents(PENDING_FILE), true);
    }

    private static function savePending($pending)
    {
        if (!is_dir(CONFIG_DIR)) {
            mkdir(CONFIG_DIR, 0755, true);
        }
        file_put_contents(PENDING_FILE, json_encode($pending, JSON_PRETTY_PRINT));
    }
    private static function verifyAnnoncesCreds($email, $password)
    {
        $tempConfig = [
            'email' => $email,
            'password' => $password,
            'timeouts' => [
                'modal' => 1500,
                'input' => 200,
                'submit' => 300,
                'loginSuccess' => 5000
            ],
            'selectors' => [
                'loginModal' => 'mat-dialog-container annonces-login',
                'loginEmail' => 'input[type="email"]',
                'loginPassword' => 'input[type="password"]',
                'loginSubmit' => 'button[type="submit"]'
            ]
        ];

        $tempFile = CONFIG_DIR . '/verify_' . md5($email) . '.json';
        file_put_contents($tempFile, json_encode($tempConfig));

        // Utiliser le Python du venv
        $pythonPath = BASE_PATH . '/venv/bin/python3';
        $scriptPath = BASE_PATH . '/verify-login.py';

        $cmd = sprintf(
            '%s %s %s 2>&1',
            escapeshellarg($pythonPath),
            escapeshellarg($scriptPath),
            escapeshellarg($tempFile)
        );

        exec($cmd, $output, $returnCode);

        // Logs
        file_put_contents(
            BASE_PATH . '/verify.log',
            "=== VERIFY " . date('Y-m-d H:i:s') . " ===\n" .
                "Email: $email\n" .
                "Output: " . implode("\n", $output) . "\n" .
                "Return: $returnCode\n\n",
            FILE_APPEND
        );

        @unlink($tempFile);

        return $returnCode === 0;
    }

    private static function notifyAdmin($email)
    {
        $config = self::loadConfig();
        $adminChatId = $config['admin_telegram_chat_id'] ?? null;
        $botToken = $config['telegram_bot_token'] ?? null;

        if (!$adminChatId || !$botToken) {
            return false;
        }

        $message = "🔔 <b>Nouvelle demande d'accès</b>\n\n";
        $message .= "Email: <code>$email</code>\n";
        $message .= "Les credentials ont été vérifiés sur annonces.nc\n\n";
        $message .= "Pour approuver:\n";
        $message .= "<code>php approve-user.php $email</code>\n\n";
        $message .= "Pour rejeter:\n";
        $message .= "<code>php reject-user.php $email</code>";

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $data = [
            'chat_id' => $adminChatId,
            'text' => $message,
            'parse_mode' => 'HTML'
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

    public static function login($email, $password)
    {
        $config = self::loadConfig();

        foreach ($config['users'] as $user) {
            if ($user['email'] === $email && password_verify($password, $user['password_hash'])) {
                $_SESSION['authenticated'] = true;
                $_SESSION['email'] = $email;
                $_SESSION['db_name'] = $user['db_name'];
                $_SESSION['annonces_password'] = $password;
                $_SESSION['telegram_chat_id'] = $user['telegram_chat_id'] ?? null;
                return ['success' => true, 'status' => 'logged_in'];
            }
        }

        $pending = self::loadPending();
        if (isset($pending[$email])) {
            return [
                'success' => false,
                'status' => 'pending',
                'message' => 'Votre demande d\'accès est en attente de validation'
            ];
        }

        if (!self::verifyAnnoncesCreds($email, $password)) {
            return [
                'success' => false,
                'status' => 'invalid_credentials',
                'message' => 'Identifiants annonces.nc invalides'
            ];
        }

        $pending[$email] = [
            'email' => $email,
            'password' => $password,
            'requested_at' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        self::savePending($pending);
        self::notifyAdmin($email);

        return [
            'success' => false,
            'status' => 'pending',
            'message' => 'Credentials valides ! Demande envoyée à l\'administrateur pour validation.'
        ];
    }

    public static function logout()
    {
        session_destroy();
    }

    public static function isAuthenticated()
    {
        return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
    }

    public static function requireAuth()
    {
        if (!self::isAuthenticated()) {
            header('Location: ' . dirname($_SERVER['SCRIPT_NAME']) . '/auth/login.php');
            exit;
        }
    }

    public static function getCurrentUser()
    {
        return [
            'email' => $_SESSION['email'] ?? null,
            'db_name' => $_SESSION['db_name'] ?? null,
            'annonces_password' => $_SESSION['annonces_password'] ?? null,
            'telegram_chat_id' => $_SESSION['telegram_chat_id'] ?? null
        ];
    }

    public static function approveUser($email)
    {
        $pending = self::loadPending();

        if (!isset($pending[$email])) {
            return ['success' => false, 'error' => 'Utilisateur non trouvé en attente'];
        }

        $userData = $pending[$email];
        $dbName = 'annonces_messages_' . preg_replace('/[^a-z0-9]/', '_', strtolower(explode('@', $email)[0]));

        try {
            self::createDatabase($dbName);
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Erreur création BDD: ' . $e->getMessage()];
        }

        $config = self::loadConfig();
        $config['users'][] = [
            'email' => $email,
            'password_hash' => password_hash($userData['password'], PASSWORD_DEFAULT),
            'db_name' => $dbName,
            'telegram_chat_id' => null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        self::saveConfig($config);

        unset($pending[$email]);
        self::savePending($pending);

        return ['success' => true, 'db_name' => $dbName];
    }

    private static function createDatabase($dbName)
    {
        $pdo = new PDO(
            "mysql:host=localhost",
            'root',
            'mysqlroot',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbName`");

        $schema = file_get_contents(BASE_PATH . '/schema_update.sql');

        $statements = array_filter(
            array_map('trim', explode(';', $schema)),
            fn($s) => !empty($s) && !preg_match('/^--/', $s)
        );

        foreach ($statements as $stmt) {
            try {
                $pdo->exec($stmt);
            } catch (Exception $e) {
                // Ignorer si table existe déjà
            }
        }
    }
}
