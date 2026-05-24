<?php
$_GET['_sess'] = 'UMDC_ADMIN';

require __DIR__ . '/auth.php';
require_once __DIR__ . '/../Config/db.php';
$activePage = 'admin';
// Restrict admin page to admin role only
if ($userRole !== 'admin') {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>UMDC — Admin</title>
  <link rel="stylesheet" href="/assets/css/dashboard.css" />
</head>
<body>
<?php include __DIR__ . '/admin_sidebar.php'; ?>
<main class="main">
  <div class="page active">
    <div class="page-header">
      <h1>Admin Control Panel</h1>
      <p>System-level settings and privileged administrative actions.</p>
    </div>

    <div class="alert alert-info">
      <div class="alert-icon"><svg width="18" height="18" fill="none" stroke="#3b82f6" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
      <div class="alert-body"><strong>Administrator access</strong><p>This area is restricted to administrators only. All actions are logged.</p></div>
    </div>

<?php
    // Live DB stats for admin panel
    $adminStats = ['total_users'=>0,'total_orgs'=>0,'total_admins'=>0];
    try {
        $pdo = db();
        $adminStats['total_users']  = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $adminStats['total_orgs']   = (int)$pdo->query("SELECT COUNT(*) FROM organizations")->fetchColumn();
        $adminStats['total_admins'] = (int)$pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.role_id=u.role_id WHERE r.role_name='admin'")->fetchColumn();
    } catch (Throwable $e) {}
?>
    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-top"><div class="stat-icon" style="background:var(--blue-bg)"><svg width="20" height="20" fill="none" stroke="var(--blue)" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div></div>
        <div class="stat-value"><?= number_format($adminStats['total_users']) ?></div><div class="stat-label">Total Users</div>
      </div>
      <div class="stat-card">
        <div class="stat-top"><div class="stat-icon" style="background:var(--green-bg)"><svg width="20" height="20" fill="none" stroke="var(--green)" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></div></div>
        <div class="stat-value"><?= number_format($adminStats['total_orgs']) ?></div><div class="stat-label">Organizations</div>
      </div>
      <div class="stat-card">
        <div class="stat-top"><div class="stat-icon" style="background:var(--yellow-bg)"><svg width="20" height="20" fill="none" stroke="var(--yellow)" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div></div>
        <div class="stat-value"><?= $adminStats['total_admins'] ?></div><div class="stat-label">Admin Accounts</div>
      </div>
      <div class="stat-card">
        <div class="stat-top"><div class="stat-icon" style="background:var(--pink-bg)"><svg width="20" height="20" fill="none" stroke="var(--pink)" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg></div></div>
        <div class="stat-value">Optimal</div><div class="stat-label">System Health</div>
      </div>
    </div>

    <div class="table-card">
      <div class="table-card-header"><h3>Admin Actions Log</h3></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Action</th><th>Performed By</th><th>Target</th><th>Date</th></tr></thead>
          <tbody>
          <?php
          $auditRows = [];
          try {
              $auditRows = $pdo->query("
                  SELECT a.action, a.entity_type, a.entity_id, a.created_at,
                         CONCAT(u.first_name,' ',u.last_name) AS performed_by
                    FROM audit_logs a
                    LEFT JOIN users u ON u.user_id = a.user_id
                   ORDER BY a.created_at DESC LIMIT 20
              ")->fetchAll();
          } catch (Throwable $e) {}
          if (empty($auditRows)): ?>
            <tr><td colspan="4" class="muted" style="text-align:center;padding:24px">No audit log entries yet.</td></tr>
          <?php else: foreach ($auditRows as $al): ?>
            <tr>
              <td><?= htmlspecialchars(ucwords(str_replace('_',' ',$al['action']))) ?></td>
              <td><?= htmlspecialchars($al['performed_by'] ?? 'System') ?></td>
              <td class="muted"><?= $al['entity_type'] ? ucfirst($al['entity_type']).' #'.$al['entity_id'] : '—' ?></td>
              <td class="muted"><?= date('M j, Y H:i', strtotime($al['created_at'])) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>
</body>
</html>
