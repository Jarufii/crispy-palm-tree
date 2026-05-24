<?php
require __DIR__.'/../Auth/middleware.php';
require __DIR__.'/../Config/db.php';

$user_id=$_SESSION['user_id'];

$q=$pdo->prepare("SELECT status FROM verification_requests WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
$q->execute([$user_id]);
$status=$q->fetchColumn();

echo "Your verification status: $status";
?>