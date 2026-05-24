<?php
require __DIR__.'/../Auth/middleware.php';
require __DIR__.'/../Config/db.php';

$user_id = $_SESSION['user_id'];
$type = $_POST['type']; 

$check = $pdo->prepare("SELECT id FROM verification_requests WHERE user_id=? AND status='pending'");
$check->execute([$user_id]);

if($check->rowCount() > 0){
    die("You already have a pending verification request.");
}

$stmt = $pdo->prepare("
    INSERT INTO verification_requests (user_id, type, status)
    VALUES (?, ?, 'pending')
");

$stmt->execute([$user_id, $type]);

echo "Verification request submitted successfully.";
?>