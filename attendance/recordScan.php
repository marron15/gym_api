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
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.',
    ]);
    exit();
}

require_once '../class/Attendance.php';

try {
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $customerId = isset($payload['customer_id']) ? (int)$payload['customer_id'] : 0;
    $adminPayload = $payload['admin_payload'] ?? null;

    if ($customerId <= 0 || empty($adminPayload)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'customer_id and admin_payload are required.',
        ]);
        exit();
    }

    if (is_string($adminPayload)) {
        $decodedPayload = json_decode($adminPayload, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedPayload)) {
            $adminPayload = $decodedPayload;
        }
    }

    if (!is_array($adminPayload)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid admin_payload supplied.',
        ]);
        exit();
    }

    $platform = $payload['platform'] ?? null;

    $attendance = new Attendance();
    $snapshot = $attendance->recordScan($customerId, $adminPayload, $platform);

    echo json_encode([
        'success' => true,
        'message' => 'Attendance updated successfully.',
        'snapshot' => $snapshot,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to record attendance.',
        'error' => $e->getMessage(),
    ]);
}


