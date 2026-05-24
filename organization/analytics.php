<?php
require __DIR__ . '/../dashboard/auth.php';
requireRole('organization');
require_once __DIR__ . '/../Config/db.php';
$activePage = 'analytics';

$pdo = db();
$monthly = $pdo->prepare(
    "SELECT DATE_FORMAT(d.created_at,'%Y-%m') AS period,
            COUNT(*) AS donations, COALESCE(SUM(d.amount),0) AS total
       FROM donations d
       JOIN campaigns c ON c.campaign_id=d.campaign_id
      WHERE c.org_id=? AND d.status='completed'
      GROUP BY DATE_FORMAT(d.created_at,'%Y-%m')
      ORDER BY period DESC LIMIT 12"
);
$monthly->execute([$userId]);
$monthlyData = $monthly->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>UMDC — Organization Analytics</title>
  <link rel="stylesheet" href="/assets/css/dashboard.css"/>
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main">
  <div class="page active">
    <div class="page-header"><h1>Analytics</h1><p>Donation trends for your campaigns.</p></div>
    <div class="table-card">
      <div class="table-card-header"><h3>Monthly Donations</h3></div>
      <div class="table-wrap"><table>
        <thead><tr><th>Month</th><th>Donations</th><th>Total Raised</th></tr></thead>
        <tbody>
        <?php if (empty($monthlyData)): ?>
          <tr><td colspan="3" class="muted" style="text-align:center;padding:24px">No data yet.</td></tr>
        <?php else: foreach ($monthlyData as $m): ?>
          <tr>
            <td><?= $m['period'] ?></td>
            <td><?= number_format($m['donations']) ?></td>
            <td>₱<?= number_format($m['total'],2) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</main>
</body>
</html>
