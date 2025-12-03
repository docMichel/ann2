<?php

/**
 * Lance le scraper en arrière-plan pour l'utilisateur connecté
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth/auth.php';
Auth::requireAuth();

header('Content-Type: application/json');

$user = Auth::getCurrentUser();
$username = explode('@', $user['email'])[0];
$lockFile = BASE_PATH . '/locks/' . $username . '.lock';
$logFile = BASE_PATH . '/logs/' . $username . '_sync.log';

// Créer les dossiers si nécessaire
@mkdir(BASE_PATH . '/locks', 0755, true);
@mkdir(BASE_PATH . '/logs', 0755, true);

// Vider le log au début
file_put_contents($logFile, '');  // Vider le log

// Logger avec format uniforme
function logSync($msg)
{
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$ts][PHP] $msg\n", FILE_APPEND);
}

logSync("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
logSync("DEMANDE DE SCRAPING VIA WEB");
logSync("User: $username");
logSync("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");


// ========== VÉRIFIER SI UN SCRAPER TOURNE DÉJÀ ==========

if (file_exists($lockFile)) {
    $oldPid = trim(file_get_contents($lockFile));

    // Si le process existe encore, on le kill
    if (!empty($oldPid) && is_numeric($oldPid) && posix_getpgid($oldPid) !== false) {
        posix_kill($oldPid, SIGTERM);
        sleep(1); // Laisser le temps au process de mourir

        // Si toujours vivant, SIGKILL
        if (posix_getpgid($oldPid) !== false) {
            posix_kill($oldPid, SIGKILL);
        }

        logsync(" ⚠️  Ancien scraper (PID: $oldPid) arrêté\n", FILE_APPEND);
    }

    // Supprimer l'ancien lock
    unlink($lockFile);
}
// ========== CRÉER LA CONFIG TEMPORAIRE POUR LE SCRAPER ==========

$scraperConfig = [
    'email' => $user['email'],
    'password' => $user['annonces_password'],
    'db_name' => $user['db_name'],
    'apiUrl' => 'http://127.0.0.1' . dirname($_SERVER['PHP_SELF']) . '/api.php?action=save',
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
logSync("Config temp créée: $tempConfigFile");

// ========== LANCER LE SCRAPER VIA SCRIPT SHELL ==========

$launchScript = BASE_PATH . '/launch-scraper.sh';

// Vérifier que le script existe et est exécutable
if (!file_exists($launchScript)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Script launcher introuvable: ' . $launchScript
    ]);
    exit;
}

// Rendre exécutable si nécessaire
if (!is_executable($launchScript)) {
    chmod($launchScript, 0755);
}

// Logger le démarrage
logsync("[" . date('Y-m-d H:i:s') . "] ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n", FILE_APPEND);
logsync("[" . date('Y-m-d H:i:s') . "] 🔵 DEMANDE DE SCRAPING VIA WEB\n", FILE_APPEND);
logsync("[" . date('Y-m-d H:i:s') . "] User: $username\n", FILE_APPEND);
logsync("[" . date('Y-m-d H:i:s') . "] ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n", FILE_APPEND);

// Lancer le script shell
$cmd = sprintf(
    '%s %s %s %s 2>&1',
    escapeshellarg($launchScript),
    escapeshellarg($tempConfigFile),
    escapeshellarg($logFile),
    escapeshellarg($lockFile)
);

exec($cmd, $output, $returnCode);

// Lire le PID du lock file
$pid = file_exists($lockFile) ? trim(file_get_contents($lockFile)) : null;

if ($returnCode === 0 && $pid) {
    echo json_encode([
        'status' => 'started',
        'message' => 'Scraper lancé en arrière-plan',
        'pid' => $pid,
        'log_file' => basename($logFile),
        'output' => implode("\n", $output)
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Échec lancement scraper',
        'return_code' => $returnCode,
        'output' => implode("\n", $output)
    ]);
}
