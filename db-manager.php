<?php

/**
 * DATABASE MANAGER - UNIQUE SOURCE DE VÉRITÉ
 * 
 * Gestion de la création et initialisation des bases de données
 * Utilisé par auth.php ET api.php
 */

class DatabaseManager
{
    /**
     * Crée et initialise une base de données avec le schema complet
     * 
     * @param string $dbName Nom de la base à créer
     * @return bool Succès ou échec
     * @throws Exception En cas d'erreur
     */
    public static function createDatabase($dbName)
    {
        $logFile = BASE_PATH . '/logs/createdb_' . $dbName . '.log';
        @mkdir(BASE_PATH . '/logs', 0755, true);

        self::log($logFile, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        self::log($logFile, "🔵 CRÉATION BASE: $dbName");
        self::log($logFile, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        try {
            // Connexion root
            $pdoRoot = new PDO(
                "mysql:host=127.0.0.1",
                'root',
                'mysqlroot',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Vérifier si existe
            $result = $pdoRoot->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbName'");

            if ($result->rowCount() > 0) {
                self::log($logFile, "ℹ️  Base existe déjà, vérification des tables...");
            } else {
                self::log($logFile, "➕ Création nouvelle base...");
                $pdoRoot->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }

            $pdoRoot->exec("USE `$dbName`");
            self::log($logFile, "✓ Base sélectionnée");

            // Charger schema
            $schemaFile = BASE_PATH . '/schema_update.sql';
            if (!file_exists($schemaFile)) {
                throw new Exception("Schema SQL introuvable: $schemaFile");
            }

            $sql = file_get_contents($schemaFile);
            self::log($logFile, "✓ Schema chargé");

            // Nettoyer commentaires
            $sql = preg_replace('/--.*$/m', '', $sql);

            // Parser proprement les statements (gestion des quotes)
            $statements = self::parseSqlStatements($sql);
            self::log($logFile, "✓ " . count($statements) . " statements parsés");

            // Exécuter
            $success = 0;
            $skipped = 0;

            foreach ($statements as $i => $stmt) {
                try {
                    $pdoRoot->exec($stmt);
                    $success++;
                    self::log($logFile, "  ✓ Statement #$i OK");
                } catch (Exception $e) {
                    // Table existe déjà = OK
                    if (strpos($e->getMessage(), 'already exists') !== false) {
                        $skipped++;
                        self::log($logFile, "  ⏭ Statement #$i: existe déjà");
                    } else {
                        self::log($logFile, "  ⚠ Statement #$i: " . $e->getMessage());
                    }
                }
            }

            self::log($logFile, "");
            self::log($logFile, "✅ TERMINÉ");
            self::log($logFile, "   Success: $success");
            self::log($logFile, "   Skipped: $skipped");
            self::log($logFile, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            return true;
        } catch (Exception $e) {
            self::log($logFile, "❌ ERREUR: " . $e->getMessage());
            self::log($logFile, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            throw $e;
        }
    }

    /**
     * Parse les statements SQL en gérant correctement les quotes et backslashes
     * 
     * @param string $sql Le SQL brut
     * @return array Liste des statements
     */
    private static function parseSqlStatements($sql)
    {
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = '';

        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];

            // Détection entrée/sortie de string
            if (!$inString && ($char === '"' || $char === "'")) {
                $inString = true;
                $stringChar = $char;
            } elseif ($inString && $char === $stringChar && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $inString = false;
            }

            // Split sur ; uniquement hors des strings
            if (!$inString && $char === ';') {
                $stmt = trim($current);
                if (!empty($stmt)) {
                    $statements[] = $stmt;
                }
                $current = '';
            } else {
                $current .= $char;
            }
        }

        // Dernier statement si pas de ; final
        $last = trim($current);
        if (!empty($last)) {
            $statements[] = $last;
        }

        return $statements;
    }

    /**
     * Logger simple
     */
    private static function log($file, $message)
    {
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($file, "[$timestamp] $message\n", FILE_APPEND);
    }

    /**
     * Vérifie si une base existe et contient des tables
     * 
     * @param string $dbName
     * @return array ['exists' => bool, 'has_tables' => bool, 'table_count' => int]
     */
    public static function checkDatabase($dbName)
    {
        try {
            $pdo = new PDO(
                "mysql:host=127.0.0.1",
                'root',
                'mysqlroot',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $result = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbName'");

            if ($result->rowCount() === 0) {
                return ['exists' => false, 'has_tables' => false, 'table_count' => 0];
            }

            $result = $pdo->query("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$dbName'");
            $row = $result->fetch(PDO::FETCH_ASSOC);
            $tableCount = (int)$row['cnt'];

            return [
                'exists' => true,
                'has_tables' => $tableCount > 0,
                'table_count' => $tableCount
            ];
        } catch (Exception $e) {
            return ['exists' => false, 'has_tables' => false, 'table_count' => 0, 'error' => $e->getMessage()];
        }
    }
}
