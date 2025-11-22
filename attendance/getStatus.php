<?php

declare(strict_types=1);

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
if ($origin !== '*') {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

require_once '../class/Attendance.php';

try {
    $input = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
    }

    $customerId = isset($input['customer_id']) ? (int)$input['customer_id'] : (int)($_GET['customer_id'] ?? 0);

    if ($customerId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or missing customer_id.',
        ]);
        exit();
    }

    $attendance = new Attendance();
    $snapshot = $attendance->getSnapshot($customerId);

    echo json_encode([
        'success' => true,
        'snapshot' => $snapshot,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to fetch attendance status.',
        'error' => $e->getMessage(),
    ]);
}


