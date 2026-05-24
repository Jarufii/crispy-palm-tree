<?php
// Support both the dashboard session ($_SESSION['user']) and the public UMDC_USER session.
// This allows users who log in via the public login page to reach their dashboard.
$_sessName = $_GET['_sess'] ?? '';
if (in_array($_sessName, ['UMDC_USER','UMDC_ORG'], true)) {
    session_name($_sessName);
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user'])) {
    // Try bridging from UMDC session format to dashboard format
    if (isset($_SESSION['user_id'], $_SESSION['role'])) {
        $_SESSION['user'] = [
            'id'    => (int)$_SESSION['user_id'],
            'name'  => $_SESSION['user_name']  ?? 'User',
            'email' => $_SESSION['user_email'] ?? '',
            'role'  => $_SESSION['role'],
        ];
    } else {
        header('Location: /public/login.php');
        exit;
    }
}
// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$userName  = htmlspecialchars($_SESSION['user']['name'] ?? 'User');
$userRole  = htmlspecialchars($_SESSION['user']['role'] ?? 'User');
$userInitials = strtoupper(substr($userName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UMDC — User Dashboard</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css" />
    <style>
      .campaigns-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(280px,1fr)); gap:16px; margin-top:16px; }
      .campaign-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:20px; }
      .campaign-card h4 { font-size:14px; font-weight:600; margin-bottom:6px; }
      .campaign-card p  { font-size:12.5px; color:var(--text-secondary); margin-bottom:12px; }
      .progress-bar-wrap { background:rgba(255,255,255,0.07); border-radius:99px; height:6px; margin-bottom:8px; }
      .progress-bar-fill { background:var(--accent); border-radius:99px; height:6px; transition:width 0.4s; }
      .progress-label { font-size:11.5px; color:var(--text-muted); margin-bottom:12px; }
      .modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:400; align-items:center; justify-content:center; }
      .modal-backdrop.open { display:flex; }
      .form-label { display:block; font-size:12.5px; font-weight:600; color:var(--text-secondary); margin-bottom:6px; }
      .form-control { width:100%; background:var(--bg-base); border:1px solid var(--border); border-radius:var(--radius-sm); color:var(--text-primary); font-family:var(--font-sans); font-size:14px; padding:9px 12px; margin-bottom:14px; }
      .form-control:focus { outline:none; border-color:var(--accent); }
      select.form-control { cursor:pointer; }
      .check-row { display:flex; gap:8px; align-items:flex-start; font-size:12.5px; color:var(--text-secondary); margin-bottom:16px; }
      .check-row input { margin-top:2px; flex-shrink:0; }
    </style>
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-logo"><div class="logo-icon">UM</div><div class="logo-text"><strong>UMDC</strong><span>User Portal</span></div></div>
  <nav>
    <div class="nav-section">
      <div class="nav-label">My Account</div>
      <a href="#" class="nav-item active" onclick="showSection('campaigns')">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>Campaigns
      </a>
      <a href="#" class="nav-item" onclick="showSection('my-donations')">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>My Donations
      </a>
    </div>
  </nav>
  <div class="sidebar-footer">
    <div class="avatar-sm"><?= $userInitials ?></div>
    <div class="user-info"><strong><?= $userName ?></strong><span><?= $userRole ?></span></div>
    <a href="/dashboard/logout.php" class="logout-btn" title="Logout"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></a>
  </div>
</aside>

