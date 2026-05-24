<?php
$_GET['_sess'] = 'UMDC_ADMIN';
require __DIR__ . '/../dashboard/auth.php';
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf_token'];
require_once __DIR__ . '/../Config/db.php';
$activePage = 'users';

$pdo = db();

// Handle status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['csrf_token']) || ($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        http_response_code(403); die('CSRF token mismatch');
    }
    $uid    = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($uid > 0 && in_array($action, ['suspend','activate','verify'], true)) {
        if ($action === 'verify') {
            $pdo->prepare("UPDATE users SET is_verified=1 WHERE user_id=?")->execute([$uid]);
        } else {
            $newStatus = $action === 'suspend' ? 'suspended' : 'active';
            $pdo->prepare("UPDATE users SET status=? WHERE user_id=?")->execute([$newStatus, $uid]);
        }
        audit($userId, $action.'_user', 'user', $uid);
    }
    header('Location: users.php');
    exit;
}

$total   = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$active  = $pdo->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
$newToday= $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$unverified = $pdo->query("SELECT COUNT(*) FROM users WHERE is_verified=0")->fetchColumn();

$rows = $pdo->query(
    "SELECT u.user_id, u.first_name, u.last_name, u.email, u.status,
            u.is_verified, u.created_at, r.role_name
       FROM users u JOIN roles r ON r.role_id=u.role_id
      ORDER BY u.created_at DESC"
)->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>UMDC — Users</title><link rel="stylesheet" href="/assets/css/dashboard.css"/>
</head><body>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main"><div class="page active">
  <div class="page-header"><h1>Users Management</h1><p>Manage all registered users and their access.</p></div>

  <?php if ($unverified > 0): ?>
  <div class="alert alert-info">
    <div class="alert-icon"><svg width="18" height="18" fill="none" stroke="#3b82f6" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
    <div class="alert-body"><strong><?= $unverified ?> unverified user<?= $unverified!=1?'s':'' ?></strong><p>Review and verify new accounts below.</p></div>
  </div>
  <?php endif; ?>

  <div class="stat-grid">
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--blue-bg)"><svg width="20" height="20" fill="none" stroke="var(--blue)" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div></div>
      <div class="stat-value"><?= $total ?></div><div class="stat-label">Total Users</div></div>
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--green-bg)"><svg width="20" height="20" fill="none" stroke="var(--green)" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div></div>
      <div class="stat-value"><?= $active ?></div><div class="stat-label">Active Users</div></div>
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--yellow-bg)"><svg width="20" height="20" fill="none" stroke="var(--yellow)" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg></div></div>
      <div class="stat-value"><?= $newToday ?></div><div class="stat-label">New Today</div></div>
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--pink-bg)"><svg width="20" height="20" fill="none" stroke="var(--pink)" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div></div>
      <div class="stat-value"><?= $unverified ?></div><div class="stat-label">Unverified</div></div>
  </div>

  <div class="table-card">
    <div class="table-card-header"><h3>All Users</h3></div>
    <div class="table-wrap"><table>
      <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th>Verified</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td>#USR<?= str_pad($r['user_id'],5,'0',STR_PAD_LEFT) ?></td>
          <td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
          <td class="muted"><?= htmlspecialchars($r['email']) ?></td>
          <td class="muted"><?= ucfirst($r['role_name']) ?></td>
          <td class="muted"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
          <td><?= $r['is_verified'] ? '<span class="badge badge-green">Yes</span>' : '<span class="badge badge-yellow">No</span>' ?></td>
          <td><?php $sc=match($r['status']){'active'=>'badge-green','suspended'=>'badge-yellow',default=>'badge-red'};
              echo "<span class=\"badge $sc\">".ucfirst($r['status'])."</span>"; ?></td>
          <td>
            <?php if (!$r['is_verified']): ?>
            <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="user_id" value="<?= $r['user_id'] ?>">
              <input type="hidden" name="action" value="verify">
              <button class="btn btn-blue btn-sm" type="submit">Verify</button>
            </form>
            <?php endif; ?>
            <?php if ($r['status'] === 'active'): ?>
            <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="user_id" value="<?= $r['user_id'] ?>">
              <input type="hidden" name="action" value="suspend">
              <button class="btn btn-red btn-sm" type="submit">Suspend</button>
            </form>
            <?php else: ?>
            <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="user_id" value="<?= $r['user_id'] ?>">
              <input type="hidden" name="action" value="activate">
              <button class="btn btn-green btn-sm" type="submit">Activate</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div></main></body></html>
