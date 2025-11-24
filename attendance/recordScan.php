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
require_once '../class/AuditLog.php';

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

    $status = strtoupper((string)($snapshot['status'] ?? ($snapshot['is_clocked_in'] ? 'IN' : 'OUT')));
    $activityType = $status === 'IN' ? 'attendance_in' : 'attendance_out';
    $activityTitle = $status === 'IN' ? 'Customer timed in' : 'Customer timed out';
    $customerName = $snapshot['customer_name'] ?? null;
    $actorName = $snapshot['verified_by'] ?? ($adminPayload['name'] ?? null);

    try {
        $auditLog = new AuditLog();
        // Time in/out is a customer activity (customer performs the action)
        // Admin verification is noted in description and metadata
        $adminId = $snapshot['verified_by_admin_id'] ?? ($adminPayload['adminId'] ?? null);
        $auditLog->record([
            'customer_id' => $snapshot['customer_id'] ?? $customerId,
            'customer_name' => $customerName,
            'admin_id' => $adminId,
            'actor_type' => 'customer', // Customer performs the time in/out action
            'actor_name' => $customerName, // Customer is the actor
            'activity_category' => 'attendance',
            'activity_type' => $activityType,
            'activity_title' => $activityTitle,
            'description' => sprintf(
                '%s %s%s',
                $customerName ? $customerName : "Customer #{$customerId}",
                $status === 'IN' ? 'timed in' : 'timed out',
                $actorName ? " (verified by {$actorName})" : ''
            ),
            'metadata' => [
                'attendance_id' => $snapshot['attendance_id'] ?? null,
                'time_in' => $snapshot['last_time_in'] ?? null,
                'time_out' => $snapshot['last_time_out'] ?? null,
                'platform' => $snapshot['platform'] ?? $platform,
                'verified_by_admin_id' => $adminId,
                'verified_by_admin_name' => $actorName,
            ],
        ]);
    } catch (Exception $e) {
        error_log('Audit log recordScan error: ' . $e->getMessage());
    }

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


