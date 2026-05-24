<?php
$_GET['_sess'] = 'UMDC_ADMIN';
require __DIR__ . '/../dashboard/auth.php';
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf_token'];
require_once __DIR__ . '/../Config/db.php';
$activePage = 'frauds';

$pdo = db();

// Handle resolve
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['csrf_token']) || ($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        http_response_code(403); die('CSRF token mismatch');
    }
    $flagId = (int)($_POST['flag_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($flagId > 0 && in_array($action, ['reviewed','resolved'], true)) {
        $pdo->prepare("UPDATE fraud_flags SET status=? WHERE flag_id=?")->execute([$action, $flagId]);
        audit($userId, 'fraud_flag_'.$action, 'fraud_flag', $flagId);
    }
    header('Location: frauds.php');
    exit;
}

$flagged  = $pdo->query("SELECT COUNT(*) FROM fraud_flags WHERE status='open'")->fetchColumn();
$reviewed = $pdo->query("SELECT COUNT(*) FROM fraud_flags WHERE status='reviewed'")->fetchColumn();
$resolved = $pdo->query("SELECT COUNT(*) FROM fraud_flags WHERE status='resolved'")->fetchColumn();
$total    = $pdo->query("SELECT COUNT(*) FROM fraud_flags")->fetchColumn();

$rows = $pdo->query(
    "SELECT f.flag_id, f.entity_type, f.entity_id, f.reason, f.status, f.created_at,
            CONCAT(u.first_name,' ',u.last_name) AS flagged_by_name
       FROM fraud_flags f
       LEFT JOIN users u ON u.user_id=f.flagged_by
      ORDER BY FIELD(f.status,'open','reviewed','resolved'), f.created_at DESC"
)->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>UMDC — Frauds</title><link rel="stylesheet" href="/assets/css/dashboard.css"/>
</head><body>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main"><div class="page active">
  <div class="page-header"><h1>Fraud Detection Dashboard</h1><p>Real-time monitoring of suspicious activities.</p></div>

  <?php if ($flagged > 0): ?>
  <div class="alert alert-danger">
    <div class="alert-icon"><svg width="18" height="18" fill="none" stroke="var(--red)" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
    <div class="alert-body"><strong><?= $flagged ?> open fraud flag<?= $flagged!=1?'s':'' ?></strong><p>Immediate review required.</p></div>
  </div>
  <?php endif; ?>

  <div class="stat-grid">
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--red-bg)"><svg width="20" height="20" fill="none" stroke="var(--red)" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/></svg></div></div>
      <div class="stat-value"><?= $total ?></div><div class="stat-label">Total Flags</div></div>
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--yellow-bg)"><svg width="20" height="20" fill="none" stroke="var(--yellow)" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg></div></div>
      <div class="stat-value"><?= $flagged ?></div><div class="stat-label">Open</div></div>
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--blue-bg)"><svg width="20" height="20" fill="none" stroke="var(--blue)" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div></div>
      <div class="stat-value"><?= $reviewed ?></div><div class="stat-label">Under Review</div></div>
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--green-bg)"><svg width="20" height="20" fill="none" stroke="var(--green)" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div></div>
      <div class="stat-value"><?= $resolved ?></div><div class="stat-label">Resolved</div></div>
  </div>

  <div class="table-card">
    <div class="table-card-header"><h3>Fraud Flags</h3></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Flag ID</th><th>Entity</th><th>Reason</th><th>Flagged By</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="7" class="muted" style="text-align:center;padding:24px">No fraud flags on record.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td>#FF<?= str_pad($r['flag_id'],5,'0',STR_PAD_LEFT) ?></td>
          <td class="muted"><?= ucfirst($r['entity_type']) ?> #<?= $r['entity_id'] ?></td>
          <td class="muted"><?= htmlspecialchars($r['reason'] ?? '—') ?></td>
          <td class="muted"><?= htmlspecialchars($r['flagged_by_name'] ?? 'System') ?></td>
          <td class="muted"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
          <td><?php $sc=match($r['status']){'open'=>'badge-red','reviewed'=>'badge-yellow','resolved'=>'badge-green',default=>'badge-gray'};
              echo "<span class=\"badge $sc\">".ucfirst($r['status'])."</span>"; ?></td>
          <td>
            <?php if ($r['status']==='open'): ?>
            <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="flag_id" value="<?= $r['flag_id'] ?>">
              <input type="hidden" name="action" value="reviewed">
              <button class="btn btn-blue btn-sm" type="submit">Review</button>
            </form>
            <?php elseif ($r['status']==='reviewed'): ?>
            <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="flag_id" value="<?= $r['flag_id'] ?>">
              <input type="hidden" name="action" value="resolved">
              <button class="btn btn-green btn-sm" type="submit">Resolve</button>
            </form>
            <?php else: echo '<span class="muted">—</span>'; endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div>
  </div>
</div></main></body></html>
