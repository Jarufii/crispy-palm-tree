<?php
require __DIR__ . '/../Config/db.php';
require __DIR__ . '/../Auth/middleware.php';
requireRole('user');

header('Content-Type: application/json');

try {
    $user_id = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("
        SELECT d.donation_id, d.donation_type, d.amount, d.status, d.created_at,
               c.title as campaign_title,
               i.item_name, i.quantity,
               d.service_description,
               r.receipt_number
        FROM donations d
        LEFT JOIN campaigns c ON d.campaign_id = c.campaign_id
        LEFT JOIN item_donations i ON d.donation_id = i.donation_id
        LEFT JOIN receipts r ON d.donation_id = r.donation_id
        WHERE d.user_id = ?
        ORDER BY d.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'donations' => $donations
    ]);
    
} catch (PDOException $e) {
    error_log("My Donations Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Unable to fetch donations'
    ]);
}
?>