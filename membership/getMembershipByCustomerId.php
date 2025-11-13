<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../class/Membership.php';

if (!function_exists('logMembershipDebug')) {
    function logMembershipDebug(string $message): void {
        $logFile = __DIR__ . '/../membership_debug.log';
        $timestamp = '[' . date('c') . '] ';
        @file_put_contents($logFile, $timestamp . $message . PHP_EOL, FILE_APPEND);
    }
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($input['customer_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Customer ID is required']);
            exit();
        }
        
        $customerId = $input['customer_id'];
        logMembershipDebug("Lookup membership for customer_id={$customerId}");
        $membership = new Membership();
        $result = $membership->getByCustomerId($customerId);
        
        if ($result) {
            logMembershipDebug('Membership found: ' . json_encode($result));
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
        } else {
            logMembershipDebug('No membership found for customer_id=' . $customerId);
            echo json_encode([
                'success' => false,
                'message' => 'No membership found for this customer'
            ]);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
