<?php
require __DIR__ . '/../Config/db.php';
require __DIR__ . '/../Auth/middleware.php';
requireRole('user');

header('Content-Type: application/json');

try {
    // Get only active/approved campaigns
    $stmt = $pdo->prepare("
        SELECT campaign_id, title, description, target_amount, 
               COALESCE(current_amount, 0) as current_amount, image_url as image,
               created_at
        FROM campaigns 
        WHERE status IN ('approved', 'active')
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'campaigns' => $campaigns
    ]);
    
} catch (PDOException $e) {
    error_log("Campaigns API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Unable to fetch campaigns'
    ]);
}
?>