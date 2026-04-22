<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../class/Membership.php';

try {
    $customerId = isset($_GET['customerId']) ? (int)$_GET['customerId'] : 0;
    if ($customerId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'customerId is required'
        ]);
        exit();
    }

    $membership = new Membership();
    $history = $membership->getHistoryByCustomerId($customerId);

    echo json_encode([
        'success' => true,
        'customer_id' => $customerId,
        'data' => $history,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>