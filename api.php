<?php

/**
 * API REST pour messages annonces.nc - VERSION UNIFIÉE
 * Fichier: api.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Config
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'annonces_messages',
    'user' => 'root',
    'password' => 'mysqlroot'
];

// Activer les logs d'erreur
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ========== CONFIG LOGS DÉDIÉS ==========
$logFile = __DIR__ . '/api_debug.log';
ini_set('log_errors', 1);
ini_set('error_log', $logFile);

function logDebug($message)
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

logDebug("═══════════════════════════════════════════════════");
logDebug("🚀 API CALL: {$_SERVER['REQUEST_METHOD']} ?action=" . ($_GET['action'] ?? 'none'));

try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
        $dbConfig['user'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die(json_encode(['error' => 'BDD: ' . $e->getMessage()]));
}

// ========== FONCTIONS DATETIME ==========

function parseApiDateToDateTime($dateStr)
{
    if (empty($dateStr)) return null;
    try {
        $dt = new DateTime($dateStr);
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        logDebug("⚠️ Erreur parse date API: $dateStr - " . $e->getMessage());
        return null;
    }
}

// ========== ROUTER ==========

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'POST' && $action === 'save') {
    saveConversation($pdo);
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
    echo json_encode(['error' => 'Action invalide']);
}

// ========== SAVE CONVERSATION ==========

function saveConversation($pdo)
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

    try {
        $pdo->beginTransaction();

        // 1. Annonce
        $annonceId = $data['annonce_id'] ?? null;

        if ($annonceId) {
            logDebug("📄 Traitement annonce ID: $annonceId");

            $stmt = $pdo->prepare("
                INSERT INTO annonces (id, url, title, site, description) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    url = VALUES(url),
                    title = VALUES(title),
                    site = VALUES(site),
                    description = COALESCE(VALUES(description), description)
            ");

            try {
                $stmt->execute([
                    $annonceId,
                    $data['annonce_url'] ?? null,
                    $data['info']['title'] ?? 'Sans titre',
                    $data['info']['site'] ?? 'annonces.nc',
                    $data['annonce_description'] ?? null
                ]);
                logDebug("✅ Annonce $annonceId OK");
            } catch (Exception $e) {
                logDebug("⚠️ Erreur annonce: " . $e->getMessage());
                $annonceId = null;
            }
        }

        // 2. User - Ne pas écraser les données personnalisées
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

        // 4. Messages - UPSERT avec vrais ID
        $stmtMsg = $pdo->prepare("
            INSERT INTO messages (
                id, conversation_id, from_me, message_text, 
                message_date, message_datetime, api_from_user_id, api_status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                from_me = VALUES(from_me),
                message_text = VALUES(message_text),
                message_date = VALUES(message_date),
                message_datetime = VALUES(message_datetime),
                api_from_user_id = VALUES(api_from_user_id),
                api_status = VALUES(api_status)
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
                logDebug("⚠️ Message sans ID, skip");
                $skipped++;
                continue;
            }

            $msgDatetime = parseApiDateToDateTime($msg['created_at'] ?? null);

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

            $msgCount++;

            // Images depuis l'API
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

        // 5. Images depuis le scraper DOM (fallback)
        if (!empty($data['images'])) {
            logDebug("📸 Traitement " . count($data['images']) . " images DOM");

            $lastMsgId = $pdo->query("
                SELECT id FROM messages 
                WHERE conversation_id = $conversationId 
                ORDER BY id DESC LIMIT 1
            ")->fetchColumn();

            if ($lastMsgId) {
                $stmtCheckImg = $pdo->prepare("
                    SELECT COUNT(*) FROM message_images mi
                    JOIN messages m ON mi.message_id = m.id
                    WHERE m.conversation_id = ? AND mi.full_url = ?
                ");

                foreach ($data['images'] as $img) {
                    $fullUrl = $img['full'] ?? null;
                    if (!$fullUrl) continue;

                    $stmtCheckImg->execute([$conversationId, $fullUrl]);
                    $exists = $stmtCheckImg->fetchColumn() > 0;

                    if (!$exists) {
                        try {
                            $stmtImg->execute([$lastMsgId, $fullUrl]);
                            $imgCount++;
                            logDebug("📸 Image ajoutée: $fullUrl");
                        } catch (Exception $e) {
                            // Doublon ignoré
                        }
                    } else {
                        logDebug("📸 Image déjà existante, ignorée: $fullUrl");
                    }
                }
            }
        }

        $pdo->commit();

        logDebug("✅ SUCCESS: $msgCount messages, $imgCount images" . ($skipped ? ", $skipped skipped" : ""));
        logDebug("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        echo json_encode([
            'status' => 'saved',
            'message' => 'Conversation enregistrée',
            'conversation_id' => $conversationId,
            'messages_count' => $msgCount,
            'images_count' => $imgCount,
            'skipped' => $skipped
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        logDebug("❌ ERREUR: " . $e->getMessage());
        logDebug("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// ========== GET FUNCTIONS ==========

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
        GROUP BY a.id
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

    try {
        $stmt = $pdo->prepare("
            SELECT m.* FROM messages m
            WHERE m.conversation_id = ?
            ORDER BY m.message_datetime ASC
        ");
        $stmt->execute([$convId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($messages)) {
            $messages = [];
        }

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
    } catch (Exception $e) {
        logDebug("❌ getMessages error: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
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

// ========== UPDATE FUNCTIONS ==========

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

    // Sécurité: whitelist des champs autorisés
    $allowedFields = ['name', 'phone', 'facebook', 'whatsapp', 'commentaire', 'photo_url'];

    if (!in_array($field, $allowedFields)) {
        echo json_encode(['error' => 'Champ non autorisé']);
        return;
    }

    $stmt = $pdo->prepare("UPDATE users SET $field = ? WHERE user_id = ?");
    $stmt->execute([$value, $userId]);
    echo json_encode(['success' => true]);
}
