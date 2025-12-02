<?php

/**
 * API REST multi-utilisateurs avec gestion robuste des erreurs
 */

// Désactiver l'affichage des erreurs (on retourne du JSON)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Forcer JSON dès le début
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type, X-User-Database');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Fonction pour toujours retourner du JSON propre
function jsonError($message, $code = 500)
{
    http_response_code($code);
    echo json_encode(['error' => $message, 'success' => false]);
    exit;
}

function jsonSuccess($data)
{
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

// Charger les dépendances
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

// Auth sauf pour save
if ($action !== 'save') {
    require_once __DIR__ . '/auth/auth.php';
    if (!Auth::isAuthenticated()) {
        jsonError('Non authentifié', 401);
    }
}

// Déterminer la base de données
$dbName = null;

if ($action === 'save') {
    $dbName = $_SERVER['HTTP_X_USER_DATABASE'] ?? null;
    if (!$dbName) {
        jsonError('Header X-User-Database manquant', 400);
    }
} else {
    require_once __DIR__ . '/auth/auth.php';
    $user = Auth::getCurrentUser();
    $dbName = $user['db_name'];

    if (!$dbName) {
        jsonError('Base de données non définie pour cet utilisateur', 500);
    }
}

// Logs
$logFile = BASE_PATH . '/logs/api_' . $dbName . '.log';
@mkdir(BASE_PATH . '/logs', 0755, true);

function logDebug($message)
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

logDebug("API CALL: {$_SERVER['REQUEST_METHOD']} ?action=$action");

// Créer la base si elle n'existe pas
// Créer la base si elle n'existe pas
try {
    require_once BASE_PATH . '/db-manager.php';

    $dbCheck = DatabaseManager::checkDatabase($dbName);

    if (!$dbCheck['exists'] || !$dbCheck['has_tables']) {
        logDebug("Base '$dbName' nécessite initialisation (exists={$dbCheck['exists']}, tables={$dbCheck['table_count']})");
        DatabaseManager::createDatabase($dbName);
    } else {
        logDebug("Base '$dbName' OK ({$dbCheck['table_count']} tables)");
    }
} catch (Exception $e) {
    logDebug("Erreur init base: " . $e->getMessage());
    jsonError('Erreur initialisation base de données: ' . $e->getMessage(), 500);
}

// Connexion à la base
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=$dbName;charset=utf8mb4",
        'root',
        'mysqlroot',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    logDebug("Erreur connexion PDO: " . $e->getMessage());
    jsonError('Erreur connexion base de données', 500);
}

// ========== FONCTIONS DATETIME ==========

function parseApiDateToDateTime($dateStr)
{
    if (empty($dateStr)) return null;
    try {
        $dt = new DateTime($dateStr);
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return null;
    }
}

// ========== ROUTER ==========

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST' && $action === 'save') {
        saveConversation($pdo, $dbName);
    } elseif ($method === 'POST' && $action === 'update_user_comment') {
        updateUserComment($pdo);
    } elseif ($method === 'POST' && $action === 'update_user_photo') {
        updateUserPhoto($pdo);
    } elseif ($method === 'POST' && $action === 'update_user_profile') {
        updateUserProfile($pdo);
    } elseif ($method === 'POST' && $action === 'update_user_field') {
        updateUserField($pdo);
    } elseif ($method === 'GET' && $action === 'stats') {
        getStats($pdo);
    } elseif ($method === 'GET' && $action === 'annonces') {
        getAnnonces($pdo);
    } elseif ($method === 'GET' && $action === 'users') {
        getUsers($pdo);
    } elseif ($method === 'GET' && $action === 'conversations') {
        getConversations($pdo);
    } elseif ($method === 'GET' && $action === 'messages') {
        getMessages($pdo);
    } elseif ($method === 'GET' && $action === 'conversation_detail') {
        getConversationDetail($pdo);
    } else {
        jsonError('Action invalide', 400);
    }
} catch (Exception $e) {
    logDebug("Exception: " . $e->getMessage());
    jsonError('Erreur serveur: ' . $e->getMessage(), 500);
}

// ========== SAVE CONVERSATION (avec notification Telegram) ==========

