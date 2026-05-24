<?php
$_GET['_sess'] = 'UMDC_ADMIN';
require __DIR__ . '/../dashboard/auth.php';
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf_token'];
require_once __DIR__ . '/../Config/db.php';
$activePage = 'organization';

$pdo = db();
$total   = $pdo->query("SELECT COUNT(*) FROM organizations")->fetchColumn();
$verified= $pdo->query("SELECT COUNT(*) FROM organizations WHERE verified=1")->fetchColumn();
$pending = $pdo->query("SELECT COUNT(*) FROM organizations WHERE verified=0")->fetchColumn();

// Handle verify
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['csrf_token']) || ($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        http_response_code(403); die('CSRF token mismatch');
    }
    $orgId  = (int)($_POST['org_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($orgId > 0 && $action === 'verify') {
        $pdo->prepare("UPDATE organizations SET verified=1 WHERE org_id=?")->execute([$orgId]);
        audit($userId, 'verify_organization', 'organization', $orgId);
    }
    header('Location: organization.php');
    exit;
}

$rows = $pdo->query(
    "SELECT o.org_id, o.org_name, o.org_email, o.verified, o.created_at,
            CONCAT(u.first_name,' ',u.last_name) AS owner
       FROM organizations o JOIN users u ON u.user_id=o.user_id
      ORDER BY o.created_at DESC"
)->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>UMDC — Organization</title><link rel="stylesheet" href="/assets/css/dashboard.css"/>
</head><body>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main"><div class="page active">
  <div class="page-header"><h1>Organization Dashboard</h1><p>Manage all registered organizations.</p></div>

  <?php if ($pending > 0): ?>
  <div class="alert alert-warning">
    <div class="alert-icon"><svg width="18" height="18" fill="none" stroke="#f59e0b" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
    <div class="alert-body"><strong><?= $pending ?> organization<?= $pending!=1?'s':'' ?> pending verification</strong></div>
  </div>
  <?php endif; ?>

  <div class="stat-grid">
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--blue-bg)"><svg width="20" height="20" fill="none" stroke="var(--blue)" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></div></div>
      <div class="stat-value"><?= $total ?></div><div class="stat-label">Total Organizations</div></div>
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--green-bg)"><svg width="20" height="20" fill="none" stroke="var(--green)" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div></div>
      <div class="stat-value"><?= $verified ?></div><div class="stat-label">Verified</div></div>
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--yellow-bg)"><svg width="20" height="20" fill="none" stroke="var(--yellow)" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div>
      <div class="stat-value"><?= $pending ?></div><div class="stat-label">Pending</div></div>
  </div>

  <div class="table-card">
    <div class="table-card-header"><h3>Registered Organizations</h3></div>
    <div class="table-wrap"><table>
      <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Owner</th><th>Joined</th><th>Verified</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="7" class="muted" style="text-align:center;padding:24px">No organizations registered yet.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td>#ORG<?= str_pad($r['org_id'],5,'0',STR_PAD_LEFT) ?></td>
          <td><?= htmlspecialchars($r['org_name']) ?></td>
          <td class="muted"><?= htmlspecialchars($r['org_email'] ?? '—') ?></td>
          <td class="muted"><?= htmlspecialchars($r['owner']) ?></td>
          <td class="muted"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
          <td><?= $r['verified'] ? '<span class="badge badge-green">Verified</span>' : '<span class="badge badge-yellow">Pending</span>' ?></td>
          <td><?php if (!$r['verified']): ?>
            <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="org_id" value="<?= $r['org_id'] ?>">
              <input type="hidden" name="action" value="verify">
              <button class="btn btn-green btn-sm" type="submit">Verify</button>
            </form>
          <?php else: echo '<span class="muted">—</span>'; endif; ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div>
  </div>
</div></main></body></html>
