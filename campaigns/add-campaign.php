<?php

// Pass _sess parameter so auth.php opens the correct session
if (!isset($_GET['_sess']) && isset($_SERVER['HTTP_REFERER'])) {
    parse_str(parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY) ?? '', $refParams);
    if (!empty($refParams['_sess'])) {
        $_GET['_sess'] = $refParams['_sess'];
    }
}
if (!isset($_GET['_sess'])) {
    $_GET['_sess'] = 'UMDC_ORG';
}

require __DIR__ . '/../dashboard/auth.php';
requireRole('organization');
require_once __DIR__ . '/../Config/db.php';

/* ================= ORGANIZATION + USER ================= */

$stmt = $pdo->prepare("
SELECT 
    organizations.*,
    users.first_name,
    users.last_name
FROM organizations
INNER JOIN users
ON organizations.user_id = users.user_id
WHERE organizations.user_id = ?
ORDER BY organizations.org_id DESC
LIMIT 1
");

$stmt->execute([$userId]);
$org = $stmt->fetch(PDO::FETCH_ASSOC);

/* IF NO ORGANIZATION */
if(!$org){
    die("No organization found.");
}

$org_id = $org['org_id'];

/* ================= ADD CAMPAIGN ================= */

if(isset($_POST['add_campaign'])){

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $target_amount = trim($_POST['target_amount']);
    $start_date = trim($_POST['start_date']);
    $end_date = trim($_POST['end_date']);

    try {

        $stmt = $pdo->prepare("
            INSERT INTO campaigns (
                org_id,
                title,
                description,
                category,
                target_amount,
                current_amount,
                start_date,
                end_date,
                status
            )
            VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");

        $stmt->execute([
            $org_id,
            $title,
            $description,
            $category,
            $target_amount,
            0,
            $start_date,
            $end_date,
            'pending'
        ]);

        header("Location: ../dashboard/organization.php?_sess=UMDC_ORG");
        exit();

    } catch (PDOException $e) {
        $error = "Failed to create campaign.";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>UMDC Add Campaign</title>

<link rel="stylesheet" href="add-campaign.css">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

</head>

<body>

<!-- ================= SIDEBAR ================= -->

<aside class="sidebar">

<div class="sidebar-top">

<div class="logo">
<h2>UMDC</h2>
<p>Organization</p>
</div>

<div class="menu">

<a href="../dashboard/organization.php?_sess=UMDC_ORG" class="menu-item">
<i class="fa-solid fa-table-columns"></i>
Dashboard
</a>

<a href="/../campaigns/add-campaign.php" class="menu-item active">
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

<!-- SIDEBAR BOTTOM -->

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

<a href="../Auth/logout.php" class="logout">
<i class="fa-solid fa-arrow-right-from-bracket"></i>
</a>

</div>

</aside>

<!-- ================= MAIN CONTENT ================= -->

<div class="main">

<div class="title">
<h1>Create New Campaign</h1>
<p>Set up a new fundraising campaign for your organization</p>
</div>

<div class="card">

<?php if(isset($error)){ ?>
<div class="error">
    <?php echo htmlspecialchars($error); ?>
</div>
<?php } ?>

<form method="POST">

<!-- Campaign Name -->
<div class="form-group">
<label>Campaign Name</label>
<input type="text" name="title" class="input" required>
</div>

<!-- Description -->
<div class="form-group">
<label>Description</label>
<textarea name="description" class="textarea" required></textarea>
</div>

<!-- CATEGORY & GOAL -->
<div class="row">

<div class="form-group">
<label>Category</label>
<select name="category" class="select" required>
<option value="">Select a category</option>
<option value="Education">Education</option>
<option value="Medical">Medical</option>
<option value="Community">Community</option>
<option value="Environment">Environment</option>
</select>
</div>

<div class="form-group">
<label>Fundraising Goal</label>
<input type="number" name="target_amount" class="input" required>
</div>

</div>

<!-- DATES -->
<div class="row">

<div class="form-group">
<label>Start Date</label>
<input type="date" name="start_date" class="input" required>
</div>

<div class="form-group">
<label>End Date</label>
<input type="date" name="end_date" class="input" required>
</div>

</div>

<!-- BUTTON -->
<div class="button-wrapper">

<button type="submit" name="add_campaign" class="btn">
<i class="fa-solid fa-bullseye"></i>
Launch Campaign
</button>

</div>

</form>

</div>

</div>

</body>
</html>
