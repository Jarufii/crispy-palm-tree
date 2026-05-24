<?php
// public/login.php — Authentication handler
// Receives POST from login.html, validates credentials, redirects to the correct dashboard.

require_once __DIR__ . '/../Config/db.php';

// Simple session — no complex multi-session switching here
session_name('UMDC_SES');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
if (session_status() === PHP_SESSION_NONE) session_start();

// Already logged in — send to dashboard
if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    header('Location: ' . match($_SESSION['role']) {
        'admin'        => '/dashboard/admin.php',
        'organization' => '/dashboard/organization.php',
        default        => '/dashboard/user.php',
    });
    exit;
}

// GET request — just show the login page
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /public/login.html');
    exit;
}

$email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    header('Location: /public/login.html?error=invalid');
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT u.*, r.role_name
        FROM users u
        JOIN roles r ON u.role_id = r.role_id
        WHERE u.email = ?
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if (in_array($user['status'], ['banned', 'suspended'])) {
            header('Location: /public/login.html?error=suspended');
            exit;
        }

        $role = $user['role_name'];

        // Start a fresh role-specific session
        session_write_close();
        $sessName = match($role) {
            'admin'        => 'UMDC_ADMIN',
            'organization' => 'UMDC_ORG',
            default        => 'UMDC_USER',
        };
        session_name($sessName);
        session_set_cookie_params(['lifetime'=>0,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
        session_start();
        session_regenerate_id(true);

        $_SESSION['user_id']    = (int)$user['user_id'];
        $_SESSION['role']       = $role;
        $_SESSION['user_name']  = trim($user['first_name'] . ' ' . $user['last_name']);
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user']       = [
            'id'    => (int)$user['user_id'],
            'name'  => trim($user['first_name'] . ' ' . $user['last_name']),
            'email' => $user['email'],
            'role'  => $role,
        ];

        // Redirect to the correct dashboard
        $dest = match($role) {
            'admin'        => '/dashboard/admin.php',
            'organization' => '/dashboard/organization.php',
            default        => '/dashboard/user.php',
        };
        header("Location: $dest?_sess=$sessName");
        exit;

    } else {
        header('Location: /public/login.html?error=invalid');
        exit;
    }
} catch (PDOException $e) {
    error_log('Login error: ' . $e->getMessage());
    header('Location: /public/login.html?error=server');
    exit;
}