function saveConversation($pdo, $dbName)
{
    logDebug("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    logDebug("🔵 API SAVE START");

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!$data) {
        logDebug("❌ JSON decode failed");
        echo json_encode(['error' => 'JSON invalide']);
        return;
    }

    logDebug("📦 Data reçue:");
    logDebug("   - conversation_id: " . ($data['conversation_id'] ?? 'NULL'));
    logDebug("   - user_id: " . ($data['user_id'] ?? 'NULL'));
    logDebug("   - annonce_id: " . ($data['annonce_id'] ?? 'NULL'));
    logDebug("   - messages: " . count($data['messages'] ?? []));

    $newMessagesCount = 0;

    try {
        $pdo->beginTransaction();

        // 1. Annonce
        $annonceId = $data['annonce_id'] ?? null;

        if ($annonceId) {
            logDebug("📄 Traitement annonce ID: $annonceId");

            $stmt = $pdo->prepare("
                INSERT INTO annonces (id, url, title, site, description, is_deleted) 
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    url = VALUES(url),
                    title = VALUES(title),
                    site = VALUES(site),
                    description = COALESCE(VALUES(description), description),
                    is_deleted = VALUES(is_deleted)
            ");

            try {
                $isDeleted = strpos($annonceId, 'deleted_') === 0;

                $stmt->execute([
                    $annonceId,
                    $data['annonce_url'] ?? null,
                    $data['info']['title'] ?? 'Sans titre',
                    $data['info']['site'] ?? 'annonces.nc',
                    $data['annonce_description'] ?? null,
                    $isDeleted ? 1 : 0
                ]);

                if ($isDeleted) {
                    logDebug("✅ Annonce supprimée $annonceId OK");
                } else {
                    logDebug("✅ Annonce $annonceId OK");
                }
            } catch (Exception $e) {
                logDebug("⚠️ Erreur annonce: " . $e->getMessage());
                $annonceId = null;
            }
        }

        // 2. User
        $userId = $data['user_id'] ?? null;

        if (!$userId) {
            logDebug("❌ user_id manquant !");
            throw new Exception('user_id requis');
        }

        logDebug("👤 Traitement user ID: $userId");

        $stmt = $pdo->prepare("
            INSERT INTO users (user_id, user_name) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE 
                user_name = VALUES(user_name)
        ");
        $stmt->execute([
            $userId,
            $data['info']['user'] ?? "Utilisateur $userId"
        ]);
        logDebug("✅ User $userId OK");

        // 3. Conversation
        $conversationId = $data['conversation_id'] ?? null;

        if (!$conversationId) {
            logDebug("❌ conversation_id manquant !");
            throw new Exception('conversation_id requis');
        }

        logDebug("💬 Traitement conversation ID: $conversationId");

        $stmt = $pdo->prepare("
            INSERT INTO conversations (id, annonce_id, user_id)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                annonce_id = COALESCE(VALUES(annonce_id), annonce_id)
        ");
        $stmt->execute([
            $conversationId,
            $annonceId,
            $userId
        ]);

        logDebug("✅ Conversation $conversationId OK");

        // 4. Messages - compter les nouveaux
        $stmtMsg = $pdo->prepare("
            INSERT INTO messages (
                id, conversation_id, from_me, message_text, 
                message_date, message_datetime, api_from_user_id, api_status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE id = id
        ");

        $stmtImg = $pdo->prepare("
            INSERT INTO message_images (message_id, full_url)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE full_url = VALUES(full_url)
        ");

        $msgCount = 0;
        $imgCount = 0;
        $skipped = 0;

        foreach ($data['messages'] as $msg) {
            $messageId = $msg['id'] ?? null;

            if (!$messageId) {
                $skipped++;
                continue;
            }

            $msgDatetime = parseApiDateToDateTime($msg['created_at'] ?? null);

            // Vérifier si nouveau
            $existsStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE id = ?");
            $existsStmt->execute([$messageId]);
            $isNew = $existsStmt->fetchColumn() == 0;

            $stmtMsg->execute([
                $messageId,
                $conversationId,
                ($msg['my_message'] ?? false) ? 1 : 0,
                $msg['content'] ?? '',
                $msg['created_at'] ?? null,
                $msgDatetime,
                $msg['from'] ?? null,
                $msg['status'] ?? null
            ]);

            if ($isNew) {
                $newMessagesCount++;
            }

            $msgCount++;

            // Images
            if (!empty($msg['medias'])) {
                foreach ($msg['medias'] as $media) {
                    $fullUrl = $media['versions']['original']['url'] ?? null;
                    if ($fullUrl) {
                        try {
                            $stmtImg->execute([$messageId, $fullUrl]);
                            $imgCount++;
                        } catch (Exception $e) {
                            // Doublon ignoré
                        }
                    }
                }
            }
        }

        // 5. Images DOM
        if (!empty($data['images'])) {
            $lastMsgId = $pdo->query("
                SELECT id FROM messages 
                WHERE conversation_id = $conversationId 
                ORDER BY id DESC LIMIT 1
            ")->fetchColumn();

            if ($lastMsgId) {
                foreach ($data['images'] as $img) {
                    $fullUrl = $img['full'] ?? null;
                    if ($fullUrl) {
                        try {
                            $stmtImg->execute([$lastMsgId, $fullUrl]);
                            $imgCount++;
                        } catch (Exception $e) {
                            // Doublon ignoré
                        }
                    }
                }
            }
        }

        $pdo->commit();

        logDebug("✅ SUCCESS: $msgCount messages ($newMessagesCount nouveaux), $imgCount images");
        logDebug("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // Notification Telegram si nouveaux messages
        if ($newMessagesCount > 0) {
            sendTelegramNotification($dbName, $newMessagesCount);
        }

        echo json_encode([
            'status' => 'saved',
            'message' => 'Conversation enregistrée',
            'conversation_id' => $conversationId,
            'messages_count' => $msgCount,
            'new_messages' => $newMessagesCount,
            'images_count' => $imgCount,
            'skipped' => $skipped
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        logDebug("❌ ERREUR: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function sendTelegramNotification($dbName, $newCount)
{
    try {
        $configFile = __DIR__ . '/config/users.json';
        if (!file_exists($configFile)) return;

        $config = json_decode(file_get_contents($configFile), true);

        // Trouver le telegram_chat_id pour cette base
        $chatId = null;
        foreach ($config['users'] as $user) {
            if ($user['db_name'] === $dbName) {
                $chatId = $user['telegram_chat_id'] ?? null;
                break;
            }
        }

        if (!$chatId) return;

        $notifier = new TelegramNotifier();
        $notifier->send(
            $chatId,
            "🔔 <b>$newCount nouveau(x) message(s)</b>\n\nConsultez votre interface pour les voir."
        );
    } catch (Exception $e) {
        logDebug("⚠️ Erreur notification Telegram: " . $e->getMessage());
    }
}

// ========== AUTRES FONCTIONS (identiques à l'original) ==========

function getStats($pdo)
{
    echo json_encode([
        'annonces' => (int)$pdo->query("SELECT COUNT(*) FROM annonces")->fetchColumn(),
        'users' => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'conversations' => (int)$pdo->query("SELECT COUNT(*) FROM conversations")->fetchColumn(),
        'messages' => (int)$pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn()
    ]);
}

function getAnnonces($pdo)
{
    $annonces = $pdo->query("
        SELECT 
            a.*,
            COUNT(DISTINCT c.id) as conv_count,
            COUNT(m.id) as total_messages,
            MAX(m.message_datetime) as last_message_datetime,
            DATE_FORMAT(MAX(m.message_datetime), '%d %b %Y à %H:%i') as last_message_date
        FROM annonces a
        LEFT JOIN conversations c ON a.id = c.annonce_id
        LEFT JOIN messages m ON c.id = m.conversation_id
        GROUP BY a.id, a.url, a.title, a.site, a.description, a.is_deleted, a.created_at
        ORDER BY last_message_datetime DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($annonces);
}

function getUsers($pdo)
{
    $users = $pdo->query("
        SELECT 
            u.*,
            COUNT(DISTINCT c.id) as conv_count,
            COUNT(m.id) as total_messages,
            MAX(m.message_datetime) as last_message_datetime,
            DATE_FORMAT(MAX(m.message_datetime), '%d %b %Y à %H:%i') as last_message_date
        FROM users u
        LEFT JOIN conversations c ON u.user_id = c.user_id
        LEFT JOIN messages m ON c.id = m.conversation_id
        GROUP BY u.user_id
        ORDER BY last_message_datetime DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($users);
}

function getConversations($pdo)
{
    $annonceId = $_GET['annonce_id'] ?? null;
    $userId = $_GET['user_id'] ?? null;

    if (!$annonceId && !$userId) {
        echo json_encode(['error' => 'annonce_id ou user_id requis']);
        return;
    }

    if ($annonceId) {
        $where = "c.annonce_id = " . (int)$annonceId;
    } else {
        $where = "c.user_id = " . (int)$userId;
    }

    $conversations = $pdo->query("
        SELECT 
            c.id as conversation_id,
            c.annonce_id,
            c.user_id,
            MAX(m.message_datetime) as last_message_datetime,
            DATE_FORMAT(MAX(m.message_datetime), '%d %b %Y à %H:%i') as last_message_date,
            COUNT(m.id) as messages_count,
            a.title as annonce_title, 
            a.url as annonce_url,
            a.description as annonce_description,
            u.user_name, 
            u.name as user_display_name,
            u.photo_url, 
            u.phone,
            u.facebook,
            u.whatsapp,
            u.commentaire
        FROM conversations c
        LEFT JOIN messages m ON c.id = m.conversation_id
        LEFT JOIN annonces a ON c.annonce_id = a.id
        LEFT JOIN users u ON c.user_id = u.user_id
        WHERE $where
        GROUP BY c.id, c.annonce_id, c.user_id, a.title, a.url, a.description, 
                 u.user_name, u.name, u.photo_url, u.phone, u.facebook, u.whatsapp, u.commentaire
        ORDER BY last_message_datetime DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($conversations);
}

function getMessages($pdo)
{
    $convId = (int)($_GET['conversation_id'] ?? 0);

    if (!$convId) {
        echo json_encode(['error' => 'conversation_id requis']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT m.* FROM messages m
        WHERE m.conversation_id = ?
        ORDER BY m.message_datetime ASC
    ");
    $stmt->execute([$convId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($messages as &$msg) {
        $stmtImg = $pdo->prepare("
            SELECT id, full_url, local_path
            FROM message_images
            WHERE message_id = ?
        ");
        $stmtImg->execute([$msg['id']]);
        $msg['images'] = $stmtImg->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    echo json_encode($messages);
}

function getConversationDetail($pdo)
{
    $convId = (int)($_GET['conversation_id'] ?? 0);

    if (!$convId) {
        echo json_encode(['error' => 'conversation_id requis']);
        return;
    }

    $detail = $pdo->query("
        SELECT c.*, 
               a.title as annonce_title, 
               a.description as annonce_description
        FROM conversations c
        LEFT JOIN annonces a ON c.annonce_id = a.id
        WHERE c.id = $convId
    ")->fetch(PDO::FETCH_ASSOC);

    echo json_encode($detail ?: []);
}

function updateUserComment($pdo)
{
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("UPDATE users SET commentaire = ? WHERE user_id = ?");
    $stmt->execute([$data['commentaire'], (int)$data['user_id']]);
    echo json_encode(['success' => true]);
}

function updateUserPhoto($pdo)
{
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("UPDATE users SET photo_url = ? WHERE user_id = ?");
    $stmt->execute([$data['photo_url'], (int)$data['user_id']]);
    echo json_encode(['success' => true]);
}

function updateUserProfile($pdo)
{
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("
        UPDATE users 
        SET name = ?, 
            phone = ?,
            facebook = ?,
            whatsapp = ?,
            commentaire = ? 
        WHERE user_id = ?
    ");
    $stmt->execute([
        $data['name'],
        $data['phone'] ?? null,
        $data['facebook'] ?? null,
        $data['whatsapp'] ?? null,
        $data['commentaire'],
        (int)$data['user_id']
    ]);
    echo json_encode(['success' => true]);
}

function updateUserField($pdo)
{
    $data = json_decode(file_get_contents('php://input'), true);
    $field = $data['field'];
    $value = $data['value'];
    $userId = (int)$data['user_id'];

    $allowedFields = ['name', 'phone', 'facebook', 'whatsapp', 'commentaire', 'photo_url'];

    if (!in_array($field, $allowedFields)) {
        echo json_encode(['error' => 'Champ non autorisé']);
        return;
    }

    $stmt = $pdo->prepare("UPDATE users SET $field = ? WHERE user_id = ?");
    $stmt->execute([$value, $userId]);
    echo json_encode(['success' => true]);
}
