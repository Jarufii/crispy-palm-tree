<?php

$_GET['_sess'] = 'UMDC_ORG';

require_once __DIR__ . '/../Config/db.php';
require_once __DIR__ . '/../dashboard/auth.php';

$user_id = $userId;

/* ================= GET USER + ORGANIZATION ================= */

$stmt = $pdo->prepare("
SELECT 
    users.user_id,
    users.first_name,
    users.last_name,
    users.email,
    users.phone,
    users.address,

    organizations.org_id,
    organizations.org_name,
    organizations.org_email,
    organizations.org_contact,
    organizations.description,
    organizations.created_at AS org_created

FROM users
LEFT JOIN organizations 
ON users.user_id = organizations.user_id

WHERE users.user_id = ?
LIMIT 1
");

$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$user){
    die("User not found.");
}

/* ================= UPDATE PROFILE ================= */

if(isset($_POST['save_profile'])){

    $org_name = trim($_POST['org_name']);
    $org_email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $description = trim($_POST['description']);

    /* UPDATE USERS */
    $stmt = $pdo->prepare("
        UPDATE users
        SET phone = ?, address = ?
        WHERE user_id = ?
    ");
    $stmt->execute([$phone, $address, $user_id]);

    /* CHECK ORGANIZATION EXISTS */
    $stmt = $pdo->prepare("
        SELECT org_id 
        FROM organizations 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $orgExists = $stmt->fetch(PDO::FETCH_ASSOC);

    if($orgExists){

        /* UPDATE ORGANIZATION */
        $stmt = $pdo->prepare("
            UPDATE organizations
            SET org_name = ?, org_email = ?, org_contact = ?, description = ?
            WHERE user_id = ?
        ");
        $stmt->execute([
            $org_name,
            $org_email,
            $phone,
            $description,
            $user_id
        ]);

    }else{

        /* INSERT ORGANIZATION */
        $stmt = $pdo->prepare("
            INSERT INTO organizations (
                user_id,
                org_name,
                org_email,
                org_contact,
                description
            )
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $org_name,
            $org_email,
            $phone,
            $description
        ]);
    }

    header("Location: profile.php?_sess=UMDC_ORG&updated=1");
    exit();
}

/* ================= DISPLAY NAME ================= */

$display_name = $user['first_name'].' '.$user['last_name'];

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Organization Profile</title>

<link rel="stylesheet" href="profile.css">

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

<!-- SIDEBAR BOTTOM -->

<div class="sidebar-bottom">

<a href="profile.php?_sess=UMDC_ORG" class="profile-link">

<div class="profile">

<div class="profile-icon">
<i class="fa-solid fa-user"></i>
</div>

<div class="profile-info">

<h4>
<?php echo htmlspecialchars($display_name); ?>
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

<!-- ================= MAIN ================= -->

<div class="main">

<h1 class="page-title">Profile</h1>

<?php if(isset($_GET['updated'])): ?>
<div class="success">
    Profile updated successfully.
</div>
<?php endif; ?>

<div class="profile-card">

<!-- PROFILE TOP -->

<div class="profile-top">

<div class="big-profile">
<i class="fa-regular fa-user"></i>
</div>

<div>
<h2 class="profile-name">
<?php echo htmlspecialchars($display_name); ?>
</h2>
</div>

</div>

<!-- FORM -->

<form method="POST">

<div class="profile-details">

<!-- ORGANIZATION NAME -->
<div class="detail-group">
<div class="detail-label">Organization Name</div>
<div class="detail-value">
<i class="fa-solid fa-building"></i>
<input type="text" name="org_name"
value="<?php echo htmlspecialchars($user['org_name'] ?? ''); ?>" required>
</div>
</div>

<!-- ORGANIZATION EMAIL -->
<div class="detail-group">
<div class="detail-label">Organization Email</div>
<div class="detail-value">
<i class="fa-regular fa-envelope"></i>
<input type="email" name="email"
value="<?php echo htmlspecialchars($user['org_email'] ?? ''); ?>" required>
</div>
</div>

<!-- PHONE -->
<div class="detail-group">
<div class="detail-label">Phone Number</div>
<div class="detail-value">
<i class="fa-solid fa-phone"></i>
<input type="text" name="phone"
value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
</div>
</div>

<!-- ADDRESS -->
<div class="detail-group">
<div class="detail-label">Address</div>
<div class="detail-value">
<i class="fa-solid fa-location-dot"></i>
<input type="text" name="address"
value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
</div>
</div>

<!-- DESCRIPTION -->
<div class="detail-group">
<div class="detail-label">Organization Description</div>
<div class="detail-value">
<i class="fa-solid fa-circle-info"></i>
<textarea name="description"><?php echo htmlspecialchars($user['description'] ?? ''); ?></textarea>
</div>
</div>

<!-- MEMBER SINCE -->
<div class="detail-group">
<div class="detail-label">Member Since</div>
<div class="detail-value">
<i class="fa-regular fa-calendar"></i>
<input type="text"
value="<?php echo !empty($user['org_created']) ? date('F Y', strtotime($user['org_created'])) : 'No Date'; ?>"
disabled>
</div>
</div>

</div>

<!-- BUTTON -->

<div class="btn-area">

<button type="submit" name="save_profile" class="save-btn">

<i class="fa-solid fa-check"></i>
Save Changes

</button>

</div>

</form>

</div>

</div>

</body>
</html>
