<?php
/**
 * UMDC Admin Panel — api.php
 * Backend API handler for donations, approvals, reports, users, and courier.
 * Returns JSON. Requires PHP 8.0+.
 *
 * Usage:  api.php?action=<action>[&params]
 * Or POST with JSON body: { "action": "...", ... }
 */

session_start();

require_once __DIR__ . '/../Config/db.php';

header('Content-Type: application/json');
// Restrict CORS to same origin in production; wildcard only for local dev
$allowedOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($allowedOrigin, ['http://localhost', 'http://127.0.0.1']) ? $allowedOrigin : ''));
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Require authentication for all API calls except login and ping
$publicActions = ['login', 'logout', 'ping'];
$requestAction = $_GET['action'] ?? (json_decode(file_get_contents('php://input'), true)['action'] ?? '');
if (!in_array($requestAction, $publicActions) && !isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.', 'timestamp' => date('c')]);
    exit;
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function respond(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['success' => true, 'data' => $data, 'timestamp' => date('c')]);
    exit;
}

function error(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message, 'timestamp' => date('c')]);
    exit;
}

function getInput(): array
{
    // Accept GET params or JSON POST body
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        return $_GET;
    }
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    return is_array($json) ? $json : $_POST;
}

// ── Mock data ─────────────────────────────────────────────────────────────────

function mockDonations(): array
{
    return [
        ['id' => 'DN12345', 'donor' => 'Alice Johnson',  'email' => 'alice.j@email.com',   'amount' => 500.00,  'date' => '2026-05-08', 'status' => 'approved', 'location' => 'New York, USA'],
        ['id' => 'DN12346', 'donor' => 'Bob Smith',       'email' => 'bob.s@email.com',     'amount' => 250.00,  'date' => '2026-05-09', 'status' => 'approved', 'location' => 'London, UK'],
        ['id' => 'DN12347', 'donor' => 'Carol White',     'email' => 'carol.w@email.com',   'amount' => 1000.00, 'date' => '2026-05-09', 'status' => 'approved', 'location' => 'Toronto, Canada'],
        ['id' => 'DN12348', 'donor' => 'David Brown',     'email' => 'david.b@email.com',   'amount' => 750.00,  'date' => '2026-05-10', 'status' => 'approved', 'location' => 'Sydney, Australia'],
        ['id' => 'DN12349', 'donor' => 'Emily Davis',     'email' => 'emily.d@email.com',   'amount' => 350.00,  'date' => '2026-05-10', 'status' => 'pending',  'location' => 'Berlin, Germany'],
        ['id' => 'DN12350', 'donor' => 'Frank Wilson',    'email' => 'frank.w@email.com',   'amount' => 600.00,  'date' => '2026-05-10', 'status' => 'pending',  'location' => 'Tokyo, Japan'],
        ['id' => 'DN12351', 'donor' => 'Emma Wilson',     'email' => 'emma.w@email.com',    'amount' => 300.00,  'date' => '2026-05-12', 'status' => 'pending',  'location' => 'Paris, France'],
        ['id' => 'DN12352', 'donor' => 'Frank Miller',    'email' => 'frank.m@email.com',   'amount' => 450.00,  'date' => '2026-05-12', 'status' => 'pending',  'location' => 'Rome, Italy'],
        ['id' => 'DN12353', 'donor' => 'Grace Lee',       'email' => 'grace.l@email.com',   'amount' => 200.00,  'date' => '2026-05-11', 'status' => 'pending',  'location' => 'Seoul, South Korea'],
        ['id' => 'DN12354', 'donor' => 'Henry Martinez',  'email' => 'henry.m@email.com',   'amount' => 850.00,  'date' => '2026-05-11', 'status' => 'pending',  'location' => 'Mexico City, Mexico'],
    ];
}

