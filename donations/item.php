<?php
require __DIR__ . '/../Config/db.php';
$id=$_GET['id'];

if($_SERVER['REQUEST_METHOD']=='POST'){
    $name=$_POST['item'];
    $qty=$_POST['qty'];
    $desc=$_POST['desc'];

    $pdo->prepare("INSERT INTO item_donations (donation_id,item_name,quantity,description) VALUES (?,?,?,?)")
        ->execute([$id,$name,$qty,$desc]);

    header("Location: ../couriers/request.php?donation_id=$id");
}
?>