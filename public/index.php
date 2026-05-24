<?php
// =============================================================
// public/index.php
// =============================================================
defined('UMDC_APP') or define('UMDC_APP', true);

require_once __DIR__ . '/../Config/security.php';
require_once __DIR__ . '/../Config/db.php';

// Dev error reporting — controlled by APP_ENV in keys.php/security
if (defined('APP_ENV') && APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

umdc_session_start();
setSecureHeaders();

// ── Route resolution ─────────────────────────────────────────
$request = strtok($_SERVER['REQUEST_URI'], '?');
$method  = $_SERVER['REQUEST_METHOD'];

$routes = [
    '/'                  => ['file' => null,            'action' => 'home'],
    '/public/campaigns'  => ['file' => 'campaigns.php', 'action' => 'include'],
    '/public/transparency'=> ['file' => 'transparency.php','action' => 'include'],
    '/auth/login'        => ['file' => '../Auth/login.php',    'action' => 'include'],
    '/auth/register'     => ['file' => '../Auth/register.php', 'action' => 'include'],
];

$matched = $routes[$request] ?? null;

if ($matched && $matched['action'] === 'include' && $matched['file']) {
    require __DIR__ . '/' . $matched['file'];
    exit;
}

// ── Home page (default route or '/') ─────────────────────────
$is_logged_in  = isLoggedIn();
$user_role     = currentRole() ?? '';
$user_name     = $is_logged_in ? e($_SESSION['user_name'] ?? '') : '';
$dash_link     = match($user_role) {
    'admin'        => '/dashboard/admin.php?_sess=UMDC_ADMIN',
    'organization' => '/dashboard/organization.php?_sess=UMDC_ORG',
    default        => '/dashboard/user.php?_sess=UMDC_USER',
};

// ── DB stats for home page ────────────────────────────────────
try {
    $stat_raised    = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status='completed'")->fetchColumn();
    $stat_campaigns = (int)$pdo->query("SELECT COUNT(*) FROM campaigns WHERE status IN ('active','approved')")->fetchColumn();
    $stat_donors    = (int)$pdo->query("SELECT COUNT(DISTINCT user_id) FROM donations WHERE status='completed'")->fetchColumn();

    $featured = $pdo->query("
        SELECT c.campaign_id, c.title, c.description, c.category,
               c.target_amount, COALESCE(c.current_amount,0) AS current_amount,
               o.org_name, o.verified,
               (SELECT COUNT(*) FROM donations d WHERE d.campaign_id=c.campaign_id AND d.status='completed') AS donor_count
        FROM campaigns c
        JOIN organizations o ON c.org_id = o.org_id
        WHERE c.status IN ('active','approved')
        ORDER BY c.current_amount DESC
        LIMIT 3
    ")->fetchAll();
} catch (Exception $e) {
    $stat_raised = $stat_campaigns = $stat_donors = 0;
    $featured = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UMDC — Unified Movement for Donation &amp; Charity</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/public.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<!-- ── Nav ────────────────────────────────────────────────── -->
<nav class="umdc-nav">
    <a class="nav-logo" href="/public/index.php">UMDC <span>Platform</span></a>
    <div class="nav-links">
        <a href="/public/campaigns.php">Campaigns</a>
        <a href="/public/transparency.php">Transparency</a>
        <?php if ($is_logged_in): ?>
            <a href="<?= e($dash_link) ?>" class="nav-cta">Dashboard</a>
        <?php else: ?>
            <a href="/public/login.php">Sign In</a>
            <a href="/public/register.php" class="nav-cta">Get Started</a>
        <?php endif; ?>
    </div>
</nav>

<!-- ── Hero ───────────────────────────────────────────────── -->
<section class="hero">
    <h1>Give More. Impact More.</h1>
    <p>UMDC connects donors with verified Philippine organizations. Every peso tracked with full transparency.</p>
    <?php if (!$is_logged_in): ?>
        <a href="/public/register.php" class="btn-primary">Start Donating</a>
        <a href="/public/campaigns.php" class="btn-outline">Browse Campaigns</a>
    <?php else: ?>
        <a href="<?= e($dash_link) ?>" class="btn-primary">Go to Dashboard</a>
        <a href="/public/campaigns.php" class="btn-outline">Browse Campaigns</a>
    <?php endif; ?>
</section>

<!-- ── Stats ──────────────────────────────────────────────── -->
<section class="stats-bar">
    <div class="stat-item">
        <span class="stat-num">₱<?= number_format($stat_raised) ?></span>
        <span class="stat-lbl">Total Raised</span>
    </div>
    <div class="stat-item">
        <span class="stat-num"><?= $stat_campaigns ?></span>
        <span class="stat-lbl">Active Campaigns</span>
    </div>
    <div class="stat-item">
        <span class="stat-num"><?= $stat_donors ?></span>
        <span class="stat-lbl">Donors</span>
    </div>
</section>

<!-- ── Featured Campaigns ─────────────────────────────────── -->
<?php if (!empty($featured)): ?>
<section class="featured-campaigns">
    <h2>Featured Campaigns</h2>
    <div class="campaign-grid">
        <?php foreach ($featured as $c):
            $pct = $c['target_amount'] > 0
                ? min(100, round(($c['current_amount'] / $c['target_amount']) * 100)) : 0;
        ?>
        <div class="campaign-card">

            <div class="card-body">
                <h3><?= e($c['title']) ?></h3>
                <p class="card-org"><?= e($c['org_name']) ?></p>
                <p class="card-desc"><?= e(mb_substr($c['description'], 0, 100)) ?>…</p>
                <div class="progress-bar">
                    <div class="progress-fill" style="width:<?= $pct ?>%"></div>
                </div>
                <div class="card-stats">
                    <span>₱<?= number_format($c['current_amount']) ?> raised</span>
                    <span><?= $pct ?>%</span>
                </div>
                <a href="/public/campaigns.php" class="btn-donate">View Campaign</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div style="text-align:center;margin-top:2rem">
        <a href="/public/campaigns.php" class="btn-outline">See All Campaigns →</a>
    </div>
</section>
<?php endif; ?>

<script src="../assets/js/global.js"></script>
</body>
</html>
