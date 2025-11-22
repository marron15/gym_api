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
    $payload = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    }

    $filters = [
        'date' => $payload['date'] ?? ($_GET['date'] ?? null),
        'search' => $payload['search'] ?? ($_GET['search'] ?? null),
    ];

    if (!empty($filters['date'])) {
        $parsedDate = strtotime($filters['date']);
        if ($parsedDate !== false) {
            $filters['date'] = date('Y-m-d', $parsedDate);
        } else {
            unset($filters['date']);
        }
    }

    $attendance = new Attendance();
    $records = $attendance->getLogs($filters);

    echo json_encode([
        'success' => true,
        'message' => 'Attendance logs retrieved successfully.',
        'data' => $records,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load attendance logs.',
        'error' => $e->getMessage(),
    ]);
}


