<?php
$_GET['_sess'] = 'UMDC_ADMIN';
require __DIR__ . '/../dashboard/auth.php';
require_once __DIR__ . '/../Config/db.php';
$activePage = 'donations';

$pdo = db();
$total     = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status='completed'")->fetchColumn();
$approved  = $pdo->query("SELECT COUNT(*) FROM donations WHERE status='completed'")->fetchColumn();
$pending   = $pdo->query("SELECT COUNT(*) FROM donations WHERE status='pending'")->fetchColumn();
$donors    = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM donations")->fetchColumn();

$rows = $pdo->query(
    "SELECT d.donation_id, CONCAT(u.first_name,' ',u.last_name) AS donor,
            d.amount, d.donation_type, d.status, d.created_at
       FROM donations d JOIN users u ON u.user_id=d.user_id
      ORDER BY d.created_at DESC LIMIT 20"
)->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>UMDC — Donations</title><link rel="stylesheet" href="/assets/css/dashboard.css"/>
</head><body>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main"><div class="page active">
  <div class="page-header"><h1>Donation Dashboard</h1><p>Monitor and manage all donation activities in real-time.</p></div>

  <?php if ($pending > 0): ?>
  <div class="alert alert-warning">
    <div class="alert-icon"><svg width="18" height="18" fill="none" stroke="#f59e0b" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
    <div class="alert-body"><strong><?= $pending ?> donation<?= $pending!=1?'s':'' ?> pending approval</strong><p>Waiting for review.</p></div>
    <a href="approvals.php" class="btn btn-primary btn-sm">View Approvals</a>
  </div>
  <?php endif; ?>

  <div class="stat-grid">
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--blue-bg)"><svg width="20" height="20" fill="none" stroke="var(--blue)" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div></div>
      <div class="stat-value">₱<?= number_format($total,0) ?></div><div class="stat-label">Total Donations</div></div>
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--green-bg)"><svg width="20" height="20" fill="none" stroke="var(--green)" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div></div>
      <div class="stat-value"><?= $approved ?></div><div class="stat-label">Completed</div></div>
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--yellow-bg)"><svg width="20" height="20" fill="none" stroke="var(--yellow)" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div>
      <div class="stat-value"><?= $pending ?></div><div class="stat-label">Pending Approvals</div></div>
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--pink-bg)"><svg width="20" height="20" fill="none" stroke="var(--pink)" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></div></div>
      <div class="stat-value"><?= $donors ?></div><div class="stat-label">Total Donors</div></div>
  </div>

  <div class="table-card">
    <div class="table-card-header"><h3>All Donations</h3></div>
    <div class="table-wrap"><table>
      <thead><tr><th>ID</th><th>Donor</th><th>Amount</th><th>Type</th><th>Date</th><th>Status</th></tr></thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="6" class="muted" style="text-align:center;padding:24px">No donations found.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td>#DN<?= str_pad($r['donation_id'],5,'0',STR_PAD_LEFT) ?></td>
          <td><?= htmlspecialchars($r['donor']) ?></td>
          <td><?= $r['donation_type']==='cash' ? '₱'.number_format($r['amount'],2) : '—' ?></td>
          <td class="muted"><?= ucfirst($r['donation_type']) ?></td>
          <td class="muted"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
          <td><?php $cls=match($r['status']){'completed'=>'badge-green','pending'=>'badge-yellow','failed'=>'badge-red',default=>'badge-gray'};
              echo "<span class=\"badge $cls\">".ucfirst($r['status'])."</span>"; ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div>
  </div>
</div></main></body></html>
