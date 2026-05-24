<?php
$_GET['_sess'] = 'UMDC_ADMIN';
require __DIR__ . '/../dashboard/auth.php';
require_once __DIR__ . '/../Config/db.php';
$activePage = 'dashboard';

// Initialize with safe defaults in case DB is unavailable
$totalDonations = $approvedCount = $pendingCount = $activeDonors = $pendingAccounts = 0;
$recentDonations = [];
$dbError = null;

try {
    $pdo = db();

    $totalDonations   = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status='completed'")->fetchColumn();
    $approvedCount    = $pdo->query("SELECT COUNT(*) FROM donations WHERE status='completed'")->fetchColumn();
    $pendingCount     = $pdo->query("SELECT COUNT(*) FROM donations WHERE status='pending'")->fetchColumn();
    $activeDonors     = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM donations WHERE status='completed'")->fetchColumn();
    $pendingAccounts  = $pdo->query("SELECT COUNT(*) FROM users WHERE is_verified=0 AND status='active'")->fetchColumn();

    $recentDonations = $pdo->query(
        "SELECT d.donation_id, CONCAT(u.first_name,' ',u.last_name) AS donor,
                d.amount, d.status, d.created_at
           FROM donations d
           JOIN users u ON u.user_id = d.user_id
          ORDER BY d.created_at DESC LIMIT 6"
    )->fetchAll();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>UMDC — Dashboard</title>
  <link rel="stylesheet" href="/assets/css/dashboard.css" />
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main">
  <div class="page active">
    <div class="page-header">
      <h1>Welcome back, <?= htmlspecialchars(explode(' ', $userName)[0]) ?>!</h1>
      <p>Here's what's happening with your donation system today.</p>
    </div>

    <?php if (!empty($dbError)): ?>
    <div class="alert alert-danger">
      <div class="alert-body"><strong>Database error</strong><p><?= htmlspecialchars($dbError) ?></p></div>
    </div>
    <?php endif; ?>

    <?php if ($pendingCount > 0): ?>
    <div class="alert alert-warning">
      <div class="alert-icon"><svg width="18" height="18" fill="none" stroke="#f59e0b" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
      <div class="alert-body"><strong><?= $pendingCount ?> donation<?= $pendingCount != 1 ? 's' : '' ?> pending approval</strong><p>Waiting for review.</p></div>
      <a href="approvals.php" class="btn btn-primary btn-sm">View Approvals</a>
    </div>
    <?php endif; ?>

    <?php if ($pendingAccounts > 0): ?>
    <div class="alert alert-info" style="margin-top:-10px">
      <div class="alert-icon"><svg width="18" height="18" fill="none" stroke="#3b82f6" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
      <div class="alert-body"><strong><?= $pendingAccounts ?> account<?= $pendingAccounts != 1 ? 's' : '' ?> awaiting verification</strong><p>New registrations require approval.</p></div>
      <a href="users.php" class="btn btn-blue btn-sm">Review Accounts</a>
    </div>
    <?php endif; ?>

    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-top"><div class="stat-icon" style="background:var(--blue-bg)"><svg width="20" height="20" fill="none" stroke="var(--blue)" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div></div>
        <div class="stat-value">₱<?= number_format($totalDonations, 0) ?></div><div class="stat-label">Total Donations</div>
      </div>
      <div class="stat-card">
        <div class="stat-top"><div class="stat-icon" style="background:var(--green-bg)"><svg width="20" height="20" fill="none" stroke="var(--green)" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div></div>
        <div class="stat-value"><?= number_format($approvedCount) ?></div><div class="stat-label">Completed Donations</div>
      </div>
      <div class="stat-card">
        <div class="stat-top"><div class="stat-icon" style="background:var(--yellow-bg)"><svg width="20" height="20" fill="none" stroke="var(--yellow)" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div>
        <div class="stat-value"><?= number_format($pendingCount) ?></div><div class="stat-label">Pending Approvals</div>
      </div>
      <div class="stat-card">
        <div class="stat-top"><div class="stat-icon" style="background:var(--pink-bg)"><svg width="20" height="20" fill="none" stroke="var(--pink)" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></div></div>
        <div class="stat-value"><?= number_format($activeDonors) ?></div><div class="stat-label">Active Donors</div>
      </div>
    </div>

    <div class="table-card">
      <div class="table-card-header"><h3>Recent Donations</h3><a href="donations.php" class="btn btn-secondary btn-sm">View All</a></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Donation ID</th><th>Donor</th><th>Amount</th><th>Date</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (empty($recentDonations)): ?>
            <tr><td colspan="5" class="muted" style="text-align:center;padding:24px">No donations yet.</td></tr>
            <?php else: foreach ($recentDonations as $r): ?>
            <tr>
              <td>#DN<?= str_pad($r['donation_id'], 5, '0', STR_PAD_LEFT) ?></td>
              <td><?= htmlspecialchars($r['donor']) ?></td>
              <td>₱<?= number_format($r['amount'], 2) ?></td>
              <td class="muted"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
              <td><?php
                $s = $r['status'];
                $cls = match($s) { 'completed'=>'badge-green', 'pending'=>'badge-yellow', 'failed'=>'badge-red', default=>'badge-gray' };
                echo "<span class=\"badge $cls\">".ucfirst($s)."</span>";
              ?></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>
</body>
</html>
