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

require_once '../class/AuditLog.php';

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON data',
        ]);
        exit();
    }

    $payload = [
        'customer_id' => isset($data['customer_id']) ? (int)$data['customer_id'] : null,
        'customer_name' => isset($data['customer_name']) ? trim((string)$data['customer_name']) : null,
        'admin_id' => isset($data['admin_id']) ? (int)$data['admin_id'] : null,
        'actor_type' => isset($data['actor_type']) ? trim((string)$data['actor_type']) : 'system',
        'actor_name' => isset($data['actor_name']) ? trim((string)$data['actor_name']) : null,
        'activity_category' => isset($data['activity_category']) ? trim((string)$data['activity_category']) : 'general',
        'activity_type' => isset($data['activity_type']) ? trim((string)$data['activity_type']) : 'general',
        'activity_title' => isset($data['activity_title']) ? trim((string)$data['activity_title']) : 'Activity',
        'description' => isset($data['description']) ? trim((string)$data['description']) : null,
        'metadata' => isset($data['metadata']) ? $data['metadata'] : null,
    ];

    // Decode metadata if it's a JSON string
    if (isset($payload['metadata']) && is_string($payload['metadata'])) {
        $decoded = json_decode($payload['metadata'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $payload['metadata'] = $decoded;
        }
    }

    $auditLog = new AuditLog();
    $success = $auditLog->record($payload);

    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Audit log created successfully',
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create audit log',
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to create audit log',
        'error' => $e->getMessage(),
    ]);
}

?>

