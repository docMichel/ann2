<?php
if (php_sapi_name() !== 'cli') {
    die('Ce script doit être exécuté en ligne de commande.');
}

require_once __DIR__ . '/auth/auth.php';

$email = $argv[1] ?? null;

if (!$email) {
    echo "Usage: php approve-user.php email@example.com\n";
    exit(1);
}

$result = Auth::approveUser($email);

if ($result['success']) {
    echo "✅ Utilisateur approuvé avec succès\n";
    echo "Base de données: {$result['db_name']}\n";
    echo "\nL'utilisateur peut maintenant se connecter avec ses credentials annonces.nc\n";
} else {
    echo "❌ Erreur: {$result['error']}\n";
    exit(1);
}
