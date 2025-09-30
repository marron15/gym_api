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
    
    // Require only customer_id; allow partial updates for other fields
    if (empty($input['customer_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Customer ID is required'
        ]);
        exit();
    }
    
    $customerId = (int)$input['customer_id'];
    
    // If email provided, validate format
    if (isset($input['email']) && strlen(trim($input['email'])) > 0) {
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid email format'
            ]);
            exit();
        }
    }
    
    // Prepare data for update
    // Fetch current values to backfill
    $customer = new Customer();
    $currentRows = $customer->getById($customerId);
    $current = ($currentRows && count($currentRows) > 0) ? $currentRows[0] : [];

    function valOrNull($arr, $key) { return isset($arr[$key]) && strlen(trim($arr[$key])) > 0 ? trim($arr[$key]) : null; }

    $data = [
        'firstName' => valOrNull($input, 'first_name') ?? ($current['first_name'] ?? null),
        'middleName' => valOrNull($input, 'middle_name') ?? ($current['middle_name'] ?? null),
        'lastName' => valOrNull($input, 'last_name') ?? ($current['last_name'] ?? null),
        'email' => strtolower(valOrNull($input, 'email') ?? ($current['email'] ?? '')),
        'birthdate' => valOrNull($input, 'birthdate') ?? ($current['birthdate'] ?? null),
        'phoneNumber' => valOrNull($input, 'phone_number') ?? ($current['phone_number'] ?? null),
        'emergencyContactName' => valOrNull($input, 'emergency_contact_name') ?? ($current['emergency_contact_name'] ?? null),
        'emergencyContactNumber' => valOrNull($input, 'emergency_contact_number') ?? ($current['emergency_contact_number'] ?? null),
        'updatedBy' => 'customer_update',
        'updatedAt' => date('Y-m-d H:i:s'),
        'img' => valOrNull($input, 'img') ?? ($current['img'] ?? null),
        'status' => $current['status'] ?? 'active'
    ];

    // Enforce 11-digit phone number when provided
    if ($data['phoneNumber'] !== null) {
        if (!preg_match('/^\d{11}$/', $data['phoneNumber'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Contact number must be exactly 11 digits'
            ]);
            exit();
        }
    }
    
    // Handle password update if provided
    if (isset($input['password']) && !empty(trim($input['password']))) {
        $data['password'] = trim($input['password']);
    }
    
    // Create customer instance and attempt update
    $result = $customer->updateCustomersByID($customerId, $data);
    
    if ($result) {
        // Also update address if provided
        $addressUpdated = true;
        if (isset($input['address_details']) && is_array($input['address_details'])) {
            // Convert address array to string format for the updateCustomerAddress method
            $addressString = implode(', ', array_filter([
                $input['address_details']['street'] ?? '',
                $input['address_details']['city'] ?? '',
                $input['address_details']['state'] ?? '',
                $input['address_details']['postal_code'] ?? '',
                $input['address_details']['country'] ?? ''
            ]));
            $addressUpdated = $customer->updateCustomerAddress($customerId, $addressString);
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'customer_id' => $customerId,
                'updated_at' => $data['updatedAt'],
                'address_updated' => $addressUpdated
            ]
        ], JSON_PRETTY_PRINT);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update profile data'
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
