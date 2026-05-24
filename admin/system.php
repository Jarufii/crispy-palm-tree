<?php
$_GET['_sess'] = 'UMDC_ADMIN';
require __DIR__ . '/../dashboard/auth.php';
require_once __DIR__ . '/../Config/db.php';
$activePage = 'system';

$pdo = db();
$totalDonations  = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status='completed'")->fetchColumn();
$approvedCount   = $pdo->query("SELECT COUNT(*) FROM donations WHERE status='completed'")->fetchColumn();
$pendingCount    = $pdo->query("SELECT COUNT(*) FROM donations WHERE status='pending'")->fetchColumn();
$activeSessions  = $pdo->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
$recentLogs      = $pdo->query(
    "SELECT a.action, a.entity_type, a.entity_id, a.created_at, a.ip_address,
            CONCAT(u.first_name,' ',u.last_name) AS actor
       FROM audit_logs a LEFT JOIN users u ON u.user_id=a.user_id
      ORDER BY a.created_at DESC LIMIT 10"
)->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>UMDC — System</title><link rel="stylesheet" href="/assets/css/dashboard.css"/>
</head><body>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main"><div class="page active">
  <div class="page-header"><h1>System Dashboard</h1><p>Monitor system health and audit logs.</p></div>

  <div class="stat-grid">
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--blue-bg)"><svg width="20" height="20" fill="none" stroke="var(--blue)" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div></div>
      <div class="stat-value">$<?= number_format($totalDonations,0) ?></div><div class="stat-label">Total Donations</div></div>
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--green-bg)"><svg width="20" height="20" fill="none" stroke="var(--green)" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div></div>
      <div class="stat-value"><?= $approvedCount ?></div><div class="stat-label">Completed</div></div>
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--yellow-bg)"><svg width="20" height="20" fill="none" stroke="var(--yellow)" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div>
      <div class="stat-value"><?= $pendingCount ?></div><div class="stat-label">Pending</div></div>
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--green-bg)"><svg width="20" height="20" fill="none" stroke="var(--green)" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></div><span class="health-tag"><span class="health-dot"></span>healthy</span></div>
      <div class="stat-value"><?= $activeSessions ?></div><div class="stat-label">Active Users</div></div>
  </div>

  <div class="table-card">
    <div class="table-card-header"><h3>Recent Audit Log</h3></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Action</th><th>Entity</th><th>Performed By</th><th>IP</th><th>Date</th></tr></thead>
      <tbody>
      <?php if (empty($recentLogs)): ?>
        <tr><td colspan="5" class="muted" style="text-align:center;padding:24px">No audit log entries yet.</td></tr>
      <?php else: foreach ($recentLogs as $r): ?>
        <tr>
          <td><?= htmlspecialchars(str_replace('_',' ',ucfirst($r['action']))) ?></td>
          <td class="muted"><?= $r['entity_type'] ? ucfirst($r['entity_type']).' #'.$r['entity_id'] : '—' ?></td>
          <td class="muted"><?= htmlspecialchars($r['actor'] ?? 'System') ?></td>
          <td class="muted"><?= htmlspecialchars($r['ip_address'] ?? '—') ?></td>
          <td class="muted"><?= date('M j, Y H:i', strtotime($r['created_at'])) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div>
  </div>
</div></main></body></html>
