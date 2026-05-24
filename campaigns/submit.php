<?php
require __DIR__ . '/../Auth/Middleware.php';
require __DIR__ . '/../Config/db.php';
requireRole('organization');

$cid = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("UPDATE campaigns SET status='pending' WHERE campaign_id=?");
$stmt->execute([$cid]);

$ap = $pdo->prepare("INSERT INTO approvals (entity_type, entity_id, status) VALUES ('campaign', ?, 'pending')");
$ap->execute([$cid]);

echo "Campaign submitted for approval";
?>