<?php
require __DIR__ . '/../Config/db.php';
$q=$pdo->query("SELECT l.public_hash, d.amount, d.created_at FROM ledgers l JOIN donations d ON l.donation_id=d.donation_id");
foreach($q as $row){
    echo "<p>Hash: {$row['public_hash']} | Amount: {$row['amount']} | Date: {$row['created_at']}</p>";
}
?>