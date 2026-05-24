<?php
require __DIR__ . '/../Config/db.php';
require __DIR__ . '/../Auth/middleware.php';
requireRole('user');

header('Content-Type: application/json');

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$user_id = $_SESSION['user_id'];

// Validate inputs
if (empty($first_name) || empty($last_name) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

try {
    // Check if email is already taken by another user
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $stmt->execute([$email, $user_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already in use']);
        exit;
    }
    
    // Update profile
    $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE user_id = ?");
    $stmt->execute([$first_name, $last_name, $email, $user_id]);
    
    // Update session name
    $_SESSION['user_name'] = $first_name . ' ' . $last_name;
    
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    
} catch (PDOException $e) {
    error_log("Update Profile Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}
?>