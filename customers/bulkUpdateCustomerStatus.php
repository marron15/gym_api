<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON input'
        ]);
        exit();
    }
    
    // Check required fields
    if (empty($input['customer_ids']) || !is_array($input['customer_ids'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Customer IDs array is required'
        ]);
        exit();
    }
    
    if (empty($input['status'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Status is required'
        ]);
        exit();
    }
    
    $customerIds = array_map('intval', $input['customer_ids']);
    $status = $input['status'];
    
    // Validate status
    $validStatuses = ['active', 'inactive', 'archived'];
    if (!in_array($status, $validStatuses)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid status. Must be one of: active, inactive, archived'
        ]);
        exit();
    }
    
    // Validate customer IDs
    if (empty($customerIds) || count($customerIds) === 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'At least one customer ID is required'
        ]);
        exit();
    }
    
    // Remove any invalid IDs
    $customerIds = array_filter($customerIds, function($id) {
        return $id > 0;
    });
    
    if (empty($customerIds)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No valid customer IDs provided'
        ]);
        exit();
    }
    
    // Create customer instance and attempt bulk update
    $customer = new Customer();
    
    // Perform bulk status update
    $result = $customer->bulkUpdateCustomerStatus($customerIds, $status);
    
    if ($result) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Customer statuses updated successfully',
            'data' => [
                'updated_customer_ids' => $customerIds,
                'new_status' => $status,
                'total_updated' => count($customerIds),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update customer statuses'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>
