<?php
// donations/success.php

require __DIR__ . '/../Config/db.php';
require __DIR__ . '/../Auth/middleware.php';
require __DIR__ . '/../services/PayMongoService.php';

requireRole('user');

$donation_id = $_GET['id'] ?? 0;

if (!$donation_id) {
    header('Location: /public/user.php');
    exit;
}

// Fetch donation with campaign and receipt info
$stmt = $pdo->prepare("
    SELECT d.*, c.title AS campaign_title, r.receipt_number
    FROM donations d
    JOIN campaigns c ON d.campaign_id = c.campaign_id
    LEFT JOIN receipts r ON d.donation_id = r.donation_id
    WHERE d.donation_id = ? AND d.user_id = ?
");
$stmt->execute([$donation_id, $_SESSION['user_id']]);
$donation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$donation) {
    header('Location: /public/user.php');
    exit;
}

// Check and settle a pending PayMongo payment
$paymongo        = new PayMongoService();
$paymentCompleted = false;

if ($donation['status'] === 'pending' && !empty($donation['payment_link_id'])) {
    error_log("Checking payment status for donation #{$donation_id}, link_id: {$donation['payment_link_id']}");

    $paymentLink = $paymongo->getPaymentLink($donation['payment_link_id']);

    error_log("Payment link status: " . ($paymentLink['success'] ? $paymentLink['status'] : 'Failed to retrieve'));

    if ($paymentLink['success'] && $paymentLink['status'] === 'paid') {
        // Mark donation completed
        $pdo->prepare("UPDATE donations SET status = 'completed', paid_at = NOW() WHERE donation_id = ?")
            ->execute([$donation_id]);

        // Generate receipt
        $receiptNumber = 'UMDC-' . date('Ymd') . '-' . str_pad($donation_id, 6, '0', STR_PAD_LEFT);
        $pdo->prepare("INSERT INTO receipts (donation_id, receipt_number, generated_at) VALUES (?, ?, NOW())")
            ->execute([$donation_id, $receiptNumber]);

        // Record in public ledger
        $hash = hash('sha256', $receiptNumber . $donation_id . time());
        $pdo->prepare("INSERT INTO ledgers (donation_id, public_hash, created_at) VALUES (?, ?, NOW())")
            ->execute([$donation_id, $hash]);

        // Update campaign running total
        $pdo->prepare("UPDATE campaigns SET current_amount = current_amount + ? WHERE campaign_id = ?")
            ->execute([$donation['amount'], $donation['campaign_id']]);

        // Refresh donation data
        $stmt = $pdo->prepare("
            SELECT d.*, c.title AS campaign_title, r.receipt_number
            FROM donations d
            JOIN campaigns c ON d.campaign_id = c.campaign_id
            LEFT JOIN receipts r ON d.donation_id = r.donation_id
            WHERE d.donation_id = ?
        ");
        $stmt->execute([$donation_id]);
        $donation = $stmt->fetch(PDO::FETCH_ASSOC);

        $paymentCompleted = true;
        error_log("Payment completed for donation #{$donation_id}");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation Status - UMDC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/donation-status.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card status-card">
                    <div class="card-body text-center p-5">

                        <?php if ($donation['status'] === 'completed'): ?>
                            <!-- SUCCESS STATE -->
                            <div class="success-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h2 class="card-title mb-3">Thank You for Your Donation!</h2>
                            <p class="card-text text-muted mb-4">
                                Your generosity makes a difference in someone's life.
                            </p>
                            <div class="alert alert-success mb-4">
                                <strong>Donation Successful!</strong><br>
                                Your donation has been processed successfully.
                            </div>
                            <div class="bg-light p-3 rounded mb-4 text-start">
                                <p class="mb-2"><strong>Campaign:</strong> <?php echo htmlspecialchars($donation['campaign_title']); ?></p>
                                <p class="mb-2"><strong>Amount:</strong> ₱<?php echo number_format($donation['amount'], 2); ?></p>
                                <p class="mb-2"><strong>Date:</strong> <?php echo date('F d, Y', strtotime($donation['paid_at'] ?? $donation['created_at'])); ?></p>
                                <?php if ($donation['receipt_number']): ?>
                                    <p class="mb-0"><strong>Receipt #:</strong>
                                        <span class="receipt-number"><?php echo htmlspecialchars($donation['receipt_number']); ?></span>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-3 justify-content-center">
                                <a href="/receipts/view.php?id=<?php echo $donation_id; ?>"
                                   class="btn-outline-custom" target="_blank">
                                    <i class="fas fa-receipt"></i> View Receipt
                                </a>
                                <a href="/public/user.php" class="btn-custom">
                                    <i class="fas fa-home"></i> Back to Dashboard
                                </a>
                            </div>

                        <?php elseif ($donation['status'] === 'pending'): ?>
                            <!-- PENDING STATE -->
                            <div class="pending-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h2 class="card-title mb-3">Waiting for Payment</h2>
                            <p class="card-text text-muted mb-4">
                                Please complete your payment to confirm your donation.
                            </p>
                            <div class="alert alert-info mb-4">
                                <i class="fas fa-info-circle"></i>
                                <strong>Payment Pending</strong><br>
                                Your donation has been created but not yet paid. Please complete payment using the link below.
                            </div>
                            <div class="bg-light p-3 rounded mb-4">
                                <p class="mb-2"><strong>Campaign:</strong> <?php echo htmlspecialchars($donation['campaign_title']); ?></p>
                                <p class="mb-2"><strong>Amount:</strong> ₱<?php echo number_format($donation['amount'], 2); ?></p>
                                <p class="mb-0"><strong>Donation ID:</strong> #<?php echo $donation_id; ?></p>
                            </div>
                            <div class="progress-timer">
                                <div class="progress-timer-bar"></div>
                            </div>
                            <div class="d-flex gap-3 justify-content-center flex-wrap">
                                <a href="<?php echo htmlspecialchars($donation['payment_link_url']); ?>"
                                   class="btn-custom" target="_blank">
                                    <i class="fas fa-credit-card"></i> Complete Payment
                                </a>
                                <button class="btn-outline-custom" onclick="checkPaymentStatus()" id="checkStatusBtn">
                                    <i class="fas fa-sync-alt"></i> Check Status
                                </button>
                            </div>
                            <p class="text-muted small mt-3">
                                <i class="fas fa-shield-alt"></i> Your payment is secure.
                                You'll be redirected back here after payment.
                            </p>

                        <?php elseif ($donation['status'] === 'failed'): ?>
                            <!-- FAILED STATE -->
                            <div class="failed-icon">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <h2 class="card-title mb-3">Payment Failed</h2>
                            <p class="card-text text-muted mb-4">
                                We couldn't process your donation at this time.
                            </p>
                            <div class="alert alert-danger mb-4">
                                <i class="fas fa-exclamation-triangle"></i>
                                Your payment was not completed. Please try again.
                            </div>
                            <div class="d-flex gap-3 justify-content-center">
                                <a href="/campaigns/view.php?id=<?php echo $donation['campaign_id']; ?>"
                                   class="btn-custom">
                                    <i class="fas fa-redo-alt"></i> Try Again
                                </a>
                                <a href="/public/user.php" class="btn-outline-custom">
                                    <i class="fas fa-home"></i> Dashboard
                                </a>
                            </div>
                        <?php endif; ?>

                        <hr class="my-4">
                        <p class="text-muted small mb-0">
                            <i class="fas fa-shield-alt"></i> Your donation is secure and transparent.<br>
                            Transaction ID: <?php echo htmlspecialchars($donation['payment_link_id'] ?? 'N/A'); ?>
                        </p>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php if ($donation['status'] === 'pending'): ?>
    <!-- Inject donation ID for the poller, then load it -->
    <script>window.DONATION_ID = <?php echo (int) $donation_id; ?>;</script>
    <script src="../assets/js/payment-status-poller.js"></script>
    <?php endif; ?>
</body>
</html>
