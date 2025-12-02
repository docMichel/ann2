<?php

/**
 * Lance le scraper en arrière-plan pour l'utilisateur connecté
 */

require_once __DIR__ . '/auth/auth.php';
require_once __DIR__ . '/config.php';
Auth::requireAuth();

header('Content-Type: application/json');

$user = Auth::getCurrentUser();
$username = explode('@', $user['email'])[0];  // ← Utiliser email
$lockFile = BASE_PATH . '/locks/' . $username . '.lock';
$logFile = BASE_PATH . '/logs/' . $username . '_sync.log';

// Créer les dossiers si nécessaire
@mkdir(__DIR__ . '/locks', 0755, true);
@mkdir(__DIR__ . '/logs', 0755, true);

// ========== VÉRIFIER SI UN SCRAPER TOURNE DÉJÀ ==========

if (file_exists($lockFile)) {
    $lockTime = filemtime($lockFile);
    $elapsed = time() - $lockTime;

    // Timeout de 2h (configurable)
    $timeout = 7200;

    if ($elapsed < $timeout) {
        // Lire le PID si disponible
        $pid = file_get_contents($lockFile);

        // Vérifier si le process tourne encore
        if (posix_getpgid($pid) !== false) {
            echo json_encode([
                'status' => 'running',
                'message' => 'Un scraper est déjà en cours',
                'elapsed' => $elapsed,
                'pid' => $pid
            ]);
            exit;
        }
    }

    // Lock expiré ou process mort, on le supprime
    unlink($lockFile);
}

// ========== CRÉER LA CONFIG TEMPORAIRE POUR LE SCRAPER ==========

$scraperConfig = [
    'email' => $user['email'],  // ← Pas annonces_email
    'password' => $user['annonces_password'],
    'db_name' => $user['db_name'],  // ← AJOUTE ÇA
    'apiUrl' => 'http://localhost' . dirname($_SERVER['PHP_SELF']) . '/api.php?action=save',
    'maxPages' => 5,
    'maxConversations' => 600,
    'timeouts' => [
        'modal' => 1500,
        'input' => 200,
        'submit' => 300,
        'loginSuccess' => 5000,
        'xhrTimeout' => 3000,
        'annonceModal' => 1500,
        'betweenConvs' => 300,
        'loadMore' => 1000,
        'images' => 500
    ],
    'selectors' => [
        'loginModal' => 'mat-dialog-container annonces-login',
        'loginEmail' => 'input[type="email"]',
        'loginPassword' => 'input[type="password"]',
        'loginSubmit' => 'button[type="submit"]',
        'convList' => '.conversations__sidebar__content > .clickable',
        'convTitle' => '.text-dark.text-sm',
        'convUser' => '.font-weight-normal.position-relative',
        'voirPlus' => '.conversations__sidebar__content button.rounded-pill',
        'annonceBtn' => 'button.btn-primary.ml-2',
        'annonceDesc' => '.mat-dialog-container .card-body .pre-wrap.text-justify',
        'annonceBadge' => '.mat-dialog-container .badge.badge-light.text-sm',
        'annonceClose' => '.mat-dialog-container .text-2x',
        'images' => '.chat-content annonces-image img'
    ]
];

$tempConfigFile = BASE_PATH . '/config/temp_' . $username . '.json';
file_put_contents($tempConfigFile, json_encode($scraperConfig, JSON_PRETTY_PRINT));

// ========== LANCER LE SCRAPER EN ARRIÈRE-PLAN ==========

$pythonCmd = sprintf(
    'cd %s && %s/venv/bin/python3 sync.py --config=%s > %s 2>&1 & echo $!',
    escapeshellarg(BASE_PATH),
    escapeshellarg(BASE_PATH),
    escapeshellarg($tempConfigFile),
    escapeshellarg($logFile)
);

// Exécuter et récupérer le PID
$pid = shell_exec($pythonCmd);
$pid = trim($pid);

// Créer le lock avec le PID
file_put_contents($lockFile, $pid);

// Logger le démarrage
file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Démarrage scraper (PID: $pid)\n", FILE_APPEND);

echo json_encode([
    'status' => 'started',
    'message' => 'Scraper lancé en arrière-plan',
    'pid' => $pid,
    'log_file' => basename($logFile)
]);
