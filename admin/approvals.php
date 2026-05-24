<?php
$_GET['_sess'] = 'UMDC_ADMIN';
require __DIR__ . '/../dashboard/auth.php';
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf_token'];
require_once __DIR__ . '/../Config/db.php';
$activePage = 'approvals';

$pdo = db();

// Handle approve / reject POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['csrf_token']) || ($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        http_response_code(403); die('CSRF token mismatch');
    }
    $action     = $_POST['action']      ?? '';
    $donationId = (int)($_POST['donation_id'] ?? 0);
    if ($donationId > 0 && in_array($action, ['approve','reject'], true)) {
        $newStatus = $action === 'approve' ? 'completed' : 'failed';
        $pdo->prepare("UPDATE donations SET status=? WHERE donation_id=?")->execute([$newStatus, $donationId]);
        // Log approval record
        $pdo->prepare("INSERT INTO approvals (entity_type, entity_id, admin_id, status) VALUES ('donation',?,?,?)")
            ->execute([$donationId, $userId, $action === 'approve' ? 'approved' : 'rejected']);
        audit($userId, $action.'_donation', 'donation', $donationId);
    }
    header('Location: approvals.php');
    exit;
}

$pending   = $pdo->query("SELECT COUNT(*) FROM donations WHERE status='pending'")->fetchColumn();
$approvedToday = $pdo->query("SELECT COUNT(*) FROM donations WHERE status='completed' AND DATE(created_at)=CURDATE()")->fetchColumn();
$totalApproved = $pdo->query("SELECT COUNT(*) FROM donations WHERE status='completed'")->fetchColumn();
$rejected  = $pdo->query("SELECT COUNT(*) FROM donations WHERE status='failed'")->fetchColumn();

$rows = $pdo->query(
    "SELECT d.donation_id, CONCAT(u.first_name,' ',u.last_name) AS donor,
            u.email, d.amount, d.donation_type, d.created_at
       FROM donations d JOIN users u ON u.user_id=d.user_id
      WHERE d.status='pending'
      ORDER BY d.created_at ASC"
)->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>UMDC — Approvals</title><link rel="stylesheet" href="/assets/css/dashboard.css"/>
</head><body>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main"><div class="page active">
  <div class="page-header"><h1>Donation Approval Dashboard</h1><p>Review and approve pending donation requests.</p></div>

  <div class="stat-grid">
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--yellow-bg)"><svg width="20" height="20" fill="none" stroke="var(--yellow)" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div>
      <div class="stat-value"><?= $pending ?></div><div class="stat-label">Pending Approvals</div></div>
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--green-bg)"><svg width="20" height="20" fill="none" stroke="var(--green)" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div></div>
      <div class="stat-value"><?= $approvedToday ?></div><div class="stat-label">Approved Today</div></div>
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--blue-bg)"><svg width="20" height="20" fill="none" stroke="var(--blue)" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div></div>
      <div class="stat-value"><?= $totalApproved ?></div><div class="stat-label">Total Approved</div></div>
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--red-bg)"><svg width="20" height="20" fill="none" stroke="var(--red)" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div></div>
      <div class="stat-value"><?= $rejected ?></div><div class="stat-label">Rejected</div></div>
  </div>

  <div class="table-card">
    <div class="table-card-header"><div><h3>Pending Donations</h3><p>Approve or reject each donation below.</p></div></div>
    <div class="table-wrap"><table>
      <thead><tr><th>ID</th><th>Donor</th><th>Amount</th><th>Type</th><th>Submitted</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="6" class="muted" style="text-align:center;padding:24px">No pending donations. 🎉</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td>#DN<?= str_pad($r['donation_id'],5,'0',STR_PAD_LEFT) ?></td>
          <td><div class="user-cell"><?= htmlspecialchars($r['donor']) ?><small><?= htmlspecialchars($r['email']) ?></small></div></td>
          <td><?= $r['donation_type']==='cash' ? '₱'.number_format($r['amount'],2) : '—' ?></td>
          <td class="muted"><?= ucfirst($r['donation_type']) ?></td>
          <td class="muted"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
          <td>
            <div class="approval-actions">
              <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="donation_id" value="<?= $r['donation_id'] ?>">
                <input type="hidden" name="action" value="approve">
                <button type="submit" class="btn btn-green btn-sm">✓ Approve</button>
              </form>
              <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="donation_id" value="<?= $r['donation_id'] ?>">
                <input type="hidden" name="action" value="reject">
                <button type="submit" class="btn btn-red btn-sm">✗ Reject</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div>
  </div>
</div></main></body></html>
