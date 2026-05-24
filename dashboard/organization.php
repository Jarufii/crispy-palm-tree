<?php

require __DIR__ . '/auth.php';
requireRole('organization');
require_once __DIR__ . '/../Config/db.php';

/* ================= ORGANIZATION + USER ================= */

$stmt = $pdo->prepare("
SELECT
    organizations.*,
    users.first_name,
    users.last_name
FROM organizations
INNER JOIN users ON organizations.user_id = users.user_id
WHERE organizations.user_id = ?
ORDER BY organizations.org_id DESC
LIMIT 1
");
$stmt->execute([$userId]);
$org = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$org) {
    $org = [
        'org_id'     => 0,
        'org_name'   => 'No Organization Found',
        'first_name' => 'User',
        'last_name'  => '',
        'verified'   => 0,
    ];
}

$org_id = $org['org_id'];

/* ================= TOTAL DONATIONS ================= */

$stmt = $pdo->prepare("
SELECT SUM(d.amount) AS total_donations
FROM donations d
INNER JOIN campaigns c ON d.campaign_id = c.campaign_id
WHERE c.org_id = ?
AND d.status = 'completed'
");
$stmt->execute([$org_id]);
$totalDonation = $stmt->fetch(PDO::FETCH_ASSOC);

/* ================= TOTAL DONORS ================= */

$stmt = $pdo->prepare("
SELECT COUNT(DISTINCT d.user_id) AS total_donors
FROM donations d
INNER JOIN campaigns c ON d.campaign_id = c.campaign_id
WHERE c.org_id = ?
");
$stmt->execute([$org_id]);
$totalDonor = $stmt->fetch(PDO::FETCH_ASSOC);

/* ================= MONTHLY DONORS ================= */

$stmt = $pdo->prepare("
SELECT COUNT(DISTINCT d.user_id) AS monthly_donors
FROM donations d
INNER JOIN campaigns c ON d.campaign_id = c.campaign_id
WHERE c.org_id = ?
AND MONTH(d.created_at) = MONTH(CURRENT_DATE())
AND YEAR(d.created_at) = YEAR(CURRENT_DATE())
");
$stmt->execute([$org_id]);
$monthlyDonor = $stmt->fetch(PDO::FETCH_ASSOC);

/* ================= ACTIVE CAMPAIGNS ================= */

$stmt = $pdo->prepare("
SELECT COUNT(*) AS active_campaigns
FROM campaigns
WHERE org_id = ?
AND status = 'active'
");
$stmt->execute([$org_id]);
$activeCampaign = $stmt->fetch(PDO::FETCH_ASSOC);

/* ================= CAMPAIGNS LIST ================= */

$stmt = $pdo->prepare("
SELECT *
FROM campaigns
WHERE org_id = ?
ORDER BY created_at DESC
");
$stmt->execute([$org_id]);
$campaignQuery = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= BAR CHART ================= */

$chart_labels = [];
$chart_data   = [];

for ($i = 6; $i >= 0; $i--) {
    $chart_labels[] = date("M", strtotime("-$i months"));

    $stmt = $pdo->prepare("
    SELECT SUM(d.amount) AS total
    FROM donations d
    INNER JOIN campaigns c ON d.campaign_id = c.campaign_id
    WHERE c.org_id = ?
    AND MONTH(d.created_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL $i MONTH))
    AND YEAR(d.created_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL $i MONTH))
    ");
    $stmt->execute([$org_id]);
    $result       = $stmt->fetch(PDO::FETCH_ASSOC);
    $chart_data[] = $result['total'] ?? 0;
}

/* ================= PIE CHART ================= */

$stmt = $pdo->prepare("
SELECT COUNT(*) AS total
FROM donations d
INNER JOIN campaigns c ON d.campaign_id = c.campaign_id
WHERE c.org_id = ?
AND donation_type = 'cash'
");
$stmt->execute([$org_id]);
$cash = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
SELECT COUNT(*) AS total
FROM donations d
INNER JOIN campaigns c ON d.campaign_id = c.campaign_id
WHERE c.org_id = ?
AND donation_type = 'item'
");
$stmt->execute([$org_id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
SELECT COUNT(*) AS total
FROM donations d
INNER JOIN campaigns c ON d.campaign_id = c.campaign_id
WHERE c.org_id = ?
AND donation_type = 'service'
");
$stmt->execute([$org_id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>

<title>UMDC Dashboard</title>

<link rel="stylesheet" href="organization.css">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body>

<aside class="sidebar">

<div class="sidebar-top">

<div class="logo">
<h2>UMDC</h2>
<p>Organization</p>
</div>

<div class="menu">

<a href="/../dashboard/organization.php" class="menu-item active">
<i class="fa-solid fa-table-columns"></i>
Dashboard
</a>

<a href="/../campaigns/add-campaign.php" class="menu-item">
<i class="fa-solid fa-circle-plus"></i>
Add Campaign
</a>

<a href="/../transactions/transaction.php" class="menu-item">
<i class="fa-solid fa-money-check-dollar"></i>
Transaction
</a>

<a href="/../transactions/history.php" class="menu-item">
<i class="fa-solid fa-clock-rotate-left"></i>
History
</a>

<a href="/../verification/verification.php" class="menu-item">
<i class="fa-solid fa-shield-heart"></i>
Verification
</a>

</div>

</div>

<div class="sidebar-bottom">

<a href="/../profile/profile.php" class="profile-link">

<div class="profile">

<div class="profile-icon">
<i class="fa-solid fa-user"></i>
</div>

<div class="profile-info">

<h4>
<?php echo htmlspecialchars($org['first_name'].' '.$org['last_name']); ?>
</h4>

<p>View Profile</p>

</div>

</div>

</a>

<a href="/../Auth/logout.php" class="logout">
<i class="fa-solid fa-arrow-right-from-bracket"></i>
</a>

</div>

</aside>

<div class="main">

<div class="main-content">

<div class="header">

<h1><?php echo htmlspecialchars($org['org_name']); ?></h1>

<p>Here's an overview of your organization's donation activity</p>

</div>

<?php if (!$org['verified']): ?>
<div class="alert alert-warning">
    <div class="alert-icon">
        <svg width="18" height="18" fill="none" stroke="#f59e0b" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
    </div>
    <div class="alert-body">
        <strong>Your organization is pending admin verification.</strong>
        You can set up campaigns, but they won't be publicly visible until verified.
    </div>
</div>
<?php endif; ?>

<div class="stats">

<div class="card">
<h3>Total Donations</h3>
<h2>₱<?php echo number_format($totalDonation['total_donations'] ?? 0,2); ?></h2>
</div>

<div class="card">
<h3>Total Donors</h3>
<h2><?php echo $totalDonor['total_donors'] ?? 0; ?></h2>
</div>

<div class="card">
<h3>Monthly Donors</h3>
<h2><?php echo $monthlyDonor['monthly_donors'] ?? 0; ?></h2>
</div>

<div class="card">
<h3>Active Campaigns</h3>
<h2><?php echo $activeCampaign['active_campaigns'] ?? 0; ?></h2>
</div>

</div>

<div class="charts">

<div class="chart-card">
<h2>Donation Trend</h2>
<canvas id="donationChart"></canvas>
</div>

<div class="chart-card">
<h2>Donation Sources</h2>
<canvas id="pieChart"></canvas>
</div>

</div>

<div class="table-card">

<h2>Campaigns</h2>

<table>

<thead>
<tr>
<th>Campaign</th>
<th>Goal</th>
<th>Progress</th>
<th>Status</th>
</tr>
</thead>

<tbody>

<?php foreach($campaignQuery as $campaign):

$progress = 0;
if($campaign['target_amount'] > 0){
    $progress = ($campaign['current_amount'] / $campaign['target_amount']) * 100;
}
?>

<tr>

<td><?php echo htmlspecialchars($campaign['title']); ?></td>

<td>₱<?php echo number_format($campaign['target_amount'],2); ?></td>

<td>

<div class="progress-bar">
<div class="progress-fill" style="width:<?php echo $progress; ?>%"></div>
</div>

<?php echo round($progress); ?>%

</td>

<td>
<span class="status <?php echo $campaign['status'] == 'completed' ? 'completed' : 'ongoing'; ?>">
<?php echo ucfirst($campaign['status']); ?>
</span>
</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

</div>

<script>

const ctx = document.getElementById('donationChart');

new Chart(ctx, {
type: 'bar',
data: {
labels: <?php echo json_encode($chart_labels); ?>,
datasets: [{
data: <?php echo json_encode($chart_data); ?>,
backgroundColor: '#0d9488'
}]
}
});

const pie = document.getElementById('pieChart');

new Chart(pie, {
type: 'pie',
data: {
labels: ['Cash','Item','Service'],
datasets: [{
data: [
<?php echo $cash['total'] ?? 0; ?>,
<?php echo $item['total'] ?? 0; ?>,
<?php echo $service['total'] ?? 0; ?>
],
backgroundColor: ['#0d9488','#14b8a6','#99f6e4']
}]
}
});

</script>

</body>
</html>
