<?php
// =============================================================
// public/campaigns.php
// =============================================================
defined('UMDC_APP') or define('UMDC_APP', true);

require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';

umdc_session_start();
setSecureHeaders();

$is_logged_in = isLoggedIn();
$user_role    = currentRole() ?? '';
$dash_link    = match($user_role) {
    'admin'        => '/dashboard/admin.php?_sess=UMDC_ADMIN',
    'organization' => '/dashboard/organization.php?_sess=UMDC_ORG',
    default        => '/dashboard/user.php?_sess=UMDC_USER',
};

// ── Query (original logic kept, made safe with prepared statement) ──
try {
    $stmt = $pdo->prepare("
        SELECT c.campaign_id, c.title, c.description, c.category,
               c.target_amount, COALESCE(c.current_amount, 0) AS current_amount,

               c.end_date, o.org_name, o.verified,
               (SELECT COUNT(*) FROM donations d
                WHERE d.campaign_id = c.campaign_id AND d.status = 'completed') AS donor_count
        FROM campaigns c
        JOIN organizations o ON c.org_id = o.org_id
        WHERE c.status = 'approved' OR c.status = 'active'
        ORDER BY c.current_amount DESC
        LIMIT 50
    ");
    $stmt->execute();
    $campaigns = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('campaigns.php: ' . $e->getMessage());
    $campaigns = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campaigns — UMDC</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/public.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<!-- ── Nav ── -->
<nav class="umdc-nav">
    <a class="nav-logo" href="/public/index.php">UMDC <span>Platform</span></a>
    <div class="nav-links">
        <a href="/public/campaigns.php" class="active">Campaigns</a>
        <a href="/public/transparency.php">Transparency</a>
        <?php if ($is_logged_in): ?>
            <a href="<?= e($dash_link) ?>" class="nav-cta">Dashboard</a>
        <?php else: ?>
            <a href="/public/login.php" class="nav-cta">Sign In</a>
        <?php endif; ?>
    </div>
</nav>

<main class="campaigns-page">
    <h1 class="page-title">Active Campaigns</h1>

    <?php if (empty($campaigns)): ?>
        <p class="empty-state">No active campaigns right now. Check back soon!</p>
    <?php else: ?>
    <div class="campaign-grid">
        <?php foreach ($campaigns as $c):
            $pct = $c['target_amount'] > 0
                ? min(100, round(($c['current_amount'] / $c['target_amount']) * 100)) : 0;
        ?>
        <div class="campaign-card">

            <div class="card-body">
                <h3><?= e($c['title']) ?></h3>
                <p class="card-org">
                    <?= e($c['org_name']) ?>
                    <?php if (!empty($c['verified'])): ?>
                        <i class="fa-solid fa-circle-check" style="color:#22d3a5" title="Verified"></i>
                    <?php endif; ?>
                </p>
                <p class="card-desc"><?= e($c['description']) ?></p>
                <p><strong>Goal:</strong> ₱<?= number_format($c['target_amount']) ?>
                   &nbsp;|&nbsp;
                   <strong>Raised:</strong> ₱<?= number_format($c['current_amount']) ?></p>
                <div class="progress-bar">
                    <div class="progress-fill" style="width:<?= $pct ?>%"></div>
                </div>
                <div class="card-stats">
                    <span><?= $pct ?>% funded</span>
                    <span><i class="fa-solid fa-users"></i> <?= (int)$c['donor_count'] ?> donors</span>
                    <?php if (!empty($c['end_date'])): ?>
                        <span><i class="fa-solid fa-calendar"></i> <?= e($c['end_date']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($is_logged_in && $user_role === 'user'): ?>
                    <a href="/donations/cash.php?campaign_id=<?= (int)$c['campaign_id'] ?>&_sess=UMDC_USER"
                       class="btn-donate">Donate Now</a>
                <?php elseif (!$is_logged_in): ?>
                    <a href="/public/login.php" class="btn-donate btn-outline">Sign In to Donate</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<script src="../assets/js/global.js"></script>
</body>
</html>
