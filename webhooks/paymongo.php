<?php
// webhooks/paymongo.php

require __DIR__ . '/../Config/db.php';
require __DIR__ . '/../services/PayMongoService.php';

// Get raw input
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

// Log webhook received
error_log("PayMongo Webhook Received");
error_log("Payload: " . $payload);

// Verify webhook signature
$paymongo = new PayMongoService();
$isValid = $paymongo->verifyWebhookSignature($payload, $signature);

if (!$isValid) {
    error_log("Invalid webhook signature");
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$data = json_decode($payload, true);

if ($data && isset($data['data'])) {
    $event = $data['data'];
    $type = $event['attributes']['type'] ?? '';
    
    error_log("Webhook Event Type: " . $type);
    
    switch ($type) {
        case 'payment.paid':
            handlePaymentPaid($event, $pdo);
            break;
            
        case 'payment.failed':
            handlePaymentFailed($event, $pdo);
            break;
            
        case 'link.payment_method_used':
            handlePaymentMethodUsed($event, $pdo);
            break;
            
        default:
            error_log("Unhandled webhook type: " . $type);
    }
}

// Always return 200 to acknowledge receipt
http_response_code(200);
echo json_encode(['success' => true]);

/**
 * Handle successful payment
 */
function handlePaymentPaid($event, $pdo) {
    $paymentId = $event['id'];
    $paymentData = $event['attributes'];
    $linkId = $paymentData['data']['attributes']['link_id'] ?? null;
    
    if (!$linkId) {
        error_log("No link_id found in payment.paid event");
        return;
    }
    
    error_log("Processing payment for link_id: " . $linkId);
    
    // Find donation by payment link ID
    $stmt = $pdo->prepare("
        SELECT donation_id, campaign_id, amount, user_id 
        FROM donations 
        WHERE payment_link_id = ? AND status = 'pending'
    ");
    $stmt->execute([$linkId]);
    $donation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($donation) {
        error_log("Found donation #{$donation['donation_id']} for payment");
        
        // Update donation status
        $stmt = $pdo->prepare("
            UPDATE donations 
            SET status = 'completed', paid_at = NOW(), payment_id = ? 
            WHERE donation_id = ?
        ");
        $stmt->execute([$paymentId, $donation['donation_id']]);
        
        // Update campaign current amount
        $stmt = $pdo->prepare("
            UPDATE campaigns 
            SET current_amount = current_amount + ? 
            WHERE campaign_id = ?
        ");
        $stmt->execute([$donation['amount'], $donation['campaign_id']]);
        
        // Generate receipt
        $receiptNumber = 'UMDC-' . date('Ymd') . '-' . str_pad($donation['donation_id'], 6, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("
            INSERT INTO receipts (donation_id, receipt_number, generated_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$donation['donation_id'], $receiptNumber]);
        
        // Add to public ledger
        $hash = hash('sha256', $receiptNumber . $donation['donation_id'] . time());
        $stmt = $pdo->prepare("
            INSERT INTO ledgers (donation_id, public_hash, created_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$donation['donation_id'], $hash]);
        
        // Log the transaction
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, created_at) 
            VALUES (?, 'PAYMENT_COMPLETED', 'donation', ?, 'webhook', NOW())
        ");
        $stmt->execute([$donation['user_id'], $donation['donation_id']]);
        
        error_log("Payment completed for donation #{$donation['donation_id']}");
        
        // Optional: Send email notification to user
        // sendDonationConfirmationEmail($donation['user_id'], $donation['donation_id'], $receiptNumber);
        
    } else {
        error_log("No pending donation found for link_id: " . $linkId);
    }
}

/**
 * Handle failed payment
 */
function handlePaymentFailed($event, $pdo) {
    $paymentData = $event['attributes'];
    $linkId = $paymentData['data']['attributes']['link_id'] ?? null;
    
    if ($linkId) {
        $stmt = $pdo->prepare("
            UPDATE donations 
            SET status = 'failed' 
            WHERE payment_link_id = ? AND status = 'pending'
        ");
        $stmt->execute([$linkId]);
        
        error_log("Payment failed for link_id: " . $linkId);
    }
}

/**
 * Handle payment method used (for analytics)
 */
function handlePaymentMethodUsed($event, $pdo) {
    $paymentData = $event['attributes'];
    $linkId = $paymentData['data']['attributes']['link_id'] ?? null;
    $paymentMethod = $paymentData['data']['attributes']['source']['type'] ?? null;
    
    if ($linkId && $paymentMethod) {
        $stmt = $pdo->prepare("
            UPDATE donations 
            SET payment_method_used = ? 
            WHERE payment_link_id = ?
        ");
        $stmt->execute([$paymentMethod, $linkId]);
        
        error_log("Payment method used: " . $paymentMethod . " for link_id: " . $linkId);
    }
}