<main class="main">
  <!-- Campaigns section -->
  <div id="section-campaigns" class="page active">
    <div class="page-header">
      <h1>Active Campaigns</h1>
      <p>Browse and donate to active campaigns.</p>
    </div>
    <div class="campaigns-grid" id="campaign-list">
      <!-- Populated by JS -->
    </div>
  </div>

  <!-- My Donations section -->
  <div id="section-my-donations" class="page">
    <div class="page-header">
      <h1>My Donations</h1>
      <p>Your donation history.</p>
    </div>
    <div class="table-card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>ID</th><th>Campaign</th><th>Amount</th><th>Date</th><th>Status</th></tr></thead>
          <tbody id="donation-history">
            <?php
            try {
                require_once __DIR__ . '/../Config/db.php';
                $pdo = db();
                $myDonations = $pdo->prepare("
                    SELECT d.donation_id, c.title AS campaign, d.amount, d.donation_type, d.status, d.created_at
                    FROM donations d
                    LEFT JOIN campaigns c ON c.campaign_id = d.campaign_id
                    WHERE d.user_id = ?
                    ORDER BY d.created_at DESC LIMIT 20
                ");
                $myDonations->execute([$_SESSION['user']['id']]);
                $donRows = $myDonations->fetchAll(PDO::FETCH_ASSOC);
                if (empty($donRows)): ?>
                <tr><td colspan="5" class="muted" style="text-align:center;padding:24px">No donations yet. Browse campaigns to get started!</td></tr>
                <?php else: foreach ($donRows as $dr):
                    $dBadge = match($dr['status']) { 'completed'=>'badge-green','pending'=>'badge-yellow','failed'=>'badge-red',default=>'badge-gray' };
                    $dLabel = ucfirst($dr['status'] === 'completed' ? 'Approved' : $dr['status']); ?>
                <tr>
                    <td>#DN<?= str_pad($dr['donation_id'],5,'0',STR_PAD_LEFT) ?></td>
                    <td><?= htmlspecialchars($dr['campaign'] ?? '—') ?></td>
                    <td><?= $dr['donation_type']==='cash' ? '₱'.number_format($dr['amount'],2) : ucfirst($dr['donation_type']) ?></td>
                    <td class="muted"><?= date('M j, Y', strtotime($dr['created_at'])) ?></td>
                    <td><span class="badge <?= $dBadge ?>"><?= $dLabel ?></span></td>
                </tr>
                <?php endforeach; endif; ?>
            <?php } catch (Throwable $e) { ?>
                <tr><td colspan="5" class="muted" style="text-align:center;padding:24px">Unable to load donations.</td></tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<!-- Donation Modal -->
<div class="modal-backdrop" id="donate-modal">
  <div class="modal">
    <div class="modal-header">
      <h3>Make a Donation</h3>
      <button class="modal-close" onclick="closeModal()">×</button>
    </div>
    <div class="modal-body">
      <div id="modal-campaign-info" style="margin-bottom:14px;font-weight:600;color:var(--text-primary)"></div>
      <label class="form-label">Donation Type</label>
      <select class="form-control" id="donation-type" onchange="toggleDonationType()">
        <option value="cash">Cash Donation</option>
        <option value="item">Item Donation</option>
        <option value="service">Service Donation</option>
      </select>

      <div id="cash-fields">
        <label class="form-label">Amount (₱)</label>
        <input type="number" class="form-control" id="donate-amount" min="10" step="0.01" placeholder="Minimum ₱10.00" />
      </div>
      <div id="item-fields" style="display:none">
        <label class="form-label">Item Name</label>
        <input type="text" class="form-control" id="item-name" placeholder="e.g. Winter jackets" />
        <label class="form-label">Quantity</label>
        <input type="number" class="form-control" id="item-qty" min="1" placeholder="1" />
        <label class="form-label">Description</label>
        <textarea class="form-control" id="item-desc" rows="2" placeholder="Optional description"></textarea>
      </div>
      <div id="service-fields" style="display:none">
        <label class="form-label">Service Description</label>
        <textarea class="form-control" id="service-desc" rows="3" placeholder="Describe the service you are donating"></textarea>
      </div>

      <div class="check-row">
        <input type="checkbox" id="confirm-check" />
        <label for="confirm-check">I confirm this donation is legitimate and I understand it will be recorded for transparency.</label>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
      <button class="btn btn-primary" onclick="submitDonation()">Confirm Donation</button>
    </div>
  </div>
</div>

<script>
const CSRF = '<?= $_SESSION['csrf_token'] ?>';
let activeCampaignId = null;

let campaigns = [];

async function loadCampaigns() {
  const list = document.getElementById('campaign-list');
  list.innerHTML = '<p style="color:var(--text-secondary);padding:20px">Loading campaigns...</p>';
  try {
    const res = await fetch('api.php?action=campaigns');
    const json = await res.json();
    if (json.success && json.data) {
      campaigns = json.data;
    }
  } catch(e) { /* fallback to empty */ }
  loadCampaigns();
}

function renderCampaigns() {
  const list = document.getElementById('campaign-list');
  if (!campaigns.length) {
    list.innerHTML = '<p style="color:var(--text-secondary);padding:20px">No active campaigns at this time.</p>';
    return;
  }
  list.innerHTML = campaigns.map(c => {
    const pct = Math.round(((c.current||c.current_amount||0) / (c.target||c.target_amount||1)) * 100);
    const title = c.title || '';
    const desc  = c.description || '';
    return `<div class="campaign-card">
      <h4>${title}</h4>
      <p>${desc}</p>
      <div class="progress-bar-wrap"><div class="progress-bar-fill" style="width:${pct}%"></div></div>
      <div class="progress-label">₱${(c.current||c.current_amount||0).toLocaleString()} / ₱${(c.target||c.target_amount||0).toLocaleString()} (${pct}%)</div>
      <button class="btn btn-primary btn-sm" onclick="openModal(${c.id||c.campaign_id}, \`${title.replace(/`/g,"'")}\`)">Donate</button>
    </div>`;
  }).join('');
}

