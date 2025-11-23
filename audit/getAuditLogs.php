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
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use GET.',
    ]);
    exit();
}

require_once '../class/AuditLog.php';

try {
    $filters = [
        'activity_type' => isset($_GET['activity_type']) ? trim((string)$_GET['activity_type']) : null,
        'activity_category' => isset($_GET['activity_category']) ? trim((string)$_GET['activity_category']) : null,
        'search' => isset($_GET['search']) ? trim((string)$_GET['search']) : null,
        'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : null,
    ];

    if (!empty($_GET['customer_id'])) {
        $filters['customer_id'] = (int)$_GET['customer_id'];
    }

    $auditLog = new AuditLog();
    $logs = $auditLog->getLogs($filters);

    echo json_encode([
        'success' => true,
        'data' => $logs,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to fetch audit logs',
        'error' => $e->getMessage(),
    ]);
}

?>

