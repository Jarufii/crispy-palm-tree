<?php
require __DIR__ . '/../Config/db.php';
$id=$_GET['id'];

$pdo->prepare("UPDATE donations SET status='completed' WHERE donation_id=?")->execute([$id]);
header("Location: success.php?id=$id");
?>