function showSection(name) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.getElementById('section-' + name).classList.add('active');
  document.querySelectorAll('.nav-item').forEach(a => a.classList.remove('active'));
  event.currentTarget.classList.add('active');
}

function openModal(id, title) {
  activeCampaignId = id;
  document.getElementById('modal-campaign-info').textContent = title;
  document.getElementById('donate-modal').classList.add('open');
}
function closeModal() {
  document.getElementById('donate-modal').classList.remove('open');
}

function toggleDonationType() {
  const type = document.getElementById('donation-type').value;
  document.getElementById('cash-fields').style.display    = type === 'cash'    ? '' : 'none';
  document.getElementById('item-fields').style.display    = type === 'item'    ? '' : 'none';
  document.getElementById('service-fields').style.display = type === 'service' ? '' : 'none';
}

async function submitDonation() {
  if (!document.getElementById('confirm-check').checked) {
    alert('Please confirm the donation before proceeding.');
    return;
  }
  const type = document.getElementById('donation-type').value;
  const body = new FormData();
  body.append('action', 'donate');
  body.append('csrf_token', CSRF);
  body.append('campaign_id', activeCampaignId);
  body.append('donation_type', type);

  if (type === 'cash') {
    const amt = parseFloat(document.getElementById('donate-amount').value);
    if (!amt || amt < 10) { alert('Minimum donation is ₱10.00'); return; }
    body.append('amount', amt);
  } else if (type === 'item') {
    const name = document.getElementById('item-name').value.trim();
    if (!name) { alert('Please enter the item name.'); return; }
    body.append('item_name', name);
    body.append('item_qty', document.getElementById('item-qty').value || 1);
    body.append('item_desc', document.getElementById('item-desc').value);
  } else if (type === 'service') {
    const desc = document.getElementById('service-desc').value.trim();
    if (!desc) { alert('Please describe the service.'); return; }
    body.append('service_desc', desc);
  }

  const btn = document.querySelector('#donate-modal .modal-footer .btn-primary');
  btn.disabled = true;
  btn.textContent = 'Submitting…';

  try {
    const res  = await fetch('api.php?action=donate', { method: 'POST', body });
    const json = await res.json();
    if (json.success) {
      closeModal();
      alert('Donation submitted! It will appear in your history once processed.');
      // Reload donation history table
      location.reload();
    } else {
      alert('Error: ' + (json.error || json.message || 'Unknown error'));
    }
  } catch(e) {
    alert('Network error. Please try again.');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Confirm Donation';
  }
}

renderCampaigns();
</script>
</body>
</html>
