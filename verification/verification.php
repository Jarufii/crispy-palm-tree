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

/* ================= SUBMIT VERIFICATION ================= */

$message = "";

if(isset($_POST['submit_verification'])){

    $document_type = trim($_POST['document_type']);

    if(isset($_FILES['document']) && $_FILES['document']['error'] == 0){

        $file_name = $_FILES['document']['name'];
        $tmp_name  = $_FILES['document']['tmp_name'];
        $file_size = $_FILES['document']['size'];

        $allowed = ['pdf','jpg','jpeg','png'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if(in_array($ext, $allowed)){

            if($file_size <= 10000000){

                if(!is_dir('uploads/verification')){
                    mkdir('uploads/verification', 0777, true);
                }

                $new_name    = time().'_'.$file_name;
                $upload_path = 'uploads/verification/'.$new_name;

                move_uploaded_file($tmp_name, $upload_path);

                try {

                    $stmt = $pdo->prepare("
                        INSERT INTO verification_requests (
                            user_id,
                            type,
                            document_type,
                            document_path,
                            status
                        )
                        VALUES (?, ?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $userId,
                        'organization',
                        $document_type,
                        $upload_path,
                        'pending'
                    ]);

                    $message = "Verification document submitted successfully.";

                } catch(PDOException $e){
                    $message = "Database error occurred.";
                }

            } else {
                $message = "File size exceeds 10MB.";
            }

        } else {
            $message = "Invalid file type.";
        }

    } else {
        $message = "Please upload a document.";
    }
}

/* ================= GET LATEST STATUS ================= */

$stmt = $pdo->prepare("
SELECT *
FROM verification_requests
WHERE user_id = ?
ORDER BY id DESC
LIMIT 1
");

$stmt->execute([$userId]);
$verification = $stmt->fetch(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Verification Dashboard</title>

<link rel="stylesheet" href="verification.css">

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

<a href="/../transactions/history.php?_sess=UMDC_ORG" class="menu-item">
<i class="fa-solid fa-clock-rotate-left"></i>
History
</a>

<a href="/../verification/verification.php?_sess=UMDC_ORG" class="menu-item active">
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

<main class="main">

<div class="header">
<h1>Verification Tab</h1>
<p>Upload your organization's verification documents for approval and review.</p>
</div>

<?php if($verification): ?>
<div class="status-box">
<span>Status:</span>
<strong class="<?php echo htmlspecialchars($verification['status']); ?>">
<?php echo ucfirst($verification['status']); ?>
</strong>
</div>
<?php endif; ?>

<?php if(!empty($message)): ?>
<div class="message">
<?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="verification-box">

<h2>Verification</h2>

<p class="verification-text">
To ensure transparency and security, please upload the required
verification documents for your organization.
</p>

<form method="POST" enctype="multipart/form-data">

<div class="verification-grid">

<div class="upload-card">
<div class="upload-icon">
<i class="fa-solid fa-file-arrow-up"></i>
</div>
<h3>Upload Verification Document</h3>
<p>Accepted files: PDF, JPG, PNG <br> Maximum file size: 10MB</p>
<input type="file" name="document" required>
</div>

<div class="requirements-card">
<h3>Choose Document Type:</h3>
<select name="document_type" class="document-select" required>
<option value="">Select Document</option>
<option value="Business Registration Certificate">Business Registration Certificate</option>
<option value="DTI Certificate">DTI Certificate</option>
<option value="Business Permit">Business Permit</option>
<option value="Certificate of Registration">Certificate of Registration</option>
</select>
<ul>
<li>Business Registration Certificate</li>
<li>DTI Certificate</li>
<li>Business Permit</li>
<li>Certificate of Registration</li>
</ul>
</div>

</div>

<div class="submit-section">
<button type="submit" name="submit_verification" class="submit-btn">
Submit for Review
</button>
</div>

</form>

</div>

</main>

</body>
</html>
