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

require_once '../class/Database.php';

try {
    $payload = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    }

    $customerId = (int)($payload['customer_id'] ?? $_GET['customer_id'] ?? 0);

    if ($customerId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'A valid customer_id is required.',
        ]);
        exit();
    }

    $db = new Database();
    $conn = $db->connection();

    $sql = "SELECT
                cal.id           AS attendance_id,
                cal.customer_id,
                cal.time_in,
                cal.time_out,
                cal.status,
                cal.verified_by_name,
                cal.platform,
                cal.created_at
            FROM customer_attendance_logs cal
            WHERE cal.customer_id = :customerId
            ORDER BY cal.created_at DESC
            LIMIT 500";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':customerId', $customerId, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $records = array_map(function (array $row): array {
        return [
            'attendance_id' => (int)$row['attendance_id'],
            'customer_id'   => (int)$row['customer_id'],
            'time_in'       => $row['time_in'],
            'time_out'      => $row['time_out'],
            'status'        => strtoupper($row['status'] ?? ($row['time_out'] ? 'OUT' : 'IN')),
            'verified_by'   => $row['verified_by_name'] ?? '',
            'platform'      => $row['platform'] ?? null,
            'date'          => $row['time_in'] ?? $row['time_out'] ?? $row['created_at'],
        ];
    }, $rows);

    echo json_encode([
        'success' => true,
        'message' => 'Attendance logs retrieved successfully.',
        'data'    => $records,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load attendance logs.',
        'error'   => $e->getMessage(),
    ]);
}
