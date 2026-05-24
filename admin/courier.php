<?php
$_GET['_sess'] = 'UMDC_ADMIN';
require __DIR__ . '/../dashboard/auth.php';
require_once __DIR__ . '/../Config/db.php';
$activePage = 'courier';

$pdo = db();
$active     = $pdo->query("SELECT COUNT(*) FROM deliveries WHERE status='in_transit'")->fetchColumn();
$delivered  = $pdo->query("SELECT COUNT(*) FROM deliveries WHERE status='delivered' AND MONTH(created_at)=MONTH(CURDATE())")->fetchColumn();
$pending    = $pdo->query("SELECT COUNT(*) FROM deliveries WHERE status='requested'")->fetchColumn();
$couriers   = $pdo->query("SELECT COUNT(*) FROM couriers WHERE active=1")->fetchColumn();

$rows = $pdo->query(
    "SELECT d.delivery_id, d.tracking_code, d.status, d.created_at,
            c.name AS courier_name,
            id.item_name, id.quantity
       FROM deliveries d
       LEFT JOIN couriers c ON c.courier_id=d.courier_id
       LEFT JOIN item_donations id ON id.item_id=d.item_id
      ORDER BY d.created_at DESC LIMIT 20"
)->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>UMDC — Courier</title><link rel="stylesheet" href="/assets/css/dashboard.css"/>
</head><body>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main"><div class="page active">
  <div class="page-header"><h1>Courier &amp; Delivery Dashboard</h1><p>Track donation shipments in real-time.</p></div>

  <div class="stat-grid">
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--blue-bg)"><svg width="20" height="20" fill="none" stroke="var(--blue)" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></div></div>
      <div class="stat-value"><?= $active ?></div><div class="stat-label">Active Shipments</div></div>
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--green-bg)"><svg width="20" height="20" fill="none" stroke="var(--green)" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div></div>
      <div class="stat-value"><?= $delivered ?></div><div class="stat-label">Delivered This Month</div></div>
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--yellow-bg)"><svg width="20" height="20" fill="none" stroke="var(--yellow)" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div>
      <div class="stat-value"><?= $pending ?></div><div class="stat-label">Pending Pickup</div></div>
    <div class="stat-card"><div class="stat-top"><div class="stat-icon" style="background:var(--purple-bg)"><svg width="20" height="20" fill="none" stroke="var(--purple)" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div></div>
      <div class="stat-value"><?= $couriers ?></div><div class="stat-label">Active Couriers</div></div>
  </div>

  <div class="table-card">
    <div class="table-card-header"><h3>Deliveries</h3></div>
    <div class="table-wrap"><table>
      <thead><tr><th>ID</th><th>Tracking</th><th>Item</th><th>Courier</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="6" class="muted" style="text-align:center;padding:24px">No deliveries on record.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td>#DLV<?= str_pad($r['delivery_id'],5,'0',STR_PAD_LEFT) ?></td>
          <td class="muted"><?= htmlspecialchars($r['tracking_code'] ?? '—') ?></td>
          <td class="muted"><?= htmlspecialchars($r['item_name'] ?? '—') ?><?= $r['quantity'] ? ' (×'.$r['quantity'].')' : '' ?></td>
          <td class="muted"><?= htmlspecialchars($r['courier_name'] ?? '—') ?></td>
          <td><?php $sc=match($r['status']){'delivered'=>'badge-green','in_transit'=>'badge-blue','requested'=>'badge-yellow','cancelled'=>'badge-red',default=>'badge-gray'};
              echo "<span class=\"badge $sc\">".ucfirst(str_replace('_',' ',$r['status']))."</span>"; ?></td>
          <td class="muted"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div>
  </div>
</div></main></body></html>
