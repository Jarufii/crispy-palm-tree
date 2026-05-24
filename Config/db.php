<?php
// Config/db.php — Database connection + helpers

defined('UMDC_APP') or define('UMDC_APP', true);

$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'umdc';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(503);
    die('Database unavailable. Please try again later.');
}

/**
 * db() — returns the shared PDO instance.
 * Used by dashboard pages that call db() instead of $pdo directly.
 */
function db(): PDO {
    global $pdo;
    return $pdo;
}

/**
 * audit() — write an entry to audit_logs.
 */
function audit(int $userId, string $action, string $entityType = '', int $entityId = 0): void {
    try {
        db()->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $userId, $action, $entityType ?: null, $entityId ?: null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable) {}
}

/**
 * auditLog() — alias used by Auth/ files.
 */
function auditLog(PDO $pdo, ?int $userId, string $action, string $entityType = '', ?int $entityId = null): void {
    try {
        $pdo->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $userId, $action, $entityType ?: null, $entityId,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable) {}
}
