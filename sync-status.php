<?php

/**
 * Vérifier le statut du scraper
 */

require_once __DIR__ . '/auth/auth.php';
Auth::requireAuth();

header('Content-Type: application/json');

$user = Auth::getCurrentUser();
$username = explode('@', $user['email'])[0];
$lockFile = __DIR__ . '/locks/' . $username . '.lock';

// Vérifier si le lock existe
if (!file_exists($lockFile)) {
    echo json_encode(['status' => 'idle']);
    exit;
}

// Vérifier si le process est toujours actif
$pid = file_get_contents($lockFile);

if (posix_getpgid($pid) === false) {
    // Process mort, nettoyer le lock
    unlink($lockFile);
    echo json_encode(['status' => 'idle']);
    exit;
}

// Lire le log pour avoir des infos
$logFile = __DIR__ . '/logs/' . $username . '_sync.log';
$lastLine = '';

if (file_exists($logFile)) {
    $lines = file($logFile);
    $lastLine = trim(end($lines));
}

echo json_encode([
    'status' => 'running',
    'pid' => $pid,
    'last_log' => $lastLine
]);
