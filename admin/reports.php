<?php
$_GET['_sess'] = 'UMDC_ADMIN';
require __DIR__ . '/../dashboard/auth.php';
require_once __DIR__ . '/../Config/db.php';
$activePage = 'reports';

$pdo = db();

// Monthly summary report
$monthly = $pdo->query(
    "SELECT DATE_FORMAT(created_at,'%Y-%m') AS period,
            COUNT(*) AS count,
            COALESCE(SUM(amount),0) AS total,
            SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN status='pending'   THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status='failed'    THEN 1 ELSE 0 END) AS failed
       FROM donations
      GROUP BY period ORDER BY period DESC LIMIT 12"
)->fetchAll();

$typeBreakdown = $pdo->query(
    "SELECT donation_type, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total
       FROM donations WHERE status='completed'
      GROUP BY donation_type"
)->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>UMDC — Reports</title><link rel="stylesheet" href="/assets/css/dashboard.css"/>
</head><body>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main"><div class="page active">
  <div class="page-header"><h1>Reports</h1><p>Summary reports generated from live data.</p></div>

  <div class="table-card" style="margin-bottom:22px">
    <div class="table-card-header"><h3>Monthly Donation Summary</h3></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Period</th><th>Total Donations</th><th>Total Amount</th><th>Completed</th><th>Pending</th><th>Failed</th></tr></thead>
      <tbody>
      <?php if (empty($monthly)): ?>
        <tr><td colspan="6" class="muted" style="text-align:center;padding:24px">No data yet.</td></tr>
      <?php else: foreach ($monthly as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['period']) ?></td>
          <td><?= $r['count'] ?></td>
          <td>$<?= number_format($r['total'],2) ?></td>
          <td><span class="badge badge-green"><?= $r['completed'] ?></span></td>
          <td><span class="badge badge-yellow"><?= $r['pending'] ?></span></td>
          <td><span class="badge badge-red"><?= $r['failed'] ?></span></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div>
  </div>

  <div class="table-card">
    <div class="table-card-header"><h3>Donation Type Breakdown</h3></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Type</th><th>Count</th><th>Total Amount</th></tr></thead>
      <tbody>
      <?php if (empty($typeBreakdown)): ?>
        <tr><td colspan="3" class="muted" style="text-align:center;padding:24px">No completed donations yet.</td></tr>
      <?php else: foreach ($typeBreakdown as $r): ?>
        <tr>
          <td><?= ucfirst($r['donation_type']) ?></td>
          <td><?= $r['cnt'] ?></td>
          <td><?= $r['donation_type']==='cash' ? '$'.number_format($r['total'],2) : '—' ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div>
  </div>
</div></main></body></html>
