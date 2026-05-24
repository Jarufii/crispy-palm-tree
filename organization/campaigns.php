<?php
require __DIR__ . '/../dashboard/auth.php';
requireRole('organization');
require_once __DIR__ . '/../Config/db.php';
$activePage = 'campaigns';

$pdo = db();
$rows = $pdo->prepare("SELECT c.campaign_id, c.title, c.target_amount AS goal_amount, c.current_amount, c.status, c.created_at FROM campaigns c WHERE c.org_id=? ORDER BY c.created_at DESC");
$rows->execute([$userId]);
$campaigns = $rows->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>UMDC — My Campaigns</title>
  <link rel="stylesheet" href="/assets/css/dashboard.css"/>
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main">
  <div class="page active">
    <div class="page-header">
      <h1>My Campaigns</h1>
      <p>All campaigns created by your organization.</p>
      <a href="/campaigns/create.php" class="btn btn-primary">+ New Campaign</a>
    </div>
    <div class="table-card">
      <div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Title</th><th>Goal</th><th>Raised</th><th>Status</th><th>Created</th></tr></thead>
        <tbody>
        <?php if (empty($campaigns)): ?>
          <tr><td colspan="6" class="muted" style="text-align:center;padding:24px">No campaigns yet.</td></tr>
        <?php else: foreach ($campaigns as $c): ?>
          <tr>
            <td>#<?= str_pad($c['campaign_id'],5,'0',STR_PAD_LEFT) ?></td>
            <td><?= htmlspecialchars($c['title']) ?></td>
            <td>₱<?= number_format($c['goal_amount'],0) ?></td>
            <td>₱<?= number_format($c['current_amount'],0) ?></td>
            <td><span class="badge badge-<?= $c['status']==='active'?'green':($c['status']==='pending'?'yellow':'gray') ?>"><?= ucfirst($c['status']) ?></span></td>
            <td class="muted"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</main>
</body>
</html>
