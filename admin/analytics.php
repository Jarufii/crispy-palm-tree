<?php
$_GET['_sess'] = 'UMDC_ADMIN';
require __DIR__ . '/../dashboard/auth.php';
require_once __DIR__ . '/../Config/db.php';
$activePage = 'analytics';

$pdo = db();
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status='completed'")->fetchColumn();
$totalDonors  = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM donations")->fetchColumn();
$avgDonation  = $pdo->query("SELECT COALESCE(AVG(amount),0) FROM donations WHERE status='completed' AND donation_type='cash'")->fetchColumn();

// Monthly totals for current year
$monthly = $pdo->query(
    "SELECT MONTH(created_at) AS mo, COALESCE(SUM(amount),0) AS total, COUNT(*) AS cnt
       FROM donations WHERE status='completed' AND YEAR(created_at)=YEAR(CURDATE())
      GROUP BY MONTH(created_at)"
)->fetchAll(PDO::FETCH_KEY_PAIR);   // mo => total (fetchAll gives arrays, remap below)

$monthly = $pdo->query(
    "SELECT MONTH(created_at) AS mo, COALESCE(SUM(amount),0) AS total
       FROM donations WHERE status='completed' AND YEAR(created_at)=YEAR(CURDATE())
      GROUP BY mo ORDER BY mo"
)->fetchAll();

$topDonors = $pdo->query(
    "SELECT CONCAT(u.first_name,' ',u.last_name) AS donor,
            SUM(d.amount) AS total, COUNT(*) AS cnt,
            AVG(d.amount) AS avg_amt
       FROM donations d JOIN users u ON u.user_id=d.user_id
      WHERE d.status='completed'
      GROUP BY d.user_id ORDER BY total DESC LIMIT 5"
)->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>UMDC — Analytics</title><link rel="stylesheet" href="/assets/css/dashboard.css"/>
</head><body>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main"><div class="page active">
  <div class="page-header"><h1>Donation Analytics</h1><p>Comprehensive insights from live data.</p></div>

  <div class="stat-grid">
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--blue-bg)"><svg width="20" height="20" fill="none" stroke="var(--blue)" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div></div>
      <div class="stat-value">$<?= number_format($totalRevenue,0) ?></div><div class="stat-label">Total Revenue</div></div>
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--blue-bg)"><svg width="20" height="20" fill="none" stroke="var(--blue)" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div></div>
      <div class="stat-value"><?= $totalDonors ?></div><div class="stat-label">Total Donors</div></div>
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--green-bg)"><svg width="20" height="20" fill="none" stroke="var(--green)" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div></div>
      <div class="stat-value">$<?= number_format($avgDonation,0) ?></div><div class="stat-label">Avg Donation</div></div>
  </div>

  <?php if (!empty($monthly)): ?>
  <div class="table-card" style="margin-bottom:22px">
    <div class="table-card-header"><h3>Monthly Revenue (<?= date('Y') ?>)</h3></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Month</th><th>Total Collected</th></tr></thead>
      <tbody>
      <?php
      $months = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      foreach ($monthly as $m):
      ?>
        <tr><td><?= $months[(int)$m['mo']] ?></td><td>$<?= number_format($m['total'],2) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <?php endif; ?>

  <div class="table-card">
    <div class="table-card-header"><h3>Top Donors</h3></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Donor</th><th>Total Donated</th><th># Donations</th><th>Avg Donation</th></tr></thead>
      <tbody>
      <?php if (empty($topDonors)): ?>
        <tr><td colspan="4" class="muted" style="text-align:center;padding:24px">No completed donations yet.</td></tr>
      <?php else: foreach ($topDonors as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['donor']) ?></td>
          <td>$<?= number_format($r['total'],2) ?></td>
          <td class="muted"><?= $r['cnt'] ?></td>
          <td class="muted">$<?= number_format($r['avg_amt'],2) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div>
  </div>
</div></main></body></html>
