<?php
require __DIR__ . '/../Auth/middleware.php';
require __DIR__ . '/../Config/db.php';
requireRole('user');

$q=$pdo->prepare("SELECT * FROM donations WHERE user_id=?");
$q->execute([$_SESSION['user_id']]);

foreach($q as $d){
    echo "Donation #{$d['donation_id']} - {$d['donation_type']} - {$d['status']}<br>";
}
?>