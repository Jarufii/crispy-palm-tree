<?php
// donations/failed.php

require __DIR__ . '/../Config/db.php';
require __DIR__ . '/../Auth/middleware.php';

requireRole('user');

$donation_id = $_GET['id'] ?? 0;
$donation    = null;

if ($donation_id) {
    $stmt = $pdo->prepare("
        SELECT d.*, c.title AS campaign_title
        FROM donations d
        JOIN campaigns c ON d.campaign_id = c.campaign_id
        WHERE d.donation_id = ? AND d.user_id = ?
    ");
    $stmt->execute([$donation_id, $_SESSION['user_id']]);
    $donation = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation Failed - UMDC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/donation-status.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card failed-card">
                    <div class="card-body text-center p-5">

                        <div class="failed-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>

                        <h2 class="card-title mb-3">Payment Failed</h2>
                        <p class="card-text text-muted mb-4">
                            We couldn't process your donation at this time.
                        </p>

                        <div class="alert alert-danger mb-4">
                            <i class="fas fa-exclamation-triangle"></i>
                            Your payment was not completed. Please try again or use a different payment method.
                        </div>

                        <?php if ($donation): ?>
                            <div class="bg-light p-3 rounded mb-4">
                                <p class="mb-2"><strong>Campaign:</strong> <?php echo htmlspecialchars($donation['campaign_title']); ?></p>
                                <p class="mb-2"><strong>Amount:</strong> ₱<?php echo number_format($donation['amount'], 2); ?></p>
                                <p class="mb-0"><strong>Donation ID:</strong> #<?php echo $donation_id; ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex gap-3 justify-content-center">
                            <a href="/public/user.php" class="btn-custom">
                                <i class="fas fa-home"></i> Back to Dashboard
                            </a>
                            <?php if ($donation): ?>
                                <a href="/campaigns/view.php?id=<?php echo $donation['campaign_id']; ?>"
                                   class="btn-outline-custom">
                                    <i class="fas fa-redo-alt"></i> Try Again
                                </a>
                            <?php endif; ?>
                        </div>

                        <hr class="my-4">
                        <p class="text-muted small mb-0">
                            <i class="fas fa-life-ring"></i> Need help? Contact our support team.
                        </p>

                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
