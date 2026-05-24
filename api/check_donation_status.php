<?php
// api/check_donation_status.php

require __DIR__ . '/../Config/db.php';
require __DIR__ . '/../Auth/middleware.php';
require __DIR__ . '/../services/PayMongoService.php';

requireRole('user');

header('Content-Type: application/json');

$donation_id = $_GET['id'] ?? 0;

if (!$donation_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid donation ID']);
    exit;
}
