<?php
/**
 * config/db.php — Connexion PDO unique (singleton).
 *
 * Toute l'application passe par db() pour obtenir la connexion.
 * Les erreurs PDO ne sont JAMAIS renvoyées au client en production.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST, DB_PORT, DB_NAME
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // vraies requêtes préparées
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]);
    } catch (PDOException $e) {
        // En prod : journaliser, ne pas afficher le détail.
        if (IS_PROD) {
            error_log('[DB] Connexion échouée : ' . $e->getMessage());
            http_response_code(500);
            die('Service temporairement indisponible.');
        }
        die('Connexion base de données impossible : ' . htmlspecialchars($e->getMessage()));
    }

    return $pdo;
}
