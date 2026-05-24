<?php
// api/donate.php

require __DIR__ . '/../Config/db.php';
require __DIR__ . '/../Auth/middleware.php';
require __DIR__ . '/../services/PayMongoService.php';

requireRole('user');

header('Content-Type: application/json');

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

// Validate required fields
if (!isset($_POST['campaign_id']) || !isset($_POST['donation_type'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$campaign_id = filter_var($_POST['campaign_id'], FILTER_VALIDATE_INT);
$donation_type = $_POST['donation_type'];
$user_id = $_SESSION['user_id'];

// Validate campaign exists and is active
$stmt = $pdo->prepare("SELECT campaign_id, title FROM campaigns WHERE campaign_id = ? AND status IN ('approved', 'active')");
$stmt->execute([$campaign_id]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign) {
    echo json_encode(['success' => false, 'message' => 'Campaign not found or not active']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Create donation record
    $stmt = $pdo->prepare("
        INSERT INTO donations (user_id, campaign_id, donation_type, status, created_at) 
        VALUES (?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$user_id, $campaign_id, $donation_type]);
    $donation_id = $pdo->lastInsertId();
    
    $response = ['success' => true, 'donation_id' => $donation_id];
    
    // Handle different donation types
    if ($donation_type === 'cash') {
        $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
        $payment_method = $_POST['payment_method'] ?? 'gcash';
        
        if (!$amount || $amount < 10) {
            throw new Exception('Invalid donation amount. Minimum donation is ₱10');
        }
        
        // Update donation with amount
        $stmt = $pdo->prepare("UPDATE donations SET amount = ?, payment_method = ? WHERE donation_id = ?");
        $stmt->execute([$amount, $payment_method, $donation_id]);
        
        // Create PayMongo payment link
        $paymongo = new PayMongoService();
        
        // Generate success and failed URLs
        $baseUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
        $successUrl = $baseUrl . "/donations/success.php?id=" . $donation_id;
        $failedUrl = $baseUrl . "/donations/failed.php?id=" . $donation_id;
        
        $paymentLink = $paymongo->createPaymentLink(
            $amount,
            "Donation to: " . $campaign['title'],
            $successUrl,
            $failedUrl,
            "UMDC-{$donation_id}",
            $payment_method
        );
        
        if (!$paymentLink['success']) {
            throw new Exception($paymentLink['message']);
        }
        
        // Save payment link info
        $stmt = $pdo->prepare("
            UPDATE donations 
            SET payment_link_id = ?, payment_link_url = ? 
            WHERE donation_id = ?
        ");
        $stmt->execute([
            $paymentLink['payment_link_id'],
            $paymentLink['checkout_url'],
            $donation_id
        ]);
        
        $response['checkout_url'] = $paymentLink['checkout_url'];
        $response['message'] = 'Redirecting to payment gateway';
        
    } elseif ($donation_type === 'item') {
        $item_name = trim($_POST['item_name']);
        $quantity = filter_var($_POST['quantity'], FILTER_VALIDATE_INT);
        $description = trim($_POST['item_description'] ?? '');
        
        if (empty($item_name)) {
            throw new Exception('Item name is required');
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO item_donations (donation_id, item_name, quantity, description, status) 
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$donation_id, $item_name, $quantity ?: 1, $description]);
        
        $response['message'] = 'Item donation recorded. Our team will contact you for pickup details.';
        
    } elseif ($donation_type === 'service') {
        $description = trim($_POST['service_description']);
        
        if (empty($description)) {
            throw new Exception('Service description is required');
        }
        
        $stmt = $pdo->prepare("
            UPDATE donations 
            SET service_description = ?, status = 'completed' 
            WHERE donation_id = ?
        ");
        $stmt->execute([$description, $donation_id]);
        
        // Generate receipt immediately for service donations
        require_once __DIR__ . '/../receipts/generate.php';
        if (function_exists('generateReceipt')) {
            generateReceipt($donation_id);
        }
        
        $response['message'] = 'Service donation recorded. Thank you for your support!';
    }
    
    // Log the donation
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, created_at) 
        VALUES (?, 'DONATION_CREATED', 'donation', ?, ?, NOW())
    ");
    $stmt->execute([$user_id, $donation_id, $_SERVER['REMOTE_ADDR']]);
    
    $pdo->commit();
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Donation Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}