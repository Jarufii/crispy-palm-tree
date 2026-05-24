<?php
require __DIR__ . '/../Auth/middleware.php';
require __DIR__ . '/../Config/db.php';
requireRole('user');

$campaign_id=$_GET['cid'];
$type=$_POST['type'];

$stmt=$pdo->prepare("INSERT INTO donations (user_id,campaign_id,donation_type,status) VALUES (?,?,?, 'pending')");
$stmt->execute([$_SESSION['user_id'],$campaign_id,$type]);

$donation_id=$pdo->lastInsertId();

if($type=='cash') header("Location: cash.php?id=$donation_id");
if($type=='item') header("Location: item.php?id=$donation_id");
if($type=='service') header("Location: service.php?id=$donation_id");
?>