function mockUsers(): array
{
    return [
        ['id' => 'USR12345', 'name' => 'Alice Johnson', 'email' => 'alice.j@email.com',  'join_date' => '2026-05-08', 'status' => 'active',   'location' => 'New York, USA',     'donations' => 5],
        ['id' => 'USR12346', 'name' => 'Bob Smith',      'email' => 'bob.smith@email.com','join_date' => '2026-05-09', 'status' => 'active',   'location' => 'London, UK',        'donations' => 3],
        ['id' => 'USR12347', 'name' => 'Carol White',    'email' => 'carol.w@email.com',  'join_date' => '2026-05-09', 'status' => 'active',   'location' => 'Toronto, Canada',   'donations' => 8],
        ['id' => 'USR12348', 'name' => 'David Brown',    'email' => 'david.b@email.com',  'join_date' => '2026-05-10', 'status' => 'active',   'location' => 'Sydney, Australia', 'donations' => 4],
        ['id' => 'USR12349', 'name' => 'Emily Davis',    'email' => 'emily.d@email.com',  'join_date' => '2026-05-10', 'status' => 'inactive', 'location' => 'Berlin, Germany',   'donations' => 2],
        ['id' => 'USR12350', 'name' => 'Frank Wilson',   'email' => 'frank.w@email.com',  'join_date' => '2026-05-10', 'status' => 'active',   'location' => 'Tokyo, Japan',      'donations' => 6],
    ];
}

function mockShipments(): array
{
    return [
        ['id' => 'SH12345', 'origin' => 'New York, USA',    'destination' => 'Los Angeles, USA', 'courier' => 'FedEx Express',      'transport' => 'Truck',   'items' => 'Medical Supplies',    'status' => 'in_transit',      'eta' => '2026-05-12'],
        ['id' => 'SH12346', 'origin' => 'London, UK',        'destination' => 'Berlin, Germany',  'courier' => 'DHL International',  'transport' => 'Air',     'items' => 'Food Packages',       'status' => 'in_transit',      'eta' => '2026-05-11'],
        ['id' => 'SH12347', 'origin' => 'Tokyo, Japan',      'destination' => 'Sydney, Australia','courier' => 'UPS Freight',        'transport' => 'Sea',     'items' => 'Clothing & Textiles', 'status' => 'in_transit',      'eta' => '2026-05-20'],
        ['id' => 'SH12348', 'origin' => 'Toronto, Canada',   'destination' => 'Vancouver, Canada','courier' => 'Canada Post',        'transport' => 'Truck',   'items' => 'Books & Educational', 'status' => 'delivered',       'eta' => '2026-05-10'],
        ['id' => 'SH12349', 'origin' => 'Paris, France',     'destination' => 'Rome, Italy',      'courier' => 'Bike Couriers Co',  'transport' => 'Courier', 'items' => 'Documents',           'status' => 'in_transit',      'eta' => '2026-05-11'],
        ['id' => 'SH12350', 'origin' => 'Singapore',         'destination' => 'Mumbai, India',    'courier' => 'Maersk Shipping',   'transport' => 'Sea',     'items' => 'Electronics',         'status' => 'pending_pickup',  'eta' => '2026-05-18'],
    ];
}

function mockOrganizations(): array
{
    return [
        ['id' => 'ORG12345', 'name' => 'Global Health Foundation', 'category' => 'Healthcare',     'members' => 1250, 'joined' => '2026-05-08', 'status' => 'active', 'location' => 'New York, USA'],
        ['id' => 'ORG12346', 'name' => 'Education for All',         'category' => 'Education',      'members' => 850,  'joined' => '2026-05-09', 'status' => 'active', 'location' => 'London, UK'],
        ['id' => 'ORG12347', 'name' => 'Clean Water Initiative',    'category' => 'Environment',    'members' => 2100, 'joined' => '2026-05-09', 'status' => 'active', 'location' => 'Toronto, Canada'],
        ['id' => 'ORG12348', 'name' => "Children's Relief Fund",   'category' => 'Children',       'members' => 1750, 'joined' => '2026-05-10', 'status' => 'active', 'location' => 'Sydney, Australia'],
        ['id' => 'ORG12349', 'name' => 'Animal Rescue Society',     'category' => 'Animal Welfare', 'members' => 920,  'joined' => '2026-05-10', 'status' => 'active', 'location' => 'Berlin, Germany'],
        ['id' => 'ORG12350', 'name' => 'Tech for Good',             'category' => 'Technology',     'members' => 1340, 'joined' => '2026-05-10', 'status' => 'active', 'location' => 'Tokyo, Japan'],
    ];
}

