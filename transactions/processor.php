<?
require __DIR__ . '/../Config/db.php';
function processTransaction($donation_id) {
    global $pdo;
    $receipt='UMDC-TXN-'.time().'-'.$donation_id;
    $pdo->prepare("INSERT INTO receipts (donation_id,receipt_number) VALUES (?,?)")
        ->execute([$donation_id,$receipt]);

    // Ledger entry
    $hash=hash('sha256',$receipt.$donation_id);
    $pdo->prepare("INSERT INTO ledgers (donation_id,public_hash) VALUES (?,?)")
        ->execute([$donation_id,$hash]);

    // Audit log
    $pdo->prepare("INSERT INTO audit_logs (user_id,action,entity_type,entity_id,ip_address) VALUES (?,?,?,?,?)")
        ->execute([
            $_SESSION['user_id'],
            'DONATION_COMPLETED',
            'donation',
            $donation_id,
            $_SERVER['REMOTE_ADDR']
        ]);
}
?>