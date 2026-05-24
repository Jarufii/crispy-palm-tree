<?php
// =============================================================
// public/transparency.php
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

// ── Query (original logic kept, made safe) ────────────────────
try {
    $stmt = $pdo->query("
        SELECT c.title, c.category,
               SUM(d.amount)        AS total,
               COUNT(d.donation_id) AS donor_count
        FROM campaigns c
        JOIN donations d ON c.campaign_id = d.campaign_id
        WHERE d.status = 'completed'
        GROUP BY c.campaign_id
        ORDER BY total DESC
    ");
    $rows = $stmt->fetchAll();

    $grand_total = (float)$pdo->query(
        "SELECT COALESCE(SUM(amount),0) FROM donations WHERE status='completed'"
    )->fetchColumn();
} catch (PDOException $e) {
    error_log('transparency.php: ' . $e->getMessage());
    $rows = [];
    $grand_total = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transparency — UMDC</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/public.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .grand-total {
            background: linear-gradient(135deg, #6C72FF, #22d3a5);
            color: #fff;
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        .grand-total .amount { font-size: 2.5rem; font-weight: 800; }
        .grand-total .label  { opacity: .85; }
        .transparency-table  { width: 100%; border-collapse: collapse; }
        .transparency-table th {
            background: #f5f5f5; padding: .75rem 1rem;
            text-align: left; font-size: .8rem;
            text-transform: uppercase; letter-spacing: .05em;
        }
        .transparency-table td { padding: .75rem 1rem; border-bottom: 1px solid #eee; }
        .transparency-table tr:last-child td { border-bottom: none; }
    </style>
</head>
<body>

<!-- ── Nav ── -->
<nav class="umdc-nav">
    <a class="nav-logo" href="/public/index.php">UMDC <span>Platform</span></a>
    <div class="nav-links">
        <a href="/public/campaigns.php">Campaigns</a>
        <a href="/public/transparency.php" class="active">Transparency</a>
        <?php if ($is_logged_in): ?>
            <a href="<?= e($dash_link) ?>" class="nav-cta">Dashboard</a>
        <?php else: ?>
            <a href="/public/login.php" class="nav-cta">Sign In</a>
        <?php endif; ?>
    </div>
</nav>

<main class="campaigns-page">
    <h1 class="page-title">Transparency Ledger</h1>
    <p style="color:#666;margin-bottom:1.5rem">Every donation is publicly recorded. No personal data is exposed.</p>

    <div class="grand-total">
        <div class="amount">₱<?= number_format($grand_total, 2) ?></div>
        <div class="label">Total Verified Donations</div>
    </div>

    <?php if (empty($rows)): ?>
        <p class="empty-state">No completed donations on record yet.</p>
    <?php else: ?>
        <table class="transparency-table">
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Category</th>
                    <th>Total Donations</th>
                    <th>Donors</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e($row['title']) ?></td>
                    <td><?= e($row['category'] ?? '—') ?></td>
                    <td><strong>₱<?= number_format($row['total'], 2) ?></strong></td>
                    <td><?= (int)$row['donor_count'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</main>

<script src="../assets/js/global.js"></script>
</body>
</html>
