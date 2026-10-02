<?php
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        // Keep MySQL's NOW()/CURRENT_TIMESTAMP in Nairobi time, matching PHP's timezone.
        try { $pdo->exec("SET time_zone = '+03:00'"); } catch (Throwable $e) { error_log('db time zone: ' . $e->getMessage()); }
    }
    return $pdo;
}
