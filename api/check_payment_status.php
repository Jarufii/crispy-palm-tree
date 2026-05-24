<?php
// api/check_payment_status.php

require __DIR__ . '/../Config/db.php';
require __DIR__ . '/../Auth/middleware.php';
require __DIR__ . '/../services/PayMongoService.php';

requireRole('user');

header('Content-Type: application/json');

$donation_id = $_GET['id'] ?? 0;

if (!$donation_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid donation ID']);
    exit;
}

// Verify ownership
$stmt = $pdo->prepare("SELECT user_id, status, payment_link_id, amount, campaign_id FROM donations WHERE donation_id = ?");
$stmt->execute([$donation_id]);
$donation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$donation || $donation['user_id'] != $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// If already completed
if ($donation['status'] === 'completed') {
    echo json_encode(['success' => true, 'status' => 'completed']);
    exit;
}

// If no payment link
if (empty($donation['payment_link_id'])) {
    echo json_encode(['success' => true, 'status' => $donation['status']]);
    exit;
}

// Check payment status with PayMongo
try {
    $paymongo = new PayMongoService();
    $paymentLink = $paymongo->getPaymentLink($donation['payment_link_id']);
    
    if ($paymentLink['success'] && $paymentLink['status'] === 'paid') {
        // Update donation status
        $stmt = $pdo->prepare("
            UPDATE donations 
            SET status = 'completed', paid_at = NOW() 
            WHERE donation_id = ?
        ");
        $stmt->execute([$donation_id]);
        
        // Generate receipt
        $receiptNumber = 'UMDC-' . date('Ymd') . '-' . str_pad($donation_id, 6, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("
            INSERT INTO receipts (donation_id, receipt_number, generated_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$donation_id, $receiptNumber]);
        
        // Add to public ledger
        $hash = hash('sha256', $receiptNumber . $donation_id . time());
        $stmt = $pdo->prepare("
            INSERT INTO ledgers (donation_id, public_hash, created_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$donation_id, $hash]);
        
        // Update campaign amount
        $stmt = $pdo->prepare("
            UPDATE campaigns 
            SET current_amount = current_amount + ? 
            WHERE campaign_id = ?
        ");
        $stmt->execute([$donation['amount'], $donation['campaign_id']]);
        
        echo json_encode(['success' => true, 'status' => 'completed']);
    } else {
        echo json_encode(['success' => true, 'status' => $donation['status']]);
    }
    
} catch (Exception $e) {
    error_log("Check Payment Status Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error checking payment status']);
}