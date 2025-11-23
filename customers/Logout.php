<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../class/AuditLog.php';
require_once '../class/Customer.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Only POST requests are accepted.'
    ]);
    exit();
}

try {
    // Get JSON input
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON input'
        ]);
        exit();
    }

    if (!is_array($input)) {
        $input = [];
    }
    
    // Optional: Check if customer_id is provided for logging purposes
    $customerId = isset($input['customer_id']) ? $input['customer_id'] : null;
    
    // In a stateless REST API, logout is primarily handled client-side
    // by removing stored tokens/session data from the client
    // This endpoint mainly serves to acknowledge the logout request
    // and can be used for logging/analytics purposes
    
    if ($customerId) {
        $customerName = null;
        try {
            $customerModel = new Customer();
            $customerData = $customerModel->getByIdSingle((int)$customerId);
            if ($customerData) {
                $customerName = trim(($customerData['first_name'] ?? '') . ' ' . ($customerData['last_name'] ?? ''));
            }
        } catch (Exception $e) {
            error_log('Customer lookup for logout audit failed: ' . $e->getMessage());
        }

        try {
            $auditLog = new AuditLog();
            $displayName = $customerName ?: "Customer #{$customerId}";
            $auditLog->record([
                'customer_id' => (int)$customerId,
                'customer_name' => $customerName,
                'activity_category' => 'auth',
                'activity_type' => 'logout',
                'activity_title' => 'Customer logged out',
                'description' => "{$displayName} logged out",
                'metadata' => [
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ],
                'actor_type' => 'customer',
                'actor_name' => $customerName,
            ]);
        } catch (Exception $e) {
            error_log('Audit log logout error: ' . $e->getMessage());
        }
    }
    
    // For session-based authentication, you would destroy the session here:
    // session_start();
    // session_destroy();
    
    // For token-based authentication, you might invalidate tokens here
    // (requires a token blacklist or similar mechanism)
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Logout successful',
        'data' => [
            'logged_out_at' => date('Y-m-d H:i:s'),
            'customer_id' => $customerId
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>
