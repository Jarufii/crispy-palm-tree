<?php
$_GET['_sess'] = 'UMDC_ADMIN';
require __DIR__ . '/../dashboard/auth.php';
require_once __DIR__ . '/../Config/db.php';
$activePage = 'review';

$pdo = db();
$pending  = $pdo->query("SELECT COUNT(*) FROM verification_requests WHERE status='pending'")->fetchColumn();
$approved = $pdo->query("SELECT COUNT(*) FROM verification_requests WHERE status='approved'")->fetchColumn();
$rejected = $pdo->query("SELECT COUNT(*) FROM verification_requests WHERE status='rejected'")->fetchColumn();

// Handle review action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reqId  = (int)($_POST['req_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($reqId > 0 && in_array($action, ['approved','rejected'], true)) {
        $pdo->prepare("UPDATE verification_requests SET status=?, reviewed_by=? WHERE id=?")
            ->execute([$action, $userId, $reqId]);
        audit($userId, 'verification_'.$action, 'verification_request', $reqId);
    }
    header('Location: review.php');
    exit;
}

$rows = $pdo->query(
    "SELECT v.id, v.type, v.document_type, v.status, v.created_at,
            CONCAT(u.first_name,' ',u.last_name) AS user_name, u.email
       FROM verification_requests v
       JOIN users u ON u.user_id=v.user_id
      ORDER BY FIELD(v.status,'pending','approved','rejected'), v.created_at DESC"
)->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>UMDC — Review</title><link rel="stylesheet" href="/assets/css/dashboard.css"/>
</head><body>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main"><div class="page active">
  <div class="page-header"><h1>Verification Review</h1><p>Review user and organization verification requests.</p></div>

  <?php if ($pending > 0): ?>
  <div class="alert alert-warning">
    <div class="alert-icon"><svg width="18" height="18" fill="none" stroke="#f59e0b" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
    <div class="alert-body"><strong><?= $pending ?> request<?= $pending!=1?'s':'' ?> pending review</strong></div>
  </div>
  <?php endif; ?>

  <div class="stat-grid">
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--yellow-bg)"><svg width="20" height="20" fill="none" stroke="var(--yellow)" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div>
      <div class="stat-value"><?= $pending ?></div><div class="stat-label">Pending</div></div>
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--green-bg)"><svg width="20" height="20" fill="none" stroke="var(--green)" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div></div>
      <div class="stat-value"><?= $approved ?></div><div class="stat-label">Approved</div></div>
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--red-bg)"><svg width="20" height="20" fill="none" stroke="var(--red)" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div></div>
      <div class="stat-value"><?= $rejected ?></div><div class="stat-label">Rejected</div></div>
  </div>

  <div class="table-card">
    <div class="table-card-header"><h3>Verification Requests</h3></div>
    <div class="table-wrap"><table>
      <thead><tr><th>ID</th><th>Applicant</th><th>Email</th><th>Type</th><th>Document</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="8" class="muted" style="text-align:center;padding:24px">No verification requests.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td>#VR<?= str_pad($r['id'],5,'0',STR_PAD_LEFT) ?></td>
          <td><?= htmlspecialchars($r['user_name']) ?></td>
          <td class="muted"><?= htmlspecialchars($r['email']) ?></td>
          <td class="muted"><?= ucfirst($r['type']) ?></td>
          <td class="muted"><?= htmlspecialchars($r['document_type'] ?? '—') ?></td>
          <td class="muted"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
          <td><?php $sc=match($r['status']){'approved'=>'badge-green','rejected'=>'badge-red',default=>'badge-yellow'};
              echo "<span class=\"badge $sc\">".ucfirst($r['status'])."</span>"; ?></td>
          <td>
            <?php if ($r['status']==='pending'): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
              <input type="hidden" name="action" value="approved">
              <button class="btn btn-green btn-sm" type="submit">Approve</button>
            </form>
            <form method="POST" style="display:inline">
              <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
              <input type="hidden" name="action" value="rejected">
              <button class="btn btn-red btn-sm" type="submit">Reject</button>
            </form>
            <?php else: echo '<span class="muted">—</span>'; endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div>
  </div>
</div></main></body></html>