function mockFraud(): array
{
    return [
        ['id' => 'DN12351', 'donor' => 'Mark Thompson',   'amount' => 5000.00, 'risk_score' => 87, 'risk_level' => 'high',   'location' => 'Lagos, Nigeria',        'reason' => 'Unusual amount, high-risk location', 'date' => '2026-05-10'],
        ['id' => 'DN12352', 'donor' => 'Jennifer Wang',   'amount' => 3500.00, 'risk_score' => 72, 'risk_level' => 'high',   'location' => 'Manila, Philippines',   'reason' => 'Multiple cards used',                'date' => '2026-05-10'],
        ['id' => 'DN12353', 'donor' => 'Robert Chen',     'amount' => 2800.00, 'risk_score' => 65, 'risk_level' => 'medium', 'location' => 'Jakarta, Indonesia',    'reason' => 'Velocity check failed',              'date' => '2026-05-09'],
        ['id' => 'DN12354', 'donor' => 'Sarah Mitchell',  'amount' => 4200.00, 'risk_score' => 78, 'risk_level' => 'high',   'location' => 'Cairo, Egypt',          'reason' => 'IP mismatch detected',               'date' => '2026-05-09'],
        ['id' => 'DN12355', 'donor' => 'Michael Brown',   'amount' => 1900.00, 'risk_score' => 58, 'risk_level' => 'medium', 'location' => 'Mumbai, India',         'reason' => 'Card verification failed',           'date' => '2026-05-08'],
        ['id' => 'DN12356', 'donor' => 'Emma Rodriguez',  'amount' => 3100.00, 'risk_score' => 69, 'risk_level' => 'medium', 'location' => 'São Paulo, Brazil',     'reason' => 'Proxy/VPN detected',                 'date' => '2026-05-08'],
    ];
}

function mockStats(): array
{
    return [
        'total_donations'    => 104000,
        'approved_donations' => 376,
        'pending_approvals'  => 3,
        'active_donors'      => 248,
        'total_users'        => 3847,
        'active_users'       => 2934,
        'total_orgs'         => 348,
        'active_shipments'   => 45,
        'delivered_month'    => 148,
        'flagged_transactions' => 87,
        'system_uptime'      => '99.9%',
        'api_response_ms'    => 124,
        'db_health'          => 'Optimal',
        'active_sessions'    => 1247,
    ];
}

function mockChartData(): array
{
    return [
        'labels'   => ['Jan','Feb','Mar','Apr','May','Jun'],
        'revenue'  => [5500, 8200, 11000, 14500, 18000, 20800],
        'count'    => [18, 32, 45, 52, 65, 72],
        'users'    => [200, 350, 480, 600, 720, 850],
        'fraud'    => [3, 8, 5, 12, 7, 15],
        'shipments'=> [12, 18, 9, 6, 15, 22],
    ];
}

// ── Router ────────────────────────────────────────────────────────────────────

$input  = getInput();
$action = $input['action'] ?? '';

