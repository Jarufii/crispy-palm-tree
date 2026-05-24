<?php
require __DIR__ . '/../Config/db.php';
require __DIR__ . '/../Auth/middleware.php';
requireRole('user');

header('Content-Type: application/json');

try {
    $user_id = $_SESSION['user_id'];
    
    // Get user info
    $stmt = $pdo->prepare("SELECT first_name, last_name, email, created_at FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get donation statistics
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(amount), 0) as total_donated,
            COUNT(*) as donation_count,
            COUNT(DISTINCT campaign_id) as campaigns_supported,
            MAX(created_at) as last_donation
        FROM donations 
        WHERE user_id = ? AND status = 'completed'
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'user' => $user,
        'stats' => $stats
    ]);
    
} catch (PDOException $e) {
    error_log("Profile API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Unable to fetch profile data'
    ]);
}
?>