<?php
/**
 * Auth/logout.php
 * Unified logout — opens the correct role session, logs the action, then destroys it.
 */
require_once __DIR__ . '/../Config/db.php';

// Open the correct session based on _sess param (same pattern as auth.php)
$_sessName = $_GET['_sess'] ?? '';
if (in_array($_sessName, ['UMDC_ADMIN', 'UMDC_ORG', 'UMDC_USER'], true)) {
    session_name($_sessName);
}
session_set_cookie_params(['lifetime'=>0,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
if (session_status() === PHP_SESSION_NONE) session_start();

// Audit log before destroying
if (!empty($_SESSION['user_id'])) {
    try {
        $pdo = db();
        $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type) VALUES (?, 'logout', 'user')")
            ->execute([$_SESSION['user_id']]);
    } catch (Throwable $e) {
        // Don't block logout on audit failure
    }
}

// Destroy session and clear cookie
$_SESSION = [];
$name = session_name();
$p    = session_get_cookie_params();
setcookie($name, '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
session_destroy();

header('Location: /public/login.html?logged_out=1');
exit;