switch ($action) {

    // ── Dashboard stats ──────────────────────────────────────────────────────
    case 'dashboard':
    case 'stats':
        try {
            $pdo = db();
            $realStats = [
                'total_donations'    => (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status='completed'")->fetchColumn(),
                'approved_donations' => (int)$pdo->query("SELECT COUNT(*) FROM donations WHERE status='completed'")->fetchColumn(),
                'pending_approvals'  => (int)$pdo->query("SELECT COUNT(*) FROM donations WHERE status='pending'")->fetchColumn(),
                'active_donors'      => (int)$pdo->query("SELECT COUNT(DISTINCT user_id) FROM donations WHERE status='completed'")->fetchColumn(),
                'total_users'        => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
                'active_users'       => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn(),
                'total_orgs'         => (int)$pdo->query("SELECT COUNT(*) FROM organizations")->fetchColumn(),
                'flagged_transactions'=> (int)$pdo->query("SELECT COUNT(*) FROM fraud_flags WHERE status='open'")->fetchColumn(),
                'db_health'          => 'Optimal',
            ];
            respond(['stats' => $realStats, 'chart_data' => mockChartData()]);
        } catch (Throwable $e) {
            respond(['stats' => mockStats(), 'chart_data' => mockChartData()]);
        }

    // ── Donations ────────────────────────────────────────────────────────────
    case 'donations':
    case 'list_donations':
        try {
            $pdo = db();
            $where = '1=1';
            $params = [];
            if (!empty($input['status'])) {
                $where .= ' AND d.status = ?'; $params[] = $input['status'];
            }
            if (!empty($input['search'])) {
                $where .= ' AND (CONCAT(u.first_name," ",u.last_name) LIKE ? OR d.donation_id LIKE ?)';
                $params[] = '%'.$input['search'].'%';
                $params[] = '%'.$input['search'].'%';
            }
            $rows = $pdo->prepare("
                SELECT d.donation_id AS id, CONCAT(u.first_name,' ',u.last_name) AS donor,
                       u.email, d.amount, d.donation_type, d.status, d.created_at AS date
                  FROM donations d JOIN users u ON u.user_id=d.user_id
                 WHERE $where ORDER BY d.created_at DESC LIMIT 100
            ");
            $rows->execute($params);
            $donations = $rows->fetchAll();
            $total = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status='completed'")->fetchColumn();
            respond([
                'donations' => $donations,
                'total'     => count($donations),
                'summary'   => ['total_amount' => $total],
            ]);
        } catch (Throwable $e) { error('DB error: '.$e->getMessage()); }

    // ── Single donation ──────────────────────────────────────────────────────
    case 'get_donation':
        $id = $input['id'] ?? '';
        if (!$id) error('Donation ID required');
        $found = array_filter(mockDonations(), fn($d) => $d['id'] === ltrim($id, '#'));
        $found = array_values($found);
        if (empty($found)) error('Donation not found', 404);
        respond(['donation' => $found[0]]);

    // ── Approve donation ─────────────────────────────────────────────────────
    case 'approve_donation':
        $id = $input['id'] ?? '';
        if (!$id) error('Donation ID required');
        respond([
            'id'      => $id,
            'status'  => 'approved',
            'message' => "Donation #{$id} has been approved.",
            'approved_at' => date('c'),
            'approved_by' => 'John Doe',
        ]);

    // ── Reject donation ──────────────────────────────────────────────────────
    case 'reject_donation':
        $id     = $input['id'] ?? '';
        $reason = $input['reason'] ?? 'No reason provided';
        if (!$id) error('Donation ID required');
        respond([
            'id'          => $id,
            'status'      => 'rejected',
            'reason'      => $reason,
            'message'     => "Donation #{$id} has been rejected.",
            'rejected_at' => date('c'),
            'rejected_by' => 'John Doe',
        ]);

    // ── Approvals list ───────────────────────────────────────────────────────
    case 'approvals':
    case 'list_approvals':
        $pending = array_filter(mockDonations(), fn($d) => $d['status'] === 'pending');
        respond([
            'pending'     => array_values($pending),
            'total'       => count($pending),
            'approved_today' => 12,
            'total_approved' => 376,
            'rejected'       => 52,
        ]);

    // ── Users ────────────────────────────────────────────────────────────────
    case 'users':
    case 'list_users':
        $users = mockUsers();
        if (!empty($input['status'])) {
            $users = array_filter($users, fn($u) => $u['status'] === $input['status']);
        }
        respond([
            'users'       => array_values($users),
            'total'       => 3847,
            'active'      => 2934,
            'new_signups' => 3,
            'active_today'=> 1248,
        ]);

    // ── Single user ──────────────────────────────────────────────────────────
    case 'get_user':
        $id = $input['id'] ?? '';
        if (!$id) error('User ID required');
        $found = array_filter(mockUsers(), fn($u) => $u['id'] === $id);
        $found = array_values($found);
        if (empty($found)) error('User not found', 404);
        respond(['user' => $found[0]]);

    // ── Update user status ───────────────────────────────────────────────────
    case 'update_user_status':
        $id     = $input['id'] ?? '';
        $status = $input['status'] ?? '';
        if (!$id || !$status) error('User ID and status required');
        if (!in_array($status, ['active','inactive','suspended'])) error('Invalid status');
        respond(['id' => $id, 'status' => $status, 'updated_at' => date('c')]);

    // ── Courier / Shipments ──────────────────────────────────────────────────
    case 'courier':
    case 'shipments':
        $shipments = mockShipments();
        if (!empty($input['status'])) {
            $shipments = array_filter($shipments, fn($s) => $s['status'] === $input['status']);
        }
        respond([
            'shipments'        => array_values($shipments),
            'active'           => 45,
            'delivered_month'  => 148,
            'pending_pickups'  => 3,
            'courier_partners' => 28,
        ]);

    // ── Schedule pickup ──────────────────────────────────────────────────────
    case 'schedule_pickup':
        $shipment_id = $input['shipment_id'] ?? '';
        $pickup_time = $input['pickup_time'] ?? date('c', strtotime('+2 hours'));
        if (!$shipment_id) error('Shipment ID required');
        respond([
            'shipment_id' => $shipment_id,
            'status'      => 'pickup_scheduled',
            'pickup_time' => $pickup_time,
            'message'     => "Pickup scheduled for shipment #{$shipment_id}",
        ]);

    // ── Analytics ────────────────────────────────────────────────────────────
    case 'analytics':
        respond([
            'stats' => [
                'total_revenue'  => 104000,
                'total_donors'   => 316,
                'avg_donation'   => 275,
                'retention_rate' => 68,
            ],
            'chart_data'  => mockChartData(),
            'top_donors'  => [
                ['name' => 'Alice Johnson', 'total' => 12500, 'count' => 25, 'avg' => 500,  'location' => 'New York, USA'],
                ['name' => 'Bob Smith',     'total' => 10200, 'count' => 41, 'avg' => 249,  'location' => 'London, UK'],
                ['name' => 'Carol White',   'total' =>  9800, 'count' => 12, 'avg' => 817,  'location' => 'Toronto, Canada'],
                ['name' => 'David Brown',   'total' =>  8750, 'count' => 35, 'avg' => 250,  'location' => 'Sydney, Australia'],
                ['name' => 'Emily Davis',   'total' =>  7350, 'count' => 21, 'avg' => 350,  'location' => 'Berlin, Germany'],
            ],
            'donor_types' => [
                ['type' => 'Annual',    'percentage' => 25],
                ['type' => 'Corporate', 'percentage' => 30],
                ['type' => 'Monthly',   'percentage' => 28],
                ['type' => 'One-time',  'percentage' => 17],
            ],
            'geographic' => [
                ['region' => 'North America', 'amount' => 58000],
                ['region' => 'Europe',        'amount' => 42000],
                ['region' => 'Asia',          'amount' => 31000],
                ['region' => 'Australia',     'amount' => 18000],
                ['region' => 'South America', 'amount' => 12000],
            ],
        ]);

    // ── Reports ──────────────────────────────────────────────────────────────
    case 'reports':
    case 'list_reports':
        respond([
            'reports' => [
                ['id' => 'RPT001', 'name' => 'May 2026 Monthly Report',    'type' => 'Monthly Donations', 'generated' => '2026-05-10', 'date_range' => 'May 1–31, 2026',       'status' => 'ready'],
                ['id' => 'RPT002', 'name' => 'Donor Activity Q2 2026',     'type' => 'Donor Activity',    'generated' => '2026-05-08', 'date_range' => 'Apr 1 – Jun 30, 2026', 'status' => 'ready'],
                ['id' => 'RPT003', 'name' => 'Geographic Distribution 2026','type' => 'Geographic',        'generated' => '2026-05-07', 'date_range' => 'Jan 1 – Dec 31, 2026', 'status' => 'ready'],
                ['id' => 'RPT004', 'name' => 'Custom Donor Report',         'type' => 'Custom',            'generated' => '2026-05-03', 'date_range' => 'Mar 1 – Apr 30, 2026', 'status' => 'processing'],
            ],
            'total'        => 24,
            'downloads'    => 156,
            'last_generated' => '2026-05-10',
            'this_month'   => 8,
        ]);

    // ── Generate report ──────────────────────────────────────────────────────
    case 'generate_report':
        $type       = $input['type'] ?? 'monthly';
        $date_range = $input['date_range'] ?? date('Y-m');
        respond([
            'report_id'  => 'RPT' . rand(100, 999),
            'type'       => $type,
            'date_range' => $date_range,
            'status'     => 'processing',
            'message'    => 'Report generation started. It will be ready in a few minutes.',
            'started_at' => date('c'),
        ]);

    // ── Download report ──────────────────────────────────────────────────────
    case 'download_report':
        $id = $input['id'] ?? '';
        if (!$id) error('Report ID required');
        respond([
            'id'          => $id,
            'download_url'=> "/reports/{$id}.pdf",
            'expires_at'  => date('c', strtotime('+1 hour')),
            'message'     => 'Download link generated successfully.',
        ]);

    // ── Fraud / Flagged ──────────────────────────────────────────────────────
    case 'fraud':
    case 'flagged':
        respond([
            'flagged'             => mockFraud(),
            'total_flagged'       => 87,
            'high_risk'           => 3,
            'verified_safe'       => 1245,
            'under_review'        => 6,
        ]);

    // ── Mark fraud reviewed ──────────────────────────────────────────────────
    case 'review_fraud':
        $id      = $input['id'] ?? '';
        $verdict = $input['verdict'] ?? 'safe'; // 'safe' | 'confirmed_fraud'
        if (!$id) error('Donation ID required');
        respond([
            'id'          => $id,
            'verdict'     => $verdict,
            'reviewed_at' => date('c'),
            'reviewed_by' => 'John Doe',
            'message'     => $verdict === 'confirmed_fraud'
                ? "Donation #{$id} marked as confirmed fraud."
                : "Donation #{$id} cleared as safe.",
        ]);

    // ── Reviews ──────────────────────────────────────────────────────────────
    case 'reviews':
        respond([
            'reviews' => [
                ['id' => 'RV12345', 'reviewer' => 'Alice Johnson', 'rating' => 5, 'review' => 'Excellent service! Very satisfied with my experience.', 'date' => '2026-05-08', 'status' => 'approved', 'location' => 'New York, USA'],
                ['id' => 'RV12346', 'reviewer' => 'Bob Smith',     'rating' => 4, 'review' => 'Good overall, but could improve response times.',       'date' => '2026-05-09', 'status' => 'approved', 'location' => 'London, UK'],
                ['id' => 'RV12347', 'reviewer' => 'Carol White',   'rating' => 5, 'review' => 'Outstanding! Would highly recommend to everyone.',        'date' => '2026-05-09', 'status' => 'approved', 'location' => 'Toronto, Canada'],
                ['id' => 'RV12348', 'reviewer' => 'David Brown',   'rating' => 4, 'review' => 'Very professional team and great results.',               'date' => '2026-05-10', 'status' => 'approved', 'location' => 'Sydney, Australia'],
                ['id' => 'RV12349', 'reviewer' => 'Emily Davis',   'rating' => 2, 'review' => 'Decent service, met basic expectations.',                 'date' => '2026-05-10', 'status' => 'approved', 'location' => 'Berlin, Germany'],
                ['id' => 'RV12350', 'reviewer' => 'Frank Wilson',  'rating' => 5, 'review' => 'Amazing experience from start to finish!',                'date' => '2026-05-10', 'status' => 'approved', 'location' => 'Tokyo, Japan'],
            ],
            'total'          => 1101,
            'avg_rating'     => 4.4,
            'pending_reviews'=> 3,
            'active_reviewers'=> 892,
        ]);

    // ── Moderate review ──────────────────────────────────────────────────────
    case 'moderate_review':
        $id     = $input['id'] ?? '';
        $action_v = $input['verdict'] ?? 'approve'; // 'approve' | 'reject'
        if (!$id) error('Review ID required');
        respond([
            'id'           => $id,
            'status'       => $action_v === 'approve' ? 'approved' : 'rejected',
            'moderated_at' => date('c'),
            'moderated_by' => 'John Doe',
        ]);

    // ── Organizations ────────────────────────────────────────────────────────
    case 'organizations':
        $orgs = mockOrganizations();
        respond([
            'organizations'    => $orgs,
            'total'            => 348,
            'active'           => 312,
            'pending_approvals'=> 3,
            'total_members'    => 8210,
        ]);

    // ── System health ────────────────────────────────────────────────────────
    case 'system':
    case 'system_health':
        respond([
            'uptime'          => '99.9%',
            'api_response_ms' => rand(110, 140),
            'db_health'       => 'Optimal',
            'active_sessions' => rand(1200, 1300),
            'server_time'     => date('c'),
            'php_version'     => PHP_VERSION,
            'memory_usage'    => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'services'        => [
                ['name' => 'Database',       'status' => 'healthy', 'latency_ms' => 8],
                ['name' => 'Cache (Redis)',   'status' => 'healthy', 'latency_ms' => 2],
                ['name' => 'Payment Gateway','status' => 'healthy', 'latency_ms' => 45],
                ['name' => 'Email Service',  'status' => 'healthy', 'latency_ms' => 120],
                ['name' => 'File Storage',   'status' => 'healthy', 'latency_ms' => 15],
            ],
            'recent_donations' => array_slice(mockDonations(), 0, 6),
        ]);

    // ── Login (placeholder) ──────────────────────────────────────────────────
    // ── Submit donation from user dashboard ─────────────────────────────────
    case 'donate':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('POST required', 405);
        // CSRF check
        $postToken = $_POST['csrf_token'] ?? '';
        if (!$postToken || $postToken !== ($_SESSION['csrf_token'] ?? '')) {
            error('Invalid security token', 403);
        }
        $uid = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
        if (!$uid) error('Not authenticated', 401);
        $campaignId  = (int)($input['campaign_id'] ?? 0);
        $donType     = in_array($input['donation_type'] ?? '', ['cash','item','service']) ? $input['donation_type'] : null;
        if (!$campaignId || !$donType) error('Missing required fields');
        try {
            $pdo = db();
            // Verify campaign is active
            $camp = $pdo->prepare("SELECT campaign_id FROM campaigns WHERE campaign_id=? AND status IN ('active','approved')");
            $camp->execute([$campaignId]);
            if (!$camp->fetch()) error('Campaign not found or not active', 404);
            $pdo->beginTransaction();
            $ins = $pdo->prepare("INSERT INTO donations (user_id, campaign_id, donation_type, status, created_at) VALUES (?,?,?,'pending',NOW())");
            $ins->execute([$uid, $campaignId, $donType]);
            $donationId = (int)$pdo->lastInsertId();
            if ($donType === 'cash') {
                $amount = filter_var($input['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
                if (!$amount || $amount < 10) { $pdo->rollBack(); error('Minimum cash donation is ₱10'); }
                $pdo->prepare("UPDATE donations SET amount=? WHERE donation_id=?")->execute([$amount, $donationId]);
            } elseif ($donType === 'item') {
                $pdo->prepare("INSERT INTO item_donations (donation_id, item_name, quantity, description) VALUES (?,?,?,?)")
                    ->execute([$donationId, $input['item_name'] ?? '', (int)($input['item_qty'] ?? 1), $input['item_desc'] ?? '']);
            }
            $pdo->commit();
            respond(['donation_id' => $donationId, 'message' => 'Donation submitted for review.']);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error('Failed to submit donation: ' . $e->getMessage());
        }

    case 'campaigns':
        // Serve active campaigns from the real database for the user dashboard
        try {
            $pdo = db();
            $rows = $pdo->query("
                SELECT c.campaign_id AS id, c.title, c.description, c.category,
                       c.target_amount AS target, COALESCE(c.current_amount,0) AS current,
                       o.org_name, o.verified
                  FROM campaigns c
                  JOIN organizations o ON o.org_id = c.org_id
                 WHERE c.status IN ('active','approved')
                 ORDER BY c.current_amount DESC
                 LIMIT 20
            ")->fetchAll();
            respond($rows);
        } catch (Throwable $e) {
            error('Failed to load campaigns: ' . $e->getMessage());
        }

    case 'login':
        // API login is handled by dashboard/login.php form — redirect there.
        error('Use /dashboard/login.php to authenticate', 405);

    // ── Logout ───────────────────────────────────────────────────────────────
    case 'logout':
        respond(['message' => 'Logged out successfully.']);

    // ── Health ping ──────────────────────────────────────────────────────────
    case 'ping':
        respond(['pong' => true, 'time' => date('c')]);

    // ── Unknown action ───────────────────────────────────────────────────────
    default:
        error(
            "Unknown action: '{$action}'. Available: dashboard, stats, donations, list_donations, " .
            "get_donation, approve_donation, reject_donation, approvals, users, list_users, get_user, " .
            "update_user_status, courier, shipments, schedule_pickup, analytics, reports, generate_report, " .
            "download_report, fraud, flagged, review_fraud, reviews, moderate_review, organizations, " .
            "system, system_health, login, logout, ping",
            404
        );
}
