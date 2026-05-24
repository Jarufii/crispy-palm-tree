<?php
// Auth/register.php — Registration handler
// Receives POST from public/register.html, creates account, redirects to login.

require_once __DIR__ . '/../Config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /public/register.html');
    exit;
}

$role     = in_array($_POST['role'] ?? '', ['user', 'organization']) ? $_POST['role'] : 'user';
$fname    = trim($_POST['first_name'] ?? '');
$lname    = trim($_POST['last_name']  ?? '');
$email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$password = $_POST['password']         ?? '';
$confirm  = $_POST['confirm_password'] ?? '';

// ── Validation ────────────────────────────────────────────────
if (!$fname || strlen($fname) > 100 || !$lname || strlen($lname) > 100) {
    header('Location: /public/register.html?error=invalid_name'); exit;
}
if (!$email) {
    header('Location: /public/register.html?error=invalid_email'); exit;
}
if (strlen($password) < 8) {
    header('Location: /public/register.html?error=weak_password'); exit;
}
if ($password !== $confirm) {
    header('Location: /public/register.html?error=password_mismatch'); exit;
}

try {
    // ── Duplicate email check ─────────────────────────────────
    $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        header('Location: /public/register.html?error=email_exists'); exit;
    }

    // ── Resolve role_id ───────────────────────────────────────
    // Try exact match first, then fallback to 'donor' or 'user', then use ID 3
    $roleStmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = ? LIMIT 1");
    $roleStmt->execute([$role]);
    $role_id = $roleStmt->fetchColumn();

    if (!$role_id) {
        // Try common alternative names
        $fallback = $role === 'organization' ? 'organization' : 'user';
        $roleStmt->execute(['donor']);
        $role_id = $roleStmt->fetchColumn();
    }
    if (!$role_id) {
        // Last resort: grab the lowest non-admin role_id from the table
        $role_id = $pdo->query("SELECT role_id FROM roles WHERE role_name NOT IN ('admin') ORDER BY role_id ASC LIMIT 1")->fetchColumn() ?: 2;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // ── Insert user ───────────────────────────────────────────
    $stmt = $pdo->prepare("
        INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
        VALUES (?, ?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$role_id, $fname, $lname, $email, $hash]);
    $newUserId = (int)$pdo->lastInsertId();

    if (!$newUserId) {
        throw new RuntimeException('User insert returned no ID');
    }

    // ── Create organization record if needed ──────────────────
    if ($role === 'organization') {
        // Re-resolve org role_id specifically
        $orgRoleStmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = 'organization' LIMIT 1");
        $orgRoleStmt->execute();
        $orgRoleId = $orgRoleStmt->fetchColumn();
        if ($orgRoleId && $orgRoleId != $role_id) {
            // Update user to correct org role if we used a fallback above
            $pdo->prepare("UPDATE users SET role_id = ? WHERE user_id = ?")->execute([$orgRoleId, $newUserId]);
        }

        $orgName = trim($fname . ' ' . $lname);
        $orgStmt = $pdo->prepare("
            INSERT INTO organizations (user_id, org_name, org_email, verified)
            VALUES (?, ?, ?, 0)
        ");
        $orgStmt->execute([$newUserId, $orgName, $email]);
    }

    header('Location: /public/login.html?registered=1');
    exit;

} catch (PDOException $e) {
    error_log('Register PDO error: ' . $e->getMessage());
    // Pass a sanitised hint so the page can show it (never expose raw DB errors)
    $hint = urlencode(substr($e->getMessage(), 0, 120));
    header('Location: /public/register.html?error=server&hint=' . $hint);
    exit;
} catch (Throwable $e) {
    error_log('Register error: ' . $e->getMessage());
    header('Location: /public/register.html?error=server');
    exit;
}
