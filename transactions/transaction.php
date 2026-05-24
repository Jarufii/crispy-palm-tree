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

/* ================= TRANSACTIONS ================= */

$stmt = $pdo->prepare("
SELECT 
    donations.*,
    campaigns.title,
    users.first_name,
    users.last_name,
    users.email
FROM donations
LEFT JOIN campaigns 
ON donations.campaign_id = campaigns.campaign_id
LEFT JOIN users
ON donations.user_id = users.user_id
WHERE campaigns.org_id = ?
ORDER BY donations.created_at DESC
");

$stmt->execute([$org_id]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>UMDC Transactions</title>

<link rel="stylesheet" href="transaction.css">

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

<a href="/../transactions/transaction.php?_sess=UMDC_ORG" class="menu-item active">
<i class="fa-solid fa-money-check-dollar"></i>
Transaction
</a>

<a href="/../transactions/history.php?_sess=UMDC_ORG" class="menu-item">
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

<div class="header">
<h1>Transactions</h1>
<p>View and manage all donation transactions</p>
</div>

<div class="card">

<div class="card-top">

<h2>All Transactions</h2>

<div class="actions">

<div class="search-box">
<i class="fa-solid fa-magnifying-glass"></i>
<input type="text" placeholder="Search transactions...">
</div>

<select class="filter">
<option>All Methods</option>
<option>Cash</option>
<option>Item</option>
<option>Service</option>
</select>

</div>

</div>

<div class="table-container">

<table>

<thead>
<tr>
<th>Donor</th>
<th>Campaign</th>
<th>Description</th>
<th>Method</th>
<th>Date & Time</th>
</tr>
</thead>

<tbody>

<?php if(count($transactions) > 0): ?>

<?php foreach($transactions as $row): ?>

<tr>

<td>
<div class="donor-name">
<?php echo htmlspecialchars($row['first_name'].' '.$row['last_name']); ?>
</div>
<div class="donor-email">
<?php echo htmlspecialchars($row['email']); ?>
</div>
</td>

<td>
<?php echo htmlspecialchars($row['title']); ?>
</td>

<td>
<?php
if($row['donation_type'] == 'cash'){
    echo '₱'.number_format($row['amount'],2);
}else{
    echo ucfirst($row['donation_type']).' Donation';
}
?>
</td>

<td>
<div class="method">
<i class="fa-regular fa-credit-card"></i>
<?php echo ucfirst($row['donation_type']); ?>
</div>
</td>

<td>
<div class="date">
<?php echo date("Y-m-d", strtotime($row['created_at'])); ?>
</div>
<div class="time">
<?php echo date("H:i", strtotime($row['created_at'])); ?>
</div>
</td>

</tr>

<?php endforeach; ?>

<?php else: ?>

<tr>
<td colspan="5" style="text-align:center; padding:40px;">
No transactions found.
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
