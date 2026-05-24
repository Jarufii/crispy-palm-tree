<?php

$_GET['_sess'] = 'UMDC_ORG';

require __DIR__ . '/../dashboard/auth.php';
requireRole('organization');
require_once __DIR__ . '/../Config/db.php';

/* ================= ORGANIZATION + USER ================= */

$stmt = $pdo->prepare("
SELECT 
    organizations.*,
    users.first_name AS org_first_name,
    users.last_name AS org_last_name
FROM organizations
INNER JOIN users
ON organizations.user_id = users.user_id
WHERE organizations.user_id = ?
ORDER BY organizations.org_id DESC
LIMIT 1
");

$stmt->execute([$userId]);
$org = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$org){
    die("No organization found.");
}

$org_id = $org['org_id'];

/* ================= SEARCH + FILTER ================= */

$search = $_GET['search'] ?? '';
$campaign_filter = $_GET['campaign'] ?? '';

/* ================= STATS ================= */

$stmt = $pdo->prepare("
SELECT COUNT(*) AS total
FROM donations d
INNER JOIN campaigns c ON d.campaign_id = c.campaign_id
WHERE c.org_id = ?
");
$stmt->execute([$org_id]);
$total_transactions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $pdo->prepare("
SELECT SUM(amount) AS total_amount
FROM donations d
INNER JOIN campaigns c ON d.campaign_id = c.campaign_id
WHERE c.org_id = ?
AND donation_type = 'cash'
");
$stmt->execute([$org_id]);
$total_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;

$stmt = $pdo->prepare("
SELECT SUM(amount) AS month_total
FROM donations d
INNER JOIN campaigns c ON d.campaign_id = c.campaign_id
WHERE c.org_id = ?
AND MONTH(d.created_at) = MONTH(CURRENT_DATE())
AND YEAR(d.created_at) = YEAR(CURRENT_DATE())
AND donation_type = 'cash'
");
$stmt->execute([$org_id]);
$this_month = $stmt->fetch(PDO::FETCH_ASSOC)['month_total'] ?? 0;

$stmt = $pdo->prepare("
SELECT SUM(amount) AS week_total
FROM donations d
INNER JOIN campaigns c ON d.campaign_id = c.campaign_id
WHERE c.org_id = ?
AND d.created_at >= NOW() - INTERVAL 7 DAY
AND donation_type = 'cash'
");
$stmt->execute([$org_id]);
$last_7_days = $stmt->fetch(PDO::FETCH_ASSOC)['week_total'] ?? 0;

/* ================= CAMPAIGNS ================= */

$stmt = $pdo->prepare("
SELECT *
FROM campaigns
WHERE org_id = ?
ORDER BY title ASC
");
$stmt->execute([$org_id]);
$campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= HISTORY ================= */

$sql = "
SELECT 
    d.*,
    c.title,
    u.first_name,
    u.last_name,
    p.gateway,
    item_donations.item_name,
    item_donations.quantity
FROM donations d
INNER JOIN campaigns c ON d.campaign_id = c.campaign_id
INNER JOIN users u ON d.user_id = u.user_id
LEFT JOIN payments p ON d.donation_id = p.donation_id
LEFT JOIN item_donations ON d.donation_id = item_donations.donation_id
WHERE c.org_id = ?
";

$params = [$org_id];

if(!empty($search)){
    $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR c.title LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if(!empty($campaign_filter)){
    $sql .= " AND c.campaign_id = ?";
    $params[] = $campaign_filter;
}

$sql .= " ORDER BY d.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Donation History</title>

<link rel="stylesheet" href="history.css">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

</head>

<body>

<aside class="sidebar">

<div class="sidebar-top">

<div class="logo">
<h2>UMDC</h2>
<p>Organization</p>
</div>

<div class="menu">

<a href="/../dashboard/organization.php?_sess=UMDC_ORG" class="menu-item">
<i class="fa-solid fa-table-columns"></i>
Dashboard
</a>

<a href="/../campaigns/add-campaign.php?_sess=UMDC_ORG" class="menu-item">
<i class="fa-solid fa-circle-plus"></i>
Add Campaign
</a>

<a href="/../transactions/transaction.php?_sess=UMDC_ORG" class="menu-item">
<i class="fa-solid fa-money-check-dollar"></i>
Transaction
</a>

<a href="/../transactions/history.php?_sess=UMDC_ORG" class="menu-item active">
<i class="fa-solid fa-clock-rotate-left"></i>
History
</a>

<a href="/../verification/verification.php?_sess=UMDC_ORG" class="menu-item">
<i class="fa-solid fa-shield-heart"></i>
Verification
</a>

</div>

</div>

<div class="sidebar-bottom">

<a href="/../profile/profile.php?_sess=UMDC_ORG" class="profile-link">

<div class="profile">

<div class="profile-icon">
<i class="fa-solid fa-user"></i>
</div>

<div class="profile-info">
<h4>
<?php echo htmlspecialchars($org['org_first_name'].' '.$org['org_last_name']); ?>
</h4>
<p>View Profile</p>
</div>

</div>

</a>

<a href="../Auth/logout.php" class="logout">
<i class="fa-solid fa-arrow-right-from-bracket"></i>
</a>

</div>

</aside>

<div class="main">

<div class="title">
<h1>Donation History</h1>
<p>View and manage all donation transactions</p>
</div>

<div class="stats">

<div class="stat-box">
<span>Total Transactions</span>
<h2><?php echo $total_transactions; ?></h2>
</div>

<div class="stat-box">
<span>Total Amount</span>
<h2>₱<?php echo number_format($total_amount,2); ?></h2>
</div>

<div class="stat-box">
<span>This Month</span>
<h2>₱<?php echo number_format($this_month,2); ?></h2>
</div>

<div class="stat-box">
<span>Last 7 Days</span>
<h2>₱<?php echo number_format($last_7_days,2); ?></h2>
</div>

</div>

<div class="search-section">

<form method="GET" class="search-form">

<input type="hidden" name="_sess" value="UMDC_ORG">

<div class="search-box">
<i class="fa-solid fa-magnifying-glass"></i>
<input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
</div>

<select name="campaign" class="filter">
<option value="">All Campaigns</option>
<?php foreach($campaigns as $c): ?>
<option value="<?php echo $c['campaign_id']; ?>"
<?php if($campaign_filter == $c['campaign_id']) echo "selected"; ?>>
<?php echo htmlspecialchars($c['title']); ?>
</option>
<?php endforeach; ?>
</select>

<button type="submit" class="search-btn">Search</button>

</form>

</div>

<div class="table-card">

<div class="table-header">
<h2>Transaction History</h2>
<p><?php echo count($history); ?> transactions</p>
</div>

<div class="table-container">

<table>

<thead>
<tr>
<th>Donor</th>
<th>Campaign</th>
<th>Type</th>
<th>Details</th>
<th>Date & Time</th>
<th>Delivery</th>
</tr>
</thead>

<tbody>

<?php if(count($history) > 0): ?>

<?php foreach($history as $row): ?>

<tr>

<td>
<?php echo htmlspecialchars($row['first_name'].' '.$row['last_name']); ?>
</td>

<td>
<?php echo htmlspecialchars($row['title']); ?>
</td>

<td style="text-transform:capitalize;">
<?php echo htmlspecialchars($row['donation_type']); ?>
</td>

<td>
<?php if($row['donation_type'] == 'cash'): ?>
₱<?php echo number_format($row['amount'],2); ?>
<?php elseif($row['donation_type'] == 'item'): ?>
<?php echo htmlspecialchars($row['item_name'])." - ".$row['quantity']." pcs"; ?>
<?php else: ?>
Service Donation
<?php endif; ?>
</td>

<td>
<div class="date">
<?php echo date("F d, Y", strtotime($row['created_at'])); ?>
</div>
<div class="time">
<?php echo date("g:i A", strtotime($row['created_at'])); ?>
</div>
</td>

<td>
<?php echo $row['gateway'] ? htmlspecialchars($row['gateway']) : 'In-Person'; ?>
</td>

</tr>

<?php endforeach; ?>

<?php else: ?>

<tr>
<td colspan="6" style="text-align:center; padding:40px;">
No donation history found.
</td>
</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>

</div>

</body>
</html>
