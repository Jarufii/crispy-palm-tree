<?php
require __DIR__ . '/../Config/db.php';
$donation_id=$_GET['donation_id'];

$item=$pdo->query("SELECT item_id FROM item_donations WHERE donation_id=$donation_id")->fetchColumn();

$pdo->prepare("INSERT INTO deliveries (item_id,status) VALUES (?, 'requested')")->execute([$item]);

echo "Courier request submitted for admin approval";
?>