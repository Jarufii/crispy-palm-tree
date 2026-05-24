<?php
require __DIR__ . '/../Auth/Middleware.php';
require __DIR__ . '/../Config/db.php';
requireRole('organization');

if($_SERVER['REQUEST_METHOD']=='POST'){
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $cat = $_POST['category'];
    $target = $_POST['target'];
    $org_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("INSERT INTO campaigns (org_id, title, description, category, target_amount, status) VALUES (?,?,?,?,?, 'pending')");
    $stmt->execute([$org_id, $title, $desc, $cat, $target]);
    
    $campaign_id = $pdo->lastInsertId();
    
    // Add to approvals
    $ap = $pdo->prepare("INSERT INTO approvals (entity_type, entity_id, status) VALUES ('campaign', ?, 'pending')");
    $ap->execute([$campaign_id]);

    header("Location: /dashboard/organization.php");
    exit;
}
?>