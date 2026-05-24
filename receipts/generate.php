<?php
function generateReceipt($donation_id) {
    global $pdo;
    
    $receipt_number = 'UMDC-' . date('Ymd') . '-' . str_pad($donation_id, 6, '0', STR_PAD_LEFT);
    
    // Insert receipt
    $stmt = $pdo->prepare("INSERT INTO receipts (donation_id, receipt_number, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$donation_id, $receipt_number]);
    
    // Add to public ledger
    $hash = hash('sha256', $receipt_number . $donation_id . time());
    $stmt = $pdo->prepare("INSERT INTO ledgers (donation_id, public_hash, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$donation_id, $hash]);
    
    // Update donation status if not already completed
    $stmt = $pdo->prepare("UPDATE donations SET status = 'completed' WHERE donation_id = ? AND status = 'pending'");
    $stmt->execute([$donation_id]);
    
    return ['receipt_number' => $receipt_number, 'hash' => $hash];
}
?>