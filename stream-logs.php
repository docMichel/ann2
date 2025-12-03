<?php

/**
 * STREAM LOGS - Server-Sent Events
 * 
 * Stream les logs du scraper en temps réel vers le navigateur
 * Plus simple que websockets, parfait pour ce cas d'usage
 */

require_once __DIR__ . '/auth/auth.php';
require_once __DIR__ . '/config.php';
Auth::requireAuth();

$user = Auth::getCurrentUser();
$username = explode('@', $user['email'])[0];
$logFile = BASE_PATH . '/logs/' . $username . '_sync.log';

// Libérer le verrou de session
session_write_close();

// Headers SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');  // Nginx
header('Connection: keep-alive');

// Désactiver le buffering PHP
ob_end_flush();
if (ob_get_level()) ob_end_clean();

/**
 * Envoie un événement SSE
 */
function sendEvent($event, $data)
{
    echo "event: $event\n";
    echo "data: " . json_encode($data) . "\n\n";
    if (ob_get_level()) ob_flush();
    flush();
}

/**
 * Vérifie si le scraper tourne
 */
function isScraperRunning($username)
{
    $lockFile = BASE_PATH . '/locks/' . $username . '.lock';

    if (!file_exists($lockFile)) {
        return false;
    }

    $pid = trim(file_get_contents($lockFile));
    if (empty($pid) || !is_numeric($pid)) {
        return false;
    }

    // Vérifier si le process existe (Unix)
    return posix_getpgid($pid) !== false;
}

// Envoyer un ping initial
sendEvent('connected', ['timestamp' => time()]);

// Si le log n'existe pas encore
if (!file_exists($logFile)) {
    sendEvent('info', ['message' => 'En attente du démarrage du scraper...']);

    // Attendre max 10s que le log apparaisse
    for ($i = 0; $i < 20; $i++) {
        usleep(100000); // 500ms
        if (file_exists($logFile)) {
            break;
        }
    }

    if (!file_exists($logFile)) {
        sendEvent('error', ['message' => 'Log file introuvable: ' . basename($logFile)]);
        exit;
    }
}

// Position actuelle dans le fichier
$lastPos = 0;
$lastCheck = time();
$timeout = 600; // 10 minutes max

sendEvent('info', ['message' => 'Connexion établie, streaming des logs...']);

while (true) {
    // Timeout
    if (time() - $lastCheck > $timeout) {
        sendEvent('timeout', ['message' => 'Timeout après ' . $timeout . 's']);
        break;
    }

    // Vérifier si le scraper tourne toujours
    $running = isScraperRunning($username);

    // Lire les nouvelles lignes
    if (file_exists($logFile)) {
        clearstatcache(false, $logFile);
        $currentSize = filesize($logFile);

        if ($currentSize > $lastPos) {
            $fp = fopen($logFile, 'r');
            fseek($fp, $lastPos);

            while (($line = fgets($fp)) !== false) {
                $line = trim($line);
                if (!empty($line)) {
                    sendEvent('log', [
                        'line' => $line,
                        'timestamp' => time()
                    ]);
                }
            }

            $lastPos = ftell($fp);
            fclose($fp);
            $lastCheck = time();
        }
    }

    // Si le scraper n'est plus actif, envoyer un événement final
    if (!$running) {
        sendEvent('complete', [
            'message' => 'Scraper terminé',
            'timestamp' => time()
        ]);
        break;
    }

    // Heartbeat toutes les 5s
    if (time() % 5 == 0) {
        sendEvent('heartbeat', ['timestamp' => time()]);
    }

    usleep(100000); // 500ms
}

sendEvent('close', ['message' => 'Stream fermé']);
