<?php
// FILE: api/analytics.php
require __DIR__ . '/../Config/db.php';
header('Content-Type: application/json');

$data = [];

// totals
$data['totalDonations'] = $pdo->query("SELECT IFNULL(SUM(amount),0) FROM donations WHERE status='completed'")->fetchColumn();
$data['totalUsers'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$data['totalCampaigns'] = $pdo->query("SELECT COUNT(*) FROM campaigns")->fetchColumn();
$data['totalOrgs'] = $pdo->query("SELECT COUNT(*) FROM organizations")->fetchColumn();

// recent donations
$q = $pdo->query("SELECT donation_id as id, amount, status, created_at as date FROM donations ORDER BY donation_id DESC LIMIT 5");
$data['recentDonations'] = $q->fetchAll(PDO::FETCH_ASSOC);

// chart data (last 7 days)
$q = $pdo->query("SELECT DATE(created_at) as day, SUM(amount) as total FROM donations WHERE status='completed' GROUP BY day ORDER BY day DESC LIMIT 7");
$rows = array_reverse($q->fetchAll(PDO::FETCH_ASSOC));

$data['chart'] = [
    'labels' => array_column($rows, 'day'),
    'values' => array_column($rows, 'total')
];

echo json_encode($data);
?>
