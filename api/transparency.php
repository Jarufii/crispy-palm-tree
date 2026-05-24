<?php
require __DIR__ . '/../Config/db.php';
require __DIR__ . '/../Auth/middleware.php';
requireRole('user');

header('Content-Type: application/json');

try {
    // Get total donations
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM donations WHERE status = 'completed'");
    $total_donations = $stmt->fetchColumn();
    
    // Get total donors (unique)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM donations WHERE status = 'completed'");
    $total_donors = $stmt->fetchColumn();
    
    // Get active campaigns
    $stmt = $pdo->query("SELECT COUNT(*) FROM campaigns WHERE status IN ('approved', 'active')");
    $active_campaigns = $stmt->fetchColumn();
    
    // Get successful campaigns (completed/cancelled with goal reached)
    $stmt = $pdo->query("SELECT COUNT(*) FROM campaigns WHERE status = 'completed' OR current_amount >= target_amount");
    $successful_campaigns = $stmt->fetchColumn();
    
    // Get recent transactions for public ledger
    $stmt = $pdo->prepare("
        SELECT l.public_hash, d.amount, d.created_at, c.title as campaign_title
        FROM ledgers l
        JOIN donations d ON l.donation_id = d.donation_id
        JOIN campaigns c ON d.campaign_id = c.campaign_id
        WHERE d.status = 'completed'
        ORDER BY d.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'total_donations' => $total_donations,
        'total_donors' => $total_donors,
        'active_campaigns' => $active_campaigns,
        'successful_campaigns' => $successful_campaigns,
        'transactions' => $transactions
    ]);
    
} catch (PDOException $e) {
    error_log("Transparency API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Unable to fetch transparency data'
    ]);
}
?>