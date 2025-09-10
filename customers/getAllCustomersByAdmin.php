<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../class/Customer.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Only GET requests are accepted.'
    ]);
    exit();
}

try {
    // Create customer instance and get all customers with passwords (admin access)
    $customer = new Customer();
    
    // Get customers with passwords and add status information
    $customers = $customer->getAllWithPasswords(); // This will include passwords
    
    // Process customers to ensure status field is present
    if ($customers && is_array($customers)) {
        foreach ($customers as &$customerData) {
            // Ensure status field exists, default to 'active' if not set
            if (!isset($customerData['status']) || empty($customerData['status'])) {
                $customerData['status'] = 'active';
            }
        }
    }
    
    if ($customers !== false) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Customers retrieved successfully',
            'data' => $customers
        ], JSON_PRETTY_PRINT);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to retrieve customers'
        ], JSON_PRETTY_PRINT);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
