<?php
require __DIR__ . '/../Config/db.php';
$id=$_GET['id'];

if($_SERVER['REQUEST_METHOD']=='POST'){
    $amount=$_POST['amount'];
    $pdo->prepare("UPDATE donations SET amount=?, status='completed' WHERE donation_id=?")->execute([$amount,$id]);

    $pdo->prepare("INSERT INTO payments (donation_id,gateway,payment_status) VALUES (?,?, 'success')")
        ->execute([$id,'manual']);

    header("Location: success.php?id=$id");
}
?>

<form method="POST">
<input name="amount" placeholder="Amount">
<button>Donate</button>
</form>