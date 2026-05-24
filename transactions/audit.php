<?php
require __DIR__ . '/../Config/db.php';
function logAction($user,$action,$entity,$id){
    global $pdo;
    $pdo->prepare("INSERT INTO audit_logs (user_id,action,entity_type,entity_id,ip_address) VALUES (?,?,?,?,?)")
        ->execute([$user,$action,$entity,$id,$_SERVER['REMOTE_ADDR']]);
}
?>