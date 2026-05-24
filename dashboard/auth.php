<?php
/**
 * dashboard/auth.php
 * Include at the top of every protected dashboard page.
 *
 * Supports two session formats:
 *   1. UMDC role sessions (UMDC_ADMIN / UMDC_ORG / UMDC_USER) set by Auth/login.php
 *   2. Legacy $_SESSION['user'] format (dashboard/login.php — now removed)
 *
 * On success, exposes: $userName, $userEmail, $userRole, $userId, $userInitials
 * On failure, redirects to /public/login.php
 */

// ── Open the correct role session ────────────────────────────────────────────
$_sessName = $_GET['_sess'] ?? '';
if (in_array($_sessName, ['UMDC_ADMIN', 'UMDC_ORG', 'UMDC_USER'], true)) {
    session_name($_sessName);
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Bridge UMDC session format → dashboard format ────────────────────────────
if (!isset($_SESSION['user']) && isset($_SESSION['user_id'], $_SESSION['role'])) {
    $_SESSION['user'] = [
        'id'    => (int)$_SESSION['user_id'],
        'name'  => $_SESSION['user_name']  ?? 'User',
        'email' => $_SESSION['user_email'] ?? '',
        'role'  => $_SESSION['role'],
    ];
}

// ── Guard: must be logged in ─────────────────────────────────────────────────
if (!isset($_SESSION['user'])) {
    header('Location: /public/login.html');
    exit;
}

// ── Prevent back-button cache ─────────────────────────────────────────────────
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// ── Expose session vars ───────────────────────────────────────────────────────
$_authUser    = $_SESSION['user'];
$userName     = htmlspecialchars($_authUser['name']  ?? '');
$userEmail    = htmlspecialchars($_authUser['email'] ?? '');
$userRole     = htmlspecialchars($_authUser['role']  ?? '');
$userId       = (int)($_authUser['id'] ?? 0);

$_nameParts   = explode(' ', trim($_authUser['name'] ?? 'U'));
$userInitials = strtoupper(substr($_nameParts[0], 0, 1) . substr(end($_nameParts), 0, 1));

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

/**
 * requireRole('admin') or requireRole(['admin','organization'])
 * Redirects non-matching roles to their own dashboard home.
 */
function requireRole(string|array $allowed): void {
    global $userRole;
    $allowed = (array) $allowed;
    if (!in_array($userRole, $allowed, true)) {
        $redirect = match($userRole) {
            'admin'        => '/admin/dashboard.php',
            'organization' => '/dashboard/organization.php',
            default        => '/dashboard/user.php',
        };
        header("Location: $redirect");
        exit;
    }
}
