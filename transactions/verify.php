<?php
require __DIR__ . '/../Config/db.php';
$hash=$_GET['hash'];

$stmt=$pdo->prepare("SELECT l.public_hash, d.amount, d.created_at, c.title FROM ledgers l JOIN donations d ON l.donation_id=d.donation_id JOIN campaigns c ON d.campaign_id=c.campaign_id WHERE l.public_hash=?");
$stmt->execute([$hash]);
$data=$stmt->fetch(PDO::FETCH_ASSOC);

if($data){
    echo "<h3>Verified Donation</h3>";
    echo "Campaign: {$data['title']}<br>";
    echo "Amount: {$data['amount']}<br>";
    echo "Date: {$data['created_at']}<br>";
    echo "Status: VERIFIED";
} else {
    echo "Invalid or non-existent transaction hash";
}
?>