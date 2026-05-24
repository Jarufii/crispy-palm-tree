<?php
require __DIR__ . '/../dashboard/auth.php';
requireRole('organization');
require_once __DIR__ . '/../Config/db.php';
$activePage = 'donations';

$pdo = db();
$rows = $pdo->prepare(
    "SELECT d.donation_id, CONCAT(u.first_name,' ',u.last_name) AS donor,
            c.title AS campaign, d.amount, d.donation_type, d.status, d.created_at
       FROM donations d
       JOIN campaigns c ON c.campaign_id=d.campaign_id
       JOIN users u ON u.user_id=d.user_id
      WHERE c.org_id=?
      ORDER BY d.created_at DESC"
);
$rows->execute([$userId]);
$donations = $rows->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>UMDC — Donations Received</title>
  <link rel="stylesheet" href="/assets/css/dashboard.css"/>
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main">
  <div class="page active">
    <div class="page-header"><h1>Donations Received</h1><p>All donations made to your campaigns.</p></div>
    <div class="table-card">
      <div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Donor</th><th>Campaign</th><th>Amount</th><th>Type</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        <?php if (empty($donations)): ?>
          <tr><td colspan="7" class="muted" style="text-align:center;padding:24px">No donations yet.</td></tr>
        <?php else: foreach ($donations as $d): ?>
          <tr>
            <td>#DN<?= str_pad($d['donation_id'],5,'0',STR_PAD_LEFT) ?></td>
            <td><?= htmlspecialchars($d['donor']) ?></td>
            <td><?= htmlspecialchars($d['campaign']) ?></td>
            <td>₱<?= number_format($d['amount'],2) ?></td>
            <td><?= ucfirst($d['donation_type']) ?></td>
            <td><span class="badge badge-<?= $d['status']==='completed'?'green':($d['status']==='pending'?'yellow':'red') ?>"><?= ucfirst($d['status']) ?></span></td>
            <td class="muted"><?= date('M j, Y', strtotime($d['created_at'])) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</main>
</body>
</html>
