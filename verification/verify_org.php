<?php
require __DIR__.'/../Auth/middleware.php';
require __DIR__.'/../Config/db.php';
requireRole('organization');

$org_id = $_SESSION['user_id'];

if($_SERVER['REQUEST_METHOD']=='POST'){
    $docName=$_POST['doc_name'];
    $file=$_FILES['document'];

    $path=__DIR__.'/../uploads/'.time().'_'.$file['name'];
    move_uploaded_file($file['tmp_name'],$path);

    $pdo->prepare("INSERT INTO organization_documents (org_id,document_name,document_path) VALUES (?,?,?)")
        ->execute([$org_id,$docName,$path]);

    $pdo->prepare("INSERT INTO verification_requests (user_id,type,status) VALUES (?, 'organization','pending')")
        ->execute([$org_id]);

    echo "Organization verification submitted";
}
?>