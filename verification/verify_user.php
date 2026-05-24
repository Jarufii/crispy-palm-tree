<?php
require __DIR__.'/../Auth/middleware.php';
require __DIR__.'/../Config/db.php';
requireRole('admin');

$q=$pdo->query("SELECT * FROM verification_requests WHERE status='pending'");

foreach($q as $v){
    echo "Request #{$v['id']} - {$v['type']} - <a href='?approve={$v['id']}'>Approve</a> | <a href='?reject={$v['id']}'>Reject</a><br>";
}

if(isset($_GET['approve'])){
    $pdo->prepare("UPDATE verification_requests SET status='approved', reviewed_by=? WHERE id=?")
        ->execute([$_SESSION['user_id'],$_GET['approve']]);
}

if(isset($_GET['reject'])){
    $pdo->prepare("UPDATE verification_requests SET status='rejected', reviewed_by=? WHERE id=?")
        ->execute([$_SESSION['user_id'],$_GET['reject']]);
}
?>