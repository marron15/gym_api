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
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Customer ID is required'
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
    
    $id = (int)$input['id'];
    $status = $input['status'];
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid customer ID'
        ]);
        exit();
    }
    
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
    
    // Create customer instance and attempt status update
    $customer = new Customer();
    
    // First check if customer exists
    $existingCustomer = $customer->getById($id);
    if (!$existingCustomer) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Customer not found'
        ]);
        exit();
    }
    
    // Get current status for comparison
    $currentStatus = $existingCustomer[0]['status'] ?? 'active';
    
    // Update the customer status
    $result = $customer->updateCustomerStatus($id, $status);
    
    if ($result) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Customer status updated successfully',
            'data' => [
                'customer_id' => $id,
                'previous_status' => $currentStatus,
                'new_status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update customer status'
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
