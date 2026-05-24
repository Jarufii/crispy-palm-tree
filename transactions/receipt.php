<?php
require __DIR__ . '/../Config/db.php';
$id=$_GET['id'];
$r=$pdo->prepare("SELECT r.receipt_number,d.amount,d.created_at FROM receipts r JOIN donations d ON r.donation_id=d.donation_id WHERE r.donation_id=?");
$r->execute([$id]);
$data=$r->fetch();

echo "Receipt #: {$data['receipt_number']}<br>";
echo "Amount: {$data['amount']}<br>";
echo "Date: {$data['created_at']}";
?>