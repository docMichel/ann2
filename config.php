<?php
// Configuration de l'application
define('BASE_URL', '/ann2');  // Ton chemin (ou '' si à la racine)
define('BASE_PATH', __DIR__);
$tz = @file_get_contents('/etc/timezone') ?: trim(shell_exec('readlink /etc/localtime | sed "s|.*/zoneinfo/||"'));
if ($tz) date_default_timezone_set(trim($tz));
