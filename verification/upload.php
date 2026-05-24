<?php
require __DIR__.'/../Auth/middleware.php';
require __DIR__.'/../Config/db.php';

$user_id = $_SESSION['user_id'];

if($_SERVER['REQUEST_METHOD']=='POST'){
    $docType = $_POST['document_type'];
    $file = $_FILES['document'];

    $path = __DIR__.'/../uploads/'.time().'_'.$file['name'];
    move_uploaded_file($file['tmp_name'],$path);

    $stmt=$pdo->prepare("INSERT INTO verification_requests (user_id,type,document_type,document_path) VALUES (?,?,?,?)");
    $stmt->execute([$user_id,'user',$docType,$path]);

    echo "Verification submitted";
}
?>