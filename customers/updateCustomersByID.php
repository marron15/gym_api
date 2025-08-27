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
    if (empty($input['id']) || empty($input['first_name']) || empty($input['last_name']) || empty($input['email'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID, first name, last name, and email are required'
        ]);
        exit();
    }
    
    $id = (int)$input['id'];
    
    // Validate email format
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email format'
        ]);
        exit();
    }
    
    // Prepare data for update (regular customer update - no password changes)
    $data = [
        'firstName' => trim($input['first_name']),
        'middleName' => isset($input['middle_name']) && !empty(trim($input['middle_name'])) ? trim($input['middle_name']) : null,
        'lastName' => trim($input['last_name']),
        'email' => strtolower(trim($input['email'])),
        'birthdate' => isset($input['birthdate']) && !empty(trim($input['birthdate'])) ? trim($input['birthdate']) : null,
        'phoneNumber' => isset($input['phone_number']) && !empty(trim($input['phone_number'])) ? trim($input['phone_number']) : null,
        'emergencyContactName' => isset($input['emergency_contact_name']) && !empty(trim($input['emergency_contact_name'])) ? trim($input['emergency_contact_name']) : null,
        'emergencyContactNumber' => isset($input['emergency_contact_number']) && !empty(trim($input['emergency_contact_number'])) ? trim($input['emergency_contact_number']) : null,
        'updatedBy' => 'customer_update',
        'updatedAt' => date('Y-m-d H:i:s'),
        'img' => isset($input['img']) && !empty(trim($input['img'])) ? trim($input['img']) : null
    ];
    
    // Note: This endpoint does NOT allow password updates for security
    // Use updateCustomerByAdmin.php for admin password management
    
    // Create customer instance and attempt update
    $customer = new Customer();
    $result = $customer->updateCustomersByID($id, $data);
    
    if ($result) {
        // Also update address if provided
        $addressUpdated = true;
        if (isset($input['address_details']) && is_array($input['address_details'])) {
            $addressData = $input['address_details'];
            $addressUpdated = $customer->updateCustomerAddress($id, $addressData);
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Customer updated successfully',
            'data' => [
                'customer_id' => $id,
                'updated_at' => $data['updatedAt'],
                'address_updated' => $addressUpdated
            ]
        ], JSON_PRETTY_PRINT);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update customer data'
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
